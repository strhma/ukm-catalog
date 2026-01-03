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
    
    // RajaOngkir Data
    $provinceId = isset($_POST['province']) ? intval($_POST['province']) : 0;
    $cityId = isset($_POST['city']) ? intval($_POST['city']) : 0;
    $courier = isset($_POST['courier']) ? sanitizeInput($_POST['courier']) : '';
    $shippingCost = isset($_POST['shipping_cost']) ? floatval($_POST['shipping_cost']) : 0;
    $shippingService = isset($_POST['shipping_service']) ? sanitizeInput($_POST['shipping_service']) : '';

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

    if (empty($provinceId) || empty($cityId) || empty($courier) || empty($shippingService)) {
        $errors[] = 'Silakan pilih layanan pengiriman lengkap (Provinsi, Kota, Kurir)';
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
            $totalWeight = 0; // Total weight in grams
            $orderItems = [];
            
            foreach ($products as $product) {
                $quantity = $cartItems[$product['id']];
                
                if ($quantity > $product['stock']) {
                    throw new Exception("Stok tidak cukup untuk produk: {$product['name']}");
                }
                
                $subtotal = $product['price'] * $quantity;
                $totalAmount += $subtotal;
                $totalWeight += ($product['weight'] ?? 1000) * $quantity; // Default 1kg if not set
                
                $orderItems[] = [
                    'product_id' => $product['id'],
                    'quantity' => $quantity,
                    'price' => $product['price'],
                    'subtotal' => $subtotal
                ];
            }
            
            // Verify Shipping Cost (Server-Side)
            $totalAmount += $shippingCost; // Add shipping to total
            
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
            $query = "INSERT INTO orders (customer_id, order_number, total_amount, status, shipping_address, notes, shipping_cost, courier, shipping_service, created_at) 
                      VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $customerId,
                $orderNumber,
                $totalAmount,
                $shippingAddress,
                $notes,
                $shippingCost,
                $courier,
                $shippingService
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
            header('Location: ' . BASE_URL . 'order-success.php?order_id=' . $orderId);
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
        <form action="checkout.php" method="POST" class="needs-validation" novalidate>
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
            
            <!-- RajaOngkir Selectors -->
            <div class="row">
                <div class="col-md-6 form-group">
                    <label>Provinsi</label>
                    <select class="form-control" id="province" name="province" required>
                        <option value="">Pilih Provinsi...</option>
                    </select>
                </div>
                <div class="col-md-6 form-group">
                    <label>Kota/Kabupaten</label>
                    <select class="form-control" id="city" name="city" required disabled>
                        <option value="">Pilih Kota...</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 form-group">
                    <label>Kurir</label>
                    <select class="form-control" id="courier" name="courier" required disabled>
                        <option value="">Pilih Kurir...</option>
                        <option value="jne">JNE</option>
                        <option value="pos">POS Indonesia</option>
                        <option value="tiki">TIKI</option>
                    </select>
                </div>
                <div class="col-md-6 form-group">
                    <label>Layanan Pengiriman</label>
                    <select class="form-control" id="shipping_service" name="shipping_service" required disabled>
                        <option value="">Pilih Layanan...</option>
                    </select>
                </div>
            </div>
            
            <input type="hidden" name="shipping_cost" id="shipping_cost" value="0">
            
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
                    <span id="display_shipping_cost">Rp 0</span>
                </div>
                <div class="d-flex justify-content-between mb-3" style="font-size: 1.2rem; font-weight: bold;">
                    <span>Total Pembayaran:</span>
                    <span style="color: #3742fa;" id="display_total_amount"><?php echo formatRupiah($totalAmount); ?></span>
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
    const baseTotal = <?php echo $totalAmount; ?>;
    
    // RajaOngkir Logic
    const provinceSelect = document.getElementById('province');
    const citySelect = document.getElementById('city');
    const courierSelect = document.getElementById('courier');
    const serviceSelect = document.getElementById('shipping_service');
    const shippingCostInput = document.getElementById('shipping_cost');
    const displayShippingCost = document.getElementById('display_shipping_cost');
    const displayTotalAmount = document.getElementById('display_total_amount');
    
    // Load Provinces
    fetch('api/rajaongkir/provinces.php')
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                res.data.forEach(prov => {
                    const option = document.createElement('option');
                    option.value = prov.province_id;
                    option.textContent = prov.province;
                    provinceSelect.appendChild(option);
                });
            }
        })
        .catch(err => console.error('Error loading provinces:', err));
        
    // Load Cities when Province changes
    provinceSelect.addEventListener('change', function() {
        citySelect.innerHTML = '<option value="">Pilih Kota...</option>';
        citySelect.disabled = true;
        courierSelect.disabled = true;
        serviceSelect.innerHTML = '<option value="">Pilih Layanan...</option>';
        serviceSelect.disabled = true;
        updateTotals(0);
        
        if (this.value) {
            fetch(`api/rajaongkir/cities.php?province_id=${this.value}`)
                .then(response => response.json())
                .then(res => {
                    if (res.success) {
                        res.data.forEach(city => {
                            const option = document.createElement('option');
                            option.value = city.city_id;
                            option.textContent = `${city.type} ${city.city_name}`;
                            citySelect.appendChild(option);
                        });
                        citySelect.disabled = false;
                    }
                });
        }
    });
    
    // Enable Courier when City selected
    citySelect.addEventListener('change', function() {
        if (this.value) {
            courierSelect.disabled = false;
        } else {
            courierSelect.disabled = true;
        }
        serviceSelect.innerHTML = '<option value="">Pilih Layanan...</option>';
        serviceSelect.disabled = true;
        updateTotals(0);
    });
    
    // Calculate Cost when Courier selected
    courierSelect.addEventListener('change', function() {
        serviceSelect.innerHTML = '<option value="">Loading...</option>';
        serviceSelect.disabled = true;
        updateTotals(0);
        
        if (this.value && citySelect.value) {
            fetch('api/rajaongkir/cost.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    destination: citySelect.value,
                    courier: this.value,
                    weight: 1000 // Default 1kg for now
                })
            })
            .then(response => response.json())
            .then(res => {
                serviceSelect.innerHTML = '<option value="">Pilih Layanan...</option>';
                if (res.success && res.data[0].costs.length > 0) {
                    res.data[0].costs.forEach(cost => {
                        const option = document.createElement('option');
                        const serviceName = cost.service;
                        const price = cost.cost[0].value;
                        const etd = cost.cost[0].etd;
                        
                        option.value = serviceName;
                        option.textContent = `${serviceName} - ${formatRupiah(price)} (Est: ${etd} hari)`;
                        option.dataset.price = price;
                        serviceSelect.appendChild(option);
                    });
                    serviceSelect.disabled = false;
                } else {
                    serviceSelect.innerHTML = '<option value="">Tidak ada layanan tersedia</option>';
                    if (res.message) alert(res.message);
                }
            })
            .catch(err => {
                console.error(err);
                serviceSelect.innerHTML = '<option value="">Error fetching costs</option>';
            });
        }
    });

    // Update totals when Service selected
    serviceSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const price = selectedOption.dataset.price ? parseInt(selectedOption.dataset.price) : 0;
        updateTotals(price);
    });
    
    function updateTotals(shippingPrice) {
        shippingCostInput.value = shippingPrice;
        displayShippingCost.textContent = formatRupiah(shippingPrice);
        
        const total = baseTotal + shippingPrice;
        displayTotalAmount.textContent = formatRupiah(total);
    }
    
    function formatRupiah(number) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(number);
    }
</script>

<?php include 'includes/footer.php'; ?>