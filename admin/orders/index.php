<?php
/**
 * Orders Management - Minimalist Dashboard
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';

// Access Control
authorize([ROLE_ADMIN]);

$page_title = "Order Management";
$page_icon = "fa-shopping-cart";
$page_description = "Manage and track your inventory sales and fulfillment";

// Search and Filter logic
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : '';

// Base query for orders joined with customers
$query = "SELECT o.*, u.name as customer_name 
          FROM orders o 
          LEFT JOIN users u ON o.customer_id = u.id 
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (o.id LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $query .= " AND o.status = ?";
    $params[] = $status_filter;
}

if ($type_filter) {
    $query .= " AND o.order_type = ?";
    $params[] = $type_filter;
}

if ($date_filter) {
    $query .= " AND DATE(o.created_at) = ?";
    $params[] = $date_filter;
}

$query .= " ORDER BY o.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $orders = [];
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
    :root {
        --card-shadow: 0 10px 40px -15px rgba(0, 0, 0, 0.1);
        --border-light: 1px solid #f1f5f9;
        --primary: #2563eb;
    }

    .table-container {
        background: white;
        border-radius: 20px;
        border: var(--border-light);
        box-shadow: var(--card-shadow);
        overflow: hidden;
        margin-top: 1.5rem;
    }

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

    .search-wrapper { position: relative; width: 100%; }
    .search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
    .search-input { 
        width: 100%; height: 44px; padding: 0 1rem 0 2.8rem; 
        background: white; border: var(--border-light); 
        border-radius: 12px; font-size: 0.9rem; transition: all 0.2s;
    }
    .search-input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

    .filter-btn {
        padding: 0.6rem 1.2rem;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
        border: 1px solid #e2e8f0;
        background: white;
        color: #64748b;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-block;
    }
    .filter-btn:hover, .filter-btn.active {
        background: #0f172a;
        color: white;
        border-color: #0f172a;
    }

    .btn-create {
        background: var(--primary); color: white;
        padding: 0.7rem 1.4rem; border-radius: 12px;
        font-weight: 700; font-size: 0.9rem; border: none;
        transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.6rem;
        text-decoration: none;
    }
    .btn-create:hover { background: #1d4ed8; transform: translateY(-2px); box-shadow: 0 10px 25px -8px #2563eb; color: white;}
</style>

<div class="px-3 py-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 fw-bold text-slate-900 mb-0"><?= $page_title ?></h1>
        <a href="create.php" class="btn-create px-4">
            <i class="fa-solid fa-plus"></i>
            <span>Create Order</span>
        </a>
    </div>

    <?php display_flash_message(); ?>

    <!-- Filters and Search -->
    <div class="bg-white p-3 rounded-4 border border-slate-100 shadow-sm mb-4">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-lg-4">
                <div class="search-wrapper">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" name="search" class="search-input" placeholder="Search Order ID or Customer..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-lg-2">
                <select name="status" class="form-select border-slate-200 rounded-3 py-2">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="completed" <?= $status_filter == 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status_filter == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-lg-2">
                <select name="type" class="form-select border-slate-200 rounded-3 py-2">
                    <option value="">All Types</option>
                    <option value="shop" <?= $type_filter == 'shop' ? 'selected' : '' ?>>Shop</option>
                    <option value="online" <?= $type_filter == 'online' ? 'selected' : '' ?>>Online</option>
                </select>
            </div>
            <div class="col-lg-2">
                <input type="date" name="date" class="form-control border-slate-200 rounded-3 py-2" value="<?= htmlspecialchars($date_filter) ?>">
            </div>
            <div class="col-lg-2">
                <button type="submit" class="btn btn-dark w-100 py-2 rounded-3 fw-bold">Apply Filter</button>
            </div>
        </form>
    </div>

    <!-- Orders Table -->
    <div class="table-container">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr class="bg-slate-50">
                        <th class="ps-4 py-3">Order ID</th>
                        <th class="py-3">Customer</th>
                        <th class="py-3">Type</th>
                        <th class="py-3">Total Amount</th>
                        <th class="py-3">Discount</th>
                        <th class="py-3">Delivery</th>
                        <th class="py-3">Final Amount</th>
                        <th class="py-3">Status</th>
                        <th class="py-3">Date</th>
                        <th class="text-end pe-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-bold text-slate-800">#ORD-<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-slate-700"><?= htmlspecialchars($order['customer_name'] ?? 'Walk-in Customer') ?></div>
                                </td>
                                <td>
                                    <span class="status-badge badge-<?= $order['order_type'] ?>">
                                        <i class="fa-solid <?= $order['order_type'] == 'shop' ? 'fa-store' : 'fa-globe' ?>"></i>
                                        <?= ucfirst($order['order_type']) ?>
                                    </span>
                                </td>
                                <td><?= format_price($order['total_amount']) ?></td>
                                <td class="text-rose-600">-<?= format_price($order['discount']) ?></td>
                                <td class="text-indigo-600">+<?= format_price($order['delivery_charges']) ?></td>
                                <td>
                                    <div class="fw-bold text-slate-900"><?= format_price($order['final_amount']) ?></div>
                                </td>
                                <td>
                                    <span class="status-badge badge-<?= $order['status'] ?>">
                                        <i class="fa-solid <?= $order['status'] == 'completed' ? 'fa-check' : ($order['status'] == 'pending' ? 'fa-clock' : 'fa-xmark') ?>"></i>
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                </td>
                                <td class="small text-slate-500">
                                    <?= date('d M, Y', strtotime($order['created_at'])) ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <a href="view.php?id=<?= $order['id'] ?>" class="btn btn-light btn-sm rounded-3 px-3 border fw-bold text-slate-600">
                                            View Details
                                        </a>
                                        <a href="invoice/index.php?order_id=<?= $order['id'] ?>" class="btn btn-outline-primary btn-sm rounded-3 px-3 fw-bold" target="_blank">
                                            <i class="fa-solid fa-file-invoice"></i> Invoice
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <div class="mb-3 text-slate-300"><i class="fa-solid fa-box-open fa-3x"></i></div>
                                <h5 class="fw-bold text-slate-600">No Orders Found</h5>
                                <p class="text-slate-400">Match your search/filters or create a new order</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.title = "<?= $page_title ?> - <?= APP_NAME ?>";
</script>

<?php include '../../includes/footer.php'; ?>
