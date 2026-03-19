<?php

/**
 * Database Connection
 */

// Check if constants are defined, if not include them
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/constants.php';
}

try {
    // Create PDO connection
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET . ";port=" . DB_PORT;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // Test the connection
    $pdo->query("SELECT 1");
} catch (PDOException $e) {
    // Log error and show user-friendly message
    error_log("Database Connection Error: " . $e->getMessage());

    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        die("Database Connection Failed: " . $e->getMessage());
    } else {
        die("Unable to connect to the database. Please try again later.");
    }
}

// Function to get database connection
function getDB()
{
    global $pdo;
    return $pdo;
}
