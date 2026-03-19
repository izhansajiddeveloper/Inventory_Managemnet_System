<?php
/**
 * Profit Statistics Report - Modern Dashboard
 * Analysis of business margins and net yield
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';

authorize([ROLE_ADMIN]);

$page_title = "Profit Analytics";
$page_icon = "fa-chart-pie";
$page_description = "Analyze business profitability and net business yield";

// Filters
$from_date = isset($_GET['from']) ? sanitize($_GET['from']) : date('Y-m-01');
$to_date   = isset($_GET['to'])   ? sanitize($_GET['to'])   : date('Y-m-d');

// Initialize variables
$stats = [
    'total_cost' => 0,
    'total_revenue' => 0,
    'gross_profit' => 0,
    'net_profit' => 0,
    'margin_pct' => 0,
    'total_discounts' => 0
];

$daily_profit = [];
$profit_labels = [];
$profit_data = [];

$error_message = '';

try {
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }

    // 1. Overall Stats for period
    $stats_query = $pdo->prepare("
        SELECT 
           COALESCE(SUM(p.cost_price * oi.quantity), 0) as total_cost,
           COALESCE(SUM(oi.total), 0) as total_revenue,
           COALESCE(SUM(oi.total - (p.cost_price * oi.quantity)), 0) as gross_profit
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        WHERE o.status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?
    ");
    $stats_query->execute([$from_date, $to_date]);
    $res = $stats_query->fetch(PDO::FETCH_ASSOC);

    // Get discounts for net profit
    $discount_query = $pdo->prepare("
        SELECT COALESCE(SUM(discount), 0) as total_discounts 
        FROM orders 
        WHERE status = 'completed' AND DATE(created_at) BETWEEN ? AND ?
    ");
    $discount_query->execute([$from_date, $to_date]);
    $disc_res = $discount_query->fetch(PDO::FETCH_ASSOC);

    if ($res) {
        $stats['total_cost'] = (float)$res['total_cost'];
        $stats['total_revenue'] = (float)$res['total_revenue'];
        $stats['gross_profit'] = (float)$res['gross_profit'];
        $stats['total_discounts'] = (float)$disc_res['total_discounts'];
        $stats['net_profit'] = $stats['gross_profit'] - $stats['total_discounts'];
        
        if ($stats['total_revenue'] > 0) {
            $stats['margin_pct'] = ($stats['net_profit'] / $stats['total_revenue']) * 100;
        }
    }

    // 2. Daily Profit Trend (Last 30 Days)
    $trend_query = $pdo->query("
        SELECT 
            DATE(o.created_at) as date, 
            SUM(oi.total - (p.cost_price * oi.quantity)) as gross_profit,
            (SELECT COALESCE(SUM(discount), 0) FROM orders WHERE status = 'completed' AND DATE(created_at) = DATE(o.created_at)) as daily_discount
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        WHERE o.status = 'completed' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
        GROUP BY DATE(o.created_at)
        ORDER BY date ASC
    ");
    $trend_raw = $trend_query->fetchAll(PDO::FETCH_ASSOC);
    
    $trend_map = [];
    foreach ($trend_raw as $row) {
        $trend_map[$row['date']] = (float)$row['gross_profit'] - (float)$row['daily_discount'];
    }

    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $display = date('M d', strtotime($date));
        $profit_labels[] = $display;
        $profit_data[] = $trend_map[$date] ?? 0;
    }

    // 3. Weekly/Daily Breakdown for Table
    $breakdown_query = $pdo->prepare("
        SELECT DATE(o.created_at) as date, 
               SUM(oi.total) as revenue,
               SUM(p.cost_price * oi.quantity) as cost,
               SUM(oi.total - (p.cost_price * oi.quantity)) as gross_profit,
               (SELECT COALESCE(SUM(discount), 0) FROM orders WHERE status = 'completed' AND DATE(created_at) = DATE(o.created_at)) as discount
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        WHERE o.status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY DATE(o.created_at)
        ORDER BY DATE(o.created_at) DESC
    ");
    $breakdown_query->execute([$from_date, $to_date]);
    $daily_profit = $breakdown_query->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Profit Report Error: " . $e->getMessage();
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
    :root {
        --chart-primary: #4361ee;
        --chart-success: #06d6a0;
        --chart-warning: #ffb703;
        --chart-danger: #ef476f;
    }

    .stat-card {
        transition: all 0.2s ease;
        border: 1px solid rgba(0, 0, 0, 0.05);
        background: white;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
    }

    .chart-container {
        position: relative;
        min-height: 300px;
        width: 100%;
    }

    .table th {
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
    }

    @media print {
        .no-print { display: none !important; }
        .stat-card { border: 1px solid #ddd !important; box-shadow: none !important; }
    }
</style>

<div class="px-4 py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 no-print">
        <div>
            <h1 class="h3 fw-bold text-slate-800 mb-1"><?= $page_title ?></h1>
            <p class="text-slate-500 mb-0"><?= $page_description ?></p>
        </div>
        <div class="d-flex gap-2 mt-3 mt-sm-0">
            <button class="btn btn-outline-primary rounded-3 px-3" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Print Audit
            </button>
            <button class="btn btn-primary rounded-3 px-4 shadow-sm">
                <i class="fa-solid fa-file-export me-1"></i> Export PDF
            </button>
        </div>
    </div>

    <!-- Date Filters -->
    <div class="bg-white p-3 rounded-4 border border-slate-100 shadow-sm mb-4 no-print">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-slate-600">Start Date</label>
                <input type="date" name="from" class="form-control rounded-3" value="<?= $from_date ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-slate-600">End Date</label>
                <input type="date" name="to" class="form-control rounded-3" value="<?= $to_date ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-dark w-100 py-2 rounded-3 fw-bold shadow-sm">Execute Audit</button>
            </div>
        </form>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger rounded-4 shadow-sm"><?= $error_message ?></div>
    <?php endif; ?>

    <!-- KPI Summary Row -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-3">
                        <i class="fa-solid fa-money-bill-wave me-1"></i> Operating Cost
                    </span>
                    <i class="fa-solid fa-arrow-down text-danger small"></i>
                </div>
                <h3 class="h4 fw-bold text-slate-800 mb-1"><?= format_price($stats['total_cost']) ?></h3>
                <div class="text-slate-400 small">Total cost of goods sold</div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card rounded-4 p-4 shadow-sm h-100 border-start border-4 border-success">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-3">
                        <i class="fa-solid fa-chart-line me-1"></i> Net Yield
                    </span>
                    <i class="fa-solid fa-arrow-up text-success small"></i>
                </div>
                <h3 class="h4 fw-bold text-success mb-1"><?= format_price($stats['net_profit']) ?></h3>
                <div class="text-slate-400 small">Total net business profit</div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-3">
                        <i class="fa-solid fa-percent me-1"></i> Profit Margin
                    </span>
                </div>
                <h3 class="h4 fw-bold text-slate-800 mb-1"><?= number_format($stats['margin_pct'], 1) ?>%</h3>
                <div class="text-slate-400 small">Overall profitability ratio</div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 rounded-3">
                        <i class="fa-solid fa-wallet me-1"></i> Total Revenue
                    </span>
                </div>
                <h3 class="h4 fw-bold text-slate-800 mb-1"><?= format_price($stats['total_revenue']) ?></h3>
                <div class="text-slate-400 small">Gross cash inflow entry</div>
            </div>
        </div>
    </div>

    <!-- Chart Row -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="bg-white rounded-4 shadow-sm p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h5 class="fw-bold text-slate-800 mb-0">30-Day Profit Sensitivity Analysis</h5>
                    <div class="d-flex gap-2">
                        <span class="badge bg-slate-100 text-slate-600 rounded-pill px-3">Daily Net Yield</span>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="profitTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Row -->
    <div class="row g-4">
        <div class="col-12">
            <div class="bg-white rounded-4 shadow-sm overflow-hidden border border-slate-100">
                <div class="px-4 py-3 bg-slate-50 border-bottom">
                    <h5 class="fw-bold text-slate-800 mb-0">
                        <i class="fa-solid fa-table-list me-2 text-primary"></i>Daily Profit Breakdown
                    </h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="ps-4">Audit Date</th>
                                <th class="text-end">Revenue</th>
                                <th class="text-end">Cost</th>
                                <th class="text-end">Gross Margin</th>
                                <th class="text-end">Discount</th>
                                <th class="text-end pe-4">Net Yield</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daily_profit)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="fa-solid fa-receipt fa-2x text-slate-200 mb-2"></i>
                                        <p class="text-slate-400 mb-0">No business transactions audit data available</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($daily_profit as $t): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-slate-800"><?= date('D, d M Y', strtotime($t['date'])) ?></td>
                                        <td class="text-end"><?= format_price($t['revenue']) ?></td>
                                        <td class="text-end text-slate-500"><?= format_price($t['cost']) ?></td>
                                        <td class="text-end fw-medium text-slate-700"><?= format_price($t['gross_profit']) ?></td>
                                        <td class="text-end text-rose-500">- <?= format_price($t['discount']) ?></td>
                                        <td class="text-end pe-4">
                                            <?php $net = (float)$t['gross_profit'] - (float)$t['discount']; ?>
                                            <span class="badge <?= $net >= 0 ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-rose-50 text-rose-700 border-rose-100' ?> border rounded-pill px-3 py-2 fw-bold">
                                                <?= format_price($net) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('profitTrendChart').getContext('2d');
        const profitGradient = ctx.createLinearGradient(0, 0, 0, 400);
        profitGradient.addColorStop(0, 'rgba(6, 214, 160, 0.2)');
        profitGradient.addColorStop(1, 'rgba(6, 214, 160, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($profit_labels) ?>,
                datasets: [{
                    label: 'Net Daily Profit',
                    data: <?= json_encode($profit_data) ?>,
                    borderColor: '#06d6a0',
                    backgroundColor: profitGradient,
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#06d6a0',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.03)' },
                        ticks: {
                            callback: value => 'Rs. ' + value.toLocaleString()
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    });
</script>

<?php include '../../includes/footer.php'; ?>
