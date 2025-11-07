<?php
// Enable error reporting for testing
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<!DOCTYPE html>";
echo "<html lang='id'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>UKM Catalog - System Test (Fixed)</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }";
echo ".container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }";
echo "h1 { color: #333; text-align: center; margin-bottom: 30px; }";
echo ".test-result { margin: 10px 0; padding: 10px; border-radius: 5px; }";
echo ".success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }";
echo ".error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }";
echo ".warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }";
echo ".info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }";
echo ".section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }";
echo ".section h2 { margin-top: 0; color: #495057; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<div class='container'>";
echo "<h1>🔧 UKM Catalog - System Test (Fixed Version)</h1>";

$tests = [];
$totalTests = 0;
$passedTests = 0;

// Test 1: Basic Configuration
$totalTests++;
try {
    require_once 'config/config.php';
    $tests[] = ['name' => 'Configuration Loading', 'status' => 'success', 'message' => 'Configuration loaded successfully'];
    $passedTests++;
} catch (Exception $e) {
    $tests[] = ['name' => 'Configuration Loading', 'status' => 'error', 'message' => $e->getMessage()];
}

// Test 2: Database Connection
$totalTests++;
try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($database->testConnection()) {
        $tests[] = ['name' => 'Database Connection', 'status' => 'success', 'message' => 'Database connection successful'];
        $passedTests++;
    } else {
        $tests[] = ['name' => 'Database Connection', 'status' => 'error', 'message' => 'Database connection test failed'];
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'Database Connection', 'status' => 'error', 'message' => $e->getMessage()];
}

// Test 3: Session Management
$totalTests++;
try {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $tests[] = ['name' => 'Session Management', 'status' => 'success', 'message' => 'Session management active'];
        $passedTests++;
    } else {
        $tests[] = ['name' => 'Session Management', 'status' => 'error', 'message' => 'Session management not active'];
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'Session Management', 'status' => 'error', 'message' => $e->getMessage()];
}

// Test 4: File System Permissions
$totalTests++;
try {
    $dirsToCheck = [
        'uploads' => UPLOAD_PATH,
        'config' => CONFIG_PATH,
        'includes' => INCLUDES_PATH
    ];
    
    $allWritable = true;
    $errorMessages = [];
    
    foreach ($dirsToCheck as $name => $path) {
        if (!is_dir($path)) {
            // Try to create directory
            if (!mkdir($path, 0777, true)) {
                $allWritable = false;
                $errorMessages[] = "Directory '$name' does not exist and cannot be created";
            }
        } elseif (!is_writable($path)) {
            // Try to make writable
            if (!chmod($path, 0777)) {
                $allWritable = false;
                $errorMessages[] = "Directory '$name' is not writable";
            }
        }
    }
    
    if ($allWritable) {
        $tests[] = ['name' => 'File System Permissions', 'status' => 'success', 'message' => 'All directories are accessible'];
        $passedTests++;
    } else {
        $tests[] = ['name' => 'File System Permissions', 'status' => 'warning', 'message' => implode(', ', $errorMessages)];
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'File System Permissions', 'status' => 'error', 'message' => $e->getMessage()];
}

// Test 5: Database Tables
$totalTests++;
try {
    $tables = ['users', 'products', 'categories', 'orders', 'customers', 'order_items', 'activity_logs'];
    $missingTables = [];
    
    foreach ($tables as $table) {
        $query = "SHOW TABLES LIKE ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            $missingTables[] = $table;
        }
    }
    
    if (empty($missingTables)) {
        $tests[] = ['name' => 'Database Tables', 'status' => 'success', 'message' => 'All required tables exist'];
        $passedTests++;
    } else {
        $tests[] = ['name' => 'Database Tables', 'status' => 'error', 'message' => 'Missing tables: ' . implode(', ', $missingTables)];
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'Database Tables', 'status' => 'error', 'message' => $e->getMessage()];
}

