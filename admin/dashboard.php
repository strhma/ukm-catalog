<?php
require_once '../config/config.php';

$auth = new Auth($db);
$auth->requireLogin();
$auth->requireRole('admin');

$pageTitle = 'Admin Dashboard';

// Get statistics
$stats = [];

// Total users
$query = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total products
$query = "SELECT COUNT(*) as total FROM products WHERE status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total categories
$query = "SELECT COUNT(*) as total FROM categories WHERE status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_categories'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total orders
$query = "SELECT COUNT(*) as total FROM orders";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total revenue
$query = "SELECT SUM(total_amount) as total FROM orders WHERE status = 'completed'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Recent orders
$query = "SELECT o.*, c.name as customer_name, COUNT(oi.id) as total_items 
          FROM orders o 
          LEFT JOIN customers c ON o.customer_id = c.id 
          LEFT JOIN order_items oi ON o.id = oi.order_id 
          GROUP BY o.id 
          ORDER BY o.created_at DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent products
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          ORDER BY p.created_at DESC 
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$recentProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Low stock products
$query = "SELECT * FROM products WHERE stock <= 5 AND status = 'active' ORDER BY stock ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '/../../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Admin Dashboard</h1>
        <div class="d-flex gap-2">
            <a href="../index.php" class="btn btn-secondary">View Site</a>
            <a href="settings.php" class="btn btn-warning">Settings</a>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Statistics -->
    <div class="dashboard-stats mb-5">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_users']; ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_products']; ?></div>
            <div class="stat-label">Total Products</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_categories']; ?></div>
            <div class="stat-label">Total Categories</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_orders']; ?></div>
            <div class="stat-label">Total Orders</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #2ed573;"><?php echo formatRupiah($stats['total_revenue']); ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #ff4757;"><?php echo count($lowStockProducts); ?></div>
            <div class="stat-label">Low Stock Products</div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Orders -->
        <div class="col-md-6 mb-4">
            <div class="form-container">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3>Recent Orders</h3>
                    <a href="orders.php" class="btn btn-primary btn-sm">View All</a>
                </div>
                
                <?php if (empty($recentOrders)): ?>
                    <p class="text-muted">No recent orders</p>
                <?php else: ?>
                    <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?></td>
                                        <td><?php echo formatRupiah($order['total_amount']); ?></td>
                                        <td>
                                            <?php 
                                            $statusLabels = [
                                                'pending' => 'Pending',
                                                'processing' => 'Processing',
                                                'completed' => 'Completed',
                                                'cancelled' => 'Cancelled'
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
                                        <td>
                                            <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-info btn-sm">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Products -->
        <div class="col-md-6 mb-4">
            <div class="form-container">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3>Recent Products</h3>
                    <a href="products.php" class="btn btn-primary btn-sm">View All</a>
                </div>
                
                <?php if (empty($recentProducts)): ?>
                    <p class="text-muted">No products available</p>
                <?php else: ?>
                    <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentProducts as $product): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($product['image']): ?>
                                                    <img src="<?php echo UPLOAD_URL . $product['image']; ?>" 
                                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                         style="width: 30px; height: 30px; object-fit: cover; margin-right: 0.5rem;">
                                                <?php else: ?>
                                                    <div style="width: 30px; height: 30px; background: #f1f2f6; display: flex; align-items: center; justify-content: center; margin-right: 0.5rem;">
                                                        <span style="color: #747d8c; font-size: 0.6rem;">No</span>
                                                    </div>
                                                <?php endif; ?>
                                                <span><?php echo htmlspecialchars($product['name']); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['category_name'] ?? '-'); ?></td>
                                        <td><?php echo formatRupiah($product['price']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $product['stock'] <= 5 ? 'warning' : 'success'; ?>">
                                                <?php echo $product['stock']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="../product-detail.php?id=<?php echo $product['id']; ?>" class="btn btn-info btn-sm">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Low Stock Alert -->
    <?php if (!empty($lowStockProducts)): ?>
        <div class="form-container mb-4">
            <h3 class="text-danger">⚠️ Low Stock Alert</h3>
            <div class="table-container" style="max-height: 300px; overflow-y: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Current Stock</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStockProducts as $product): ?>
                            <tr class="table-warning">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($product['image']): ?>
                                            <img src="<?php echo UPLOAD_URL . $product['image']; ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                 style="width: 30px; height: 30px; object-fit: cover; margin-right: 0.5rem;">
                                        <?php else: ?>
                                            <div style="width: 30px; height: 30px; background: #f1f2f6; display: flex; align-items: center; justify-content: center; margin-right: 0.5rem;">
                                                <span style="color: #747d8c; font-size: 0.6rem;">No</span>
                                            </div>
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($product['name']); ?></span>
                                    </div>
                                </td>
                                <td><strong class="text-danger"><?php echo $product['stock']; ?></strong></td>
                                <td>
                                    <a href="edit-product.php?id=<?php echo $product['id']; ?>" class="btn btn-warning btn-sm">Update Stock</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Quick Actions -->
    <div class="form-container">
        <h3>Quick Actions</h3>
        <div class="d-flex flex-wrap gap-2">
            <a href="add-product.php" class="btn btn-success">➕ Add Product</a>
            <a href="add-category.php" class="btn btn-info">➕ Add Category</a>
            <a href="users.php" class="btn btn-primary">👥 Manage Users</a>
            <a href="reports.php" class="btn btn-warning">📊 Reports</a>
            <a href="export.php" class="btn btn-secondary">📥 Export Data</a>
            <a href="settings.php" class="btn btn-dark">⚙️ Settings</a>
        </div>
    </div>
</div>

<script>
// Auto-refresh dashboard every 60 seconds
setInterval(function() {
    location.reload();
}, 60000);

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch(e.key) {
            case '1':
                e.preventDefault();
                window.location.href = 'add-product.php';
                break;
            case '2':
                e.preventDefault();
                window.location.href = 'add-category.php';
                break;
            case '3':
                e.preventDefault();
                window.location.href = 'users.php';
                break;
            case '4':
                e.preventDefault();
                window.location.href = 'orders.php';
                break;
        }
    }
});

// Animate statistics on load
document.addEventListener('DOMContentLoaded', function() {
    const statNumbers = document.querySelectorAll('.stat-number');
    
    statNumbers.forEach(function(stat) {
        const finalValue = stat.textContent;
        stat.textContent = '0';
        
        setTimeout(function() {
            stat.textContent = finalValue;
            stat.style.transition = 'all 0.5s ease-in-out';
            stat.style.transform = 'scale(1.1)';
            
            setTimeout(function() {
                stat.style.transform = 'scale(1)';
            }, 200);
        }, Math.random() * 500);
    });
});
</script>

<?php include '../includes/footer.php'; ?>