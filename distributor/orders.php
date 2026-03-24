<?php
/**
 * Distributor View Orders - Read Only
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = "My Orders";
define('CURRENCY_SYMBOL', 'PKR ');

$orders = [];
if (isset($conn)) {
    $result = mysqli_query($conn, "
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count 
        FROM orders o 
        WHERE o.customer_id = $user_id 
        ORDER BY o.created_at DESC
    ");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) $orders[] = $row;
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<div class="px-5 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-dark mb-0">My Purchase Orders</h4>
        <div class="text-muted small">Total Orders: <?= count($orders) ?></div>
    </div>

    <div class="bg-white rounded-4 border border-slate-200 overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="bg-light">
                    <tr class="text-uppercase small fw-bold text-muted" style="font-size: 11px;">
                        <th class="ps-4 py-3">Order ID</th>
                        <th class="py-3">Details</th>
                        <th class="py-3">Amount</th>
                        <th class="py-3">Status</th>
                        <th class="py-3">Date</th>
                        <th class="text-end pe-4 py-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($orders)): ?>
                        <?php foreach($orders as $o): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary">#ORD-<?= str_pad($o['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                <td class="small text-muted"><?= $o['item_count'] ?> items | <?= ucfirst($o['order_type']) ?></td>
                                <td class="fw-bold"><?= CURRENCY_SYMBOL ?><?= number_format($o['final_amount'], 2) ?></td>
                                <td>
                                    <span class="badge rounded-pill bg-<?= $o['status'] == 'completed' ? 'success' : ($o['status'] == 'pending' ? 'warning' : 'danger') ?> px-2 py-1 small fw-bold">
                                        <?= strtoupper($o['status']) ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                                <td class="text-end pe-4">
                                    <a href="<?= BASE_URL ?>admin/orders/invoice/index.php?order_id=<?= $o['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary border-0 rounded-3">
                                        <i class="fa-solid fa-file-invoice me-1"></i> Invoice
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No orders found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
