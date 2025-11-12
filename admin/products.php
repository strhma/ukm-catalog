<?php
require_once '../config/config.php';

$auth = new Auth($db);
$auth->requireLogin();
$auth->requireRole('admin');

$pageTitle = 'Manage Products';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid request');
        header('Location: products.php');
        exit();
    }

    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'delete':
            $productId = intval($_POST['product_id']);
            
            // Get product image to delete
            $query = "SELECT image FROM products WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete product
            $query = "DELETE FROM products WHERE id = ?";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$productId])) {
                // Delete image file
                if ($product['image'] && file_exists(UPLOAD_PATH . $product['image'])) {
                    unlink(UPLOAD_PATH . $product['image']);
                }
                
                setFlashMessage('success', 'Product deleted successfully');
            } else {
                setFlashMessage('error', 'Failed to delete product');
            }
            break;
            
        case 'toggle_status':
            $productId = intval($_POST['product_id']);
            $currentStatus = $_POST['current_status'];
            $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
            
            $query = "UPDATE products SET status = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$newStatus, $productId])) {
                setFlashMessage('success', 'Product status updated');
            } else {
                setFlashMessage('error', 'Failed to update status');
            }
            break;
    }
    
    header('Location: products.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Search and filter
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$categoryId = isset($_GET['category']) ? intval($_GET['category']) : 0;
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';

// Build query
$whereConditions = ['1=1'];
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

if ($status !== 'all') {
    $whereConditions[] = 'p.status = ?';
    $params[] = $status;
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM products p WHERE $whereClause";
$countStmt = $db->prepare($countQuery);
$countStmt->execute($params);
$totalProducts = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalProducts / $limit);

// Get products
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE $whereClause 
          ORDER BY p.created_at DESC 
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

include '../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/style.css">
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Manage Products</h1>
        <div class="d-flex gap-2">
            <a href="add-product.php" class="btn btn-success">➕ Add Product</a>
            <a href="export.php?type=products" class="btn btn-secondary">📥 Export</a>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Search and Filter -->
    <div class="form-container mb-4">
        <form method="GET" class="d-flex flex-wrap gap-3 align-items-end">
            <div class="flex-grow-1">
                <label for="search">Search Products</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Product name or description..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div>
                <label for="category">Category</label>
                <select class="form-control" id="category" name="category">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" 
                                <?php echo $categoryId == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="status">Status</label>
                <select class="form-control" id="status" name="status">
                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="products.php" class="btn btn-warning">Reset</a>
        </form>
    </div>
    
    <!-- Results Info -->
    <div class="mb-4">
        <p class="text-muted">
            Showing <?php echo count($products); ?> of <?php echo $totalProducts; ?> products
            <?php if ($search): ?>
                for search "<?php echo htmlspecialchars($search); ?>"
            <?php endif; ?>
            <?php if ($categoryId > 0): ?>
                in category "<?php echo htmlspecialchars(array_column($categories, 'name', 'id')[$categoryId] ?? ''); ?>"
            <?php endif; ?>
        </p>
    </div>
    
    <!-- Products Table -->
    <?php if (empty($products)): ?>
        <div class="form-container text-center">
            <div style="font-size: 3rem; margin-bottom: 1rem;">📦</div>
            <h3>No products found</h3>
            <p class="text-muted">No products match your search criteria.</p>
            <a href="add-product.php" class="btn btn-success">Add First Product</a>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo $product['id']; ?></td>
                            <td>
                                <?php if ($product['image']): ?>
                                    <img src="<?php echo UPLOAD_URL . $product['image']; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                         style="width: 50px; height: 50px; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 50px; height: 50px; background: #f1f2f6; display: flex; align-items: center; justify-content: center;">
                                        <span style="color: #747d8c; font-size: 0.7rem;">No</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                                <small class="text-muted">
                                    <?php echo substr(htmlspecialchars($product['description']), 0, 50); ?>...
                                </small>
                            </td>
                            <td><?php echo htmlspecialchars($product['category_name'] ?? '-'); ?></td>
                            <td><?php echo formatRupiah($product['price']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $product['stock'] <= 5 ? 'warning' : 'success'; ?>">
                                    <?php echo $product['stock']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $product['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($product['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($product['created_at'])); ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="edit-product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                    <a href="../product-detail.php?id=<?php echo $product['id']; ?>" class="btn btn-info btn-sm" target="_blank">View</a>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to <?php echo $product['status'] === 'active' ? 'deactivate' : 'activate'; ?> this product?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <input type="hidden" name="current_status" value="<?php echo $product['status']; ?>">
                                        <button type="submit" class="btn btn-<?php echo $product['status'] === 'active' ? 'warning' : 'success'; ?> btn-sm">
                                            <?php echo $product['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="text-center mt-4">
            <?php
            $baseUrl = 'products.php?';
            $paramsArray = [];
            if ($search) $paramsArray[] = 'search=' . urlencode($search);
            if ($categoryId > 0) $paramsArray[] = 'category=' . $categoryId;
            if ($status !== 'all') $paramsArray[] = 'status=' . $status;
            
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
    const statusSelect = document.getElementById('status');
    
    categorySelect.addEventListener('change', function() {
        this.form.submit();
    });
    
    statusSelect.addEventListener('change', function() {
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

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch(e.key) {
            case 'n':
                e.preventDefault();
                window.location.href = 'add-product.php';
                break;
            case 'f':
                e.preventDefault();
                document.getElementById('search').focus();
                break;
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>