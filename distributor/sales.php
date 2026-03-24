<?php
/**
 * Distributor View Sales - Read Only
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = "My Sales Record";
define('CURRENCY_SYMBOL', 'PKR ');

$sales = [];
if (isset($conn)) {
    $result = mysqli_query($conn, "
        SELECT s.*, o.id as order_no, o.order_type 
        FROM sales s 
        JOIN orders o ON s.order_id = o.id 
        WHERE s.customer_id = $user_id 
        ORDER BY s.created_at DESC
    ");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) $sales[] = $row;
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<div class="px-5 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-dark mb-0">My Sales Distribution</h4>
        <div class="text-muted small">Total Records: <?= count($sales) ?></div>
    </div>

    <div class="bg-white rounded-4 border border-slate-200 shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="bg-light">
                    <tr class="text-uppercase small fw-bold text-muted" style="font-size: 11px;">
                        <th class="ps-4 py-3">Sale ID</th>
                        <th class="py-3">Order Ref</th>
                        <th class="py-3">Amount</th>
                        <th class="py-3">Final Amount</th>
                        <th class="py-3">Status</th>
                        <th class="py-3 text-end pe-4">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($sales)): ?>
                        <?php foreach($sales as $s): ?>
                            <tr>
                                <td class="ps-4 fw-bold">#SALE-<?= str_pad($s['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                <td class="fw-bold text-primary">ORD-<?= str_pad($s['order_no'], 5, '0', STR_PAD_LEFT) ?></td>
                                <td class="text-muted"><?= CURRENCY_SYMBOL ?><?= number_format($s['total_amount'], 2) ?></td>
                                <td class="fw-bold text-dark"><?= CURRENCY_SYMBOL ?><?= number_format($s['final_amount'], 2) ?></td>
                                <td>
                                    <span class="badge rounded-pill bg-<?= $s['status'] == 'completed' ? 'success' : 'warning' ?> px-2 py-1 small fw-bold">
                                        <?= strtoupper($s['status']) ?>
                                    </span>
                                </td>
                                <td class="text-muted small text-end pe-4"><?= date('d M Y, h:i A', strtotime($s['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No sales distribution record found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
