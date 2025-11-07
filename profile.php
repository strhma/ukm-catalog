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
            
            <!-- Quick Stats -->
            <div style="min-width: 250px;">
                <h3>Statistik</h3>
                <div class="dashboard-stats" style="grid-template-columns: 1fr;">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($orders); ?></div>
                        <div class="stat-label">Total Pesanan</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">
                            <?php
                            $completedOrders = array_filter($orders, function($order) {
                                return $order['status'] === 'completed';
                            });
                            echo count($completedOrders);
                            ?>
                        </div>
                        <div class="stat-label">Pesanan Selesai</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">
                            <?php
                            $totalSpent = array_sum(array_map(function($order) {
                                return $order['status'] === 'completed' ? $order['total_amount'] : 0;
                            }, $orders));
                            echo formatRupiah($totalSpent);
                            ?>
                        </div>
                        <div class="stat-label">Total Belanja</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Orders -->
    <div class="form-container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3>Pesanan Terakhir</h3>
            <a href="orders.php" class="btn btn-info">Lihat Semua Pesanan</a>
        </div>
        
        <?php if (empty($orders)): ?>
            <div class="text-center py-4">
                <p class="text-muted">Anda belum memiliki pesanan</p>
                <a href="products.php" class="btn btn-primary">Mulai Belanja</a>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nomor Pesanan</th>
                            <th>Tanggal</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Item</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                                <td><?php echo formatRupiah($order['total_amount']); ?></td>
                                <td>
                                    <?php 
                                    $statusLabels = [
                                        'pending' => 'Menunggu Pembayaran',
                                        'processing' => 'Diproses',
                                        'completed' => 'Selesai',
                                        'cancelled' => 'Dibatalkan'
                                    ];
                                    $statusColors = [
                                        'pending' => 'warning',
                                        'processing' => 'info',
                                        'completed' => 'success',
                                        'cancelled' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge badge-<?php echo $statusColors[$order['status']]; ?>">
                                        <?php echo $statusLabels[$order['status']]; ?>
                                    </span>
                                </td>
                                <td><?php echo $order['total_items']; ?> item</td>
                                <td>
                                    <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm">Detail</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Actions -->
    <div class="form-container mt-5">
        <h3 class="mb-4">Aksi Cepat</h3>
        <div class="d-flex flex-wrap gap-2">
            <a href="products.php" class="btn btn-primary">Lanjut Belanja</a>
            <a href="cart.php" class="btn btn-secondary">Lihat Keranjang</a>
            <a href="wishlist.php" class="btn btn-info">Wishlist</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="admin/dashboard.php" class="btn btn-danger">Admin Dashboard</a>
            <?php endif; ?>
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