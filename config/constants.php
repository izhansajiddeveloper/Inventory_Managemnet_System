<?php

/**
 * System Constants
 */

// Prevent multiple definitions
if (!defined('APP_NAME')) {
    // Application Settings
    define('APP_NAME', 'Inventory Management System');
    define('APP_VERSION', '1.0.0');
    define('APP_CURRENCY', '$');
    define('APP_DATE_FORMAT', 'Y-m-d H:i:s');
    define('APP_TIMEZONE', 'America/New_York');

    // Pagination Settings
    define('ITEMS_PER_PAGE', 20);
    define('MAX_RECENT_ITEMS', 10);

    // Role Constants (Matching the database IDs)
    define('ROLE_ADMIN', 1);
    define('ROLE_DISTRIBUTOR', 2);
    define('ROLE_STAFF', 3);
    define('ROLE_CUSTOMER', 4);

    // User Status
    define('USER_ACTIVE', 'active');
    define('USER_INACTIVE', 'inactive');

    // Order Statuses
    define('ORDER_PENDING', 'pending');
    define('ORDER_COMPLETED', 'completed');
    define('ORDER_CANCELLED', 'cancelled');
    define('ORDER_PROCESSING', 'processing');
    define('ORDER_SHIPPED', 'shipped');
    define('ORDER_DELIVERED', 'delivered');

    // Payment Statuses
    define('PAYMENT_PAID', 'paid');
    define('PAYMENT_UNPAID', 'unpaid');
    define('PAYMENT_PARTIAL', 'partial');
    define('PAYMENT_REFUNDED', 'refunded');
    define('PAYMENT_PENDING', 'pending');

    // Payment Methods
    define('PAYMENT_METHOD_CASH', 'cash');
    define('PAYMENT_METHOD_ONLINE', 'online');
    define('PAYMENT_METHOD_BANK', 'bank');
    define('PAYMENT_METHOD_CARD', 'card');

    // Transaction Types
    define('TRANS_IN', 'IN');
    define('TRANS_OUT', 'OUT');
    define('TRANS_ADJUSTMENT', 'ADJUSTMENT');
    define('TRANS_RETURN', 'RETURN');

    // Transaction Reference Types
    define('REF_TYPE_ORDER', 'order');
    define('REF_TYPE_PURCHASE', 'purchase');
    define('REF_TYPE_MANUAL', 'manual');
    define('REF_TYPE_RETURN', 'return');

    // Order Types
    define('ORDER_TYPE_ONLINE', 'online');
    define('ORDER_TYPE_SHOP', 'shop');
    define('ORDER_TYPE_DELIVERY', 'delivery');
    define('ORDER_TYPE_WHOLESALE', 'wholesale');

    // Product Status
    define('PRODUCT_ACTIVE', 'active');
    define('PRODUCT_INACTIVE', 'inactive');
    define('PRODUCT_DISCONTINUED', 'discontinued');

    // Stock Alert Levels
    define('STOCK_CRITICAL', 5);
    define('STOCK_LOW', 10);
    define('STOCK_MEDIUM', 25);
    define('STOCK_HIGH', 50);

    // Report Types
    define('REPORT_DAILY', 'daily');
    define('REPORT_WEEKLY', 'weekly');
    define('REPORT_MONTHLY', 'monthly');
    define('REPORT_YEARLY', 'yearly');
    define('REPORT_CUSTOM', 'custom');

    // Session Keys
    define('SESSION_USER_ID', 'user_id');
    define('SESSION_USER_NAME', 'user_name');
    define('SESSION_USER_ROLE', 'user_role');
    define('SESSION_USER_EMAIL', 'user_email');
    define('SESSION_FLASH', 'flash_messages');

    // Cookie Names
    define('COOKIE_REMEMBER', 'remember_token');
    define('COOKIE_EXPIRY', 2592000); // 30 days in seconds

    // File Upload
    define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
    define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    define('UPLOAD_PATH', __DIR__ . '/../uploads/');

    // Cache Settings
    define('CACHE_ENABLED', false);
    define('CACHE_DIR', __DIR__ . '/../cache/');
    define('CACHE_LIFETIME', 3600); // 1 hour in seconds

    // Logging
    define('LOG_ENABLED', true);
    define('LOG_PATH', __DIR__ . '/../logs/');
    define('LOG_LEVEL', 'debug'); // debug, info, warning, error

    // Security
    define('BCRYPT_ROUNDS', 12);
    define('SESSION_TIMEOUT', 7200); // 2 hours in seconds
    define('MAX_LOGIN_ATTEMPTS', 5);
    define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes in seconds

    // API Settings (if applicable)
    define('API_ENABLED', false);
    define('API_RATE_LIMIT', 60); // requests per minute
    define('API_VERSION', 'v1');

    // Notification Settings
    define('NOTIFY_LOW_STOCK', true);
    define('NOTIFY_NEW_ORDER', true);
    define('NOTIFY_PAYMENT_RECEIVED', true);
    define('LOW_STOCK_THRESHOLD', 10);

    // Tax Settings
    define('TAX_ENABLED', false);
    define('TAX_RATE', 0.00); // 0% default
    define('TAX_INCLUSIVE', false); // false = add tax to total

    // Discount Settings
    define('MAX_DISCOUNT_PERCENT', 100);
    define('MIN_DISCOUNT_AMOUNT', 0);
    define('ALLOW_COUPONS', true);

    // Dashboard Settings
    define('DASHBOARD_REFRESH_INTERVAL', 300); // 5 minutes in seconds
    define('SHOW_CHARTS', true);
    define('DEFAULT_CHART_TYPE', 'line');

    // Export Settings
    define('EXPORT_CSV_ENABLED', true);
    define('EXPORT_PDF_ENABLED', true);
    define('EXPORT_EXCEL_ENABLED', false);
    define('EXPORT_MAX_ROWS', 5000);

    // Backup Settings
    define('BACKUP_ENABLED', true);
    define('BACKUP_PATH', __DIR__ . '/../backups/');
    define('BACKUP_RETENTION_DAYS', 30);

    // Debug Mode (set to false in production)
    define('DEBUG_MODE', true);
    define('DISPLAY_ERRORS', true);
    define('LOG_ERRORS', true);

    // Database Connection Settings (if not in separate file)
    if (!defined('DB_HOST')) {
        define('DB_HOST', 'localhost');
        define('DB_NAME', 'inventory_management_system');
        define('DB_USER', 'root');
        define('DB_PASS', '');
        define('DB_CHARSET', 'utf8mb4');
        define('DB_PORT', 3306);
    }

    // Email Settings
    define('MAIL_ENABLED', false);
    define('MAIL_HOST', 'smtp.gmail.com');
    define('MAIL_PORT', 587);
    define('MAIL_USERNAME', '');
    define('MAIL_PASSWORD', '');
    define('MAIL_ENCRYPTION', 'tls');
    define('MAIL_FROM_ADDRESS', 'noreply@inventory.com');
    define('MAIL_FROM_NAME', 'Inventory System');

    // System Paths
    define('BASE_PATH', __DIR__ . '/../');
    define('INCLUDES_PATH', BASE_PATH . 'includes/');
    define('CORE_PATH', BASE_PATH . 'core/');
    define('ADMIN_PATH', BASE_PATH . 'admin/');
    define('ASSETS_PATH', BASE_PATH . 'assets/');
    define('UPLOADS_PATH', BASE_PATH . 'uploads/');

    // URLs (set these in your app config)
    if (!defined('BASE_URL')) {
        define('BASE_URL', 'http://localhost/inventory-management-system/');
    }
    define('ASSETS_URL', BASE_URL . 'assets/');
    define('UPLOADS_URL', BASE_URL . 'uploads/');
}
