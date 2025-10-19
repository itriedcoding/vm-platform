<?php
require_once 'config/database.php';

class VMManager {
    private $db;
    private $hypervisor;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->hypervisor = $this->detectHypervisor();
    }
    
    private function detectHypervisor() {
        // Detect available hypervisor
        if (shell_exec('which qemu-system-x86_64') !== null) {
            return 'qemu';
        } elseif (shell_exec('which virsh') !== null) {
            return 'libvirt';
        } elseif (shell_exec('which vmrun') !== null) {
            return 'vmware';
        } elseif (shell_exec('which vboxmanage') !== null) {
            return 'virtualbox';
        } else {
            return 'qemu'; // Default fallback
        }
    }
    
    public function createVM($userId, $vmData) {
        try {
            // Generate unique VM ID
            $vmId = 'vm_' . uniqid();
            
            // Insert VM record
            $stmt = $this->db->prepare("
                INSERT INTO virtual_machines 
                (user_id, name, description, template, cpu_cores, memory, disk_size, network_config, vm_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $networkConfig = json_encode([
                'type' => $vmData['network'] ?? 'default',
                'bridge' => 'vmbr0',
                'ip' => $this->generateIP()
            ]);
            
            $stmt->execute([
                $userId,
                $vmData['name'],
                $vmData['description'] ?? '',
                $vmData['template'],
                $vmData['cpu_cores'],
                $vmData['memory'],
                $vmData['disk_size'],
                $networkConfig,
                $vmId
            ]);
            
            $vmDbId = $this->db->lastInsertId();
            
            // Create actual VM based on hypervisor
            $result = $this->createActualVM($vmId, $vmData);
            
            if ($result['success']) {
                $this->logAction('vm_create', 'vm', $vmDbId, $vmData);
                return ['success' => true, 'vm_id' => $vmId, 'message' => 'VM created successfully'];
            } else {
                // Clean up database record if VM creation failed
                $this->deleteVM($vmDbId);
                return ['success' => false, 'message' => $result['message']];
            }
        } catch(PDOException $e) {
            error_log("VM creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    private function createActualVM($vmId, $vmData) {
        $vmPath = "/var/lib/vm-platform/vms/{$vmId}";
        $diskPath = "{$vmPath}/disk.qcow2";
        
        // Create VM directory
        if (!is_dir($vmPath)) {
            mkdir($vmPath, 0755, true);
        }
        
        // Create disk image
        $diskSize = $vmData['disk_size'] . 'G';
        $cmd = "qemu-img create -f qcow2 {$diskPath} {$diskSize}";
        $output = shell_exec($cmd . ' 2>&1');
        
        if ($output === null) {
            return ['success' => false, 'message' => 'Failed to create disk image'];
        }
        
        // Download template image if needed
        $template = $this->getTemplate($vmData['template']);
        if ($template && !empty($template['image_url'])) {
            $this->downloadTemplate($template['image_url'], $diskPath);
        }
        
        // Create VM configuration
        $config = $this->generateVMConfig($vmId, $vmData, $diskPath);
        file_put_contents("{$vmPath}/vm.conf", $config);
        
        return ['success' => true, 'message' => 'VM created successfully'];
    }
    
    private function getTemplate($templateName) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM vm_templates WHERE name = ?");
            $stmt->execute([$templateName]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            return false;
        }
    }
    
    private function downloadTemplate($imageUrl, $diskPath) {
        $cmd = "wget -O {$diskPath} {$imageUrl}";
        shell_exec($cmd);
    }
    
    private function generateVMConfig($vmId, $vmData, $diskPath) {
        $memory = $vmData['memory'] * 1024; // Convert to MB
        $cpuCores = $vmData['cpu_cores'];
        
        return "[vm]
name = {$vmId}
memory = {$memory}
cpus = {$cpuCores}
disk = {$diskPath}
network = bridge:vmbr0
vnc = 5900
";
    }
    
    private function generateIP() {
        return '192.168.100.' . rand(10, 254);
    }
    
    public function startVM($vmId) {
        try {
            $vm = $this->getVMByVmId($vmId);
            if (!$vm) {
                return ['success' => false, 'message' => 'VM not found'];
            }
            
            $vmPath = "/var/lib/vm-platform/vms/{$vmId}";
            $configPath = "{$vmPath}/vm.conf";
            
            if (!file_exists($configPath)) {
                return ['success' => false, 'message' => 'VM configuration not found'];
            }
            
            // Start VM using QEMU
            $cmd = "qemu-system-x86_64 -daemonize -name {$vmId} -m {$vm['memory']} -smp {$vm['cpu_cores']} -drive file={$vmPath}/disk.qcow2,format=qcow2 -netdev bridge,id=net0,br=vmbr0 -device virtio-net-pci,netdev=net0 -vnc :{$vm['id']}";
            
            $output = shell_exec($cmd . ' 2>&1');
            
            if ($output === null) {
                // Update status
                $this->updateVMStatus($vm['id'], 'running');
                $this->logAction('vm_start', 'vm', $vm['id']);
                return ['success' => true, 'message' => 'VM started successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to start VM: ' . $output];
            }
        } catch(Exception $e) {
            error_log("Start VM error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error starting VM'];
        }
    }
    
    public function stopVM($vmId) {
        try {
            $vm = $this->getVMByVmId($vmId);
            if (!$vm) {
                return ['success' => false, 'message' => 'VM not found'];
            }
            
            // Find and kill QEMU process
            $cmd = "pkill -f 'qemu-system-x86_64.*{$vmId}'";
            shell_exec($cmd);
            
            $this->updateVMStatus($vm['id'], 'stopped');
            $this->logAction('vm_stop', 'vm', $vm['id']);
            
            return ['success' => true, 'message' => 'VM stopped successfully'];
        } catch(Exception $e) {
            error_log("Stop VM error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error stopping VM'];
        }
    }
    
    public function restartVM($vmId) {
        $stopResult = $this->stopVM($vmId);
        if ($stopResult['success']) {
            sleep(2); // Wait a moment
            return $this->startVM($vmId);
        }
        return $stopResult;
    }
    
    public function deleteVM($vmId) {
        try {
            $vm = $this->getVMByVmId($vmId);
            if (!$vm) {
                return ['success' => false, 'message' => 'VM not found'];
            }
            
            // Stop VM first
            $this->stopVM($vmId);
            
            // Remove VM files
            $vmPath = "/var/lib/vm-platform/vms/{$vmId}";
            if (is_dir($vmPath)) {
                $this->removeDirectory($vmPath);
            }
            
            // Delete from database
            $stmt = $this->db->prepare("DELETE FROM virtual_machines WHERE vm_id = ?");
            $stmt->execute([$vmId]);
            
            $this->logAction('vm_delete', 'vm', $vm['id']);
            
            return ['success' => true, 'message' => 'VM deleted successfully'];
        } catch(Exception $e) {
            error_log("Delete VM error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error deleting VM'];
        }
    }
    
    public function getUserVMs($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM virtual_machines 
                WHERE user_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Get user VMs error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getVMByVmId($vmId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM virtual_machines WHERE vm_id = ?");
            $stmt->execute([$vmId]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("Get VM error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateVMStatus($vmId, $status) {
        try {
            $stmt = $this->db->prepare("UPDATE virtual_machines SET status = ? WHERE id = ?");
            $stmt->execute([$status, $vmId]);
        } catch(PDOException $e) {
            error_log("Update VM status error: " . $e->getMessage());
        }
    }
    
    public function getSystemStats() {
        // Get system resource information
        $totalMemory = intval(shell_exec("free -m | awk 'NR==2{print $2}'")) / 1024;
        $totalCpus = intval(shell_exec("nproc"));
        $totalDisk = intval(shell_exec("df -BG / | awk 'NR==2{print $2}' | sed 's/G//'"));
        
        return [
            'total_memory' => round($totalMemory, 1),
            'total_cpus' => $totalCpus,
            'total_disk' => $totalDisk,
            'hypervisor' => $this->hypervisor
        ];
    }
    
    public function createSnapshot($vmId, $snapshotName, $description = '') {
        try {
            $vm = $this->getVMByVmId($vmId);
            if (!$vm) {
                return ['success' => false, 'message' => 'VM not found'];
            }
            
            $snapshotId = 'snap_' . uniqid();
            $vmPath = "/var/lib/vm-platform/vms/{$vmId}";
            $snapshotPath = "{$vmPath}/snapshots/{$snapshotId}.qcow2";
            
            // Create snapshot directory
            if (!is_dir("{$vmPath}/snapshots")) {
                mkdir("{$vmPath}/snapshots", 0755, true);
            }
            
            // Create snapshot
            $cmd = "qemu-img snapshot -c {$snapshotId} {$vmPath}/disk.qcow2";
            $output = shell_exec($cmd . ' 2>&1');
            
            if ($output === null) {
                // Save snapshot info to database
                $stmt = $this->db->prepare("
                    INSERT INTO vm_snapshots (vm_id, name, description, snapshot_id) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$vm['id'], $snapshotName, $description, $snapshotId]);
                
                $this->logAction('snapshot_create', 'vm', $vm['id'], ['snapshot_name' => $snapshotName]);
                return ['success' => true, 'message' => 'Snapshot created successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to create snapshot: ' . $output];
            }
        } catch(Exception $e) {
            error_log("Create snapshot error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error creating snapshot'];
        }
    }
    
    public function restoreSnapshot($vmId, $snapshotId) {
        try {
            $vm = $this->getVMByVmId($vmId);
            if (!$vm) {
                return ['success' => false, 'message' => 'VM not found'];
            }
            
            // Stop VM first
            $this->stopVM($vmId);
            
            $vmPath = "/var/lib/vm-platform/vms/{$vmId}";
            
            // Restore snapshot
            $cmd = "qemu-img snapshot -a {$snapshotId} {$vmPath}/disk.qcow2";
            $output = shell_exec($cmd . ' 2>&1');
            
            if ($output === null) {
                $this->logAction('snapshot_restore', 'vm', $vm['id'], ['snapshot_id' => $snapshotId]);
                return ['success' => true, 'message' => 'Snapshot restored successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to restore snapshot: ' . $output];
            }
        } catch(Exception $e) {
            error_log("Restore snapshot error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error restoring snapshot'];
        }
    }
    
    public function createBackup($vmId, $backupName) {
        try {
            $vm = $this->getVMByVmId($vmId);
            if (!$vm) {
                return ['success' => false, 'message' => 'VM not found'];
            }
            
            // Stop VM for backup
            $this->stopVM($vmId);
            
            $backupId = 'backup_' . uniqid();
            $vmPath = "/var/lib/vm-platform/vms/{$vmId}";
            $backupPath = "/var/lib/vm-platform/backups/{$backupId}.qcow2";
            
            // Create backup directory
            if (!is_dir("/var/lib/vm-platform/backups")) {
                mkdir("/var/lib/vm-platform/backups", 0755, true);
            }
            
            // Create backup
            $cmd = "cp {$vmPath}/disk.qcow2 {$backupPath}";
            $output = shell_exec($cmd . ' 2>&1');
            
            if ($output === null) {
                $backupSize = filesize($backupPath);
                
                // Save backup info to database
                $stmt = $this->db->prepare("
                    INSERT INTO vm_backups (vm_id, backup_name, backup_path, backup_size, status) 
                    VALUES (?, ?, ?, ?, 'completed')
                ");
                $stmt->execute([$vm['id'], $backupName, $backupPath, $backupSize]);
                
                $this->logAction('backup_create', 'vm', $vm['id'], ['backup_name' => $backupName]);
                return ['success' => true, 'message' => 'Backup created successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to create backup: ' . $output];
            }
        } catch(Exception $e) {
            error_log("Create backup error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error creating backup'];
        }
    }
    
    private function removeDirectory($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->removeDirectory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
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
?>