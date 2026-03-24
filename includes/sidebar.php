<?php

/**
 * Advanced Premium Sidebar Navigation
 * With Multi-level Dropdowns & Inventory Focus
 */
$role_id = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 0;
$current_page = basename($_SERVER['PHP_SELF']);
$current_full_url = $_SERVER['REQUEST_URI'];
$current_role = isset($active_role) ? $active_role : (isset($_GET['role']) ? (int)$_GET['role'] : 0);
$is_users_module = (strpos($current_full_url, '/admin/users/') !== false);

// Get user info
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Guest';
?>

<style>
    :root {
        --primary-blue: #2563eb;
        --primary-green: #10b981;
        --sidebar-bg: #ffffff;
        --nav-text: #64748b;
        --nav-active-bg: #eff6ff;
        --nav-active-text: #2563eb;
    }

    .modern-sidebar {
        height: 100vh;
        width: 280px;
        min-width: 280px;
        flex-shrink: 0;
        background: var(--sidebar-bg);
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 1040;
        overflow-y: auto;
        position: sticky;
        top: 0;
        border-right: 1px solid #f1f5f9;
        display: flex;
        flex-direction: column;
    }

    /* Scrollbar */
    .modern-sidebar::-webkit-scrollbar {
        width: 5px;
    }

    .modern-sidebar::-webkit-scrollbar-track {
        background: transparent;
    }

    .modern-sidebar::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 10px;
    }

    /* Header Section */
    .sidebar-header {
        padding: 30px 24px;
        background: linear-gradient(135deg, var(--primary-blue), #1d4ed8);
        color: white;
        margin-bottom: 25px;
        border-radius: 0 0 30px 0;
    }

    .brand-box {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .brand-logo {
        width: 42px;
        height: 42px;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .brand-name {
        font-size: 18px;
        font-weight: 800;
        letter-spacing: -0.5px;
    }

    /* Nav Styles */
    .nav-list {
        list-style: none;
        padding: 0 16px;
        margin: 0;
    }

    .nav-group-title {
        font-size: 11px;
        font-weight: 800;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        margin: 20px 0 10px 12px;
    }

    .nav-link-custom {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-radius: 14px;
        color: var(--nav-text);
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s;
        margin-bottom: 4px;
        position: relative;
    }

    .nav-link-custom:hover {
        background: #f8fafc;
        color: var(--primary-blue);
    }

    .nav-link-custom i:first-child {
        width: 20px;
        text-align: center;
        font-size: 16px;
        opacity: 0.7;
    }

    .nav-link-custom .chevron {
        margin-left: auto;
        font-size: 11px;
        transition: transform 0.3s;
        opacity: 0.5;
    }

    .nav-link-custom[aria-expanded="true"] {
        color: var(--primary-blue);
        background: var(--nav-active-bg);
    }

    .nav-link-custom[aria-expanded="true"] .chevron {
        transform: rotate(180deg);
        opacity: 1;
    }

    /* Submenu items */
    .submenu {
        list-style: none;
        padding: 4px 0 10px 42px;
        position: relative;
    }

    .submenu::before {
        content: '';
        position: absolute;
        left: 22px;
        top: 0;
        bottom: 10px;
        width: 2px;
        background: #f1f5f9;
    }

    .submenu-item a {
        display: block;
        padding: 8px 12px;
        font-size: 13px;
        color: #64748b;
        text-decoration: none;
        font-weight: 500;
        border-radius: 8px;
        transition: all 0.2;
    }

    .submenu-item a:hover {
        color: var(--primary-blue);
        background: #f1f5f9;
        padding-left: 18px;
    }

    /* Nested Submenu (Level 3) */
    .nested-toggle {
        font-size: 13px;
        color: #64748b;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        cursor: pointer;
        border-radius: 8px;
    }

    .nested-toggle:hover {
        background: #f1f5f9;
        color: var(--primary-blue);
    }

    /* Improved Dropdown Visibility */
    .collapse.show {
        display: block !important;
        visibility: visible !important;
        height: auto !important;
    }

    .submenu {
        list-style: none;
        padding: 4px 0 10px 38px;
        position: relative;
        overflow: visible;
        /* Ensure nested submenus aren't clipped */
    }

    .submenu::before {
        content: '';
        position: absolute;
        left: 20px;
        top: 0;
        bottom: 10px;
        width: 1px;
        background: #e2e8f0;
    }

    .submenu-item {
        position: relative;
        margin-bottom: 2px;
    }

    .submenu-item a {
        display: block;
        padding: 8px 15px;
        font-size: 13.5px;
        color: #64748b;
        text-decoration: none;
        font-weight: 500;
        border-radius: 10px;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .submenu-item a:hover {
        color: var(--primary-blue);
        background: #f8fafc;
        transform: translateX(4px);
    }

    /* Nested Toggles (Level 3) */
    .nested-toggle {
        font-size: 13.5px;
        color: #64748b;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 15px;
        cursor: pointer;
        border-radius: 10px;
        transition: all 0.2s;
        user-select: none;
    }

    .nested-toggle:hover {
        color: var(--primary-blue);
        background: #f8fafc;
    }

    .nested-toggle i.fa-plus {
        transition: transform 0.3s;
    }

    .nested-toggle[aria-expanded="true"] {
        color: var(--primary-blue);
    }

    .nested-toggle[aria-expanded="true"] i.fa-plus {
        transform: rotate(45deg);
        color: #ef4444;
        /* Turn red-ish when it's an 'x' */
    }

    .nested-submenu {
        list-style: none;
        padding: 5px 0 5px 15px;
        border-left: 1px dashed #cbd5e1;
        margin-left: 10px;
        margin-top: 2px;
        margin-bottom: 8px;
    }

    .nested-submenu li a {
        padding: 6px 12px;
        font-size: 12.5px;
    }

    /* Active State for Nested Items */
    .nested-submenu li a.active {
        color: var(--primary-blue);
        font-weight: 700;
        background: #eff6ff;
    }

    /* Fix for Tailwind Collapse Conflict */
    .collapse:not(.show) {
        display: none !important;
    }
</style>

<aside class="modern-sidebar" id="sidebar">
    <!-- Header -->
    <div class="sidebar-header">
        <div class="brand-box">
            <div class="brand-logo">
                <i class="fa-solid fa-boxes-packing"></i>
            </div>
            <div class="brand-name">Inventory System</div>
        </div>
    </div>

    <!-- Navigation List -->
    <ul class="nav-list" id="sidebarNav">

        <!-- Dashboard Section -->
        <li class="nav-item">
            <?php 
            $dashboard_url = BASE_URL;
            if ($role_id == 1) $dashboard_url .= "admin/dashboard.php";
            elseif ($role_id == 3) $dashboard_url .= "staff/dashboard.php";
            elseif ($role_id == 2) $dashboard_url .= "distributor/dashboard.php";
            else $dashboard_url .= "dashboard.php";
            ?>
            <a href="<?= $dashboard_url ?>" class="nav-link-custom <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-house-chimney-window"></i>
                <span>Overview</span>
            </a>
        </li>

        <?php if ($role_id == 1): // Admin 
        ?>

            <div class="nav-group-title">Operations</div>

            <!-- User Management (Level 1) -->
            <li class="nav-item">
                <a class="nav-link-custom <?= $is_users_module ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#userMgmt" role="button" aria-expanded="<?= $is_users_module ? 'true' : 'false' ?>" aria-controls="userMgmt">
                    <i class="fa-solid fa-user-gear"></i>
                    <span>User Management</span>
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </a>
                <div class="collapse <?= $is_users_module ? 'show' : '' ?>" id="userMgmt">
                    <ul class="submenu">
                        <!-- Distributors (Level 2) -->
                        <li class="submenu-item">
                            <?php $is_dist = ($is_users_module && $current_role == 2); ?>
                            <div class="nested-toggle <?= $is_dist ? 'active text-primary' : '' ?>" data-bs-toggle="collapse" data-bs-target="#distSub" aria-expanded="<?= $is_dist ? 'true' : 'false' ?>">
                                <span>Distributors</span>
                                <i class="fa-solid fa-plus text-[10px] opacity-60"></i>
                            </div>
                            <div class="collapse <?= $is_dist ? 'show' : '' ?>" id="distSub">
                                <ul class="nested-submenu">
                                    <li><a href="<?= BASE_URL ?>admin/users/index.php?role=2" class="<?= ($is_dist && $current_page == 'index.php') ? 'active' : '' ?>">List Distributors</a></li>
                                    <li><a href="<?= BASE_URL ?>admin/users/create.php?role=2" class="<?= ($is_dist && $current_page == 'create.php') ? 'active' : '' ?>">Add Distributor</a></li>
                                </ul>
                            </div>
                        </li>
                        <!-- Staff (Level 2) -->
                        <li class="submenu-item">
                            <?php $is_staff = ($is_users_module && $current_role == 3); ?>
                            <div class="nested-toggle <?= $is_staff ? 'active text-primary' : '' ?>" data-bs-toggle="collapse" data-bs-target="#staffSub" aria-expanded="<?= $is_staff ? 'true' : 'false' ?>">
                                <span>Staff Members</span>
                                <i class="fa-solid fa-plus text-[10px] opacity-60"></i>
                            </div>
                            <div class="collapse <?= $is_staff ? 'show' : '' ?>" id="staffSub">
                                <ul class="nested-submenu">
                                    <li><a href="<?= BASE_URL ?>admin/users/index.php?role=3" class="<?= ($is_staff && $current_page == 'index.php') ? 'active' : '' ?>">List Staff</a></li>
                                    <li><a href="<?= BASE_URL ?>admin/users/create.php?role=3" class="<?= ($is_staff && $current_page == 'create.php') ? 'active' : '' ?>">Add Staff Member</a></li>
                                </ul>
                            </div>
                        </li>
                        <!-- Customers (Level 2) -->
                        <li class="submenu-item">
                            <?php $is_cust = ($is_users_module && $current_role == 4); ?>
                            <div class="nested-toggle <?= $is_cust ? 'active text-primary' : '' ?>" data-bs-toggle="collapse" data-bs-target="#custSub" aria-expanded="<?= $is_cust ? 'true' : 'false' ?>">
                                <span>Customers</span>
                                <i class="fa-solid fa-plus text-[10px] opacity-60"></i>
                            </div>
                            <div class="collapse <?= $is_cust ? 'show' : '' ?>" id="custSub">
                                <ul class="nested-submenu">
                                    <li><a href="<?= BASE_URL ?>admin/users/index.php?role=4" class="<?= ($is_cust && $current_page == 'index.php') ? 'active' : '' ?>">List Customers</a></li>
                                    <li><a href="<?= BASE_URL ?>admin/users/create.php?role=4" class="<?= ($is_cust && $current_page == 'create.php') ? 'active' : '' ?>">Add Customer</a></li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </div>
            </li>

            <?php
            $is_products_module = (strpos($current_full_url, '/admin/products/') !== false);
            ?>
            <li class="nav-item">
                <a class="nav-link-custom <?= $is_products_module ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#prodMgmt" role="button" aria-expanded="<?= $is_products_module ? 'true' : 'false' ?>" aria-controls="prodMgmt">
                    <i class="fa-solid fa-box-open"></i>
                    <span>Products</span>
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </a>
                <div class="collapse <?= $is_products_module ? 'show' : '' ?>" id="prodMgmt">
                    <ul class="submenu">
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/products/index.php" class="<?= ($is_products_module && $current_page == 'index.php') ? 'active' : '' ?>">Product List</a></li>
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/products/create.php" class="<?= ($is_products_module && $current_page == 'create.php') ? 'active' : '' ?>">Add New Product</a></li>
                    </ul>
                </div>
            </li>

            <?php
            $is_stock_module = (strpos($current_full_url, '/admin/stock/') !== false);
            ?>
            <li class="nav-item">
                <a class="nav-link-custom <?= $is_stock_module ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#stockMgmt" role="button" aria-expanded="<?= $is_stock_module ? 'true' : 'false' ?>" aria-controls="stockMgmt">
                    <i class="fa-solid fa-warehouse"></i>
                    <span>Stock Management</span>
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </a>
                <div class="collapse <?= $is_stock_module ? 'show' : '' ?>" id="stockMgmt">
                    <ul class="submenu">
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/stock/index.php" class="<?= ($is_stock_module && $current_page == 'index.php') ? 'active' : '' ?>">Stock Dashboard</a></li>
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/stock/add_stock.php" class="<?= ($is_stock_module && $current_page == 'add_stock.php') ? 'active' : '' ?>">Add Stock</a></li>
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/stock/transactions.php" class="<?= ($is_stock_module && $current_page == 'transactions.php') ? 'active' : '' ?>">Stock History</a></li>
                    </ul>
                </div>
            </li>

            <?php
            $is_orders_module = (strpos($current_full_url, '/admin/orders/') !== false);
            ?>
            <li class="nav-item">
                <a class="nav-link-custom <?= $is_orders_module ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#ordMgmt" role="button" aria-expanded="<?= $is_orders_module ? 'true' : 'false' ?>" aria-controls="ordMgmt">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span>Orders</span>
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </a>
                <div class="collapse <?= $is_orders_module ? 'show' : '' ?>" id="ordMgmt">
                    <ul class="submenu">
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/orders/index.php" class="<?= ($is_orders_module && $current_page == 'index.php') ? 'active' : '' ?>">Order List</a></li>
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/orders/create.php" class="<?= ($is_orders_module && $current_page == 'create.php') ? 'active' : '' ?>">Create New Order</a></li>
                    </ul>
                </div>
            </li>

            <?php
            $is_payments_module = (strpos($current_full_url, '/admin/payments/') !== false);
            ?>
            <li class="nav-item">
                <a class="nav-link-custom <?= $is_payments_module ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#payMgmt" role="button" aria-expanded="<?= $is_payments_module ? 'true' : 'false' ?>" aria-controls="payMgmt">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    <span>Payments</span>
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </a>
                <div class="collapse <?= $is_payments_module ? 'show' : '' ?>" id="payMgmt">
                    <ul class="submenu">
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/payments/index.php" class="<?= ($is_payments_module && $current_page == 'index.php') ? 'active' : '' ?>">Payment History</a></li>
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/payments/add.php" class="<?= ($is_payments_module && $current_page == 'add.php') ? 'active' : '' ?>">Record Payment</a></li>
                    </ul>
                </div>
            </li>

            <?php
            $is_sales_module = (strpos($current_full_url, '/admin/sales/') !== false);
            ?>
            <li class="nav-item">
                <a class="nav-link-custom <?= $is_sales_module ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#saleMgmt" role="button" aria-expanded="<?= $is_sales_module ? 'true' : 'false' ?>" aria-controls="saleMgmt">
                    <i class="fa-solid fa-receipt"></i>
                    <span>Sales Management</span>
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </a>
                <div class="collapse <?= $is_sales_module ? 'show' : '' ?>" id="saleMgmt">
                    <ul class="submenu">
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/sales/index.php" class="<?= ($is_sales_module && $current_page == 'index.php') ? 'active' : '' ?>">Sales Dashboard</a></li>
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/orders/create.php" class="<?= ($is_sales_module && $current_page == 'create.php') ? 'active' : '' ?>">New Sale / Order</a></li>
                    </ul>
                </div>
            </li>

            <div class="nav-group-title">Analytics</div>

            <!-- Profit Section (Level 1) -->
            <li class="nav-item">
                <a class="nav-link-custom <?= (strpos($current_full_url, '/admin/profit/') !== false) ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#profitMgmt" role="button" aria-expanded="false" aria-controls="profitMgmt">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Profit Analysis</span>
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </a>
                <div class="collapse <?= (strpos($current_full_url, '/admin/profit/') !== false) ? 'show' : '' ?>" id="profitMgmt">
                    <ul class="submenu">
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/profit/index.php" class="<?= ($current_page == 'index.php' && strpos($current_full_url, '/profit/') !== false) ? 'active' : '' ?>">Profit Overview</a></li>
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/profit/order_profit.php" class="<?= ($current_page == 'order_profit.php') ? 'active' : '' ?>">Profit per Order</a></li>
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/profit/itemized_profit.php" class="<?= ($current_page == 'itemized_profit.php') ? 'active' : '' ?>">Profit per Order Item</a></li>
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/profit/product_profit.php" class="<?= ($current_page == 'product_profit.php') ? 'active' : '' ?>">Profit per Product</a></li>
                    </ul>
                </div>
            </li>

            <!-- Reports (Level 1) -->
            <li class="nav-item">
                <a class="nav-link-custom <?= (strpos($current_full_url, '/admin/reports/') !== false) ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#reportMgmt" role="button" aria-expanded="false" aria-controls="reportMgmt">
                    <i class="fa-solid fa-file-contract"></i>
                    <span>Reports</span>
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </a>
                <div class="collapse <?= (strpos($current_full_url, '/admin/reports/') !== false) ? 'show' : '' ?>" id="reportMgmt">
                    <ul class="submenu">
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/reports/inventory.php" class="<?= ($current_page == 'inventory.php') ? 'active' : '' ?>">Inventory Report</a></li>
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/reports/sales.php" class="<?= ($current_page == 'sales.php') ? 'active' : '' ?>">Sales Report</a></li>
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/reports/profit.php" class="<?= ($current_page == 'profit.php') ? 'active' : '' ?>">Profit Report</a></li>
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/reports/customer_report.php" class="<?= ($current_page == 'customer_report.php') ? 'active' : '' ?>">Customer Insights</a></li>
                    </ul>
                </div>
            </li>

        <?php elseif ($role_id == 3): // Staff 
        ?>
            <div class="nav-group-title">Operations</div>

            <?php
            $is_orders_module = (strpos($current_full_url, '/admin/orders/') !== false);
            ?>
            <li class="nav-item">
                <a class="nav-link-custom <?= $is_orders_module ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#ordMgmt" role="button" aria-expanded="<?= $is_orders_module ? 'true' : 'false' ?>" aria-controls="ordMgmt">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span>Orders</span>
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </a>
                <div class="collapse <?= $is_orders_module ? 'show' : '' ?>" id="ordMgmt">
                    <ul class="submenu">
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/orders/index.php" class="<?= ($is_orders_module && $current_page == 'index.php') ? 'active' : '' ?>">Order List</a></li>
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/orders/create.php" class="<?= ($is_orders_module && $current_page == 'create.php') ? 'active' : '' ?>">Create New Order</a></li>
                    </ul>
                </div>
            </li>

            <?php
            $is_payments_module = (strpos($current_full_url, '/admin/payments/') !== false);
            ?>
            <li class="nav-item">
                <a class="nav-link-custom <?= $is_payments_module ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#payMgmt" role="button" aria-expanded="<?= $is_payments_module ? 'true' : 'false' ?>" aria-controls="payMgmt">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    <span>Payments</span>
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </a>
                <div class="collapse <?= $is_payments_module ? 'show' : '' ?>" id="payMgmt">
                    <ul class="submenu">
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/payments/index.php" class="<?= ($is_payments_module && $current_page == 'index.php') ? 'active' : '' ?>">Payment History</a></li>
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/payments/add.php" class="<?= ($is_payments_module && $current_page == 'add.php') ? 'active' : '' ?>">Record Payment</a></li>
                    </ul>
                </div>
            </li>

            <?php
            $is_sales_module = (strpos($current_full_url, '/admin/sales/') !== false);
            ?>
            <li class="nav-item">
                <a class="nav-link-custom <?= $is_sales_module ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#saleMgmt" role="button" aria-expanded="<?= $is_sales_module ? 'true' : 'false' ?>" aria-controls="saleMgmt">
                    <i class="fa-solid fa-receipt"></i>
                    <span>Sales Management</span>
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </a>
                <div class="collapse <?= $is_sales_module ? 'show' : '' ?>" id="saleMgmt">
                    <ul class="submenu">
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/sales/index.php" class="<?= ($is_sales_module && $current_page == 'index.php') ? 'active' : '' ?>">Sales Dashboard</a></li>
                        <li class="submenu-item"><a href="<?= BASE_URL ?>admin/orders/create.php" class="<?= ($is_sales_module && $current_page == 'create.php') ? 'active' : '' ?>">New Sale / Order</a></li>
                    </ul>
                </div>
            </li>

        <?php elseif ($role_id == 3): // Staff (Sale Person) 
        ?>
            <div class="nav-group-title">Operations</div>

            <!-- Orders -->
            <?php $is_orders_module = (strpos($current_full_url, '/admin/orders/') !== false); ?>
            <li class="nav-item">
                <a class="nav-link-custom <?= $is_orders_module ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/orders/index.php">
                    <i class="fa-solid fa-shopping-cart"></i>
                    <span>Orders</span>
                </a>
            </li>

            <!-- Payments -->
            <?php $is_payments_module = (strpos($current_full_url, '/admin/payments/') !== false); ?>
            <li class="nav-item">
                <a class="nav-link-custom <?= $is_payments_module ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/payments/index.php">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                    <span>Payments</span>
                </a>
            </li>

            <!-- Sales -->
            <?php $is_sales_module = (strpos($current_full_url, '/admin/sales/') !== false); ?>
            <li class="nav-item">
                <a class="nav-link-custom <?= $is_sales_module ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/sales/index.php">
                    <i class="fa-solid fa-receipt"></i>
                    <span>Sale</span>
                </a>
            </li>

        <?php elseif ($role_id == 2): // Distributor 
        ?>
            <div class="nav-group-title">My Portal</div>

            <!-- Orders -->
            <li class="nav-item">
                <a class="nav-link-custom" href="<?= BASE_URL ?>distributor/orders.php">
                    <i class="fa-solid fa-shopping-cart"></i>
                    <span>My Orders</span>
                </a>
            </li>

            <!-- Payments -->
            <li class="nav-item">
                <a class="nav-link-custom" href="<?= BASE_URL ?>distributor/payments.php">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                    <span>My Payments</span>
                </a>
            </li>

            <!-- Sales -->
            <li class="nav-item">
                <a class="nav-link-custom" href="<?= BASE_URL ?>distributor/sales.php">
                    <i class="fa-solid fa-receipt"></i>
                    <span>My Sales</span>
                </a>
            </li>

        <?php endif; ?>

        <!-- System -->
        <div class="nav-group-title">System</div>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>auth/logout.php" class="nav-link-custom logout-link">
                <i class="fa-solid fa-power-off"></i>
                <span>Logout System</span>
            </a>
        </li>
    </ul>
</aside>

<!-- Bootstrap helper script for sidebar icons and robust toggling -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add logic to rotate Icons on click manually if Bootstrap transitions are slow
        const toggles = document.querySelectorAll('[data-bs-toggle="collapse"]');
        toggles.forEach(toggle => {
            toggle.addEventListener('click', function() {
                // Give extra boost to visibility
                const targetId = this.getAttribute('data-bs-target') || this.getAttribute('href');
                const target = document.querySelector(targetId);
                if (target) {
                    // Wait a tiny bit for bootstrap to do its thing
                    setTimeout(() => {
                        if (target.classList.contains('show')) {
                            console.log('Menu opened:', targetId);
                        }
                    }, 50);
                }
            });
        });
    });
</script>