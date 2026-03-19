<?php
/**
 * Cancel Sale & Restore Stock
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';

authorize([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('error', 'Invalid request method.');
    redirect('admin/sales/index.php');
}

$sale_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$sale_id) {
    set_flash_message('error', 'Invalid sale ID.');
    redirect('admin/sales/index.php');
}

try {
    $pdo->beginTransaction();

    // 1. Fetch Sale
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch();

    if (!$sale) {
        throw new Exception("Sale record not found.");
    }
    if ($sale['status'] === 'cancelled') {
        throw new Exception("Sale is already cancelled.");
    }

    // 2. Fetch Sale Items to restore stock
    $stmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
    $stmt->execute([$sale_id]);
    $items = $stmt->fetchAll();

    $created_by = $_SESSION[SESSION_USER_ID] ?? 1;

    foreach ($items as $item) {
        $pid = $item['product_id'];
        $qty = $item['quantity'];

        // Restore stock
        $pdo->prepare("UPDATE product_stock SET quantity = quantity + ? WHERE product_id = ?")
            ->execute([$qty, $pid]);

        // Record IN transaction
        $pdo->prepare("
            INSERT INTO product_transactions (product_id, quantity, type, reference_type, reference_id, created_by, note)
            VALUES (?, ?, 'IN', 'sale_return', ?, ?, ?)
        ")->execute([
            $pid, $qty, $sale_id, $created_by, "Returned from Cancelled Sale #SALE-" . str_pad($sale_id, 5, '0', STR_PAD_LEFT)
        ]);
    }

    // 3. Update Sale Status
    $pdo->prepare("UPDATE sales SET status = 'cancelled' WHERE id = ?")->execute([$sale_id]);

    // 4. Update Linked Order (if any)
    if ($sale['order_id']) {
        $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$sale['order_id']]);
        
        // Also update any payments linked to the order/sale to 'unpaid' status? 
        // Typically cancellation means the money is "lost" or "refunded" manually. 
        // We'll leave payment records as is but the status reflects cancellation.
    }

    $pdo->commit();
    set_flash_message('success', "Sale #SALE-$sale_id and linked order have been cancelled. Stock has been restored.");
    redirect('admin/sales/view.php?id=' . $sale_id);

} catch (Exception $e) {
    $pdo->rollBack();
    set_flash_message('error', "Error cancelling sale: " . $e->getMessage());
    redirect('admin/sales/view.php?id=' . $sale_id);
}
