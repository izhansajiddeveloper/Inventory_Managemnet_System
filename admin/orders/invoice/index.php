<?php
/**
 * Professional Invoice Template
 */
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../core/session.php';
require_once __DIR__ . '/../../../core/auth.php';
require_once __DIR__ . '/../../../core/functions.php';

authorize([ROLE_ADMIN, ROLE_STAFF]);

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$order_id) {
    die("Error: No order ID provided.");
}

try {
    // Fetch Order
    $order_stmt = $pdo->prepare("SELECT o.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_address 
                                FROM orders o 
                                LEFT JOIN users c ON o.customer_id = c.id 
                                WHERE o.id = ?");
    $order_stmt->execute([$order_id]);
    $order = $order_stmt->fetch();

    if (!$order) {
        die("Error: Order not found.");
    }

    // Fetch Items
    $items_stmt = $pdo->prepare("SELECT oi.*, p.name as product_name, p.sku as product_sku 
                                FROM order_items oi 
                                JOIN products p ON oi.product_id = p.id 
                                WHERE oi.order_id = ?");
    $items_stmt->execute([$order_id]);
    $items = $items_stmt->fetchAll();

    // Fetch Payment
    $payment_stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? LIMIT 1");
    $payment_stmt->execute([$order_id]);
    $payment = $payment_stmt->fetch();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #ORD-<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f1f5f9; font-family: 'Inter', sans-serif; }
        .invoice-container { max-width: 900px; margin: 40px auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.05); }
        .brand-logo { width: 40px; height: 40px; background: #2563eb; color: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .table thead th { background: #f8fafc; color: #64748b; font-size: 11px; text-transform: uppercase; padding: 12px; }
        .total-section { background: #f8fafc; border-radius: 12px; padding: 20px; }
        .badge-status { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .badge-paid { background: #ecfdf5; color: #059669; }
        @media print {
            body { background: white; }
            .invoice-container { box-shadow: none; margin: 0; max-width: 100%; border-radius: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="container no-print mt-4 mb-2 text-center">
    <button class="btn btn-primary px-4 py-2 rounded-3 fw-bold" onclick="window.print()">
        <i class="fa-solid fa-print me-2"></i> Print Invoice
    </button>
    <a href="../index.php" class="btn btn-light px-4 py-2 rounded-3 fw-bold border ms-2">
        <i class="fa-solid fa-arrow-left me-2"></i> Back to Orders
    </a>
</div>

<div class="invoice-container">
    <div class="row align-items-center mb-5">
        <div class="col-6">
            <div class="d-flex align-items-center gap-3">
                <div class="brand-logo"><i class="fa-solid fa-boxes-packing"></i></div>
                <h4 class="fw-bold mb-0"><?= APP_NAME ?></h4>
            </div>
            <p class="text-slate-500 small mt-2">123 Business Avenue, Sector-7, New York<br>Phone: +1 (555) 000-0000</p>
        </div>
        <div class="col-6 text-end">
            <h1 class="h3 fw-bold text-slate-900 mb-1">INVOICE</h1>
            <div class="text-slate-500">#ORD-<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></div>
            <div class="mt-2">
                <span class="badge-status badge-paid">
                    <?= strtoupper($order['status']) ?>
                </span>
            </div>
        </div>
    </div>

    <hr class="border-slate-100 my-4">

    <div class="row mb-5">
        <div class="col-6">
            <div class="text-uppercase small fw-bold text-slate-400 mb-2">Billed To:</div>
            <h5 class="fw-bold text-slate-900 mb-1"><?= htmlspecialchars($order['customer_name']) ?></h5>
            <p class="text-slate-500 small">
                <?= htmlspecialchars($order['customer_phone']) ?><br>
                <?= htmlspecialchars($order['customer_email']) ?><br>
                <?= nl2br(htmlspecialchars($order['customer_address'])) ?>
            </p>
        </div>
        <div class="col-6 text-end">
            <div class="text-uppercase small fw-bold text-slate-400 mb-2">Order Details:</div>
            <p class="small mb-0"><strong>Date:</strong> <?= date('d M, Y', strtotime($order['created_at'])) ?></p>
            <p class="small mb-0"><strong>Time:</strong> <?= date('h:i A', strtotime($order['created_at'])) ?></p>
            <p class="small mb-0"><strong>Type:</strong> <?= ucfirst($order['order_type']) ?></p>
            <p class="small mb-0"><strong>Status:</strong> <?= ucfirst($order['status']) ?></p>
        </div>
    </div>

    <table class="table mb-4">
        <thead>
            <tr>
                <th style="width: 50%;">Product Name</th>
                <th class="text-center">Price</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <div class="fw-bold text-slate-900"><?= htmlspecialchars($item['product_name']) ?></div>
                        <div class="small text-slate-400"><?= htmlspecialchars($item['product_sku']) ?></div>
                    </td>
                    <td class="text-center"><?= format_price($item['price']) ?></td>
                    <td class="text-center"><?= $item['quantity'] ?></td>
                    <td class="text-end fw-bold"><?= format_price($item['total']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="row justify-content-end">
        <div class="col-md-5">
            <div class="total-section">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-slate-500">Subtotal:</span>
                    <span class="fw-bold text-slate-800"><?= format_price($order['total_amount']) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-slate-500">Discount:</span>
                    <span class="fw-bold text-rose-500">-<?= format_price($order['discount']) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-slate-500">Delivery Charges:</span>
                    <span class="fw-bold text-indigo-600">+<?= format_price($order['delivery_charges']) ?></span>
                </div>
                <hr class="my-3">
                <div class="d-flex justify-content-between">
                    <span class="h5 fw-bold text-slate-900 mb-0">Final Total:</span>
                    <span class="h5 fw-bold text-blue-600 mb-0"><?= format_price($order['final_amount']) ?></span>
                </div>
            </div>
            
            <?php if ($payment): ?>
                <div class="mt-4 p-3 bg-light rounded-3 text-center small text-slate-500 border">
                    <i class="fa-solid fa-circle-check text-success me-1"></i> Payment received via <strong><?= strtoupper($payment['payment_method']) ?></strong>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-5 pt-5 text-center text-slate-400 small">
        <p>Thank you for your business! If you have any questions, please contact us.</p>
        <p class="mb-0">Created with Panze IMS v1.0</p>
    </div>
</div>

</body>
</html>
