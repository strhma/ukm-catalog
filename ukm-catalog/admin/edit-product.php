<?php
require_once '../config/config.php';

$auth = new Auth($db);
$auth->requireLogin();
$auth->requireRole('admin');

$pageTitle = 'Edit Product';

// Get product ID
$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($productId <= 0) {
    setFlashMessage('error', 'Product not found');
    header('Location: products.php');
    exit();
}

// Get product data
$query = "SELECT * FROM products WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    setFlashMessage('error', 'Product not found');
    header('Location: products.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid request');
        header('Location: edit-product.php?id=' . $productId);
        exit();
    }

    // Get form data
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $categoryId = intval($_POST['category_id']);
    $status = sanitizeInput($_POST['status']);

    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Product name is required';
    }
    
    if ($price <= 0) {
        $errors[] = 'Price must be greater than 0';
    }
    
    if ($stock < 0) {
        $errors[] = 'Stock cannot be negative';
    }
    
    if ($categoryId <= 0) {
        $errors[] = 'Please select a category';
    }

    // Handle file upload
    $image = $product['image']; // Keep existing image by default
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadFile($_FILES['image'], ['image/jpeg', 'image/png', 'image/gif'], 2097152);
        
        if ($uploadResult['success']) {
            // Delete old image
            if ($product['image'] && file_exists(UPLOAD_PATH . $product['image'])) {
                unlink(UPLOAD_PATH . $product['image']);
            }
            $image = $uploadResult['filename'];
        } else {
            $errors[] = 'Image upload failed: ' . $uploadResult['message'];
        }
    }

    if (empty($errors)) {
        try {
            $query = "UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, stock = ?, image = ?, status = ?, updated_at = NOW() 
                      WHERE id = ?";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$categoryId, $name, $description, $price, $stock, $image, $status, $productId])) {
                // Log activity
                $query = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
                          VALUES (?, 'product_updated', ?, ?, ?, NOW())";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $_SESSION['user_id'],
                    "Product '$name' (ID: $productId) updated",
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                setFlashMessage('success', 'Product updated successfully!');
                
                // Refresh product data
                $query = "SELECT * FROM products WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$productId]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                setFlashMessage('error', 'Failed to update product');
            }
        } catch (Exception $e) {
            setFlashMessage('error', 'Error: ' . $e->getMessage());
        }
    } else {
        setFlashMessage('error', implode('<br>', $errors));
    }
}

// Get categories
$query = "SELECT * FROM categories WHERE status = 'active' ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Edit Product</h1>
        <div class="d-flex gap-2">
            <a href="products.php" class="btn btn-secondary">← Back to Products</a>
            <a href="../product-detail.php?id=<?php echo $product['id']; ?>" class="btn btn-info" target="_blank">View Product</a>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="form-container">
        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-group">
                <label for="name">Product Name *</label>
                <input type="text" class="form-control" id="name" name="name" required 
                       value="<?php echo htmlspecialchars($product['name']); ?>"
                       placeholder="Enter product name">
            </div>
            
            <div class="form-group">
                <label for="category_id">Category *</label>
                <select class="form-control" id="category_id" name="category_id" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" 
                                <?php echo $product['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-text text-muted">
                    Don't see the category you need? <a href="add-category.php" target="_blank">Add new category</a>
                </small>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4" 
                          placeholder="Enter product description"><?php echo htmlspecialchars($product['description']); ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="price">Price *</label>
                        <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required 
                               value="<?php echo htmlspecialchars($product['price']); ?>"
                               placeholder="0.00">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="stock">Stock *</label>
                        <input type="number" class="form-control" id="stock" name="stock" min="0" required 
                               value="<?php echo htmlspecialchars($product['stock']); ?>"
                               placeholder="0">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="active" <?php echo $product['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $product['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="image">Product Image</label>
                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                <small class="form-text text-muted">
                    Allowed formats: JPG, PNG, GIF. Max size: 2MB. Leave empty to keep current image.
                </small>
                
                <?php if ($product['image']): ?>
                    <div class="mt-3">
                        <label>Current Image:</label><br>
                        <img src="<?php echo UPLOAD_URL . $product['image']; ?>" 
                             alt="Current product image" 
                             style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                <?php endif; ?>
                
                <div id="imagePreview" style="margin-top: 10px; display: none;">
                    <label>New Image Preview:</label><br>
                    <img id="previewImg" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">💾 Update Product</button>
                <button type="button" class="btn btn-secondary" onclick="resetForm()">Reset</button>
                <a href="products.php" class="btn btn-danger">Cancel</a>
            </div>
        </form>
    </div>
    
    <!-- Product History -->
    <div class="form-container mt-5">
        <h3>Product Information</h3>
        <div class="table-container">
            <table class="table">
                <tr>
                    <td><strong>Product ID:</strong></td>
                    <td><?php echo $product['id']; ?></td>
                </tr>
                <tr>
                    <td><strong>Created:</strong></td>
                    <td><?php echo date('d F Y H:i', strtotime($product['created_at'])); ?></td>
                </tr>
                <tr>
                    <td><strong>Last Updated:</strong></td>
                    <td><?php echo $product['updated_at'] ? date('d F Y H:i', strtotime($product['updated_at'])) : 'Never'; ?></td>
                </tr>
                <tr>
                    <td><strong>Image Path:</strong></td>
                    <td><?php echo $product['image'] ? UPLOAD_URL . $product['image'] : 'No image uploaded'; ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>

<script>
// Image preview
document.getElementById('image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('imagePreview');
    const img = document.getElementById('previewImg');
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
    }
});

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
            message = 'This field is required';
            isValid = false;
        } else if (field.type === 'number' && field.min && parseFloat(value) < parseFloat(field.min)) {
            message = 'Value must be at least ' + field.min;
            isValid = false;
        } else if (field.name === 'price' && parseFloat(value) <= 0) {
            message = 'Price must be greater than 0';
            isValid = false;
        } else if (field.name === 'stock' && parseInt(value) < 0) {
            message = 'Stock cannot be negative';
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
        }
    });
});

function resetForm() {
    // Reset form to original values
    document.getElementById('name').value = '<?php echo addslashes($product['name']); ?>';
    document.getElementById('category_id').value = '<?php echo $product['category_id']; ?>';
    document.getElementById('description').value = '<?php echo addslashes($product['description']); ?>';
    document.getElementById('price').value = '<?php echo $product['price']; ?>';
    document.getElementById('stock').value = '<?php echo $product['stock']; ?>';
    document.getElementById('status').value = '<?php echo $product['status']; ?>';
    
    // Hide image preview
    document.getElementById('imagePreview').style.display = 'none';
    document.getElementById('image').value = '';
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch(e.key) {
            case 's':
                e.preventDefault();
                document.querySelector('form').submit();
                break;
            case 'r':
                e.preventDefault();
                resetForm();
                break;
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>