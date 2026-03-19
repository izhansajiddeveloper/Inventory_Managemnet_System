<?php
/**
 * Detailed Product-wise Profit Report
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';

authorize([ROLE_ADMIN]);

$page_title = "Product Profit Margins";
$page_icon = "fa-box-open";
$page_description = "Comparative analysis of unit profitability and price efficiency per product";

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

$query = "
    SELECT pr.*, p.name as product_name, p.sku as product_sku, p.status as p_status
    FROM profits pr
    JOIN products p ON pr.reference_id = p.id
    WHERE pr.reference_type = 'product'";

$params = [];
if ($search) {
    $query .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY pr.profit_amount DESC"; // Ranking high-margin products

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Stats
    $summary = $pdo->query("
        SELECT 
           AVG(profit_amount / NULLIF(selling_price, 0)) * 100 as avg_margin_pct,
           MAX(profit_amount) as max_profit,
           MIN(profit_amount) as min_profit
        FROM profits 
        WHERE reference_type = 'product'
    ")->fetch();

} catch (PDOException $e) { $products = []; }

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="px-3 py-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 fw-bold text-slate-900 mb-0"><?= $page_title ?></h1>
            <p class="text-slate-500 small mb-0"><?= $page_description ?></p>
        </div>
    </div>

    <!-- Margin Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="bg-indigo-600 p-4 rounded-4 shadow-sm text-white text-center border-0 border-top border-5 border-indigo-400">
                <div class="small fw-bold text-indigo-100 uppercase tracking-wider mb-2">Average Sales Margin</div>
                <div class="h3 fw-bold mb-0"><?= number_format($summary['avg_margin_pct'] ?? 0, 1) ?>%</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="bg-emerald-600 p-4 rounded-4 shadow-sm text-white text-center border-0 border-top border-5 border-emerald-400">
                <div class="small fw-bold text-emerald-100 uppercase tracking-wider mb-2">Top Product Contribution</div>
                <div class="h3 fw-bold mb-0"><?= format_price($summary['max_profit'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="bg-rose-600 p-4 rounded-4 shadow-sm text-white text-center border-0 border-top border-5 border-rose-400">
                <div class="small fw-bold text-rose-100 uppercase tracking-wider mb-2">Lowest Product Yield</div>
                <div class="h3 fw-bold mb-0"><?= format_price($summary['min_profit'] ?? 0) ?></div>
            </div>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="bg-white p-3 rounded-4 border border-slate-100 shadow-sm mb-4">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-lg-10">
                <div class="position-relative">
                    <i class="fa-solid fa-magnifying-glass position-absolute top-50 translate-middle-y ms-3 text-slate-400"></i>
                    <input type="text" name="search" class="form-control rounded-3 py-2 ps-5 border-slate-200" placeholder="Search product name or SKU..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-lg-2">
                <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-bold shadow-sm">Search</button>
            </div>
        </form>
    </div>

    <!-- Margins Table -->
    <div class="bg-white rounded-4 border border-slate-100 shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 small uppercase fw-bold tracking-wider">
                        <th class="ps-4 py-3">Product Item</th>
                        <th class="py-3">SKU</th>
                        <th class="py-3 text-end">Accumulated Cost</th>
                        <th class="py-3 text-end">Total Revenue</th>
                        <th class="py-3 text-end pe-4 text-emerald-600">Total Profit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-slate-900"><?= htmlspecialchars($p['product_name']) ?></div>
                                    <span class="badge border py-1 px-2 rounded-pill fw-bold text-uppercase <?= $p['p_status'] == 'active' ? 'bg-indigo-50 text-indigo-700 border-indigo-100' : 'bg-slate-50 text-slate-400 border-slate-100' ?>" style="font-size: 8px;">
                                        <?= $p['p_status'] ?>
                                    </span>
                                </td>
                                <td class="text-slate-500 small font-monospace"><?= htmlspecialchars($p['product_sku'] ?? 'N/A') ?></td>
                                <td class="text-end fw-semibold text-slate-600"><?= format_price($p['cost_price']) ?></td>
                                <td class="text-end fw-semibold text-blue-600"><?= format_price($p['selling_price']) ?></td>
                                <td class="text-end pe-4">
                                    <div class="fw-black h6 mb-0 text-emerald-600">+ <?= format_price($p['profit_amount']) ?></div>
                                    <div class="progress mt-1" style="height: 4px; width: 80px; margin-left: auto;">
                                        <?php $pct = ($p['profit_amount'] / ($p['selling_price'] ?: 1)) * 100; ?>
                                        <div class="progress-bar bg-emerald-500" role="progressbar" style="width: <?= $pct ?>%" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <div class="small mt-1" style="font-size: 10px; opacity: 0.6;">Margin: <?= number_format($pct, 1) ?>%</div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-5">No product-wise profit metrics synchronized.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
