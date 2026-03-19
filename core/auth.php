<?php
/**
 * Authentication Helper Functions
 */

require_once __DIR__ . '/../config/constants.php';

/**
 * Check if the user is logged in
 * 
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect to login if not authenticated
 */
function check_login() {
    if (!is_logged_in()) {
        header("Location: " . BASE_URL . "auth/login.php");
        exit();
    }
}

/**
 * Check if user has a specific role
 * 
 * @param int $role_id
 * @return bool
 */
function has_role($role_id) {
    return (isset($_SESSION['user_role']) && $_SESSION['user_role'] == $role_id);
}

/**
 * Restrict access to specific roles
 * 
 * @param array $allowed_roles
 */
function authorize($allowed_roles = []) {
    check_login();
    
    if (!in_array($_SESSION['user_role'], $allowed_roles)) {
        // Redirect to their respective dashboard or 403 page
        set_flash_message('danger', 'You do not have permission to access this page.');
        
        switch ($_SESSION['user_role']) {
            case ROLE_ADMIN:
                header("Location: " . BASE_URL . "admin/dashboard.php");
                break;
            case ROLE_DISTRIBUTOR:
                header("Location: " . BASE_URL . "distributor/dashboard.php");
                break;
            case ROLE_STAFF:
                header("Location: " . BASE_URL . "staff/dashboard.php");
                break;
            default:
                header("Location: " . BASE_URL . "index.php");
        }
        exit();
    }
}
