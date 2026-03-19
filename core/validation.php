<?php
/**
 * Validation Helper Functions
 */

/**
 * Check if a string is empty
 * 
 * @param string $data
 * @return bool
 */
function is_empty($data) {
    return empty(trim($data));
}

/**
 * Check if an email is valid
 * 
 * @param string $email
 * @return bool
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Check if a phone number is valid (Simple check)
 * 
 * @param string $phone
 * @return bool
 */
function is_valid_phone($phone) {
    // Basic regex for digits, spaces, and + sign
    return preg_match('/^[0-9\-\+ ]+$/', $phone);
}
