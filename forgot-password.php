<?php
require_once 'config/config.php';

$auth = new Auth($db);

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$pageTitle = 'Forgot Password';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid request');
        header('Location: forgot-password.php');
        exit();
    }

    $email = sanitizeInput($_POST['email']);
    
    if (empty($email) || !validateEmail($email)) {
        setFlashMessage('error', 'Please enter a valid email address');
    } else {
        $result = $auth->resetPasswordRequest($email);
        
        if ($result['success']) {
            // In real application, send email
            // For demo, we'll show the token
            setFlashMessage('success', 'Password reset instructions have been sent to your email. Token: ' . $result['token']);
            header('Location: reset-password.php?token=' . $result['token']);
            exit();
        } else {
            setFlashMessage('error', $result['message']);
        }
    }
}

include 'includes/header.php';
?>

<div class="hero">
    <div class="container">
        <h1>Reset Password</h1>
        <p>Enter your email address to receive password reset instructions</p>
    </div>
</div>

<div class="container">
    <div class="form-container">
        <h2 class="text-center mb-3">Forgot Password</h2>
        
        <?php displayFlashMessage(); ?>
        
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" required 
                       placeholder="Enter your email address">
            </div>
            
            <button type="submit" class="btn btn-primary w-100">Send Reset Instructions</button>
        </form>
        
        <div class="text-center mt-3">
            <p>Remember your password? <a href="login.php">Login here</a></p>
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>
</div>

<script>
// Client-side validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.needs-validation');
    const emailInput = document.getElementById('email');

    emailInput.addEventListener('blur', function() {
        validateEmail(this);
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
        if (!validateEmail(emailInput)) {
            e.preventDefault();
            e.stopPropagation();
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>