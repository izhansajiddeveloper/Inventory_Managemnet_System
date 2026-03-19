<?php

/**
 * Reports Core Functions
 * Handles all reporting and analytics functionality
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/config/db.php';

/**
 * Get sales report for a date range
 */
function getSalesReport($start_date = null, $end_date = null)
{
    global $pdo;

    if (!$start_date) {
        $start_date = date('Y-m-d', strtotime('-30 days'));
    }
    if (!$end_date) {
        $end_date = date('Y-m-d');
    }

    $sql = "
        SELECT 
            DATE(o.created_at) as date,
            COUNT(DISTINCT o.id) as order_count,
            COALESCE(SUM(o.final_amount), 0) as revenue,
            COALESCE(SUM(o.discount), 0) as total_discount,
            COALESCE(SUM((oi.price - p.cost_price) * oi.quantity), 0) as profit,
            COUNT(DISTINCT o.customer_id) as customer_count
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE DATE(o.created_at) BETWEEN :start_date AND :end_date
            AND o.status = 'completed'
        GROUP BY DATE(o.created_at)
        ORDER BY date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get product performance report
 */
function getProductPerformanceReport($limit = 10)
{
    global $pdo;

    $sql = "
        SELECT 
            p.id,
            p.name,
            p.sku,
            p.cost_price,
            p.selling_price,
            COALESCE(ps.quantity, 0) as current_stock,
            COUNT(DISTINCT oi.id) as times_ordered,
            COALESCE(SUM(oi.quantity), 0) as units_sold,
            COALESCE(SUM(oi.total), 0) as revenue,
            COALESCE(SUM((oi.price - p.cost_price) * oi.quantity), 0) as profit,
            CASE 
                WHEN COALESCE(SUM(oi.quantity), 0) > 100 THEN 'High'
                WHEN COALESCE(SUM(oi.quantity), 0) > 50 THEN 'Medium'
                ELSE 'Low'
            END as performance
        FROM products p
        LEFT JOIN product_stock ps ON p.id = ps.product_id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
        WHERE p.status = 'active'
        GROUP BY p.id
        ORDER BY profit DESC
        LIMIT :limit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get distributor performance report
 */
function getDistributorReport($limit = 10)
{
    global $pdo;

    $sql = "
        SELECT 
            d.id,
            d.company_name,
            u.name as contact_name,
            u.email,
            u.phone,
            COUNT(DISTINCT pu.id) as purchase_count,
            COALESCE(SUM(pu.total_amount), 0) as total_purchases,
            COUNT(DISTINCT pi.product_id) as products_supplied,
            COALESCE(SUM(pi.quantity), 0) as units_supplied,
            MAX(pu.created_at) as last_supply_date
        FROM distributors d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN purchases pu ON d.id = pu.distributor_id
        LEFT JOIN purchase_items pi ON pu.id = pi.purchase_id
        GROUP BY d.id
        ORDER BY total_purchases DESC
        LIMIT :limit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get inventory valuation report
 */
function getInventoryValuationReport()
{
    global $pdo;

    $sql = "
        SELECT 
            p.id,
            p.name,
            p.sku,
            p.cost_price,
            p.selling_price,
            COALESCE(ps.quantity, 0) as quantity,
            (p.cost_price * COALESCE(ps.quantity, 0)) as cost_value,
            (p.selling_price * COALESCE(ps.quantity, 0)) as selling_value,
            ((p.selling_price - p.cost_price) * COALESCE(ps.quantity, 0)) as potential_profit
        FROM products p
        LEFT JOIN product_stock ps ON p.id = ps.product_id
        WHERE p.status = 'active'
        ORDER BY cost_value DESC
    ";

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get monthly summary report
 */
function getMonthlySummary($year = null)
{
    global $pdo;

    if (!$year) {
        $year = date('Y');
    }

    $sql = "
        SELECT 
            MONTH(o.created_at) as month,
            COUNT(DISTINCT o.id) as order_count,
            COALESCE(SUM(o.final_amount), 0) as revenue,
            COALESCE(SUM(o.discount), 0) as discounts,
            COALESCE(SUM((oi.price - p.cost_price) * oi.quantity), 0) as profit,
            COUNT(DISTINCT o.customer_id) as customer_count,
            COUNT(DISTINCT oi.product_id) as products_sold
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE YEAR(o.created_at) = :year
            AND o.status = 'completed'
        GROUP BY MONTH(o.created_at)
        ORDER BY month
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':year' => $year]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get low stock report
 */
function getLowStockReport($threshold = 10)
{
    global $pdo;

    $sql = "
        SELECT 
            p.id,
            p.name,
            p.sku,
            p.selling_price,
            p.cost_price,
            COALESCE(ps.quantity, 0) as current_stock,
            CASE 
                WHEN COALESCE(ps.quantity, 0) = 0 THEN 'Out of Stock'
                WHEN COALESCE(ps.quantity, 0) < 5 THEN 'Critical'
                WHEN COALESCE(ps.quantity, 0) < :threshold THEN 'Low'
                ELSE 'Normal'
            END as stock_status,
            COALESCE((
                SELECT SUM(oi.quantity) 
                FROM order_items oi 
                JOIN orders o ON oi.order_id = o.id 
                WHERE oi.product_id = p.id 
                    AND o.status = 'completed'
                    AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ), 0) as monthly_demand
        FROM products p
        LEFT JOIN product_stock ps ON p.id = ps.product_id
        WHERE p.status = 'active'
        HAVING current_stock < :threshold OR current_stock = 0
        ORDER BY current_stock ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':threshold' => $threshold]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get payment summary report
 */
function getPaymentSummary($start_date = null, $end_date = null)
{
    global $pdo;

    if (!$start_date) {
        $start_date = date('Y-m-d', strtotime('-30 days'));
    }
    if (!$end_date) {
        $end_date = date('Y-m-d');
    }

    $sql = "
        SELECT 
            payment_method,
            COUNT(*) as payment_count,
            COALESCE(SUM(amount), 0) as total_amount,
            status,
            DATE(created_at) as payment_date
        FROM payments
        WHERE DATE(created_at) BETWEEN :start_date AND :end_date
        GROUP BY payment_method, status, DATE(created_at)
        ORDER BY payment_date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Export report to CSV
 */
function exportToCSV($data, $filename = 'report.csv')
{
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Add headers if data exists
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
    }

    // Add data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

/**
 * Get dashboard summary stats
 */
function getDashboardStats()
{
    global $pdo;

    $stats = [];

    // Today's stats
    $today = date('Y-m-d');
    $stats['today'] = $pdo->query("
        SELECT 
            COUNT(*) as orders,
            COALESCE(SUM(final_amount), 0) as revenue,
            COALESCE(SUM((oi.price - p.cost_price) * oi.quantity), 0) as profit
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE DATE(o.created_at) = '$today' AND o.status = 'completed'
    ")->fetch(PDO::FETCH_ASSOC);

    // This week
    $stats['week'] = $pdo->query("
        SELECT 
            COUNT(*) as orders,
            COALESCE(SUM(final_amount), 0) as revenue
        FROM orders
        WHERE YEARWEEK(created_at) = YEARWEEK(NOW()) 
            AND status = 'completed'
    ")->fetch(PDO::FETCH_ASSOC);

    // This month
    $stats['month'] = $pdo->query("
        SELECT 
            COUNT(*) as orders,
            COALESCE(SUM(final_amount), 0) as revenue
        FROM orders
        WHERE MONTH(created_at) = MONTH(NOW()) 
            AND YEAR(created_at) = YEAR(NOW())
            AND status = 'completed'
    ")->fetch(PDO::FETCH_ASSOC);

    return $stats;
}
