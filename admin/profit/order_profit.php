<?php
/**
 * Detailed Order-wise Profit Report
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';

authorize([ROLE_ADMIN]);

$page_title = "Order Profit Analysis";
$page_icon = "fa-cart-shopping";
$page_description = "Detailed breakdown of profit generated per individual sales order";

// Dates
$from = isset($_GET['from']) ? sanitize($_GET['from']) : date('Y-m-01'); // default this month
$to   = isset($_GET['to'])   ? sanitize($_GET['to'])   : date('Y-m-t');

$query = "
    SELECT pr.*, 
           o.order_type,
           c.name as customer_name,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM profits pr
    JOIN orders o ON pr.reference_id = o.id
    LEFT JOIN customers c ON o.customer_id = c.id
    WHERE pr.reference_type = 'order'";

$params = [];
if ($from) { $query .= " AND DATE(pr.created_at) >= ?"; $params[] = $from; }
if ($to)   { $query .= " AND DATE(pr.created_at) <= ?"; $params[] = $to; }

$query .= " ORDER BY pr.profit_amount DESC"; // Ranking most profitable

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // Aggregates
    $agg = $pdo->prepare("
        SELECT 
           COUNT(*) as total_orders,
           AVG(profit_amount) as avg_profit,
           SUM(profit_amount) as net_profit
        FROM profits 
        WHERE reference_type = 'order' 
        AND DATE(created_at) >= ? AND DATE(created_at) <= ?
    ");
    $agg->execute([$from, $to]);
    $summary = $agg->fetch();

} catch (PDOException $e) { $orders = []; }

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="px-3 py-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 fw-bold text-slate-900 mb-0"><?= $page_title ?></h1>
            <p class="text-slate-500 small mb-0"><?= $page_description ?></p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-dark rounded-3 px-3 py-2 fw-bold" onclick="window.print()">
                <i class="fa-solid fa-print"></i> Print Report
            </button>
        </div>
    </div>

    <!-- Summary Section -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="bg-indigo-600 p-4 rounded-4 shadow-sm text-white text-center border-0">
                <div class="small fw-bold text-indigo-100 uppercase tracking-wider mb-2">Orders Analyzed</div>
                <div class="h3 fw-bold mb-0"><?= $summary['total_orders'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="bg-emerald-600 p-4 rounded-4 shadow-sm text-white text-center border-0">
                <div class="small fw-bold text-emerald-100 uppercase tracking-wider mb-2">Net Period Profit</div>
                <div class="h3 fw-bold mb-0"><?= format_price($summary['net_profit'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="bg-blue-600 p-4 rounded-4 shadow-sm text-white text-center border-0">
                <div class="small fw-bold text-blue-100 uppercase tracking-wider mb-2">Average Profit / Order</div>
                <div class="h3 fw-bold mb-0"><?= format_price($summary['avg_profit'] ?? 0) ?></div>
            </div>
        </div>
    </div>

    <!-- Date Filter Bar -->
    <div class="bg-white p-3 rounded-4 border border-slate-100 shadow-sm mb-4">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-lg-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-slate-200">From</span>
                    <input type="date" name="from" class="form-control rounded-end-3 py-2 border-slate-200" value="<?= htmlspecialchars($from) ?>">
                </div>
            </div>
            <div class="col-lg-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-slate-200">To</span>
                    <input type="date" name="to" class="form-control rounded-end-3 py-2 border-slate-200" value="<?= htmlspecialchars($to) ?>">
                </div>
            </div>
            <div class="col-lg-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-bold shadow-sm">Update Report</button>
                <a href="index.php" class="btn btn-light w-50 py-2 rounded-3 fw-bold border border-slate-200">Overview</a>
            </div>
        </form>
    </div>

    <!-- Details Table -->
    <div class="bg-white rounded-4 border border-slate-100 shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 small uppercase fw-bold tracking-wider">
                        <th class="ps-4 py-3">Order ID</th>
                        <th class="py-3">Type</th>
                        <th class="py-3">Customer</th>
                        <th class="py-3 text-center">Items</th>
                        <th class="py-3 text-end">Net Bill</th>
                        <th class="py-3 text-end">Order Cost</th>
                        <th class="py-3 text-end pe-4 text-emerald-600">Pure Profit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-bold text-slate-900">#ORD-<?= str_pad($o['reference_id'], 5, '0', STR_PAD_LEFT) ?></span>
                                </td>
                                <td>
                                    <span class="badge rounded-pill <?= $o['order_type'] == 'shop' ? 'bg-indigo-50 text-indigo-700' : 'bg-blue-50 text-blue-700' ?> px-3 py-2 border small uppercase">
                                        <?= $o['order_type'] ?>
                                    </span>
                                </td>
                                <td class="fw-semibold text-slate-700"><?= htmlspecialchars($o['customer_name'] ?? 'Guest Customer') ?></td>
                                <td class="text-center font-monospace small"><span class="badge bg-slate-100 text-slate-600 p-2"><?= $o['item_count'] ?> units</span></td>
                                <td class="text-end fw-bold text-slate-900"><?= format_price($o['selling_price']) ?></td>
                                <td class="text-end text-slate-500 font-monospace small"><?= format_price($o['cost_price']) ?></td>
                                <td class="text-end pe-4">
                                    <div class="fw-black h6 mb-0 text-emerald-600">
                                        + <?= format_price($o['profit_amount']) ?>
                                    </div>
                                    <div class="small" style="font-size: 10px; opacity: 0.6;">
                                        Margin: <?= number_format(($o['profit_amount'] / $o['selling_price']) * 100, 1) ?>%
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-5">No order-profit data for this period.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
