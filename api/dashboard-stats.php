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

try {
    $vmManager = new VMManager();
    
    // Get user's VMs
    $userVMs = $vmManager->getUserVMs($_SESSION['user_id']);
    
    // Calculate stats
    $totalVMs = count($userVMs);
    $runningVMs = count(array_filter($userVMs, function($vm) {
        return $vm['status'] === 'running';
    }));
    
    // Get system stats
    $systemStats = $vmManager->getSystemStats();
    
    // Calculate resource usage
    $totalMemoryUsed = array_sum(array_column($userVMs, 'memory'));
    $totalCpuUsed = array_sum(array_column($userVMs, 'cpu_cores'));
    
    $memoryUsage = $systemStats['total_memory'] > 0 ? 
        round(($totalMemoryUsed / $systemStats['total_memory']) * 100, 1) : 0;
    
    $cpuUsage = $systemStats['total_cpus'] > 0 ? 
        round(($totalCpuUsed / $systemStats['total_cpus']) * 100, 1) : 0;
    
    // Get disk usage
    $diskUsage = $this->getDiskUsage();
    
    // Get network stats
    $networkStats = $this->getNetworkStats();
    
    $stats = [
        'totalVMs' => $totalVMs,
        'runningVMs' => $runningVMs,
        'stoppedVMs' => $totalVMs - $runningVMs,
        'totalMemory' => $systemStats['total_memory'],
        'usedMemory' => $totalMemoryUsed,
        'totalCPUs' => $systemStats['total_cpus'],
        'usedCPUs' => $totalCpuUsed,
        'memoryUsage' => $memoryUsage,
        'cpuUsage' => $cpuUsage,
        'diskUsage' => $diskUsage,
        'networkStats' => $networkStats,
        'hypervisor' => $systemStats['hypervisor']
    ];
    
    echo json_encode(['success' => true, 'stats' => $stats]);
    
} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to get dashboard stats']);
}

function getDiskUsage() {
    try {
        $output = shell_exec("df -h /var/lib/vm-platform 2>/dev/null | awk 'NR==2{print $5}' | sed 's/%//'");
        return intval($output) ?: 0;
    } catch (Exception $e) {
        return 0;
    }
}

function getNetworkStats() {
    try {
        // Get network interface statistics
        $output = shell_exec("cat /proc/net/dev | grep -E 'eth0|ens|enp' | head -1 | awk '{print $2,$10}'");
        $parts = explode(' ', trim($output));
        
        return [
            'bytes_received' => intval($parts[0] ?? 0),
            'bytes_transmitted' => intval($parts[1] ?? 0),
            'packets_received' => 0, // Would need additional parsing
            'packets_transmitted' => 0 // Would need additional parsing
        ];
    } catch (Exception $e) {
        return [
            'bytes_received' => 0,
            'bytes_transmitted' => 0,
            'packets_received' => 0,
            'packets_transmitted' => 0
        ];
    }
}
?>