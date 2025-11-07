<?php
require_once 'config/config.php';

$auth = new Auth($db);

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$pageTitle = 'Reset Password';

// Check token
$token = $_GET['token'] ?? '';

if (empty($token)) {
    setFlashMessage('error', 'Invalid reset token');
    header('Location: login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid request');
        header('Location: reset-password.php?token=' . $token);
        exit();
    }

    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    // Validation
    $errors = [];
    
    if (empty($password) || strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }

    if (empty($errors)) {
        $result = $auth->resetPassword($token, $password);
        
        if ($result['success']) {
            setFlashMessage('success', 'Password reset successful! Please login with your new password.');
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
        <h1>Reset Password</h1>
        <p>Enter your new password</p>
    </div>
</div>

<div class="container">
    <div class="form-container">
        <h2 class="text-center mb-3">Set New Password</h2>
        
        <?php displayFlashMessage(); ?>
        
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" class="form-control" id="password" name="password" required 
                       placeholder="Enter new password">
                <small class="form-text text-muted">Password must be at least 6 characters</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required 
                       placeholder="Confirm new password">
            </div>
            
            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
        </form>
        
        <div class="text-center mt-3">
            <p>Remember your password? <a href="login.php">Login here</a></p>
        </div>
    </div>
</div>

<script>
// Client-side validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.needs-validation');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');

    passwordInput.addEventListener('blur', function() {
        validatePassword(this);
    });

    confirmPasswordInput.addEventListener('blur', function() {
        validateConfirmPassword(this);
    });

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

    form.addEventListener('submit', function(e) {
        let isValid = true;

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