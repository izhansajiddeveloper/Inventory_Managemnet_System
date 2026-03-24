<?php

/**
 * Distributor Dashboard - Simplified Read-only View
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

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 0;
$allowed_roles = [2]; // Distributor only (Admin/Staff have their own)

if (!in_array($user_role, $allowed_roles)) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

$page_title = "Distributor Dashboard";
define('CURRENCY_SYMBOL', 'PKR '); 

// Initialize data
$stats = [
    'total_orders'    => 0,
    'pending_orders'  => 0,
    'completed_orders'=> 0,
    'total_spent'     => 0,
    'total_unpaid'    => 0
];

$recent_orders = [];
$payment_history = [];

// Fetch distributor specific data
if (isset($conn) && $conn !== null) {
    // Basic stats for THIS distributor
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE customer_id = $user_id");
    $stats['total_orders'] = $result ? (int) mysqli_fetch_assoc($result)['count'] : 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE customer_id = $user_id AND status = 'pending'");
    $stats['pending_orders'] = $result ? (int) mysqli_fetch_assoc($result)['count'] : 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE customer_id = $user_id AND status = 'completed'");
    $stats['completed_orders'] = $result ? (int) mysqli_fetch_assoc($result)['count'] : 0;

    $result = mysqli_query($conn, "SELECT COALESCE(SUM(final_amount), 0) as total FROM orders WHERE customer_id = $user_id");
    $stats['total_spent'] = $result ? (float) mysqli_fetch_assoc($result)['total'] : 0;

    $result = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as paid FROM payments p JOIN orders o ON p.order_id = o.id WHERE o.customer_id = $user_id");
    $total_paid = $result ? (float) mysqli_fetch_assoc($result)['paid'] : 0;
    $stats['total_unpaid'] = max(0, $stats['total_spent'] - $total_paid);

    // Recent orders for THIS distributor
    $result = mysqli_query($conn, "
        SELECT o.*, COUNT(oi.id) as item_count 
        FROM orders o 
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.customer_id = $user_id 
        GROUP BY o.id 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $recent_orders[] = $row;
        }
    }

    // Recent payments for THIS distributor
    $result = mysqli_query($conn, "
        SELECT p.*, o.id as order_ref 
        FROM payments p 
        JOIN orders o ON p.order_id = o.id 
        WHERE o.customer_id = $user_id 
        ORDER BY p.created_at DESC 
        LIMIT 5
    ");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $payment_history[] = $row;
        }
    }
}

// Helper function for time
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
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --dark: #0f172a;
        --light-bg: #f8fafc;
        --border: #e2e8f0;
    }

    .distributor-wrapper {
        padding: 30px;
        background: #f1f5f9;
        min-height: calc(100vh - 70px);
    }

    .welcome-card {
        background: linear-gradient(135deg, #1e293b, #0f172a);
        color: white;
        border-radius: 24px;
        padding: 40px;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
        border: none;
    }

    .welcome-card::after {
        content: '';
        position: absolute;
        width: 300px;
        height: 300px;
        background: rgba(37, 99, 235, 0.1);
        border-radius: 50%;
        top: -100px;
        right: -100px;
    }

    .stats-card {
        background: white;
        border-radius: 20px;
        padding: 24px;
        border: 1px solid var(--border);
        transition: all 0.3s ease;
        height: 100%;
    }

    .stats-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.05); }

    .icon-box {
        width: 44px; height: 44px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 16px; font-size: 20px;
    }

    .bg-blue-light { background: #eff6ff; color: #2563eb; }
    .bg-green-light { background: #f0fdf4; color: #10b981; }
    .bg-amber-light { background: #fffbeb; color: #f59e0b; }
    .bg-rose-light { background: #fff1f2; color: #ef4444; }

    .table-card {
        background: white;
        border-radius: 24px;
        border: 1px solid var(--border);
        overflow: hidden;
        margin-top: 30px;
    }

    .table-header {
        padding: 24px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .custom-table th {
        background: #f8fafc;
        padding: 16px 24px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: 0.5px;
    }

    .custom-table td {
        padding: 16px 24px;
        font-size: 14px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .status-badge {
        padding: 4px 12px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 600;
    }

    .status-completed { background: #ecfdf5; color: #059669; }
    .status-pending { background: #fff7ed; color: #ea580c; }
    .status-cancelled { background: #fef2f2; color: #dc2626; }

    .h-600 { font-weight: 600; }
</style>

<div class="distributor-wrapper">
    <!-- Welcome Header -->
    <div class="welcome-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-6 fw-bold mb-2">Welcome Back, Distributor!</h1>
                <p class="mb-0 opacity-75">Track your orders, view payments, and manage your account summary in real-time.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="h3 fw-bold mb-0"><?= CURRENCY_SYMBOL ?><?= number_format($stats['total_spent'], 2) ?></div>
                <div class="small opacity-50">Total Purchase Volume</div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-4">
        <div class="col-xl-3 col-md-6">
            <div class="stats-card">
                <div class="icon-box bg-blue-light"><i class="fas fa-shopping-bag"></i></div>
                <div class="small text-muted mb-1">Total Orders</div>
                <div class="h4 mb-0 fw-bold"><?= number_format($stats['total_orders']) ?></div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stats-card">
                <div class="icon-box bg-amber-light"><i class="fas fa-clock"></i></div>
                <div class="small text-muted mb-1">Pending Orders</div>
                <div class="h4 mb-0 fw-bold text-warning"><?= number_format($stats['pending_orders']) ?></div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stats-card">
                <div class="icon-box bg-green-light"><i class="fas fa-check-double"></i></div>
                <div class="small text-muted mb-1">Completed</div>
                <div class="h4 mb-0 fw-bold text-success"><?= number_format($stats['completed_orders']) ?></div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stats-card">
                <div class="icon-box bg-rose-light"><i class="fas fa-hand-holding-usd"></i></div>
                <div class="small text-muted mb-1">Due Balance</div>
                <div class="h4 mb-0 fw-bold text-danger"><?= CURRENCY_SYMBOL ?><?= number_format($stats['total_unpaid'], 2) ?></div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Orders -->
        <div class="col-lg-8">
            <div class="table-card">
                <div class="table-header">
                    <h5 class="mb-0 fw-bold">My Recent Orders</h5>
                    <a href="<?= BASE_URL ?>distributor/orders.php" class="btn btn-sm btn-light border px-3 rounded-pill fw-bold text-primary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table custom-table mb-0">
                        <thead>
                            <tr>
                                <th>Order Ref</th>
                                <th>Items</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_orders)): ?>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td><span class="fw-bold">#ORD-<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></span></td>
                                        <td class="text-muted small"><?= $order['item_count'] ?> Products</td>
                                        <td><span class="h-600 text-dark"><?= CURRENCY_SYMBOL ?><?= number_format($order['final_amount'], 2) ?></span></td>
                                        <td>
                                            <span class="status-badge status-<?= $order['status'] ?>">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-muted small"><?= date('d M, Y', strtotime($order['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">No orders placed yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="col-lg-4">
            <div class="table-card">
                <div class="table-header">
                    <h5 class="mb-0 fw-bold">Recent Payments</h5>
                </div>
                <div class="table-responsive">
                    <table class="table custom-table mb-0">
                        <thead>
                            <tr>
                                <th>Ref</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($payment_history)): ?>
                                <?php foreach ($payment_history as $pay): ?>
                                    <tr>
                                        <td><span class="small fw-bold text-primary">#<?= str_pad($pay['id'], 5, '0', STR_PAD_LEFT) ?></span></td>
                                        <td><span class="fw-bold text-success"><?= CURRENCY_SYMBOL ?><?= number_format($pay['amount'], 2) ?></span></td>
                                        <td class="small text-muted"><?= timeAgo($pay['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center py-5 text-muted small">No payment history found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-3 bg-light text-center">
                    <a href="<?= BASE_URL ?>distributor/payments.php" class="small fw-bold text-decoration-none">View Full History →</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
