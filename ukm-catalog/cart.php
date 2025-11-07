<?php
require_once 'config/config.php';

$auth = new Auth($db);
$auth->requireLogin();

$cart = new Cart();
$pageTitle = 'Shopping Cart';

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid request');
        header('Location: cart.php');
        exit();
    }

    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update':
            $productId = intval($_POST['product_id']);
            $quantity = max(0, intval($_POST['quantity']));
            
            if ($quantity > 0) {
                $cart->update($productId, $quantity);
                setFlashMessage('success', 'Cart updated successfully');
            } else {
                $cart->remove($productId);
                setFlashMessage('success', 'Product removed from cart');
            }
            break;
            
        case 'remove':
            $productId = intval($_POST['product_id']);
            $cart->remove($productId);
            setFlashMessage('success', 'Product removed from cart');
            break;
            
        case 'clear':
            $cart->clear();
            setFlashMessage('success', 'Cart cleared successfully');
            break;
    }
    
    header('Location: cart.php');
    exit();
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
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <h1 class="text-center mb-4">Keranjang Belanja</h1>
    
    <?php displayFlashMessage(); ?>
    
    <?php if (empty($cartProducts)): ?>
        <div class="cart-container text-center">
            <div style="font-size: 4rem; margin-bottom: 1rem;">🛒</div>
            <h3>Keranjang Anda Kosong</h3>
            <p class="text-muted">Tambahkan produk favorit Anda ke keranjang!</p>
            <a href="products.php" class="btn btn-primary">Jelajahi Produk</a>
        </div>
    <?php else: ?>
        <div class="cart-container">
            <form method="POST" id="cartForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Harga</th>
                                <th>Jumlah</th>
                                <th>Subtotal</th>
                                <th>Aksi</th>
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
                                                     style="width: 60px; height: 60px; object-fit: cover; margin-right: 1rem;">
                                            <?php else: ?>
                                                <div style="width: 60px; height: 60px; background: #f1f2f6; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                                                    <span style="color: #747d8c; font-size: 0.8rem;">No Image</span>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?php echo htmlspecialchars($item['product']['name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($item['product']['category_name'] ?? 'Tidak berkategori'); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo formatRupiah($item['product']['price']); ?></td>
                                    <td>
                                        <div class="quantity-control">
                                            <button type="button" class="quantity-btn" onclick="updateQuantity(<?php echo $item['product']['id']; ?>, <?php echo $item['quantity'] - 1; ?>)" 
                                                    <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>-</button>
                                            <input type="number" class="quantity-input" value="<?php echo $item['quantity']; ?>" 
                                                   min="1" max="<?php echo $item['product']['stock']; ?>" 
                                                   onchange="updateQuantity(<?php echo $item['product']['id']; ?>, this.value)">
                                            <button type="button" class="quantity-btn" onclick="updateQuantity(<?php echo $item['product']['id']; ?>, <?php echo $item['quantity'] + 1; ?>)" 
                                                    <?php echo $item['quantity'] >= $item['product']['stock'] ? 'disabled' : ''; ?>>+</button>
                                        </div>
                                        <small class="text-muted">Stok: <?php echo $item['product']['stock']; ?></small>
                                    </td>
                                    <td><strong><?php echo formatRupiah($item['subtotal']); ?></strong></td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm" 
                                                onclick="removeFromCart(<?php echo $item['product']['id']; ?>)" 
                                                title="Hapus dari keranjang">
                                            🗑️
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            
            <!-- Cart Summary -->
            <div class="cart-summary">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>Ringkasan Belanja</h4>
                    <button type="button" class="btn btn-warning" onclick="clearCart()">
                        🗑️ Kosongkan Keranjang
                    </button>
                </div>
                
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Produk:</span>
                    <span><?php echo count($cartProducts); ?> item</span>
                </div>
                
                <div class="d-flex justify-content-between mb-3">
                    <span>Total Harga:</span>
                    <span class="cart-total"><?php echo formatRupiah($totalAmount); ?></span>
                </div>
                
                <div class="d-flex gap-2">
                    <a href="products.php" class="btn btn-secondary flex-grow-1">Lanjut Belanja</a>
                    <a href="checkout.php" class="btn btn-primary flex-grow-1">Checkout Sekarang</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function updateQuantity(productId, quantity) {
    quantity = Math.max(1, parseInt(quantity) || 1);
    
    // Create form and submit
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="product_id" value="${productId}">
        <input type="hidden" name="quantity" value="${quantity}">
    `;
    
    document.body.appendChild(form);
    form.submit();
}

function removeFromCart(productId) {
    if (confirm('Apakah Anda yakin ingin menghapus produk ini dari keranjang?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="product_id" value="${productId}">
        `;
        
        document.body.appendChild(form);
        form.submit();
    }
}

function clearCart() {
    if (confirm('Apakah Anda yakin ingin mengosongkan seluruh keranjang?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="clear">
        `;
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Update cart count when quantities change
document.addEventListener('DOMContentLoaded', function() {
    const quantityInputs = document.querySelectorAll('.quantity-input');
    
    quantityInputs.forEach(input => {
        input.addEventListener('change', function() {
            const productId = this.closest('tr').querySelector('[onclick*="updateQuantity"]').getAttribute('onclick').match(/\d+/)[0];
            updateQuantity(productId, this.value);
        });
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        window.location.href = 'products.php';
    }
});
</script>

<?php include 'includes/footer.php'; ?>