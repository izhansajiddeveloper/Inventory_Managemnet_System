<?php
/**
 * Login Process Handling
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password']; // User requested plain password comparison

    // Validation
    if (empty($email) || empty($password)) {
        set_flash_message('danger', 'Please fill in all fields.');
        header("Location: login.php");
        exit();
    }

    try {
        // Find user by email
        $stmt = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email = ? AND u.status = 'active' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $password === $user['password']) {
            // Authentication successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];

            // Redirect based on role
            switch ($user['role_id']) {
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

        } else {
            // Authentication failed
            set_flash_message('danger', 'Invalid email or password.');
            header("Location: login.php");
            exit();
        }

    } catch (PDOException $e) {
        set_flash_message('danger', 'A system error occurred. Please try again later.');
        header("Location: login.php");
        exit();
    }
} else {
    // If not a POST request, redirect to login
    header("Location: login.php");
    exit();
}
