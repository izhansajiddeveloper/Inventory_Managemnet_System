<?php

/**
 * Itemized Profit Report
 * Displays profit for every product sold within each order
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

$page_title = "Itemized Order Profit";
$page_icon = "fa-list-check";
$page_description = "Breakdown of profit per product for every completed order";

$search    = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$date_from = isset($_GET['from']) ? sanitize($_GET['from']) : '';
$date_to   = isset($_GET['to'])   ? sanitize($_GET['to'])   : '';

// Primary Query: Explode orders into items and calculate individual profit
$query = "
    SELECT oi.id as item_ref_id,
           oi.order_id,
           oi.quantity,
           oi.price as unit_sell_price,
           oi.total as item_total_sell,
           p.name as product_name,
           p.cost_price as unit_cost_price,
           (p.cost_price * oi.quantity) as total_item_cost,
           (oi.total - (p.cost_price * oi.quantity)) as item_profit,
           o.created_at as order_date,
           o.discount as order_wide_discount,
           c.name as customer_name
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN users c ON o.customer_id = c.id
    WHERE o.status = 'completed'";

if (!empty($search)) {
    $query .= " AND (o.id LIKE '%$search%' OR p.name LIKE '%$search%' OR c.name LIKE '%$search%')";
}

if (!empty($date_from)) {
    $query .= " AND DATE(o.created_at) >= '$date_from'";
}

if (!empty($date_to)) {
    $query .= " AND DATE(o.created_at) <= '$date_to'";
}

$query .= " ORDER BY o.created_at DESC";

$items = [];
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
}

// Stats: Aggregated from the filtered view
$stats_query = "
    SELECT 
       SUM(p.cost_price * oi.quantity) as total_cost,
       SUM(oi.total) as total_revenue,
       SUM(oi.total - (p.cost_price * oi.quantity)) as total_gross_profit,
       (SELECT SUM(discount) FROM orders WHERE status = 'completed'";

if (!empty($date_from)) $stats_query .= " AND DATE(created_at) >= '$date_from'";
if (!empty($date_to))   $stats_query .= " AND DATE(created_at) <= '$date_to'";

$stats_query .= ") as total_discounts
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE o.status = 'completed'";

if (!empty($date_from)) $stats_query .= " AND DATE(o.created_at) >= '$date_from'";
if (!empty($date_to))   $stats_query .= " AND DATE(o.created_at) <= '$date_to'";

$stats_result = mysqli_query($conn, $stats_query);
$stats = $stats_result ? mysqli_fetch_assoc($stats_result) : ['total_cost' => 0, 'total_revenue' => 0, 'total_gross_profit' => 0, 'total_discounts' => 0];

// Net profit after order-wide discounts
$net_profit = ($stats['total_gross_profit'] ?? 0) - ($stats['total_discounts'] ?? 0);

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
        <div>
            <button class="btn btn-dark rounded-3 px-3 py-2 fw-bold" onclick="window.print()">
                <i class="fa-solid fa-file-export me-1"></i> Export PDF
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="p-4 bg-white rounded-4 border-start border-5 border-slate-300 shadow-sm">
                <div class="small fw-bold text-slate-400 uppercase mb-1">Total Item Cost</div>
                <div class="h5 fw-bold mb-0"><?= format_price($stats['total_cost'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="p-4 bg-white rounded-4 border-start border-5 border-blue-400 shadow-sm">
                <div class="small fw-bold text-slate-400 uppercase mb-1">Total Sales Value</div>
                <div class="h5 fw-bold text-blue-600 mb-0"><?= format_price(($stats['total_revenue'] ?? 0)) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="p-4 bg-white rounded-4 border-start border-5 border-amber-400 shadow-sm">
                <div class="small fw-bold text-slate-400 uppercase mb-1">Discounts Given</div>
                <div class="h5 fw-bold text-amber-600 mb-0">- <?= format_price($stats['total_discounts'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="p-4 bg-white rounded-4 border-start border-5 border-emerald-400 shadow-sm">
                <div class="small fw-bold text-slate-400 uppercase mb-1">Net Actual Profit</div>
                <div class="h5 fw-bold text-emerald-600 mb-0"><?= format_price($net_profit ?? 0) ?></div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white p-3 rounded-4 border border-slate-100 shadow-sm mb-4 no-print">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label small fw-bold text-slate-600">Search Product or Order</label>
                <div class="position-relative">
                    <i class="fa-solid fa-search position-absolute top-50 translate-middle-y ms-3 text-slate-300"></i>
                    <input type="text" name="search" class="form-control rounded-3 ps-5" placeholder="Order ID, Product Name..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-lg-2">
                <label class="form-label small fw-bold text-slate-600">From Date</label>
                <input type="date" name="from" class="form-control rounded-3" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="col-lg-2">
                <label class="form-label small fw-bold text-slate-600">To Date</label>
                <input type="date" name="to" class="form-control rounded-3" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="col-lg-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-bold">Filter Analysis</button>
                <a href="itemized_profit.php" class="btn btn-light border w-50 py-2 rounded-3 fw-bold">Reset</a>
            </div>
        </form>
    </div>

    <!-- Report Table -->
    <div class="bg-white rounded-4 border border-slate-100 shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 small uppercase fw-bold tracking-wider">
                        <th class="ps-4 py-3">Order Details</th>
                        <th class="py-3">Product Item</th>
                        <th class="py-3 text-center">Qty</th>
                        <th class="py-3 text-end">Sourcing/Cost</th>
                        <th class="py-3 text-end">Selling Price</th>
                        <th class="py-3 text-end">Unit Profit</th>
                        <th class="py-3 text-end pe-4 text-emerald-600">Total Item Profit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($items)): ?>
                        <?php
                        $current_order_id = null;
                        foreach ($items as $item):
                            $is_new_order = ($item['order_id'] !== $current_order_id);
                            $current_order_id = $item['order_id'];

                            if ($is_new_order):
                        ?>
                                <tr class="bg-slate-50 border-top border-slate-200">
                                    <td colspan="7" class="ps-4 py-2">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="fw-black text-slate-900 uppercase" style="letter-spacing: 0.5px;">
                                                <i class="fa-solid fa-receipt me-2 opacity-50"></i>Order #ORD-<?= str_pad($item['order_id'], 5, '0', STR_PAD_LEFT) ?>
                                                <span class="mx-2 text-slate-300">|</span>
                                                <span class="small fw-bold text-slate-500"><?= htmlspecialchars($item['customer_name'] ?? 'Walk-in') ?></span>
                                            </div>
                                            <div class="small text-slate-400 fw-bold me-3">
                                                <?= date('d M Y, h:i A', strtotime($item['order_date'])) ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <td class="ps-4"></td>
                                <td>
                                    <div class="fw-bold text-slate-700"><?= htmlspecialchars($item['product_name']) ?></div>
                                    <div class="small text-slate-400" style="font-size: 10px;">SKU: <?= htmlspecialchars($item['item_ref_id']) ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge border border-slate-200 text-slate-600 px-2 py-1"><?= $item['quantity'] ?></span>
                                </td>
                                <td class="text-end text-slate-500 font-monospace small">
                                    <?= format_price($item['unit_cost_price']) ?>
                                </td>
                                <td class="text-end text-blue-600 font-monospace small">
                                    <?= format_price($item['unit_sell_price']) ?>
                                </td>
                                <td class="text-end fw-bold text-slate-600 small">
                                    <?= format_price($item['unit_sell_price'] - $item['unit_cost_price']) ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="fw-black h6 mb-0 <?= $item['item_profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' ?>">
                                        <?= ($item['item_profit'] >= 0 ? '+' : '') . format_price($item['item_profit']) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (($stats['total_discounts'] ?? 0) > 0): ?>
                            <tr class="bg-rose-50 border-top-2 border-rose-100">
                                <td colspan="5" class="ps-4 py-3 text-rose-800 fw-bold italic">Adjusting for Order-Wide Discounts</td>
                                <td class="text-end pe-4 text-rose-700 fw-bold">- <?= format_price($stats['total_discounts']) ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">No itemized profit data found for the selected filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>