<?php
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once 'services/Database.php';
require_once 'services/ReferenceMapper.php';

// Check if reference mappings are set
$referenceMapper = new ReferenceMapper();
$hasMappings = $referenceMapper->hasValidMappings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Shipment System' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar {
            min-height: calc(100vh - 56px);
            background: #f8f9fa;
            border-right: 1px solid #dee2e6;
        }
        .nav-link.active {
            background-color: #0d6efd;
            color: white !important;
        }
        .alert-warning {
            background-color: #fff3cd;
            border-color: #ffc107;
        }
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-box-seam"></i> Shipment System
            </a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    
    <?php if (!$hasMappings): ?>
    <div class="alert alert-warning m-3 d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <div>
            <strong>Warning:</strong> Reference mappings not configured. 
            Please upload reference file before uploading shipments or invoices.
            <a href="reference.php" class="alert-link">Configure Now</a>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <div class="list-group list-group-flush">
                    <a href="index.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>
                    <a href="shipments.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'shipments.php' ? 'active' : '' ?>">
                        <i class="bi bi-truck me-2"></i> Shipments
                    </a>
                    <a href="invoices.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'invoices.php' ? 'active' : '' ?>">
                        <i class="bi bi-receipt me-2"></i> Invoices
                    </a>
                    <a href="missing_orders.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'missing_orders.php' ? 'active' : '' ?>">
                        <i class="bi bi-exclamation-triangle me-2"></i> Missing Orders
                        <?php
                        $compareService = new CompareService();
                        $missingCount = $compareService->getMissingOrdersCount();
                        if ($missingCount > 0): ?>
                            <span class="badge bg-danger float-end"><?= $missingCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="reference.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'reference.php' ? 'active' : '' ?>">
                        <i class="bi bi-gear me-2"></i> Reference Schema
                    </a>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
