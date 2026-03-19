<?php

/**
 * User Management - Create User
 * Professional form with role pre-selection and plain password
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

function sanitize($data)
{
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

// Define role constants
define('ROLE_ADMIN', 1);
define('ROLE_DISTRIBUTOR', 2);
define('ROLE_STAFF', 3);
define('ROLE_CUSTOMER', 4);

// Get role from URL for pre-selection
$preselected_role = isset($_GET['role']) ? (int)$_GET['role'] : 0;

// Set page title based on role
$role_titles = [
    ROLE_DISTRIBUTOR => 'Add New Distributor',
    ROLE_STAFF => 'Add New Staff Member',
    ROLE_CUSTOMER => 'Add New Customer',
    0 => 'Add New User'
];
$page_title = $role_titles[$preselected_role] ?? 'Add New User';

// Set role icon
$role_icons = [
    ROLE_DISTRIBUTOR => 'fa-truck-fast',
    ROLE_STAFF => 'fa-user-tie',
    ROLE_CUSTOMER => 'fa-user',
    0 => 'fa-user-plus'
];
$role_icon = $role_icons[$preselected_role] ?? 'fa-user-plus';

// Set role description
$role_descriptions = [
    ROLE_DISTRIBUTOR => 'Create a new distributor account with inventory and order management access',
    ROLE_STAFF => 'Add a staff member for internal operations and customer service',
    ROLE_CUSTOMER => 'Register a new customer account for making purchases',
    0 => 'Create a new system user with specific role-based access'
];
$role_description = $role_descriptions[$preselected_role] ?? 'Create a new system user';

// Fetch roles for dropdown
$roles_result = mysqli_query($conn, "SELECT * FROM roles ORDER BY id ASC");
$roles = [];
if ($roles_result) {
    while ($row = mysqli_fetch_assoc($roles_result)) {
        $roles[] = $row;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_id = (int)($_POST['role_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'active');

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($role_id)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 3) {
        $error = "Password must be at least 3 characters long.";
    } else {
        // Check if email exists
        $check_query = "SELECT id FROM users WHERE email = '$email'";
        $check_result = mysqli_query($conn, $check_query);
        if ($check_result && mysqli_num_rows($check_result) > 0) {
            $error = "This email is already registered in the system.";
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $insert_query = "INSERT INTO users (name, email, password, phone, role_id, status, created_at) 
                            VALUES ('$name', '$email', '$hashed_password', '$phone', $role_id, '$status', NOW())";

            if (mysqli_query($conn, $insert_query)) {
                set_flash_message('success', 'User created successfully.');
                // Redirect back to the list page
                redirect('index.php' . ($preselected_role ? '?role=' . $preselected_role : ''));
            } else {
                $error = "Failed to create user. Please try again.";
            }
        }
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
    :root {
        --form-spacing: 1.5rem;
        --input-height: 44px;
        --border-light: 1px solid #e2e8f0;
        --focus-ring: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .form-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 1.5rem;
    }

    .page-header-minimal {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2rem;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: #64748b;
        font-size: 0.9rem;
        font-weight: 500;
        text-decoration: none;
        padding: 0.5rem 1rem;
        border-radius: 10px;
        background: white;
        border: var(--border-light);
        transition: all 0.2s;
    }

    .back-link:hover {
        background: #f8fafc;
        color: #2563eb;
        border-color: #2563eb;
    }

    .form-card {
        background: white;
        border-radius: 20px;
        border: var(--border-light);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
        overflow: hidden;
    }

    .form-header {
        padding: 1.5rem 2rem;
        border-bottom: var(--border-light);
        background: #fafbff;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .header-icon {
        width: 48px;
        height: 48px;
        background: white;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: #2563eb;
        border: var(--border-light);
    }

    .header-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0.25rem;
    }

    .header-subtitle {
        font-size: 0.85rem;
        color: #64748b;
    }

    .form-body {
        padding: 2rem;
    }

    .form-section {
        margin-bottom: 2rem;
    }

    .section-title {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .section-title i {
        width: 28px;
        height: 28px;
        background: #f1f5f9;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        color: #2563eb;
    }

    .section-title span {
        font-size: 0.95rem;
        font-weight: 700;
        color: #1e293b;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .form-group {
        width: 100%;
    }

    .form-label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: #475569;
        margin-bottom: 0.5rem;
    }

    .form-label i {
        margin-right: 0.35rem;
        color: #94a3b8;
        font-size: 0.75rem;
    }

    .required:after {
        content: '*';
        color: #ef4444;
        margin-left: 0.25rem;
        font-size: 0.7rem;
    }

    .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .input-icon-left {
        position: absolute;
        left: 1rem;
        color: #94a3b8;
        font-size: 0.9rem;
        pointer-events: none;
    }

    .form-control {
        width: 100%;
        height: var(--input-height);
        padding: 0 1rem 0 2.5rem;
        background: white;
        border: var(--border-light);
        border-radius: 12px;
        font-size: 0.9rem;
        color: #1e293b;
        transition: all 0.2s;
    }

    .form-control:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: var(--focus-ring);
    }

    .form-control::placeholder {
        color: #cbd5e1;
        font-size: 0.85rem;
    }

    select.form-control {
        padding: 0 2rem 0 2.5rem;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 1rem center;
        cursor: pointer;
    }

    .password-strength {
        margin-top: 0.5rem;
        font-size: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .strength-bar {
        flex: 1;
        height: 4px;
        background: #e2e8f0;
        border-radius: 2px;
        overflow: hidden;
    }

    .strength-progress {
        height: 100%;
        width: 0;
        background: #10b981;
        transition: width 0.3s;
    }

    .phone-hint {
        font-size: 0.7rem;
        color: #94a3b8;
        margin-top: 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .status-options {
        display: flex;
        gap: 1.5rem;
        padding: 0.5rem 0;
    }

    .status-radio {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
    }

    .status-radio input[type="radio"] {
        width: 18px;
        height: 18px;
        accent-color: #2563eb;
        margin: 0;
    }

    .status-radio span {
        font-size: 0.9rem;
        color: #1e293b;
    }

    .status-radio small {
        font-size: 0.7rem;
        color: #94a3b8;
        margin-left: 0.25rem;
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: var(--border-light);
    }

    .btn-cancel {
        height: var(--input-height);
        padding: 0 1.5rem;
        background: white;
        border: var(--border-light);
        border-radius: 12px;
        font-size: 0.9rem;
        font-weight: 600;
        color: #64748b;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        transition: all 0.2s;
    }

    .btn-cancel:hover {
        background: #f8fafc;
        color: #1e293b;
        border-color: #cbd5e1;
    }

    .btn-submit {
        height: var(--input-height);
        padding: 0 2rem;
        background: #2563eb;
        border: none;
        border-radius: 12px;
        font-size: 0.9rem;
        font-weight: 600;
        color: white;
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-submit:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
    }

    .alert {
        padding: 1rem 1.5rem;
        border-radius: 12px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 2rem;
    }

    .alert-error {
        background: #fef2f2;
        border: 1px solid #fee2e2;
        color: #991b1b;
    }

    .role-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        background: #f1f5f9;
        border-radius: 30px;
        font-size: 0.7rem;
        font-weight: 600;
        color: #475569;
        margin-left: 1rem;
    }

    .password-requirement {
        font-size: 0.7rem;
        color: #94a3b8;
        margin-top: 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .password-requirement i {
        color: #10b981;
        font-size: 0.65rem;
    }

    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .form-body {
            padding: 1.5rem;
        }

        .form-actions {
            flex-direction: column-reverse;
        }

        .btn-cancel,
        .btn-submit {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="form-container">
    <!-- Header with back link -->
    <div class="page-header-minimal">
        <a href="index.php<?= $preselected_role ? '?role=' . $preselected_role : '' ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to Directory</span>
        </a>
        <?php if ($preselected_role > 0): ?>
            <?php
            $role_names = [
                ROLE_DISTRIBUTOR => 'Distributor',
                ROLE_STAFF => 'Staff',
                ROLE_CUSTOMER => 'Customer'
            ];
            ?>
            <span class="role-badge">
                <i class="fa-regular fa-circle me-1"></i>
                <?= $role_names[$preselected_role] ?? 'User' ?> Registration
            </span>
        <?php endif; ?>
    </div>

    <!-- Main Form Card -->
    <div class="form-card">
        <div class="form-header">
            <div class="header-icon">
                <i class="fa-solid <?= $role_icon ?>"></i>
            </div>
            <div>
                <h1 class="header-title"><?= $page_title ?></h1>
                <p class="header-subtitle"><?= $role_description ?></p>
            </div>
        </div>

        <div class="form-body">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" id="createUserForm">
                <!-- Personal Information Section -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fa-solid fa-user"></i>
                        <span>Personal Information</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">
                                <i class="fa-regular fa-user"></i>
                                Full Name
                            </label>
                            <div class="input-wrapper">
                                <i class="fa-regular fa-user input-icon-left"></i>
                                <input type="text"
                                    name="name"
                                    class="form-control"
                                    placeholder="John Doe"
                                    value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>"
                                    required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label required">
                                <i class="fa-regular fa-envelope"></i>
                                Email Address
                            </label>
                            <div class="input-wrapper">
                                <i class="fa-regular fa-envelope input-icon-left"></i>
                                <input type="email"
                                    name="email"
                                    class="form-control"
                                    placeholder="john@company.com"
                                    value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                                    required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fa-solid fa-phone"></i>
                                Phone Number
                            </label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-phone input-icon-left"></i>
                                <input type="tel"
                                    name="phone"
                                    class="form-control"
                                    placeholder="(555) 123-4567"
                                    value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                            </div>
                            <div class="phone-hint">
                                <i class="fa-regular fa-circle-info"></i>
                                Optional: Enter 10-digit number
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label required">
                                <i class="fa-solid fa-lock"></i>
                                Password
                            </label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-key input-icon-left"></i>
                                <input type="password"
                                    name="password"
                                    class="form-control"
                                    placeholder="Enter password"
                                    id="password"
                                    minlength="3"
                                    required>
                            </div>
                            <div class="password-strength" id="passwordStrength">
                                <span>Strength:</span>
                                <div class="strength-bar">
                                    <div class="strength-progress" id="strengthProgress"></div>
                                </div>
                            </div>
                            <div class="password-requirement">
                                <i class="fa-regular fa-circle-check"></i>
                                Minimum 3 characters required
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Role & Permissions Section -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fa-solid fa-shield"></i>
                        <span>Role & Permissions</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">
                                <i class="fa-solid fa-badge"></i>
                                User Role
                            </label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-layer-group input-icon-left"></i>
                                <select name="role_id" class="form-control" required>
                                    <option value="">Select a role...</option>
                                    <?php foreach ($roles as $role): ?>
                                        <?php
                                        $selected = ($preselected_role == $role['id'] || (isset($_POST['role_id']) && $_POST['role_id'] == $role['id']));
                                        $role_icons = [
                                            'Admin' => '👑',
                                            'Distributor' => '🚚',
                                            'Staff' => '👔',
                                            'Customer' => '👤'
                                        ];
                                        $icon = $role_icons[$role['name']] ?? '•';
                                        ?>
                                        <option value="<?= $role['id'] ?>" <?= $selected ? 'selected' : '' ?>>
                                            <?= $icon ?> <?= htmlspecialchars($role['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label required">
                                <i class="fa-regular fa-circle-check"></i>
                                Account Status
                            </label>
                            <div class="status-options">
                                <label class="status-radio">
                                    <input type="radio" name="status" value="active" <?= (!isset($_POST['status']) || $_POST['status'] == 'active') ? 'checked' : '' ?>>
                                    <span>Active</span>
                                    <small>(Immediate access)</small>
                                </label>
                                <label class="status-radio">
                                    <input type="radio" name="status" value="inactive" <?= (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'checked' : '' ?>>
                                    <span>Inactive</span>
                                    <small>(Restricted)</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="index.php<?= $preselected_role ? '?role=' . $preselected_role : '' ?>" class="btn-cancel">
                        <i class="fa-regular fa-times"></i>
                        Cancel
                    </a>
                    <button type="submit" class="btn-submit">
                        <i class="fa-regular fa-check"></i>
                        Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Password strength indicator (updated for 3 char minimum) -->
<script>
    document.getElementById('password')?.addEventListener('input', function(e) {
        const password = e.target.value;
        const strengthBar = document.getElementById('strengthProgress');

        let strength = 0;
        if (password.length >= 3) strength += 25;
        if (password.length >= 6) strength += 25;
        if (password.match(/[A-Z]/)) strength += 25;
        if (password.match(/[0-9]/) || password.match(/[$@#&!]/)) strength += 25;

        strengthBar.style.width = strength + '%';

        if (strength < 30) {
            strengthBar.style.background = '#ef4444';
        } else if (strength < 60) {
            strengthBar.style.background = '#f59e0b';
        } else {
            strengthBar.style.background = '#10b981';
        }
    });

    // Format phone number as user types (optional enhancement)
    document.querySelector('input[name="phone"]')?.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 10) value = value.slice(0, 10);

        if (value.length >= 6) {
            value = '(' + value.slice(0, 3) + ') ' + value.slice(3, 6) + '-' + value.slice(6);
        } else if (value.length >= 3) {
            value = '(' + value.slice(0, 3) + ') ' + value.slice(3);
        }

        e.target.value = value;
    });
</script>

<?php include '../../includes/footer.php'; ?>