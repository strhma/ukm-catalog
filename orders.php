<?php
require_once 'config/config.php';

$auth = new Auth($db);
$auth->requireLogin();

$pageTitle = 'Riwayat Pesanan';
include 'includes/header.php';

// Get user's orders
$userId = $_SESSION['user_id'];
$query = "SELECT o.*, c.name as customer_name, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as total_items 
          FROM orders o 
          JOIN customers c ON o.customer_id = c.id 
          WHERE c.user_id = ? 
          ORDER BY o.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Riwayat Pesanan</h1>
        <a href="products.php" class="btn btn-primary">Belanja Lagi</a>
    </div>

    <?php if (empty($orders)): ?>
        <div class="text-center py-5">
            <div style="font-size: 4rem; opacity: 0.5;">🛍️</div>
            <h3 class="mt-3">Belum ada pesanan</h3>
            <p class="text-muted">Yuk, mulai belanja dan penuhi kebutuhanmu!</p>
            <a href="products.php" class="btn btn-outline-primary mt-2">Lihat Produk</a>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="py-3 px-4">No. Pesanan</th>
                                        <th class="py-3 px-4">Tanggal</th>
                                        <th class="py-3 px-4">Total</th>
                                        <th class="py-3 px-4">Status</th>
                                        <th class="py-3 px-4">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
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
                                        <tr>
                                            <td class="py-3 px-4">
                                                <strong><?php echo htmlspecialchars($order['order_number']); ?></strong><br>
                                                <small class="text-muted"><?php echo $order['total_items']; ?> barang</small>
                                            </td>
                                            <td class="py-3 px-4">
                                                <?php echo date('d M Y H:i', strtotime($order['created_at'])); ?>
                                            </td>
                                            <td class="py-3 px-4">
                                                <strong><?php echo formatRupiah($order['total_amount']); ?></strong>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="badge badge-<?php echo $statusColors[$order['status']]; ?>">
                                                    <?php echo $statusLabels[$order['status']]; ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info text-white">
                                                    Detail
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
