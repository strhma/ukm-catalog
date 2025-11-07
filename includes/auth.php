<?php
class Auth {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function login($email, $password, $remember = false) {
        $query = "SELECT id, name, email, password, role FROM users WHERE email = ? AND status = 'active'";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['last_activity'] = time();

            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expiry = time() + (86400 * 30); // 30 days
                
                setcookie('remember_token', $token, [
                    'expires' => $expiry,
                    'path' => '/',
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);

                $query = "UPDATE users SET remember_token = ?, remember_expiry = ? WHERE id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$token, date('Y-m-d H:i:s', $expiry), $user['id']]);
            }

            return true;
        }

        return false;
    }

    public function register($name, $email, $password, $phone = '') {
        // Check if email exists
        $query = "SELECT id FROM users WHERE email = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already exists'];
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $query = "INSERT INTO users (name, email, password, phone, role, status, created_at) VALUES (?, ?, ?, ?, 'user', 'active', NOW())";
        $stmt = $this->db->prepare($query);
        
        if ($stmt->execute([$name, $email, $hashedPassword, $phone])) {
            return ['success' => true, 'message' => 'Registration successful'];
        }

        return ['success' => false, 'message' => 'Registration failed'];
    }

    public function logout() {
        // Clear remember token
        if (isset($_SESSION['user_id'])) {
            $query = "UPDATE users SET remember_token = NULL, remember_expiry = NULL WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$_SESSION['user_id']]);
        }

        // Clear session
        $_SESSION = [];
        session_destroy();

        // Clear cookies
        setcookie('remember_token', '', time() - 3600, '/');

        return true;
    }

    public function checkRememberLogin() {
        if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
            $query = "SELECT id, name, email, role FROM users WHERE remember_token = ? AND remember_expiry > NOW() AND status = 'active'";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$_COOKIE['remember_token']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['last_activity'] = time();

                return true;
            }
        }
        return false;
    }

    public function isLoggedIn() {
        if (isset($_SESSION['user_id'])) {
            // Check session timeout
            if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
                $this->logout();
                return false;
            }
            $_SESSION['last_activity'] = time();
            return true;
        }

        // Try remember login
        return $this->checkRememberLogin();
    }

    public function hasRole($role) {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            setFlashMessage('error', 'Please login to continue');
            header('Location: ' . BASE_URL . 'login.php');
            exit();
        }
    }

    public function requireRole($role) {
        $this->requireLogin();
        if (!$this->hasRole($role)) {
            setFlashMessage('error', 'Access denied');
            header('Location: ' . BASE_URL . 'index.php');
            exit();
        }
    }

    public function resetPasswordRequest($email) {
        $query = "SELECT id FROM users WHERE email = ? AND status = 'active'";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expiry = time() + 3600; // 1 hour
            
            $query = "UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$token, date('Y-m-d H:i:s', $expiry), $user['id']]);

            // In real application, send email with reset link
            // For demo, we'll return the token
            return ['success' => true, 'token' => $token];
        }

        return ['success' => false, 'message' => 'Email not found'];
    }

    public function resetPassword($token, $newPassword) {
        $query = "SELECT id FROM users WHERE reset_token = ? AND reset_expiry > NOW()";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $query = "UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$hashedPassword, $user['id']]);

            return ['success' => true, 'message' => 'Password reset successful'];
        }

        return ['success' => false, 'message' => 'Invalid or expired token'];
    }
}
?>