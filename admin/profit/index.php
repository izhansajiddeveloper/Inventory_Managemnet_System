<?php

/**
 * Profit Dashboard - index.php
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
function format_price($amount)
{
    return 'Rs. ' . number_format($amount, 2);
}

function sanitize($data)
{
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

$page_title = "Profit Dashboard";
$page_icon = "fa-chart-line";
$page_description = "Monitor profit performance across all business orders and products";

// Filters
$type_filter = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$search      = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$date_from   = isset($_GET['from']) ? sanitize($_GET['from']) : '';
$date_to     = isset($_GET['to'])   ? sanitize($_GET['to'])   : '';

// Base Query
$query = "
    SELECT pr.*, 
           CASE 
               WHEN pr.reference_type = 'order' THEN 'Order #'
               ELSE 'Product:'
           END as ref_label,
           CASE 
               WHEN pr.reference_type = 'order' THEN CAST(pr.reference_id AS CHAR)
               ELSE p.name
           END as display_name
    FROM profits pr
    LEFT JOIN products p ON (pr.reference_type = 'product' AND pr.reference_id = p.id)
    LEFT JOIN orders o ON (pr.reference_type = 'order' AND pr.reference_id = o.id)
    LEFT JOIN users c ON (o.customer_id = c.id)
    WHERE 1=1";

if (!empty($type_filter)) {
    $query .= " AND pr.reference_type = '$type_filter'";
}

if (!empty($search)) {
    $query .= " AND (pr.reference_id LIKE '%$search%' OR p.name LIKE '%$search%' OR c.name LIKE '%$search%')";
}

if (!empty($date_from)) {
    $query .= " AND DATE(pr.created_at) >= '$date_from'";
}

if (!empty($date_to)) {
    $query .= " AND DATE(pr.created_at) <= '$date_to'";
}

$query .= " ORDER BY pr.created_at DESC";

$results = [];
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $results[] = $row;
    }
}

// Stats
$stats_query = "SELECT 
           SUM(cost_price) as total_cost,
           SUM(selling_price) as total_revenue,
           SUM(profit_amount) as total_profit
        FROM profits
        WHERE 1=1";

// Applying filters to stats too
if (!empty($type_filter)) {
    $stats_query .= " AND reference_type = '$type_filter'";
}
if (!empty($date_from)) {
    $stats_query .= " AND DATE(created_at) >= '$date_from'";
}
if (!empty($date_to)) {
    $stats_query .= " AND DATE(created_at) <= '$date_to'";
}

$stats_result = mysqli_query($conn, $stats_query);
$stats = $stats_result ? mysqli_fetch_assoc($stats_result) : ['total_cost' => 0, 'total_revenue' => 0, 'total_profit' => 0];

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="px-3 py-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-blue-50 text-blue-600 rounded-4 border border-blue-100">
                <i class="fa-solid <?= $page_icon ?> fa-xl"></i>
            </div>
            <div>
                <h1 class="h4 fw-bold text-slate-900 mb-0"><?= $page_title ?></h1>
                <p class="text-slate-500 small mb-0"><?= $page_description ?></p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="order_profit.php" class="btn btn-outline-primary rounded-3 px-3 py-2 fw-bold">
                <i class="fa-solid fa-chart-simple me-1"></i> Order Profit
            </a>
            <a href="product_profit.php" class="btn btn-outline-primary rounded-3 px-3 py-2 fw-bold">
                <i class="fa-solid fa-box me-1"></i> Product Profit
            </a>
            <a href="itemized_profit.php" class="btn btn-outline-primary rounded-3 px-3 py-2 fw-bold">
                <i class="fa-solid fa-list me-1"></i> Itemized
            </a>
        </div>
    </div>

    <!-- Stats summary group -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="p-4 bg-white rounded-4 border border-slate-100 shadow-sm text-center">
                <div class="small fw-bold text-slate-400 uppercase tracking-wider mb-2">Total Costs</div>
                <div class="h4 fw-bold text-slate-900 mb-0"><?= format_price($stats['total_cost'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-4 bg-white rounded-4 border border-slate-100 shadow-sm text-center">
                <div class="small fw-bold text-slate-400 uppercase tracking-wider mb-2">Total Revenue</div>
                <div class="h4 fw-bold text-blue-600 mb-0"><?= format_price($stats['total_revenue'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-4 bg-white rounded-4 border border-slate-100 shadow-sm text-center">
                <div class="small fw-bold text-slate-400 uppercase tracking-wider mb-2">Total Combined Profit</div>
                <div class="h4 fw-bold <?= ($stats['total_profit'] ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600' ?> mb-0">
                    <?= format_price($stats['total_profit'] ?? 0) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-white p-3 rounded-4 border border-slate-100 shadow-sm mb-4">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-lg-3">
                <label class="form-label small fw-bold text-slate-600 mb-1">Search ID/Name</label>
                <div class="position-relative">
                    <i class="fa-solid fa-magnifying-glass position-absolute top-50 translate-middle-y ms-3 text-slate-400"></i>
                    <input type="text" name="search" class="form-control rounded-3 py-2 ps-5 border-slate-200" placeholder="Search product or reference..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-lg-2">
                <label class="form-label small fw-bold text-slate-600 mb-1">Type</label>
                <select name="type" class="form-select rounded-3 py-2 border-slate-200">
                    <option value="">All Types</option>
                    <option value="order" <?= $type_filter == 'order' ? 'selected' : '' ?>>Order Profits</option>
                    <option value="product" <?= $type_filter == 'product' ? 'selected' : '' ?>>Product Margins</option>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label small fw-bold text-slate-600 mb-1">From Date</label>
                <input type="date" name="from" class="form-control rounded-3 py-2 border-slate-200" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="col-lg-2">
                <label class="form-label small fw-bold text-slate-600 mb-1">To Date</label>
                <input type="date" name="to" class="form-control rounded-3 py-2 border-slate-200" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="col-lg-3 d-flex gap-2 align-self-end mt-2">
                <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-bold shadow-sm">Filter Reports</button>
                <a href="index.php" class="btn btn-light w-50 py-2 rounded-3 fw-bold border border-slate-200">Reset</a>
            </div>
        </form>
    </div>

    <!-- Profit Table -->
    <div class="bg-white rounded-4 border border-slate-100 shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 small uppercase fw-bold tracking-wider">
                        <th class="ps-4 py-3">Reference</th>
                        <th class="py-3">Type</th>
                        <th class="py-3 text-end">Cost Price</th>
                        <th class="py-3 text-end">Selling Price</th>
                        <th class="py-3 text-end">Discount</th>
                        <th class="py-3 text-end pe-4">Profit Amount</th>
                        <th class="py-3">Created On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($results)): ?>
                        <?php foreach ($results as $r): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-slate-800">
                                        <?= $r['ref_label'] ?> <?= htmlspecialchars($r['display_name']) ?>
                                    </div>
                                    <?php if ($r['reference_type'] == 'order'): ?>
                                        <div class="small text-slate-400">Order Migration Linked</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge border py-2 px-3 rounded-pill fw-bold text-uppercase <?= $r['reference_type'] == 'order' ? 'bg-indigo-50 text-indigo-700 border-indigo-100' : 'bg-emerald-50 text-emerald-700 border-emerald-100' ?>" style="font-size: 10px;">
                                        <?= $r['reference_type'] ?>
                                    </span>
                                </td>
                                <td class="text-end fw-semibold text-slate-600"><?= format_price($r['cost_price']) ?></td>
                                <td class="text-end fw-semibold text-blue-600"><?= format_price($r['selling_price']) ?></td>
                                <td class="text-end text-rose-500"><?= ($r['discount'] ?? 0) > 0 ? "- " . format_price($r['discount']) : "—" ?></td>
                                <td class="text-end pe-4">
                                    <div class="fw-black h6 mb-0 <?= ($r['profit_amount'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700 bg-rose-50 px-2 py-1 rounded d-inline-block' ?>">
                                        <?= format_price($r['profit_amount']) ?>
                                    </div>
                                </td>
                                <td class="small text-slate-400">
                                    <?= date('d M Y, h:i A', strtotime($r['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="mb-3 text-slate-200"><i class="fa-solid fa-chart-pie fa-4x"></i></div>
                                <h5 class="fw-bold text-slate-600">No Profit Records Found</h5>
                                <p class="text-slate-400 px-4">Ensure calculations have been triggered after orders are fully paid.</p>
                                <div class="mt-3">
                                    <a href="calculate.php?action=sync_all_orders" class="btn btn-sm btn-primary">Sync Orders</a>
                                    <a href="calculate.php?action=sync_all_products" class="btn btn-sm btn-outline-primary">Sync Products</a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    @media print {

        .btn,
        .modern-sidebar,
        .includes-navbar,
        form,
        .btn-outline-primary {
            display: none !important;
        }

        .bg-slate-50 {
            -webkit-print-color-adjust: exact;
        }
    }
</style>

<?php include '../../includes/footer.php'; ?>