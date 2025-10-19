<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/vm_manager.php';

requireLogin();

$vmId = $_GET['vm_id'] ?? '';

if (empty($vmId)) {
    die('VM ID required');
}

$vmManager = new VMManager();
$vm = $vmManager->getVMByVmId($vmId);

if (!$vm || $vm['user_id'] != $_SESSION['user_id']) {
    die('VM not found or access denied');
}

// Get VNC port from VM configuration
$vmDir = "/var/lib/vm-platform/vms/{$vmId}";
$configFile = "{$vmDir}/vm.conf";

$vncPort = 5900; // Default port
if (file_exists($configFile)) {
    $config = parse_ini_file($configFile);
    $vncPort = $config['vnc'] ?? 5900;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VM Console - <?php echo htmlspecialchars($vm['name']); ?></title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #000;
            font-family: Arial, sans-serif;
            overflow: hidden;
        }
        
        .console-header {
            background: #333;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #555;
        }
        
        .console-title {
            font-size: 16px;
            font-weight: bold;
        }
        
        .console-controls {
            display: flex;
            gap: 10px;
        }
        
        .console-btn {
            background: #555;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .console-btn:hover {
            background: #666;
        }
        
        .console-btn.danger {
            background: #d32f2f;
        }
        
        .console-btn.danger:hover {
            background: #f44336;
        }
        
        .console-container {
            width: 100%;
            height: calc(100vh - 50px);
            position: relative;
        }
        
        .console-placeholder {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: #666;
        }
        
        .console-placeholder i {
            font-size: 48px;
            margin-bottom: 20px;
            display: block;
        }
        
        .console-info {
            background: #222;
            color: #ccc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .console-info h3 {
            margin: 0 0 15px 0;
            color: white;
        }
        
        .console-info p {
            margin: 5px 0;
        }
        
        .console-info strong {
            color: #4CAF50;
        }
        
        .vnc-container {
            width: 100%;
            height: 100%;
            background: #000;
        }
        
        .no-vnc {
            display: none;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="console-header">
        <div class="console-title">
            <i class="fas fa-terminal"></i>
            <?php echo htmlspecialchars($vm['name']); ?> Console
        </div>
        <div class="console-controls">
            <button class="console-btn" onclick="toggleFullscreen()">
                <i class="fas fa-expand"></i> Fullscreen
            </button>
            <button class="console-btn" onclick="sendCtrlAltDel()">
                <i class="fas fa-keyboard"></i> Ctrl+Alt+Del
            </button>
            <button class="console-btn" onclick="refreshConsole()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button class="console-btn danger" onclick="closeConsole()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
    
    <div class="console-container">
        <div id="consolePlaceholder" class="console-placeholder">
            <i class="fas fa-desktop"></i>
            <div class="console-info">
                <h3>VM Console</h3>
                <p><strong>VM Name:</strong> <?php echo htmlspecialchars($vm['name']); ?></p>
                <p><strong>VM ID:</strong> <?php echo $vmId; ?></p>
                <p><strong>VNC Port:</strong> <?php echo $vncPort; ?></p>
                <p><strong>Status:</strong> 
                    <span id="vmStatus"><?php echo ucfirst($vm['status']); ?></span>
                </p>
                <p><strong>Instructions:</strong></p>
                <ul style="text-align: left; margin: 10px 0;">
                    <li>Use VNC viewer to connect to localhost:<?php echo $vncPort; ?></li>
                    <li>Or use a web-based VNC client</li>
                    <li>Make sure the VM is running before connecting</li>
                </ul>
                <button class="console-btn" onclick="startVM()" id="startBtn" style="display: none;">
                    <i class="fas fa-play"></i> Start VM
                </button>
                <button class="console-btn" onclick="openVNC()">
                    <i class="fas fa-external-link-alt"></i> Open VNC
                </button>
            </div>
        </div>
        
        <div id="vncContainer" class="vnc-container no-vnc">
            <!-- VNC client will be loaded here -->
        </div>
    </div>

    <script>
        const vmId = '<?php echo $vmId; ?>';
        const vncPort = <?php echo $vncPort; ?>;
        
        // Check VM status
        async function checkVMStatus() {
            try {
                const response = await fetch(`api/vm-action.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'status',
                        vm_id: vmId
                    })
                });
                
                const result = await response.json();
                const statusElement = document.getElementById('vmStatus');
                const startBtn = document.getElementById('startBtn');
                
                if (result.success) {
                    statusElement.textContent = 'Running';
                    statusElement.style.color = '#4CAF50';
                    startBtn.style.display = 'none';
                } else {
                    statusElement.textContent = 'Stopped';
                    statusElement.style.color = '#f44336';
                    startBtn.style.display = 'inline-block';
                }
            } catch (error) {
                console.error('Failed to check VM status:', error);
            }
        }
        
        // Start VM
        async function startVM() {
            const startBtn = document.getElementById('startBtn');
            const originalText = startBtn.innerHTML;
            
            startBtn.innerHTML = '<span class="loading"></span> Starting...';
            startBtn.disabled = true;
            
            try {
                const response = await fetch('api/vm-action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'start',
                        vm_id: vmId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('VM started successfully! Please wait a moment for it to fully boot.');
                    checkVMStatus();
                } else {
                    alert('Failed to start VM: ' + result.message);
                }
            } catch (error) {
                console.error('Error starting VM:', error);
                alert('An error occurred while starting the VM');
            } finally {
                startBtn.innerHTML = originalText;
                startBtn.disabled = false;
            }
        }
        
        // Open VNC in new window
        function openVNC() {
            const vncUrl = `vnc://localhost:${vncPort}`;
            window.open(vncUrl, '_blank');
        }
        
        // Toggle fullscreen
        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen();
            } else {
                document.exitFullscreen();
            }
        }
        
        // Send Ctrl+Alt+Del
        async function sendCtrlAltDel() {
            try {
                const response = await fetch('api/vm-action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'send_key',
                        vm_id: vmId,
                        key: 'ctrl-alt-del'
                    })
                });
                
                const result = await response.json();
                if (!result.success) {
                    alert('Failed to send Ctrl+Alt+Del: ' + result.message);
                }
            } catch (error) {
                console.error('Error sending key:', error);
            }
        }
        
        // Refresh console
        function refreshConsole() {
            checkVMStatus();
        }
        
        // Close console
        function closeConsole() {
            window.close();
        }
        
        // Initialize console
        document.addEventListener('DOMContentLoaded', function() {
            checkVMStatus();
            
            // Check status every 10 seconds
            setInterval(checkVMStatus, 10000);
        });
        
        // Handle keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.altKey && e.key === 'Delete') {
                e.preventDefault();
                sendCtrlAltDel();
            } else if (e.key === 'F11') {
                e.preventDefault();
                toggleFullscreen();
            } else if (e.key === 'Escape') {
                closeConsole();
            }
        });
    </script>
</body>
</html>