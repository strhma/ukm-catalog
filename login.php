<?php
require_once 'config/config.php';

$auth = new Auth($db);

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid request');
        header('Location: login.php');
        exit();
    }

    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    if ($auth->login($email, $password, $remember)) {
        setFlashMessage('success', 'Login successful!');
        
        // Redirect based on role
        if ($auth->hasRole('admin')) {
            header('Location: admin/dashboard.php');
        } else {
            header('Location: index.php');
        }
        exit();
    } else {
        setFlashMessage('error', 'Invalid email or password');
    }
}

include 'includes/header.php';
?>

<div class="hero">
    <div class="container">
        <h1>Selamat Datang di UKM Catalog</h1>
        <p>Platform terpercaya untuk produk UMKM berkualitas</p>
    </div>
</div>

<div class="container">
    <div class="form-container">
        <h2 class="text-center mb-3">Login</h2>
        
        <?php displayFlashMessage(); ?>
        
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label class="d-flex align-items-center">
                    <input type="checkbox" name="remember" value="1" class="mr-2">
                    Remember me
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        
        <div class="text-center mt-3">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
            <p><a href="forgot-password.php">Forgot your password?</a></p>
        </div>
    </div>
</div>

<script>
// Client-side validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.needs-validation');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    // Real-time validation
    emailInput.addEventListener('blur', function() {
        validateEmail(this);
    });

    passwordInput.addEventListener('blur', function() {
        validatePassword(this);
    });

    function validateEmail(input) {
        const email = input.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (!email) {
            showError(input, 'Email is required');
            return false;
        } else if (!emailRegex.test(email)) {
            showError(input, 'Please enter a valid email address');
            return false;
        } else {
            clearError(input);
            return true;
        }
    }

    function validatePassword(input) {
        const password = input.value;
        
        if (!password) {
            showError(input, 'Password is required');
            return false;
        } else if (password.length < 6) {
            showError(input, 'Password must be at least 6 characters');
            return false;
        } else {
            clearError(input);
            return true;
        }
    }

    function showError(input, message) {
        input.classList.add('is-invalid');
        let feedback = input.parentNode.querySelector('.invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            input.parentNode.appendChild(feedback);
        }
        feedback.textContent = message;
    }

    function clearError(input) {
        input.classList.remove('is-invalid');
        const feedback = input.parentNode.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.remove();
        }
    }

    // Form submission
    form.addEventListener('submit', function(e) {
        let isValid = true;

        if (!validateEmail(emailInput)) {
            isValid = false;
        }

        if (!validatePassword(passwordInput)) {
            isValid = false;
        }

        if (!isValid) {
            e.preventDefault();
            e.stopPropagation();
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>