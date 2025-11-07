<?php
// Error Handler untuk production
function handleError($errno, $errstr, $errfile, $errline) {
    // Jangan tampilkan error di production
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo "Error: [$errno] $errstr in $errfile on line $errline";
    }
    
    // Log error
    error_log("[$errno] $errstr in $errfile on line $errline");
    
    // Return true untuk mencegah PHP default error handler
    return true;
}

// Set error handler
set_error_handler("handleError");

// Exception handler
function handleException($exception) {
    // Log exception
    error_log("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    
    // Tampilkan error page yang user-friendly
    if (!headers_sent()) {
        http_response_code(500);
    }
    
    // Simpan error message untuk ditampilkan
    $_SESSION['last_error'] = [
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ];
    
    // Redirect ke error page atau tampilkan error sederhana
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo "<h1>Error</h1>";
        echo "<p>" . htmlspecialchars($exception->getMessage()) . "</p>";
        echo "<p>File: " . htmlspecialchars($exception->getFile()) . " Line: " . $exception->getLine() . "</p>";
    } else {
        echo "<h1>Terjadi Kesalahan</h1>";
        echo "<p>Maaf, terjadi kesalahan pada sistem. Silakan coba lagi nanti.</p>";
    }
}

// Set exception handler
set_exception_handler("handleException");

// Shutdown function untuk menangkap fatal errors
function shutdownHandler() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal Error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        
        if (!headers_sent()) {
            http_response_code(500);
        }
        
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            echo "<h1>Fatal Error</h1>";
            echo "<p>" . htmlspecialchars($error['message']) . "</p>";
            echo "<p>File: " . htmlspecialchars($error['file']) . " Line: " . $error['line'] . "</p>";
        } else {
            echo "<h1>Terjadi Kesalahan</h1>";
            echo "<p>Maaf, terjadi kesalahan pada sistem. Silakan coba lagi nanti.</p>";
        }
    }
}

register_shutdown_function("shutdownHandler");
?>