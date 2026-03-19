<?php
require_once __DIR__ . '/config/db.php';
try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    print_r($tables);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
