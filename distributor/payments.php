<?php
/**
 * Distributor View Payments - Read Only
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = "My Payments";
define('CURRENCY_SYMBOL', 'PKR ');

$payments = [];
if (isset($conn)) {
    $result = mysqli_query($conn, "
        SELECT p.*, o.id as order_no 
        FROM payments p 
        JOIN orders o ON p.order_id = o.id 
        WHERE o.customer_id = $user_id 
        ORDER BY p.created_at DESC
    ");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) $payments[] = $row;
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<div class="px-5 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-dark mb-0">My Payment History</h4>
        <div class="text-muted small">Total Transactions: <?= count($payments) ?></div>
    </div>

    <div class="bg-white rounded-4 border border-slate-200 overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="bg-light">
                    <tr class="text-uppercase small fw-bold text-muted" style="font-size: 11px;">
                        <th class="ps-4 py-3">Pay ID</th>
                        <th class="py-3">Order Ref</th>
                        <th class="py-3">Method</th>
                        <th class="py-3">Amount</th>
                        <th class="py-3">Status</th>
                        <th class="py-3 text-end pe-4">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($payments)): ?>
                        <?php foreach($payments as $p): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted">#<?= str_pad($p['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                <td class="fw-bold text-primary">ORD-<?= str_pad($p['order_no'], 5, '0', STR_PAD_LEFT) ?></td>
                                <td class="small text-muted text-uppercase"><?= $p['payment_method'] ?></td>
                                <td class="fw-bold text-success"><?= CURRENCY_SYMBOL ?><?= number_format($p['amount'], 2) ?></td>
                                <td>
                                    <span class="badge rounded-pill bg-<?= $p['status'] == 'paid' ? 'success' : 'warning' ?> px-2 py-1 small fw-bold">
                                        <?= strtoupper($p['status']) ?>
                                    </span>
                                </td>
                                <td class="text-muted small text-end pe-4"><?= date('d M Y, h:i A', strtotime($p['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No payments recorded.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
