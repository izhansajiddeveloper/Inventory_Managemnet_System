<?php

/**
 * Sales Analytics - Modern Dashboard
 * Track revenue growth and business performance metrics
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

$page_title = "Sales Analytics";
$page_icon = "fa-chart-line";
$page_description = "Track revenue growth and business performance metrics";

// Filters
$from_date = isset($_GET['from']) ? sanitize($_GET['from']) : date('Y-m-01');
$to_date   = isset($_GET['to'])   ? sanitize($_GET['to'])   : date('Y-m-d');

// Initialize variables
$stats = [
    'total_orders' => 0,
    'gross_revenue' => 0,
    'total_discounts' => 0,
    'net_revenue' => 0,
    'avg_order_value' => 0,
    'completed_rate' => 0
];

$top_products = [];
$recent_orders = [];
$daily_sales = [];
$order_type_stats = ['shop' => 0, 'online' => 0];

$chart_labels = [];
$chart_revenue = [];
$chart_orders = [];

$error_message = '';

try {
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // 1. Summary Metrics
    $stats_query = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
            COALESCE(SUM(total_amount), 0) as gross_revenue,
            COALESCE(SUM(discount), 0) as total_discounts,
            COALESCE(SUM(final_amount), 0) as net_revenue
        FROM orders 
        WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date'";

    $stats_result = mysqli_query($conn, $stats_query);
    if (!$stats_result) {
        throw new Exception(mysqli_error($conn));
    }
    $raw_stats = mysqli_fetch_assoc($stats_result);

    if ($raw_stats) {
        $stats['total_orders'] = (int)$raw_stats['total_orders'];
        $stats['gross_revenue'] = (float)$raw_stats['gross_revenue'];
        $stats['total_discounts'] = (float)$raw_stats['total_discounts'];
        $stats['net_revenue'] = (float)$raw_stats['net_revenue'];

        if ($stats['total_orders'] > 0) {
            $stats['avg_order_value'] = $stats['net_revenue'] / $stats['total_orders'];
            $stats['completed_rate'] = ($raw_stats['completed_orders'] / $stats['total_orders']) * 100;
        }
    }

    // 2. Order Type Breakdown
    $type_query = "
        SELECT order_type, COUNT(*) as count 
        FROM orders 
        WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date'
        GROUP BY order_type";

    $type_result = mysqli_query($conn, $type_query);
    if ($type_result) {
        while ($row = mysqli_fetch_assoc($type_result)) {
            if (isset($order_type_stats[$row['order_type']])) {
                $order_type_stats[$row['order_type']] = (int)$row['count'];
            }
        }
    }

    // 3. Daily Sales (Last 30 Days)
    $daily_query = "
        SELECT DATE(created_at) as date, SUM(final_amount) as revenue, COUNT(*) as orders
        FROM orders 
        WHERE status = 'completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC";

    $daily_result = mysqli_query($conn, $daily_query);

    $daily_map = [];
    if ($daily_result) {
        while ($row = mysqli_fetch_assoc($daily_result)) {
            $daily_map[$row['date']] = $row;
        }
    }

    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $display = date('M d', strtotime($date));
        $chart_labels[] = $display;

        if (isset($daily_map[$date])) {
            $chart_revenue[] = (float)$daily_map[$date]['revenue'];
            $chart_orders[] = (int)$daily_map[$date]['orders'];
        } else {
            $chart_revenue[] = 0;
            $chart_orders[] = 0;
        }
    }

    // 4. Top Selling Products
    $top_query = "
        SELECT 
            p.name, 
            p.sku,
            SUM(oi.quantity) as total_qty, 
            SUM(oi.total) as total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status = 'completed' AND DATE(o.created_at) BETWEEN '$from_date' AND '$to_date'
        GROUP BY oi.product_id
        ORDER BY total_revenue DESC
        LIMIT 10";

    $top_result = mysqli_query($conn, $top_query);
    if ($top_result) {
        while ($row = mysqli_fetch_assoc($top_result)) {
            $top_products[] = $row;
        }
    }

    // 5. Recent High-Value Orders
    $recent_query = "
        SELECT o.id, o.final_amount, o.status, o.created_at, o.order_type
        FROM orders o
        ORDER BY o.created_at DESC
        LIMIT 5";

    $recent_result = mysqli_query($conn, $recent_query);
    if ($recent_result) {
        while ($row = mysqli_fetch_assoc($recent_result)) {
            $recent_orders[] = $row;
        }
    }
} catch (Exception $e) {
    $error_message = "Report Error: " . $e->getMessage();
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
    :root {
        --chart-primary: #4361ee;
        --chart-secondary: #06d6a0;
        --chart-accent: #f72585;
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
        min-height: 250px;
        width: 100%;
    }

    .table th {
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
    }

    .progress-xs {
        height: 6px;
        border-radius: 10px;
    }

    .badge-indicator {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 8px;
    }

    @media print {
        .no-print {
            display: none !important;
        }

        .stat-card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
        }
    }
</style>

<div class="px-4 py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 no-print">
        <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-blue-50 text-blue-600 rounded-4 border border-blue-100">
                <i class="fa-solid <?= $page_icon ?> fa-xl"></i>
            </div>
            <div>
                <h1 class="h4 fw-bold text-slate-900 mb-0"><?= $page_title ?></h1>
                <p class="text-slate-500 small mb-0"><?= $page_description ?></p>
            </div>
        </div>
        <div class="d-flex gap-2 mt-3 mt-sm-0">
            <button class="btn btn-outline-primary rounded-3 px-3" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Print
            </button>
            <button class="btn btn-primary rounded-3 px-4 shadow-sm" onclick="exportCSV()">
                <i class="fa-solid fa-download me-1"></i> Export Data
            </button>
        </div>
    </div>

    <!-- Date Filters -->
    <div class="bg-white p-3 rounded-4 border border-slate-100 shadow-sm mb-4 no-print">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-slate-600">Start Date</label>
                <input type="date" name="from" class="form-control rounded-3" value="<?= $from_date ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-slate-600">End Date</label>
                <input type="date" name="to" class="form-control rounded-3" value="<?= $to_date ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-dark w-100 py-2 rounded-3 fw-bold">Update Analytics</button>
            </div>
            <div class="col-md-2">
                <a href="sales.php" class="btn btn-outline-secondary w-100 py-2 rounded-3 fw-bold">Reset</a>
            </div>
        </form>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger rounded-4"><?= $error_message ?></div>
    <?php endif; ?>

    <!-- KPI Summary Row -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-3">
                        <i class="fa-solid fa-indian-rupee-sign me-1"></i> Net Revenue
                    </span>
                    <i class="fa-solid fa-arrow-up text-success small"></i>
                </div>
                <h3 class="h4 fw-bold text-slate-800 mb-1"><?= format_price($stats['net_revenue']) ?></h3>
                <div class="text-slate-400 small">
                    From <?= $stats['total_orders'] ?> processed orders
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-3">
                        <i class="fa-solid fa-shopping-bag me-1"></i> Avg. Order
                    </span>
                </div>
                <h3 class="h4 fw-bold text-slate-800 mb-1"><?= format_price($stats['avg_order_value']) ?></h3>
                <div class="text-slate-400 small">
                    Average revenue per transaction
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 rounded-3">
                        <i class="fa-solid fa-percent me-1"></i> Success Rate
                    </span>
                </div>
                <h3 class="h4 fw-bold text-slate-800 mb-1"><?= round($stats['completed_rate'], 1) ?>%</h3>
                <div class="text-slate-400 small">
                    Completed vs total orders
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-3">
                        <i class="fa-solid fa-tags me-1"></i> Total Discounts
                    </span>
                </div>
                <h3 class="h4 fw-bold text-slate-800 mb-1"><?= format_price($stats['total_discounts']) ?></h3>
                <div class="text-slate-400 small">
                    Promotional revenue reduction
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <!-- Revenue Trend -->
        <div class="col-xl-8">
            <div class="bg-white rounded-4 shadow-sm p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h5 class="fw-bold text-slate-800 mb-0">30-Day Revenue Momentum</h5>
                    <span class="badge bg-slate-100 text-slate-600 rounded-pill px-3">Daily Trend</span>
                </div>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Order Source Breakdown -->
        <div class="col-xl-4">
            <div class="bg-white rounded-4 shadow-sm p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h5 class="fw-bold text-slate-800 mb-0">Order Source</h5>
                    <span class="badge bg-slate-100 text-slate-600 rounded-pill px-3">Breakdown</span>
                </div>
                <div class="chart-container" style="min-height: 200px;">
                    <canvas id="sourceChart"></canvas>
                </div>
                <div class="mt-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <span class="badge-indicator bg-primary"></span>
                            <span class="small fw-medium text-slate-600">Shop / In-Store</span>
                        </div>
                        <span class="fw-bold text-slate-800"><?= $order_type_stats['shop'] ?></span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="badge-indicator bg-success"></span>
                            <span class="small fw-medium text-slate-600">Online Orders</span>
                        </div>
                        <span class="fw-bold text-slate-800"><?= $order_type_stats['online'] ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="row g-4">
        <!-- Top Products -->
        <div class="col-lg-8">
            <div class="bg-white rounded-4 shadow-sm overflow-hidden h-100">
                <div class="px-4 py-3 bg-slate-50 border-bottom d-flex align-items-center justify-content-between">
                    <h5 class="fw-bold text-slate-800 mb-0">
                        <i class="fa-solid fa-crown me-2 text-warning"></i>Top Selling Products
                    </h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="ps-4">Product Details</th>
                                <th class="text-center">Units Sold</th>
                                <th class="text-end pe-4">Revenue Contrib.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_products)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-5">
                                        <i class="fa-solid fa-box-open fa-2x text-slate-200 mb-2"></i>
                                        <p class="text-slate-400 mb-0">No sales recorded for this period</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_products as $tp): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-slate-800"><?= htmlspecialchars($tp['name']) ?></div>
                                            <small class="text-slate-400 font-monospace"><?= htmlspecialchars($tp['sku']) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-slate-100 text-slate-600 rounded-pill px-3 py-2">
                                                <?= number_format($tp['total_qty']) ?> units
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="fw-bold text-primary"><?= format_price($tp['total_revenue']) ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Activity Feed -->
        <div class="col-lg-4">
            <div class="bg-white rounded-4 shadow-sm overflow-hidden h-100">
                <div class="px-4 py-3 bg-slate-50 border-bottom">
                    <h5 class="fw-bold text-slate-800 mb-0">
                        <i class="fa-solid fa-history me-2 text-info"></i>Recent Transactions
                    </h5>
                </div>
                <div class="p-3 overflow-auto" style="max-height: 450px;">
                    <?php if (empty($recent_orders)): ?>
                        <div class="text-center py-5 text-slate-400">
                            <i class="fa-solid fa-clock-rotate-left fa-2x mb-2 opacity-25"></i>
                            <p>No transactions yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="p-3 border-bottom border-slate-50 last:border-0 hover:bg-slate-50 rounded-3 transition-all mb-2">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <span class="fw-bold text-slate-800">Order #<?= $order['id'] ?></span>
                                        <span class="badge bg-<?= $order['order_type'] == 'shop' ? 'primary' : 'success' ?> bg-opacity-10 text-<?= $order['order_type'] == 'shop' ? 'primary' : 'success' ?> small ms-2 px-2">
                                            <?= strtoupper($order['order_type']) ?>
                                        </span>
                                    </div>
                                    <span class="text-slate-400 small"><?= date('H:i', strtotime($order['created_at'])) ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="fw-bold text-primary"><?= format_price($order['final_amount']) ?></div>
                                    <span class="badge bg-<?= $order['status'] == 'completed' ? 'emerald' : ($order['status'] == 'pending' ? 'amber' : 'rose') ?>-500 text-white rounded-pill px-3 py-1 scale-90">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                </div>
                                <div class="text-slate-400 small mt-1">
                                    <?= date('M d, Y', strtotime($order['created_at'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js and Export -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Revenue Line Chart
        const revCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Daily Revenue',
                    data: <?= json_encode($chart_revenue) ?>,
                    borderColor: '#4361ee',
                    backgroundColor: 'rgba(67, 97, 238, 0.05)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#4361ee',
                    pointBorderWidth: 2
                }, {
                    label: 'Orders',
                    data: <?= json_encode($chart_orders) ?>,
                    borderColor: '#06d6a0',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    tension: 0.4,
                    fill: false,
                    pointRadius: 0,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.03)'
                        },
                        ticks: {
                            callback: function(value) {
                                return 'Rs. ' + value.toLocaleString();
                            }
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            display: false
                        },
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Source Doughnut Chart
        const sourceCtx = document.getElementById('sourceChart').getContext('2d');
        new Chart(sourceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Shop', 'Online'],
                datasets: [{
                    data: [<?= $order_type_stats['shop'] ?>, <?= $order_type_stats['online'] ?>],
                    backgroundColor: ['#4361ee', '#06d6a0'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    });

    function exportCSV() {
        let rows = [
            ["Metric", "Value"],
            ["Total Revenue", "<?= $stats['net_revenue'] ?>"],
            ["Total Orders", "<?= $stats['total_orders'] ?>"],
            ["Avg Order Value", "<?= $stats['avg_order_value'] ?>"],
            ["Success Rate", "<?= $stats['completed_rate'] ?>%"],
            ["", ""],
            ["Product", "Quantity", "Revenue"]
        ];

        <?php foreach ($top_products as $tp): ?>
            rows.push(["<?= addslashes($tp['name']) ?>", "<?= $tp['total_qty'] ?>", "<?= $tp['total_revenue'] ?>"]);
        <?php endforeach; ?>

        let csvContent = "data:text/csv;charset=utf-8," + rows.map(e => e.join(",")).join("\n");
        let encodedUri = encodeURI(csvContent);
        let link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "sales_report_<?= date('Y-m-d') ?>.csv");
        document.body.appendChild(link);
        link.click();
    }
</script>

<?php include '../../includes/footer.php'; ?>