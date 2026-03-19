<?php

/**
 * Product Management - List Products
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
$allowed_roles = [1]; // Admin only

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

$page_title = "Products Inventory";
$page_icon = "fa-boxes-stacked";
$page_description = "Manage your product catalog, pricing, and stock status";

// Search and Filter Logic
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

// Pagination
$limit = 10; // ITEMS_PER_PAGE
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$query = "SELECT * FROM products WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (name LIKE '%$search%' OR sku LIKE '%$search%')";
}

if (!empty($status_filter)) {
    $query .= " AND status = '$status_filter'";
}

// Get total count for pagination
$count_query = str_replace("SELECT *", "SELECT COUNT(*) as total", $query);
$count_result = mysqli_query($conn, $count_query);
$total_items = $count_result ? (int)mysqli_fetch_assoc($count_result)['total'] : 0;
$total_pages = ceil($total_items / $limit);

$query .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";

$products = [];
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
    :root {
        --card-shadow: 0 10px 40px -15px rgba(0, 0, 0, 0.1);
        --hover-shadow: 0 20px 40px -15px rgba(37, 99, 235, 0.15);
        --border-light: 1px solid #f1f5f9;
    }

    .page-header {
        padding: 1.5rem 1.5rem 1rem 1.5rem;
        background: white;
        border-radius: 20px;
        border: var(--border-light);
        box-shadow: var(--card-shadow);
        margin-bottom: 1.5rem;
    }

    .page-icon {
        width: 56px;
        height: 56px;
        background: linear-gradient(135deg, #2563eb10, #1d4ed810);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        color: #2563eb;
        border: 1px solid #2563eb20;
    }

    .btn-create {
        background: #2563eb;
        color: white;
        padding: 0.6rem 1.4rem;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.9rem;
        border: none;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        text-decoration: none;
    }

    .btn-create:hover {
        background: #1d4ed8;
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -8px #2563eb;
        color: white;
    }

    .filter-section {
        background: white;
        padding: 1.2rem;
        border-radius: 16px;
        border: var(--border-light);
        margin-bottom: 1.5rem;
    }

    .search-wrapper {
        position: relative;
    }

    .search-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 0.9rem;
        pointer-events: none;
    }

    .search-input {
        width: 100%;
        height: 42px;
        padding: 0 1rem 0 2.8rem;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 12px;
        font-size: 0.9rem;
        color: #1e293b;
        transition: all 0.2s;
    }

    .search-input:focus {
        outline: none;
        border-color: #2563eb;
        background: white;
        box-shadow: 0 0 0 4px #2563eb20;
    }

    .filter-select {
        height: 42px;
        padding: 0 1rem;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 12px;
        font-size: 0.9rem;
        color: #1e293b;
        cursor: pointer;
        transition: all 0.2s;
        width: 100%;
    }

    .btn-search {
        height: 42px;
        padding: 0 1.8rem;
        background: #1e293b;
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-reset {
        height: 42px;
        width: 42px;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 12px;
        color: #64748b;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        text-decoration: none;
    }

    .table-container {
        background: white;
        border-radius: 20px;
        border: var(--border-light);
        box-shadow: var(--card-shadow);
        overflow: hidden;
    }

    .table thead th {
        background: #f8fafc;
        padding: 1rem 1.5rem;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #64748b;
        border-bottom: 1px solid #f1f5f9;
    }

    .table tbody td {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #f8fafc;
        vertical-align: middle;
    }

    .btn-action {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        border: none;
        font-size: 0.9rem;
        text-decoration: none;
    }

    .btn-action-edit {
        background: #eff6ff;
        color: #2563eb;
    }

    .btn-action-edit:hover {
        background: #2563eb;
        color: white;
        transform: translateY(-2px);
    }

    .btn-action-delete {
        background: #fff1f2;
        color: #e11d48;
    }

    .btn-action-delete:hover {
        background: #e11d48;
        color: white;
        transform: translateY(-2px);
    }

    .status-indicator {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.8rem;
        font-weight: 600;
        padding: 0.3rem 0.8rem;
        border-radius: 8px;
        background: #f8fafc;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }

    .price-tag {
        font-weight: 700;
        color: #1e293b;
    }

    .sku-badge {
        font-family: 'Monaco', 'Consolas', monospace;
        background: #f1f5f9;
        color: #475569;
        padding: 0.2rem 0.5rem;
        border-radius: 6px;
        font-size: 0.75rem;
    }
</style>

<div class="px-3 py-3">
    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="page-icon">
                <i class="fa-solid <?= $page_icon ?>"></i>
            </div>
            <div>
                <h1 class="h5 fw-bold text-slate-900 mb-0"><?= $page_title ?></h1>
                <p class="text-slate-500 small mb-0"><?= $page_description ?></p>
            </div>
        </div>
        <div>
            <a href="create.php" class="btn-create">
                <i class="fa-solid fa-plus-circle"></i>
                <span>Add Product</span>
            </a>
        </div>
    </div>

    <?php display_flash_message(); ?>

    <!-- Filters -->
    <div class="filter-section">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-lg-6">
                <div class="search-wrapper">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" name="search" class="search-input" placeholder="Search by name or SKU..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-lg-3">
                <select name="status" class="filter-select">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status_filter == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-lg-3">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn-search flex-grow-1">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <span>Search</span>
                    </button>
                    <a href="index.php" class="btn-reset" title="Reset filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Products Table -->
    <div class="table-container">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product Details</th>
                        <th>SKU</th>
                        <th>Cost Price</th>
                        <th>Selling Price</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td class="text-slate-500 font-medium">#<?= $product['id'] ?></td>
                                <td>
                                    <div class="fw-bold text-slate-900"><?= htmlspecialchars($product['name']) ?></div>
                                    <div class="small text-slate-500">Added on <?= date('M d, Y', strtotime($product['created_at'])) ?></div>
                                </td>
                                <td>
                                    <span class="sku-badge"><?= htmlspecialchars($product['sku']) ?></span>
                                </td>
                                <td><span class="price-tag"><?= format_price($product['cost_price']) ?></span></td>
                                <td><span class="price-tag text-blue-600"><?= format_price($product['selling_price']) ?></span></td>
                                <td>
                                    <?php if ($product['status'] == 'active'): ?>
                                        <span class="status-indicator">
                                            <span class="status-dot" style="background: #10b981;"></span>
                                            <span style="color: #059669;">Active</span>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-indicator">
                                            <span class="status-dot" style="background: #ef4444;"></span>
                                            <span style="color: #dc2626;">Inactive</span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2 justify-content-end">
                                        <a href="edit.php?id=<?= $product['id'] ?>" class="btn-action btn-action-edit" title="Edit product">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <a href="javascript:void(0)" onclick="confirmDelete(<?= $product['id'] ?>)" class="btn-action btn-action-delete" title="Delete product">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-slate-400 mb-2"><i class="fa-solid fa-box-open fa-3x"></i></div>
                                <div class="fw-bold text-slate-600">No products found</div>
                                <div class="text-slate-400 small">Try adjusting your filters or add a new product</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<script>
    function confirmDelete(id) {
        if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
            window.location.href = 'delete.php?id=' + id;
        }
    }
    document.title = "<?= $page_title ?> - Inventory System";
</script>

<?php include '../../includes/footer.php'; ?>