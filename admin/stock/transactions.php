<?php
/**
 * Stock Management - Stock History / Transactions
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';

// Access Control
authorize([ROLE_ADMIN]);

$page_title = "Stock Movement History";
$page_icon = "fa-clock-rotate-left";
$page_description = "Detailed audit log of every stock change, including manual additions and order deductions";

// Fetching filter parameters
$product_id_filter = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$type_filter = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : '';

// Base query for transactions
$query = "SELECT t.*, p.name as product_name, p.sku as product_sku, u.name as user_name 
          FROM product_transactions t 
          LEFT JOIN products p ON t.product_id = p.id 
          LEFT JOIN users u ON t.created_by = u.id 
          WHERE 1=1";
$params = [];

// Filtering logic
if ($product_id_filter) {
    $query .= " AND t.product_id = ?";
    $params[] = $product_id_filter;
}
if ($type_filter) {
    $query .= " AND t.type = ?";
    $params[] = $type_filter;
}
if ($start_date) {
    $query .= " AND DATE(t.created_at) >= ?";
    $params[] = $start_date;
}
if ($end_date) {
    $query .= " AND DATE(t.created_at) <= ?";
    $params[] = $end_date;
}

$query .= " ORDER BY t.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    $transactions = [];
}

// Fetch products for the dropdown filter
try {
    $products = $pdo->query("SELECT id, name FROM products ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
    .transaction-card { background: white; border-radius: 20px; border: 1px solid #f1f5f9; box-shadow: 0 10px 40px -15px rgba(0,0,0,0.08); overflow: hidden; }
    .table thead th { background: #f8fafc; color: #64748b; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; padding: 1rem 1.5rem; }
    .table tbody td { padding: 1.2rem 1.5rem; border-bottom: 1px solid #f8fafc; }
    .type-badge { padding: 0.35rem 0.85rem; border-radius: 8px; font-size: 0.7rem; font-weight: 800; display: inline-flex; align-items: center; gap: 0.4rem; }
    .badge-in { background: #ecfdf5; color: #059669; }
    .badge-out { background: #fef2f2; color: #dc2626; }
    .ref-badge { background: #f1f5f9; color: #475569; padding: 0.25rem 0.6rem; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: capitalize; }
    .date-text { font-size: 0.85rem; font-weight: 600; color: #1e293b; }
    .time-text { font-size: 0.75rem; color: #94a3b8; }
    .filter-panel { background: white; border-radius: 16px; border: 1px solid #e2e8f0; padding: 1.25rem; margin-bottom: 1.5rem; }
    .filter-label { font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 0.4rem; display: block; }
    .filter-control { 
        height: 42px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-size: 0.85rem; padding: 0 1rem; width: 100%; transition: all 0.2s;
    }
    .filter-control:focus { outline: none; border-color: #2563eb; }
</style>

<div class="px-4 py-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-purple-50 text-purple-600 rounded-4 border border-purple-100">
                <i class="fa-solid <?= $page_icon ?> fa-xl"></i>
            </div>
            <div>
                <h1 class="h4 fw-bold text-slate-900 mb-0"><?= $page_title ?></h1>
                <p class="text-slate-500 small mb-0"><?= $page_description ?></p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-light rounded-3 px-3 py-2 border fw-bold text-slate-700">Stock Dashboard</a>
            <a href="add_stock.php" class="btn btn-primary rounded-3 px-3 py-2 fw-bold shadow-sm">Record Intake</a>
        </div>
    </div>

    <!-- Filter Panel -->
    <div class="filter-panel shadow-sm">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="filter-label">Product</label>
                <?php if ($product_id_filter > 0): ?>
                    <div class="position-relative">
                        <select class="filter-control bg-light" disabled>
                            <?php foreach ($products as $product): ?>
                                <?php if ($product_id_filter == $product['id']): ?>
                                    <option value="<?= $product['id'] ?>" selected>
                                        <?= htmlspecialchars($product['name']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="product_id" value="<?= $product_id_filter ?>">
                        <a href="transactions.php" class="position-absolute end-0 top-50 translate-middle-y me-2 text-danger small fw-bold text-decoration-none" title="Clear Product Filter">
                            <i class="fa-solid fa-circle-xmark"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <select name="product_id" class="filter-control">
                        <option value="0">All Products</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= $product['id'] ?>" <?= $product_id_filter == $product['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($product['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div class="col-md-2">
                <label class="filter-label">Type</label>
                <select name="type" class="filter-control">
                    <option value="">All Types</option>
                    <option value="IN" <?= $type_filter == 'IN' ? 'selected' : '' ?>>IN (Added)</option>
                    <option value="OUT" <?= $type_filter == 'OUT' ? 'selected' : '' ?>>OUT (Deducted)</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="filter-label">From Date</label>
                <input type="date" name="start_date" class="filter-control" value="<?= $start_date ?>">
            </div>
            <div class="col-md-2">
                <label class="filter-label">To Date</label>
                <input type="date" name="end_date" class="filter-control" value="<?= $end_date ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-dark w-100 rounded-3 py-2 fw-bold">Filter</button>
                <a href="transactions.php" class="btn btn-light border rounded-3 py-2 px-3 fw-bold text-slate-500" title="Reset All Filters"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>

    <!-- Transactions List -->
    <div class="transaction-card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Product Details</th>
                        <th class="text-center">Type</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-center">Reference</th>
                        <th class="text-center">Created By</th>
                        <th class="text-center">Date & Time</th>
                        <th class="pe-4">Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transactions)): ?>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-slate-900"><?= htmlspecialchars($t['product_name']) ?></div>
                                    <div class="small text-slate-400"><?= htmlspecialchars($t['product_sku'] ?? 'N/A') ?></div>
                                </td>
                                <td class="text-center">
                                    <?php if ($t['type'] == 'IN'): ?>
                                        <span class="type-badge badge-in"><i class="fa-solid fa-arrow-down"></i> IN</span>
                                    <?php else: ?>
                                        <span class="type-badge badge-out"><i class="fa-solid fa-arrow-up"></i> OUT</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="h6 fw-bold mb-0 <?= $t['type'] == 'IN' ? 'text-emerald-600' : 'text-rose-600' ?>">
                                        <?= $t['type'] == 'IN' ? '+' : '-' ?><?= number_format($t['quantity']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="ref-badge"><?= htmlspecialchars($t['reference_type']) ?></span>
                                    <?php if ($t['reference_id']): ?>
                                        <div class="small text-slate-400 mt-1">ID: #<?= $t['reference_id'] ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center text-slate-600 fw-medium small">
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <i class="fa-regular fa-user-circle text-slate-300"></i>
                                        <?= htmlspecialchars($t['user_name'] ?? 'System') ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="date-text"><?= date('d M, Y', strtotime($t['created_at'])) ?></div>
                                    <div class="time-text"><?= date('h:i A', strtotime($t['created_at'])) ?></div>
                                </td>
                                <td class="pe-4">
                                    <div class="small text-slate-500 fst-italic w-40 truncate-text" title="<?= htmlspecialchars($t['note'] ?? 'No notes provided') ?>">
                                        <?= $t['note'] ? htmlspecialchars(substr($t['note'], 0, 50)) . (strlen($t['note']) > 50 ? '...' : '') : '<span class="text-slate-200">No notes</span>' ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="mb-3 text-slate-200"><i class="fa-solid fa-clock-rotate-left fa-3x"></i></div>
                                <h6 class="fw-bold text-slate-400">No transaction logs available</h6>
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
