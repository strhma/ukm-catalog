<?php
require_once 'config/config.php';

echo "<h1>UKM Catalog - System Test</h1>";

// Test database connection
try {
    $db = (new Database())->getConnection();
    echo "<p style='color: green;'>✅ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

// Test session
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p style='color: green;'>✅ Session management active</p>";
} else {
    echo "<p style='color: red;'>❌ Session management not active</p>";
}

// Test upload directory
if (is_dir(UPLOAD_PATH) && is_writable(UPLOAD_PATH)) {
    echo "<p style='color: green;'>✅ Upload directory is writable</p>";
} else {
    echo "<p style='color: red;'>❌ Upload directory is not writable</p>";
}

// Test database tables
try {
    $tables = ['users', 'products', 'categories', 'orders', 'customers'];
    foreach ($tables as $table) {
        $query = "SELECT 1 FROM {$table} LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        echo "<p style='color: green;'>✅ Table '{$table}' exists</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Table error: " . $e->getMessage() . "</p>";
}

// Test CSRF token generation
$token = generateCSRFToken();
if (!empty($token)) {
    echo "<p style='color: green;'>✅ CSRF token generation working</p>";
} else {
    echo "<p style='color: red;'>❌ CSRF token generation failed</p>";
}

// Test functions
try {
    $testEmail = "test@example.com";
    if (validateEmail($testEmail)) {
        echo "<p style='color: green;'>✅ Email validation working</p>";
    } else {
        echo "<p style='color: red;'>❌ Email validation failed</p>";
    }
    
    $testPhone = "081234567890";
    if (validatePhone($testPhone)) {
        echo "<p style='color: green;'>✅ Phone validation working</p>";
    } else {
        echo "<p style='color: red;'>❌ Phone validation failed</p>";
    }
    
    $testPrice = 10000;
    $formatted = formatRupiah($testPrice);
    if (strpos($formatted, 'Rp') !== false) {
        echo "<p style='color: green;'>✅ Currency formatting working</p>";
    } else {
        echo "<p style='color: red;'>❌ Currency formatting failed</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Function test error: " . $e->getMessage() . "</p>";
}

echo "<h2>System Information</h2>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Base URL: " . BASE_URL . "</p>";
echo "<p>Upload Path: " . UPLOAD_PATH . "</p>";
echo "<p>Session ID: " . session_id() . "</p>";

echo "<h2>Recommendations</h2>";
echo "<ul>";
echo "<li>Ensure HTTPS is enabled for production</li>";
echo "<li>Set proper file permissions (644 for files, 755 for directories)</li>";
echo "<li>Configure php.ini for production (error_reporting, display_errors)</li>";
echo "<li>Set up regular database backups</li>";
echo "<li>Monitor error logs regularly</li>";
echo "</ul>";
?>