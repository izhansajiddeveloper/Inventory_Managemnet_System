<?php
/**
 * Product Management - Delete Product
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';

// Access Control
authorize([ROLE_ADMIN]);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('error', 'Invalid product ID.');
    redirect('admin/products/index.php');
}

$product_id = (int)$_GET['id'];

try {
    // Check if product exists
    $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        set_flash_message('error', 'Product not found.');
        redirect('admin/products/index.php');
    }

    // Delete the product
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    if ($stmt->execute([$product_id])) {
        set_flash_message('success', 'Product "' . $product['name'] . '" deleted successfully.');
    } else {
        set_flash_message('error', 'Failed to delete product. Please try again.');
    }
} catch (PDOException $e) {
    // If there's a foreign key constraint (e.g. if the product is in inventory or orders)
    if ($e->getCode() == '23000') {
        set_flash_message('error', 'This product cannot be deleted because it is linked to other records (inventory or orders).');
    } else {
        set_flash_message('error', 'Database error: ' . $e->getMessage());
    }
}

redirect('admin/products/index.php');
