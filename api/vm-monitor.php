<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

$vmId = $_GET['vm_id'] ?? '';

if (empty($vmId)) {
    echo json_encode(['success' => false, 'message' => 'VM ID required']);
    exit();
}

try {
    // Get VM process information
    $output = shell_exec("ps aux | grep 'qemu-system-x86_64.*{$vmId}' | grep -v grep");
    
    if (empty($output)) {
        echo json_encode([
            'success' => true,
            'monitoring' => [
                'cpu' => 0,
                'memory' => 0,
                'disk' => 0,
                'status' => 'stopped'
            ]
        ]);
        exit();
    }
    
    // Parse process information
    $lines = explode("\n", trim($output));
    $processInfo = explode(' ', preg_replace('/\s+/', ' ', trim($lines[0])));
    
    $cpuUsage = floatval($processInfo[2] ?? 0);
    $memoryUsage = floatval($processInfo[3] ?? 0);
    
    // Get disk usage
    $vmDir = "/var/lib/vm-platform/vms/{$vmId}";
    $diskPath = "{$vmDir}/disk.qcow2";
    
    $diskUsage = 0;
    if (file_exists($diskPath)) {
        $diskSize = filesize($diskPath);
        $diskUsage = min(100, ($diskSize / (50 * 1024 * 1024 * 1024)) * 100); // Assuming 50GB max
    }
    
    // Get network statistics
    $networkStats = getNetworkStats();
    
    echo json_encode([
        'success' => true,
        'monitoring' => [
            'cpu' => round($cpuUsage, 1),
            'memory' => round($memoryUsage, 1),
            'disk' => round($diskUsage, 1),
            'status' => 'running',
            'network' => $networkStats
        ]
    ]);
    
} catch (Exception $e) {
    error_log("VM monitoring error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to get monitoring data']);
}

function getNetworkStats() {
    try {
        // Get network interface statistics
        $output = shell_exec("cat /proc/net/dev | grep -E 'eth0|ens|enp' | head -1");
        if ($output) {
            $parts = preg_split('/\s+/', trim($output));
            return [
                'bytes_received' => intval($parts[1] ?? 0),
                'bytes_transmitted' => intval($parts[9] ?? 0),
                'packets_received' => intval($parts[2] ?? 0),
                'packets_transmitted' => intval($parts[10] ?? 0)
            ];
        }
    } catch (Exception $e) {
        // Ignore errors
    }
    
    return [
        'bytes_received' => 0,
        'bytes_transmitted' => 0,
        'packets_received' => 0,
        'packets_transmitted' => 0
    ];
}
?>