<?php
/**
 * Sale Detail View
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';

authorize([ROLE_ADMIN, ROLE_STAFF]);

$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$sale_id) { set_flash_message('error','Invalid sale.'); redirect('admin/sales/index.php'); }

try {
    $stmt = $pdo->prepare("
        SELECT s.*,
               c.name  AS customer_name,
               c.phone AS customer_phone,
               c.email AS customer_email,
               u.name  AS staff_name,
               o.order_type,
               o.delivery_charges
        FROM sales s
        LEFT JOIN users c ON s.customer_id = c.id
        LEFT JOIN users u     ON s.created_by  = u.id
        LEFT JOIN orders o    ON s.order_id    = o.id
        WHERE s.id = ?
    ");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch();

    if (!$sale) { set_flash_message('error','Sale not found.'); redirect('admin/sales/index.php'); }

    // Sale items
    $items = $pdo->prepare("
        SELECT si.*, p.name AS product_name, p.sku
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = ?
        ORDER BY si.id ASC
    ");
    $items->execute([$sale_id]);
    $items = $items->fetchAll();

    // All payments linked to this sale (either directly or via order)
    $pay_q = "SELECT p.*, 'link' AS source FROM payments p WHERE p.sale_id = ? OR (p.order_id IS NOT NULL AND p.order_id = ?)";
    $ps = $pdo->prepare($pay_q);
    $ps->execute([$sale_id, $sale['order_id'] ?? 0]);
    $payments   = $ps->fetchAll();
    $total_paid = array_sum(array_column($payments, 'amount'));
    $balance    = $sale['final_amount'] - $total_paid;

    if ($total_paid <= 0)    $pay_status = 'unpaid';
    elseif ($balance > 0.01) $pay_status = 'partial';
    else                     $pay_status = 'paid';

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

$page_title = "Sale #SALE-" . str_pad($sale_id,5,'0',STR_PAD_LEFT);
$page_icon  = "fa-receipt";

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
.uppercase { text-transform:uppercase; }
.tracking-wider { letter-spacing:.05em; }
.sbadge   { padding:.33rem .85rem;border-radius:10px;font-size:.67rem;font-weight:700;text-transform:uppercase;display:inline-flex;align-items:center;gap:.35rem; }
.sb-pending   { background:#fff7ed;color:#ea580c; }
.sb-completed { background:#ecfdf5;color:#059669; }
.sb-cancelled { background:#fef2f2;color:#dc2626; }
.sb-paid      { background:#ecfdf5;color:#059669; }
.sb-partial   { background:#fefce8;color:#ca8a04; }
.sb-unpaid    { background:#fef2f2;color:#dc2626; }
.meta-row { display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid #f1f5f9;font-size:.875rem; }
.meta-row:last-child { border:none; }
.meta-lbl { color:#64748b;font-weight:600;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em; }
.meta-val { font-weight:700;color:#0f172a; }
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
                <p class="text-slate-500 small mb-0">
                    <?= date('d M Y, h:i A', strtotime($sale['created_at'])) ?>
                    &bull; By <?= htmlspecialchars($sale['staff_name'] ?? 'System') ?>
                    <?php if ($sale['order_id']): ?>
                    &bull; Linked to <a href="../orders/view.php?id=<?= $sale['order_id'] ?>" class="fw-bold text-blue-600">
                        #ORD-<?= str_pad($sale['order_id'],5,'0',STR_PAD_LEFT) ?>
                    </a>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="receipt.php?id=<?= $sale_id ?>" target="_blank" class="btn btn-light rounded-3 px-3 py-2 border fw-bold text-slate-600">
                <i class="fa-solid fa-print me-1"></i> Receipt
            </a>
            <?php if ($sale['status'] !== 'cancelled'): ?>
                <?php if ($pay_status !== 'paid'): ?>
                <a href="../payments/add.php?<?= $sale['order_id'] ? 'order_id='.$sale['order_id'] : 'sale_id='.$sale_id ?>"
                class="btn btn-success rounded-3 px-3 py-2 fw-bold">
                    <i class="fa-solid fa-hand-holding-dollar me-1"></i> Add Payment
                </a>
                <?php endif; ?>
                
                <?php if ($role_id == ROLE_ADMIN && $sale['status'] == 'pending'): ?>
                <form action="cancel.php" method="POST" class="d-inline" onsubmit="return confirm('WARNING: This will CANCEL the sale and RESTORE all stock items. Proceed?');">
                    <input type="hidden" name="id" value="<?= $sale_id ?>">
                    <button type="submit" class="btn btn-outline-danger rounded-3 px-3 py-2 fw-bold">
                        <i class="fa-solid fa-ban me-1"></i> Cancel Sale
                    </button>
                </form>
                <?php endif; ?>
            <?php endif; ?>
            <a href="index.php" class="btn btn-dark rounded-3 px-3 py-2 fw-bold">← Sales</a>
        </div>
    </div>

    <?php display_flash_message(); ?>

    <div class="row g-4">

        <!-- LEFT: Status + Customer + Amounts -->
        <div class="col-lg-4">

            <!-- Status Overview -->
            <div class="bg-white p-4 rounded-4 border border-slate-100 shadow-sm mb-4">
                <h6 class="fw-bold text-slate-600 mb-3 small uppercase tracking-wider">Sale Overview</h6>

                <div class="text-center py-3 bg-slate-50 rounded-3 mb-4">
                    <div class="small text-slate-400 fw-bold uppercase mb-1">Final Amount</div>
                    <div class="h3 fw-bold text-slate-900 mb-0"><?= format_price($sale['final_amount']) ?></div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="p-2 bg-emerald-50 rounded-3 text-center">
                            <div class="small fw-bold text-emerald-600 uppercase" style="font-size:.65rem">Paid</div>
                            <div class="fw-bold text-emerald-700"><?= format_price($total_paid) ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 <?= $balance > 0.01 ? 'bg-rose-50' : 'bg-slate-50' ?> rounded-3 text-center">
                            <div class="small fw-bold <?= $balance > 0.01 ? 'text-rose-500' : 'text-slate-400' ?> uppercase" style="font-size:.65rem">Balance</div>
                            <div class="fw-bold <?= $balance > 0.01 ? 'text-rose-700' : 'text-slate-400' ?>"><?= format_price(max(0,$balance)) ?></div>
                        </div>
                    </div>
                </div>

                <div class="meta-row">
                    <span class="meta-lbl">Sale Status</span>
                    <span class="sbadge sb-<?= $sale['status'] ?>">
                        <i class="fa-solid <?= $sale['status']=='completed'?'fa-check':($sale['status']=='pending'?'fa-hourglass':'fa-xmark') ?>"></i>
                        <?= ucfirst($sale['status']) ?>
                    </span>
                </div>
                <div class="meta-row">
                    <span class="meta-lbl">Payment</span>
                    <span class="sbadge sb-<?= $pay_status ?>">
                        <i class="fa-solid <?= $pay_status=='paid'?'fa-circle-check':($pay_status=='partial'?'fa-circle-half-stroke':'fa-clock') ?>"></i>
                        <?= ucfirst($pay_status) ?>
                    </span>
                </div>
                <?php if ($sale['order_id']): ?>
                <div class="meta-row">
                    <span class="meta-lbl">Order</span>
                    <a href="../orders/view.php?id=<?= $sale['order_id'] ?>" class="fw-bold text-blue-600 small">
                        #ORD-<?= str_pad($sale['order_id'],5,'0',STR_PAD_LEFT) ?>
                        <i class="fa-solid fa-arrow-up-right-from-square ms-1" style="font-size:.6rem"></i>
                    </a>
                </div>
                <div class="meta-row">
                    <span class="meta-lbl">Order Type</span>
                    <span class="meta-val small"><?= ucfirst($sale['order_type'] ?? '—') ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Customer -->
            <div class="bg-white p-4 rounded-4 border border-slate-100 shadow-sm mb-4">
                <h6 class="fw-bold text-slate-600 mb-3 small uppercase tracking-wider">Customer</h6>
                <?php if ($sale['customer_name']): ?>
                    <div class="fw-bold text-slate-800 mb-1"><?= htmlspecialchars($sale['customer_name']) ?></div>
                    <div class="small text-slate-500"><?= htmlspecialchars($sale['customer_phone'] ?? '—') ?></div>
                    <div class="small text-slate-400 mt-1"><?= htmlspecialchars($sale['customer_email'] ?? '') ?></div>
                <?php else: ?>
                    <span class="text-slate-400 small fst-italic">Walk-in / No customer</span>
                <?php endif; ?>
            </div>

            <!-- Amounts Breakdown -->
            <div class="bg-white p-4 rounded-4 border border-slate-100 shadow-sm">
                <h6 class="fw-bold text-slate-600 mb-3 small uppercase tracking-wider">Amounts</h6>
                <div class="meta-row"><span class="meta-lbl">Subtotal</span><span class="meta-val"><?= format_price($sale['total_amount']) ?></span></div>
                <div class="meta-row"><span class="meta-lbl text-rose-500">Discount</span><span class="meta-val text-rose-600">- <?= format_price($sale['discount']) ?></span></div>
                <?php if ($sale['delivery_charges'] > 0): ?>
                <div class="meta-row"><span class="meta-lbl text-indigo-500">Delivery</span><span class="meta-val text-indigo-600">+ <?= format_price($sale['delivery_charges']) ?></span></div>
                <?php endif; ?>
                <div class="meta-row"><span class="meta-lbl fw-black text-slate-800">Final Bill</span><span class="meta-val h6 mb-0 text-blue-600"><?= format_price($sale['final_amount']) ?></span></div>
            </div>
        </div>

        <!-- RIGHT: Items + Payment History -->
        <div class="col-lg-8">

            <!-- Sale Items -->
            <div class="bg-white p-4 rounded-4 border border-slate-100 shadow-sm mb-4">
                <h6 class="fw-bold text-slate-700 mb-4 small uppercase tracking-wider">
                    <i class="fa-solid fa-boxes-stacked me-2 text-blue-400"></i>Items Sold
                </h6>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="bg-slate-50 text-slate-500 small uppercase fw-bold">
                            <tr>
                                <th class="ps-3 py-3">Product</th>
                                <th class="py-3">SKU</th>
                                <th class="py-3 text-center">Qty</th>
                                <th class="py-3 text-end">Price</th>
                                <th class="py-3 text-end pe-3">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="ps-3 fw-semibold text-slate-800"><?= htmlspecialchars($item['product_name']) ?></td>
                                <td class="text-slate-400 small"><?= htmlspecialchars($item['sku'] ?? '—') ?></td>
                                <td class="text-center"><span class="badge bg-blue-50 text-blue-700 fw-bold"><?= $item['quantity'] ?></span></td>
                                <td class="text-end text-slate-600"><?= format_price($item['price']) ?></td>
                                <td class="text-end pe-3 fw-bold text-slate-900"><?= format_price($item['price'] * $item['quantity']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="border-top">
                            <tr>
                                <td colspan="4" class="text-end text-slate-500 small pe-3 py-3 fw-bold uppercase">Grand Total</td>
                                <td class="text-end pe-3 fw-bold text-blue-600 h6 mb-0 py-3"><?= format_price($sale['final_amount']) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Payment History -->
            <div class="bg-white p-4 rounded-4 border border-slate-100 shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h6 class="fw-bold text-slate-700 mb-0 small uppercase tracking-wider">
                        <i class="fa-solid fa-money-bill-wave me-2 text-emerald-400"></i>Payment History
                    </h6>
                    <?php if ($pay_status !== 'paid' && $sale['status'] !== 'cancelled'): ?>
                    <a href="../payments/add.php?<?= $sale['order_id'] ? 'order_id='.$sale['order_id'] : 'sale_id='.$sale_id ?>"
                       class="btn btn-sm btn-outline-success rounded-3 px-3 fw-bold">
                        <i class="fa-solid fa-plus me-1"></i> Add Payment
                    </a>
                    <?php endif; ?>
                </div>

                <?php if (!empty($payments)): ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="bg-slate-50 text-slate-500 small uppercase fw-bold">
                            <tr>
                                <th class="ps-3 py-3">Pay ID</th>
                                <th class="py-3">Method</th>
                                <th class="py-3 text-end">Amount</th>
                                <th class="py-3 text-center">Status</th>
                                <th class="py-3 text-end pe-3">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                            <tr>
                                <td class="ps-3 fw-bold small text-slate-700">
                                    <a href="../payments/view.php?id=<?= $p['id'] ?>" class="text-blue-600 text-decoration-none">
                                        #PAY-<?= str_pad($p['id'],5,'0',STR_PAD_LEFT) ?>
                                    </a>
                                </td>
                                <td><span class="badge bg-slate-100 text-slate-600 fw-semibold px-2 uppercase small"><?= $p['payment_method'] ?></span></td>
                                <td class="text-end fw-bold text-emerald-600"><?= format_price($p['amount']) ?></td>
                                <td class="text-center">
                                    <span class="sbadge sb-<?= $p['status'] ?>">
                                        <i class="fa-solid <?= $p['status']=='paid'?'fa-check':($p['status']=='partial'?'fa-minus':'fa-clock') ?>"></i>
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-3 small text-slate-400"><?= date('d M Y, h:i A', strtotime($p['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="border-top">
                            <tr>
                                <td colspan="2" class="ps-3 fw-bold text-slate-500 small uppercase py-3">Total Collected</td>
                                <td class="text-end fw-bold text-emerald-600 py-3"><?= format_price($total_paid) ?></td>
                                <td colspan="2" class="text-end pe-3 py-3 fw-bold <?= $balance > 0.01 ? 'text-rose-600' : 'text-slate-400' ?>">
                                    <?= $balance > 0.01 ? 'Balance: ' . format_price($balance) : '✓ Fully Paid' ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fa-solid fa-hand-holding-dollar fa-2x text-slate-200 mb-2"></i>
                    <p class="text-slate-400 small mb-3">No payments recorded yet.</p>
                    <a href="../payments/add.php?<?= $sale['order_id'] ? 'order_id='.$sale['order_id'] : 'sale_id='.$sale_id ?>"
                       class="btn btn-sm btn-outline-primary rounded-3 px-3 fw-bold">Record First Payment</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
