<?php

/**
 * Login Page - Modern Redesign
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/session.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['user_role']) {
        case 1:
            header("Location: " . BASE_URL . "admin/dashboard.php");
            break;
        case 2:
            header("Location: " . BASE_URL . "distributor/dashboard.php");
            break;
        case 3:
            header("Location: " . BASE_URL . "staff/dashboard.php");
            break;
        default:
            header("Location: " . BASE_URL . "index.php");
    }
    exit();
}

$page_title = "Login";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' | ' . APP_NAME; ?></title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        :root {
            --primary: #4158F0;
            --primary-dark: #3044c7;
            --secondary: #C850C0;
            --accent: #FFCC70;
            --dark: #1e293b;
            --light: #f8fafc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .bg-animation .shape {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 20s infinite ease-in-out;
        }

        .shape-1 {
            width: 500px;
            height: 500px;
            top: -250px;
            right: -100px;
            animation-delay: 0s;
        }

        .shape-2 {
            width: 400px;
            height: 400px;
            bottom: -200px;
            left: -100px;
            animation-delay: -5s;
            background: rgba(255, 255, 255, 0.08);
        }

        .shape-3 {
            width: 300px;
            height: 300px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.05);
            animation-delay: -10s;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0) rotate(0deg);
            }

            33% {
                transform: translateY(-50px) rotate(120deg);
            }

            66% {
                transform: translateY(50px) rotate(240deg);
            }
        }

        /* Main Container */
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        /* Glass Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            width: 100%;
            max-width: 440px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: transform 0.3s ease;
        }

        .glass-card:hover {
            transform: translateY(-5px);
        }

        /* Header */
        .login-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 3rem 2.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            transform: rotate(45deg);
            animation: shine 6s infinite;
        }

        @keyframes shine {
            0% {
                transform: translateX(-100%) rotate(45deg);
            }

            20%,
            100% {
                transform: translateX(100%) rotate(45deg);
            }
        }

        .brand-icon {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.2);
        }

        .brand-icon i {
            font-size: 2.5rem;
            color: white;
            filter: drop-shadow(0 5px 10px rgba(0, 0, 0, 0.2));
        }

        .login-header h2 {
            color: white;
            font-weight: 800;
            font-size: 2rem;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .login-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Body */
        .login-body {
            padding: 2.5rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            color: #94a3b8;
            font-size: 1rem;
            transition: color 0.2s;
            z-index: 1;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 2.75rem;
            border: 2px solid #f1f5f9;
            border-radius: 16px;
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--dark);
            background: #f8fafc;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(65, 88, 240, 0.1);
        }

        .form-control::placeholder {
            color: #cbd5e1;
            font-weight: 400;
        }

        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 1rem;
            color: #94a3b8;
            cursor: pointer;
            font-size: 1rem;
            transition: color 0.2s;
            z-index: 1;
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        /* Options Row */
        .options-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .remember-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .remember-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .remember-check span {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--dark);
        }

        .forgot-link {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
            transition: color 0.2s;
        }

        .forgot-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Login Button */
        .login-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 16px;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 20px -5px rgba(65, 88, 240, 0.3);
        }

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px -5px rgba(65, 88, 240, 0.4);
        }

        .login-btn:hover::before {
            left: 100%;
        }

        .login-btn i {
            margin-left: 0.5rem;
            transition: transform 0.3s;
        }

        .login-btn:hover i {
            transform: translateX(5px);
        }

        /* Footer */
        .login-footer {
            padding: 0 2.5rem 2.5rem;
            text-align: center;
        }

        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #f1f5f9;
            border-radius: 30px;
            color: var(--dark);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .security-badge i {
            color: #10b981;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: none;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
        }

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
        }

        .alert i {
            font-size: 1.1rem;
        }

        /* Responsive */
        @media (max-width: 576px) {
            .login-container {
                padding: 1rem;
            }

            .login-header {
                padding: 2rem 1.5rem;
            }

            .login-body {
                padding: 1.5rem;
            }

            .login-footer {
                padding: 0 1.5rem 1.5rem;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, .3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>

    <!-- Animated Background -->
    <div class="bg-animation">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <div class="login-container">
        <div class="glass-card" data-aos="fade-up" data-aos-duration="1000">
            <!-- Header -->
            <div class="login-header">
                <div class="brand-icon" data-aos="zoom-in" data-aos-delay="300">
                    <i class="fa-solid fa-boxes-packing"></i>
                </div>
                <h2 data-aos="fade-up" data-aos-delay="400">Welcome Back!</h2>
                <p data-aos="fade-up" data-aos-delay="500">Sign in to continue to <?= APP_NAME ?></p>
            </div>

            <!-- Body -->
            <div class="login-body">
                <?php
                $flash = display_flash_message(true);
                if ($flash):
                ?>
                    <div class="alert <?= $flash['type'] == 'success' ? 'alert-success' : 'alert-danger' ?>" data-aos="fade-up">
                        <i class="fa-solid <?= $flash['type'] == 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <form action="login_process.php" method="POST" id="loginForm">
                    <div class="form-group" data-aos="fade-up" data-aos-delay="600">
                        <label class="form-label">
                            <i class="fa-regular fa-envelope me-1"></i>
                            Email Address
                        </label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-envelope input-icon"></i>
                            <input type="email"
                                name="email"
                                class="form-control"
                                placeholder="admin@example.com"
                                required
                                autocomplete="email"
                                value="<?= htmlspecialchars($_COOKIE['remember_email'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group" data-aos="fade-up" data-aos-delay="700">
                        <label class="form-label">
                            <i class="fa-solid fa-lock me-1"></i>
                            Password
                        </label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password"
                                name="password"
                                id="password"
                                class="form-control"
                                placeholder="••••••••"
                                required
                                autocomplete="current-password">
                            <i class="fa-regular fa-eye password-toggle" id="togglePassword"></i>
                        </div>
                    </div>

                    <div class="options-row" data-aos="fade-up" data-aos-delay="800">
                        <label class="remember-check">
                            <input type="checkbox" name="remember" <?= isset($_COOKIE['remember_email']) ? 'checked' : '' ?>>
                            <span>Remember me</span>
                        </label>
                        <a href="forgot_password.php" class="forgot-link">
                            Forgot Password?
                        </a>
                    </div>

                    <button type="submit" class="login-btn" id="submitBtn" data-aos="fade-up" data-aos-delay="900">
                        <span class="btn-text">Sign In</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </form>
            </div>

            <!-- Footer -->
            <div class="login-footer" data-aos="fade-up" data-aos-delay="1000">
                <div class="security-badge">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>Secure Login • SSL Encrypted</span>
                </div>
                <p class="text-muted small mt-3 mb-0">
                    <i class="fa-regular fa-clock me-1"></i>
                    Access restricted to authorized personnel only
                </p>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <script>
        // Initialize AOS
        AOS.init({
            once: true,
            disable: 'mobile'
        });

        // Password Toggle
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);

            // Toggle icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Form Submit Animation
        const loginForm = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = submitBtn.querySelector('.btn-text');

        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Validate form
            const email = document.querySelector('input[name="email"]').value;
            const password = document.querySelector('input[name="password"]').value;

            if (!email || !password) {
                // Show error message (you can customize this)
                alert('Please fill in all fields');
                return;
            }

            // Add loading state
            submitBtn.disabled = true;
            btnText.textContent = 'Signing in...';
            submitBtn.innerHTML = '<span class="loading me-2"></span> Signing in...';

            // Submit form
            setTimeout(() => {
                loginForm.submit();
            }, 500);
        });

        // Input focus effects
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.closest('.input-wrapper').querySelector('.input-icon').style.color = '#4158F0';
            });

            input.addEventListener('blur', function() {
                this.closest('.input-wrapper').querySelector('.input-icon').style.color = '#94a3b8';
            });
        });

        // Add dynamic particles effect (optional)
        function createParticle() {
            const particles = document.querySelector('.bg-animation');
            if (!particles) return;

            const particle = document.createElement('div');
            particle.style.position = 'absolute';
            particle.style.width = Math.random() * 5 + 'px';
            particle.style.height = particle.style.width;
            particle.style.background = 'rgba(255, 255, 255, 0.1)';
            particle.style.borderRadius = '50%';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.top = '-10px';
            particle.style.animation = `float ${Math.random() * 10 + 10}s infinite`;

            particles.appendChild(particle);

            setTimeout(() => {
                particle.remove();
            }, 20000);
        }

        // Create particles periodically
        setInterval(createParticle, 3000);
    </script>

</body>

</html>