<?php
/**
 * View Order Details
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';

// Access Control
authorize([ROLE_ADMIN]);

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$order_id) {
    set_flash_message('error', "Invalid order requested.");
    redirect('admin/orders/index.php');
}

// Handle Status Changes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = sanitize($_POST['status']);
    $current_status_stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $current_status_stmt->execute([$order_id]);
    $current_status = $current_status_stmt->fetchColumn();

    if ($current_status !== $new_status) {
        try {
            $pdo->beginTransaction();

            // 1. Update Order Status
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $order_id]);

            // 2. Cancellation Logic (RESTORE STOCK & Sync Sale)
            if ($new_status === 'cancelled') {
                // Restore stock
                $items_stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                $items_stmt->execute([$order_id]);
                $items = $items_stmt->fetchAll();

                foreach ($items as $item) {
                    $pdo->prepare("UPDATE product_stock SET quantity = quantity + ? WHERE product_id = ?")
                        ->execute([$item['quantity'], $item['product_id']]);
                    $pdo->prepare("INSERT INTO product_transactions (product_id, quantity, type, reference_type, reference_id, created_by, note) 
                                   VALUES (?, ?, 'IN', 'order', ?, ?, ?)")
                        ->execute([$item['product_id'], $item['quantity'], $order_id, $_SESSION[SESSION_USER_ID], "Stock Restored - Cancelled Order #$order_id"]);
                }

                // Sync Sale Cancellation
                $pdo->prepare("UPDATE sales SET status = 'cancelled' WHERE order_id = ?")->execute([$order_id]);
            }

            // 3. Status Completion Logic (Ensure Sale Record Exists)
            if ($new_status === 'completed') {
                // Check if sale exists
                $s_check = $pdo->prepare("SELECT id FROM sales WHERE order_id = ?");
                $s_check->execute([$order_id]);
                $sale_exists = $s_check->fetchColumn();

                if (!$sale_exists) {
                    // Create Sale from scratch
                    $o_data = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
                    $o_data->execute([$order_id]);
                    $ord = $o_data->fetch();

                    $pdo->prepare("INSERT INTO sales (order_id, customer_id, created_by, total_amount, discount, final_amount, status) VALUES (?, ?, ?, ?, ?, ?, 'completed')")
                        ->execute([$order_id, $ord['customer_id'], $ord['created_by'], $ord['total_amount'], $ord['discount'], $ord['final_amount']]);
                    $new_sale_id = $pdo->lastInsertId();

                    // Copy items
                    $oi_stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
                    $oi_stmt->execute([$order_id]);
                    $o_items = $oi_stmt->fetchAll();
                    foreach ($o_items as $oi) {
                        $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)")
                            ->execute([$new_sale_id, $oi['product_id'], $oi['quantity'], $oi['price']]);
                    }
                } else {
                    // Update existing sale status
                    $pdo->prepare("UPDATE sales SET status = 'completed' WHERE order_id = ?")->execute([$order_id]);
                }
            }

            $pdo->commit();
            set_flash_message('success', "Order and linked sale updated to " . ucfirst($new_status));
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            set_flash_message('error', "Failed to update status: " . $e->getMessage());
        }
    }
}

// Fetch Order Details
try {
    $order_stmt = $pdo->prepare("SELECT o.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_address, u.name as staff_name 
                                FROM orders o 
                                LEFT JOIN users c ON o.customer_id = c.id 
                                LEFT JOIN users u ON o.created_by = u.id 
                                WHERE o.id = ?");
    $order_stmt->execute([$order_id]);
    $order = $order_stmt->fetch();

    if (!$order) {
        set_flash_message('error', "Order details not found for ID $order_id.");
        redirect('admin/orders/index.php');
    }

    $items_stmt = $pdo->prepare("SELECT oi.*, p.name as product_name, p.sku as product_sku 
                                FROM order_items oi 
                                JOIN products p ON oi.product_id = p.id 
                                WHERE oi.order_id = ?");
    $items_stmt->execute([$order_id]);
    $items = $items_stmt->fetchAll();

} catch (PDOException $e) {
    die("Error fetching order details: " . $e->getMessage());
}

$page_title = "Order Details #ORD-" . str_pad($order['id'], 5, '0', STR_PAD_LEFT);
$page_icon = "fa-file-invoice";

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="px-3 py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-purple-50 text-purple-600 rounded-4 border border-purple-100">
                <i class="fa-solid <?= $page_icon ?> fa-xl"></i>
            </div>
            <div>
                <h1 class="h4 fw-bold text-slate-900 mb-0"><?= $page_title ?></h1>
                <p class="text-slate-500 small mb-0">Managing order from <?= htmlspecialchars($order['customer_name']) ?></p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="invoice/index.php?order_id=<?= $order['id'] ?>" class="btn btn-primary rounded-3 px-3 py-2 fw-bold" target="_blank">
                <i class="fa-solid fa-file-invoice me-1"></i> View Invoice
            </a>
            <button class="btn btn-light rounded-3 px-3 py-2 border fw-bold text-slate-700" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Print
            </button>
            <a href="index.php" class="btn btn-dark rounded-3 px-3 py-2 fw-bold">Back to List</a>
        </div>
    </div>

    <?php display_flash_message(); ?>

    <div class="row g-4">
        <!-- Main Content: Items Table -->
        <div class="col-lg-8">
            <div class="bg-white p-4 rounded-4 border border-slate-100 shadow-sm mb-4">
                <h5 class="fw-bold text-slate-800 mb-4 h6 text-uppercase tracking-wider">Item Breakdown</h5>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 small uppercase fw-bold">
                                <th>Product Details</th>
                                <th class="text-center">Price</th>
                                <th class="text-center">Quantity</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-slate-900"><?= htmlspecialchars($item['product_name']) ?></div>
                                        <div class="small text-slate-400">SKU: <?= htmlspecialchars($item['product_sku']) ?></div>
                                    </td>
                                    <td class="text-center"><?= format_price($item['price']) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-slate-700 rounded-pill px-3 py-2"><?= $item['quantity'] ?></span>
                                    </td>
                                    <td class="text-end fw-bold"><?= format_price($item['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="border-top-2">
                            <tr>
                                <td colspan="3" class="text-end text-slate-500 pt-4">Subtotal</td>
                                <td class="text-end pt-4 fw-bold text-slate-800"><?= format_price($order['total_amount']) ?></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end text-rose-500">Discount</td>
                                <td class="text-end text-rose-500 fw-bold">-<?= format_price($order['discount']) ?></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end text-indigo-600">Delivery Charges</td>
                                <td class="text-end text-indigo-600 fw-bold">+<?= format_price($order['delivery_charges']) ?></td>
                            </tr>
                            <tr class="border-top">
                                <td colspan="3" class="text-end h5 fw-bold text-slate-900 pt-3">Final Total</td>
                                <td class="text-end h5 fw-bold text-blue-600 pt-3"><?= format_price($order['final_amount']) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sidebar: Order Context & Actions -->
        <div class="col-lg-4">
            <!-- Customer Card -->
            <div class="bg-white p-4 rounded-4 border border-slate-100 shadow-sm mb-4">
                <h5 class="fw-bold text-slate-800 mb-4 h6 text-uppercase tracking-wider">Customer Details</h5>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="flex-shrink-0 bg-blue-100 text-blue-600 rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-slate-900"><?= htmlspecialchars($order['customer_name']) ?></div>
                        <div class="small text-slate-500"><?= htmlspecialchars($order['customer_phone']) ?></div>
                    </div>
                </div>
                <div class="pt-3 border-top">
                    <div class="text-slate-400 small fw-bold mb-1">EMAIL</div>
                    <div class="small text-slate-700 mb-3"><?= htmlspecialchars($order['customer_email']) ?></div>
                    
                    <div class="text-slate-400 small fw-bold mb-1">ADDRESS</div>
                    <div class="small text-slate-700"><?= nl2br(htmlspecialchars($order['customer_address'])) ?></div>
                </div>
            </div>

            <!-- Order Stats -->
            <div class="bg-white p-4 rounded-4 border border-slate-100 shadow-sm mb-4">
                <h5 class="fw-bold text-slate-800 mb-4 h6 text-uppercase tracking-wider">Order Context</h5>
                
                <div class="mb-3">
                    <div class="text-slate-400 small fw-bold mb-1">ORDER TYPE</div>
                    <span class="status-badge badge-<?= $order['order_type'] ?>">
                        <i class="fa-solid <?= $order['order_type'] == 'shop' ? 'fa-store' : 'fa-globe' ?>"></i>
                        <?= ucfirst($order['order_type']) ?>
                    </span>
                </div>

                <div class="mb-3">
                    <div class="text-slate-400 small fw-bold mb-1">STATUS</div>
                    <span class="status-badge badge-<?= $order['status'] ?>">
                        <i class="fa-solid <?= $order['status'] == 'completed' ? 'fa-check' : ($order['status'] == 'pending' ? 'fa-clock' : 'fa-xmark') ?>"></i>
                        <?= ucfirst($order['status']) ?>
                    </span>
                </div>

                <?php 
                    // Check for linked sale
                    $sale_stmt = $pdo->prepare("SELECT id FROM sales WHERE order_id = ?");
                    $sale_stmt->execute([$order['id']]);
                    $linked_sale = $sale_stmt->fetch();
                ?>
                <?php if ($linked_sale): ?>
                <div class="mb-3">
                    <div class="text-slate-400 small fw-bold mb-1">LINKED SALE</div>
                    <a href="../sales/view.php?id=<?= $linked_sale['id'] ?>" class="fw-bold text-blue-600 small">
                        #SALE-<?= str_pad($linked_sale['id'], 5, '0', STR_PAD_LEFT) ?>
                        <i class="fa-solid fa-arrow-up-right-from-square ms-1" style="font-size:10px"></i>
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="mb-0">
                    <div class="text-slate-400 small fw-bold mb-1">PLACED BY</div>
                    <div class="small text-slate-700"><i class="fa-solid fa-user-tie me-1"></i> <?= htmlspecialchars($order['staff_name']) ?></div>
                    <div class="small text-slate-500"><i class="fa-solid fa-clock me-1"></i> <?= date('d M, Y h:i A', strtotime($order['created_at'])) ?></div>
                </div>
            </div>

            <!-- Management Actions -->
            <div class="bg-white p-4 rounded-4 border border-slate-100 shadow-sm">
                <h5 class="fw-bold text-slate-800 mb-4 h6 text-uppercase tracking-wider">Order Management</h5>
                
                <form method="POST">
                    <input type="hidden" name="update_status" value="1">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-slate-600">Change Status</label>
                        <select name="status" class="form-select rounded-3 py-2" <?= in_array($order['status'], ['cancelled', 'completed']) ? 'disabled' : '' ?>>
                            <option value="pending" <?= $order['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="completed" <?= $order['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                            <?php if ($order['status'] == 'pending'): ?>
                                <option value="cancelled">Cancelled</option>
                            <?php endif; ?>
                            <?php if ($order['status'] == 'cancelled'): ?>
                                <option value="cancelled" selected disabled>Cancelled</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <?php if ($order['status'] != 'cancelled'): ?>
                        <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-bold">Update Status</button>
                    <?php else: ?>
                        <div class="alert alert-danger small p-2 text-center mb-0 rounded-3">
                            <i class="fa-solid fa-circle-exclamation me-1"></i> Order is cancelled and stock has been restored.
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .status-badge {
        padding: 0.4rem 1rem;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    .badge-pending { background: #fff7ed; color: #ea580c; }
    .badge-completed { background: #ecfdf5; color: #059669; }
    .badge-cancelled { background: #fef2f2; color: #dc2626; }
    .badge-shop { background: #f1f5f9; color: #475569; }
    .badge-online { background: #eef2ff; color: #4f46e5; }
    
    @media print {
        .includes-sidebar, .includes-navbar, .btn, .management-card { display: none !important; }
        .px-3 { padding: 0 !important; }
        body { background: white !important; }
    }
</style>

<?php include '../../includes/footer.php'; ?>
