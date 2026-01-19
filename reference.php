<?php
$page_title = 'Reference Schema';
require_once 'templates/header.php';
require_once 'templates/message.php';

require_once 'services/ReferenceMapper.php';
require_once 'services/FileReader.php';

$referenceMapper = new ReferenceMapper();
$message = '';
$messageType = '';

// Handle reference file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['reference_file'])) {
    try {
        $file = $_FILES['reference_file'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('File size exceeds limit');
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx'])) {
            throw new Exception('Invalid file type. Only CSV and XLSX allowed.');
        }
        
        // Move uploaded file
        $uploadPath = UPLOAD_DIR . uniqid('reference_') . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Failed to save uploaded file');
        }
        
        // Read reference file
        $fileReader = new FileReader($uploadPath, 'reference');
        $data = $fileReader->readData();
        
        // Validate reference file structure
        $requiredColumns = ['file_type', 'source_column', 'system_column'];
        $firstRow = $data[0] ?? [];
        
        foreach ($requiredColumns as $col) {
            if (!isset($firstRow[$col])) {
                throw new Exception("Reference file must contain column: $col");
            }
        }
        
        // Validate file_type values
        foreach ($data as $row) {
            $fileType = strtolower(trim($row['file_type']));
            if (!in_array($fileType, ['shipment', 'invoice'])) {
                throw new Exception("Invalid file_type: {$row['file_type']}. Must be 'shipment' or 'invoice'");
            }
            
            if (empty($row['source_column']) || empty($row['system_column'])) {
                throw new Exception('source_column and system_column cannot be empty');
            }
        }
        
        // Save mappings
        $referenceMapper->saveMappings($data);
        
        // Clean up
        unlink($uploadPath);
        
        $message = 'Reference schema uploaded successfully: ' . count($data) . ' mappings saved';
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get current mappings
$shipmentMappings = $referenceMapper->getMappings('shipment');
$invoiceMappings = $referenceMapper->getMappings('invoice');
$hasValidMappings = $referenceMapper->hasValidMappings();

// Get required columns
$requiredShipmentColumns = $referenceMapper->getRequiredColumns('shipment');
$requiredInvoiceColumns = $referenceMapper->getRequiredColumns('invoice');
?>

