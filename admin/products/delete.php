<?php

/**
 * Product Management - Delete Product
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
$allowed_roles = [1]; // Admin only

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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('error', 'Invalid product ID.');
    redirect('admin/products/index.php');
}

$product_id = (int)$_GET['id'];

// Check if product exists
$product_query = "SELECT name FROM products WHERE id = $product_id";
$product_result = mysqli_query($conn, $product_query);

if (!$product_result || mysqli_num_rows($product_result) == 0) {
    set_flash_message('error', 'Product not found.');
    redirect('admin/products/index.php');
}

$product = mysqli_fetch_assoc($product_result);

// Delete the product
$delete_query = "DELETE FROM products WHERE id = $product_id";

if (mysqli_query($conn, $delete_query)) {
    set_flash_message('success', 'Product "' . $product['name'] . '" deleted successfully.');
} else {
    // Check for foreign key constraint error (MySQL error code 1451)
    if (mysqli_errno($conn) == 1451) {
        set_flash_message('error', 'This product cannot be deleted because it is linked to other records (inventory or orders).');
    } else {
        set_flash_message('error', 'Database error: ' . mysqli_error($conn));
    }
}

redirect('admin/products/index.php');
