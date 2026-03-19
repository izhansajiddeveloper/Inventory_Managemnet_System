<?php
/**
 * Application Configuration
 */

// Check if constants are already defined before defining them
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Inventory Pro');
}

if (!defined('BASE_URL')) {
    // Base URL (Adjust this if your project is in a different folder)
    define('BASE_URL', 'http://localhost/inventory-management-system/');
}

// Set Default Timezone
date_default_timezone_set('Asia/Karachi'); // Adjust based on user's preference or system requirement

// Error Reporting (Internal settings)
error_reporting(E_ALL);
ini_set('display_errors', 1);