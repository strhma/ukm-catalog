<?php
require_once 'config/config.php';

// Get error details
$error = $_SESSION['last_error'] ?? [
    'message' => 'An unexpected error occurred',
    'file' => '',
    'line' => '',
    'trace' => ''
];

// Clear error from session
unset($_SESSION['last_error']);

$pageTitle = 'Error';
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="form-container text-center">
        <div style="font-size: 4rem; margin-bottom: 1rem;">😔</div>
        <h1>Terjadi Kesalahan</h1>
        <p class="text-muted mb-4">Maaf, terjadi kesalahan pada sistem.</p>
        
        <div class="alert alert-danger text-left">
            <h4>Detail Error:</h4>
            <p><strong>Pesan:</strong> <?php echo htmlspecialchars($error['message']); ?></p>
            
            <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
                <p><strong>File:</strong> <?php echo htmlspecialchars($error['file']); ?></p>
                <p><strong>Line:</strong> <?php echo htmlspecialchars($error['line']); ?></p>
                
                <?php if (!empty($error['trace'])): ?>
                    <details>
                        <summary>Stack Trace</summary>
                        <pre style="background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 12px; overflow-x: auto;"><?php echo htmlspecialchars($error['trace']); ?></pre>
                    </details>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div class="mt-4">
            <h4>Apa yang bisa Anda lakukan?</h4>
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">🔄 Coba Lagi</h5>
                            <p class="card-text">Kembali ke halaman sebelumnya dan coba lagi.</p>
                            <button onclick="history.back()" class="btn btn-primary">Kembali</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">🏠 Ke Beranda</h5>
                            <p class="card-text">Kembali ke halaman utama website.</p>
                            <a href="<?php echo BASE_URL; ?>" class="btn btn-success">Beranda</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">📞 Hubungi Support</h5>
                            <p class="card-text">Laporkan masalah ini kepada tim support.</p>
                            <a href="mailto:support@ukmcatalog.com" class="btn btn-info">Email Support</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">🔍 Cek Status</h5>
                            <p class="card-text">Periksa status sistem dan layanan.</p>
                            <a href="status.php" class="btn btn-warning">Status Sistem</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
            <div class="mt-4">
                <h4>Debug Information:</h4>
                <div class="table-container">
                    <table class="table">
                        <tr>
                            <td><strong>Request Method:</strong></td>
                            <td><?php echo htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? 'Unknown'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Request URI:</strong></td>
                            <td><?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'Unknown'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>User Agent:</strong></td>
                            <td><?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>IP Address:</strong></td>
                            <td><?php echo htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Unknown'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Timestamp:</strong></td>
                            <td><?php echo date('Y-m-d H:i:s'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <p class="text-muted">
                <small>
                    Error ID: ERR-<?php echo date('YmdHis'); ?><br>
                    Jika masalah berlanjut, silakan hubungi support dengan menyertakan Error ID di atas.
                </small>
            </p>
        </div>
    </div>
</div>

<script>
// Auto report error if in debug mode
<?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
document.addEventListener('DOMContentLoaded', function() {
    console.error('Error Details:', <?php echo json_encode($error); ?>);
    
    // Send error report (optional)
    if (window.debugger) {
        debugger.logError('error_page', {
            message: '<?php echo addslashes($error['message']); ?>',
            file: '<?php echo addslashes($error['file']); ?>',
            line: '<?php echo addslashes($error['line']); ?>'
        });
    }
});
<?php endif; ?>

// Add error reporting functionality
document.getElementById('report-error-btn')?.addEventListener('click', function() {
    if (confirm('Kirim laporan error ini ke tim support?')) {
        // In real implementation, send error report
        alert('Terima kasih! Laporan error telah dikirim.');
    }
});
</script>

<?php include 'includes/footer.php'; ?>