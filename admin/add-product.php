<?php
require_once '../config/config.php';

$auth = new Auth($db);
$auth->requireLogin();
$auth->requireRole('admin');

$pageTitle = 'Add Product';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid request');
        header('Location: add-product.php');
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
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadFile($_FILES['image'], ['image/jpeg', 'image/png', 'image/gif'], 2097152);
        
        if ($uploadResult['success']) {
            $image = $uploadResult['filename'];
        } else {
            $errors[] = 'Image upload failed: ' . $uploadResult['message'];
        }
    }

    if (empty($errors)) {
        try {
            $query = "INSERT INTO products (category_id, name, description, price, stock, image, status, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$categoryId, $name, $description, $price, $stock, $image, $status])) {
                $productId = $db->lastInsertId();
                
                // Log activity
                $query = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
                          VALUES (?, 'product_created', ?, ?, ?, NOW())";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $_SESSION['user_id'],
                    "Product '$name' created with ID $productId",
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                setFlashMessage('success', 'Product added successfully!');
                header('Location: edit-product.php?id=' . $productId);
                exit();
            } else {
                setFlashMessage('error', 'Failed to add product');
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
<link rel="stylesheet" href="../assets/css/style.css">
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Add New Product</h1>
        <a href="products.php" class="btn btn-secondary">← Back to Products</a>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="form-container">
        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-group">
                <label for="name">Product Name *</label>
                <input type="text" class="form-control" id="name" name="name" required 
                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                       placeholder="Enter product name">
            </div>
            
            <div class="form-group">
                <label for="category_id">Category *</label>
                <select class="form-control" id="category_id" name="category_id" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" 
                                <?php echo isset($_POST['category_id']) && $_POST['category_id'] == $category['id'] ? 'selected' : ''; ?>>
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
                          placeholder="Enter product description"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="price">Price *</label>
                        <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required 
                               value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>"
                               placeholder="0.00">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="stock">Stock *</label>
                        <input type="number" class="form-control" id="stock" name="stock" min="0" required 
                               value="<?php echo htmlspecialchars($_POST['stock'] ?? '0'); ?>"
                               placeholder="0">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="active" <?php echo isset($_POST['status']) && $_POST['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo isset($_POST['status']) && $_POST['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="image">Product Image</label>
                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                <small class="form-text text-muted">
                    Allowed formats: JPG, PNG, GIF. Max size: 2MB
                </small>
                <div id="imagePreview" style="margin-top: 10px; display: none;">
                    <img id="previewImg" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">💾 Save Product</button>
                <button type="button" class="btn btn-secondary" onclick="resetForm()">Reset</button>
                <a href="products.php" class="btn btn-danger">Cancel</a>
            </div>
        </form>
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
    document.querySelector('form').reset();
    document.getElementById('imagePreview').style.display = 'none';
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