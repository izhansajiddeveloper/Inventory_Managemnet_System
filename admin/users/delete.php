<?php

/**
 * User Management - Delete User (Hard Delete)
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include only database config
require_once __DIR__ . '/../../config/db.php';

// Simple authorization check
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

$user_role = $_SESSION['user_role'] ?? 0;
$allowed_roles = [1]; // Admin only

if (!in_array($user_role, $allowed_roles)) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

// Helper functions
function set_flash_message($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function redirect($url)
{
    header("Location: " . BASE_URL . $url);
    exit();
}

// Check for ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('danger', 'Invalid user ID.');
    redirect('admin/users/index.php');
}

$user_id = (int)$_GET['id'];

// Prevent self-deletion
if ($user_id == $_SESSION['user_id']) {
    set_flash_message('danger', 'You cannot delete your own account.');
    redirect('admin/users/index.php');
}

// Check if user exists
$user_query = "SELECT name FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_query);

if ($user_result && mysqli_num_rows($user_result) > 0) {
    $user = mysqli_fetch_assoc($user_result);

    // Option 1: Hard Delete
    $delete_query = "DELETE FROM users WHERE id = $user_id";
    if (mysqli_query($conn, $delete_query)) {
        set_flash_message('success', 'User [ ' . htmlspecialchars($user['name']) . ' ] has been permanently removed.');
    } else {
        // Check for foreign key constraint error (MySQL error code 1451)
        if (mysqli_errno($conn) == 1451) {
            set_flash_message('danger', 'Cannot delete user: This user has active records (orders/transactions) associated with them. Consider setting their status to "Inactive" instead.');
        } else {
            set_flash_message('danger', 'Failed to delete user: ' . mysqli_error($conn));
        }
    }
} else {
    set_flash_message('warning', 'User not found or already removed.');
}

redirect('admin/users/index.php');
