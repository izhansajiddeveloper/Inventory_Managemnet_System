<?php

/**
 * User Management - List Users
 * Enhanced directory view with role-specific filtering
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

function display_flash_message()
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        $alertClass = $flash['type'] == 'success' ? 'alert-success' : 'alert-danger';
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">';
        echo '<i class="fa-solid ' . ($flash['type'] == 'success' ? 'fa-circle-check' : 'fa-circle-exclamation') . ' me-2"></i>';
        echo $flash['message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        unset($_SESSION['flash']);
    }
}

// Define role constants
define('ROLE_ADMIN', 1);
define('ROLE_DISTRIBUTOR', 2);
define('ROLE_STAFF', 3);
define('ROLE_CUSTOMER', 4);

// Get role filter and determine page context
$role_filter = isset($_GET['role']) ? (int)$_GET['role'] : 0;

// Set page title based on role
$page_titles = [
    ROLE_DISTRIBUTOR => 'Distributor Network',
    ROLE_STAFF => 'Staff Directory',
    ROLE_CUSTOMER => 'Customer Registry',
    0 => 'User Directory'
];
$page_title = $page_titles[$role_filter] ?? 'User Directory';

// Set icon based on role
$page_icons = [
    ROLE_DISTRIBUTOR => 'fa-truck-fast',
    ROLE_STAFF => 'fa-user-tie',
    ROLE_CUSTOMER => 'fa-user',
    0 => 'fa-users'
];
$page_icon = $page_icons[$role_filter] ?? 'fa-users';

// Set description based on role
$page_descriptions = [
    ROLE_DISTRIBUTOR => 'Manage your distribution partners and their access privileges',
    ROLE_STAFF => 'Oversee internal team members and their system permissions',
    ROLE_CUSTOMER => 'View and manage customer accounts and profiles',
    0 => 'System-wide access control and personnel management'
];
$page_description = $page_descriptions[$role_filter] ?? 'User management and access control';

// Search and Filter Logic
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

$query = "SELECT u.*, r.name as role_name 
          FROM users u 
          LEFT JOIN roles r ON u.role_id = r.id 
          WHERE 1=1";

// If a specific role is selected, filter by it
if ($role_filter > 0) {
    $query .= " AND u.role_id = $role_filter";
}

if (!empty($search)) {
    $query .= " AND (u.name LIKE '%$search%' OR u.email LIKE '%$search%' OR u.phone LIKE '%$search%')";
}

if (!empty($status_filter)) {
    $query .= " AND u.status = '$status_filter'";
}

$query .= " ORDER BY u.created_at DESC";

$users = [];
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
}

// Get counts for different roles (for quick stats)
$role_counts_query = "SELECT role_id, COUNT(*) as count 
                     FROM users 
                     WHERE status = 'active' 
                     GROUP BY role_id";
$role_counts_result = mysqli_query($conn, $role_counts_query);
$role_counts = [];
if ($role_counts_result) {
    while ($row = mysqli_fetch_assoc($role_counts_result)) {
        $role_counts[$row['role_id']] = $row['count'];
    }
}

// Fetch roles for reference
$roles_result = mysqli_query($conn, "SELECT * FROM roles ORDER BY id ASC");
$roles = [];
if ($roles_result) {
    while ($row = mysqli_fetch_assoc($roles_result)) {
        $roles[] = $row;
    }
}

// Get role name for display
$current_role_name = '';
if ($role_filter > 0) {
    foreach ($roles as $role) {
        if ($role['id'] == $role_filter) {
            $current_role_name = $role['name'];
            break;
        }
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
    :root {
        --card-shadow: 0 10px 40px -15px rgba(0, 0, 0, 0.1);
        --hover-shadow: 0 20px 40px -15px rgba(37, 99, 235, 0.15);
        --border-light: 1px solid #f1f5f9;
    }

    .page-header {
        padding: 1.5rem 1.5rem 1rem 1.5rem;
        background: white;
        border-radius: 20px;
        border: var(--border-light);
        box-shadow: var(--card-shadow);
        margin-bottom: 1.5rem;
    }

    .page-icon {
        width: 56px;
        height: 56px;
        background: linear-gradient(135deg, #2563eb10, #1d4ed810);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        color: #2563eb;
        border: 1px solid #2563eb20;
    }

    .btn-create {
        background: #2563eb;
        color: white;
        padding: 0.6rem 1.4rem;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.9rem;
        border: none;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        text-decoration: none;
    }

    .btn-create:hover {
        background: #1d4ed8;
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -8px #2563eb;
        color: white;
    }

    .filter-section {
        background: white;
        padding: 1.2rem;
        border-radius: 16px;
        border: var(--border-light);
        margin-bottom: 1.5rem;
    }

    .search-wrapper {
        position: relative;
    }

    .search-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 0.9rem;
        pointer-events: none;
    }

    .search-input {
        width: 100%;
        height: 42px;
        padding: 0 1rem 0 2.8rem;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 12px;
        font-size: 0.9rem;
        color: #1e293b;
        transition: all 0.2s;
    }

    .search-input:focus {
        outline: none;
        border-color: #2563eb;
        background: white;
        box-shadow: 0 0 0 4px #2563eb20;
    }

    .filter-select {
        height: 42px;
        padding: 0 1rem;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 12px;
        font-size: 0.9rem;
        color: #1e293b;
        cursor: pointer;
        transition: all 0.2s;
        width: 100%;
    }

    .filter-select:focus {
        outline: none;
        border-color: #2563eb;
        background: white;
    }

    .btn-search {
        height: 42px;
        padding: 0 1.8rem;
        background: #1e293b;
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        white-space: nowrap;
    }

    .btn-search:hover {
        background: #0f172a;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px -8px #1e293b;
    }

    .btn-reset {
        height: 42px;
        width: 42px;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 12px;
        color: #64748b;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        text-decoration: none;
    }

    .btn-reset:hover {
        background: #f1f5f9;
        color: #2563eb;
        border-color: #2563eb40;
    }

    .table-container {
        background: white;
        border-radius: 20px;
        border: var(--border-light);
        box-shadow: var(--card-shadow);
        overflow: hidden;
    }

    .table {
        margin: 0;
        width: 100%;
    }

    .table thead th {
        background: #f8fafc;
        padding: 1rem 1.5rem;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #64748b;
        border-bottom: 1px solid #f1f5f9;
        white-space: nowrap;
    }

    .table tbody td {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #f8fafc;
        vertical-align: middle;
    }

    .table tbody tr:last-child td {
        border-bottom: none;
    }

    .table tbody tr:hover {
        background: #fafbff;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1rem;
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
        box-shadow: 0 4px 10px rgba(37, 99, 235, 0.15);
    }

    .user-info {
        line-height: 1.4;
    }

    .user-name {
        font-weight: 700;
        font-size: 0.95rem;
        color: #0f172a;
        margin-bottom: 0.2rem;
    }

    .user-email {
        font-size: 0.8rem;
        color: #64748b;
    }

    .user-phone {
        font-size: 0.75rem;
        color: #94a3b8;
        margin-top: 0.1rem;
    }

    .role-badge {
        padding: 0.3rem 0.8rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75rem;
        letter-spacing: 0.01em;
        white-space: nowrap;
        display: inline-block;
    }

    .status-indicator {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.8rem;
        font-weight: 600;
        padding: 0.3rem 0.8rem;
        border-radius: 8px;
        background: #f8fafc;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }

    .date-cell {
        font-size: 0.85rem;
    }

    .date-main {
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.1rem;
    }

    .date-sub {
        font-size: 0.7rem;
        color: #94a3b8;
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
    }

    .btn-action {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        border: none;
        font-size: 0.9rem;
        text-decoration: none;
    }

    .btn-action-edit {
        background: #eff6ff;
        color: #2563eb;
    }

    .btn-action-edit:hover {
        background: #2563eb;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 6px 15px -5px #2563eb;
    }

    .btn-action-delete {
        background: #fff1f2;
        color: #e11d48;
    }

    .btn-action-delete:hover {
        background: #e11d48;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 6px 15px -5px #e11d48;
    }

    .empty-state {
        padding: 3rem;
        text-align: center;
    }

    .empty-icon {
        width: 70px;
        height: 70px;
        background: #f8fafc;
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: #cbd5e1;
        margin: 0 auto 1.2rem;
    }

    .empty-title {
        font-weight: 700;
        font-size: 1.1rem;
        color: #1e293b;
        margin-bottom: 0.3rem;
    }

    .empty-text {
        font-size: 0.9rem;
        color: #94a3b8;
        margin-bottom: 1.5rem;
    }

    .quick-stats {
        display: flex;
        gap: 0.5rem;
        margin-left: 1rem;
    }

    .stat-pill {
        background: #f8fafc;
        padding: 0.3rem 0.8rem;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 600;
        color: #475569;
        border: 1px solid #f1f5f9;
    }

    .stat-pill i {
        margin-right: 0.3rem;
        color: #2563eb;
        font-size: 0.7rem;
    }
</style>

<div class="px-3 py-3">
    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="page-icon">
                <i class="fa-solid <?= $page_icon ?>"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h1 class="h5 fw-bold text-slate-900 mb-0"><?= $page_title ?></h1>
                    <?php if ($role_filter > 0): ?>
                        <span class="stat-pill">
                            <i class="fa-regular fa-user"></i> <?= $role_counts[$role_filter] ?? 0 ?> active
                        </span>
                    <?php endif; ?>
                </div>
                <p class="text-slate-500 small mb-0"><?= $page_description ?></p>
            </div>
        </div>
        <div>
            <a href="create.php<?= $role_filter ? '?role=' . $role_filter : '' ?>" class="btn-create">
                <i class="fa-solid fa-plus-circle"></i>
                <span>New <?= $current_role_name ?: 'User' ?></span>
            </a>
        </div>
    </div>

    <?php display_flash_message(); ?>

    <!-- Filters -->
    <div class="filter-section">
        <form method="GET" class="row g-2 align-items-center">
            <?php if ($role_filter > 0): ?>
                <input type="hidden" name="role" value="<?= $role_filter ?>">
            <?php endif; ?>

            <div class="col-lg-6">
                <div class="search-wrapper">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text"
                        name="search"
                        class="search-input"
                        placeholder="Search by name, email or phone..."
                        value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-lg-3">
                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status_filter == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-lg-3">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn-search flex-grow-1">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <span>Search</span>
                    </button>
                    <a href="index.php<?= $role_filter ? '?role=' . $role_filter : '' ?>" class="btn-reset" title="Reset filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="table-container">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="user-avatar">
                                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                        </div>
                                        <div class="user-info">
                                            <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                                            <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                                            <?php if ($user['phone']): ?>
                                                <div class="user-phone">
                                                    <i class="fa-solid fa-phone me-1" style="font-size: 0.65rem;"></i>
                                                    <?= htmlspecialchars($user['phone']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $role_colors = [
                                        ROLE_ADMIN => ['bg' => '#fef2f2', 'text' => '#b91c1c'],
                                        ROLE_DISTRIBUTOR => ['bg' => '#eef2ff', 'text' => '#4f46e5'],
                                        ROLE_STAFF => ['bg' => '#ecfdf5', 'text' => '#059669'],
                                        ROLE_CUSTOMER => ['bg' => '#fffbeb', 'text' => '#d97706']
                                    ];
                                    $color = $role_colors[$user['role_id']] ?? ['bg' => '#f8fafc', 'text' => '#475569'];
                                    ?>
                                    <span class="role-badge" style="background: <?= $color['bg'] ?>; color: <?= $color['text'] ?>;">
                                        <i class="fa-regular fa-circle me-1" style="font-size: 0.5rem;"></i>
                                        <?= htmlspecialchars($user['role_name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['status'] == 'active'): ?>
                                        <span class="status-indicator">
                                            <span class="status-dot" style="background: #10b981;"></span>
                                            <span style="color: #059669;">Active</span>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-indicator">
                                            <span class="status-dot" style="background: #94a3b8;"></span>
                                            <span style="color: #64748b;">Inactive</span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="date-cell">
                                        <div class="date-main"><?= date('M d, Y', strtotime($user['created_at'])) ?></div>
                                        <div class="date-sub"><?= date('h:i A', strtotime($user['created_at'])) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="edit.php?id=<?= $user['id'] ?>" class="btn-action btn-action-edit" title="Edit user">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <a href="javascript:void(0)" onclick="confirmDelete(<?= $user['id'] ?>)" class="btn-action btn-action-delete" title="Delete user">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="empty-state">
                                <div class="empty-icon">
                                    <i class="fa-regular <?= $role_filter ? 'fa-user-slash' : 'fa-users-slash' ?>"></i>
                                </div>
                                <div class="empty-title">No users found</div>
                                <div class="empty-text">
                                    <?php if ($search || $status_filter): ?>
                                        Try adjusting your search filters
                                    <?php else: ?>
                                        Get started by adding your first <?= strtolower($current_role_name ?: 'user') ?>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$search && !$status_filter): ?>
                                    <a href="create.php<?= $role_filter ? '?role=' . $role_filter : '' ?>" class="btn-create" style="display: inline-flex;">
                                        <i class="fa-solid fa-plus-circle"></i>
                                        <span>Add <?= $current_role_name ?: 'User' ?></span>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Results count -->
    <?php if (!empty($users)): ?>
        <div class="text-end mt-3">
            <small class="text-slate-400">
                <i class="fa-regular fa-list me-1"></i>
                Showing <?= count($users) ?> <?= count($users) == 1 ? 'user' : 'users' ?>
            </small>
        </div>
    <?php endif; ?>
</div>

<script>
    function confirmDelete(id) {
        if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            window.location.href = 'delete.php?id=' + id;
        }
    }

    // Update page title based on role
    document.title = "<?= $page_title ?> - Inventory System";
</script>

<?php include '../../includes/footer.php'; ?>