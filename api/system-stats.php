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

try {
    // Get system resource information
    $stats = [];
    
    // CPU usage
    $cpuUsage = 0;
    $output = shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2}' | cut -d'%' -f1");
    if ($output) {
        $cpuUsage = floatval(trim($output));
    }
    $stats['cpu_usage'] = $cpuUsage;
    
    // Memory usage
    $memoryUsage = 0;
    $output = shell_exec("free | awk 'NR==2{printf \"%.1f\", $3*100/$2}'");
    if ($output) {
        $memoryUsage = floatval(trim($output));
    }
    $stats['memory_usage'] = $memoryUsage;
    
    // Disk usage
    $diskUsage = 0;
    $output = shell_exec("df -h /var/lib/vm-platform | awk 'NR==2{print $5}' | sed 's/%//'");
    if ($output) {
        $diskUsage = intval(trim($output));
    }
    $stats['disk_usage'] = $diskUsage;
    
    // Network usage
    $networkUsage = 0;
    $output = shell_exec("cat /proc/net/dev | grep -E 'eth0|ens|enp' | head -1 | awk '{print $2,$10}'");
    if ($output) {
        $parts = explode(' ', trim($output));
        $bytesReceived = intval($parts[0] ?? 0);
        $bytesTransmitted = intval($parts[1] ?? 0);
        $totalBytes = $bytesReceived + $bytesTransmitted;
        $networkUsage = min(100, ($totalBytes / (1024 * 1024 * 1024)) * 100); // Convert to percentage
    }
    $stats['network_usage'] = round($networkUsage, 1);
    
    // Total system resources
    $totalMemory = intval(shell_exec("free -m | awk 'NR==2{print $2}'")) / 1024;
    $totalCpus = intval(shell_exec("nproc"));
    $totalDisk = intval(shell_exec("df -BG /var/lib/vm-platform | awk 'NR==2{print $2}' | sed 's/G//'"));
    
    $stats['total_memory'] = round($totalMemory, 1);
    $stats['total_cpus'] = $totalCpus;
    $stats['total_disk'] = $totalDisk;
    
    // Running VMs count
    $runningVMs = intval(shell_exec("ps aux | grep qemu-system-x86_64 | grep -v grep | wc -l"));
    $stats['running_vms'] = $runningVMs;
    
    // System uptime
    $uptime = shell_exec("uptime -p");
    $stats['uptime'] = trim($uptime);
    
    // Load average
    $loadAvg = shell_exec("uptime | awk -F'load average:' '{print $2}'");
    $stats['load_average'] = trim($loadAvg);
    
    // Network interfaces
    $interfaces = [];
    $output = shell_exec("ip -o link show | awk -F': ' '{print $2}'");
    if ($output) {
        $interfaces = array_filter(explode("\n", trim($output)));
    }
    $stats['network_interfaces'] = $interfaces;
    
    // VM Platform specific stats
    $vmDir = '/var/lib/vm-platform/vms';
    $vmCount = 0;
    $totalVmSize = 0;
    
    if (is_dir($vmDir)) {
        $vms = glob("{$vmDir}/vm_*");
        $vmCount = count($vms);
        
        foreach ($vms as $vm) {
            $diskPath = "{$vm}/disk.qcow2";
            if (file_exists($diskPath)) {
                $totalVmSize += filesize($diskPath);
            }
        }
    }
    
    $stats['total_vms'] = $vmCount;
    $stats['total_vm_size'] = formatBytes($totalVmSize);
    
    // Backup count
    $backupDir = '/var/lib/vm-platform/backups';
    $backupCount = 0;
    if (is_dir($backupDir)) {
        $backups = glob("{$backupDir}/*.qcow2");
        $backupCount = count($backups);
    }
    $stats['total_backups'] = $backupCount;
    
    // Recent activity (last 24 hours)
    $database = new Database();
    $db = $database->getConnection();
    $stmt = $db->prepare("
        SELECT COUNT(*) as activity_count 
        FROM system_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['recent_activity'] = intval($result['activity_count']);
    
    echo json_encode(['success' => true, 'stats' => $stats]);
    
} catch (Exception $e) {
    error_log("System stats error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to get system statistics']);
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>