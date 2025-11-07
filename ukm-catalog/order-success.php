<?php
require_once 'config/config.php';

$auth = new Auth($db);
$auth->requireLogin();

$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($orderId <= 0) {
    header('Location: index.php');
    exit();
}

// Get order details
$query = "SELECT o.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address 
          FROM orders o 
          LEFT JOIN customers c ON o.customer_id = c.id 
          WHERE o.id = ? AND c.user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$orderId, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    setFlashMessage('error', 'Order not found');
    header('Location: index.php');
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

$pageTitle = 'Order Success';
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="form-container text-center">
        <div style="font-size: 4rem; margin-bottom: 1rem;">🎉</div>
        <h1>Pesanan Berhasil Dibuat!</h1>
        <p class="text-muted">Terima kasih telah berbelanja di UKM Catalog</p>
        
        <div class="alert alert-success mt-4">
            <h4>Nomor Pesanan: <?php echo htmlspecialchars($order['order_number']); ?></h4>
            <p>Total Pembayaran: <strong><?php echo formatRupiah($order['total_amount']); ?></strong></p>
        </div>
        
        <?php displayFlashMessage(); ?>
        
        <!-- Order Details -->
        <div class="text-left mt-5">
            <h3>Detail Pesanan</h3>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Jumlah</th>
                            <th>Harga</th>
                            <th>Subtotal</th>
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
                                                 style="width: 40px; height: 40px; object-fit: cover; margin-right: 1rem;">
                                        <?php else: ?>
                                            <div style="width: 40px; height: 40px; background: #f1f2f6; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                                                <span style="color: #747d8c; font-size: 0.6rem;">No Image</span>
                                            </div>
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($item['product_name']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo formatRupiah($item['price']); ?></td>
                                <td><?php echo formatRupiah($item['subtotal']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #f8f9fa;">
                            <td colspan="3"><strong>Total:</strong></td>
                            <td><strong><?php echo formatRupiah($order['total_amount']); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        
        <!-- Customer Information -->
        <div class="text-left mt-4">
            <h3>Informasi Pembeli</h3>
            <div class="table-container">
                <table class="table">
                    <tr>
                        <td><strong>Nama:</strong></td>
                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Email:</strong></td>
                        <td><?php echo htmlspecialchars($order['customer_email']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Telepon:</strong></td>
                        <td><?php echo htmlspecialchars($order['customer_phone']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Alamat Pengiriman:</strong></td>
                        <td><?php echo nl2br(htmlspecialchars($order['address'])); ?></td>
                    </tr>
                    <?php if ($order['notes']): ?>
                        <tr>
                            <td><strong>Catatan:</strong></td>
                            <td><?php echo nl2br(htmlspecialchars($order['notes'])); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong>Status Pesanan:</strong></td>
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
                    </tr>
                    <tr>
                        <td><strong>Tanggal Pemesanan:</strong></td>
                        <td><?php echo date('d F Y H:i', strtotime($order['created_at'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Next Steps -->
        <div class="mt-5">
            <h3>Langkah Selanjutnya</h3>
            <div class="alert alert-info">
                <?php if ($order['status'] === 'pending'): ?>
                    <h5>🔄 Menunggu Pembayaran</h5>
                    <p>Silakan lakukan pembayaran sesuai dengan metode yang Anda pilih saat checkout.</p>
                    <p>Anda akan menerima email konfirmasi dengan detail pembayaran.</p>
                <?php elseif ($order['status'] === 'processing'): ?>
                    <h5>📦 Pesanan Diproses</h5>
                    <p>Pesanan Anda sedang diproses oleh penjual.</p>
                    <p>Anda akan menerima notifikasi ketika pesanan dikirim.</p>
                <?php elseif ($order['status'] === 'completed'): ?>
                    <h5>✅ Pesanan Selesai</h5>
                    <p>Terima kasih! Pesanan Anda telah selesai.</p>
                    <p>Jangan lupa berikan ulasan untuk produk yang Anda beli.</p>
                <?php else: ?>
                    <h5>❌ Pesanan Dibatalkan</h5>
                    <p>Maaf, pesanan Anda telah dibatalkan.</p>
                    <p>Silakan hubungi customer service untuk informasi lebih lanjut.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="mt-4 d-flex flex-wrap gap-2 justify-content-center">
            <a href="index.php" class="btn btn-primary">Kembali ke Beranda</a>
            <a href="products.php" class="btn btn-secondary">Lanjut Belanja</a>
            <a href="orders.php" class="btn btn-info">Lihat Semua Pesanan</a>
            <button type="button" class="btn btn-success" onclick="copyOrderNumber()">📋 Salin Nomor Pesanan</button>
        </div>
    </div>
</div>

<script>
function copyOrderNumber() {
    const orderNumber = "<?php echo $order['order_number']; ?>";
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(orderNumber).then(function() {
            alert('Nomor pesanan berhasil disalin: ' + orderNumber);
        }).catch(function(err) {
            console.error('Could not copy text: ', err);
            fallbackCopyTextToClipboard(orderNumber);
        });
    } else {
        fallbackCopyTextToClipboard(orderNumber);
    }
}

function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        alert('Nomor pesanan berhasil disalin: ' + text);
    } catch (err) {
        console.error('Fallback: Oops, unable to copy', err);
        prompt('Salin nomor pesanan ini:', text);
    }
    
    document.body.removeChild(textArea);
}

// Auto-hide success message after 10 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert-success');
    alerts.forEach(function(alert) {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(function() {
            alert.style.display = 'none';
        }, 500);
    });
}, 10000);
</script>

<?php include 'includes/footer.php'; ?>