<div class="container-fluid">
    <h1 class="h2 mb-4">Reference Schema Management</h1>
    
    <?php if ($message): displayMessage($messageType, $message); endif; ?>
    
    <!-- Status Card -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="card-title">Schema Status</h5>
                    <p class="card-text">
                        Reference mappings define how uploaded files are mapped to system columns.
                        This is required before uploading shipment or invoice files.
                    </p>
                    <div class="d-flex gap-3">
                        <div>
                            <span class="badge bg-<?= !empty($shipmentMappings) ? 'success' : 'danger' ?>">
                                Shipment Mappings: <?= count($shipmentMappings) ?>
                            </span>
                        </div>
                        <div>
                            <span class="badge bg-<?= !empty($invoiceMappings) ? 'success' : 'danger' ?>">
                                Invoice Mappings: <?= count($invoiceMappings) ?>
                            </span>
                        </div>
                        <div>
                            <span class="badge bg-<?= $hasValidMappings ? 'success' : 'warning' ?>">
                                <?= $hasValidMappings ? 'Valid Configuration' : 'Needs Configuration' ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="bi bi-upload me-2"></i> Upload Reference File
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Shipment Mappings -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Shipment File Mappings</h5>
                </div>
                <div class="card-body">
                    <h6>Required System Columns:</h6>
                    <div class="mb-3">
                        <?php foreach ($requiredShipmentColumns as $col): ?>
                            <span class="badge bg-primary me-1 mb-1"><?= $col ?></span>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (empty($shipmentMappings)): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            No shipment mappings configured.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Source Column (in file)</th>
                                        <th>System Column (internal)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($shipmentMappings as $mapping): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($mapping['source_column']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    in_array($mapping['system_column'], $requiredShipmentColumns) ? 'success' : 'info'
                                                ?>">
                                                    <?= htmlspecialchars($mapping['system_column']) ?>
                                                    <?php if (in_array($mapping['system_column'], $requiredShipmentColumns)): ?>
                                                        <i class="bi bi-check-circle ms-1"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Invoice Mappings -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Invoice File Mappings</h5>
                </div>
                <div class="card-body">
                    <h6>Required System Columns:</h6>
                    <div class="mb-3">
                        <?php foreach ($requiredInvoiceColumns as $col): ?>
                            <span class="badge bg-primary me-1 mb-1"><?= $col ?></span>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (empty($invoiceMappings)): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            No invoice mappings configured.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Source Column (in file)</th>
                                        <th>System Column (internal)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoiceMappings as $mapping): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($mapping['source_column']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    in_array($mapping['system_column'], $requiredInvoiceColumns) ? 'success' : 'info'
                                                ?>">
                                                    <?= htmlspecialchars($mapping['system_column']) ?>
                                                    <?php if (in_array($mapping['system_column'], $requiredInvoiceColumns)): ?>
                                                        <i class="bi bi-check-circle ms-1"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sample Reference Section -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Sample Reference File Format</h5>
        </div>
        <div class="card-body">
            <p>Create a CSV or Excel file with the following columns:</p>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>file_type</th>
                            <th>source_column</th>
                            <th>system_column</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>shipment</td>
                            <td>Order Code</td>
                            <td>order_code</td>
                        </tr>
                        <tr>
                            <td>shipment</td>
                            <td>Customer Name</td>
                            <td>customer_name</td>
                        </tr>
                        <tr>
                            <td>shipment</td>
                            <td>Delivery Status</td>
                            <td>status</td>
                        </tr>
                        <tr>
                            <td>shipment</td>
                            <td>Order Amount</td>
                            <td>amount</td>
                        </tr>
                        <tr>
                            <td>shipment</td>
                            <td>Shipping Fees</td>
                            <td>shipping_fee</td>
                        </tr>
                        <tr>
                            <td>shipment</td>
                            <td>Delivery Date</td>
                            <td>delivered_date</td>
                        </tr>
                        <tr>
                            <td>invoice</td>
                            <td>Order Number</td>
                            <td>orderNumber</td>
                        </tr>
                        <tr>
                            <td>invoice</td>
                            <td>Order Amount</td>
                            <td>order_amount</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <button class="btn btn-outline-primary" onclick="downloadSample()">
                    <i class="bi bi-download me-2"></i> Download Sample CSV
                </button>
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
                    <h5 class="modal-title">Upload Reference File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Reference File (CSV or XLSX)</label>
                        <input type="file" name="reference_file" class="form-control" accept=".csv,.xlsx" required>
                        <div class="form-text">
                            File must contain columns: file_type, source_column, system_column
                            Max file size: 10MB
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        Uploading a new reference file will replace all existing mappings.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function downloadSample() {
    const sampleData = [
        ['file_type', 'source_column', 'system_column'],
        ['shipment', 'Order Code', 'order_code'],
        ['shipment', 'Customer Name', 'customer_name'],
        ['shipment', 'Delivery Status', 'status'],
        ['shipment', 'Order Amount', 'amount'],
        ['shipment', 'Shipping Fees', 'shipping_fee'],
        ['shipment', 'Delivery Date', 'delivered_date'],
        ['invoice', 'Order Number', 'orderNumber'],
        ['invoice', 'Order Amount', 'order_amount']
    ];
    
    let csvContent = "data:text/csv;charset=utf-8,";
    sampleData.forEach(row => {
        csvContent += row.map(cell => `"${cell}"`).join(",") + "\r\n";
    });
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "reference_schema_sample.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php require_once 'templates/footer.php'; ?>
