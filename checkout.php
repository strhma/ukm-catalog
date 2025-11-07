<?php
require_once 'config/config.php';

$auth = new Auth($db);
$auth->requireLogin();

$cart = new Cart();
$pageTitle = 'Checkout';

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid request');
        header('Location: checkout.php');
        exit();
    }

    // Get form data
    $customerName = sanitizeInput($_POST['customer_name']);
    $customerEmail = sanitizeInput($_POST['customer_email']);
    $customerPhone = sanitizeInput($_POST['customer_phone']);
    $shippingAddress = sanitizeInput($_POST['shipping_address']);
    $notes = sanitizeInput($_POST['notes'] ?? '');
    $paymentMethod = sanitizeInput($_POST['payment_method']);

    // Validation
    $errors = [];
    
    if (empty($customerName)) {
        $errors[] = 'Nama lengkap diperlukan';
    }
    
    if (empty($customerEmail) || !validateEmail($customerEmail)) {
        $errors[] = 'Email valid diperlukan';
    }
    
    if (empty($customerPhone) || !validatePhone($customerPhone)) {
        $errors[] = 'Nomor telepon valid diperlukan (10-13 digit)';
    }
    
    if (empty($shippingAddress)) {
        $errors[] = 'Alamat pengiriman diperlukan';
    }

    // Get cart items
    $cartItems = $cart->getItems();
    if (empty($cartItems)) {
        $errors[] = 'Keranjang belanja kosong';
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Get product details and validate stock
            $productIds = array_keys($cartItems);
            $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
            
            $query = "SELECT id, name, price, stock FROM products WHERE id IN ($placeholders) AND status = 'active'";
            $stmt = $db->prepare($query);
            $stmt->execute($productIds);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Validate stock
            $totalAmount = 0;
            $orderItems = [];
            
            foreach ($products as $product) {
                $quantity = $cartItems[$product['id']];
                
                if ($quantity > $product['stock']) {
                    throw new Exception("Stok tidak cukup untuk produk: {$product['name']}");
                }
                
                $subtotal = $product['price'] * $quantity;
                $totalAmount += $subtotal;
                
                $orderItems[] = [
                    'product_id' => $product['id'],
                    'quantity' => $quantity,
                    'price' => $product['price'],
                    'subtotal' => $subtotal
                ];
            }
            
            // Create customer record
            $query = "INSERT INTO customers (user_id, name, email, phone, address, created_at) 
                      VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $_SESSION['user_id'],
                $customerName,
                $customerEmail,
                $customerPhone,
                $shippingAddress
            ]);
            $customerId = $db->lastInsertId();
            
            // Generate order number
            $orderNumber = 'ORD' . date('YmdHis') . str_pad($customerId, 4, '0', STR_PAD_LEFT);
            
            // Create order
            $query = "INSERT INTO orders (customer_id, order_number, total_amount, status, shipping_address, notes, created_at) 
                      VALUES (?, ?, ?, 'pending', ?, ?, NOW())";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $customerId,
                $orderNumber,
                $totalAmount,
                $shippingAddress,
                $notes
            ]);
            $orderId = $db->lastInsertId();
            
            // Create order items and update stock
            foreach ($orderItems as $item) {
                // Insert order item
                $query = "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) 
                          VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price'],
                    $item['subtotal']
                ]);
                
                // Update stock
                $query = "UPDATE products SET stock = stock - ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            // Clear cart
            $cart->clear();
            
            // Log activity
            $query = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
                      VALUES (?, 'order_created', ?, ?, ?, NOW())";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $_SESSION['user_id'],
                "Order #{$orderNumber} created with total " . formatRupiah($totalAmount),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            $db->commit();
            
            // Success message and redirect
            setFlashMessage('success', "Pesanan #{$orderNumber} berhasil dibuat! Silakan lakukan pembayaran.");
            header('Location: order-success.php?order_id=' . $orderId);
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            setFlashMessage('error', 'Gagal membuat pesanan: ' . $e->getMessage());
        }
    } else {
        setFlashMessage('error', implode('<br>', $errors));
    }
}

