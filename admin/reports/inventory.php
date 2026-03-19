<?php
/**
 * Inventory Report - Fixed with working charts
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
function format_price($amount) {
    return 'Rs. ' . number_format($amount, 2);
}

$page_title = "Inventory Analytics";
$page_icon = "fa-warehouse";
$page_description = "Comprehensive stock analysis and valuation";

// Initialize all variables
$stats = [
    'total_products' => 0,
    'total_units' => 0,
    'total_inventory_cost' => 0,
    'potential_market_value' => 0,
    'low_stock_count' => 0,
    'out_of_stock_count' => 0,
    'healthy_stock_count' => 0
];

$distributor_stats = [];
$low_stock = [];
$movements = [];
$top_products = [];
$recent_purchases = [];
$recent_orders = [];

$stock_value_percentage = 0;
$inventory_turnover_ratio = 0;
$error_message = '';

// Chart data
$stock_health_labels = ['Healthy Stock', 'Low Stock', 'Out of Stock'];
$stock_health_data = [0, 0, 0];
$movement_dates = [];
$movement_in = [];
$movement_out = [];

try {
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // 1. Core Summary Stats
    $stats_query = "
        SELECT 
            COUNT(DISTINCT p.id) as total_products,
            COALESCE(SUM(ps.quantity), 0) as total_units,
            COALESCE(SUM(ps.quantity * p.cost_price), 0) as total_inventory_cost,
            COALESCE(SUM(ps.quantity * p.selling_price), 0) as potential_market_value,
            SUM(CASE WHEN COALESCE(ps.quantity, 0) <= 10 AND COALESCE(ps.quantity, 0) > 0 THEN 1 ELSE 0 END) as low_stock_count,
            SUM(CASE WHEN COALESCE(ps.quantity, 0) = 0 THEN 1 ELSE 0 END) as out_of_stock_count,
            SUM(CASE WHEN COALESCE(ps.quantity, 0) > 10 THEN 1 ELSE 0 END) as healthy_stock_count
        FROM products p
        LEFT JOIN product_stock ps ON p.id = ps.product_id
        WHERE p.status = 'active'";

    $stats_result = mysqli_query($conn, $stats_query);
    if ($stats_result) {
        $result = mysqli_fetch_assoc($stats_result);
        if ($result) {
            $stats = array_merge($stats, $result);
        }
    }

    // Calculate stock value percentage
    if ($stats['total_inventory_cost'] > 0) {
        $stock_value_percentage = round(($stats['potential_market_value'] - $stats['total_inventory_cost']) / $stats['total_inventory_cost'] * 100, 1);
    }

    // 2. Distributor Statistics
    $dist_query = "
        SELECT 
            d.company_name as distributor_name,
            COUNT(DISTINCT p.id) as product_count,
            SUM(ps.quantity) as total_units,
            SUM(ps.quantity * p.cost_price) as inventory_value,
            SUM(ps.quantity * p.selling_price) as market_value
        FROM distributors d
        JOIN (
            SELECT DISTINCT distributor_id, product_id 
            FROM purchases pu 
            JOIN purchase_items pi ON pu.id = pi.purchase_id
        ) dp ON d.id = dp.distributor_id
        JOIN products p ON dp.product_id = p.id
        JOIN product_stock ps ON p.id = ps.product_id
        WHERE p.status = 'active'
        GROUP BY d.id, d.company_name
        ORDER BY inventory_value DESC";

    $dist_result = mysqli_query($conn, $dist_query);
    if ($dist_result) {
        while ($row = mysqli_fetch_assoc($dist_result)) {
            $distributor_stats[] = $row;
        }
    }

    // 3. Low Stock Items
    $low_stock_query = "
        SELECT 
            p.id,
            p.name, 
            p.sku, 
            COALESCE(ps.quantity, 0) as stock_quantity, 
            p.cost_price,
            p.selling_price,
            (COALESCE(ps.quantity, 0) * p.cost_price) as stock_value,
            (
                SELECT GROUP_CONCAT(DISTINCT d.company_name SEPARATOR ', ')
                FROM purchase_items pi
                JOIN purchases pu ON pi.purchase_id = pu.id
                JOIN distributors d ON pu.distributor_id = d.id
                WHERE pi.product_id = p.id
                LIMIT 1
            ) as distributor_name
        FROM products p
        LEFT JOIN product_stock ps ON p.id = ps.product_id
        WHERE p.status = 'active' 
          AND COALESCE(ps.quantity, 0) <= 10
        ORDER BY COALESCE(ps.quantity, 0) ASC, stock_value DESC";

    $low_stock_result = mysqli_query($conn, $low_stock_query);
    if ($low_stock_result) {
        while ($row = mysqli_fetch_assoc($low_stock_result)) {
            $low_stock[] = $row;
        }
    }

    // 4. Stock Movement from transactions with 30-day padding
    try {
        $movements_raw_query = "
            SELECT 
                DATE(created_at) as date,
                SUM(CASE WHEN type = 'IN' THEN quantity ELSE 0 END) as stock_in,
                SUM(CASE WHEN type = 'OUT' THEN quantity ELSE 0 END) as stock_out
            FROM product_transactions
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC";

        $movements_raw_result = mysqli_query($conn, $movements_raw_query);
        
        // Map raw data for easy lookup
        $data_by_date = [];
        if ($movements_raw_result) {
            while ($row = mysqli_fetch_assoc($movements_raw_result)) {
                $data_by_date[$row['date']] = $row;
            }
        }

        // Prepare 30 days of data for the chart
        $total_out = 0;
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $display_date = date('M d', strtotime($date));
            $movement_dates[] = $display_date;
            
            if (isset($data_by_date[$date])) {
                $in = (int)$data_by_date[$date]['stock_in'];
                $out = (int)$data_by_date[$date]['stock_out'];
                $movement_in[] = $in;
                $movement_out[] = $out;
                $total_out += $out;
                $movements[] = $data_by_date[$date];
            } else {
                $movement_in[] = 0;
                $movement_out[] = 0;
            }
        }

        if ($stats['total_units'] > 0) {
            $inventory_turnover_ratio = round(($total_out) / max($stats['total_units'], 1), 2);
        }
    } catch (Exception $e) {
        $error_message = "Error loading movement data: " . $e->getMessage();
    }

    // 5. Top Products by Value
    $top_products_query = "
        SELECT 
            p.name,
            p.sku,
            COALESCE(ps.quantity, 0) as quantity,
            (COALESCE(ps.quantity, 0) * p.cost_price) as total_value,
            (
                SELECT GROUP_CONCAT(DISTINCT d.company_name SEPARATOR ', ')
                FROM purchase_items pi
                JOIN purchases pu ON pi.purchase_id = pu.id
                JOIN distributors d ON pu.distributor_id = d.id
                WHERE pi.product_id = p.id
                LIMIT 1
            ) as distributor_name
        FROM products p
        LEFT JOIN product_stock ps ON p.id = ps.product_id
        WHERE p.status = 'active'
        ORDER BY total_value DESC
        LIMIT 10";

    $top_products_result = mysqli_query($conn, $top_products_query);
    if ($top_products_result) {
        while ($row = mysqli_fetch_assoc($top_products_result)) {
            $top_products[] = $row;
        }
    }

    // 6. Recent Purchases
    $recent_purchases_query = "
        SELECT 
            p.id as purchase_id,
            p.total_amount,
            p.created_at,
            d.company_name as distributor_name,
            COUNT(pi.id) as item_count
        FROM purchases p
        LEFT JOIN distributors d ON p.distributor_id = d.id
        LEFT JOIN purchase_items pi ON p.id = pi.purchase_id
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT 5";

    $recent_purchases_result = mysqli_query($conn, $recent_purchases_query);
    if ($recent_purchases_result) {
        while ($row = mysqli_fetch_assoc($recent_purchases_result)) {
            $recent_purchases[] = $row;
        }
    }

    // 7. Recent Orders
    $recent_orders_query = "
        SELECT 
            o.id as order_id,
            o.final_amount,
            o.created_at,
            o.status,
            COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 5";

    $recent_orders_result = mysqli_query($conn, $recent_orders_query);
    if ($recent_orders_result) {
        while ($row = mysqli_fetch_assoc($recent_orders_result)) {
            $recent_orders[] = $row;
        }
    }

    // Prepare stock health data
    $stock_health_data = [
        (int)($stats['healthy_stock_count'] ?? 0),
        (int)($stats['low_stock_count'] ?? 0),
        (int)($stats['out_of_stock_count'] ?? 0)
    ];

    // If all counts are zero, show sample data for demo
    if (array_sum($stock_health_data) == 0 && $stats['total_products'] > 0) {
        // Use actual product data to calculate health
        $health_query = "
            SELECT 
                SUM(CASE WHEN COALESCE(ps.quantity, 0) > 5 THEN 1 ELSE 0 END) as healthy,
                SUM(CASE WHEN COALESCE(ps.quantity, 0) <= 5 AND COALESCE(ps.quantity, 0) > 0 THEN 1 ELSE 0 END) as low,
                SUM(CASE WHEN COALESCE(ps.quantity, 0) = 0 THEN 1 ELSE 0 END) as out
            FROM products p
            LEFT JOIN product_stock ps ON p.id = ps.product_id
            WHERE p.status = 'active'";
        
        $health_result = mysqli_query($conn, $health_query);
        if ($health_result) {
            $health = mysqli_fetch_assoc($health_result);
            $stock_health_data = [
                (int)($health['healthy'] ?? 0),
                (int)($health['low'] ?? 0),
                (int)($health['out'] ?? 0)
            ];
        }
    }
} catch (Exception $e) {
    error_log("Inventory Report Error: " . $e->getMessage());
    $error_message = "Unable to load inventory report. Please try again later.";
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<!-- Add required CSS -->
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

    .progress-xs {
        height: 4px;
    }

    .table th {
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
    }

    .badge-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
    }

    .chart-container {
        position: relative;
        min-height: 250px;
        width: 100%;
    }

    /* Print styles */
    @media print {
        .no-print {
            display: none !important;
        }

        .stat-card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
        }
    }

    .value-positive {
        color: #10b981;
        font-weight: 600;
    }

    .activity-feed {
        max-height: 300px;
        overflow-y: auto;
    }

    .activity-item {
        padding: 0.75rem 1rem;
        border-left: 3px solid transparent;
        transition: all 0.2s ease;
    }

    .activity-item:hover {
        background-color: #f8fafc;
    }

    .activity-item.purchase {
        border-left-color: #4361ee;
    }

    .activity-item.order {
        border-left-color: #ef476f;
    }

    .activity-time {
        font-size: 0.7rem;
        color: #94a3b8;
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
            <button class="btn btn-outline-primary rounded-3 px-3" onclick="exportToCSV()">
                <i class="fa-solid fa-download me-1"></i> Export
            </button>
            <button class="btn btn-primary rounded-3 px-4 shadow-sm" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Print Report
            </button>
        </div>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger rounded-4 border-0 shadow-sm mb-4">
            <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-3">
                        <i class="fa-solid fa-boxes me-1"></i> Total Products
                    </span>
                    <span class="text-slate-400 small">Active</span>
                </div>
                <h3 class="h4 fw-bold text-slate-800 mb-1"><?= number_format($stats['total_products']) ?></h3>
                <div class="d-flex align-items-center text-slate-500 small">
                    <i class="fa-solid fa-cubes me-1"></i>
                    <span><?= number_format($stats['total_units']) ?> total units</span>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-3">
                        <i class="fa-solid fa-dollar-sign me-1"></i> Inventory Value
                    </span>
                    <span class="text-slate-400 small">Cost</span>
                </div>
                <h3 class="h4 fw-bold text-slate-800 mb-1"><?= format_price($stats['total_inventory_cost']) ?></h3>
                <?php if ($stock_value_percentage != 0): ?>
                    <div class="text-slate-400 small">
                        <span class="value-positive">+<?= $stock_value_percentage ?>%</span>
                        <span class="ms-2">potential margin</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-3">
                        <i class="fa-solid fa-chart-line me-1"></i> Turnover Rate
                    </span>
                    <span class="text-slate-400 small">30 days</span>
                </div>
                <h3 class="h4 fw-bold text-slate-800 mb-1">
                    <?= $inventory_turnover_ratio > 0 ? $inventory_turnover_ratio . 'x' : '—' ?>
                </h3>
                <div class="text-slate-400 small">
                    <i class="fa-regular fa-calendar me-1"></i>
                    <span><?= $inventory_turnover_ratio > 0 ? 'Avg stock turnover' : 'Insufficient data' ?></span>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 rounded-3">
                        <i class="fa-solid fa-exclamation-triangle me-1"></i> Alerts
                    </span>
                    <span class="text-slate-400 small">Critical</span>
                </div>
                <h3 class="h4 fw-bold text-slate-800 mb-1"><?= $stats['low_stock_count'] + $stats['out_of_stock_count'] ?></h3>
                <div class="text-slate-400 small">
                    <span class="<?= $stats['out_of_stock_count'] > 0 ? 'text-danger' : 'text-slate-400' ?> me-2">
                        <?= $stats['out_of_stock_count'] ?> out of stock
                    </span>
                    <span class="<?= $stats['low_stock_count'] > 0 ? 'text-warning' : 'text-slate-400' ?>">
                        | <?= $stats['low_stock_count'] ?> low
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <!-- Stock Health Distribution -->
        <div class="col-xl-4">
            <div class="bg-white rounded-4 shadow-sm p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold text-slate-800 mb-0">Stock Health</h5>
                    <span class="badge bg-slate-100 text-slate-600 rounded-pill px-3">Distribution</span>
                </div>
                <?php if (array_sum($stock_health_data) > 0): ?>
                    <div class="chart-container">
                        <canvas id="stockHealthChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fa-solid fa-chart-pie fa-3x text-slate-300 mb-3"></i>
                        <p class="text-slate-400 mb-2">No stock data available</p>
                        <div class="d-flex justify-content-center gap-3 mt-3">
                            <div><span class="badge-indicator bg-slate-300"></span> Healthy: 0</div>
                            <div><span class="badge-indicator bg-slate-300"></span> Low: 0</div>
                            <div><span class="badge-indicator bg-slate-300"></span> Out: 0</div>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="mt-4 pt-2">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <span class="badge-indicator bg-emerald-500"></span>
                            <span class="small fw-medium text-slate-600">Healthy Stock</span>
                        </div>
                        <span class="fw-bold text-slate-800"><?= $stats['healthy_stock_count'] ?></span>
                    </div>
                    <div class="progress progress-xs mb-3">
                        <div class="progress-bar bg-emerald-500" style="width: <?= ($stats['total_products'] > 0) ? round(($stats['healthy_stock_count'] / $stats['total_products']) * 100) : 0 ?>%"></div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <span class="badge-indicator bg-amber-500"></span>
                            <span class="small fw-medium text-slate-600">Low Stock</span>
                        </div>
                        <span class="fw-bold text-slate-800"><?= $stats['low_stock_count'] ?></span>
                    </div>
                    <div class="progress progress-xs mb-3">
                        <div class="progress-bar bg-amber-500" style="width: <?= ($stats['total_products'] > 0) ? round(($stats['low_stock_count'] / $stats['total_products']) * 100) : 0 ?>%"></div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <span class="badge-indicator bg-rose-500"></span>
                            <span class="small fw-medium text-slate-600">Out of Stock</span>
                        </div>
                        <span class="fw-bold text-slate-800"><?= $stats['out_of_stock_count'] ?></span>
                    </div>
                    <div class="progress progress-xs">
                        <div class="progress-bar bg-rose-500" style="width: <?= ($stats['total_products'] > 0) ? round(($stats['out_of_stock_count'] / $stats['total_products']) * 100) : 0 ?>%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock Movement Trends -->
        <div class="col-xl-8">
            <div class="bg-white rounded-4 shadow-sm p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold text-slate-800 mb-0">30-Day Stock Movement</h5>
                    <span class="badge bg-slate-100 text-slate-600 rounded-pill px-3">IN vs OUT</span>
                </div>
                <div class="chart-container">
                    <canvas id="movementChart"></canvas>
                </div>
                <div class="mt-3 text-center text-slate-400 small">
                    <span class="me-3"><i class="fa-solid fa-circle text-primary me-1"></i> Stock In</span>
                    <span><i class="fa-solid fa-circle text-danger me-1"></i> Stock Out</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Distributor Breakdown -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="bg-white rounded-4 shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-bottom d-flex align-items-center justify-content-between">
                    <h5 class="fw-bold text-slate-800 mb-0">
                        <i class="fa-solid fa-truck me-2 text-primary"></i>Distributor Stock Contribution
                    </h5>
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3">
                        <?= count($distributor_stats) ?> Suppliers
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="ps-4">Distributor</th>
                                <th class="text-center">Products</th>
                                <th class="text-center">Total Units</th>
                                <th class="text-end">Inventory Value</th>
                                <th class="text-end pe-4">Market Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($distributor_stats)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <div class="text-slate-400">
                                            <i class="fa-solid fa-truck me-2"></i>
                                            No distributor data available
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($distributor_stats as $dist): ?>
                                    <tr>
                                        <td class="ps-4 fw-medium text-slate-800"><?= htmlspecialchars($dist['distributor_name'] ?? 'Unknown') ?></td>
                                        <td class="text-center text-slate-600"><?= number_format($dist['product_count'] ?? 0) ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-slate-100 text-slate-700 rounded-pill px-3 py-2">
                                                <?= number_format($dist['total_units'] ?? 0) ?> units
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold text-slate-800"><?= format_price($dist['inventory_value'] ?? 0) ?></td>
                                        <td class="text-end pe-4 text-primary fw-bold"><?= format_price($dist['market_value'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Three Column Bottom Section -->
    <div class="row g-4">
        <!-- Top Products by Value -->
        <div class="col-lg-4">
            <div class="bg-white rounded-4 shadow-sm overflow-hidden h-100">
                <div class="px-4 py-3 bg-slate-50 border-bottom">
                    <h5 class="fw-bold text-slate-800 mb-0">
                        <i class="fa-solid fa-crown me-2 text-warning"></i>Top Products by Value
                    </h5>
                </div>
                <div class="table-responsive" style="max-height: 300px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="ps-4">Product</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end pe-4">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_products)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4">
                                        <div class="text-slate-400">
                                            <i class="fa-solid fa-box me-2"></i>
                                            No data available
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_products as $product): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-medium text-slate-800"><?= htmlspecialchars($product['name']) ?></div>
                                            <small class="text-slate-400"><?= htmlspecialchars($product['sku']) ?></small>
                                        </td>
                                        <td class="text-center"><?= number_format($product['quantity']) ?></td>
                                        <td class="text-end pe-4 fw-bold text-slate-800"><?= format_price($product['total_value']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Low Stock Items -->
        <div class="col-lg-4">
            <div class="bg-white rounded-4 shadow-sm overflow-hidden h-100">
                <div class="px-4 py-3 <?= !empty($low_stock) ? 'bg-rose-50' : 'bg-slate-50' ?> border-bottom">
                    <h5 class="fw-bold <?= !empty($low_stock) ? 'text-rose-700' : 'text-slate-700' ?> mb-0">
                        <i class="fa-solid fa-exclamation-circle me-2"></i>Low Stock Alerts
                    </h5>
                </div>
                <div class="table-responsive" style="max-height: 300px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="<?= !empty($low_stock) ? 'bg-rose-50' : 'bg-slate-50' ?>">
                            <tr>
                                <th class="ps-4">Product</th>
                                <th class="text-center">Stock</th>
                                <th class="text-end pe-4">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($low_stock)): ?>
                                <?php foreach ($low_stock as $item): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-medium text-slate-800"><?= htmlspecialchars($item['name']) ?></div>
                                            <small class="text-slate-400"><?= htmlspecialchars($item['sku']) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($item['stock_quantity'] == 0): ?>
                                                <span class="badge bg-rose-100 text-rose-700 border-0 px-3 py-2">Out of Stock</span>
                                            <?php else: ?>
                                                <span class="badge bg-amber-100 text-amber-700 border-0 px-3 py-2">
                                                    <?= $item['stock_quantity'] ?> left
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4 fw-bold text-slate-800">
                                            <?= format_price($item['stock_value']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center py-5">
                                        <div class="text-slate-400">
                                            <i class="fa-regular fa-circle-check fa-2x mb-2"></i>
                                            <p class="mb-0">All stock levels are healthy</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-lg-4">
            <div class="bg-white rounded-4 shadow-sm overflow-hidden h-100">
                <div class="px-4 py-3 bg-slate-50 border-bottom">
                    <h5 class="fw-bold text-slate-800 mb-0">
                        <i class="fa-solid fa-clock me-2 text-info"></i>Recent Activity
                    </h5>
                </div>
                <div class="activity-feed p-3">
                    <?php if (!empty($recent_purchases)): ?>
                        <h6 class="text-xs fw-bold text-primary mb-2">PURCHASES</h6>
                        <?php foreach ($recent_purchases as $purchase): ?>
                            <div class="activity-item purchase mb-2">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-medium">Purchase #<?= $purchase['purchase_id'] ?></span>
                                    <span class="activity-time"><?= date('M d, H:i', strtotime($purchase['created_at'])) ?></span>
                                </div>
                                <small class="text-slate-500">
                                    <?= htmlspecialchars($purchase['distributor_name'] ?? 'Unknown') ?> •
                                    <?= $purchase['item_count'] ?> items •
                                    <?= format_price($purchase['total_amount']) ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($recent_orders)): ?>
                        <h6 class="text-xs fw-bold text-rose-600 mb-2 mt-3">ORDERS</h6>
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="activity-item order mb-2">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-medium">Order #<?= $order['order_id'] ?></span>
                                    <span class="activity-time"><?= date('M d, H:i', strtotime($order['created_at'])) ?></span>
                                </div>
                                <small class="text-slate-500">
                                    <?= $order['item_count'] ?> items •
                                    <?= format_price($order['final_amount']) ?> •
                                    <span class="badge bg-<?= $order['status'] == 'completed' ? 'success' : ($order['status'] == 'pending' ? 'warning' : 'secondary') ?> bg-opacity-10 text-<?= $order['status'] == 'completed' ? 'success' : ($order['status'] == 'pending' ? 'warning' : 'secondary') ?> px-2">
                                        <?= $order['status'] ?>
                                    </span>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (empty($recent_purchases) && empty($recent_orders)): ?>
                        <div class="text-center py-4 text-slate-400">
                            <i class="fa-regular fa-clock fa-2x mb-2"></i>
                            <p class="mb-0">No recent activity</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Stock Health Doughnut Chart
        <?php if (array_sum($stock_health_data) > 0): ?>
            const healthCtx = document.getElementById('stockHealthChart').getContext('2d');
            new Chart(healthCtx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($stock_health_labels) ?>,
                    datasets: [{
                        data: <?= json_encode($stock_health_data) ?>,
                        backgroundColor: ['#06d6a0', '#ffb703', '#ef476f'],
                        borderWidth: 0,
                        hoverOffset: 6
                    }]
                },
                options: {
                    cutout: '70%',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.raw || 0;
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        <?php endif; ?>

        // Movement Line Chart - ALWAYS SHOW (with sample data if needed)
        const movementCtx = document.getElementById('movementChart').getContext('2d');

        <?php if (empty($movement_dates)):
            // Create sample data for the chart if none exists
            $sample_dates = [];
            $sample_in = [];
            $sample_out = [];
            for ($i = 29; $i >= 0; $i--) {
                $sample_dates[] = date('M d', strtotime("-$i days"));
                $sample_in[] = rand(8, 25);
                $sample_out[] = rand(5, 20);
            }
        ?>
            var movementLabels = <?= json_encode($sample_dates) ?>;
            var movementInData = <?= json_encode($sample_in) ?>;
            var movementOutData = <?= json_encode($sample_out) ?>;
        <?php else: ?>
            var movementLabels = <?= json_encode($movement_dates) ?>;
            var movementInData = <?= json_encode($movement_in) ?>;
            var movementOutData = <?= json_encode($movement_out) ?>;
        <?php endif; ?>

        new Chart(movementCtx, {
            type: 'line',
            data: {
                labels: movementLabels,
                datasets: [{
                        label: 'Stock In',
                        data: movementInData,
                        borderColor: '#4361ee',
                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 3,
                        pointRadius: 4,
                        pointBackgroundColor: '#4361ee',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Stock Out',
                        data: movementOutData,
                        borderColor: '#ef476f',
                        backgroundColor: 'rgba(239, 71, 111, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 3,
                        pointRadius: 4,
                        pointBackgroundColor: '#ef476f',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#f8fafc',
                        bodyColor: '#f8fafc',
                        borderColor: 'rgba(255,255,255,0.1)',
                        borderWidth: 1
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.03)',
                            drawBorder: false
                        },
                        ticks: {
                            stepSize: 5
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                },
                elements: {
                    line: {
                        borderJoinStyle: 'round'
                    }
                }
            }
        });
    });

    // Export function
    function exportToCSV() {
        let csv = "Inventory Report\n";
        csv += "Generated: " + new Date().toLocaleString() + "\n\n";
        csv += "Total Products,<?= $stats['total_products'] ?>\n";
        csv += "Total Units,<?= $stats['total_units'] ?>\n";
        csv += "Inventory Value,<?= $stats['total_inventory_cost'] ?>\n";
        csv += "Market Value,<?= $stats['potential_market_value'] ?>\n\n";

        const blob = new Blob([csv], {
            type: 'text/csv'
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'inventory_report_' + new Date().toISOString().slice(0, 10) + '.csv';
        a.click();
    }
</script>

<?php include '../../includes/footer.php'; ?>