<?php
require_once 'config/config.php';

$auth = new Auth($db);
$auth->checkRememberLogin();

$pageTitle = 'Home';

// Get featured products
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.status = 'active' 
          ORDER BY p.created_at DESC 
          LIMIT 8";
$stmt = $db->prepare($query);
$stmt->execute();
$featuredProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories
$query = "SELECT * FROM categories WHERE status = 'active' ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="hero">
    <div class="container">
        <h1>Selamat Datang di UKM Catalog</h1>
        <p>Temukan produk berkualitas dari UMKM lokal terbaik</p>
        <a href="products.php" class="btn btn-primary">Jelajahi Produk</a>
    </div>
</div>

<div class="container">
    <?php displayFlashMessage(); ?>
    
    <!-- Search Section -->
    <div class="mb-4">
        <div class="form-container">
            <div class="form-group">
                <label for="searchInput">Cari Produk</label>
                <input type="text" class="form-control" id="searchInput" placeholder="Ketik nama produk...">
            </div>
        </div>
    </div>

    <!-- Categories Section -->
    <section class="mb-5">
        <h2 class="text-center mb-4">Kategori Produk</h2>
        <div class="products-grid">
            <?php foreach ($categories as $category): ?>
                <div class="product-card">
                    <div class="product-info">
                        <h3 class="product-title"><?php echo htmlspecialchars($category['name']); ?></h3>
                        <p class="mb-3"><?php echo htmlspecialchars($category['description']); ?></p>
                        <a href="products.php?category=<?php echo $category['id']; ?>" class="btn btn-primary">
                            Lihat Produk
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Featured Products Section -->
    <section class="mb-5">
        <h2 class="text-center mb-4">Produk Terbaru</h2>
        <div class="products-grid">
            <?php foreach ($featuredProducts as $product): ?>
                <div class="product-card">
                    <?php if ($product['image']): ?>
                        <img src="<?php echo UPLOAD_URL . $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                    <?php else: ?>
                        <div class="product-image" style="background: #f1f2f6; display: flex; align-items: center; justify-content: center;">
                            <span style="color: #747d8c;">No Image</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="product-info">
                        <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="product-description"><?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?>...</p>
                        <div class="product-price"><?php echo formatRupiah($product['price']); ?></div>
                        <div class="product-stock">
                            Stok: <?php echo $product['stock']; ?> unit
                            <?php if ($product['stock'] <= 5): ?>
                                <span style="color: #ff4757;"> (Stok rendah)</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-actions">
                            <a href="product-detail.php?id=<?php echo $product['id']; ?>" class="btn btn-primary">Detail</a>
                            <button class="btn btn-success add-to-cart" 
                                    data-product-id="<?php echo $product['id']; ?>" 
                                    data-quantity="1"
                                    <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>
                                <?php echo $product['stock'] <= 0 ? 'Habis' : 'Tambah ke Cart'; ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="products.php" class="btn btn-primary">Lihat Semua Produk</a>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="mb-5">
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($featuredProducts); ?></div>
                <div class="stat-label">Produk Tersedia</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($categories); ?></div>
                <div class="stat-label">Kategori</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">100+</div>
                <div class="stat-label">Pelanggan Puas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">24/7</div>
                <div class="stat-label">Layanan Online</div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="mb-5">
        <h2 class="text-center mb-4">Kenapa Memilih UKM Catalog?</h2>
        <div class="products-grid">
            <div class="product-card">
                <div class="product-info text-center">
                    <h3 class="product-title">🛡️ Aman & Terpercaya</h3>
                    <p>Semua produk diverifikasi kualitasnya dan transaksi aman</p>
                </div>
            </div>
            <div class="product-card">
                <div class="product-info text-center">
                    <h3 class="product-title">🚚 Pengiriman Cepat</h3>
                    <p>Pengiriman ke seluruh Indonesia dengan berbagai pilihan ekspedisi</p>
                </div>
            </div>
            <div class="product-card">
                <div class="product-info text-center">
                    <h3 class="product-title">💰 Harga Terjangkau</h3>
                    <p>Harga langsung dari produsen tanpa perantara</p>
                </div>
            </div>
            <div class="product-card">
                <div class="product-info text-center">
                    <h3 class="product-title">🤝 Dukung UMKM</h3>
                    <p>Setiap pembelian membantu pertumbuhan UMKM lokal</p>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
// Search functionality with debounce
const searchInput = document.getElementById('searchInput');
let searchTimeout;

searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const searchTerm = this.value.toLowerCase();
        const products = document.querySelectorAll('.product-card');

        products.forEach(product => {
            const title = product.querySelector('.product-title');
            const description = product.querySelector('.product-description');
            
            if (title && description) {
                const titleText = title.textContent.toLowerCase();
                const descText = description.textContent.toLowerCase();
                
                if (titleText.includes(searchTerm) || descText.includes(searchTerm)) {
                    product.style.display = 'block';
                } else {
                    product.style.display = searchTerm ? 'none' : 'block';
                }
            }
        });
    }, 300);
});

// Smooth scroll for internal links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth'
            });
        }
    });
});

// Add loading animation for add to cart
const addToCartButtons = document.querySelectorAll('.add-to-cart');
addToCartButtons.forEach(button => {
    button.addEventListener('click', function() {
        const originalText = this.textContent;
        this.innerHTML = '<span class="loading"></span>';
        this.disabled = true;
        
        setTimeout(() => {
            this.textContent = originalText;
            this.disabled = false;
        }, 1000);
    });
});
</script>

<?php include 'includes/footer.php'; ?>