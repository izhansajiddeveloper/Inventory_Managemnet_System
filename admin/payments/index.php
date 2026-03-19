<?php

/**
 * Payment Management - Dashboard
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
$allowed_roles = [1, 3]; // Admin and Staff

if (!in_array($user_role, $allowed_roles)) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

$page_title = "Payments History";
$page_icon = "fa-receipt";
$page_description = "Monitor and manage customer payments across all orders and sales";

// Helper functions
function format_price($amount)
{
    return 'Rs. ' . number_format($amount, 2);
}

function display_flash_message()
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        $alertClass = $flash['type'] == 'success' ? 'alert-success' : 'alert-danger';
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">';
        echo '<i class="fa-solid ' . ($flash['type'] == 'success' ? 'fa-circle-check' : 'fa-circle-exclamation') . ' me-2"></i>';
        echo $flash['message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        unset($_SESSION['flash']);
    }
}

// Search and Filter logic
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$method_filter = isset($_GET['method']) ? mysqli_real_escape_string($conn, $_GET['method']) : '';
$start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, $_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, $_GET['end_date']) : '';

// Base query for payments joined with orders and sales
$query = "SELECT p.*, o.id as order_id, s.id as sale_id,
                 CASE 
                    WHEN p.order_id IS NOT NULL THEN (SELECT name FROM users WHERE id = o.customer_id)
                    WHEN p.sale_id IS NOT NULL THEN (SELECT name FROM users WHERE id = s.customer_id)
                    ELSE 'Walk-in'
                 END as customer_name
          FROM payments p 
          LEFT JOIN orders o ON p.order_id = o.id 
          LEFT JOIN sales s ON p.sale_id = s.id
          WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (p.order_id LIKE '%$search%' OR p.sale_id LIKE '%$search%' OR p.transaction_id LIKE '%$search%')";
}

if (!empty($status_filter)) {
    $query .= " AND p.status = '$status_filter'";
}

if (!empty($method_filter)) {
    $query .= " AND p.payment_method = '$method_filter'";
}

if (!empty($start_date)) {
    $query .= " AND DATE(p.created_at) >= '$start_date'";
}

if (!empty($end_date)) {
    $query .= " AND DATE(p.created_at) <= '$end_date'";
}

$query .= " ORDER BY p.created_at DESC";

$payments = [];
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $payments[] = $row;
    }
}

// Stats
$stats_query = "SELECT 
    COUNT(*) as total_payments,
    SUM(amount) as total_amount,
    SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END) as cash_total,
    SUM(CASE WHEN payment_method = 'online' THEN amount ELSE 0 END) as online_total
    FROM payments";

$stats_result = mysqli_query($conn, $stats_query);
$stats = $stats_result ? mysqli_fetch_assoc($stats_result) : ['total_payments' => 0, 'total_amount' => 0, 'cash_total' => 0, 'online_total' => 0];

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
    :root {
        --card-shadow: 0 10px 40px -15px rgba(0, 0, 0, 0.1);
        --border-light: 1px solid #f1f5f9;
        --primary: #2563eb;
    }

    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 20px;
        border: var(--border-light);
        box-shadow: var(--card-shadow);
        transition: all 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        margin-bottom: 1rem;
    }

    .stat-value {
        font-size: 1.2rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 0.2rem;
    }

    .stat-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .table-container {
        background: white;
        border-radius: 20px;
        border: var(--border-light);
        box-shadow: var(--card-shadow);
        overflow: hidden;
        margin-top: 1.5rem;
    }

    .status-badge {
        padding: 0.4rem 1rem;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .badge-paid {
        background: #ecfdf5;
        color: #059669;
    }

    .badge-partial {
        background: #fff7ed;
        color: #ea580c;
    }

    .badge-unpaid {
        background: #fef2f2;
        color: #dc2626;
    }

    .badge-cash {
        background: #f1f5f9;
        color: #475569;
    }

    .badge-online {
        background: #eef2ff;
        color: #4f46e5;
    }

    .badge-bank {
        background: #fef3c7;
        color: #b45309;
    }

    .badge-card {
        background: #e0e7ff;
        color: #4338ca;
    }

    .search-wrapper {
        position: relative;
        width: 100%;
    }

    .search-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }

    .search-input {
        width: 100%;
        height: 44px;
        padding: 0 1rem 0 2.8rem;
        background: white;
        border: var(--border-light);
        border-radius: 12px;
        font-size: 0.9rem;
        transition: all 0.2s;
    }

    .search-input:focus {
        outline: none;
        border-color: #2563eb;
    }

    .btn-create {
        background: var(--primary);
        color: white;
        padding: 0.7rem 1.4rem;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.9rem;
        border: none;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        text-decoration: none;
    }

    .btn-create:hover {
        background: #1d4ed8;
        color: white;
        transform: translateY(-2px);
    }
</style>

<div class="px-3 py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-blue-50 text-blue-600 rounded-4 border border-blue-100">
                <i class="fa-solid fa-receipt fa-xl"></i>
            </div>
            <div>
                <h1 class="h4 fw-bold text-slate-900 mb-0"><?= $page_title ?></h1>
                <p class="text-slate-500 small mb-0"><?= $page_description ?></p>
            </div>
        </div>
        <a href="add.php" class="btn-create">
            <i class="fa-solid fa-plus"></i>
            <span>Add Payment</span>
        </a>
    </div>

    <?php display_flash_message(); ?>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-indigo-50 text-indigo-600"><i class="fa-solid fa-wallet"></i></div>
                <div class="stat-value text-indigo-600"><?= format_price($stats['total_amount'] ?? 0) ?></div>
                <div class="stat-label">Total Collected</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-emerald-50 text-emerald-600"><i class="fa-solid fa-money-bill-wave"></i></div>
                <div class="stat-value text-emerald-600"><?= format_price($stats['cash_total'] ?? 0) ?></div>
                <div class="stat-label">Cash Total</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-blue-50 text-blue-600"><i class="fa-solid fa-credit-card"></i></div>
                <div class="stat-value text-blue-600"><?= format_price($stats['online_total'] ?? 0) ?></div>
                <div class="stat-label">Online Total</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-amber-50 text-amber-600"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                <div class="stat-value text-amber-600"><?= number_format($stats['total_payments'] ?? 0) ?></div>
                <div class="stat-label">Transactions</div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="bg-white p-3 rounded-4 border border-slate-100 shadow-sm mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-3">
                <div class="search-wrapper">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" name="search" class="search-input" placeholder="ID or Customer Name" value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-lg-2">
                <label class="form-label small fw-bold text-slate-600">Method</label>
                <select name="method" class="form-select rounded-3 py-2 border-slate-200">
                    <option value="">All Methods</option>
                    <option value="cash" <?= $method_filter == 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="online" <?= $method_filter == 'online' ? 'selected' : '' ?>>Online</option>
                    <option value="bank" <?= $method_filter == 'bank' ? 'selected' : '' ?>>Bank</option>
                    <option value="card" <?= $method_filter == 'card' ? 'selected' : '' ?>>Card</option>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label small fw-bold text-slate-600">From Date</label>
                <input type="date" name="start_date" class="form-control rounded-3 py-2 border-slate-200" value="<?= $start_date ?>">
            </div>
            <div class="col-lg-2">
                <label class="form-label small fw-bold text-slate-600">To Date</label>
                <input type="date" name="end_date" class="form-control rounded-3 py-2 border-slate-200" value="<?= $end_date ?>">
            </div>
            <div class="col-lg-2">
                <button type="submit" class="btn btn-dark w-100 py-2 rounded-3 fw-bold">Apply Filter</button>
            </div>
            <div class="col-lg-1">
                <a href="index.php" class="btn btn-light w-100 py-2 rounded-3 border text-slate-500" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>

    <!-- Payments Table -->
    <div class="table-container">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr class="bg-slate-50">
                        <th class="ps-4 py-3">ID</th>
                        <th class="py-3">Ref ID</th>
                        <th class="py-3">Customer</th>
                        <th class="py-3">Method / TRX</th>
                        <th class="py-3 text-end">Amount</th>
                        <th class="py-3 text-center">Status</th>
                        <th class="py-3 text-end pe-4">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($payments)): ?>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td class="ps-4">
                                    <a href="view.php?id=<?= $p['id'] ?>" class="fw-bold text-slate-800 text-decoration-none">#PAY-<?= str_pad($p['id'], 5, '0', STR_PAD_LEFT) ?></a>
                                </td>
                                <td>
                                    <?php if ($p['order_id']): ?>
                                        <a href="../orders/view.php?id=<?= $p['order_id'] ?>" class="fw-bold text-blue-600 text-decoration-none small">ORD#<?= str_pad($p['order_id'], 5, '0', STR_PAD_LEFT) ?></a>
                                    <?php elseif ($p['sale_id']): ?>
                                        <a href="../sales/view.php?id=<?= $p['sale_id'] ?>" class="fw-bold text-purple-600 text-decoration-none small">SALE#<?= str_pad($p['sale_id'], 5, '0', STR_PAD_LEFT) ?></a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-slate-700 small"><?= htmlspecialchars($p['customer_name'] ?? 'Walk-in Customer') ?></div>
                                </td>
                                <td>
                                    <span class="status-badge badge-<?= $p['payment_method'] ?>">
                                        <i class="fa-solid <?= in_array($p['payment_method'], ['cash']) ? 'fa-money-bill-wave' : (in_array($p['payment_method'], ['card']) ? 'fa-credit-card' : 'fa-building-columns') ?>"></i>
                                        <?= strtoupper($p['payment_method']) ?>
                                    </span>
                                    <?php if ($p['transaction_id']): ?>
                                        <div class="small text-slate-400 mt-1">TRX: <?= htmlspecialchars($p['transaction_id']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold text-slate-900"><?= format_price($p['amount']) ?></td>
                                <td class="text-center">
                                    <span class="status-badge badge-<?= $p['status'] ?>">
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                </td>
                                <td class="small text-slate-500 text-end pe-4">
                                    <?= date('d M, Y', strtotime($p['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="mb-3 text-slate-300"><i class="fa-solid fa-receipt fa-3x"></i></div>
                                <h5 class="fw-bold text-slate-600">No Payments Found</h5>
                                <p class="text-slate-400">Match your search/filters or record a new payment</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>