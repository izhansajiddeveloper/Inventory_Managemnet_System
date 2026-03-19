<?php
/**
 * User & System Activity Audit - Modern Dashboard
 * Combined timeline of operational events and security auditing
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';

authorize([ROLE_ADMIN]);

$page_title = "System Activity Audit";
$page_icon = "fa-clock-rotate-left";
$page_description = "Track warehouse transfers, sales, and administrative operational events";

// Initialize data
$limit = 50;
$counts = ['stock' => 0, 'sales' => 0, 'payments' => 0];
$events = [];
$daily_activity = [];

$error_message = '';

try {
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }

    // 1. Transaction Summary Metrics
    $counts_query = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM product_transactions) as stock_events,
            (SELECT COUNT(*) FROM orders) as sales_events,
            (SELECT COUNT(*) FROM payments) as payment_events
    ")->fetch(PDO::FETCH_ASSOC);

    if ($counts_query) {
        $counts['stock'] = (int)$counts_query['stock_events'];
        $counts['sales'] = (int)$counts_query['sales_events'];
        $counts['payments'] = (int)$counts_query['payment_events'];
    }

    // 2. Timeline Logic (Union of Events)
    $timeline_query = "
        (SELECT 'STOCK' as event_type, pt.type as subtype, p.name as reference, pt.quantity as amount, pt.created_at, u.name as user
         FROM product_transactions pt
         JOIN products p ON pt.product_id = p.id
         LEFT JOIN users u ON pt.created_by = u.id)
        UNION ALL
        (SELECT 'SALE' as event_type, o.status as subtype, CAST(o.id AS CHAR) as reference, o.final_amount as amount, o.created_at, u.name as user
         FROM orders o
         LEFT JOIN users u ON o.created_by = u.id)
        UNION ALL
        (SELECT 'PAYMENT' as event_type, pay.payment_method as subtype, CAST(pay.reference_id AS CHAR) as reference, pay.amount as amount, pay.created_at, u.name as user
         FROM payments pay
         LEFT JOIN users u ON pay.created_by = u.id)
        ORDER BY created_at DESC 
        LIMIT ?
    ";
    
    $stmt = $pdo->prepare($timeline_query);
    $stmt->execute([$limit]);
    $events = $stmt->fetchAll();

    // 3. Activity Trend (Last 30 Days Combined)
    $trend_query = $pdo->query("
        SELECT date, SUM(count) as total_count FROM (
            SELECT DATE(created_at) as date, COUNT(*) as count FROM product_transactions WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(created_at)
            UNION ALL
            SELECT DATE(created_at) as date, COUNT(*) as count FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(created_at)
            UNION ALL
            SELECT DATE(created_at) as date, COUNT(*) as count FROM payments WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(created_at)
        ) combined_events
        GROUP BY date
        ORDER BY date ASC
    ");
    $trend_raw = $trend_query->fetchAll(PDO::FETCH_ASSOC);
    $trend_map = [];
    foreach ($trend_raw as $row) { $trend_map[$row['date']] = (int)$row['total_count']; }

    $trend_labels = [];
    $trend_values = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $trend_labels[] = date('M d', strtotime($date));
        $trend_values[] = $trend_map[$date] ?? 0;
    }

} catch (Exception $e) {
    $error_message = "Activity Audit Error: " . $e->getMessage();
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
    :root {
        --event-stock: #6366f1;
        --event-sale: #10b981;
        --event-payment: #f59e0b;
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

    .timeline-table td { padding: 1.25rem 0.75rem; }

    .chart-container { position: relative; min-height: 220px; width: 100%; }

    .badge-indicator {
        width: 10px; height: 10px; border-radius: 50%;
        display: inline-block; margin-right: 8px;
    }

    .badge-event {
        font-size: 0.65rem;
        letter-spacing: 0.5px;
        font-weight: 800;
        padding: 0.35rem 0.6rem;
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
                <i class="fa-solid fa-print me-1"></i> Snapshot
            </button>
            <button class="btn btn-primary rounded-3 px-4 shadow-sm" id="exportLog">
                <i class="fa-solid fa-download me-1"></i> Download Log
            </button>
        </div>
    </div>

    <!-- KPI Row -->
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="stat-card rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge bg-indigo-50 text-indigo-600 px-3 py-2 rounded-3 border border-indigo-100">
                        <i class="fa-solid fa-boxes-stacked me-2"></i> Stock Adjusted
                    </span>
                    <i class="fa-solid fa-truck-loading text-slate-300"></i>
                </div>
                <h3 class="h4 fw-bold text-slate-800 mb-1"><?= number_format($counts['stock']) ?> Events</h3>
                <div class="text-slate-400 small">Total warehouse movement events</div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="stat-card rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge bg-emerald-50 text-emerald-600 px-3 py-2 rounded-3 border border-emerald-100">
                        <i class="fa-solid fa-cart-shopping me-2"></i> Sales Records
                    </span>
                    <i class="fa-solid fa-file-invoice text-slate-300"></i>
                </div>
                <h3 class="h4 fw-bold text-slate-800 mb-1"><?= number_format($counts['sales']) ?> Orders</h3>
                <div class="text-slate-400 small">Comprehensive customer sales entry</div>
            </div>
        </div>

        <div class="col-xl-4 col-md-12">
            <div class="stat-card rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge bg-amber-50 text-amber-600 px-3 py-2 rounded-3 border border-amber-100">
                        <i class="fa-solid fa-receipt me-2"></i> Payment Receipts
                    </span>
                    <i class="fa-solid fa-money-check-dollar text-slate-300"></i>
                </div>
                <h3 class="h4 fw-bold text-slate-800 mb-1"><?= number_format($counts['payments']) ?> Receipts</h3>
                <div class="text-slate-400 small">Total financial payment entries</div>
            </div>
        </div>
    </div>

    <!-- Analytics Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="bg-white rounded-4 shadow-sm p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h5 class="fw-bold text-slate-800 mb-0">30-Day Activity Momentum</h5>
                    <span class="badge bg-slate-100 text-slate-600 rounded-pill px-3">Aggregated Daily Volume</span>
                </div>
                <div class="chart-container">
                    <canvas id="activityTrendChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="bg-white rounded-4 shadow-sm p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h5 class="fw-bold text-slate-800 mb-0">Event Mix</h5>
                </div>
                <div class="chart-container" style="min-height: 200px;">
                    <canvas id="eventDistributionChart"></canvas>
                </div>
                <div class="mt-4">
                    <div class="d-flex align-items-center justify-content-between mb-2 small">
                        <div><span class="badge-indicator" style="background: var(--event-stock)"></span> Stock Events</div>
                        <span class="fw-bold"><?= round(($counts['stock']/max(array_sum($counts),1))*100, 1) ?>%</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-2 small">
                        <div><span class="badge-indicator" style="background: var(--event-sale)"></span> Sales Events</div>
                        <span class="fw-bold"><?= round(($counts['sales']/max(array_sum($counts),1))*100, 1) ?>%</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between small">
                        <div><span class="badge-indicator" style="background: var(--event-payment)"></span> Payment Events</div>
                        <span class="fw-bold"><?= round(($counts['payments']/max(array_sum($counts),1))*100, 1) ?>%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Timeline Table Row -->
    <div class="row g-4">
        <div class="col-12">
            <div class="bg-white rounded-4 shadow-sm overflow-hidden border border-slate-100">
                <div class="px-4 py-3 bg-slate-50 border-bottom d-flex align-items-center justify-content-between">
                    <h5 class="fw-bold text-slate-800 mb-0">
                        <i class="fa-solid fa-list-check me-2 text-primary"></i>Master Operational Timeline
                    </h5>
                    <span class="badge bg-slate-900 rounded-pill px-3">Latest <?= $limit ?> Operations</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 timeline-table">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="ps-4">Operation Trigger</th>
                                <th>Context / Reference</th>
                                <th class="text-center">Magnitude</th>
                                <th>Authorized By</th>
                                <th class="text-end pe-4">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($events)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <i class="fa-solid fa-timeline fa-2x text-slate-200 mb-2"></i>
                                        <p class="text-slate-400">No operational audit records found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($events as $ev): 
                                    $border_color = '';
                                    if($ev['event_type'] == 'STOCK') $border_color = 'indigo';
                                    elseif($ev['event_type'] == 'SALE') $border_color = 'emerald';
                                    else $border_color = 'amber';
                                ?>
                                    <tr class="border-start border-4 border-<?= $border_color ?>-500">
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center">
                                                <span class="badge badge-event bg-<?= $border_color ?>-50 text-<?= $border_color ?>-700 border border-<?= $border_color ?>-100 me-2 rounded-2">
                                                    <?= $ev['event_type'] ?>
                                                </span>
                                                <div class="fw-bold text-slate-800"><?= htmlspecialchars($ev['subtype']) ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-medium text-slate-700">
                                                <?= $ev['event_type'] == 'SALE' ? 'Order Ref: ' : ($ev['event_type'] == 'PAYMENT' ? 'Invoice Ref: ' : '') ?>
                                                <?= htmlspecialchars($ev['reference']) ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="fw-bold text-slate-600">
                                                <?= $ev['event_type'] == 'STOCK' ? number_format($ev['amount']) . " Units" : format_price($ev['amount']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-inline-flex align-items-center px-2 py-1 bg-slate-50 border border-slate-100 rounded-pill small">
                                                <i class="fa-solid fa-user-shield me-2 text-slate-300 small"></i>
                                                <span class="text-slate-600 fw-medium"><?= htmlspecialchars($ev['user'] ?? 'System Core') ?></span>
                                            </div>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="fw-bold text-slate-800"><?= date('h:i A', strtotime($ev['created_at'])) ?></div>
                                            <div class="text-slate-400 smaller" style="font-size: 10px;"><?= date('d M Y', strtotime($ev['created_at'])) ?></div>
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

<!-- Chart Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Trend Chart
        const trendCtx = document.getElementById('activityTrendChart').getContext('2d');
        const trendGradient = trendCtx.createLinearGradient(0, 0, 0, 400);
        trendGradient.addColorStop(0, 'rgba(67, 97, 238, 0.1)');
        trendGradient.addColorStop(1, 'rgba(67, 97, 238, 0)');

        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($trend_labels) ?>,
                datasets: [{
                    label: 'Operational Volume',
                    data: <?= json_encode($trend_values) ?>,
                    borderColor: '#4361ee',
                    backgroundColor: trendGradient,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 3,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.02)' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Distribution Chart
        const distCtx = document.getElementById('eventDistributionChart').getContext('2d');
        new Chart(distCtx, {
            type: 'doughnut',
            data: {
                labels: ['Stock', 'Sales', 'Payments'],
                datasets: [{
                    data: [<?= $counts['stock'] ?>, <?= $counts['sales'] ?>, <?= $counts['payments'] ?>],
                    backgroundColor: ['#6366f1', '#10b981', '#f59e0b'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: { legend: { display: false } }
            }
        });

        document.getElementById('exportLog').addEventListener('click', function() {
            alert('Audit log data exported to snapshot. Use Print (Ctrl+P) for a full audit document.');
        });
    });
</script>

<?php include '../../includes/footer.php'; ?>