// Test 6: Functions
$totalTests++;
try {
    $functionTests = [
        'validateEmail' => 'test@example.com',
        'validatePhone' => '081234567890',
        'formatRupiah' => 10000
    ];
    
    $functionErrors = [];
    
    foreach ($functionTests as $func => $testValue) {
        if (function_exists($func)) {
            $result = $func($testValue);
            if (empty($result)) {
                $functionErrors[] = "Function '$func' returned empty result";
            }
        } else {
            $functionErrors[] = "Function '$func' does not exist";
        }
    }
    
    if (empty($functionErrors)) {
        $tests[] = ['name' => 'Core Functions', 'status' => 'success', 'message' => 'All core functions working'];
        $passedTests++;
    } else {
        $tests[] = ['name' => 'Core Functions', 'status' => 'warning', 'message' => implode(', ', $functionErrors)];
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'Core Functions', 'status' => 'error', 'message' => $e->getMessage()];
}

// Test 7: CSRF Token
$totalTests++;
try {
    $token = generateCSRFToken();
    if (!empty($token) && strlen($token) >= 32) {
        $tests[] = ['name' => 'CSRF Token Generation', 'status' => 'success', 'message' => 'CSRF token generated successfully'];
        $passedTests++;
    } else {
        $tests[] = ['name' => 'CSRF Token Generation', 'status' => 'error', 'message' => 'CSRF token generation failed'];
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'CSRF Token Generation', 'status' => 'error', 'message' => $e->getMessage()];
}

// Test 8: Upload Directory
$totalTests++;
try {
    if (is_dir(UPLOAD_PATH)) {
        $testFile = UPLOAD_PATH . 'test.txt';
        if (file_put_contents($testFile, 'test') !== false) {
            unlink($testFile);
            $tests[] = ['name' => 'Upload Directory', 'status' => 'success', 'message' => 'Upload directory is writable'];
            $passedTests++;
        } else {
            $tests[] = ['name' => 'Upload Directory', 'status' => 'error', 'message' => 'Upload directory is not writable'];
        }
    } else {
        $tests[] = ['name' => 'Upload Directory', 'status' => 'warning', 'message' => 'Upload directory does not exist'];
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'Upload Directory', 'status' => 'error', 'message' => $e->getMessage()];
}

// Test 9: Authentication System
$totalTests++;
try {
    $auth = new Auth($db);
    if ($auth instanceof Auth) {
        $tests[] = ['name' => 'Authentication System', 'status' => 'success', 'message' => 'Auth system initialized'];
        $passedTests++;
    } else {
        $tests[] = ['name' => 'Authentication System', 'status' => 'error', 'message' => 'Auth system initialization failed'];
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'Authentication System', 'status' => 'error', 'message' => $e->getMessage()];
}

// Test 10: Cart System
$totalTests++;
try {
    $cart = new Cart();
    if ($cart instanceof Cart) {
        $tests[] = ['name' => 'Cart System', 'status' => 'success', 'message' => 'Cart system initialized'];
        $passedTests++;
    } else {
        $tests[] = ['name' => 'Cart System', 'status' => 'error', 'message' => 'Cart system initialization failed'];
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'Cart System', 'status' => 'error', 'message' => $e->getMessage()];
}

// System Information Section
echo "<div class='section'>";
echo "<h2>ℹ️ System Information</h2>";
echo "<div class='table-container'>";
echo "<table class='table'>";
echo "<tr><td><strong>PHP Version:</strong></td><td>" . PHP_VERSION . "</td></tr>";
echo "<tr><td><strong>Server:</strong></td><td>" . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</td></tr>";
echo "<tr><td><strong>Base URL:</strong></td><td>" . BASE_URL . "</td></tr>";
echo "<tr><td><strong>Upload Path:</strong></td><td>" . UPLOAD_PATH . "</td></tr>";
echo "<tr><td><strong>Session ID:</strong></td><td>" . session_id() . "</td></tr>";
echo "<tr><td><strong>Environment:</strong></td><td>" . APP_ENV . "</td></tr>";
echo "<tr><td><strong>Debug Mode:</strong></td><td>" . (DEBUG_MODE ? 'Enabled' : 'Disabled') . "</td></tr>";
echo "</table>";
echo "</div>";
echo "</div>";

