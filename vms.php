<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/vm_manager.php';

requireLogin();

$vmManager = new VMManager();
$userVMs = $vmManager->getUserVMs($_SESSION['user_id']);
$templates = $vmManager->getTemplates();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Virtual Machines - VM Platform</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/vms.css">
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
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="vms.php" class="nav-link active">
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
                <h1>Virtual Machines</h1>
                <div class="header-actions">
                    <button class="btn btn-secondary" onclick="refreshVMs()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button class="btn btn-primary" onclick="openCreateVMModal()">
                        <i class="fas fa-plus"></i> Create VM
                    </button>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="vm-filters">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="statusFilter">Status</label>
                        <select id="statusFilter">
                            <option value="all">All Status</option>
                            <option value="running">Running</option>
                            <option value="stopped">Stopped</option>
                            <option value="paused">Paused</option>
                            <option value="error">Error</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="templateFilter">Template</label>
                        <select id="templateFilter">
                            <option value="all">All Templates</option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo htmlspecialchars($template['name']); ?>">
                                    <?php echo htmlspecialchars($template['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="vmSearch">Search</label>
                        <input type="text" id="vmSearch" placeholder="Search VMs...">
                    </div>
                    <div class="filter-actions">
                        <button class="btn btn-secondary" onclick="clearFilters()">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div class="bulk-actions" style="display: none;">
                <div class="bulk-info">
                    <span id="selectedCount">0</span> VMs selected
                </div>
                <div class="bulk-buttons">
                    <button class="btn btn-sm btn-success bulk-action" data-action="start">
                        <i class="fas fa-play"></i> Start
                    </button>
                    <button class="btn btn-sm btn-warning bulk-action" data-action="stop">
                        <i class="fas fa-stop"></i> Stop
                    </button>
                    <button class="btn btn-sm btn-secondary bulk-action" data-action="restart">
                        <i class="fas fa-redo"></i> Restart
                    </button>
                    <button class="btn btn-sm btn-danger bulk-action" data-action="delete">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>

            <!-- VM List -->
            <div class="content-section">
                <div class="vm-list-header">
                    <div class="vm-count">
                        <h3><?php echo count($userVMs); ?> Virtual Machines</h3>
                    </div>
                    <div class="view-options">
                        <button class="view-btn active" onclick="switchView('grid')" data-view="grid">
                            <i class="fas fa-th"></i>
                        </button>
                        <button class="view-btn" onclick="switchView('list')" data-view="list">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>

                <div id="vmGrid" class="vm-grid">
                    <?php foreach ($userVMs as $vm): ?>
                    <div class="vm-card" data-vm-id="<?php echo $vm['vm_id']; ?>" data-template="<?php echo htmlspecialchars($vm['template']); ?>">
                        <div class="vm-card-header">
                            <div class="vm-checkbox-container">
                                <input type="checkbox" class="vm-checkbox" data-vm-id="<?php echo $vm['vm_id']; ?>">
                            </div>
                            <div class="vm-title">
                                <h3><?php echo htmlspecialchars($vm['name']); ?></h3>
                                <span class="vm-id"><?php echo $vm['vm_id']; ?></span>
                            </div>
                            <div class="vm-status-container">
                                <span class="vm-status status-<?php echo $vm['status']; ?>">
                                    <?php echo ucfirst($vm['status']); ?>
                                </span>
                                <div class="vm-menu">
                                    <button class="menu-btn" onclick="toggleVMMenu(this)">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="vm-menu-dropdown">
                                        <a href="#" onclick="vmAction('<?php echo $vm['vm_id']; ?>', 'start')">
                                            <i class="fas fa-play"></i> Start
                                        </a>
                                        <a href="#" onclick="vmAction('<?php echo $vm['vm_id']; ?>', 'stop')">
                                            <i class="fas fa-stop"></i> Stop
                                        </a>
                                        <a href="#" onclick="vmAction('<?php echo $vm['vm_id']; ?>', 'restart')">
                                            <i class="fas fa-redo"></i> Restart
                                        </a>
                                        <a href="#" onclick="vmAction('<?php echo $vm['vm_id']; ?>', 'console')">
                                            <i class="fas fa-terminal"></i> Console
                                        </a>
                                        <a href="#" onclick="vmAction('<?php echo $vm['vm_id']; ?>', 'snapshot')">
                                            <i class="fas fa-camera"></i> Snapshot
                                        </a>
                                        <a href="#" onclick="vmAction('<?php echo $vm['vm_id']; ?>', 'backup')">
                                            <i class="fas fa-download"></i> Backup
                                        </a>
                                        <a href="#" onclick="editVM('<?php echo $vm['vm_id']; ?>')">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="#" onclick="vmAction('<?php echo $vm['vm_id']; ?>', 'delete')" class="danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="vm-info">
                            <div class="info-row">
                                <div class="info-item">
                                    <i class="fas fa-microchip"></i>
                                    <span><?php echo $vm['cpu_cores']; ?> CPU</span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-memory"></i>
                                    <span><?php echo $vm['memory']; ?>GB RAM</span>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-item">
                                    <i class="fas fa-hdd"></i>
                                    <span><?php echo $vm['disk_size']; ?>GB Disk</span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-layer-group"></i>
                                    <span><?php echo htmlspecialchars($vm['template']); ?></span>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($vm['description'])): ?>
                        <div class="vm-description">
                            <p><?php echo htmlspecialchars($vm['description']); ?></p>
                        </div>
                        <?php endif; ?>

                        <div class="vm-performance">
                            <div class="performance-item">
                                <span class="label">CPU</span>
                                <div class="bar">
                                    <div class="bar-fill" style="width: 0%"></div>
                                </div>
                                <span class="value">0%</span>
                            </div>
                            <div class="performance-item">
                                <span class="label">Memory</span>
                                <div class="bar">
                                    <div class="bar-fill" style="width: 0%"></div>
                                </div>
                                <span class="value">0%</span>
                            </div>
                            <div class="performance-item">
                                <span class="label">Disk</span>
                                <div class="bar">
                                    <div class="bar-fill" style="width: 0%"></div>
                                </div>
                                <span class="value">0%</span>
                            </div>
                        </div>

                        <div class="vm-actions">
                            <button class="btn btn-sm btn-success" onclick="vmAction('<?php echo $vm['vm_id']; ?>', 'start')" 
                                    <?php echo $vm['status'] === 'running' ? 'disabled' : ''; ?>>
                                <i class="fas fa-play"></i>
                            </button>
                            <button class="btn btn-sm btn-warning" onclick="vmAction('<?php echo $vm['vm_id']; ?>', 'stop')"
                                    <?php echo $vm['status'] === 'stopped' ? 'disabled' : ''; ?>>
                                <i class="fas fa-stop"></i>
                            </button>
                            <button class="btn btn-sm btn-secondary" onclick="vmAction('<?php echo $vm['vm_id']; ?>', 'restart')"
                                    <?php echo $vm['status'] === 'stopped' ? 'disabled' : ''; ?>>
                                <i class="fas fa-redo"></i>
                            </button>
                            <button class="btn btn-sm btn-info" onclick="vmAction('<?php echo $vm['vm_id']; ?>', 'console')">
                                <i class="fas fa-terminal"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div id="vmList" class="vm-list" style="display: none;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAllVMs">
                                </th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Template</th>
                                <th>Resources</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userVMs as $vm): ?>
                            <tr data-vm-id="<?php echo $vm['vm_id']; ?>">
                                <td>
                                    <input type="checkbox" class="vm-checkbox" data-vm-id="<?php echo $vm['vm_id']; ?>">
                                </td>
                                <td>
                                    <div class="vm-name">
                                        <strong><?php echo htmlspecialchars($vm['name']); ?></strong>
                                        <small><?php echo $vm['vm_id']; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="vm-status status-<?php echo $vm['status']; ?>">
                                        <?php echo ucfirst($vm['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($vm['template']); ?></td>
                                <td>
                                    <div class="resource-info">
                                        <span><?php echo $vm['cpu_cores']; ?> CPU</span>
                                        <span><?php echo $vm['memory']; ?>GB RAM</span>
                                        <span><?php echo $vm['disk_size']; ?>GB Disk</span>
                                    </div>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($vm['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-success" onclick="vmAction('<?php echo $vm['vm_id']; ?>', 'start')" 
                                                <?php echo $vm['status'] === 'running' ? 'disabled' : ''; ?>>
                                            <i class="fas fa-play"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning" onclick="vmAction('<?php echo $vm['vm_id']; ?>', 'stop')"
                                                <?php echo $vm['status'] === 'stopped' ? 'disabled' : ''; ?>>
                                            <i class="fas fa-stop"></i>
                                        </button>
                                        <button class="btn btn-sm btn-info" onclick="vmAction('<?php echo $vm['vm_id']; ?>', 'console')">
                                            <i class="fas fa-terminal"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="vmAction('<?php echo $vm['vm_id']; ?>', 'delete')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo htmlspecialchars($template['name']); ?>">
                                    <?php echo htmlspecialchars($template['name']); ?>
                                </option>
                            <?php endforeach; ?>
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
    <script src="assets/js/vms.js"></script>
</body>
</html>