<?php
/**
 * Stock Management - Minimalist Dashboard
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';

// Access Control
authorize([ROLE_ADMIN]);

$page_title = "Stock Management";
$page_icon = "fa-warehouse";
$page_description = "Manage and monitor your warehouse inventory levels";

// Search and Filter logic
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Base query for products joined with stock
$query = "SELECT p.*, COALESCE(s.quantity, 0) as stock_quantity 
          FROM products p 
          LEFT JOIN product_stock s ON p.id = s.product_id 
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Filter logic by inventory status
if ($status_filter) {
    if ($status_filter == 'in_stock') {
        $query .= " AND COALESCE(s.quantity, 0) > 10";
    } elseif ($status_filter == 'low_stock') {
        $query .= " AND COALESCE(s.quantity, 0) <= 10 AND COALESCE(s.quantity, 0) > 0";
    } elseif ($status_filter == 'out_of_stock') {
        $query .= " AND COALESCE(s.quantity, 0) = 0";
    }
}

$query .= " ORDER BY COALESCE(s.quantity, 0) ASC, p.name ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
    :root {
        --card-shadow: 0 10px 40px -15px rgba(0, 0, 0, 0.1);
        --border-light: 1px solid #f1f5f9;
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
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .badge-in { background: #ecfdf5; color: #059669; }
    .badge-low { background: #fff7ed; color: #ea580c; }
    .badge-out { background: #fef2f2; color: #dc2626; }

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
        font-size: 0.85rem;
        font-weight: 600;
        border: 1px solid #e2e8f0;
        background: white;
        color: #64748b;
        transition: all 0.2s;
        text-decoration: none;
    }
    .filter-btn:hover, .filter-btn.active {
        background: #2563eb;
        color: white;
        border-color: #2563eb;
    }

    .btn-add {
        background: #2563eb; color: white;
        padding: 0.7rem 1.4rem; border-radius: 12px;
        font-weight: 700; font-size: 0.9rem; border: none;
        transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.6rem;
        text-decoration: none;
    }
    .btn-add:hover { background: #1d4ed8; transform: translateY(-2px); box-shadow: 0 10px 25px -8px #2563eb; color: white; }
</style>

<div class="px-4 py-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 fw-bold text-slate-900 mb-0"><?= $page_title ?></h1>
        <a href="add_stock.php" class="btn-add px-4">
            <i class="fa-solid fa-plus-circle"></i>
            <span>Add Stock</span>
        </a>
    </div>

    <?php display_flash_message(); ?>

    <!-- Filters and Search -->
    <div class="bg-white p-3 rounded-4 border border-slate-100 shadow-sm mb-4">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-lg-5">
                <div class="search-wrapper">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" name="search" class="search-input" placeholder="Search product name or SKU..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-lg-5">
                <div class="d-flex gap-2">
                    <a href="?status=&search=<?= urlencode($search) ?>" class="filter-btn <?= $status_filter == '' ? 'active' : '' ?>">All</a>
                    <a href="?status=in_stock&search=<?= urlencode($search) ?>" class="filter-btn <?= $status_filter == 'in_stock' ? 'active' : '' ?>">In Stock</a>
                    <a href="?status=low_stock&search=<?= urlencode($search) ?>" class="filter-btn <?= $status_filter == 'low_stock' ? 'active' : '' ?>">Low Stock</a>
                    <a href="?status=out_of_stock&search=<?= urlencode($search) ?>" class="filter-btn <?= $status_filter == 'out_of_stock' ? 'active' : '' ?>">Out of Stock</a>
                </div>
            </div>
            <div class="col-lg-2">
                <button type="submit" class="btn btn-dark w-100 py-2 rounded-3 fw-bold">Apply Search</button>
            </div>
        </form>
    </div>

    <!-- Stock Table -->
    <div class="table-container">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Product Name</th>
                        <th>SKU</th>
                        <th class="text-center">Current Quantity</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): 
                            $qty = $product['stock_quantity'];
                            $status_badge = '';
                            
                            if ($qty == 0) {
                                $status_badge = '<span class="status-badge badge-out"><i class="fa-solid fa-circle-xmark"></i> Out of Stock</span>';
                            } elseif ($qty <= 10) {
                                $status_badge = '<span class="status-badge badge-low"><i class="fa-solid fa-triangle-exclamation"></i> Low Stock</span>';
                            } else {
                                $status_badge = '<span class="status-badge badge-in"><i class="fa-solid fa-check-circle"></i> In Stock</span>';
                            }
                        ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-slate-900"><?= htmlspecialchars($product['name']) ?></div>
                                </td>
                                <td><span class="font-mono text-slate-500 small px-2 py-1 bg-slate-50 border border-slate-100 rounded"><?= htmlspecialchars($product['sku']) ?></span></td>
                                <td class="text-center">
                                    <span class="h5 fw-bold mb-0 <?= $qty == 0 ? 'text-rose-600' : ($qty <= 10 ? 'text-amber-600' : 'text-slate-900') ?>"><?= number_format($qty) ?></span>
                                </td>
                                <td><?= $status_badge ?></td>
                                <td class="text-end pe-4">
                                    <div class="dropdown">
                                        <button class="btn btn-light btn-sm rounded-3 px-3 border" type="button" data-bs-toggle="dropdown">
                                            Manage <i class="fa-solid fa-chevron-down ms-1 small"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4">
                                            <li><a class="dropdown-item py-2" href="add_stock.php?id=<?= $product['id'] ?>"><i class="fa-solid fa-plus-circle text-blue-500 me-2"></i> Add Stock</a></li>
                                            <li><a class="dropdown-item py-2" href="transactions.php?product_id=<?= $product['id'] ?>"><i class="fa-solid fa-history text-slate-500 me-2"></i> View History</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="mb-3 text-slate-300"><i class="fa-solid fa-box-open fa-3x"></i></div>
                                <h5 class="fw-bold text-slate-600">No Inventory Found</h5>
                                <p class="text-slate-400">Match your search/filters or add new products first</p>
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
