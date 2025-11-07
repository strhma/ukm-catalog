<?php
require_once 'config/config.php';

$auth = new Auth($db);

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid request');
        header('Location: register.php');
        exit();
    }

    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Name is required';
    }
    
    if (empty($email) || !validateEmail($email)) {
        $errors[] = 'Valid email is required';
    }
    
    if (empty($password) || strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }

    if (empty($errors)) {
        $result = $auth->register($name, $email, $password, $phone);
        
        if ($result['success']) {
            setFlashMessage('success', 'Registration successful! Please login.');
            header('Location: login.php');
            exit();
        } else {
            setFlashMessage('error', $result['message']);
        }
    } else {
        setFlashMessage('error', implode('<br>', $errors));
    }
}

include 'includes/header.php';
?>

<div class="hero">
    <div class="container">
        <h1>Bergabung dengan UKM Catalog</h1>
        <p>Daftar sekarang dan nikmati produk berkualitas dari UMKM lokal</p>
    </div>
</div>

<div class="container">
    <div class="form-container">
        <h2 class="text-center mb-3">Register</h2>
        
        <?php displayFlashMessage(); ?>
        
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" class="form-control" id="phone" name="phone">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
                <small class="form-text text-muted">Password must be at least 6 characters</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">Register</button>
        </form>
        
        <div class="text-center mt-3">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>
</div>

<script>
// Client-side validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.needs-validation');
    const nameInput = document.getElementById('name');
    const emailInput = document.getElementById('email');
    const phoneInput = document.getElementById('phone');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');

    // Real-time validation
    nameInput.addEventListener('blur', function() {
        validateName(this);
    });

    emailInput.addEventListener('blur', function() {
        validateEmail(this);
    });

    phoneInput.addEventListener('blur', function() {
        validatePhone(this);
    });

    passwordInput.addEventListener('blur', function() {
        validatePassword(this);
    });

    confirmPasswordInput.addEventListener('blur', function() {
        validateConfirmPassword(this);
    });

    function validateName(input) {
        const name = input.value.trim();
        
        if (!name) {
            showError(input, 'Name is required');
            return false;
        } else if (name.length < 2) {
            showError(input, 'Name must be at least 2 characters');
            return false;
        } else {
            clearError(input);
            return true;
        }
    }

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

    function validatePhone(input) {
        const phone = input.value.trim();
        const phoneRegex = /^[0-9]{10,13}$/;
        
        if (phone && !phoneRegex.test(phone)) {
            showError(input, 'Please enter a valid phone number (10-13 digits)');
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

    function validateConfirmPassword(input) {
        const confirmPassword = input.value;
        const password = passwordInput.value;
        
        if (!confirmPassword) {
            showError(input, 'Please confirm your password');
            return false;
        } else if (confirmPassword !== password) {
            showError(input, 'Passwords do not match');
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

        if (!validateName(nameInput)) {
            isValid = false;
        }

        if (!validateEmail(emailInput)) {
            isValid = false;
        }

        if (!validatePhone(phoneInput)) {
            isValid = false;
        }

        if (!validatePassword(passwordInput)) {
            isValid = false;
        }

        if (!validateConfirmPassword(confirmPasswordInput)) {
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