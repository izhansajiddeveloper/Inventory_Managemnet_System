<?php
require_once __DIR__ . '/config/db.php';
try {
    $tables = ['products', 'product_stock', 'product_transactions', 'distributors', 'purchases', 'purchase_items', 'orders', 'order_items'];
    foreach ($tables as $table) {
        echo "\n--- $table ---\n";
        $columns = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "{$column['Field']} - {$column['Type']}\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
