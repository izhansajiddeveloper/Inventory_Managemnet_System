<?php
require_once __DIR__ . '/config/db.php';

try {
    $products = $pdo->query("SELECT count(*) FROM products")->fetchColumn();
    $stock = $pdo->query("SELECT count(*) FROM product_stock")->fetchColumn();
    $transactions = $pdo->query("SELECT count(*) FROM product_transactions")->fetchColumn();
    $purchases = $pdo->query("SELECT count(*) FROM purchases")->fetchColumn();
    $orders = $pdo->query("SELECT count(*) FROM orders")->fetchColumn();

    echo "Products: $products\n";
    echo "Stock entries: $stock\n";
    echo "Transactions: $transactions\n";
    echo "Purchases: $purchases\n";
    echo "Orders: $orders\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
