<?php

/**
 * Cancel Sale & Restore Stock
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include only database config
require_once __DIR__ . '/../../config/db.php';

// Simple authorization check
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

$user_role = $_SESSION['user_role'] ?? 0;
$allowed_roles = [1, 3]; // Admin and Staff (Sale Person)

if (!in_array($user_role, $allowed_roles)) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

// Helper functions
function set_flash_message($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function redirect($url)
{
    header("Location: " . BASE_URL . $url);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('error', 'Invalid request method.');
    redirect('admin/sales/index.php');
}

$sale_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$sale_id) {
    set_flash_message('error', 'Invalid sale ID.');
    redirect('admin/sales/index.php');
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // 1. Fetch Sale
    $sale_query = "SELECT * FROM sales WHERE id = $sale_id";
    $sale_result = mysqli_query($conn, $sale_query);

    if (!$sale_result || mysqli_num_rows($sale_result) == 0) {
        throw new Exception("Sale record not found.");
    }

    $sale = mysqli_fetch_assoc($sale_result);

    if ($sale['status'] === 'cancelled') {
        throw new Exception("Sale is already cancelled.");
    }

    // 2. Fetch Sale Items to restore stock
    $items_query = "SELECT * FROM sale_items WHERE sale_id = $sale_id";
    $items_result = mysqli_query($conn, $items_query);

    $created_by = $_SESSION['user_id'] ?? 1;

    while ($item = mysqli_fetch_assoc($items_result)) {
        $pid = $item['product_id'];
        $qty = $item['quantity'];

        // Restore stock
        $update_stock = "UPDATE product_stock SET quantity = quantity + $qty WHERE product_id = $pid";
        mysqli_query($conn, $update_stock);

        // Record IN transaction
        $note = "Returned from Cancelled Sale #SALE-" . str_pad($sale_id, 5, '0', STR_PAD_LEFT);
        $trans_query = "INSERT INTO product_transactions (product_id, quantity, type, reference_type, reference_id, created_by, note)
                       VALUES ($pid, $qty, 'IN', 'sale_return', $sale_id, $created_by, '$note')";
        mysqli_query($conn, $trans_query);
    }

    // 3. Update Sale Status
    mysqli_query($conn, "UPDATE sales SET status = 'cancelled' WHERE id = $sale_id");

    // 4. Update Linked Order (if any)
    if ($sale['order_id']) {
        mysqli_query($conn, "UPDATE orders SET status = 'cancelled' WHERE id = " . $sale['order_id']);
        // Also update any payments linked to the order/sale to 'unpaid' status? 
        // Typically cancellation means the money is "lost" or "refunded" manually. 
        // We'll leave payment records as is but the status reflects cancellation.
    }

    mysqli_commit($conn);
    set_flash_message('success', "Sale #SALE-$sale_id and linked order have been cancelled. Stock has been restored.");
    redirect('admin/sales/view.php?id=' . $sale_id);
} catch (Exception $e) {
    mysqli_rollback($conn);
    set_flash_message('error', "Error cancelling sale: " . $e->getMessage());
    redirect('admin/sales/view.php?id=' . $sale_id);
}
