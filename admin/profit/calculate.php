<?php
/**
 * Profit Calculation Engine
 * Calculates and stores profits for Orders and Products
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/functions.php';

/**
 * Calculate Profit for a specific Order
 * @param PDO $pdo
 * @param int $order_id
 * @return array|bool Result summary or false on failure
 */
function calculate_order_profit($pdo, $order_id) {
    try {
        // 1. Fetch Order and items
        $order_stmt = $pdo->prepare("SELECT id, final_amount, discount FROM orders WHERE id = ?");
        $order_stmt->execute([$order_id]);
        $order = $order_stmt->fetch();

        if (!$order) return false;

        $items_stmt = $pdo->prepare("
            SELECT oi.product_id, oi.quantity, p.cost_price 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $items_stmt->execute([$order_id]);
        $items = $items_stmt->fetchAll();

        // 2. Calculate Total Cost Price
        $total_cost = 0;
        foreach ($items as $item) {
            $total_cost += ($item['cost_price'] * $item['quantity']);
        }

        $selling_price = $order['final_amount'];
        $discount      = $order['discount'];
        $profit_amount = $selling_price - $total_cost;

        // 3. Insert or Update Profits Table
        $check_stmt = $pdo->prepare("SELECT id FROM profits WHERE reference_type = 'order' AND reference_id = ?");
        $check_stmt->execute([$order_id]);
        $existing = $check_stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE profits 
                SET cost_price = ?, selling_price = ?, discount = ?, profit_amount = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$total_cost, $selling_price, $discount, $profit_amount, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO profits (reference_type, reference_id, cost_price, selling_price, discount, profit_amount) 
                VALUES ('order', ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$order_id, $total_cost, $selling_price, $discount, $profit_amount]);
        }

        // 4. Automatically Sync product-wise accumulated profits for items in this order
        foreach ($items as $item) {
            sync_product_profit($pdo, $item['product_id']);
        }

        return true;

    } catch (PDOException $e) {
        error_log("Profit Calc Error (Order #$order_id): " . $e->getMessage());
        return false;
    }
}

function sync_product_profit($pdo, $product_id) {
    try {
        // Fetch product base cost
        $p_stmt = $pdo->prepare("SELECT cost_price FROM products WHERE id = ?");
        $p_stmt->execute([$product_id]);
        $p = $p_stmt->fetch();
        if (!$p) return false;

        // Fetch accumulated sales data directly from order_items for COMPLETED orders
        // This takes the ACTUAL selling price within each order
        $sales_stmt = $pdo->prepare("
            SELECT SUM(oi.quantity) as total_sold,
                   SUM(oi.quantity * oi.price) as total_revenue_acc,
                   SUM(oi.quantity * ?) as total_cost_acc
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE oi.product_id = ? AND o.status = 'completed'
        ");
        $sales_stmt->execute([$p['cost_price'], $product_id]);
        $row = $sales_stmt->fetch();

        $total_sold        = (float)($row['total_sold'] ?? 0);
        $total_revenue_acc = (float)($row['total_revenue_acc'] ?? 0);
        $total_cost_acc    = (float)($row['total_cost_acc'] ?? 0);
        $total_profit_acc  = $total_revenue_acc - $total_cost_acc;

        $check_stmt = $pdo->prepare("SELECT id FROM profits WHERE reference_type = 'product' AND reference_id = ?");
        $check_stmt->execute([$product_id]);
        $existing = $check_stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE profits 
                SET cost_price = ?, selling_price = ?, profit_amount = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$total_cost_acc, $total_revenue_acc, $total_profit_acc, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO profits (reference_type, reference_id, cost_price, selling_price, profit_amount) 
                VALUES ('product', ?, ?, ?, ?)
            ");
            $stmt->execute([$product_id, $total_cost_acc, $total_revenue_acc, $total_profit_acc]);
        }

        return true;
    } catch (PDOException $e) {
        error_log("Product Profit Accumulation Error: " . $e->getMessage());
        return false;
    }
}

// Logic for manual trigger if via URL
if (isset($_GET['action'])) {
    require_once __DIR__ . '/../../core/session.php';
    require_once __DIR__ . '/../../core/auth.php';
    authorize([ROLE_ADMIN]);

    $action = $_GET['action'];
    if ($action === 'calculate_order' && isset($_GET['order_id'])) {
        $res = calculate_order_profit($pdo, (int)$_GET['order_id']);
        if ($res) {
            set_flash_message('success', "Profit recalculated for Order #".$_GET['order_id']);
        } else {
            set_flash_message('error', "Failed to calculate profit.");
        }
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    if ($action === 'sync_all_products') {
        $prods = $pdo->query("SELECT id FROM products WHERE status = 'active'")->fetchAll();
        $count = 0;
        foreach ($prods as $p) {
            if (sync_product_profit($pdo, $p['id'])) $count++;
        }
        set_flash_message('success', "Synced profit metrics for $count products.");
        header("Location: index.php");
        exit;
    }

    if ($action === 'sync_all_order_profits') {
        $orders = $pdo->query("SELECT id FROM orders WHERE status = 'completed'")->fetchAll();
        $count = 0;
        foreach ($orders as $o) {
            if (calculate_order_profit($pdo, $o['id'])) $count++;
        }
        set_flash_message('success', "Processed historical margins for $count orders.");
        header("Location: index.php");
        exit;
    }
}
