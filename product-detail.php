<?php
require_once 'config/config.php';

$auth = new Auth($db);
$auth->checkRememberLogin();

// Get product ID
$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($productId <= 0) {
    setFlashMessage('error', 'Product not found');
    header('Location: products.php');
    exit();
}

// Get product details
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.id = ? AND p.status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    setFlashMessage('error', 'Product not found');
    header('Location: products.php');
    exit();
}

$pageTitle = $product['name'];

// Get related products
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.category_id = ? AND p.id != ? AND p.status = 'active' 
          ORDER BY RAND() 
          LIMIT 4";
$stmt = $db->prepare($query);
$stmt->execute([$product['category_id'], $productId]);
$relatedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container mt-4">
    <?php displayFlashMessage(); ?>
    
    <div class="form-container">
        <div class="d-flex flex-wrap">
            <!-- Product Image -->
            <div class="w-100" style="max-width: 500px;">
                <?php if ($product['image']): ?>
                    <img src="<?php echo UPLOAD_URL . $product['image']; ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                         class="product-image" style="height: auto; max-height: 400px;">
                <?php else: ?>
                    <div class="product-image" style="background: #f1f2f6; display: flex; align-items: center; justify-content: center; height: 400px;">
                        <span style="color: #747d8c; font-size: 1.2rem;">No Image</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Product Info -->
            <div class="flex-grow-1" style="margin-left: 2rem;">
                <nav aria-label="breadcrumb">
                    <ol style="list-style: none; padding: 0; margin-bottom: 1rem;">
                        <li style="display: inline;"><a href="products.php">Produk</a></li>
                        <li style="display: inline;"> / </li>
                        <li style="display: inline;">
                            <a href="products.php?category=<?php echo $product['category_id']; ?>">
                                <?php echo htmlspecialchars($product['category_name'] ?? 'Tidak berkategori'); ?>
                            </a>
                        </li>
                        <li style="display: inline;"> / </li>
                        <li style="display: inline; color: #6c757d;"><?php echo htmlspecialchars($product['name']); ?></li>
                    </ol>
                </nav>
                
                <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <div class="product-price" style="font-size: 2rem; margin: 1rem 0;">
                    <?php echo formatRupiah($product['price']); ?>
                </div>
                
                <div class="mb-3">
                    <span class="badge badge-secondary">
                        Stok: <?php echo $product['stock']; ?> unit
                    </span>
                    <?php if ($product['stock'] <= 5 && $product['stock'] > 0): ?>
                        <span class="badge badge-warning">Stok Rendah</span>
                    <?php elseif ($product['stock'] <= 0): ?>
                        <span class="badge badge-danger">Habis</span>
                    <?php endif; ?>
                </div>
                
                <div class="mb-4">
                    <h4>Deskripsi Produk</h4>
                    <p style="line-height: 1.8;"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                </div>
                
                <?php if ($product['stock'] > 0): ?>
                    <div class="mb-4">
                        <label for="quantity">Jumlah:</label>
                        <div class="quantity-control" style="max-width: 150px;">
                            <button type="button" class="quantity-btn" onclick="decreaseQuantity()">-</button>
                            <input type="number" id="quantity" class="quantity-input" value="1" min="1" max="<?php echo $product['stock']; ?>">
                            <button type="button" class="quantity-btn" onclick="increaseQuantity()">+</button>
                        </div>
                    </div>
                    
                    <div class="product-actions" style="gap: 1rem;">
                        <button class="btn btn-success add-to-cart" 
                                data-product-id="<?php echo $product['id']; ?>" 
                                data-quantity="1"
                                style="font-size: 1.1rem; padding: 0.8rem 2rem;">
                            🛒 Tambah ke Keranjang
                        </button>
                        <a href="checkout.php?product_id=<?php echo $product['id']; ?>" class="btn btn-primary" style="font-size: 1.1rem; padding: 0.8rem 2rem;">
                            🛍️ Beli Sekarang
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <strong>Maaf, produk ini sedang habis.</strong><br>
                        Silakan cek kembali nanti atau hubungi penjual untuk informasi stok.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Related Products -->
    <?php if (!empty($relatedProducts)): ?>
        <section class="mt-5">
            <h2 class="text-center mb-4">Produk Terkait</h2>
            <div class="products-grid">
                <?php foreach ($relatedProducts as $related): ?>
                    <div class="product-card">
                        <?php if ($related['image']): ?>
                            <img src="<?php echo UPLOAD_URL . $related['image']; ?>" 
                                 alt="<?php echo htmlspecialchars($related['name']); ?>" 
                                 class="product-image">
                        <?php else: ?>
                            <div class="product-image" style="background: #f1f2f6; display: flex; align-items: center; justify-content: center;">
                                <span style="color: #747d8c;">No Image</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="product-info">
                            <h3 class="product-title"><?php echo htmlspecialchars($related['name']); ?></h3>
                            <div class="product-price"><?php echo formatRupiah($related['price']); ?></div>
                            <div class="product-stock">
                                Stok: <?php echo $related['stock']; ?> unit
                                <?php if ($related['stock'] <= 5 && $related['stock'] > 0): ?>
                                    <span style="color: #ff4757;"> (Stok rendah)</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-actions">
                                <a href="product-detail.php?id=<?php echo $related['id']; ?>" class="btn btn-primary">Detail</a>
                                <button class="btn btn-success add-to-cart" 
                                        data-product-id="<?php echo $related['id']; ?>" 
                                        data-quantity="1"
                                        <?php echo $related['stock'] <= 0 ? 'disabled' : ''; ?>>
                                    <?php echo $related['stock'] <= 0 ? 'Habis' : 'Tambah ke Cart'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    
    <!-- Back to Products -->
    <div class="text-center mt-5">
        <a href="products.php" class="btn btn-secondary">← Kembali ke Daftar Produk</a>
    </div>
</div>

<script>
function increaseQuantity() {
    const quantityInput = document.getElementById('quantity');
    const currentValue = parseInt(quantityInput.value);
    const maxValue = parseInt(quantityInput.max);
    
    if (currentValue < maxValue) {
        quantityInput.value = currentValue + 1;
        updateAddToCartButton();
    }
}

function decreaseQuantity() {
    const quantityInput = document.getElementById('quantity');
    const currentValue = parseInt(quantityInput.value);
    const minValue = parseInt(quantityInput.min);
    
    if (currentValue > minValue) {
        quantityInput.value = currentValue - 1;
        updateAddToCartButton();
    }
}

function updateAddToCartButton() {
    const quantityInput = document.getElementById('quantity');
    const addToCartButton = document.querySelector('.add-to-cart');
    
    if (addToCartButton) {
        addToCartButton.dataset.quantity = quantityInput.value;
    }
}

// Update quantity when input changes
document.getElementById('quantity').addEventListener('change', updateAddToCartButton);

// Add loading animation for add to cart
document.addEventListener('DOMContentLoaded', function() {
    const addToCartButton = document.querySelector('.add-to-cart');
    if (addToCartButton) {
        addToCartButton.addEventListener('click', function() {
            if (!this.disabled) {
                const originalText = this.innerHTML;
                this.innerHTML = '<span class="loading"></span>';
                this.disabled = true;
                
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.disabled = false;
                }, 1000);
            }
        });
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
        switch(e.key) {
            case '+':
            case '=':
                e.preventDefault();
                increaseQuantity();
                break;
            case '-':
                e.preventDefault();
                decreaseQuantity();
                break;
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>