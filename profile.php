<?php
require_once 'config/config.php';

$auth = new Auth($db);
$auth->requireLogin();

$pageTitle = 'Profile';

// Get user data
$query = "SELECT id, name, email, phone, role, status, created_at FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's orders
$query = "SELECT o.*, COUNT(oi.id) as total_items 
          FROM orders o 
          LEFT JOIN customers c ON o.customer_id = c.id 
          LEFT JOIN order_items oi ON o.id = oi.order_id 
          WHERE c.user_id = ? 
          GROUP BY o.id 
          ORDER BY o.created_at DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>
<link rel="stylesheet" href="assets/css/style.css">
<div class="container mt-4">
    <h1 class="text-center mb-4">Profil Saya</h1>
    
    <?php displayFlashMessage(); ?>
    
    <div class="form-container">
        <div class="d-flex flex-wrap">
            <!-- User Info -->
            <div class="flex-grow-1" style="margin-right: 2rem;">
                <h3>Informasi Akun</h3>
                <div class="table-container">
                    <table class="table">
                        <tr>
                            <td><strong>Nama Lengkap:</strong></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Nomor Telepon:</strong></td>
                            <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Role:</strong></td>
                            <td>
                                <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td>
                                <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Bergabung Sejak:</strong></td>
                            <td><?php echo date('d F Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                    </table>
                </div>
                
                <div class="mt-4">
                    <a href="edit-profile.php" class="btn btn-primary">Edit Profil</a>
                    <a href="change-password.php" class="btn btn-warning">Ganti Password</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh stats every 30 seconds
setInterval(function() {
    // In a real application, you would fetch updated stats via AJAX
    console.log('Refreshing stats...');
}, 30000);

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch(e.key) {
            case 'e':
                e.preventDefault();
                window.location.href = 'edit-profile.php';
                break;
            case 'o':
                e.preventDefault();
                window.location.href = 'orders.php';
                break;
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>