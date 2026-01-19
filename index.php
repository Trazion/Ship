<?php
$page_title = 'Dashboard';
require_once 'templates/header.php';

require_once 'services/ShipmentService.php';
require_once 'services/InvoiceService.php';
require_once 'services/CompareService.php';

$shipmentService = new ShipmentService();
$invoiceService = new InvoiceService();
$compareService = new CompareService();

// Get stats
$shipmentStats = $shipmentService->getShipmentStats();
$invoiceStats = $invoiceService->getInvoiceStats();
$reconciliationReport = $compareService->getReconciliationReport();
?>

<div class="container-fluid">
    <h1 class="h2 mb-4">Dashboard</h1>
    
    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card stat-card border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">Total Shipments</h6>
                            <h3 class="mb-0"><?= number_format($shipmentStats['total']) ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-truck text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <small class="text-muted"><?= number_format($shipmentStats['delivered']) ?> delivered</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stat-card border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">Total Invoices</h6>
                            <h3 class="mb-0"><?= number_format($invoiceStats['total_invoices']) ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-receipt text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <small class="text-muted"><?= number_format($invoiceStats['total_orders']) ?> orders</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stat-card border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">Invoice Amount</h6>
                            <h3 class="mb-0">$<?= number_format($invoiceStats['total_amount'], 2) ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-currency-dollar text-info" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <small class="text-muted">Avg: $<?= number_format($invoiceStats['avg_amount'], 2) ?></small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stat-card border-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">Missing Orders</h6>
                            <h3 class="mb-0"><?= number_format($reconciliationReport['missing_count']) ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-exclamation-triangle text-danger" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <small class="text-muted">$<?= number_format($reconciliationReport['total_missing_value'], 2) ?> value</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reconciliation Report -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Reconciliation Status</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <td>Delivered Shipments:</td>
                                    <td class="text-end"><?= number_format($reconciliationReport['delivered_count']) ?></td>
                                </tr>
                                <tr>
                                    <td>Invoiced Orders:</td>
                                    <td class="text-end"><?= number_format($reconciliationReport['invoiced_count']) ?></td>
                                </tr>
                                <tr>
                                    <td>Missing Orders:</td>
                                    <td class="text-end text-danger"><?= number_format($reconciliationReport['missing_count']) ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <div class="progress" style="height: 30px;">
                                <div class="progress-bar bg-success" 
                                     role="progressbar" 
                                     style="width: <?= $reconciliationReport['invoiced_percentage'] ?>%">
                                    <?= number_format($reconciliationReport['invoiced_percentage'], 1) ?>% Invoiced
                                </div>
                            </div>
                            <div class="mt-3 text-center">
                                <small class="text-muted">
                                    <?= number_format($reconciliationReport['invoiced_percentage'], 1) ?>% of delivered shipments have been invoiced
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="shipments.php" class="btn btn-outline-primary">
                            <i class="bi bi-upload me-2"></i> Upload Shipments
                        </a>
                        <a href="invoices.php" class="btn btn-outline-success">
                            <i class="bi bi-upload me-2"></i> Upload Invoices
                        </a>
                        <a href="missing_orders.php" class="btn btn-outline-danger">
                            <i class="bi bi-eye me-2"></i> View Missing Orders
                        </a>
                        <?php if (!$hasMappings): ?>
                            <a href="reference.php" class="btn btn-warning">
                                <i class="bi bi-gear me-2"></i> Configure Mappings
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
