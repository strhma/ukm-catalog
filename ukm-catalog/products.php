<?php
require_once 'config/config.php';

$auth = new Auth($db);
$auth->checkRememberLogin();

$pageTitle = 'Products';

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Search and filter
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$categoryId = isset($_GET['category']) ? intval($_GET['category']) : 0;
$sort = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'newest';

// Build query
$whereConditions = ['p.status = "active"'];
$params = [];

if ($search) {
    $whereConditions[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($categoryId > 0) {
    $whereConditions[] = 'p.category_id = ?';
    $params[] = $categoryId;
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total 
               FROM products p 
               WHERE $whereClause";
$countStmt = $db->prepare($countQuery);
$countStmt->execute($params);
$totalProducts = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalProducts / $limit);

// Get products
$orderBy = 'p.created_at DESC';
switch ($sort) {
    case 'price_low':
        $orderBy = 'p.price ASC';
        break;
    case 'price_high':
        $orderBy = 'p.price DESC';
        break;
    case 'name':
        $orderBy = 'p.name ASC';
        break;
    case 'popular':
        $orderBy = 'p.stock DESC';
        break;
}

$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE $whereClause 
          ORDER BY $orderBy 
          LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$query = "SELECT * FROM categories WHERE status = 'active' ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="hero" style="padding: 2rem 0;">
    <div class="container">
        <h1>Produk Kami</h1>
        <p>Temukan produk berkualitas dari UMKM lokal</p>
    </div>
</div>

<div class="container">
    <?php displayFlashMessage(); ?>
    
    <!-- Search and Filter Section -->
    <div class="form-container mb-4">
        <form method="GET" class="d-flex flex-wrap gap-3 align-items-end">
            <div class="flex-grow-1">
                <label for="search">Cari Produk</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Nama produk..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div>
                <label for="category">Kategori</label>
                <select class="form-control" id="category" name="category">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" 
                                <?php echo $categoryId == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="sort">Urutkan</label>
                <select class="form-control" id="sort" name="sort">
                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                    <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>Nama A-Z</option>
                    <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Harga Terendah</option>
                    <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Harga Tertinggi</option>
                    <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Populer</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="products.php" class="btn btn-warning">Reset</a>
        </form>
    </div>

    <!-- Results Info -->
    <div class="mb-4">
        <p class="text-muted">
            Menampilkan <?php echo count($products); ?> dari <?php echo $totalProducts; ?> produk
            <?php if ($search): ?>
                untuk pencarian "<?php echo htmlspecialchars($search); ?>"
            <?php endif; ?>
            <?php if ($categoryId > 0): ?>
                dalam kategori "<?php echo htmlspecialchars(array_column($categories, 'name', 'id')[$categoryId] ?? ''); ?>"
            <?php endif; ?>
        </p>
    </div>

    <!-- Products Grid -->
    <?php if (empty($products)): ?>
        <div class="text-center py-5">
            <h3>Produk tidak ditemukan</h3>
            <p>Maaf, tidak ada produk yang sesuai dengan pencarian Anda.</p>
            <a href="products.php" class="btn btn-primary">Lihat Semua Produk</a>
        </div>
    <?php else: ?>
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <?php if ($product['image']): ?>
                        <img src="<?php echo UPLOAD_URL . $product['image']; ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                             class="product-image">
                    <?php else: ?>
                        <div class="product-image" style="background: #f1f2f6; display: flex; align-items: center; justify-content: center;">
                            <span style="color: #747d8c;">No Image</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="product-info">
                        <div class="mb-2">
                            <small class="text-muted"><?php echo htmlspecialchars($product['category_name'] ?? 'Tidak berkategori'); ?></small>
                        </div>
                        
                        <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="product-description"><?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?>...</p>
                        
                        <div class="product-price"><?php echo formatRupiah($product['price']); ?></div>
                        
                        <div class="product-stock">
                            Stok: <?php echo $product['stock']; ?> unit
                            <?php if ($product['stock'] <= 5 && $product['stock'] > 0): ?>
                                <span style="color: #ff4757;"> (Stok rendah)</span>
                            <?php elseif ($product['stock'] <= 0): ?>
                                <span style="color: #ff4757;"> (Habis)</span>
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
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="text-center">
            <?php
            $baseUrl = 'products.php?';
            $paramsArray = [];
            if ($search) $paramsArray[] = 'search=' . urlencode($search);
            if ($categoryId > 0) $paramsArray[] = 'category=' . $categoryId;
            if ($sort != 'newest') $paramsArray[] = 'sort=' . $sort;
            
            $queryString = implode('&', $paramsArray);
            if ($queryString) {
                $baseUrl .= $queryString . '&';
            }
            
            echo paginate($totalProducts, $limit, $page, $baseUrl);
            ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Auto-submit form when filters change
document.addEventListener('DOMContentLoaded', function() {
    const categorySelect = document.getElementById('category');
    const sortSelect = document.getElementById('sort');
    
    categorySelect.addEventListener('change', function() {
        this.form.submit();
    });
    
    sortSelect.addEventListener('change', function() {
        this.form.submit();
    });
    
    // Search on Enter key
    const searchInput = document.getElementById('search');
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.form.submit();
        }
    });
});

// Add loading animation for add to cart
const addToCartButtons = document.querySelectorAll('.add-to-cart');
addToCartButtons.forEach(button => {
    button.addEventListener('click', function() {
        if (!this.disabled) {
            const originalText = this.textContent;
            this.innerHTML = '<span class="loading"></span>';
            this.disabled = true;
            
            setTimeout(() => {
                this.textContent = originalText;
                this.disabled = false;
            }, 1000);
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>