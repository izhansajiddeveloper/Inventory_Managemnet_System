<?php
/**
 * Create New Order
 * Auto-creates a linked Sale record on every order.
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';

authorize([ROLE_ADMIN, ROLE_STAFF]);

$page_title       = "Create New Order";
$page_icon        = "fa-cart-plus";
$page_description = "Generate a new sales order — a linked sale record is created automatically";

// Fetch products with stock
try {
    $products = $pdo->query("
        SELECT p.id, p.name, p.sku, p.selling_price,
               COALESCE(ps.quantity, 0) AS stock
        FROM products p
        LEFT JOIN product_stock ps ON p.id = ps.product_id
        WHERE p.status = 'active' AND COALESCE(ps.quantity, 0) > 0
        ORDER BY p.name ASC
    ")->fetchAll();

    $customers = $pdo->query("SELECT id, name, phone FROM customers ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

$errors = [];

// ───────────────────────────────────────────────
// Handle Form Submission
// ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id      = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $order_type       = sanitize($_POST['order_type']);
    $discount         = (float)($_POST['discount'] ?? 0);
    $delivery_charges = ($order_type === 'online') ? (float)($_POST['delivery_charges'] ?? 0) : 0;
    $payment_method   = sanitize($_POST['payment_method']);
    $amount_paid      = (float)($_POST['amount_paid'] ?? 0);  // Partial or full

    $product_ids = $_POST['product_id'] ?? [];
    $quantities  = $_POST['quantity']   ?? [];
    $prices      = $_POST['price']      ?? [];

    // 1. Basic Validation
    if (empty($product_ids)) $errors[] = "Please add at least one product.";
    if ($amount_paid < 0)    $errors[] = "Payment amount cannot be negative.";

    // 2. Stock Validation + build items
    $items_to_process = [];
    $total_amount     = 0;

    foreach ($product_ids as $index => $pid) {
        $pid   = (int)$pid;
        $qty   = (int)($quantities[$index] ?? 0);
        $price = (float)($prices[$index]   ?? 0);

        if ($qty <= 0) { $errors[] = "Quantity must be > 0 for all items."; continue; }

        $st = $pdo->prepare("SELECT quantity FROM product_stock WHERE product_id = ?");
        $st->execute([$pid]);
        $avail = (int)$st->fetchColumn();
        if ($qty > $avail) {
            $sn = $pdo->prepare("SELECT name FROM products WHERE id = ?");
            $sn->execute([$pid]);
            $errors[] = "Insufficient stock for '" . $sn->fetchColumn() . "'. Available: $avail";
        }

        $item_total    = $price * $qty;
        $total_amount += $item_total;
        $items_to_process[] = [
            'product_id' => $pid,
            'quantity'   => $qty,
            'price'      => $price,
            'total'      => $item_total,
        ];
    }

    $final_amount = $total_amount - $discount + $delivery_charges;
    if ($final_amount < 0)                        $errors[] = "Discount cannot exceed the total amount.";
    if ($amount_paid > $final_amount + 0.01)      $errors[] = "Amount paid cannot exceed the order total.";

    // ───────────────────────────────────────────
    // 3. Execute (atomic transaction)
    // ───────────────────────────────────────────
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $created_by = $_SESSION[SESSION_USER_ID] ?? 1;

            // ── A. Determine statuses from payment amount
            $is_fully_paid  = ($amount_paid >= $final_amount - 0.01);
            $has_payment    = ($amount_paid > 0);
            $order_status   = $is_fully_paid ? 'completed' : 'pending';
            $sale_status    = $order_status;
            $payment_status = $is_fully_paid ? 'paid' : ($has_payment ? 'partial' : 'unpaid');

            // ── B. Insert Order
            $pdo->prepare("
                INSERT INTO orders (customer_id, created_by, order_type, total_amount, discount, delivery_charges, final_amount, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$customer_id, $created_by, $order_type, $total_amount, $discount, $delivery_charges, $final_amount, $order_status]);
            $order_id = $pdo->lastInsertId();

            // ── C. Insert Order Items + deduct stock + log transactions
            foreach ($items_to_process as $item) {
                // Order item
                $pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, quantity, price, total)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$order_id, $item['product_id'], $item['quantity'], $item['price'], $item['total']]);

                // Deduct stock immediately (regardless of payment status)
                $pdo->prepare("
                    UPDATE product_stock SET quantity = quantity - ? WHERE product_id = ?
                ")->execute([$item['quantity'], $item['product_id']]);

                // Stock transaction log
                $pdo->prepare("
                    INSERT INTO product_transactions (product_id, quantity, type, reference_type, reference_id, created_by, note)
                    VALUES (?, ?, 'OUT', 'order', ?, ?, ?)
                ")->execute([
                    $item['product_id'],
                    $item['quantity'],
                    $order_id,
                    $created_by,
                    "Order #$order_id — " . ($order_type === 'shop' ? 'Shop Sale' : 'Online Order'),
                ]);
            }

            // ── D. Auto-create linked Sale record
            $pdo->prepare("
                INSERT INTO sales (order_id, customer_id, created_by, total_amount, discount, final_amount, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$order_id, $customer_id, $created_by, $total_amount, $discount, $final_amount, $sale_status]);
            $sale_id = $pdo->lastInsertId();

            // ── E. Copy order_items → sale_items
            //      (sale_items.total is a STORED GENERATED column, do NOT insert it)
            foreach ($items_to_process as $item) {
                $pdo->prepare("
                    INSERT INTO sale_items (sale_id, product_id, quantity, price)
                    VALUES (?, ?, ?, ?)
                ")->execute([$sale_id, $item['product_id'], $item['quantity'], $item['price']]);
            }

            // ── F. Insert Payment (if any amount provided)
            if ($has_payment) {
                $pdo->prepare("
                    INSERT INTO payments (order_id, sale_id, payment_method, amount, status)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$order_id, $sale_id, $payment_method, $amount_paid, $payment_status]);
            }

            $pdo->commit();

            $msg = "Order #ORD-" . str_pad($order_id,5,'0',STR_PAD_LEFT) . " & Sale #SALE-" . str_pad($sale_id,5,'0',STR_PAD_LEFT) . " created.";
            $msg .= $has_payment ? " Payment: " . ucfirst($payment_status) . "." : " No payment recorded.";
            set_flash_message('success', $msg);

            // POS Flow: Redirect to Sale View for shop orders, Invoice for others
            if ($order_type === 'shop') {
                redirect('admin/sales/view.php?id=' . $sale_id);
            } else {
                redirect('admin/orders/invoice/index.php?order_id=' . $order_id);
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Database Error: " . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
    .uppercase       { text-transform: uppercase; }
    .tracking-wider  { letter-spacing: .05em; }
    .x-small         { font-size: .72rem; }
    .qty-input.is-invalid { border-color: #ef4444; }
</style>

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
        <a href="index.php" class="btn btn-light rounded-3 px-3 py-2 border fw-bold text-slate-700">Back to Orders</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger rounded-4 shadow-sm mb-4">
            <ul class="mb-0"><?php foreach ($errors as $error) echo "<li>$error</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="orderForm">
        <div class="row g-4">

            <!-- Left: Order Info & Products -->
            <div class="col-lg-8">

                <!-- Order Info -->
                <div class="bg-white p-4 rounded-4 border border-slate-100 shadow-sm mb-4">
                    <h5 class="fw-bold text-slate-800 mb-4 h6 text-uppercase tracking-wider">Order Information</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-slate-600">Select Customer</label>
                            <select name="customer_id" class="form-select rounded-3 py-2">
                                <option value="">-- Walk-in / No Customer --</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= $c['phone'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-slate-600">Order Type</label>
                            <select name="order_type" id="orderType" class="form-select rounded-3 py-2" required>
                                <option value="shop">Instore / Shop Sale</option>
                                <option value="online">Online Order</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Product Items -->
                <div class="bg-white p-4 rounded-4 border border-slate-100 shadow-sm">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h5 class="fw-bold text-slate-800 mb-0 h6 text-uppercase tracking-wider">Product Items</h5>
                        <button type="button" class="btn btn-primary btn-sm rounded-3 px-3 fw-bold" onclick="addRow()">
                            <i class="fa-solid fa-plus me-1"></i> Add Item
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle" id="productTable">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 small uppercase fw-bold">
                                    <th style="width:40%">Product</th>
                                    <th style="width:20%">Price</th>
                                    <th style="width:15%">Quantity</th>
                                    <th style="width:20%">Total</th>
                                    <th style="width:5%"></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div id="emptyItems" class="text-center py-4 text-slate-400">
                        <i class="fa-solid fa-box-open fa-2x mb-2"></i>
                        <p class="small mb-0">No items added yet. Click "Add Item" to start.</p>
                    </div>
                </div>
            </div>

            <!-- Right: Summary & Payment -->
            <div class="col-lg-4">
                <div class="bg-white p-4 rounded-4 border border-slate-100 shadow-sm sticky-top" style="top:20px">
                    <h5 class="fw-bold text-slate-800 mb-4 h6 text-uppercase tracking-wider">
                        <i class="fa-solid fa-calculator me-2 text-blue-400"></i>Cart Summary
                    </h5>

                    <div class="d-flex justify-content-between mb-2 text-slate-600 small">
                        <span>Subtotal</span>
                        <span id="displaySubtotal" class="fw-semibold">Rs. 0.00</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-slate-600">Discount Amount</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-slate-200">Rs.</span>
                            <input type="number" name="discount" id="discount" class="form-control border-slate-200" value="0" min="0" step="0.01" oninput="calculateSummary()">
                        </div>
                    </div>

                    <div id="deliveryField" class="mb-3" style="display:none">
                        <label class="form-label small fw-bold text-slate-600">Delivery Charges</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-slate-200">Rs.</span>
                            <input type="number" name="delivery_charges" id="delivery" class="form-control border-slate-200" value="0" min="0" step="0.01" oninput="calculateSummary()">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mb-3">
                        <span class="h6 fw-bold text-slate-900 mb-0">Final Total</span>
                        <span id="displayFinal" class="h5 fw-bold text-blue-600 mb-0">Rs. 0.00</span>
                    </div>

                    <hr class="my-3 border-slate-100">

                    <!-- Payment -->
                    <h6 class="fw-bold text-slate-700 mb-3 text-uppercase small tracking-wider">
                        <i class="fa-solid fa-hand-holding-dollar me-1 text-emerald-500"></i> Payment
                    </h6>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-slate-600">Payment Method</label>
                        <select name="payment_method" id="paymentMethod" class="form-select border-slate-200 rounded-3 py-2">
                            <option value="cash">Cash Payment</option>
                            <option value="online">Online Transfer</option>
                            <option value="bank">Bank Deposit</option>
                            <option value="card">Card Payment</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-slate-600">Amount Received</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-slate-200">Rs.</span>
                            <input type="number" name="amount_paid" id="amountPaid" class="form-control border-slate-200" value="0" min="0" step="0.01" oninput="updateChange()">
                        </div>
                        <div class="form-text small text-slate-400">Enter 0 to record as unpaid</div>
                    </div>

                    <!-- Change / balance display -->
                    <div id="changeBox" class="p-3 rounded-3 mb-3 d-none" style="background:#f0fdf4;border:1px solid #bbf7d0">
                        <div class="d-flex justify-content-between small">
                            <span id="changeLabel" class="fw-bold text-slate-600">Change Due</span>
                            <span id="changeAmt" class="fw-bold text-emerald-600">Rs. 0.00</span>
                        </div>
                    </div>

                    <div class="alert alert-info rounded-3 py-2 px-3 small mb-3">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        A <strong>Sale record</strong> is auto-created with this order.
                    </div>

                    <button type="submit" class="btn btn-dark w-100 py-3 rounded-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-check-circle me-2"></i> Confirm Order & Sale
                    </button>
                    <p class="text-center text-slate-400 small mt-2">* Stock is deducted upon confirmation</p>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
const inventory = <?= json_encode($products) ?>;
let rowCount = 0;

function addRow() {
    rowCount++;
    document.getElementById('emptyItems').style.display = 'none';
    const tbody = document.querySelector('#productTable tbody');
    const tr    = document.createElement('tr');
    tr.id       = `row-${rowCount}`;

    let opts = '<option value="">-- Select Product --</option>';
    inventory.forEach(p => {
        opts += `<option value="${p.id}" data-price="${p.selling_price}" data-stock="${p.stock}">${p.name} (Stock: ${p.stock})</option>`;
    });

    tr.innerHTML = `
        <td>
            <select name="product_id[]" class="form-select rounded-3 py-2 border-slate-200 product-select" onchange="updateRow(${rowCount})" required>
                ${opts}
            </select>
        </td>
        <td>
            <input type="number" name="price[]" class="form-control rounded-3 border-slate-200 bg-light price-input" readonly required>
        </td>
        <td>
            <input type="number" name="quantity[]" class="form-control rounded-3 border-slate-200 qty-input" min="1" value="1" oninput="updateRow(${rowCount})" required>
            <div class="stock-alert text-danger x-small fw-bold mt-1" style="display:none;font-size:10px">⚠ Exceeds Stock!</div>
        </td>
        <td>
            <input type="text" class="form-control rounded-3 border-slate-200 bg-light total-display" readonly value="Rs. 0.00">
        </td>
        <td>
            <button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="removeRow(${rowCount})">
                <i class="fa-solid fa-trash"></i>
            </button>
        </td>`;
    tbody.appendChild(tr);
}

function removeRow(id) {
    document.getElementById(`row-${id}`)?.remove();
    if (!document.querySelectorAll('#productTable tbody tr').length)
        document.getElementById('emptyItems').style.display = 'block';
    calculateSummary();
}

function updateRow(id) {
    const row   = document.getElementById(`row-${id}`);
    const sel   = row.querySelector('.product-select');
    const pInp  = row.querySelector('.price-input');
    const qInp  = row.querySelector('.qty-input');
    const tDisp = row.querySelector('.total-display');
    const alert = row.querySelector('.stock-alert');
    const opt   = sel.options[sel.selectedIndex];
    if (opt.value) {
        const price = parseFloat(opt.dataset.price);
        const stock = parseInt(opt.dataset.stock);
        const qty   = parseInt(qInp.value) || 0;
        pInp.value  = price.toFixed(2);
        if (qty > stock) { alert.style.display='block'; qInp.classList.add('is-invalid'); }
        else             { alert.style.display='none';  qInp.classList.remove('is-invalid'); }
        tDisp.value = 'Rs. ' + (price * qty).toLocaleString(undefined, {minimumFractionDigits:2});
    } else {
        pInp.value = ''; tDisp.value = 'Rs. 0.00'; alert.style.display='none';
    }
    calculateSummary();
}

function calculateSummary() {
    let subtotal = 0;
    document.querySelectorAll('.total-display').forEach(d => {
        subtotal += parseFloat(d.value.replace('Rs. ','').replace(/,/g,'')) || 0;
    });
    const disc     = parseFloat(document.getElementById('discount').value)  || 0;
    const delivery = parseFloat(document.getElementById('delivery')?.value) || 0;
    const final    = Math.max(0, subtotal - disc + delivery);

    document.getElementById('displaySubtotal').innerText = 'Rs. ' + subtotal.toLocaleString(undefined,{minimumFractionDigits:2});
    document.getElementById('displayFinal').innerText    = 'Rs. ' + final.toLocaleString(undefined,{minimumFractionDigits:2});
    updateChange();
}

function updateChange() {
    const finalText = document.getElementById('displayFinal').innerText;
    const total  = parseFloat(finalText.replace('Rs. ','').replace(/,/g,'')) || 0;
    const paid   = parseFloat(document.getElementById('amountPaid').value) || 0;
    const box    = document.getElementById('changeBox');
    if (paid > 0) {
        box.classList.remove('d-none');
        const diff = paid - total;
        document.getElementById('changeLabel').textContent = diff >= 0 ? 'Change Due' : 'Balance Remaining';
        document.getElementById('changeAmt').className  = diff >= 0 ? 'fw-bold text-emerald-600' : 'fw-bold text-rose-600';
        document.getElementById('changeAmt').innerText  = 'Rs. ' + Math.abs(diff).toLocaleString(undefined,{minimumFractionDigits:2});
        box.style.background = diff >= 0 ? '#f0fdf4' : '#fef2f2';
        box.style.borderColor= diff >= 0 ? '#bbf7d0' : '#fecaca';
    } else {
        box.classList.add('d-none');
    }
}

// Toggle delivery field
document.getElementById('orderType').addEventListener('change', function() {
    const df = document.getElementById('deliveryField');
    df.style.display = this.value === 'online' ? 'block' : 'none';
    if (this.value !== 'online') {
        document.getElementById('delivery').value = 0;
        calculateSummary();
    }
});

// Submit validation
document.getElementById('orderForm').addEventListener('submit', function(e) {
    if (document.querySelector('.qty-input.is-invalid')) {
        e.preventDefault(); alert('Fix stock quantity errors before submitting.'); return;
    }
    if (!document.querySelectorAll('#productTable tbody tr').length) {
        e.preventDefault(); alert('Please add at least one product.'); return;
    }
});

// Start with one row
addRow();
</script>

<?php include '../../includes/footer.php'; ?>
