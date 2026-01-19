<?php
$page_title = 'Invoices';
require_once 'templates/header.php';
require_once 'templates/message.php';

require_once 'services/InvoiceService.php';
require_once 'services/FileReader.php';
require_once 'services/ReferenceMapper.php';

$invoiceService = new InvoiceService();
$referenceMapper = new ReferenceMapper();
$message = '';
$messageType = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['invoice_file'])) {
    try {
        // Check if mappings exist
        if (!$referenceMapper->hasValidMappings()) {
            throw new Exception('Please configure reference mappings first');
        }
        
        $file = $_FILES['invoice_file'];
        
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
        
        // Check for invoice metadata
        if (empty($_POST['invoice_number']) || empty($_POST['invoice_date'])) {
            throw new Exception('Invoice number and date are required');
        }
        
        // Move uploaded file
        $uploadPath = UPLOAD_DIR . uniqid('invoice_') . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Failed to save uploaded file');
        }
        
        // Read file
        $fileReader = new FileReader($uploadPath, 'invoice');
        $columnNames = $fileReader->getColumnNames();
        $rawData = $fileReader->readData();
        
        // Validate and transform data using mappings
        $transformedData = $referenceMapper->validateUploadedData('invoice', $rawData, $columnNames);
        
        // Group by invoice if multiple invoices in file
        $invoices = [];
        $invoiceData = [
            'invoice_number' => trim($_POST['invoice_number']),
            'invoice_date' => $_POST['invoice_date']
        ];
        
        // Save invoice
        $invoiceId = $invoiceService->saveInvoice($invoiceData, $transformedData);
        
        // Clean up
        unlink($uploadPath);
        
        $message = 'Invoice uploaded successfully: ' . count($transformedData) . ' orders processed';
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get invoices with filters
$filters = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
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

$invoices = $invoiceService->getInvoices($filters);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Invoices</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="bi bi-upload me-2"></i> Upload Invoice
        </button>
    </div>
    
    <?php if ($message): displayMessage($messageType, $message); endif; ?>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $filters['date_from'] ?? '' ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $filters['date_to'] ?? '' ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Invoice Number" value="<?= $filters['search'] ?? '' ?>">
                        <button type="submit" class="btn btn-outline-primary">Search</button>
                        <?php if (!empty($filters)): ?>
                            <a href="invoices.php" class="btn btn-outline-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Invoices Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Orders</th>
                            <th>Total Amount</th>
                            <th>Calculated Total</th>
                            <th>Difference</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No invoices found. Upload an invoice file to get started.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $invoice): 
                                $difference = $invoice['total_amount'] - $invoice['calculated_total'];
                            ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong></td>
                                    <td><?= date('M d, Y', strtotime($invoice['invoice_date'])) ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= $invoice['order_count'] ?></span>
                                    </td>
                                    <td>$<?= number_format($invoice['total_amount'], 2) ?></td>
                                    <td>$<?= number_format($invoice['calculated_total'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?= abs($difference) < 0.01 ? 'success' : 'danger' ?>">
                                            $<?= number_format($difference, 2) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($invoice['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary view-orders" 
                                                data-invoice-id="<?= $invoice['id'] ?>"
                                                title="View Orders">
                                            <i class="bi bi-list"></i>
                                        </button>
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
                    <h5 class="modal-title">Upload Invoice File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Invoice Number *</label>
                        <input type="text" name="invoice_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Invoice Date *</label>
                        <input type="date" name="invoice_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select File (CSV or XLSX) *</label>
                        <input type="file" name="invoice_file" class="form-control" accept=".csv,.xlsx" required>
                        <div class="form-text">
                            File must contain orderNumber and order_amount columns (or mapped equivalents).
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

<!-- Orders Modal -->
<div class="modal fade" id="ordersModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Invoice Orders</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Order Amount</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTableBody">
                            <!-- Orders will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// View orders functionality
document.querySelectorAll('.view-orders').forEach(button => {
    button.addEventListener('click', async function() {
        const invoiceId = this.dataset.invoiceId;
        
        try {
            const response = await fetch(`?action=get_orders&invoice_id=${invoiceId}`);
            const orders = await response.json();
            
            const tableBody = document.getElementById('ordersTableBody');
            tableBody.innerHTML = '';
            
            orders.forEach(order => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${order.orderNumber}</td>
                    <td>$${parseFloat(order.order_amount).toFixed(2)}</td>
                    <td>${new Date(order.created_at).toLocaleDateString()}</td>
                `;
                tableBody.appendChild(row);
            });
            
            const modal = new bootstrap.Modal(document.getElementById('ordersModal'));
            modal.show();
            
        } catch (error) {
            alert('Error loading orders: ' + error.message);
        }
    });
});
</script>

<?php
// Handle AJAX request for orders
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_orders') {
    $invoiceId = $_GET['invoice_id'] ?? 0;
    $orders = $invoiceService->getInvoiceOrders($invoiceId);
    header('Content-Type: application/json');
    echo json_encode($orders);
    exit;
}
?>

<?php require_once 'templates/footer.php'; ?>
