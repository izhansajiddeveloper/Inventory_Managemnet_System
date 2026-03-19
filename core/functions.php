<?php
/**
 * Global Utility Functions
 */

/**
 * Redirect to a specific URL
 * 
 * @param string $path
 */
function redirect($path) {
    header("Location: " . BASE_URL . $path);
    exit();
}

/**
 * Sanitize input data
 * 
 * @param string $data
 * @return string
 */
function sanitize($data) {
    global $pdo;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Format currency
 * 
 * @param float $amount
 * @return string
 */
function format_price($amount) {
    return "Rs. " . number_format($amount, 2);
}

/**
 * Format date
 * 
 * @param string $date
 * @return string
 */
function format_date($date) {
    return date('d-M-Y h:i A', strtotime($date));
}

/**
 * Debugging helper (dd - Dump and Die)
 * 
 * @param mixed $data
 */
function dd($data) {
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    die();
}
