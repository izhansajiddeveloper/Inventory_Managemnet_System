<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    $role_id = $_SESSION['user_role'] ?? 0;

    if ($role_id == 1) header("Location: ../admin/dashboard.php");
    elseif ($role_id == 2) header("Location: ../distributor/dashboard.php");
    elseif ($role_id == 3) header("Location: ../staff/dashboard.php");
    elseif ($role_id == 4) header("Location: ../customer/dashboard.php");
    else header("Location: ../dashboard.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $remember = isset($_POST['remember']);

    // Check users table by email
    $sql = "SELECT * FROM users WHERE email='$email' AND status='active'";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        // Direct password comparison (as per your table)
        if ($password === $user['password']) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];

            // Set remember me cookie
            if ($remember) {
                setcookie('remember_email', $email, time() + (86400 * 30), "/"); // 30 days
            } else {
                setcookie('remember_email', '', time() - 3600, "/");
            }

            // Redirect based on role
            switch ($user['role_id']) {
                case 1: // Admin
                    header("Location: ../admin/dashboard.php");
                    break;
                case 2: // Distributor
                    header("Location: ../distributor/dashboard.php");
                    break;
                case 3: // Staff
                    header("Location: ../staff/dashboard.php");
                    break;
                case 4: // Customer
                    header("Location: ../customer/dashboard.php");
                    break;
                default:
                    header("Location: ../dashboard.php");
            }
            exit();
        } else {
            $message = "Invalid password!";
            $message_type = "error";
        }
    } else {
        $message = "Email not found or account inactive!";
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Inventory Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        * {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-indigo-50 to-blue-100 min-h-screen">

    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                <!-- Header with Gradient -->
                <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-8 text-center">
                    <div class="flex justify-center mb-4">
                        <div class="w-20 h-20 bg-white/20 rounded-2xl flex items-center justify-center">
                            <i class="fas fa-boxes text-4xl text-white"></i>
                        </div>
                    </div>
                    <h2 class="text-3xl font-bold text-white mb-2">Inventory System</h2>
                    <p class="text-blue-200">Sign in to your account</p>
                </div>

                <div class="p-8">
                    <?php if (!empty($message)): ?>
                        <div class="mb-6 p-4 <?php echo $message_type == 'error' ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-200'; ?> border rounded-xl">
                            <div class="flex items-center">
                                <i class="fas <?php echo $message_type == 'error' ? 'fa-exclamation-circle text-red-500' : 'fa-check-circle text-green-500'; ?> mr-3"></i>
                                <p class="<?php echo $message_type == 'error' ? 'text-red-700' : 'text-green-700'; ?> font-medium"><?php echo $message; ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6">
                        <!-- Email Field -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                                <i class="fas fa-envelope text-blue-600 mr-2"></i>
                                Email Address
                            </label>
                            <div class="relative">
                                <input type="email"
                                    name="email"
                                    required
                                    value="<?php echo htmlspecialchars($_COOKIE['remember_email'] ?? ''); ?>"
                                    class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="admin@example.com">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-envelope text-gray-400"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                                <i class="fas fa-lock text-blue-600 mr-2"></i>
                                Password
                            </label>
                            <div class="relative">
                                <input type="password"
                                    name="password"
                                    id="password"
                                    required
                                    class="w-full pl-10 pr-10 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="••••••••">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-key text-gray-400"></i>
                                </div>
                                <button type="button"
                                    onclick="togglePassword()"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i class="fas fa-eye text-gray-400 hover:text-blue-500" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Remember Me & Forgot Password -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <input type="checkbox"
                                    name="remember"
                                    id="remember"
                                    <?php echo isset($_COOKIE['remember_email']) ? 'checked' : ''; ?>
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="remember" class="ml-2 block text-sm text-gray-700">
                                    Remember me
                                </label>
                            </div>
                            <div class="text-sm">
                                <a href="forgot-password.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                                    <i class="fas fa-question-circle mr-1"></i>
                                    Forgot password?
                                </a>
                            </div>
                        </div>

                        <!-- Login Button -->
                        <button type="submit"
                            class="w-full py-3.5 px-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 flex items-center justify-center">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Sign In
                        </button>
                    </form>

                  

                    <!-- Register Link -->
                    <div class="mt-4 text-center">
                        <p class="text-sm text-gray-600">
                            Don't have an account?
                            <a href="register.php" class="text-blue-600 font-semibold hover:text-blue-800 hover:underline">
                                Register here
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>

</html>