<?php
/**
 * Add New Payment
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';

// Access Control
authorize([ROLE_ADMIN, ROLE_STAFF]);

$page_title = "Record New Payment";
$page_icon = "fa-hand-holding-dollar";
$page_description = "Add payment for pending or partially paid orders";

$order_id_param = isset($_GET['order_id']) ? 'ORD-' . (int)$_GET['order_id'] : (isset($_GET['sale_id']) ? 'SALE-' . (int)$_GET['sale_id'] : '');

// Fetch Pending/Partial orders
try {
    $transactions_query = "
        SELECT CONCAT('ORD-', o.id) as unique_id, o.id as raw_id, 'Order' as type, o.final_amount, c.name as customer_name,
               COALESCE((SELECT SUM(amount) FROM payments WHERE order_id = o.id), 0) as total_paid
        FROM orders o
        LEFT JOIN users c ON o.customer_id = c.id
        WHERE o.status != 'cancelled'
        HAVING total_paid < o.final_amount
        ORDER BY o.id DESC";
    
    $transactions = $pdo->query($transactions_query)->fetchAll();
} catch (PDOException $e) {
    die("Error fetching orders: " . $e->getMessage());
}

// Handle Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_uid     = sanitize($_POST['target_id']); // e.g. ORD-1
    $payment_method = sanitize($_POST['payment_method']);
    $amount         = (float)$_POST['amount'];
    $note           = sanitize($_POST['note'] ?? '');

    $parts  = explode('-', $target_uid);
    $id     = (int)$parts[1];

    $order_id = $id;
    $sale_id  = null;

    $errors = [];

    // Fetch full details to validate remaining amount
    $stmt = $pdo->prepare("
        SELECT o.final_amount,
               COALESCE((SELECT SUM(amount) FROM payments WHERE order_id = o.id), 0) AS total_paid,
               (SELECT id FROM sales WHERE order_id = o.id LIMIT 1) AS linked_sale_id
        FROM orders o WHERE o.id = ?");
    
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        $errors[] = "Invalid selection.";
    } else {
        $remaining = $row['final_amount'] - $row['total_paid'];
        if ($amount <= 0)              $errors[] = "Amount must be greater than 0.";
        if ($amount > $remaining + 0.01) $errors[] = "Amount exceeds the remaining balance of " . format_price($remaining);
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Handle Payment Proof Upload
            $proof_path = null;
            if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
                $file_ext  = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
                $file_name = "PROOF_" . time() . "_" . $id . "." . $file_ext;
                $target_dir = __DIR__ . '/../../uploads/payments/';
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $target_dir . $file_name)) {
                    $proof_path = 'uploads/payments/' . $file_name;
                }
            }

            $transaction_id = !empty($_POST['transaction_id']) ? sanitize($_POST['transaction_id']) : null;

            // Determine statuses
            $new_total_paid = $row['total_paid'] + $amount;
            $is_fully_paid  = ($new_total_paid >= $row['final_amount'] - 0.01);
            $payment_status = $is_fully_paid ? 'paid' : 'partial';

            // Resolve the linked sale_id from sales table
            $linked_sale_id = !empty($row['linked_sale_id']) ? (int)$row['linked_sale_id'] : null;

            // Insert Payment
            $pdo->prepare("
                INSERT INTO payments (order_id, sale_id, payment_method, transaction_id, payment_proof, amount, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$order_id, $linked_sale_id, $payment_method, $transaction_id, $proof_path, $amount, $payment_status]);
            $payment_id = $pdo->lastInsertId();

            if ($is_fully_paid) {
                // Trigger Profit Calculation
                require_once __DIR__ . '/../profit/calculate.php';
                calculate_order_profit($pdo, $order_id);

                // 1. Update order status
                $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ?")->execute([$order_id]);

                // 2. If no sale exists yet, CREATE it
                if (!$linked_sale_id) {
                    $o_stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
                    $o_stmt->execute([$order_id]);
                    $order = $o_stmt->fetch();
                    if ($order) {
                        $pdo->prepare("
                            INSERT INTO sales (order_id, customer_id, created_by, total_amount, discount, final_amount, status)
                            VALUES (?, ?, ?, ?, ?, ?, 'completed')
                        ")->execute([$order_id, $order['customer_id'], $order['created_by'], $order['total_amount'], $order['discount'], $order['final_amount']]);
                        $linked_sale_id = $pdo->lastInsertId();

                        // Copy items
                        $si_stmt = $pdo->prepare("SELECT product_id, quantity, price FROM order_items WHERE order_id = ?");
                        $si_stmt->execute([$order_id]);
                        $o_items = $si_stmt->fetchAll();
                        foreach ($o_items as $oi) {
                            $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)")
                                ->execute([$linked_sale_id, $oi['product_id'], $oi['quantity'], $oi['price']]);
                        }
                        
                        // Link THIS payment to the new sale
                        $pdo->prepare("UPDATE payments SET sale_id = ? WHERE id = ?")->execute([$linked_sale_id, $payment_id]);
                    }
                }

                // 3. Mark sale as completed
                if ($linked_sale_id) {
                    $pdo->prepare("UPDATE sales SET status = 'completed' WHERE id = ?")->execute([$linked_sale_id]);
                }
            }
            $pdo->commit();

            set_flash_message('success', "Payment of " . format_price($amount) . " recorded. Status: " . ucfirst($payment_status) . ".");
            if ($linked_sale_id) {
                redirect('admin/sales/view.php?id=' . $linked_sale_id);
            } else {
                redirect('admin/payments/index.php');
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Transaction failed: " . $e->getMessage();
        }
    }
}

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
                <p class="text-slate-500 small mb-0"><?= $page_description ?></p>
            </div>
        </div>
        <a href="index.php" class="btn btn-light rounded-3 px-3 py-2 border fw-bold text-slate-700">Back to History</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger rounded-4 shadow-sm mb-4">
            <ul class="mb-0">
                <?php foreach ($errors as $error) echo "<li>$error</li>"; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="bg-white p-4 rounded-4 border border-slate-100 shadow-sm">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-slate-600">Select Order</label>
                        <select name="target_id" id="orderSelect" class="form-select rounded-3 py-2 border-slate-200" required onchange="updateRemaining()">
                            <option value="">-- Choose Pending Order --</option>
                            <?php foreach ($transactions as $t): ?>
                                <option value="<?= $t['unique_id'] ?>" 
                                        data-final="<?= $t['final_amount'] ?>" 
                                        data-paid="<?= $t['total_paid'] ?>"
                                        <?= $order_id_param == $t['unique_id'] ? 'selected' : '' ?>>
                                    Order #<?= str_pad($t['raw_id'], 5, '0', STR_PAD_LEFT) ?> - <?= htmlspecialchars($t['customer_name'] ?? 'Walk-in') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="orderSummary" class="bg-slate-50 p-3 rounded-4 border border-slate-100 mb-4 d-none">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="text-slate-400 small fw-bold uppercase">Total Order Amount</div>
                                <div id="summaryFinal" class="h6 fw-bold text-slate-800 mb-0">Rs. 0.00</div>
                            </div>
                            <div class="col-6">
                                <div class="text-slate-400 small fw-bold uppercase">Remaining Balance</div>
                                <div id="summaryRemaining" class="h6 fw-bold text-rose-600 mb-0">Rs. 0.00</div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-slate-600">Payment Method</label>
                            <select name="payment_method" id="paymentMethod" class="form-select rounded-3 py-2 border-slate-200" required onchange="toggleOnlineFields()">
                                <option value="cash">Cash Payment</option>
                                <option value="online">Online Transfer</option>
                                <option value="bank">Bank Deposit</option>
                                <option value="card">Card Payment</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-slate-600">Amount to Pay</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-slate-200">Rs.</span>
                                <input type="number" name="amount" id="payAmount" class="form-control border-slate-200 py-2 rounded-end-3" step="0.01" min="0.01" required>
                            </div>
                        </div>
                    </div>

                    <!-- Online Specific Fields -->
                    <div id="onlineFields" class="mt-4 d-none">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-slate-600">Transaction ID / Reference</label>
                                <input type="text" name="transaction_id" class="form-control rounded-3 py-2 border-slate-200" placeholder="e.g. TRX-908123">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-slate-600">Upload Receipt / Proof</label>
                                <input type="file" name="payment_proof" class="form-control rounded-3 py-2 border-slate-200" accept="image/*">
                            </div>
                        </div>
                        <div class="form-text small mt-2">Required for online or bank transfers for verification.</div>
                    </div>

                    <div class="mt-4 mb-4">
                        <label class="form-label small fw-bold text-slate-600">Note (Optional)</label>
                        <textarea name="note" class="form-control rounded-3 border-slate-200" rows="2" placeholder="Reference number, etc..."></textarea>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="btn btn-dark w-100 py-3 rounded-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-check-circle me-2"></i> Confirm Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function updateRemaining() {
        const select = document.getElementById('orderSelect');
        const summary = document.getElementById('orderSummary');
        const payAmount = document.getElementById('payAmount');
        const selectedOption = select.options[select.selectedIndex];
        
        if (selectedOption.value) {
            const final = parseFloat(selectedOption.getAttribute('data-final'));
            const paid = parseFloat(selectedOption.getAttribute('data-paid'));
            const remaining = final - paid;
            
            document.getElementById('summaryFinal').innerText = 'Rs. ' + final.toLocaleString(undefined, {minimumFractionDigits: 2});
            document.getElementById('summaryRemaining').innerText = 'Rs. ' + remaining.toLocaleString(undefined, {minimumFractionDigits: 2});
            
            payAmount.value = remaining.toFixed(2);
            payAmount.max = remaining.toFixed(2);
            
            summary.classList.remove('d-none');
        } else {
            summary.classList.add('d-none');
            payAmount.value = '';
        }
    }

    function toggleOnlineFields() {
        const method = document.getElementById('paymentMethod').value;
        const onlineFields = document.getElementById('onlineFields');
        if(method !== 'cash') {
            onlineFields.classList.remove('d-none');
        } else {
            onlineFields.classList.add('d-none');
        }
    }

    // Run once on load for pre-selected order
    document.addEventListener('DOMContentLoaded', function() {
        updateRemaining();
        toggleOnlineFields();
    });
</script>

<style>
    .uppercase { text-transform: uppercase; }
</style>

<?php include '../../includes/footer.php'; ?>
