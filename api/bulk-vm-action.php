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

if (!$input || !isset($input['action']) || !isset($input['vm_ids']) || !is_array($input['vm_ids'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$action = $input['action'];
$vmIds = $input['vm_ids'];

if (empty($vmIds)) {
    echo json_encode(['success' => false, 'message' => 'No VMs selected']);
    exit();
}

try {
    $vmManager = new VMManager();
    $results = [];
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($vmIds as $vmId) {
        // Verify VM ownership
        $vm = $vmManager->getVMByVmId($vmId);
        if (!$vm) {
            $results[] = ['vm_id' => $vmId, 'success' => false, 'message' => 'VM not found'];
            $errorCount++;
            continue;
        }
        
        if ($vm['user_id'] != $_SESSION['user_id']) {
            $results[] = ['vm_id' => $vmId, 'success' => false, 'message' => 'Access denied'];
            $errorCount++;
            continue;
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
                $snapshotName = 'Bulk Snapshot ' . date('Y-m-d H:i:s');
                $result = $vmManager->createSnapshot($vmId, $snapshotName, 'Bulk operation');
                break;
                
            case 'backup':
                $backupName = 'Bulk Backup ' . date('Y-m-d H:i:s');
                $result = $vmManager->createBackup($vmId, $backupName);
                break;
                
            default:
                $result = ['success' => false, 'message' => 'Invalid action'];
                break;
        }
        
        if ($result && $result['success']) {
            $successCount++;
        } else {
            $errorCount++;
        }
        
        $results[] = array_merge(['vm_id' => $vmId], $result);
    }
    
    // Log the bulk action
    $database = new Database();
    $db = $database->getConnection();
    $stmt = $db->prepare("
        INSERT INTO system_logs (user_id, action, resource_type, resource_id, details, ip_address) 
        VALUES (?, ?, 'vm', ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "bulk_vm_{$action}",
        null,
        json_encode(['vm_ids' => $vmIds, 'action' => $action, 'success_count' => $successCount, 'error_count' => $errorCount]),
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    $message = "Bulk action completed: {$successCount} successful, {$errorCount} failed";
    
    echo json_encode([
        'success' => $errorCount === 0,
        'message' => $message,
        'results' => $results,
        'success_count' => $successCount,
        'error_count' => $errorCount
    ]);
    
} catch (Exception $e) {
    error_log("Bulk VM action error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while performing the bulk action']);
}
?>