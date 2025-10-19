<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/vm_manager.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$vmManager = new VMManager();
$userVMs = $vmManager->getUserVMs($_SESSION['user_id']);
$systemStats = $vmManager->getSystemStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced VM Platform - Dashboard</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Navigation -->
        <nav class="navbar">
            <div class="nav-brand">
                <i class="fas fa-server"></i>
                <span>VM Platform</span>
            </div>
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="vms.php" class="nav-link">
                    <i class="fas fa-desktop"></i> Virtual Machines
                </a>
                <a href="templates.php" class="nav-link">
                    <i class="fas fa-layer-group"></i> Templates
                </a>
                <a href="networks.php" class="nav-link">
                    <i class="fas fa-network-wired"></i> Networks
                </a>
                <a href="storage.php" class="nav-link">
                    <i class="fas fa-hdd"></i> Storage
                </a>
                <a href="monitoring.php" class="nav-link">
                    <i class="fas fa-chart-line"></i> Monitoring
                </a>
                <a href="settings.php" class="nav-link">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1>Dashboard</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openCreateVMModal()">
                        <i class="fas fa-plus"></i> Create VM
                    </button>
                </div>
            </div>

            <!-- System Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-desktop"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo count($userVMs); ?></h3>
                        <p>Total VMs</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo count(array_filter($userVMs, function($vm) { return $vm['status'] === 'running'; })); ?></h3>
                        <p>Running VMs</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-memory"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $systemStats['total_memory']; ?>GB</h3>
                        <p>Total Memory</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $systemStats['total_cpus']; ?></h3>
                        <p>Total CPUs</p>
                    </div>
                </div>
            </div>

            <!-- Recent VMs -->
            <div class="content-section">
                <h2>Recent Virtual Machines</h2>
                <div class="vm-grid">
                    <?php foreach (array_slice($userVMs, 0, 6) as $vm): ?>
                    <div class="vm-card">
                        <div class="vm-header">
                            <h3><?php echo htmlspecialchars($vm['name']); ?></h3>
                            <span class="vm-status status-<?php echo $vm['status']; ?>">
                                <?php echo ucfirst($vm['status']); ?>
                            </span>
                        </div>
                        <div class="vm-info">
                            <div class="info-item">
                                <i class="fas fa-microchip"></i>
                                <span><?php echo $vm['cpu_cores']; ?> CPU</span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-memory"></i>
                                <span><?php echo $vm['memory']; ?>GB RAM</span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-hdd"></i>
                                <span><?php echo $vm['disk_size']; ?>GB Disk</span>
                            </div>
                        </div>
                        <div class="vm-actions">
                            <button class="btn btn-sm" onclick="vmAction('<?php echo $vm['id']; ?>', 'start')">
                                <i class="fas fa-play"></i>
                            </button>
                            <button class="btn btn-sm" onclick="vmAction('<?php echo $vm['id']; ?>', 'stop')">
                                <i class="fas fa-stop"></i>
                            </button>
                            <button class="btn btn-sm" onclick="vmAction('<?php echo $vm['id']; ?>', 'restart')">
                                <i class="fas fa-redo"></i>
                            </button>
                            <button class="btn btn-sm" onclick="vmAction('<?php echo $vm['id']; ?>', 'console')">
                                <i class="fas fa-terminal"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Create VM Modal -->
    <div id="createVMModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create Virtual Machine</h2>
                <span class="close" onclick="closeCreateVMModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="createVMForm">
                    <div class="form-group">
                        <label for="vmName">VM Name</label>
                        <input type="text" id="vmName" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="vmTemplate">Template</label>
                        <select id="vmTemplate" name="template" required>
                            <option value="">Select Template</option>
                            <option value="ubuntu-20.04">Ubuntu 20.04 LTS</option>
                            <option value="ubuntu-22.04">Ubuntu 22.04 LTS</option>
                            <option value="centos-8">CentOS 8</option>
                            <option value="debian-11">Debian 11</option>
                            <option value="windows-10">Windows 10</option>
                            <option value="windows-server-2019">Windows Server 2019</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="vmCpu">CPU Cores</label>
                            <input type="number" id="vmCpu" name="cpu_cores" min="1" max="32" value="2" required>
                        </div>
                        <div class="form-group">
                            <label for="vmMemory">Memory (GB)</label>
                            <input type="number" id="vmMemory" name="memory" min="1" max="128" value="4" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="vmDisk">Disk Size (GB)</label>
                        <input type="number" id="vmDisk" name="disk_size" min="10" max="2048" value="50" required>
                    </div>
                    <div class="form-group">
                        <label for="vmNetwork">Network</label>
                        <select id="vmNetwork" name="network">
                            <option value="default">Default Bridge</option>
                            <option value="isolated">Isolated Network</option>
                            <option value="custom">Custom Network</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="vmDescription">Description</label>
                        <textarea id="vmDescription" name="description" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreateVMModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="createVM()">Create VM</button>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>