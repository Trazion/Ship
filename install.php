<?php
session_start();

// Check if already installed
if (file_exists('config.php')) {
    die('System already installed. Please delete config.php to reinstall.');
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_name = $_POST['db_name'] ?? '';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';
    $admin_user = $_POST['admin_user'] ?? 'admin';
    $admin_pass = $_POST['admin_pass'] ?? '';
    
    // Validate
    if (empty($db_name) || empty($db_user) || empty($admin_pass)) {
        $error = 'Please fill all required fields';
    } else {
        try {
            // Test database connection
            $pdo = new PDO(
                "mysql:host=$db_host;charset=utf8mb4",
                $db_user,
                $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$db_name`");
            
            // Create tables
            $tables = [
                "CREATE TABLE shipments (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    order_code VARCHAR(100) NOT NULL UNIQUE,
                    customer_name VARCHAR(255) NOT NULL,
                    status VARCHAR(50) NOT NULL,
                    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                    shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
                    net_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                    delivered_date DATE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_order_code (order_code),
                    INDEX idx_status (status),
                    INDEX idx_delivered_date (delivered_date)
                )",
                
                "CREATE TABLE invoices (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    invoice_number VARCHAR(100) NOT NULL UNIQUE,
                    invoice_date DATE NOT NULL,
                    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_invoice_number (invoice_number),
                    INDEX idx_invoice_date (invoice_date)
                )",
                
                "CREATE TABLE invoice_orders (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    invoice_id INT NOT NULL,
                    orderNumber VARCHAR(100) NOT NULL,
                    order_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
                    INDEX idx_orderNumber (orderNumber),
                    INDEX idx_invoice_id (invoice_id),
                    UNIQUE KEY unique_invoice_order (invoice_id, orderNumber)
                )",
                
                "CREATE TABLE reference_schema (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    file_type ENUM('shipment', 'invoice') NOT NULL,
                    source_column VARCHAR(100) NOT NULL,
                    system_column VARCHAR(100) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_file_type (file_type),
                    UNIQUE KEY unique_mapping (file_type, source_column, system_column)
                )",
                
                "CREATE TABLE users (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    username VARCHAR(50) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    role ENUM('admin', 'user') DEFAULT 'user',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )"
            ];
            
            foreach ($tables as $tableSql) {
                $pdo->exec($tableSql);
            }
            
            // Insert admin user
            $password_hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')");
            $stmt->execute([$admin_user, $password_hash]);
            
            // Create config file
            $config_content = "<?php
// Database configuration
define('DB_HOST', '" . addslashes($db_host) . "');
define('DB_NAME', '" . addslashes($db_name) . "');
define('DB_USER', '" . addslashes($db_user) . "');
define('DB_PASS', '" . addslashes($db_pass) . "');

// Application settings
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_TYPES', ['csv', 'xlsx']);
define('SESSION_LIFETIME', 3600); // 1 hour

// Create uploads directory if not exists
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
";
            
            file_put_contents('config.php', $config_content);
            
            // Create uploads directory
            if (!file_exists('uploads')) {
                mkdir('uploads', 0755, true);
            }
            
            $success = true;
            
        } catch (PDOException $e) {
            $error = 'Database Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Shipment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Install Shipment & Invoice Reconciliation System</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <h5>Installation Successful!</h5>
                                <p>Database tables created successfully. Admin user created.</p>
                                <p><strong>Important:</strong> Delete or rename this install.php file for security.</p>
                                <a href="login.php" class="btn btn-success">Go to Login</a>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <h5>Database Configuration</h5>
                                <div class="mb-3">
                                    <label class="form-label">Database Host</label>
                                    <input type="text" class="form-control" name="db_host" value="localhost" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Database Name *</label>
                                    <input type="text" class="form-control" name="db_name" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Database Username *</label>
                                    <input type="text" class="form-control" name="db_user" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Database Password</label>
                                    <input type="password" class="form-control" name="db_pass">
                                </div>
                                
                                <h5 class="mt-4">Admin Account</h5>
                                <div class="mb-3">
                                    <label class="form-label">Admin Username *</label>
                                    <input type="text" class="form-control" name="admin_user" value="admin" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Admin Password *</label>
                                    <input type="password" class="form-control" name="admin_pass" required>
                                </div>
                                
                                <div class="alert alert-info">
                                    <small>* Required fields</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Install System</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
