<?php
$page_title = 'Missing Orders';
require_once 'templates/header.php';

require_once 'services/CompareService.php';
require_once 'services/ShipmentService.php';

$compareService = new CompareService();
$shipmentService = new ShipmentService();

$missingOrders = $compareService->findMissingOrders();
$report = $compareService->getReconciliationReport();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Missing Orders Report</h1>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="window.print()">
                <i class="bi bi-printer me-2"></i> Print Report
            </button>
            <a href="shipments.php" class="btn btn-primary">
                <i class="bi bi-upload me-2"></i> Upload More Shipments
            </a>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-danger">
                <div class="card-body">
                    <h6 class="text-muted">Total Missing Orders</h6>
                    <h2 class="text-danger"><?= number_format($report['missing_count']) ?></h2>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-danger" 
                             role="progressbar" 
                             style="width: <?= min(100, ($report['missing_count'] / max(1, $report['delivered_count'])) * 100) ?>%">
                        </div>
                    </div>
                    <small class="text-muted">
                        <?= number_format(($report['missing_count'] / max(1, $report['delivered_count'])) * 100, 1) ?>% of delivered shipments
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-warning">
                <div class="card-body">
                    <h6 class="text-muted">Total Value at Risk</h6>
                    <h2 class="text-warning">$<?= number_format($report['total_missing_value'], 2) ?></h2>
                    <small class="text-muted">Net amount of all missing orders</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-info">
                <div class="card-body">
                    <h6 class="text-muted">Reconciliation Rate</h6>
                    <h2 class="text-info"><?= number_format($report['invoiced_percentage'], 1) ?>%</h2>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-info" 
                             role="progressbar" 
                             style="width: <?= $report['invoiced_percentage'] ?>%">
                        </div>
                    </div>
                    <small class="text-muted">
                        <?= number_format($report['invoiced_count']) ?> of <?= number_format($report['delivered_count']) ?> delivered
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Missing Orders Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Orders Delivered but Not Invoiced</h5>
        </div>
        <div class="card-body">
            <?php if (empty($missingOrders)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                    <h4 class="mt-3 text-success">All Clear!</h4>
                    <p class="text-muted">All delivered shipments have matching invoices.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Order Code</th>
                                <th>Customer Name</th>
                                <th>Net Amount</th>
                                <th>Delivered Date</th>
                                <th>Status</th>
                                <th>Days Since Delivery</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($missingOrders as $order): 
                                $days = $order['delivered_date'] ? 
                                    floor((time() - strtotime($order['delivered_date'])) / (60 * 60 * 24)) : 
                                    null;
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($order['order_code']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                    <td>
                                        <strong class="text-danger">$<?= number_format($order['net_amount'], 2) ?></strong>
                                    </td>
                                    <td>
                                        <?= $order['delivered_date'] ? date('M d, Y', strtotime($order['delivered_date'])) : 'N/A' ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?= htmlspecialchars($order['status']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($days !== null): ?>
                                            <span class="badge bg-<?= $days > 30 ? 'danger' : ($days > 7 ? 'warning' : 'info') ?>">
                                                <?= $days ?> days
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $shipment = $shipmentService->getShipmentByOrderCode($order['order_code']);
                                        if ($shipment): ?>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#detailsModal<?= $shipment['id'] ?>">
                                                <i class="bi bi-eye"></i> Details
                                            </button>
                                            
                                            <!-- Details Modal -->
                                            <div class="modal fade" id="detailsModal<?= $shipment['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Shipment Details</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <table class="table table-sm">
                                                                <tr>
                                                                    <th>Order Code:</th>
                                                                    <td><?= htmlspecialchars($shipment['order_code']) ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Customer:</th>
                                                                    <td><?= htmlspecialchars($shipment['customer_name']) ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Status:</th>
                                                                    <td><?= htmlspecialchars($shipment['status']) ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Amount:</th>
                                                                    <td>$<?= number_format($shipment['amount'], 2) ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Shipping Fee:</th>
                                                                    <td>$<?= number_format($shipment['shipping_fee'], 2) ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Net Amount:</th>
                                                                    <td><strong>$<?= number_format($shipment['net_amount'], 2) ?></strong></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Delivered:</th>
                                                                    <td><?= $shipment['delivered_date'] ? date('M d, Y', strtotime($shipment['delivered_date'])) : 'N/A' ?></td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2" class="text-end">Total:</th>
                                <th class="text-danger">$<?= number_format(array_sum(array_column($missingOrders, 'net_amount')), 2) ?></th>
                                <th colspan="4"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- Export Button -->
                <div class="mt-3">
                    <button class="btn btn-outline-success" onclick="exportToCSV()">
                        <i class="bi bi-download me-2"></i> Export to CSV
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function exportToCSV() {
    const rows = [
        ['Order Code', 'Customer Name', 'Net Amount', 'Delivered Date', 'Status', 'Days Since Delivery']
    ];
    
    <?php foreach ($missingOrders as $order): 
        $days = $order['delivered_date'] ? 
            floor((time() - strtotime($order['delivered_date'])) / (60 * 60 * 24)) : 
            'N/A';
    ?>
        rows.push([
            '<?= addslashes($order['order_code']) ?>',
            '<?= addslashes($order['customer_name']) ?>',
            '<?= $order['net_amount'] ?>',
            '<?= $order['delivered_date'] ?>',
            '<?= addslashes($order['status']) ?>',
            '<?= $days ?>'
        ]);
    <?php endforeach; ?>
    
    let csvContent = "data:text/csv;charset=utf-8,";
    rows.forEach(row => {
        csvContent += row.map(cell => `"${cell}"`).join(",") + "\r\n";
    });
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "missing_orders_<?= date('Y-m-d') ?>.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php require_once 'templates/footer.php'; ?>
