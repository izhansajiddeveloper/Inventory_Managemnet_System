<?php

/**
 * Product Management - Add Product
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
function set_flash_message($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function redirect($url)
{
    header("Location: " . BASE_URL . $url);
    exit();
}

$page_title = "Add New Product";
$page_icon = "fa-plus-circle";
$page_description = "Create a new product entry in the inventory system";

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
    $sku = mysqli_real_escape_string($conn, $_POST['sku'] ?? '');
    $cost_price = (float)($_POST['cost_price'] ?? 0);
    $selling_price = (float)($_POST['selling_price'] ?? 0);
    $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'active');

    // Validation
    if (empty($name) || empty($sku) || empty($cost_price) || empty($selling_price)) {
        $error = "Please fill in all required fields.";
    } elseif ($cost_price < 0 || $selling_price < 0) {
        $error = "Prices cannot be negative.";
    } elseif ($cost_price >= $selling_price) {
        $error = "Cost price must be less than the selling price.";
    } else {
        // Check if SKU exists
        $check_query = "SELECT id FROM products WHERE sku = '$sku'";
        $check_result = mysqli_query($conn, $check_query);

        if ($check_result && mysqli_num_rows($check_result) > 0) {
            $error = "This SKU is already assigned to another product.";
        } else {
            // Insert product
            $insert_query = "INSERT INTO products (name, sku, cost_price, selling_price, status) 
                            VALUES ('$name', '$sku', $cost_price, $selling_price, '$status')";

            if (mysqli_query($conn, $insert_query)) {
                set_flash_message('success', 'Product added successfully.');
                redirect('admin/products/index.php');
            } else {
                $error = "Failed to add product. Please try again.";
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
        --form-spacing: 1.5rem;
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

    .form-group {
        width: 100%;
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
                <i class="fa-solid fa-box-open"></i>
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

            <form method="POST">
                <div class="form-group mb-4">
                    <label class="form-label required">Product Name</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-tag input-icon-left"></i>
                        <input type="text" name="name" class="form-control" placeholder="Enter product name" value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" required>
                    </div>
                </div>

                <div class="form-group mb-4">
                    <label class="form-label required">SKU (Stock Keeping Unit)</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-barcode input-icon-left"></i>
                        <input type="text" name="sku" class="form-control" placeholder="e.g. PRD-001" value="<?= isset($_POST['sku']) ? htmlspecialchars($_POST['sku']) : '' ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Cost Price</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-hand-holding-dollar input-icon-left"></i>
                            <input type="number" step="0.01" name="cost_price" class="form-control" placeholder="0.00" value="<?= isset($_POST['cost_price']) ? htmlspecialchars($_POST['cost_price']) : '' ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Selling Price</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-sack-dollar input-icon-left"></i>
                            <input type="number" step="0.01" name="selling_price" class="form-control" placeholder="0.00" value="<?= isset($_POST['selling_price']) ? htmlspecialchars($_POST['selling_price']) : '' ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-4">
                    <label class="form-label required">Status</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-circle-info input-icon-left"></i>
                        <select name="status" class="form-control" style="padding-left: 2.5rem;" required>
                            <option value="active" <?= (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="index.php" class="btn-cancel">Cancel</a>
                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-check"></i>
                        Save Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.title = "<?= $page_title ?> - Inventory System";
</script>

<?php include '../../includes/footer.php'; ?>