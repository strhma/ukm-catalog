<?php
class Auth {
    private $db;
    private $sessionStarted = false;

    public function __construct($db) {
        $this->db = $db;
        $this->ensureSessionStarted();
    }

    private function ensureSessionStarted() {
        if (!$this->sessionStarted && session_status() === PHP_SESSION_NONE) {
            // Configure session
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Strict');
            
            // Set session save path if needed
            $sessionPath = ini_get('session.save_path');
            if (empty($sessionPath) || !is_writable($sessionPath)) {
                $customPath = sys_get_temp_dir() . '/sessions';
                if (!is_dir($customPath)) {
                    mkdir($customPath, 0777, true);
                }
                ini_set('session.save_path', $customPath);
            }
            
            session_start();
            $this->sessionStarted = true;
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['last_regeneration']) || time() - $_SESSION['last_regeneration'] > 300) {
                $this->regenerateSession();
            }
        }
    }

    private function regenerateSession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }

    public function login($email, $password, $remember = false) {
        try {
            // Validate input
            if (empty($email) || empty($password)) {
                return false;
            }
            
            // Get user data
            $query = "SELECT id, name, email, password, role, status, failed_attempts, locked_until 
                      FROM users WHERE email = ? AND status = 'active'";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return false;
            }

            // Check if account is locked
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                return false;
            }

            // Verify password
            if (password_verify($password, $user['password'])) {
                // Reset failed attempts
                $this->resetFailedAttempts($user['id']);
                
                // Regenerate session ID
                $this->regenerateSession();
                
                // Set session data
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

                // Handle remember me
                if ($remember) {
                    $this->setRememberMe($user['id']);
                }

                // Log successful login
                $this->logActivity($user['id'], 'login_success', 'User logged in successfully');

                return true;
            } else {
                // Increment failed attempts
                $this->incrementFailedAttempts($user['id']);
                
                // Log failed login
                $this->logActivity($user['id'], 'login_failed', 'Failed login attempt');
                
                return false;
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }

    private function incrementFailedAttempts($userId) {
        try {
            $query = "UPDATE users SET failed_attempts = failed_attempts + 1 WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$userId]);
            
            // Lock account after 5 failed attempts
            $query = "SELECT failed_attempts FROM users WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['failed_attempts'] >= 5) {
                $lockTime = date('Y-m-d H:i:s', time() + 900); // 15 minutes
                $query = "UPDATE users SET locked_until = ? WHERE id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$lockTime, $userId]);
            }
        } catch (Exception $e) {
            error_log("Failed to increment login attempts: " . $e->getMessage());
        }
    }

    private function resetFailedAttempts($userId) {
        try {
            $query = "UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("Failed to reset login attempts: " . $e->getMessage());
        }
    }

    private function setRememberMe($userId) {
        try {
            $token = bin2hex(random_bytes(32));
            $expiry = time() + (86400 * 30); // 30 days
            
            // Set cookie with secure attributes
            setcookie('remember_token', $token, [
                'expires' => $expiry,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Strict'
            ]);

            // Update database
            $query = "UPDATE users SET remember_token = ?, remember_expiry = ? WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$token, date('Y-m-d H:i:s', $expiry), $userId]);
        } catch (Exception $e) {
            error_log("Failed to set remember me: " . $e->getMessage());
        }
    }

    public function register($name, $email, $password, $phone = '') {
        try {
            // Validate input
            if (empty($name) || empty($email) || empty($password)) {
                return ['success' => false, 'message' => 'All fields are required'];
            }

            if (!validateEmail($email)) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }

            if (strlen($password) < 6) {
                return ['success' => false, 'message' => 'Password must be at least 6 characters'];
            }

            // Check if email exists
            $query = "SELECT id FROM users WHERE email = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email already exists'];
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO users (name, email, password, phone, role, status, created_at) 
                      VALUES (?, ?, ?, ?, 'user', 'active', NOW())";
            $stmt = $this->db->prepare($query);
            
            if ($stmt->execute([$name, $email, $hashedPassword, $phone])) {
                $userId = $this->db->lastInsertId();
                
                // Log registration
                $this->logActivity($userId, 'register', 'User registered successfully');
                
                return ['success' => true, 'message' => 'Registration successful'];
            } else {
                return ['success' => false, 'message' => 'Registration failed'];
            }
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }

    public function logout() {
        try {
            // Clear remember token in database
            if (isset($_SESSION['user_id'])) {
                $query = "UPDATE users SET remember_token = NULL, remember_expiry = NULL WHERE id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$_SESSION['user_id']]);
                
                // Log logout
                $this->logActivity($_SESSION['user_id'], 'logout', 'User logged out');
            }

            // Destroy session
            $_SESSION = [];
            
            // Delete session cookie
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params['path'], $params['domain'],
                    $params['secure'], $params['httponly']
                );
            }
            
            session_destroy();

            // Clear remember cookie
            setcookie('remember_token', '', time() - 3600, '/');

            return true;
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            return false;
        }
    }

    public function checkRememberLogin() {
        if (!$this->isLoggedIn() && isset($_COOKIE['remember_token'])) {
            try {
                $query = "SELECT id, name, email, role FROM users 
                          WHERE remember_token = ? AND remember_expiry > NOW() AND status = 'active'";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$_COOKIE['remember_token']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $this->regenerateSession();
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['last_activity'] = time();
                    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

                    // Log remember login
                    $this->logActivity($user['id'], 'remember_login', 'User logged in via remember token');

                    return true;
                }
            } catch (Exception $e) {
                error_log("Remember login error: " . $e->getMessage());
            }
        }
        return false;
    }

    public function isLoggedIn() {
        if (isset($_SESSION['user_id'])) {
            // Check session timeout
            $timeout = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 3600;
            if (time() - $_SESSION['last_activity'] > $timeout) {
                $this->logout();
                return false;
            }
            
            // Update last activity
            $_SESSION['last_activity'] = time();
            
            // Check IP and user agent consistency
            $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
            $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $currentIp) {
                // IP changed, might be session hijacking
                $this->logout();
                return false;
            }
            
            if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $currentUserAgent) {
                // User agent changed, might be session hijacking
                $this->logout();
                return false;
            }
            
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
        try {
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

                // Log password reset request
                $this->logActivity($user['id'], 'password_reset_request', 'Password reset requested');

                return ['success' => true, 'token' => $token];
            }

            return ['success' => false, 'message' => 'Email not found'];
        } catch (Exception $e) {
            error_log("Password reset request error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to process request'];
        }
    }

    public function resetPassword($token, $newPassword) {
        try {
            $query = "SELECT id FROM users WHERE reset_token = ? AND reset_expiry > NOW()";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if (strlen($newPassword) < 6) {
                    return ['success' => false, 'message' => 'Password must be at least 6 characters'];
                }

                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $query = "UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$hashedPassword, $user['id']]);

                // Log password reset
                $this->logActivity($user['id'], 'password_reset', 'Password reset successful');

                return ['success' => true, 'message' => 'Password reset successful'];
            }

            return ['success' => false, 'message' => 'Invalid or expired token'];
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to reset password'];
        }
    }

    private function logActivity($userId, $action, $details) {
        try {
            $query = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
                      VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $userId,
                $action,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }

    // Get current user info
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email'],
                'role' => $_SESSION['user_role']
            ];
        }
        return null;
    }
}
?>