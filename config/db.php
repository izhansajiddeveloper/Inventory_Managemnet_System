<?php
// config/db.php - Single file for everything

// Database configuration
$server   = "localhost";
$username = "root";
$password = "";
$database = "inventory_management_system";

// Create MySQLi connection
$conn = mysqli_connect($server, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, "utf8mb4");

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base URL configuration (update this to your project path)
define('BASE_URL', 'http://localhost/Inventory_Managemnet_System/');
define('BASE_PATH', dirname(__DIR__));