// Get cart items
$cartItems = $cart->getItems();
$cartProducts = [];
$totalAmount = 0;

if (!empty($cartItems)) {
    // Get product details
    $productIds = array_keys($cartItems);
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    
    $query = "SELECT p.*, c.name as category_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.id IN ($placeholders) AND p.status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute($productIds);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    foreach ($products as $product) {
        $quantity = $cartItems[$product['id']];
        $subtotal = $product['price'] * $quantity;
        $totalAmount += $subtotal;
        
        $cartProducts[] = [
            'product' => $product,
            'quantity' => $quantity,
            'subtotal' => $subtotal
        ];
    }
} else {
    // If cart is empty, check if there's a direct product purchase
    $productId = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
    if ($productId > 0) {
        $query = "SELECT p.*, c.name as category_name 
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  WHERE p.id = ? AND p.status = 'active'";
        $stmt = $db->prepare($query);
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $quantity = 1;
            $subtotal = $product['price'] * $quantity;
            $totalAmount += $subtotal;
            
            $cartProducts[] = [
                'product' => $product,
                'quantity' => $quantity,
                'subtotal' => $subtotal
            ];
        }
    }
}

// If no products, redirect to cart
if (empty($cartProducts)) {
    setFlashMessage('warning', 'Keranjang belanja kosong');
    header('Location: cart.php');
    exit();
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <h1 class="text-center mb-4">Checkout</h1>
    
    <div class="form-container">
        <?php displayFlashMessage(); ?>
        
        <!-- Order Summary -->
        <div class="mb-5">
            <h3>Ringkasan Pesanan</h3>
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
                        <?php foreach ($cartProducts as $item): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($item['product']['image']): ?>
                                            <img src="<?php echo UPLOAD_URL . $item['product']['image']; ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product']['name']); ?>" 
                                                 style="width: 50px; height: 50px; object-fit: cover; margin-right: 1rem;">
                                        <?php else: ?>
                                            <div style="width: 50px; height: 50px; background: #f1f2f6; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                                                <span style="color: #747d8c; font-size: 0.7rem;">No Image</span>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?php echo htmlspecialchars($item['product']['name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($item['product']['category_name'] ?? 'Tidak berkategori'); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo formatRupiah($item['product']['price']); ?></td>
                                <td><strong><?php echo formatRupiah($item['subtotal']); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #f8f9fa;">
                            <td colspan="3"><strong>Total Pesanan:</strong></td>
                            <td><strong style="font-size: 1.2rem; color: #3742fa;"><?php echo formatRupiah($totalAmount); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        
        <!-- Checkout Form -->
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <h3>Informasi Pembeli</h3>
            
            <div class="form-group">
                <label for="customer_name">Nama Lengkap *</label>
                <input type="text" class="form-control" id="customer_name" name="customer_name" 
                       value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="customer_email">Email *</label>
                <input type="email" class="form-control" id="customer_email" name="customer_email" 
                       value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="customer_phone">Nomor Telepon *</label>
                <input type="tel" class="form-control" id="customer_phone" name="customer_phone" required>
            </div>
            
            <div class="form-group">
                <label for="shipping_address">Alamat Pengiriman *</label>
                <textarea class="form-control" id="shipping_address" name="shipping_address" rows="3" required 
                          placeholder="Masukkan alamat lengkap pengiriman..."></textarea>
            </div>
            
            <div class="form-group">
                <label for="notes">Catatan Tambahan (Opsional)</label>
                <textarea class="form-control" id="notes" name="notes" rows="2" 
                          placeholder="Catatan khusus untuk penjual..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Metode Pembayaran *</label>
                <div class="d-flex flex-wrap gap-3">
                    <label class="d-flex align-items-center">
                        <input type="radio" name="payment_method" value="transfer" required checked>
                        <span class="ml-2">Transfer Bank</span>
                    </label>
                    <label class="d-flex align-items-center">
                        <input type="radio" name="payment_method" value="cod">
                        <span class="ml-2">Cash on Delivery (COD)</span>
                    </label>
                    <label class="d-flex align-items-center">
                        <input type="radio" name="payment_method" value="ewallet">
                        <span class="ml-2">E-Wallet</span>
                    </label>
                </div>
            </div>
            
            <div class="cart-summary">
                <h4>Konfirmasi Pesanan</h4>
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Pesanan:</span>
                    <span><?php echo formatRupiah($totalAmount); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Biaya Pengiriman:</span>
                    <span>Gratis</span>
                </div>
                <div class="d-flex justify-content-between mb-3" style="font-size: 1.2rem; font-weight: bold;">
                    <span>Total Pembayaran:</span>
                    <span style="color: #3742fa;"><?php echo formatRupiah($totalAmount); ?></span>
                </div>
                
                <div class="d-flex gap-2">
                    <a href="cart.php" class="btn btn-secondary flex-grow-1">Kembali ke Keranjang</a>
                    <button type="submit" class="btn btn-primary flex-grow-1" style="font-size: 1.1rem; padding: 1rem;">
                        🛍️ Buat Pesanan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.needs-validation');
    const inputs = form.querySelectorAll('.form-control');
    
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
    });
    
    function validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let message = '';
        
        clearFieldError(field);
        
        if (field.hasAttribute('required') && !value) {
            message = 'Field ini wajib diisi';
            isValid = false;
        } else if (field.type === 'email' && value && !isValidEmail(value)) {
            message = 'Masukkan email yang valid';
            isValid = false;
        } else if (field.name === 'customer_phone' && value && !isValidPhone(value)) {
            message = 'Masukkan nomor telepon yang valid (10-13 digit)';
            isValid = false;
        }
        
        if (!isValid) {
            showFieldError(field, message);
        }
        
        return isValid;
    }
    
    function clearFieldError(field) {
        field.classList.remove('is-invalid');
        const feedback = field.parentNode.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.remove();
        }
    }
    
    function showFieldError(field, message) {
        field.classList.add('is-invalid');
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.textContent = message;
        field.parentNode.appendChild(feedback);
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function isValidPhone(phone) {
        const phoneRegex = /^[0-9]{10,13}$/;
        return phoneRegex.test(phone);
    }
    
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        inputs.forEach(input => {
            if (!validateField(input)) {
                isValid = false;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            e.stopPropagation();
        } else {
            // Show loading
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<span class="loading"></span> Memproses...';
            submitBtn.disabled = true;
        }
    });
});

// Payment method info
document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const paymentInfo = {
            'transfer': 'Silakan transfer ke rekening: BCA 1234567890 a/n UKM Catalog',
            'cod': 'Bayar langsung saat barang sampai. Area COD: Jakarta, Bandung, Surabaya',
            'ewallet': 'Pembayaran via E-Wallet: OVO, GoPay, ShopeePay'
        };
        
        // Remove existing payment info
        const existingInfo = document.querySelector('.payment-info');
        if (existingInfo) {
            existingInfo.remove();
        }
        
        // Add payment info
        const infoDiv = document.createElement('div');
        infoDiv.className = 'payment-info alert alert-info mt-3';
        infoDiv.textContent = paymentInfo[this.value];
        
        const paymentSection = document.querySelector('input[name="payment_method"]').closest('.form-group');
        paymentSection.appendChild(infoDiv);
    });
});

// Auto-show payment info for default selection
document.addEventListener('DOMContentLoaded', function() {
    const defaultPayment = document.querySelector('input[name="payment_method"]:checked');
    if (defaultPayment) {
        defaultPayment.dispatchEvent(new Event('change'));
    }
});
</script>

<?php include 'includes/footer.php'; ?>