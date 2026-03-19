<?php
/**
 * Customer Intelligence & Retention Audit - Modern Dashboard
 * Analysis of customer lifecycle, spend patterns and business loyalty
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';

authorize([ROLE_ADMIN]);

$page_title = "Buyer Intelligence";
$page_icon = "fa-users-viewfinder";
$page_description = "Monitor customer & distributor acquisition metrics and lifecycle revenue performance";

// Filters
$from_date = isset($_GET['from']) ? sanitize($_GET['from']) : date('Y-m-01');
$to_date   = isset($_GET['to'])   ? sanitize($_GET['to'])   : date('Y-m-d');

// Initialize variables
$stats = [
    'total_customers' => 0,
    'new_customers' => 0,
    'avg_customer_ltv' => 0,
    'repeat_rate' => 0
];

$acquisition_data = [];
$segmentation_data = [0, 0, 0]; // 1 Order, 2-5 Orders, 6+ Orders
$top_customers = [];

try {
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }

    // 1. Core Summary Stats
    $total_cust_q = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role_id IN (" . ROLE_CUSTOMER . ", " . ROLE_DISTRIBUTOR . ")");
    $stats['total_customers'] = (int)$total_cust_q->fetch(PDO::FETCH_ASSOC)['count'];

    $new_cust_q = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role_id IN (" . ROLE_CUSTOMER . ", " . ROLE_DISTRIBUTOR . ") AND DATE(created_at) BETWEEN ? AND ?");
    $new_cust_q->execute([$from_date, $to_date]);
    $stats['new_customers'] = (int)$new_cust_q->fetch(PDO::FETCH_ASSOC)['count'];

    // LTV and Repeat Rate
    $ltv_query = $pdo->query("
        SELECT 
            COUNT(DISTINCT customer_id) as ordering_customers,
            SUM(final_amount) as total_revenue,
            SUM(CASE WHEN order_count > 1 THEN 1 ELSE 0 END) as repeat_customers
        FROM (
            SELECT customer_id, SUM(final_amount) as final_amount, COUNT(*) as order_count 
            FROM orders 
            WHERE status = 'completed'
            GROUP BY customer_id
        ) as customer_orders
    ");
    $ltv_res = $ltv_query->fetch(PDO::FETCH_ASSOC);

    if ($ltv_res && $ltv_res['ordering_customers'] > 0) {
        $stats['avg_customer_ltv'] = (float)$ltv_res['total_revenue'] / $ltv_res['ordering_customers'];
        $stats['repeat_rate'] = ((int)$ltv_res['repeat_customers'] / $ltv_res['ordering_customers']) * 100;
    }

    // 2. Acquisition Trend (Last 30 Days)
    $trend_query = $pdo->query("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM users 
        WHERE role_id IN (" . ROLE_CUSTOMER . ", " . ROLE_DISTRIBUTOR . ") AND created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $trend_raw = $trend_query->fetchAll(PDO::FETCH_ASSOC);
    $trend_map = [];
    foreach ($trend_raw as $row) { $trend_map[$row['date']] = (int)$row['count']; }

    $trend_labels = [];
    $trend_values = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $trend_labels[] = date('M d', strtotime($date));
        $trend_values[] = $trend_map[$date] ?? 0;
    }

    // 3. Customer Segmentation
    $seg_query = $pdo->query("
        SELECT 
            SUM(CASE WHEN orders = 1 THEN 1 ELSE 0 END) as single,
            SUM(CASE WHEN orders BETWEEN 2 AND 5 THEN 1 ELSE 0 END) as frequent,
            SUM(CASE WHEN orders > 5 THEN 1 ELSE 0 END) as elite
        FROM (SELECT customer_id, COUNT(*) as orders FROM orders WHERE status = 'completed' GROUP BY customer_id) as order_counts
    ");
    $seg_res = $seg_query->fetch(PDO::FETCH_ASSOC);
    if ($seg_res) {
        $segmentation_data = [(int)$seg_res['single'], (int)$seg_res['frequent'], (int)$seg_res['elite']];
    }

    // 4. Elite Customer List
    $elite_query = $pdo->prepare("
        SELECT 
            u.name, u.email, u.phone, u.role_id,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.final_amount), 0) as total_spend,
            MAX(o.created_at) as last_order_date
        FROM users u
        JOIN orders o ON u.id = o.customer_id
        WHERE o.status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY u.id
        ORDER BY total_spend DESC
        LIMIT 10
    ");
    $elite_query->execute([$from_date, $to_date]);
    $top_customers = $elite_query->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Customer Audit Error: " . $e->getMessage();
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
    :root {
        --color-new: #4cc9f0;
        --color-repeat: #4361ee;
        --color-elite: #f72585;
    }

    .stat-card {
        transition: all 0.2s ease;
        border: 1px solid rgba(0, 0, 0, 0.05);
        background: white;
    }

    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important; }

    .chart-container { position: relative; min-height: 250px; width: 100%; }

    .table th { font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; }

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
                <i class="fa-solid fa-print me-1"></i> Print
            </button>
            <button class="btn btn-primary rounded-3 px-4 shadow-sm">
                <i class="fa-solid fa-cloud-arrow-down me-1"></i> Export Data
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
                <button type="submit" class="btn btn-dark w-100 py-2 rounded-3 fw-bold shadow-sm">Update Analytics</button>
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
                        <i class="fa-solid fa-users me-1"></i> Total Base
                    </span>
                </div>
                <h3 class="h4 fw-bold text-slate-800 mb-1"><?= number_format($stats['total_customers']) ?></h3>
                <div class="text-slate-400 small">Registered customers in system</div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card rounded-4 p-4 shadow-sm h-100 border-start border-4 border-info">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-3">
                        <i class="fa-solid fa-user-plus me-1"></i> New Joined
                    </span>
                </div>
                <h3 class="h4 fw-bold text-slate-800 mb-1"><?= number_format($stats['new_customers']) ?></h3>
                <div class="text-slate-400 small">Acquired in this period</div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-3">
                        <i class="fa-solid fa-hands-holding-circle me-1"></i> Avg. LTV
                    </span>
                </div>
                <h3 class="h4 fw-bold text-slate-800 mb-1"><?= format_price($stats['avg_customer_ltv']) ?></h3>
                <div class="text-slate-400 small">Average value per customer</div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 rounded-3">
                        <i class="fa-solid fa-rotate me-1"></i> Repeat Rate
                    </span>
                </div>
                <h3 class="h4 fw-bold text-slate-800 mb-1"><?= number_format($stats['repeat_rate'], 1) ?>%</h3>
                <div class="text-slate-400 small">Returning vs one-time customers</div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="bg-white rounded-4 shadow-sm p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h5 class="fw-bold text-slate-800 mb-0">30-Day Acquisition Curve</h5>
                    <span class="badge bg-slate-100 text-slate-600 rounded-pill px-3">New Registration Trend</span>
                </div>
                <div class="chart-container">
                    <canvas id="acquisitionChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="bg-white rounded-4 shadow-sm p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h5 class="fw-bold text-slate-800 mb-0">Loyalty Segmentation</h5>
                </div>
                <div class="chart-container" style="min-height: 200px;">
                    <canvas id="loyaltyChart"></canvas>
                </div>
                <div class="mt-4">
                    <div class="d-flex align-items-center justify-content-between mb-2 small text-slate-600">
                        <div><i class="fa-solid fa-circle me-2 text-info small"></i> Inactive / One-time</div>
                        <span class="fw-bold"><?= $segmentation_data[0] ?></span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-2 small text-slate-600">
                        <div><i class="fa-solid fa-circle me-2 text-primary small"></i> Regular (2-5 Ord)</div>
                        <span class="fw-bold"><?= $segmentation_data[1] ?></span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between small text-slate-600">
                        <div><i class="fa-solid fa-circle me-2 text-danger small"></i> Elite (6+ Orders)</div>
                        <span class="fw-bold"><?= $segmentation_data[2] ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Elite Table -->
    <div class="row g-4">
        <div class="col-12">
            <div class="bg-white rounded-4 shadow-sm overflow-hidden border border-slate-100">
                <div class="px-4 py-3 bg-slate-50 border-bottom d-flex align-items-center justify-content-between">
                    <h5 class="fw-bold text-slate-800 mb-0">
                        <i class="fa-solid fa-crown me-2 text-warning"></i>Top Performance Elite Customers
                    </h5>
                    <span class="badge bg-slate-900 rounded-pill px-3">Top 10 Spenders</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="ps-4">Customer Details</th>
                                <th class="text-center">Order Pulse</th>
                                <th class="text-center">Lifecycle Revenue</th>
                                <th class="text-end pe-4">Last Active</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_customers)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5">
                                        <i class="fa-solid fa-user-slash fa-2x text-slate-200 mb-2"></i>
                                        <p class="text-slate-400">No customer spend data for this audit period</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_customers as $tc): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center">
                                                <div class="p-2 bg-slate-100 rounded-circle me-3">
                                                    <i class="fa-solid fa-user text-slate-400"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-slate-800">
                                                        <?= htmlspecialchars($tc['name']) ?>
                                                        <span class="badge bg-<?= $tc['role_id'] == ROLE_DISTRIBUTOR ? 'indigo' : 'slate' ?>-50 text-<?= $tc['role_id'] == ROLE_DISTRIBUTOR ? 'indigo' : 'slate' ?>-600 border border-<?= $tc['role_id'] == ROLE_DISTRIBUTOR ? 'indigo' : 'slate' ?>-100 smaller ms-1" style="font-size: 0.6rem;">
                                                            <?= $tc['role_id'] == ROLE_DISTRIBUTOR ? 'Distributor' : 'Customer' ?>
                                                        </span>
                                                    </div>
                                                    <div class="smaller text-slate-400"><?= htmlspecialchars($tc['email'] ?: $tc['phone']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-blue-50 text-blue-600 border border-blue-100 rounded-pill px-3 py-1">
                                                <?= $tc['order_count'] ?> orders
                                            </span>
                                        </td>
                                        <td class="text-center fw-black text-slate-700">
                                            <?= format_price($tc['total_spend']) ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="text-slate-800 small fw-bold"><?= date('d M Y', strtotime($tc['last_order_date'])) ?></div>
                                            <div class="smaller text-slate-400" style="font-size: 10px;"><?= date('h:i A', strtotime($tc['last_order_date'])) ?></div>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Acquisition Chart
        const acqCtx = document.getElementById('acquisitionChart').getContext('2d');
        new Chart(acqCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($trend_labels) ?>,
                datasets: [{
                    label: 'New Customers',
                    data: <?= json_encode($trend_values) ?>,
                    borderColor: '#4cc9f0',
                    backgroundColor: 'rgba(76, 201, 240, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#4cc9f0'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.02)' }, ticks: { stepSize: 1 } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Loyalty Chart
        const loyCtx = document.getElementById('loyaltyChart').getContext('2d');
        new Chart(loyCtx, {
            type: 'doughnut',
            data: {
                labels: ['Inactive/One-time', 'Regular', 'Elite'],
                datasets: [{
                    data: <?= json_encode($segmentation_data) ?>,
                    backgroundColor: ['#4cc9f0', '#4361ee', '#f72585'],
                    borderWidth: 0,
                    hoverOffset: 12
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: { legend: { display: false } }
            }
        });
    });
</script>

<?php include '../../includes/footer.php'; ?>
