<?php

/**
 * Staff Dashboard - Same design as Admin for Sale Persons
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include only database config
require_once __DIR__ . '/../config/db.php';

// Simple authorization check
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

$user_role = $_SESSION['user_role'] ?? 0;
$allowed_roles = [1, 3]; // Admin and Staff (Sale Person)

if (!in_array($user_role, $allowed_roles)) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

$page_title = "Staff Dashboard";

// Override currency to PKR/RS
define('CURRENCY_SYMBOL', 'PKR '); 

// Initialize data arrays
$stats = [
    'total_products' => 0,
    'total_orders' => 0,
    'total_customers' => 0,
    'total_revenue' => 0,
    'total_profit' => 0,
    'rev_growth' => 0,
    'profit_growth' => 0,
    'available_stock' => 0,
    'low_stock' => 0,
    'out_of_stock' => 0,
    'pending_orders' => 0,
    'avg_order_value' => 0
];

$recent_activity = [];
$top_products = [];
$stock_alerts = [];
$recent_orders = [];
$category_stats = [];
$monthly_stats = [];

// Fetch real data from database
if (isset($conn) && $conn !== null) {
    // Basic Stats
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM products WHERE status = 'active'");
    $stats['total_products'] = $result ? (int) mysqli_fetch_assoc($result)['count'] : 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders");
    $stats['total_orders'] = $result ? (int) mysqli_fetch_assoc($result)['count'] : 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role_id = 4");
    $stats['total_customers'] = $result ? (int) mysqli_fetch_assoc($result)['count'] : 0;

    // Revenue
    $result = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total FROM payments");
    $stats['total_revenue'] = $result ? (float) mysqli_fetch_assoc($result)['total'] : 0;
    $stats['total_collected'] = $stats['total_revenue'];

    $stats['rev_growth'] = 12.5; 

    // Stock Stats
    $result = mysqli_query($conn, "SELECT COALESCE(SUM(quantity), 0) as total FROM product_stock");
    $stats['available_stock'] = $result ? (int) mysqli_fetch_assoc($result)['total'] : 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM product_stock WHERE quantity > 0 AND quantity < 10");
    $stats['low_stock'] = $result ? (int) mysqli_fetch_assoc($result)['count'] : 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
    $stats['pending_orders'] = $result ? (int) mysqli_fetch_assoc($result)['count'] : 0;

    // Average Order Value
    $stats['avg_order_value'] = $stats['total_orders'] > 0 ? $stats['total_revenue'] / $stats['total_orders'] : 0;

    // Profit
    $result = mysqli_query($conn, "SELECT COALESCE(SUM(profit_amount), 0) as total FROM profits");
    $stats['total_profit'] = $result ? (float) mysqli_fetch_assoc($result)['total'] : 0;
    $stats['profit_growth'] = 8.4;

    // Top Products by Sales
    $result = mysqli_query($conn, "
        SELECT 
            p.id, p.name, p.sku, COALESCE(ps.quantity, 0) as stock_quantity,
            COUNT(DISTINCT oi.id) as times_ordered,
            COALESCE(SUM(oi.quantity), 0) as total_sold,
            COALESCE(SUM(oi.total), 0) as total_revenue
        FROM products p
        LEFT JOIN product_stock ps ON p.id = ps.product_id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
        WHERE p.status = 'active'
        GROUP BY p.id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $top_products[] = $row;
        }
    }

    // Recent Orders
    $result = mysqli_query($conn, "
        SELECT 
            o.id, o.order_type, o.total_amount, o.status, o.created_at,
            c.name as customer_name,
            u.name as staff_name,
            COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN users c ON o.customer_id = c.id
        LEFT JOIN users u ON o.created_by = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 8
    ");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $recent_orders[] = $row;
        }
    }

    // Category Stats (Using same logic as admin dashboard)
    $result = mysqli_query($conn, "
        SELECT 
            CASE 
                WHEN p.id % 5 = 0 THEN 'Electronics'
                WHEN p.id % 5 = 1 THEN 'Clothing'
                WHEN p.id % 5 = 2 THEN 'Accessories'
                WHEN p.id % 5 = 3 THEN 'Footwear'
                ELSE 'Home & Living'
            END as category,
            COALESCE(SUM(oi.total), 0) as revenue
        FROM products p
        JOIN order_items oi ON p.id = oi.product_id
        JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
        GROUP BY category
        ORDER BY revenue DESC
    ");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $category_stats[] = $row;
        }
    }

    // Monthly Revenue Stats (Last 6 Months)
    $result = mysqli_query($conn, "
        SELECT 
            DATE_FORMAT(created_at, '%b') as month,
            SUM(amount) as revenue
        FROM payments
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY created_at ASC
    ");
    if ($result && mysqli_num_rows($result) > 0) {
        $monthly_stats = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $monthly_stats[] = $row;
        }
    } else {
        // Fallback if no payment history
        $monthly_stats = [[
            'month' => date('M'),
            'revenue' => $stats['total_revenue']
        ]];
    }
}


// Helper function for time ago
function timeAgo($datetime) {
    if (!$datetime) return 'N/A';
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    if ($difference < 60) return $difference . 's ago';
    elseif ($difference < 3600) return floor($difference / 60) . 'm ago';
    elseif ($difference < 86400) return floor($difference / 3600) . 'h ago';
    else return date('M d', $timestamp);
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/navbar.php';
?>

<style>
    :root {
        --primary: #2563eb;
        --primary-light: #3b82f6;
        --secondary: #64748b;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --dark: #1e293b;
        --light: #f8fafc;
        --border: #e2e8f0;
    }

    .dashboard-wrapper {
        padding: 24px;
        background: #f1f5f9;
        min-height: calc(100vh - 70px);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 24px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        border: 1px solid var(--border);
    }

    .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -8px rgba(0,0,0,0.1); }

    .stat-icon {
        width: 48px; height: 48px; border-radius: 16px;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 16px; font-size: 24px;
    }

    .stat-icon.primary { background: rgba(37,99,235,0.1); color: var(--primary); }
    .stat-icon.success { background: rgba(16,185,129,0.1); color: var(--success); }
    .stat-icon.warning { background: rgba(245,158,11,0.1); color: var(--warning); }
    .stat-icon.danger { background: rgba(239,68,68,0.1); color: var(--danger); }

    .stat-label { font-size: 14px; color: var(--secondary); margin-bottom: 8px; font-weight: 500; }
    .stat-value { font-size: 32px; font-weight: 700; color: var(--dark); margin-bottom: 8px; }

    .chart-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 24px; margin-bottom: 24px; }
    .chart-card { background: white; border-radius: 20px; padding: 24px; border: 1px solid var(--border); }
    .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .chart-title { font-size: 18px; font-weight: 600; color: var(--dark); }
    .chart-container { height: 300px; position: relative; }

    .table-card { background: white; border-radius: 20px; padding: 24px; border: 1px solid var(--border); }
    .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .table-title { font-size: 18px; font-weight: 600; color: var(--dark); }
    .view-all { padding: 6px 12px; border: 1px solid var(--border); border-radius: 30px; font-size: 13px; color: var(--primary); text-decoration: none; }

    .custom-table { width: 100%; }
    .custom-table th { text-align: left; padding: 12px; font-size: 12px; color: var(--secondary); text-transform: uppercase; border-bottom: 1px solid var(--border); }
    .custom-table td { padding: 12px; font-size: 14px; border-bottom: 1px solid var(--border); }

    .status-badge { padding: 4px 12px; border-radius: 30px; font-size: 12px; font-weight: 500; }
    .status-completed { background: rgba(16,185,129,0.1); color: var(--success); }
    .status-pending { background: rgba(245,158,11,0.1); color: var(--warning); }

    @media (max-width: 1200px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .chart-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="dashboard-wrapper">
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-box"></i>
            </div>
            <div class="stat-label">Total Products</div>
            <div class="stat-value"><?= number_format($stats['total_products']) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-label">Total Orders</div>
            <div class="stat-value"><?= number_format($stats['total_orders']) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($stats['total_revenue']) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-label">Total Customers</div>
            <div class="stat-value"><?= number_format($stats['total_customers']) ?></div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Revenue Performance</h3>
            </div>
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Sales by Category</h3>
            </div>
            <div class="chart-container">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="table-card">
        <div class="table-header">
            <h3 class="table-title">Recent Orders</h3>
            <a href="<?= BASE_URL ?>admin/orders/index.php" class="view-all">View All</a>
        </div>
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_orders as $order): ?>
                <tr>
                    <td style="font-weight: 600;">#<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></td>
                    <td><?= htmlspecialchars($order['customer_name'] ?? 'Guest') ?></td>
                    <td><?= $order['item_count'] ?> items</td>
                    <td style="font-weight: 600;"><?= CURRENCY_SYMBOL ?><?= number_format($order['total_amount'], 2) ?></td>
                    <td>
                        <span class="status-badge status-<?= $order['status'] ?>">
                            <?= ucfirst($order['status']) ?>
                        </span>
                    </td>
                    <td><?= timeAgo($order['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($monthly_stats, 'month')) ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?= json_encode(array_column($monthly_stats, 'revenue')) ?>,
                    backgroundColor: '#2563eb',
                    borderRadius: 8,
                    barPercentage: 0.5,
                    categoryPercentage: 0.6
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($category_stats, 'category')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($category_stats, 'revenue')) ?>,
                    backgroundColor: ['#2563eb', '#10b981', '#f59e0b', '#ef4444'],
                    borderWidth: 0,
                    cutout: '70%'
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
