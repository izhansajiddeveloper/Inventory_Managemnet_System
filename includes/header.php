<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Set a default page title if not set
$page_title = isset($page_title) ? $page_title : 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- Google Fonts: Inter for a clean, professional feel -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: '#2563eb', // Indigo Blue
                            light: '#3b82f6',
                            dark: '#1d4ed8',
                        },
                        inventory: {
                            green: '#10b981',
                            orange: '#f59e0b',
                        }
                    },
                    borderRadius: {
                        '3xl': '1.5rem',
                        '4xl': '2rem',
                    }
                }
            }
        }
    </script>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <!-- Custom Theme CSS -->
    <style>
        :root {
            --primary-bg: #f8fafc;
            --sidebar-bg: #ffffff;
            --sidebar-color: #64748b;
            --sidebar-active-bg: #eff6ff;
            --sidebar-active-color: #2563eb;
            --primary-brand: #2563eb;
            --header-bg: #ffffff;
            --text-dark: #1e293b;
            --transition-speed: 0.3s;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--primary-bg);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* Layout Wrappers */
        .wrapper {
            display: flex;
            width: 100%;
            height: 100vh;
            overflow: hidden;
        }

        /* Main Content Styling */
        .main-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            transition: all var(--transition-speed);
            overflow-y: auto;
            position: relative;
        }

        /* Global UI components from provided template */
        .top-navbar {
            background: var(--header-bg);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
            padding: 12px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1030;
            border-bottom: 1px solid #f1f5f9;
        }

        .toggle-btn {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 1.1rem;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }

        .toggle-btn:hover {
            color: var(--primary-brand);
            background: #eff6ff;
            border-color: #bfdbfe;
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .transition-smooth {
            transition: all 0.3s ease;
        }

        .outline-none {
            outline: none !important;
        }

        .object-fit-cover {
            object-fit: cover;
        }

        /* Pulse for icons */
        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }
    </style>
</head>

<body>
    <div class="wrapper <?php echo isset($no_sidebar) && $no_sidebar ? 'd-block' : ''; ?>">