<?php

/**
 * Sale Payments — Financial records of revenue through sales
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

$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;
$method  = isset($_GET['method'])  ? sanitize($_GET['method'])  : '';

$page_title       = "Sale Payments";
$page_icon        = "fa-money-bill-transfer";
$page_description = "Track all money transactions recorded against sales records";

// Build query
$query = "
    SELECT p.*, 
           s.id AS sale_ref_id,
           c.name AS customer_name,
           u.name AS cashier_name
    FROM payments p
    JOIN sales s ON p.sale_id = s.id
    LEFT JOIN users c ON s.customer_id = c.id
    LEFT JOIN users u ON s.created_by  = u.id
    WHERE 1=1";

if ($sale_id) {
    $query .= " AND p.sale_id = $sale_id";
}
if (!empty($method)) {
    $query .= " AND p.payment_method = '$method'";
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
$stats_query = "
    SELECT 
        COALESCE(SUM(amount), 0) AS total_revenue,
        COUNT(*) AS total_count,
        COALESCE(SUM(CASE WHEN payment_method='cash' THEN amount ELSE 0 END),0) AS cash_total,
        COALESCE(SUM(CASE WHEN payment_method='card' THEN amount ELSE 0 END),0) AS card_total
    FROM payments 
    WHERE sale_id IS NOT NULL";

$stats_result = mysqli_query($conn, $stats_query);
$stats = $stats_result ? mysqli_fetch_assoc($stats_result) : ['total_revenue' => 0, 'total_count' => 0, 'cash_total' => 0, 'card_total' => 0];

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
    .uppercase {
        text-transform: uppercase;
    }

    .tracking-wider {
        letter-spacing: .05em;
    }

    .table-wrap {
        background: #fff;
        border-radius: 20px;
        border: 1px solid #f1f5f9;
        box-shadow: 0 10px 40px -15px rgba(0, 0, 0, .1);
        overflow: hidden;
        margin-top: 1.5rem;
    }

    .stat-mini {
        background: #fff;
        padding: 1.25rem;
        border-radius: 16px;
        border: 1px solid #f1f5f9;
        box-shadow: 0 4px 20px -10px rgba(0, 0, 0, .1);
    }

    .p-badge {
        padding: .3rem .7rem;
        border-radius: 10px;
        font-size: .65rem;
        font-weight: 700;
        text-transform: uppercase;
    }
</style>

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
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-dark rounded-3 px-3 py-2 fw-bold">← Sales</a>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-mini">
                <div class="small fw-bold text-slate-400 uppercase tracking-wider mb-1">Total Sale Revenue</div>
                <div class="h5 fw-bold text-blue-600 mb-0"><?= format_price($stats['total_revenue']) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-mini">
                <div class="small fw-bold text-slate-400 uppercase tracking-wider mb-1">Total Transactions</div>
                <div class="h5 fw-bold text-slate-800 mb-0"><?= number_format($stats['total_count']) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-mini">
                <div class="small fw-bold text-slate-400 uppercase tracking-wider mb-1">Cash Inflow</div>
                <div class="h5 fw-bold text-emerald-600 mb-0"><?= format_price($stats['cash_total']) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-mini">
                <div class="small fw-bold text-slate-400 uppercase tracking-wider mb-1">Digital/Card Flow</div>
                <div class="h5 fw-bold text-indigo-600 mb-0"><?= format_price($stats['card_total']) ?></div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white p-3 rounded-4 border border-slate-100 shadow-sm mb-4">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-lg-3">
                <input type="number" name="sale_id" class="form-control border-slate-200" placeholder="Filter by Sale ID..." value="<?= $sale_id ?: '' ?>">
            </div>
            <div class="col-lg-3">
                <select name="method" class="form-select border-slate-200">
                    <option value="">All Payment Methods</option>
                    <option value="cash" <?= $method == 'cash'   ? 'selected' : '' ?>>Cash</option>
                    <option value="bank" <?= $method == 'bank'   ? 'selected' : '' ?>>Bank Transfer</option>
                    <option value="card" <?= $method == 'card'   ? 'selected' : '' ?>>Credit/Debit Card</option>
                    <option value="online" <?= $method == 'online' ? 'selected' : '' ?>>Online Payment</option>
                </select>
            </div>
            <div class="col-lg-2">
                <button type="submit" class="btn btn-primary text-white w-100 fw-bold rounded-3">Filter</button>
            </div>
            <div class="col-lg-1">
                <a href="payments.php" class="btn btn-light w-100 fw-bold border rounded-3">Reset</a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="table-wrap">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 small uppercase fw-bold">
                        <th class="ps-4 py-3">Pay ID</th>
                        <th class="py-3">Sale Reference</th>
                        <th class="py-3">Customer</th>
                        <th class="py-3">Method</th>
                        <th class="py-3 text-end">Amount Paid</th>
                        <th class="py-3 text-center">Status</th>
                        <th class="py-3">Cashier</th>
                        <th class="text-end pe-4 py-3">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($payments)): ?>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-blue-600">
                                    <a href="../payments/view.php?id=<?= $p['id'] ?>" class="text-decoration-none">
                                        #PAY-<?= str_pad($p['id'], 5, '0', STR_PAD_LEFT) ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="view.php?id=<?= $p['sale_id'] ?>" class="small fw-bold text-slate-700">
                                        #SALE-<?= str_pad($p['sale_id'], 5, '0', STR_PAD_LEFT) ?>
                                    </a>
                                </td>
                                <td class="fw-semibold text-slate-800"><?= htmlspecialchars($p['customer_name'] ?? 'Walk-in') ?></td>
                                <td>
                                    <span class="badge bg-slate-100 text-slate-600 px-2 uppercase fw-bold" style="font-size:.6rem">
                                        <?= $p['payment_method'] ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold text-emerald-600">
                                    <?= format_price($p['amount']) ?>
                                </td>
                                <td class="text-center">
                                    <span class="p-badge" style="background:<?= $p['status'] == 'paid' ? '#ecfdf5;color:#059669' : '#fef2f2;color:#dc2626' ?>">
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                </td>
                                <td class="text-slate-500 small"><?= htmlspecialchars($p['cashier_name'] ?? 'System') ?></td>
                                <td class="text-end pe-4 text-slate-400 small">
                                    <?= date('d M Y, h:i A', strtotime($p['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="fa-solid fa-receipt fa-3x text-slate-200 mb-3"></i>
                                <h6 class="fw-bold text-slate-500">No sale payments found.</h6>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>