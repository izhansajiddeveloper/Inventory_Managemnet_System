<?php
/**
 * Printable Sale Receipt
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';

authorize([ROLE_ADMIN, ROLE_STAFF]);

$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$sale_id) { die("Invalid sale ID."); }

try {
    // 1. Fetch Sale details
    $stmt = $pdo->prepare("
        SELECT s.*, 
               c.name AS customer_name, c.phone AS customer_phone,
               u.name AS staff_name,
               o.order_type, o.delivery_charges
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.created_by = u.id
        LEFT JOIN orders o ON s.order_id = o.id
        WHERE s.id = ?
    ");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch();
    if (!$sale) { die("Sale record not found."); }

    // 2. Fetch Items
    $items_stmt = $pdo->prepare("
        SELECT si.*, p.name AS product_name, p.sku
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = ?
    ");
    $items_stmt->execute([$sale_id]);
    $items = $items_stmt->fetchAll();

    // 3. Fetch Payments (Unified)
    $pay_q = "SELECT p.*, 'link' AS source FROM payments p WHERE p.sale_id = ? OR (p.order_id IS NOT NULL AND p.order_id = ?)";
    $ps = $pdo->prepare($pay_q);
    $ps->execute([$sale_id, $sale['order_id'] ?? 0]);
    $payments = $ps->fetchAll();
    $total_paid = array_sum(array_column($payments, 'amount'));
    $balance = $sale['final_amount'] - $total_paid;

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Receipt #SALE-<?= str_pad($sale_id, 5, '0', STR_PAD_LEFT) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&family=Inter:wght@400;500;700;800&display=swap');
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter', sans-serif; background:#f1f5f9; display:flex; justify-content:center; padding:30px 0; }
        .receipt-card { background:white; width:380px; min-height:500px; border-radius:14px; overflow:hidden; box-shadow:0 20px 60px -10px rgba(0,0,0,0.2); }
        .rec-head { background:linear-gradient(135deg, #1e293b, #0f172a); color:white; padding:30px 20px; text-align:center; }
        .rec-head h1 { font-size:1.1rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px; }
        .rec-head p { font-size:.7rem; opacity:.7; text-transform:uppercase; letter-spacing:.5px; }
        .rec-body { padding:25px; }
        .meta-box { background:#f8fafc; border-radius:10px; padding:12px; margin-bottom:20px; display:grid; grid-template-columns:1fr 1fr; gap:8px 15px; }
        .meta-item { display:flex; flex-direction:column; }
        .meta-label { font-size:.65rem; color:#94a3b8; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
        .meta-val { font-size:.78rem; font-weight:700; color:#1e293b; }
        .table { width:100%; border-collapse:collapse; margin-bottom:15px; }
        .table th { border-bottom:1px dashed #e2e8f0; padding:8px 0; font-size:.7rem; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; text-align:left; }
        .table td { padding:10px 0; font-size:.8rem; border-bottom:1px dotted #f1f5f9; }
        .qty-circle { background:#e2e8f0; color:#475569; width:22px; height:22px; border-radius:11px; display:inline-flex; align-items:center; justify-content:center; font-size:.65rem; font-weight:800; margin-right:5px; }
        .totals-box { border-top:2px solid #f1f5f9; padding-top:10px; }
        .total-row { display:flex; justify-content:space-between; font-size:.85rem; padding:4px 0; }
        .total-row.final { font-size:1.05rem; font-weight:800; color:#2563eb; border-top:1px dashed #e2e8f0; margin-top:8px; padding-top:12px; }
        .pay-box { margin-top:20px; border-top:1px dashed #e2e8f0; padding-top:15px; }
        .pay-item { background:#f0fdf4; border-radius:8px; padding:8px 12px; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center; }
        .pay-item .method { font-size:.7rem; font-weight:800; text-transform:uppercase; color:#166534; }
        .pay-item .amt { font-size:.85rem; font-weight:800; color:#15803d; }
        .bal-box { background:#fef2f2; border-radius:8px; padding:8px 12px; display:flex; justify-content:space-between; align-items:center; margin-top:10px; }
        .bal-box .lbl { font-size:.7rem; font-weight:800; text-transform:uppercase; color:#991b1b; }
        .bal-box .amt { font-size:.85rem; font-weight:800; color:#b91c1c; }
        .rec-footer { padding:20px; text-align:center; border-top:1px dashed #f1f5f9; }
        .rec-footer p { font-size:.72rem; color:#94a3b8; font-weight:500; line-height:1.4; }
        .no-print { text-align:center; margin-bottom:20px; }
        .print-btn { background:#1e293b; color:white; border:none; padding:10px 25px; border-radius:30px; font-weight:700; font-size:.85rem; cursor:pointer; box-shadow:0 10px 20px -5px rgba(0,0,0,0.3); }
        .print-btn:hover { background:#334155; }
        @media print {
            body { background:white; padding:0; }
            .receipt-card { box-shadow:none; width:100%; border-radius:0; min-height:auto; border:none; }
            .no-print { display:none; }
        }
    </style>
</head>
<body>

    <div>
        <div class="no-print">
            <button class="print-btn" onclick="window.print()">Print Receipt</button>
            <p style="text-align:center; margin-top:10px; font-size:.75rem; color:#64748b; font-weight:700; text-transform:uppercase; cursor:pointer" onclick="window.close()">Close Window</p>
        </div>

        <div class="receipt-card">
            <div class="rec-head">
                <h1><?= APP_NAME ?></h1>
                <p>Sale Receipt & Receipt Number</p>
                <div style="margin-top:12px; font-weight:800; font-family:'Courier Prime', monospace; font-size:1.1rem; letter-spacing:2px">#SALE-<?= str_pad($sale_id, 5, '0', STR_PAD_LEFT) ?></div>
            </div>

            <div class="rec-body">
                <div class="meta-box">
                    <div class="meta-item"><span class="meta-label">Date</span><span class="meta-val"><?= date('d-M-Y', strtotime($sale['created_at'])) ?></span></div>
                    <div class="meta-item" style="text-align:right"><span class="meta-label">Time</span><span class="meta-val"><?= date('h:i A', strtotime($sale['created_at'])) ?></span></div>
                    <div class="meta-item"><span class="meta-label">Customer</span><span class="meta-val"><?= htmlspecialchars(explode(' ', $sale['customer_name'] ?? 'Walk-in')[0]) ?></span></div>
                    <div class="meta-item" style="text-align:right"><span class="meta-label">Staff</span><span class="meta-val"><?= htmlspecialchars(explode(' ', $sale['staff_name'] ?? 'System')[0]) ?></span></div>
                </div>

                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:60%">Item Description</th>
                            <th style="width:10%; text-align:center">Qty</th>
                            <th style="width:30%; text-align:right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $item): ?>
                        <tr>
                            <td>
                                <div style="font-weight:700; color:#1e293b"><?= htmlspecialchars($item['product_name']) ?></div>
                                <div style="font-size:.6rem; color:#94a3b8; font-weight:700"><?= htmlspecialchars($item['sku'] ?? 'N/A') ?></div>
                            </td>
                            <td style="text-align:center"><span class="qty-circle"><?= $item['quantity'] ?></span></td>
                            <td style="text-align:right; font-weight:700; color:#1e293b"><?= format_price($item['price'] * $item['quantity']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="totals-box">
                    <div class="total-row"><span style="color:#64748b">Subtotal</span><span style="font-weight:700"><?= format_price($sale['total_amount']) ?></span></div>
                    <?php if($sale['discount'] > 0): ?>
                        <div class="total-row" style="color:#b91c1c"><span>Discount (-)</span><span>- <?= format_price($sale['discount']) ?></span></div>
                    <?php endif; ?>
                    <?php if($sale['delivery_charges'] > 0): ?>
                        <div class="total-row" style="color:#1e40af"><span>Delivery (+)</span><span>+ <?= format_price($sale['delivery_charges']) ?></span></div>
                    <?php endif; ?>
                    <div class="total-row final"><span>Total Bill Amount</span><span><?= format_price($sale['final_amount']) ?></span></div>
                </div>

                <div class="pay-box">
                    <div style="font-size:.65rem; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; margin-bottom:10px">Payment History</div>
                    <?php foreach($payments as $p): ?>
                        <div class="pay-item">
                            <span class="method"><?= $p['payment_method'] ?></span>
                            <span style="font-size:.6rem; color:#166534; font-weight:700"><?= date('d/m h:i A', strtotime($p['created_at'])) ?></span>
                            <span class="amt"><?= format_price($p['amount']) ?></span>
                        </div>
                    <?php endforeach; ?>

                    <?php if($balance > 0.01): ?>
                        <div class="bal-box">
                            <span class="lbl">Pending Balance</span>
                            <span class="amt"><?= format_price($balance) ?></span>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; padding-top:10px; color:#15803d; font-size:.85rem; font-weight:800">✓ FULLY PAID</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="rec-footer">
                <p>Terms: Sold items are non-refundable.<br>Thank you for shopping with us!</p>
                <div style="margin-top:10px; font-weight:800; color:#cbd5e1; font-size:.65rem; letter-spacing:3px">WWW.RETAIL.COM</div>
            </div>
        </div>
    </div>

</body>
</html>
