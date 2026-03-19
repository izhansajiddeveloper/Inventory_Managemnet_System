<?php
/**
 * User Management - Delete User (Hard Delete)
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';

// Access Control
authorize([ROLE_ADMIN]);

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

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user) {
        // Option 1: Hard Delete
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            set_flash_message('success', 'User [ ' . htmlspecialchars($user['name']) . ' ] has been permanently removed.');
        } else {
            set_flash_message('danger', 'Failed to delete user. They might have related records in the system.');
        }
    } else {
        set_flash_message('warning', 'User not found or already removed.');
    }
} catch (PDOException $e) {
    // Handle foreign key constraint errors
    set_flash_message('danger', 'Cannot delete user: This user has active records (orders/transactions) associated with them. Consider setting their status to "Inactive" instead.');
}

redirect('admin/users/index.php');
?>
