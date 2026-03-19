<?php
/**
 * Product Management - Edit Product
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';

// Access Control
authorize([ROLE_ADMIN]);

// Check for ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('error', 'Invalid product ID.');
    redirect('admin/products/index.php');
}

$product_id = (int)$_GET['id'];

// Fetch product data
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        set_flash_message('error', 'Product not found.');
        redirect('admin/products/index.php');
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$page_title = "Edit Product: " . htmlspecialchars($product['name']);
$page_icon = "fa-pen-to-square";
$page_description = "Update the details for " . htmlspecialchars($product['name']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $sku = sanitize($_POST['sku']);
    $cost_price = (float)$_POST['cost_price'];
    $selling_price = (float)$_POST['selling_price'];
    $status = sanitize($_POST['status']);

    // Validation
    if (empty($name) || empty($sku) || empty($cost_price) || empty($selling_price)) {
        $error = "Please fill in all required fields.";
    } elseif ($cost_price < 0 || $selling_price < 0) {
        $error = "Prices cannot be negative.";
    } elseif ($cost_price >= $selling_price) {
        $error = "Cost price must be less than the selling price.";
    } else {
        // Check if SKU taken by ANOTHER product
        $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
        $stmt->execute([$sku, $product_id]);
        if ($stmt->fetch()) {
            $error = "This SKU is already assigned to another product.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE products SET name = ?, sku = ?, cost_price = ?, selling_price = ?, status = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$name, $sku, $cost_price, $selling_price, $status, $product_id])) {
                    set_flash_message('success', 'Product updated successfully.');
                    redirect('admin/products/index.php');
                } else {
                    $error = "Failed to update product. Please try again.";
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
    :root {
        --input-height: 44px;
        --border-light: 1px solid #e2e8f0;
        --focus-ring: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .form-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 1.5rem;
    }

    .page-header-minimal {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2rem;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: #64748b;
        font-size: 0.9rem;
        font-weight: 500;
        text-decoration: none;
        padding: 0.5rem 1rem;
        border-radius: 10px;
        background: white;
        border: var(--border-light);
        transition: all 0.2s;
    }

    .back-link:hover {
        background: #f8fafc;
        color: #2563eb;
        border-color: #2563eb;
    }

    .form-card {
        background: white;
        border-radius: 20px;
        border: var(--border-light);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
        overflow: hidden;
    }

    .form-header {
        padding: 1.5rem 2rem;
        border-bottom: var(--border-light);
        background: #fafbff;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .header-icon {
        width: 48px;
        height: 48px;
        background: white;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: #2563eb;
        border: var(--border-light);
    }

    .header-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0.25rem;
    }

    .header-subtitle {
        font-size: 0.85rem;
        color: #64748b;
    }

    .form-body {
        padding: 2rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: #475569;
        margin-bottom: 0.5rem;
    }

    .required:after {
        content: '*';
        color: #ef4444;
        margin-left: 0.25rem;
    }

    .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .input-icon-left {
        position: absolute;
        left: 1rem;
        color: #94a3b8;
        font-size: 0.9rem;
        pointer-events: none;
    }

    .form-control {
        width: 100%;
        height: var(--input-height);
        padding: 0 1rem 0 2.5rem;
        background: white;
        border: var(--border-light);
        border-radius: 12px;
        font-size: 0.9rem;
        color: #1e293b;
        transition: all 0.2s;
    }

    .form-control:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: var(--focus-ring);
    }

    .btn-submit {
        height: var(--input-height);
        padding: 0 2rem;
        background: #2563eb;
        border: none;
        border-radius: 12px;
        font-size: 0.9rem;
        font-weight: 600;
        color: white;
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-submit:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
    }

    .btn-cancel {
        height: var(--input-height);
        padding: 0 1.5rem;
        background: white;
        border: var(--border-light);
        border-radius: 12px;
        font-size: 0.9rem;
        font-weight: 600;
        color: #64748b;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        transition: all 0.2s;
    }

    .alert-error {
        background: #fef2f2;
        border: 1px solid #fee2e2;
        color: #991b1b;
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .product-preview {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: #f8fafc;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        border: 1px solid #f1f5f9;
    }

    .preview-icon {
        width: 40px;
        height: 40px;
        background: #2563eb10;
        color: #2563eb;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        font-size: 1.2rem;
    }
</style>

<div class="form-container">
    <div class="page-header-minimal">
        <a href="index.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to Products</span>
        </a>
    </div>

    <div class="form-card">
        <div class="form-header">
            <div class="header-icon">
                <i class="fa-solid fa-pen-to-square"></i>
            </div>
            <div>
                <h1 class="header-title"><?= $page_title ?></h1>
                <p class="header-subtitle"><?= $page_description ?></p>
            </div>
        </div>

        <div class="form-body">
            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>

            <div class="product-preview">
                <div class="preview-icon"><i class="fa-solid fa-box"></i></div>
                <div>
                    <div class="fw-bold text-slate-900"><?= htmlspecialchars($product['name']) ?></div>
                    <div class="small text-slate-500">SKU: <?= htmlspecialchars($product['sku']) ?> • Added on <?= date('M d, Y', strtotime($product['created_at'])) ?></div>
                </div>
            </div>

            <form method="POST">
                <div class="form-group mb-4">
                    <label class="form-label required">Product Name</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-tag input-icon-left"></i>
                        <input type="text" name="name" class="form-control" placeholder="Enter product name" value="<?= htmlspecialchars($product['name']) ?>" required>
                    </div>
                </div>

                <div class="form-group mb-4">
                    <label class="form-label required">SKU (Stock Keeping Unit)</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-barcode input-icon-left"></i>
                        <input type="text" name="sku" class="form-control" placeholder="e.g. PRD-001" value="<?= htmlspecialchars($product['sku']) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Cost Price</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-hand-holding-dollar input-icon-left"></i>
                            <input type="number" step="0.01" name="cost_price" class="form-control" placeholder="0.00" value="<?= htmlspecialchars($product['cost_price']) ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Selling Price</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-sack-dollar input-icon-left"></i>
                            <input type="number" step="0.01" name="selling_price" class="form-control" placeholder="0.00" value="<?= htmlspecialchars($product['selling_price']) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-4">
                    <label class="form-label required">Status</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-circle-info input-icon-left"></i>
                        <select name="status" class="form-control" style="padding-left: 2.5rem;" required>
                            <option value="active" <?= $product['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $product['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="index.php" class="btn-cancel">Cancel</a>
                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-check"></i>
                        Update Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.title = "<?= $page_title ?> - <?= APP_NAME ?>";
</script>

<?php include '../../includes/footer.php'; ?>
