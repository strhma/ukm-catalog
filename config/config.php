<?php
// Start session with enhanced security
if (session_status() === PHP_SESSION_NONE) {
    // Configure session security
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', '3600'); // 1 hour
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    
    // Set secure session name
    session_name('UKMSESSID_' . substr(md5(__DIR__), 0, 8));
    
    session_start();
}

// Define application constants
define('APP_NAME', 'UKM Catalog');
define('APP_VERSION', '1.0.0');
define('APP_ENV', $_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1' ? 'development' : 'production');

// Debug mode - only for development
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    define('DEBUG_MODE', true);
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    define('DEBUG_MODE', false);
}

// Base URL configuration
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = dirname($_SERVER['SCRIPT_NAME']);
$path = $path === '/' ? '' : $path;

define('BASE_URL', $protocol . $host . $path . '/');
define('CURRENT_URL', $protocol . $host . $_SERVER['REQUEST_URI']);

// Path configurations
define('ROOT_PATH', realpath(__DIR__ . '/../') . '/');
define('CONFIG_PATH', ROOT_PATH . 'config/');
define('INCLUDES_PATH', ROOT_PATH . 'includes/');
define('ASSETS_PATH', ROOT_PATH . 'assets/');
define('UPLOAD_PATH', ROOT_PATH . 'uploads/');
define('ADMIN_PATH', ROOT_PATH . 'admin/');
define('API_PATH', ROOT_PATH . 'api/');

// URL configurations
define('ASSETS_URL', BASE_URL . 'assets/');
define('UPLOAD_URL', BASE_URL . 'uploads/');
define('ADMIN_URL', BASE_URL . 'admin/');
define('API_URL', BASE_URL . 'api/');

// Security configurations
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 3600); // 1 hour
define('REMEMBER_ME_LIFETIME', 2592000); // 30 days

define('PASSWORD_MIN_LENGTH', 6);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes

// Upload configurations
define('UPLOAD_MAX_SIZE', 2097152); // 2MB
define('UPLOAD_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('UPLOAD_MAX_WIDTH', 2000);
define('UPLOAD_MAX_HEIGHT', 2000);

// Pagination configurations
define('DEFAULT_ITEMS_PER_PAGE', 12);
define('ADMIN_ITEMS_PER_PAGE', 20);

// Cache configurations
define('CACHE_ENABLED', APP_ENV === 'production');
define('CACHE_LIFETIME', 3600); // 1 hour

// Email configurations (placeholder)
define('MAIL_FROM_EMAIL', 'noreply@ukmcatalog.com');
define('MAIL_FROM_NAME', 'UKM Catalog');
define('MAIL_ADMIN_EMAIL', 'admin@ukmcatalog.com');

// Payment configurations (placeholder)
define('PAYMENT_GATEWAY_ENABLED', false);
define('PAYMENT_CURRENCY', 'IDR');

// Feature flags
define('FEATURE_REGISTRATION_ENABLED', true);
define('FEATURE_CART_ENABLED', true);
define('FEATURE_REVIEWS_ENABLED', false);
define('FEATURE_WISHLIST_ENABLED', false);

// RajaOngkir Configuration
define('RAJAONGKIR_API_KEY', 'YOUR_API_KEY_HERE'); // Masukkan API Key RajaOngkir di sini
define('RAJAONGKIR_BASE_URL', 'https://api.rajaongkir.com/starter');
define('ORIGIN_CITY_ID', '152'); // Default: Jakarta Pusat (Sesuaikan dengan toko Anda)

// Load required files
try {
    // Load database configuration
    if (file_exists(CONFIG_PATH . 'database_fixed.php')) {
        require_once CONFIG_PATH . 'database_fixed.php';
    } else {
        require_once CONFIG_PATH . 'database.php';
    }
    
    // Load functions
    require_once INCLUDES_PATH . 'functions.php';
    
    // Load enhanced auth if available
    if (file_exists(INCLUDES_PATH . 'auth.php')) {
        require_once INCLUDES_PATH . 'auth.php';
    } else {
        require_once INCLUDES_PATH . 'auth.php';
    }
    
    // Load cart
    require_once INCLUDES_PATH . 'cart.php';
    
    // Load error handler
    if (file_exists(INCLUDES_PATH . 'error_handler.php')) {
        require_once INCLUDES_PATH . 'error_handler.php';
    }
    
    // Load enhanced upload if available
    if (file_exists(INCLUDES_PATH . 'upload_fixed.php')) {
        require_once INCLUDES_PATH . 'upload_fixed.php';
    }
    
    // Initialize database
    $database = new Database();
    $db = $database->getConnection();
    
    // Test database connection
    if (!$database->testConnection()) {
        throw new Exception('Database connection failed');
    }
    
} catch (Exception $e) {
    // Log error
    error_log("Configuration error: " . $e->getMessage());
    
    // Store error for display
    $_SESSION['last_error'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    
    // Redirect to error page
    if (!headers_sent()) {
        header('Location: ' . BASE_URL . 'error.php');
        exit();
    } else {
        die('Configuration error: ' . $e->getMessage());
    }
}

// Security headers for production
if (APP_ENV === 'production') {
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Force HTTPS
        if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
            header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
            exit();
        }
    }
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Initialize auth
$auth = new Auth($db);

// Check for maintenance mode
if (file_exists(ROOT_PATH . 'maintenance.php') && APP_ENV === 'production') {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        include ROOT_PATH . 'maintenance.php';
        exit();
    }
}
?>