<?php
/**
 * Authentication Module
 * 
 * Handles login, sessions, and security for Luna Chatbot
 */

// Prevent direct access
if (!defined('LUNA_ROOT')) {
    die('Access denied');
}

// Require database connection
require_once LUNA_ROOT . '/inc/db.php';

class Auth {
    private static $instance = null;
    private $db;
    
    // Maximum failed login attempts before lockout
    const MAX_LOGIN_ATTEMPTS = 5;
    
    // Lockout period in minutes
    const LOCKOUT_TIME = 30;
    
    // Session timeout in seconds (4 hours)
    const SESSION_TIMEOUT = 14400;
    
    private function __construct() {
        $this->db = db()->getConnection();
        $this->startSession();
    }
    
    // Singleton pattern - Get instance
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Start secure session
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session parameters
            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_httponly', 1);
            
            // Use secure cookies in production
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                ini_set('session.cookie_secure', 1);
            }
            
            // Set SameSite attribute to Lax
            session_set_cookie_params([
                'lifetime' => self::SESSION_TIMEOUT,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            
            session_start();
            
            // Check session timeout
            if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > self::SESSION_TIMEOUT)) {
                $this->logout();
            }
            
            // Regenerate session ID periodically to prevent session fixation
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } else if (time() - $_SESSION['created'] > 1800) {
                // Regenerate session ID every 30 minutes
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
            
            $_SESSION['last_activity'] = time();
        }
    }
    
    // Login with username/password
    public function login($username, $password) {
        // Check if account is locked
        if ($this->isAccountLocked($username)) {
            return [
                'success' => false,
                'message' => 'Account is temporarily locked due to too many failed login attempts. Please try again later.'
            ];
        }
        
        // Get user from database
        $stmt = $this->db->prepare("SELECT id, username, password, status FROM admin_users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify user exists and password is correct
        if ($user && $user['status'] === 'active' && password_verify($password, $user['password'])) {
            // Reset failed login attempts on successful login
            $this->resetFailedLoginAttempts($username);
            
            // Update last login timestamp
            $updateStmt = $this->db->prepare("UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = true;
            $_SESSION['last_activity'] = time();
            
            // Regenerate session ID on login
            session_regenerate_id(true);
            
            return [
                'success' => true,
                'message' => 'Login successful'
            ];
        } else {
            // Increment failed login attempts
            $this->incrementFailedLoginAttempts($username);
            
            return [
                'success' => false,
                'message' => 'Invalid username or password'
            ];
        }
    }
    
    // Logout
    public function logout() {
        // Unset all session variables
        $_SESSION = [];
        
        // Delete the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // Destroy the session
        session_destroy();
    }
    
    // Check if user is logged in
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }
    
    // Check if account is locked due to too many failed login attempts
    private function isAccountLocked($username) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as attempt_count, MAX(created_at) as last_attempt
            FROM failed_login_attempts 
            WHERE username = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$username, self::LOCKOUT_TIME]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($result && $result['attempt_count'] >= self::MAX_LOGIN_ATTEMPTS);
    }
    
    // Increment failed login attempts
    private function incrementFailedLoginAttempts($username) {
        $stmt = $this->db->prepare("
            INSERT INTO failed_login_attempts (username, ip_address, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$username, $_SERVER['REMOTE_ADDR']]);
    }
    
    // Reset failed login attempts
    private function resetFailedLoginAttempts($username) {
        $stmt = $this->db->prepare("DELETE FROM failed_login_attempts WHERE username = ?");
        $stmt->execute([$username]);
    }
    
    // Generate CSRF token
    public function generateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    // Verify CSRF token
    public function verifyCsrfToken($token) {
        if (!isset($_SESSION['csrf_token']) || empty($token) || $_SESSION['csrf_token'] !== $token) {
            return false;
        }
        return true;
    }
    
    // Reset password
    public function resetPassword($email) {
        // Check if email exists
        $stmt = $this->db->prepare("SELECT id, username FROM admin_users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'No active account found with that email address'
            ];
        }
        
        // Generate random token
        $token = bin2hex(random_bytes(16));
        $tokenHash = password_hash($token, PASSWORD_DEFAULT);
        
        // Set expiry time (1 hour)
        $expiryTime = date('Y-m-d H:i:s', time() + 3600);
        
        // Store token in database
        $tokenStmt = $this->db->prepare("
            INSERT INTO password_reset_tokens (user_id, token, expires_at) 
            VALUES (?, ?, ?)
        ");
        $tokenStmt->execute([$user['id'], $tokenHash, $expiryTime]);
        
        // In a real-world scenario, send email with reset link
        // For now, return token for demonstration
        return [
            'success' => true,
            'message' => 'Password reset instructions sent to your email',
            'debug_token' => $token // Remove in production
        ];
    }
    
    // Validate API token
    public function validateApiToken($token) {
        if (empty($token)) {
            return false;
        }
        
        // Get API token from settings
        $stmt = $this->db->prepare("SELECT value FROM settings WHERE key = 'api_token' LIMIT 1");
        $stmt->execute();
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$setting) {
            return false;
        }
        
        // Compare tokens in constant time to prevent timing attacks
        return hash_equals($setting['value'], $token);
    }
}

// Get auth instance
function auth() {
    return Auth::getInstance();
}

// Helper: Get current user ID
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

// Helper: Get current username
function getCurrentUsername() {
    return isset($_SESSION['username']) ? $_SESSION['username'] : null;
}

// Helper: Check if current request has valid API token
function hasValidApiToken() {
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return false;
    }
    
    $token = $matches[1];
    return auth()->validateApiToken($token);
}

// Utility: Require login or redirect
function requireLogin() {
    if (!auth()->isLoggedIn()) {
        header('Location: /luna/admin/login.php');
        exit;
    }
}

// Utility: Get Bearer token from header
function getBearerToken() {
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return null;
    }
    
    return $matches[1];
}