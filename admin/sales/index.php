<?php
/**
 * Sales Management — Minimalist Dashboard
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';

authorize([ROLE_ADMIN, ROLE_STAFF]);

$page_title       = "Sales Management";
$page_icon        = "fa-cash-register";
$page_description = "Track and manage all business sales and payment distributions";

// Filters
$search        = isset($_GET['search'])   ? sanitize($_GET['search'])   : '';
$status_filter = isset($_GET['status'])   ? sanitize($_GET['status'])   : '';
$pay_filter    = isset($_GET['payment'])  ? sanitize($_GET['payment'])  : '';
$date_filter   = isset($_GET['date'])     ? sanitize($_GET['date'])     : '';

// Build query
$query = "
    SELECT s.*,
           c.name AS customer_name,
           u.name AS staff_name,
           o.order_type,
           COALESCE((SELECT SUM(p2.amount) FROM payments p2 WHERE p2.sale_id = s.id OR (s.order_id IS NOT NULL AND p2.order_id = s.order_id)), 0) AS total_paid
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN users     u ON s.created_by  = u.id
    LEFT JOIN orders    o ON s.order_id    = o.id
    WHERE 1=1";
$params = [];

if ($search) {
    $query   .= " AND (s.id LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status_filter) {
    $query   .= " AND s.status = ?";
    $params[] = $status_filter;
}
if ($date_filter) {
    $query   .= " AND DATE(s.created_at) = ?";
    $params[] = $date_filter;
}

// Payment status filter is applied after fetching
$query .= " ORDER BY s.created_at DESC";

try {
    $stmt  = $pdo->prepare($query);
    $stmt->execute($params);
    $all_sales = $stmt->fetchAll();

    // Compute payment status per row, then optionally filter
    $sales = [];
    foreach ($all_sales as $s) {
        $bal = $s['final_amount'] - $s['total_paid'];
        if ($s['total_paid'] <= 0)    $ps = 'unpaid';
        elseif ($bal > 0.01)          $ps = 'partial';
        else                          $ps = 'paid';
        $s['pay_status'] = $ps;
        $s['balance']    = max(0, $bal);
        if (!$pay_filter || $pay_filter === $ps) $sales[] = $s;
    }

} catch (PDOException $e) {
    $sales = [];
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
:root { --card-shadow: 0 10px 40px -15px rgba(0,0,0,.1); --border-light: 1px solid #f1f5f9; }
.table-wrap  { background:#fff;border-radius:20px;border:var(--border-light);box-shadow:var(--card-shadow);overflow:hidden;margin-top:1.5rem; }
.sbadge     { padding:.3rem .8rem;border-radius:10px;font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;display:inline-flex;align-items:center;gap:.3rem; }
.sb-pending   { background:#fff7ed;color:#ea580c; }
.sb-completed { background:#ecfdf5;color:#059669; }
.sb-cancelled { background:#fef2f2;color:#dc2626; }
.sb-paid      { background:#ecfdf5;color:#059669; }
.sb-partial   { background:#fefce8;color:#ca8a04; }
.sb-unpaid    { background:#fef2f2;color:#dc2626; }
.btn-new  { background:#2563eb;color:#fff;padding:.7rem 1.4rem;border-radius:12px;font-weight:700;font-size:.9rem;border:none;transition:all .2s;display:inline-flex;align-items:center;gap:.6rem;text-decoration:none; }
.btn-new:hover { background:#1d4ed8;transform:translateY(-2px);box-shadow:0 10px 24px -8px #2563eb;color:#fff; }
.srch  { position:relative; }
.srch i { position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94a3b8;z-index:1; }
.srch-inp { width:100%;height:44px;padding:0 1rem 0 2.8rem;border:var(--border-light);border-radius:12px;font-size:.9rem; }
.srch-inp:focus { outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.uppercase { text-transform:uppercase; }
.tracking-wider { letter-spacing:.05em; }
</style>

<div class="px-3 py-4">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 fw-bold text-slate-900 mb-0"><?= $page_title ?></h1>
        <div class="d-flex gap-2">
            <a href="../orders/create.php" class="btn-new px-3">
                <i class="fa-solid fa-plus"></i> New Sale
            </a>
        </div>
    </div>

    <?php display_flash_message(); ?>

    <!-- Filters -->
    <div class="bg-white p-3 rounded-4 border border-slate-100 shadow-sm mb-4">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-lg-3">
                <div class="srch">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" class="srch-inp" placeholder="Search sale ID or customer..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-lg-2">
                <select name="status" class="form-select border-slate-200 rounded-3 py-2">
                    <option value="">All Sale Status</option>
                    <option value="pending"   <?= $status_filter=='pending'   ? 'selected':'' ?>>Pending</option>
                    <option value="completed" <?= $status_filter=='completed' ? 'selected':'' ?>>Completed</option>
                    <option value="cancelled" <?= $status_filter=='cancelled' ? 'selected':'' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-lg-2">
                <select name="payment" class="form-select border-slate-200 rounded-3 py-2">
                    <option value="">All Payments</option>
                    <option value="paid"    <?= $pay_filter=='paid'    ? 'selected':'' ?>>Paid</option>
                    <option value="partial" <?= $pay_filter=='partial' ? 'selected':'' ?>>Partial</option>
                    <option value="unpaid"  <?= $pay_filter=='unpaid'  ? 'selected':'' ?>>Unpaid</option>
                </select>
            </div>
            <div class="col-lg-2">
                <input type="date" name="date" class="form-control border-slate-200 rounded-3 py-2" value="<?= htmlspecialchars($date_filter) ?>">
            </div>
            <div class="col-lg-1">
                <button type="submit" class="btn btn-dark w-100 py-2 rounded-3 fw-bold">Go</button>
            </div>
            <div class="col-lg-2">
                <a href="index.php" class="btn btn-light w-100 py-2 rounded-3 fw-bold border">Clear</a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="table-wrap">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 small uppercase fw-bold">
                        <th class="ps-4 py-3">Sale ID</th>
                        <th class="py-3">Order</th>
                        <th class="py-3">Customer</th>
                        <th class="py-3">Total</th>
                        <th class="py-3">Discount</th>
                        <th class="py-3">Final</th>
                        <th class="py-3">Paid</th>
                        <th class="py-3">Balance</th>
                        <th class="py-3">Payment</th>
                        <th class="py-3">Status</th>
                        <th class="py-3">Date</th>
                        <th class="text-end pe-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($sales)): ?>
                        <?php foreach ($sales as $s): ?>
                        <tr>
                            <td class="ps-4">
                                <span class="fw-bold text-slate-800">#SALE-<?= str_pad($s['id'],5,'0',STR_PAD_LEFT) ?></span>
                            </td>
                            <td>
                                <?php if ($s['order_id']): ?>
                                    <a href="../orders/view.php?id=<?= $s['order_id'] ?>" class="small fw-bold text-blue-600 text-decoration-none">
                                        #ORD-<?= str_pad($s['order_id'],5,'0',STR_PAD_LEFT) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-slate-300 small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold text-slate-700"><?= htmlspecialchars($s['customer_name'] ?? 'Walk-in') ?></td>
                            <td class="text-slate-600"><?= format_price($s['total_amount']) ?></td>
                            <td class="text-rose-600 small">-<?= format_price($s['discount']) ?></td>
                            <td><span class="fw-bold text-slate-900"><?= format_price($s['final_amount']) ?></span></td>
                            <td class="text-emerald-600 fw-semibold"><?= format_price($s['total_paid']) ?></td>
                            <td class="<?= $s['balance'] > 0.01 ? 'text-rose-600 fw-semibold' : 'text-slate-400' ?>">
                                <?= $s['balance'] > 0.01 ? format_price($s['balance']) : '—' ?>
                            </td>
                            <td>
                                <span class="sbadge sb-<?= $s['pay_status'] ?>">
                                    <i class="fa-solid <?= $s['pay_status']=='paid' ? 'fa-circle-check' : ($s['pay_status']=='partial' ? 'fa-circle-half-stroke' : 'fa-clock') ?>"></i>
                                    <?= ucfirst($s['pay_status']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="sbadge sb-<?= $s['status'] ?>">
                                    <i class="fa-solid <?= $s['status']=='completed' ? 'fa-check' : ($s['status']=='pending' ? 'fa-hourglass' : 'fa-xmark') ?>"></i>
                                    <?= ucfirst($s['status']) ?>
                                </span>
                            </td>
                            <td class="small text-slate-400"><?= date('d M Y', strtotime($s['created_at'])) ?></td>
                            <td class="text-end pe-4">
                                <div class="d-flex gap-1 justify-content-end">
                                    <a href="view.php?id=<?= $s['id'] ?>" class="btn btn-light btn-sm rounded-3 px-2 border fw-bold text-slate-600" title="View">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <?php if ($s['pay_status'] !== 'paid' && $s['status'] !== 'cancelled'): ?>
                                    <a href="../payments/add.php?<?= $s['order_id'] ? 'order_id='.$s['order_id'] : 'sale_id='.$s['id'] ?>"
                                       class="btn btn-sm rounded-3 px-2 fw-bold btn-outline-success" title="Add Payment">
                                        <i class="fa-solid fa-hand-holding-dollar"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="receipt.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-sm rounded-3 px-2 fw-bold btn-outline-secondary" title="Receipt">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="text-center py-5">
                                <div class="mb-3 text-slate-200"><i class="fa-solid fa-receipt fa-3x"></i></div>
                                <h5 class="fw-bold text-slate-500">No Sales Found</h5>
                                <p class="text-slate-400 small mb-3">Sales are created automatically when orders are placed.</p>
                                <a href="../orders/create.php" class="btn btn-primary rounded-3 px-4 fw-bold">Create New Order</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
