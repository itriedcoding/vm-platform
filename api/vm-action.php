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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action']) || !isset($input['vm_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$action = $input['action'];
$vmId = $input['vm_id'];

try {
    $vmManager = new VMManager();
    
    // Verify VM ownership
    $vm = $vmManager->getVMByVmId($vmId);
    if (!$vm) {
        echo json_encode(['success' => false, 'message' => 'VM not found']);
        exit();
    }
    
    if ($vm['user_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    
    $result = null;
    
    switch ($action) {
        case 'start':
            $result = $vmManager->startVM($vmId);
            break;
            
        case 'stop':
            $result = $vmManager->stopVM($vmId);
            break;
            
        case 'restart':
            $result = $vmManager->restartVM($vmId);
            break;
            
        case 'delete':
            $result = $vmManager->deleteVM($vmId);
            break;
            
        case 'snapshot':
            $snapshotName = $input['snapshot_name'] ?? 'Snapshot ' . date('Y-m-d H:i:s');
            $description = $input['description'] ?? '';
            $result = $vmManager->createSnapshot($vmId, $snapshotName, $description);
            break;
            
        case 'backup':
            $backupName = $input['backup_name'] ?? 'Backup ' . date('Y-m-d H:i:s');
            $result = $vmManager->createBackup($vmId, $backupName);
            break;
            
        case 'console':
            // Return console URL instead of performing action
            $result = [
                'success' => true, 
                'message' => 'Console opened',
                'console_url' => "console.php?vm_id={$vmId}"
            ];
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit();
    }
    
    if ($result) {
        // Log the action
        $database = new Database();
        $db = $database->getConnection();
        $stmt = $db->prepare("
            INSERT INTO system_logs (user_id, action, resource_type, resource_id, details, ip_address) 
            VALUES (?, ?, 'vm', ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            "vm_{$action}",
            $vm['id'],
            json_encode(['vm_id' => $vmId, 'action' => $action]),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        echo json_encode($result);
    } else {
        echo json_encode(['success' => false, 'message' => 'Action failed']);
    }
    
} catch (Exception $e) {
    error_log("VM action error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while performing the action']);
}
?>