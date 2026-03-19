<?php

/**
 * Stock Management - Add / Increase Stock
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

$page_title = "Add Stock Inventory";
$page_icon = "fa-plus-circle";
$page_description = "Record a manual stock intake to increase current product quantity";

$error = '';

// Get pre-selected product
$selected_product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch all active products for the dropdown
$products = [];
$products_query = "SELECT id, name, sku FROM products WHERE status = 'active' ORDER BY name ASC";
$products_result = mysqli_query($conn, $products_query);
if ($products_result) {
    while ($row = mysqli_fetch_assoc($products_result)) {
        $products[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $note = mysqli_real_escape_string($conn, $_POST['note'] ?? '');
    $user_id = $_SESSION['user_id'] ?? null;

    // Validation
    if (!$product_id || $quantity <= 0) {
        $error = "Please select a product and enter a quantity greater than zero.";
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);

        try {
            // 1. Update or Insert into product_stock
            $check_query = "SELECT id FROM product_stock WHERE product_id = $product_id";
            $check_result = mysqli_query($conn, $check_query);
            $existing_stock = $check_result ? mysqli_fetch_assoc($check_result) : null;

            if ($existing_stock) {
                $update_query = "UPDATE product_stock SET quantity = quantity + $quantity, updated_at = NOW() WHERE product_id = $product_id";
                mysqli_query($conn, $update_query);
            } else {
                $insert_query = "INSERT INTO product_stock (product_id, quantity, created_at) VALUES ($product_id, $quantity, NOW())";
                mysqli_query($conn, $insert_query);
            }

            // 2. Insert into product_transactions
            $user_id_val = $user_id ? $user_id : 'NULL';
            $note_val = $note ? "'$note'" : 'NULL';
            $trans_query = "INSERT INTO product_transactions (product_id, type, quantity, reference_type, note, created_by, created_at) 
                           VALUES ($product_id, 'IN', $quantity, 'manual', $note_val, $user_id_val, NOW())";
            mysqli_query($conn, $trans_query);

            mysqli_commit($conn);

            set_flash_message('success', 'Stock updated successfully. Transaction recorded.');
            redirect('admin/stock/index.php');
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Failed to update stock: " . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
    .form-card {
        background: white;
        border-radius: 24px;
        border: 1px solid #f1f5f9;
        box-shadow: 0 10px 40px -15px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        max-width: 650px;
        margin: 0 auto;
    }

    .form-header {
        padding: 2rem;
        background: #fafbff;
        border-bottom: 1px solid #f1f5f9;
    }

    .form-body {
        padding: 2.5rem;
    }

    .form-label {
        font-size: 0.85rem;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        margin-bottom: 0.6rem;
        display: block;
    }

    .form-control {
        height: 52px;
        border-radius: 14px;
        border: 1.5px solid #e2e8f0;
        font-size: 0.95rem;
        transition: all 0.2s;
        padding: 0 1.2rem;
    }

    .form-control:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }

    .btn-submit {
        height: 52px;
        background: #2563eb;
        color: white;
        border: none;
        font-weight: 700;
        border-radius: 14px;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
    }

    .btn-submit:hover {
        background: #1d4ed8;
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -10px #2563eb;
    }

    .back-btn {
        color: #64748b;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: white;
        padding: 0.5rem 1.2rem;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        transition: all 0.2s;
    }

    .back-btn:hover {
        color: #2563eb;
        border-color: #2563eb;
        background: #f8fafc;
    }
</style>

<div class="px-4 py-4">
    <div class="mb-4">
        <a href="index.php" class="back-btn">
            <i class="fa-solid fa-arrow-left small"></i>
            <span>Back to Stock List</span>
        </a>
    </div>

    <div class="form-card">
        <div class="form-header d-flex align-items-center gap-3">
            <div class="p-3 bg-blue-50 text-blue-600 rounded-4 border border-blue-100">
                <i class="fa-solid fa-cart-arrow-down fa-lg"></i>
            </div>
            <div>
                <h1 class="h5 fw-bold text-slate-900 mb-0"><?= $page_title ?></h1>
                <p class="text-slate-500 small mb-0"><?= $page_description ?></p>
            </div>
        </div>

        <div class="form-body">
            <?php if ($error): ?>
                <div class="alert alert-danger border-0 rounded-4 p-3 mb-4 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span class="fw-medium font-size-14"><?= $error ?></span>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-4">
                    <label class="form-label">Select Product</label>
                    <select name="product_id" class="form-control w-100" required>
                        <option value="">-- Choose a Product --</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= $product['id'] ?>" <?= $selected_product_id == $product['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($product['name']) ?> (<?= htmlspecialchars($product['sku']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label">Intake Quantity</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 ps-3 rounded-start-4"><i class="fa-solid fa-plus text-slate-400"></i></span>
                        <input type="number" name="quantity" class="form-control border-start-0 ps-1" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" placeholder="Enter quantity to add..." min="1" required>
                    </div>
                    <small class="text-slate-400 mt-2 d-block px-1"><i class="fa-solid fa-circle-info me-1"></i> Current status will be increased by this amount</small>
                </div>

                <div class="mb-5">
                    <label class="form-label">Reference / Note <span class="text-slate-400 italic font-normal">(Optional)</span></label>
                    <textarea name="note" class="form-control h-auto py-3" rows="3" placeholder="Additional details about this manual intake..."></textarea>
                </div>

                <button type="submit" class="btn-submit w-100 mb-2">
                    <i class="fa-solid fa-check-double"></i>
                    <span>Confirm Stock Update</span>
                </button>
                <div class="text-center">
                    <small class="text-slate-400">System will automatically document this transaction for auditing</small>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.title = "<?= $page_title ?> - Inventory System";
</script>

<?php include '../../includes/footer.php'; ?>