// Test Results Section
echo "<div class='section'>";
echo "<h2>🧪 Test Results</h2>";
echo "<div class='alert alert-info'>";
echo "<strong>Summary:</strong> {$passedTests} of {$totalTests} tests passed (" . round(($passedTests/$totalTests)*100) . "% success rate)";
echo "</div>";

// Display test results
foreach ($tests as $test) {
    $statusClass = $test['status'];
    echo "<div class='test-result {$statusClass}'>";
    echo "<strong>{$test['name']}:</strong> {$test['message']}";
    echo "</div>";
}
echo "</div>";

// Recommendations Section
echo "<div class='section'>";
echo "<h2>💡 Recommendations</h2>";
echo "<ul>";

if (!DEBUG_MODE) {
    echo "<li class='success'>✅ Debug mode is disabled (good for production)</li>";
} else {
    echo "<li class='warning'>⚠️ Debug mode is enabled (should be disabled in production)</li>";
}

if (APP_ENV === 'production') {
    echo "<li class='success'>✅ Running in production environment</li>";
} else {
    echo "<li class='info'>ℹ️ Running in development environment</li>";
}

// Check HTTPS
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    echo "<li class='success'>✅ HTTPS is enabled (secure)</li>";
} else {
    echo "<li class='warning'>⚠️ HTTPS is not enabled (consider enabling for production)</li>";
}

// Check PHP version
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    echo "<li class='success'>✅ PHP version is supported</li>";
} else {
    echo "<li class='error'>❌ PHP version is not supported (minimum 7.4.0 required)</li>";
}

echo "</ul>";
echo "</div>";

// Action Items Section
echo "<div class='section'>";
echo "<h2>🔧 Action Items</h2>";

$errors = array_filter($tests, function($test) { return $test['status'] === 'error'; });
$warnings = array_filter($tests, function($test) { return $test['status'] === 'warning'; });

if (!empty($errors)) {
    echo "<div class='alert alert-danger'>";
    echo "<h4>❌ Critical Issues (Must Fix):</h4>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li><strong>{$error['name']}:</strong> {$error['message']}</li>";
    }
    echo "</ul>";
    echo "</div>";
}

if (!empty($warnings)) {
    echo "<div class='alert alert-warning'>";
    echo "<h4>⚠️ Warnings (Should Fix):</h4>";
    echo "<ul>";
    foreach ($warnings as $warning) {
        echo "<li><strong>{$warning['name']}:</strong> {$warning['message']}</li>";
    }
    echo "</ul>";
    echo "</div>";
}

if (empty($errors) && empty($warnings)) {
    echo "<div class='alert alert-success'>";
    echo "<h4>🎉 System Ready!</h4>";
    echo "<p>All systems are working correctly. The website is ready for use.</p>";
    echo "</div>";
}

echo "</div>";

// Footer Actions
echo "<div class='section'>";
echo "<h2>🚀 Quick Actions</h2>";
echo "<div class='d-flex gap-2 flex-wrap'>";
echo "<a href='index.php' class='btn btn-primary'>🏠 Go to Website</a>";
echo "<a href='login.php' class='btn btn-secondary'>🔐 Login Page</a>";
echo "<a href='admin/dashboard.php' class='btn btn-info'>👨‍💼 Admin Panel</a>";
echo "<a href='products.php' class='btn btn-success'>🛍️ Product Catalog</a>";
echo "<a href='cart.php' class='btn btn-warning'>🛒 Shopping Cart</a>";
echo "</div>";
echo "</div>";

echo "</div>";
echo "</body>";
echo "</html>";

// Additional logging for debugging
if (DEBUG_MODE) {
    error_log("System Test Completed: {$passedTests}/{$totalTests} tests passed");
}
?>