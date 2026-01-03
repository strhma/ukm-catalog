<?php
require_once 'config/config.php';

$auth = new Auth($db);
$auth->requireLogin();

$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($orderId <= 0) {
    header('Location: orders.php');
    exit();
}

// Get order details
// Security check: Ensure order belongs to logged in user
$query = "SELECT o.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address 
          FROM orders o 
          JOIN customers c ON o.customer_id = c.id 
          WHERE o.id = ? AND c.user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$orderId, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    setFlashMessage('error', 'Pesanan tidak ditemukan atau Anda tidak memiliki akses.');
    header('Location: orders.php');
    exit();
}

// Get order items
$query = "SELECT oi.*, p.name as product_name, p.image 
          FROM order_items oi 
          LEFT JOIN products p ON oi.product_id = p.id 
          WHERE oi.order_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Detail Pesanan #' . $order['order_number'];
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="mb-3">
        <a href="orders.php" class="text-decoration-none">&larr; Kembali ke Riwayat Pesanan</a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Item Pesanan</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Jumlah</th>
                                    <th>Harga</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($item['image']): ?>
                                                    <img src="<?php echo UPLOAD_URL . $item['image']; ?>" 
                                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                                         style="width: 50px; height: 50px; object-fit: cover; margin-right: 1rem; border-radius: 4px;">
                                                <?php else: ?>
                                                    <div style="width: 50px; height: 50px; background: #f1f2f6; display: flex; align-items: center; justify-content: center; margin-right: 1rem; border-radius: 4px;">
                                                        <span style="color: #747d8c; font-size: 0.7rem;">No Img</span>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="align-middle text-center"><?php echo $item['quantity']; ?></td>
                                        <td class="align-middle"><?php echo formatRupiah($item['price']); ?></td>
                                        <td class="align-middle text-right fw-bold"><?php echo formatRupiah($item['subtotal']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Informasi Pengiriman & Pembayaran</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-2">Alamat Pengiriman</h6>
                            <p class="mb-1"><strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
                            <p class="mb-1"><?php echo htmlspecialchars($order['customer_phone']); ?></p>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['address'])); ?></p>
                            <?php if ($order['shipping_address']): ?>
                                <hr>
                                <p class="small text-muted mb-0">Detail Alamat: <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Metode Pengiriman</h6>
                            <?php if (!empty($order['courier'])): ?>
                                <p class="mb-1 text-uppercase"><strong><?php echo htmlspecialchars($order['courier']); ?></strong></p>
                                <p class="mb-0 text-muted"><?php echo htmlspecialchars($order['shipping_service']); ?></p>
                            <?php else: ?>
                                <p class="text-muted">-</p>
                            <?php endif; ?>

                            <h6 class="text-muted mt-3 mb-2">Catatan</h6>
                            <p class="mb-0 font-italic">"<?php echo $order['notes'] ? htmlspecialchars($order['notes']) : '-'; ?>"</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-4">Ringkasan Pesanan</h5>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>No. Pesanan</span>
                        <span class="fw-bold"><?php echo htmlspecialchars($order['order_number']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tanggal</span>
                        <span><?php echo date('d M Y', strtotime($order['created_at'])); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Status</span>
                        <?php 
                        $statusLabels = ['pending' => 'Menunggu Pembayaran', 'processing' => 'Diproses', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];
                        $statusColors = ['pending' => 'warning', 'processing' => 'info', 'completed' => 'success', 'cancelled' => 'danger'];
                        ?>
                        <span class="badge badge-<?php echo $statusColors[$order['status']]; ?>">
                            <?php echo $statusLabels[$order['status']]; ?>
                        </span>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total Harga Barang</span>
                        <span><?php echo formatRupiah($order['total_amount'] - ($order['shipping_cost'] ?? 0)); ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Ongkos Kirim</span>
                        <span><?php echo formatRupiah($order['shipping_cost'] ?? 0); ?></span>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-4">
                        <span class="h5 mb-0">Total Belanja</span>
                        <span class="h5 mb-0 text-primary"><?php echo formatRupiah($order['total_amount']); ?></span>
                    </div>

                    <?php if ($order['status'] === 'pending'): ?>
                        <button class="btn btn-primary w-100 mb-2">Bayar Sekarang</button>
                        <div class="alert alert-warning small mb-0 mt-2">
                            Silakan lakukan pembayaran ke rekening terdaftar.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
