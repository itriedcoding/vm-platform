<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/vm_manager.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $vmManager = new VMManager();
    
    // Validate required fields
    $requiredFields = ['name', 'template', 'cpu_cores', 'memory', 'disk_size'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "Field '{$field}' is required"]);
            exit();
        }
    }
    
    // Sanitize and validate input
    $vmData = [
        'name' => trim($_POST['name']),
        'template' => trim($_POST['template']),
        'cpu_cores' => intval($_POST['cpu_cores']),
        'memory' => intval($_POST['memory']),
        'disk_size' => intval($_POST['disk_size']),
        'network' => $_POST['network'] ?? 'default',
        'description' => trim($_POST['description'] ?? '')
    ];
    
    // Validate ranges
    if ($vmData['cpu_cores'] < 1 || $vmData['cpu_cores'] > 32) {
        echo json_encode(['success' => false, 'message' => 'CPU cores must be between 1 and 32']);
        exit();
    }
    
    if ($vmData['memory'] < 1 || $vmData['memory'] > 128) {
        echo json_encode(['success' => false, 'message' => 'Memory must be between 1 and 128 GB']);
        exit();
    }
    
    if ($vmData['disk_size'] < 10 || $vmData['disk_size'] > 2048) {
        echo json_encode(['success' => false, 'message' => 'Disk size must be between 10 and 2048 GB']);
        exit();
    }
    
    // Check if VM name already exists for this user
    $database = new Database();
    $db = $database->getConnection();
    $stmt = $db->prepare("SELECT id FROM virtual_machines WHERE user_id = ? AND name = ?");
    $stmt->execute([$_SESSION['user_id'], $vmData['name']]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'A VM with this name already exists']);
        exit();
    }
    
    // Create the VM
    $result = $vmManager->createVM($_SESSION['user_id'], $vmData);
    
    if ($result['success']) {
        // Log the creation
        $stmt = $db->prepare("
            INSERT INTO system_logs (user_id, action, resource_type, resource_id, details, ip_address) 
            VALUES (?, 'vm_create', 'vm', ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $result['vm_id'],
            json_encode($vmData),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Create VM error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while creating the VM']);
}
?>