<?php
$page_title = 'Shipments';
require_once 'templates/header.php';
require_once 'templates/message.php';

require_once 'services/ShipmentService.php';
require_once 'services/FileReader.php';
require_once 'services/ReferenceMapper.php';

$shipmentService = new ShipmentService();
$referenceMapper = new ReferenceMapper();
$message = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['shipment_file'])) {
    try {
        // Check if mappings exist
        if (!$referenceMapper->hasValidMappings()) {
            throw new Exception('Please configure reference mappings first');
        }
        
        $file = $_FILES['shipment_file'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('File size exceeds limit');
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ALLOWED_TYPES)) {
            throw new Exception('Invalid file type. Only CSV and XLSX allowed.');
        }
        
        // Move uploaded file
        $uploadPath = UPLOAD_DIR . uniqid('shipment_') . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Failed to save uploaded file');
        }
        
        // Read file
        $fileReader = new FileReader($uploadPath, 'shipment');
        $columnNames = $fileReader->getColumnNames();
        $rawData = $fileReader->readData();
        
        // Validate and transform data using mappings
        $transformedData = $referenceMapper->validateUploadedData('shipment', $rawData, $columnNames);
        
        // Save shipments
        $shipmentService->saveShipments($transformedData);
        
        // Clean up
        unlink($uploadPath);
        
        $message = 'Shipments uploaded successfully: ' . count($transformedData) . ' records processed';
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get shipments with filters
$filters = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['status'])) {
        $filters['status'] = $_GET['status'];
    }
    if (!empty($_GET['date_from'])) {
        $filters['date_from'] = $_GET['date_from'];
    }
    if (!empty($_GET['date_to'])) {
        $filters['date_to'] = $_GET['date_to'];
    }
    if (!empty($_GET['search'])) {
        $filters['search'] = $_GET['search'];
    }
}

$shipments = $shipmentService->getShipments($filters);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Shipments</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="bi bi-upload me-2"></i> Upload Shipments
        </button>
    </div>
    
    <?php if ($message): displayMessage($messageType, $message); endif; ?>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="Delivered" <?= isset($filters['status']) && $filters['status'] == 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="Pending" <?= isset($filters['status']) && $filters['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Shipped" <?= isset($filters['status']) && $filters['status'] == 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                        <option value="Cancelled" <?= isset($filters['status']) && $filters['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $filters['date_from'] ?? '' ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $filters['date_to'] ?? '' ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Order Code or Customer" value="<?= $filters['search'] ?? '' ?>">
                        <button type="submit" class="btn btn-outline-primary">Filter</button>
                        <?php if (!empty($filters)): ?>
                            <a href="shipments.php" class="btn btn-outline-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Shipments Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Order Code</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Shipping Fee</th>
                            <th>Net Amount</th>
                            <th>Delivered Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($shipments)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No shipments found. Upload a shipment file to get started.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($shipments as $shipment): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($shipment['order_code']) ?></strong></td>
                                    <td><?= htmlspecialchars($shipment['customer_name']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            strtolower($shipment['status']) == 'delivered' ? 'success' : 
                                            (strtolower($shipment['status']) == 'pending' ? 'warning' : 'secondary') 
                                        ?>">
                                            <?= htmlspecialchars($shipment['status']) ?>
                                        </span>
                                    </td>
                                    <td>$<?= number_format($shipment['amount'], 2) ?></td>
                                    <td>$<?= number_format($shipment['shipping_fee'], 2) ?></td>
                                    <td><strong>$<?= number_format($shipment['net_amount'], 2) ?></strong></td>
                                    <td><?= $shipment['delivered_date'] ? date('M d, Y', strtotime($shipment['delivered_date'])) : '-' ?></td>
                                    <td>
                                        <a href="#" class="btn btn-sm btn-outline-primary" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Shipment File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select File (CSV or XLSX)</label>
                        <input type="file" name="shipment_file" class="form-control" accept=".csv,.xlsx" required>
                        <div class="form-text">
                            File must contain columns mapped in reference schema. 
                            Max file size: 10MB
                        </div>
                    </div>
                    <?php if (!$hasMappings): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            Reference mappings not configured. Please configure mappings first.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" <?= !$hasMappings ? 'disabled' : '' ?>>Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
