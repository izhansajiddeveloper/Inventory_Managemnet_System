<?php

/**
 * View Payment Details
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

function set_flash_message($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
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

$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$payment_id) {
    set_flash_message('error', "No payment ID provided.");
    header("Location: " . BASE_URL . "admin/payments/index.php");
    exit();
}

// Unified query: LEFT JOIN both orders and sales; resolve fields with COALESCE
$payment_query = "
    SELECT p.*,
           -- Order fields
           o.id               AS order_ref_id,
           o.total_amount     AS o_total,
           o.discount         AS o_discount,
           o.delivery_charges AS o_delivery,
           o.final_amount     AS o_final,
           o.status           AS order_status,
           o.created_by       AS o_created_by,
           o.customer_id      AS o_customer_id,
           -- Sale fields
           s.id               AS sale_ref_id,
           s.total_amount     AS s_total,
           s.discount         AS s_discount,
           s.final_amount     AS s_final,
           s.status           AS sale_status,
           s.created_by       AS s_created_by,
           s.customer_id      AS s_customer_id,
           -- Resolved customer (order customer preferred, then sale customer)
           COALESCE(oc.name,    sc.name)    AS customer_name,
           COALESCE(oc.email,   sc.email)   AS customer_email,
           COALESCE(oc.phone,   sc.phone)   AS customer_phone,
           COALESCE(oc.address, sc.address) AS customer_address,
           -- Resolved amounts
           COALESCE(o.total_amount,     s.total_amount)     AS total_amount,
           COALESCE(o.discount,         s.discount)         AS discount,
           COALESCE(o.delivery_charges, 0)                  AS delivery_charges,
           COALESCE(o.final_amount,     s.final_amount)     AS final_amount,
           -- Cashier
           COALESCE(ou.name, su.name) AS cashier_name
    FROM payments p
    LEFT JOIN orders    o  ON p.order_id = o.id
    LEFT JOIN sales     s  ON p.sale_id  = s.id
    LEFT JOIN users oc ON o.customer_id = oc.id
    LEFT JOIN users sc ON s.customer_id = sc.id
    LEFT JOIN users     ou ON o.created_by  = ou.id
    LEFT JOIN users     su ON s.created_by  = su.id
    WHERE p.id = $payment_id";

$payment_result = mysqli_query($conn, $payment_query);

if (!$payment_result || mysqli_num_rows($payment_result) == 0) {
    set_flash_message('error', "Payment record not found.");
    header("Location: " . BASE_URL . "admin/payments/index.php");
    exit();
}

$payment = mysqli_fetch_assoc($payment_result);

// Related payments (same order OR same sale)
$history = [];
if ($payment['order_ref_id']) {
    $history_query = "SELECT * FROM payments WHERE order_id = {$payment['order_ref_id']} AND id != $payment_id ORDER BY id DESC";
    $history_result = mysqli_query($conn, $history_query);
    if ($history_result) {
        while ($row = mysqli_fetch_assoc($history_result)) {
            $history[] = $row;
        }
    }
} elseif ($payment['sale_ref_id']) {
    $history_query = "SELECT * FROM payments WHERE sale_id = {$payment['sale_ref_id']} AND id != $payment_id ORDER BY id DESC";
    $history_result = mysqli_query($conn, $history_query);
    if ($history_result) {
        while ($row = mysqli_fetch_assoc($history_result)) {
            $history[] = $row;
        }
    }
}

$page_title = "Payment Details #PAY-" . str_pad($payment['id'], 5, '0', STR_PAD_LEFT);
$page_icon = "fa-receipt";

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="px-3 py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-blue-50 text-blue-600 rounded-4 border border-blue-100">
                <i class="fa-solid <?= $page_icon ?> fa-xl"></i>
            </div>
            <div>
                <h1 class="h4 fw-bold text-slate-900 mb-0"><?= $page_title ?></h1>
                <p class="text-slate-500 small mb-0">
                    <?php if ($payment['order_ref_id']): ?>
                        Linked to Order #<?= str_pad($payment['order_ref_id'], 5, '0', STR_PAD_LEFT) ?>
                    <?php elseif ($payment['sale_ref_id']): ?>
                        Linked to Sale #<?= str_pad($payment['sale_ref_id'], 5, '0', STR_PAD_LEFT) ?>
                    <?php else: ?>
                        Standalone payment record
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-light rounded-3 px-3 py-2 border fw-bold text-slate-700" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Print Receipt
            </button>
            <a href="index.php" class="btn btn-dark rounded-3 px-3 py-2 fw-bold">Back to History</a>
        </div>
    </div>

    <?php display_flash_message(); ?>

    <div class="row g-4">
        <!-- Payment Info Card -->
        <div class="col-lg-4">
            <div class="bg-white p-4 rounded-4 border border-slate-100 shadow-sm mb-4">
                <h5 class="fw-bold text-slate-800 mb-4 h6 text-uppercase tracking-wider">Transaction Summary</h5>
                <div class="text-center py-3 bg-light rounded-4 mb-4">
                    <div class="text-slate-400 small fw-bold uppercase">Amount Collected</div>
                    <div class="h3 fw-bold text-blue-600 mb-0"><?= format_price($payment['amount']) ?></div>
                </div>

                <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                    <span class="text-slate-500 small fw-bold">PAYMENT ID</span>
                    <span class="fw-bold text-slate-800">#PAY-<?= str_pad($payment['id'], 5, '0', STR_PAD_LEFT) ?></span>
                </div>
                <?php if ($payment['order_ref_id']): ?>
                    <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                        <span class="text-slate-500 small fw-bold">ORDER ID</span>
                        <span class="fw-bold text-blue-600"><a href="../orders/view.php?id=<?= $payment['order_ref_id'] ?>" class="text-decoration-none">#ORD-<?= str_pad($payment['order_ref_id'], 5, '0', STR_PAD_LEFT) ?></a></span>
                    </div>
                <?php endif; ?>
                <?php if ($payment['sale_ref_id']): ?>
                    <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                        <span class="text-slate-500 small fw-bold">SALE ID</span>
                        <span class="fw-bold text-emerald-600"><a href="../sales/view.php?id=<?= $payment['sale_ref_id'] ?>" class="text-decoration-none">#SALE-<?= str_pad($payment['sale_ref_id'], 5, '0', STR_PAD_LEFT) ?></a></span>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                    <span class="text-slate-500 small fw-bold">PAYMENT METHOD</span>
                    <span class="fw-bold text-slate-800"><?= strtoupper($payment['payment_method']) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                    <span class="text-slate-500 small fw-bold">STATUS</span>
                    <span class="status-badge badge-<?= $payment['status'] ?>"><?= ucfirst($payment['status']) ?></span>
                </div>

                <?php if ($payment['transaction_id']): ?>
                    <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                        <span class="text-slate-500 small fw-bold">TRANSACTION ID</span>
                        <span class="fw-bold text-slate-800"><?= htmlspecialchars($payment['transaction_id']) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($payment['payment_proof']): ?>
                    <div class="mb-3">
                        <span class="text-slate-500 small fw-bold d-block mb-2">PAYMENT PROOF</span>
                        <a href="<?= BASE_URL . $payment['payment_proof'] ?>" target="_blank" class="d-block border rounded-3 overflow-hidden hover-opacity">
                            <img src="<?= BASE_URL . $payment['payment_proof'] ?>" class="img-fluid" alt="Proof of Payment">
                        </a>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between">
                    <span class="text-slate-500 small fw-bold">DATE & TIME</span>
                    <span class="text-slate-800 small fw-bold"><?= date('d M, Y h:i A', strtotime($payment['created_at'])) ?></span>
                </div>
            </div>

            <!-- Customer Details -->
            <div class="bg-white p-4 rounded-4 border border-slate-100 shadow-sm">
                <h5 class="fw-bold text-slate-800 mb-4 h6 text-uppercase tracking-wider">Customer Details</h5>
                <div class="fw-bold text-slate-900 mb-1"><?= htmlspecialchars($payment['customer_name'] ?? 'Walk-in Customer') ?></div>
                <div class="small text-slate-500 mb-3"><?= htmlspecialchars($payment['customer_phone'] ?? 'No Phone') ?></div>

                <div class="pt-3 border-top">
                    <div class="text-slate-400 small fw-bold mb-1">ADDRESS</div>
                    <div class="small text-slate-700"><?= nl2br(htmlspecialchars($payment['customer_address'] ?? 'N/A')) ?></div>
                </div>
            </div>
        </div>

        <!-- Order Breakdown Card -->
        <div class="col-lg-8">
            <div class="bg-white p-4 rounded-4 border border-slate-100 shadow-sm mb-4">
                <h5 class="fw-bold text-slate-800 mb-4 h6 text-uppercase tracking-wider">Order Overview</h5>
                <div class="table-responsive">
                    <table class="table table-borderless align-middle m-0">
                        <tbody>
                            <tr>
                                <td class="text-slate-500">Order Subtotal</td>
                                <td class="text-end fw-bold"><?= format_price($payment['total_amount']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-rose-500">Total Discount</td>
                                <td class="text-end fw-bold text-rose-500">-<?= format_price($payment['discount']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-indigo-600">Delivery Charges</td>
                                <td class="text-end fw-bold text-indigo-600">+<?= format_price($payment['delivery_charges']) ?></td>
                            </tr>
                            <tr class="border-top border-slate-100">
                                <td class="h6 fw-bold text-slate-900 pt-3">Final Bill Amount</td>
                                <td class="text-end h6 fw-bold text-slate-900 pt-3"><?= format_price($payment['final_amount']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payment History for this order -->
            <?php if (!empty($history)): ?>
                <div class="bg-white p-4 rounded-4 border border-slate-100 shadow-sm">
                    <h5 class="fw-bold text-slate-800 mb-4 h6 text-uppercase tracking-wider">Related Payments</h5>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="bg-slate-50 text-slate-500 small uppercase fw-bold">
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Method</th>
                                    <th class="text-end">Amount Paid</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $prev): ?>
                                    <tr>
                                        <td class="small fw-bold"><a href="view.php?id=<?= $prev['id'] ?>" class="text-decoration-none text-slate-800">#PAY-<?= str_pad($prev['id'], 5, '0', STR_PAD_LEFT) ?></a></td>
                                        <td><span class="small uppercase fw-bold text-slate-500"><?= $prev['payment_method'] ?></span></td>
                                        <td class="text-end fw-bold text-slate-800"><?= format_price($prev['amount']) ?></td>
                                        <td class="text-center"><span class="status-badge badge-<?= $prev['status'] ?>"><?= ucfirst($prev['status']) ?></span></td>
                                        <td class="text-end small text-slate-500"><?= date('d M, Y', strtotime($prev['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .status-badge {
        padding: 0.4rem 0.8rem;
        border-radius: 8px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        display: inline-flex;
        align-items: center;
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

    @media print {

        .includes-sidebar,
        .includes-navbar,
        .btn,
        .d-flex.gap-2 {
            display: none !important;
        }

        .px-3 {
            padding: 0 !important;
        }

        body {
            background: white !important;
        }

        .col-lg-4,
        .col-lg-8 {
            width: 100% !important;
        }
    }
</style>

<?php include '../../includes/footer.php'; ?>