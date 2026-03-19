<?php

/**
 * Profit Calculation Engine
 * Calculates and stores profits for Orders and Products
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include only database config
require_once __DIR__ . '/../../config/db.php';

// Helper functions
function set_flash_message($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Calculate Profit for a specific Order
 * @param mysqli $conn
 * @param int $order_id
 * @return bool Result on success or false on failure
 */
function calculate_order_profit($conn, $order_id)
{
    try {
        // 1. Fetch Order
        $order_query = "SELECT id, final_amount, discount FROM orders WHERE id = $order_id";
        $order_result = mysqli_query($conn, $order_query);
        if (!$order_result || mysqli_num_rows($order_result) == 0) return false;
        $order = mysqli_fetch_assoc($order_result);

        // 2. Fetch items with cost prices
        $items_query = "
            SELECT oi.product_id, oi.quantity, p.cost_price 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = $order_id";
        $items_result = mysqli_query($conn, $items_query);

        $total_cost = 0;
        $items = [];
        while ($item = mysqli_fetch_assoc($items_result)) {
            $total_cost += ($item['cost_price'] * $item['quantity']);
            $items[] = $item;
        }

        $selling_price = $order['final_amount'];
        $discount      = $order['discount'];
        $profit_amount = $selling_price - $total_cost;

        // 3. Insert or Update Profits Table
        $check_query = "SELECT id FROM profits WHERE reference_type = 'order' AND reference_id = $order_id";
        $check_result = mysqli_query($conn, $check_query);
        $existing = mysqli_fetch_assoc($check_result);

        if ($existing) {
            $update_query = "UPDATE profits 
                            SET cost_price = $total_cost, selling_price = $selling_price, discount = $discount, profit_amount = $profit_amount, updated_at = NOW() 
                            WHERE id = {$existing['id']}";
            mysqli_query($conn, $update_query);
        } else {
            $insert_query = "INSERT INTO profits (reference_type, reference_id, cost_price, selling_price, discount, profit_amount) 
                            VALUES ('order', $order_id, $total_cost, $selling_price, $discount, $profit_amount)";
            mysqli_query($conn, $insert_query);
        }

        // 4. Automatically Sync product-wise accumulated profits for items in this order
        foreach ($items as $item) {
            sync_product_profit($conn, $item['product_id']);
        }

        return true;
    } catch (Exception $e) {
        error_log("Profit Calc Error (Order #$order_id): " . $e->getMessage());
        return false;
    }
}

function sync_product_profit($conn, $product_id)
{
    try {
        // Fetch product base cost
        $p_query = "SELECT cost_price FROM products WHERE id = $product_id";
        $p_result = mysqli_query($conn, $p_query);
        if (!$p_result || mysqli_num_rows($p_result) == 0) return false;
        $p = mysqli_fetch_assoc($p_result);

        // Fetch accumulated sales data directly from order_items for COMPLETED orders
        $sales_query = "
            SELECT SUM(oi.quantity) as total_sold,
                   SUM(oi.quantity * oi.price) as total_revenue_acc,
                   SUM(oi.quantity * {$p['cost_price']}) as total_cost_acc
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE oi.product_id = $product_id AND o.status = 'completed'";

        $sales_result = mysqli_query($conn, $sales_query);
        $row = mysqli_fetch_assoc($sales_result);

        $total_sold        = (float)($row['total_sold'] ?? 0);
        $total_revenue_acc = (float)($row['total_revenue_acc'] ?? 0);
        $total_cost_acc    = (float)($row['total_cost_acc'] ?? 0);
        $total_profit_acc  = $total_revenue_acc - $total_cost_acc;

        $check_query = "SELECT id FROM profits WHERE reference_type = 'product' AND reference_id = $product_id";
        $check_result = mysqli_query($conn, $check_query);
        $existing = mysqli_fetch_assoc($check_result);

        if ($existing) {
            $update_query = "UPDATE profits 
                            SET cost_price = $total_cost_acc, selling_price = $total_revenue_acc, profit_amount = $total_profit_acc, updated_at = NOW() 
                            WHERE id = {$existing['id']}";
            mysqli_query($conn, $update_query);
        } else {
            $insert_query = "INSERT INTO profits (reference_type, reference_id, cost_price, selling_price, profit_amount) 
                            VALUES ('product', $product_id, $total_cost_acc, $total_revenue_acc, $total_profit_acc)";
            mysqli_query($conn, $insert_query);
        }

        return true;
    } catch (Exception $e) {
        error_log("Product Profit Accumulation Error: " . $e->getMessage());
        return false;
    }
}

// Logic for manual trigger if via URL
if (isset($_GET['action'])) {
    // Simple authorization check
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "auth/login.php");
        exit();
    }

    $user_role = $_SESSION['user_role'] ?? 0;
    $allowed_roles = [1]; // Admin only

    if (!in_array($user_role, $allowed_roles)) {
        header("Location: " . BASE_URL . "index.php");
        exit();
    }

    $action = $_GET['action'];
    if ($action === 'calculate_order' && isset($_GET['order_id'])) {
        $res = calculate_order_profit($conn, (int)$_GET['order_id']);
        if ($res) {
            set_flash_message('success', "Profit recalculated for Order #" . $_GET['order_id']);
        } else {
            set_flash_message('error', "Failed to calculate profit.");
        }
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    if ($action === 'sync_all_products') {
        $prods_query = "SELECT id FROM products WHERE status = 'active'";
        $prods_result = mysqli_query($conn, $prods_query);
        $count = 0;
        while ($p = mysqli_fetch_assoc($prods_result)) {
            if (sync_product_profit($conn, $p['id'])) $count++;
        }
        set_flash_message('success', "Synced profit metrics for $count products.");
        header("Location: index.php");
        exit;
    }

    if ($action === 'sync_all_order_profits') {
        $orders_query = "SELECT id FROM orders WHERE status = 'completed'";
        $orders_result = mysqli_query($conn, $orders_query);
        $count = 0;
        while ($o = mysqli_fetch_assoc($orders_result)) {
            if (calculate_order_profit($conn, $o['id'])) $count++;
        }
        set_flash_message('success', "Processed historical margins for $count orders.");
        header("Location: index.php");
        exit;
    }
}
