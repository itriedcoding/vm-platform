<?php
require_once 'config/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function register($username, $email, $password) {
        try {
            // Check if user already exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }
            
            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash) 
                VALUES (?, ?, ?)
            ");
            
            if ($stmt->execute([$username, $email, $passwordHash])) {
                return ['success' => true, 'message' => 'User registered successfully'];
            } else {
                return ['success' => false, 'message' => 'Registration failed'];
            }
        } catch(PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    public function login($username, $password) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, password_hash, role 
                FROM users 
                WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $username]);
            
            if ($stmt->rowCount() === 1) {
                $user = $stmt->fetch();
                
                if (password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Log login
                    $this->logAction('login', 'user', $user['id'], ['ip' => $_SERVER['REMOTE_ADDR']]);
                    
                    return ['success' => true, 'message' => 'Login successful'];
                } else {
                    return ['success' => false, 'message' => 'Invalid password'];
                }
            } else {
                return ['success' => false, 'message' => 'User not found'];
            }
        } catch(PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    public function logout() {
        // Log logout
        if (isset($_SESSION['user_id'])) {
            $this->logAction('logout', 'user', $_SESSION['user_id'], ['ip' => $_SERVER['REMOTE_ADDR']]);
        }
        
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
    
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Verify current password
            $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!password_verify($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }
            
            // Update password
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            
            if ($stmt->execute([$newPasswordHash, $userId])) {
                $this->logAction('password_change', 'user', $userId);
                return ['success' => true, 'message' => 'Password changed successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to update password'];
            }
        } catch(PDOException $e) {
            error_log("Password change error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    public function getUser($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, role, created_at 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateProfile($userId, $username, $email) {
        try {
            // Check if username/email already exists
            $stmt = $this->db->prepare("
                SELECT id FROM users 
                WHERE (username = ? OR email = ?) AND id != ?
            ");
            $stmt->execute([$username, $email, $userId]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }
            
            $stmt = $this->db->prepare("
                UPDATE users 
                SET username = ?, email = ? 
                WHERE id = ?
            ");
            
            if ($stmt->execute([$username, $email, $userId])) {
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $this->logAction('profile_update', 'user', $userId);
                return ['success' => true, 'message' => 'Profile updated successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to update profile'];
            }
        } catch(PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    private function logAction($action, $resourceType, $resourceId, $details = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO system_logs (user_id, action, resource_type, resource_id, details, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $action,
                $resourceType,
                $resourceId,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch(PDOException $e) {
            error_log("Log action error: " . $e->getMessage());
        }
    }
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: dashboard.php');
        exit();
    }
}

function getCurrentUser() {
    if (isLoggedIn()) {
        $auth = new Auth();
        return $auth->getUser($_SESSION['user_id']);
    }
    return false;
}
?>