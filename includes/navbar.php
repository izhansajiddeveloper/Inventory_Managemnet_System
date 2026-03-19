<?php

/**
 * Modern Premium Navbar Component
 */
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Guest User';
$user_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : 'no-reply@inventory.com';
?>
<!-- Navbar & Main Layout Start -->
<div class="main-content" id="mainContent">
    <nav class="top-navbar bg-white sticky-top">
        <div class="d-flex align-items-center flex-grow-1">
            <!-- Sleek Toggle Button -->
            <button id="sidebarToggle" class="toggle-btn me-4 d-inline-flex align-items-center justify-content-center p-2 rounded shadow-sm">
                <i class="fa-solid fa-bars-staggered"></i>
            </button>
            <div class="search-wrapper d-none d-lg-block">
                <div class="position-relative">
                    <i class="fa-solid fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 14px;"></i>
                    <input type="text" class="form-control border-0 bg-light rounded-pill ps-5 py-2" placeholder="Search item, order, etc" style="width: 380px; font-size: 14px; box-shadow: none;">
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-4">
            <!-- Dynamic Notifications Bubble -->
            <?php
            $unread_count = 0;
            if (isset($_SESSION['user_id']) && isset($pdo)) {
                try {
                    // Check if notifications table exists safely
                    $check_table = $pdo->query("SHOW TABLES LIKE 'notifications'")->rowCount() > 0;
                    if ($check_table) {
                        $u_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                        $u_stmt->execute([$_SESSION['user_id']]);
                        $unread_count = $u_stmt->fetchColumn();
                    }
                } catch (Exception $e) {
                    $unread_count = 0;
                }
            }
            ?>
            <a href="#" class="position-relative text-dark px-2 text-decoration-none transition-smooth" style="cursor: pointer;">
                <i class="fa-regular fa-bell fs-5"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 0.6rem; padding: 0.35em 0.65em; animation: pulse 2s infinite;">
                        <?= $unread_count ?>
                        <span class="visually-hidden">unread notifications</span>
                    </span>
                <?php endif; ?>
            </a>

            <!-- Premium User Dropdown Profile -->
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle shadow-none" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="border: none; outline: none;">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($user_name) ?>&background=2563eb&color=fff&bold=true"
                        alt="Profile" class="rounded-circle me-2 object-fit-cover shadow-sm" width="38" height="38">
                    <div class="d-none d-sm-block text-start">
                        <div class="fw-bold text-dark lh-1" style="font-size: 0.9rem;"><?= htmlspecialchars($user_name) ?></div>
                        <small class="text-muted" style="font-size: 0.75rem;">Online</small>
                    </div>
                </a>

                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-3 p-0" aria-labelledby="userMenuDropdown" style="min-width: 260px; border-radius: 12px; overflow: hidden;">
                    <li class="px-4 py-3 border-bottom bg-light">
                        <div class="d-flex flex-column">
                            <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($user_name) ?></span>
                            <small class="text-muted d-block text-truncate" style="max-width: 200px;"><?= htmlspecialchars($user_email) ?></small>
                        </div>
                    </li>
                    <li class="mt-2"><a class="dropdown-item py-2 fw-medium px-4 text-secondary" href="#"><i class="fa-regular fa-user-circle me-3 text-primary" style="width: 20px;"></i> My Profile</a></li>
                    <li><a class="dropdown-item py-2 fw-medium px-4 text-secondary" href="#"><i class="fa-solid fa-sliders me-3 text-info" style="width: 20px;"></i> System Prefs</a></li>
                    <li>
                        <hr class="dropdown-divider my-2">
                    </li>
                    <li><a class="dropdown-item py-3 fw-medium text-danger px-4" style="background:#fff5f5" href="<?= BASE_URL ?>auth/logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-3 text-danger" style="width: 20px;"></i> Secure Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Application Content Container -->
    <div class="container-fluid p-4 fade-in">
        <!-- Flash messages will be displayed here if needed -->