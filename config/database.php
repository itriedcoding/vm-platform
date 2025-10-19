<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'vm_platform';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}

// Create database and tables if they don't exist
function initializeDatabase() {
    try {
        $pdo = new PDO("mysql:host=localhost", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS vm_platform");
        $pdo->exec("USE vm_platform");
        
        // Create users table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM('admin', 'user') DEFAULT 'user',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Create virtual_machines table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS virtual_machines (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                template VARCHAR(50) NOT NULL,
                cpu_cores INT NOT NULL,
                memory INT NOT NULL,
                disk_size INT NOT NULL,
                network_config JSON,
                status ENUM('stopped', 'running', 'paused', 'error') DEFAULT 'stopped',
                vm_id VARCHAR(100) UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        // Create vm_templates table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS vm_templates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                os_type VARCHAR(50) NOT NULL,
                os_version VARCHAR(50) NOT NULL,
                min_cpu INT DEFAULT 1,
                min_memory INT DEFAULT 1,
                min_disk INT DEFAULT 10,
                image_url VARCHAR(255),
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create vm_networks table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS vm_networks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                type ENUM('bridge', 'nat', 'isolated') NOT NULL,
                subnet VARCHAR(20),
                gateway VARCHAR(20),
                dns_servers JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create vm_snapshots table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS vm_snapshots (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vm_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                snapshot_id VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (vm_id) REFERENCES virtual_machines(id) ON DELETE CASCADE
            )
        ");
        
        // Create vm_backups table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS vm_backups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vm_id INT NOT NULL,
                backup_name VARCHAR(100) NOT NULL,
                backup_path VARCHAR(255) NOT NULL,
                backup_size BIGINT,
                status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (vm_id) REFERENCES virtual_machines(id) ON DELETE CASCADE
            )
        ");
        
        // Create system_logs table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS system_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                action VARCHAR(100) NOT NULL,
                resource_type VARCHAR(50),
                resource_id INT,
                details JSON,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
        
        // Insert default templates
        $templates = [
            ['Ubuntu 20.04 LTS', 'Linux', 'Ubuntu 20.04', 1, 1, 10, 'https://cloud-images.ubuntu.com/focal/current/focal-server-cloudimg-amd64.img', 'Ubuntu 20.04 LTS Server'],
            ['Ubuntu 22.04 LTS', 'Linux', 'Ubuntu 22.04', 1, 1, 10, 'https://cloud-images.ubuntu.com/jammy/current/jammy-server-cloudimg-amd64.img', 'Ubuntu 22.04 LTS Server'],
            ['CentOS 8', 'Linux', 'CentOS 8', 1, 1, 10, 'https://cloud.centos.org/centos/8/x86_64/images/CentOS-8-GenericCloud-8.4.2105-20210603.0.x86_64.qcow2', 'CentOS 8 Stream'],
            ['Debian 11', 'Linux', 'Debian 11', 1, 1, 10, 'https://cloud.debian.org/images/cloud/bullseye/latest/debian-11-generic-amd64.qcow2', 'Debian 11 Bullseye'],
            ['Windows 10', 'Windows', 'Windows 10', 2, 4, 50, '', 'Windows 10 Professional'],
            ['Windows Server 2019', 'Windows', 'Windows Server 2019', 2, 4, 50, '', 'Windows Server 2019 Standard']
        ];
        
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO vm_templates (name, os_type, os_version, min_cpu, min_memory, min_disk, image_url, description) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($templates as $template) {
            $stmt->execute($template);
        }
        
        // Insert default networks
        $networks = [
            ['default-bridge', 'bridge', '192.168.100.0/24', '192.168.100.1', '["8.8.8.8", "8.8.4.4"]'],
            ['default-nat', 'nat', '192.168.200.0/24', '192.168.200.1', '["8.8.8.8", "8.8.4.4"]']
        ];
        
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO vm_networks (name, type, subnet, gateway, dns_servers) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($networks as $network) {
            $stmt->execute($network);
        }
        
        return true;
    } catch(PDOException $e) {
        error_log("Database initialization error: " . $e->getMessage());
        return false;
    }
}

// Initialize database on first run
initializeDatabase();
?>