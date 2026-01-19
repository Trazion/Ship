<?php
class CompareService {
    private $db;
    private $shipmentService;
    private $invoiceService;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->shipmentService = new ShipmentService();
        $this->invoiceService = new InvoiceService();
    }
    
    public function findMissingOrders() {
        // Get all delivered shipments
        $deliveredShipments = $this->shipmentService->getDeliveredShipments();
        
        // Get all invoiced order numbers
        $invoicedOrderNumbers = $this->invoiceService->getAllInvoicedOrderNumbers();
        
        // Find shipments not in invoices
        $missingOrders = [];
        
        foreach ($deliveredShipments as $shipment) {
            if (!in_array($shipment['order_code'], $invoicedOrderNumbers)) {
                $missingOrders[] = [
                    'order_code' => $shipment['order_code'],
                    'customer_name' => $shipment['customer_name'],
                    'net_amount' => $shipment['net_amount'],
                    'delivered_date' => $shipment['delivered_date'],
                    'status' => $shipment['status']
                ];
            }
        }
        
        return $missingOrders;
    }
    
    public function getMissingOrdersCount() {
        $missingOrders = $this->findMissingOrders();
        return count($missingOrders);
    }
    
    public function getReconciliationReport() {
        $report = [];
        
        // Missing orders
        $report['missing_orders'] = $this->findMissingOrders();
        $report['missing_count'] = count($report['missing_orders']);
        
        // Delivered shipments count
        $deliveredShipments = $this->shipmentService->getDeliveredShipments();
        $report['delivered_count'] = count($deliveredShipments);
        
        // Invoiced orders count
        $invoicedOrderNumbers = $this->invoiceService->getAllInvoicedOrderNumbers();
        $report['invoiced_count'] = count($invoicedOrderNumbers);
        
        // Percentage invoiced
        if ($report['delivered_count'] > 0) {
            $report['invoiced_percentage'] = 
                ($report['invoiced_count'] / $report['delivered_count']) * 100;
        } else {
            $report['invoiced_percentage'] = 0;
        }
        
        // Total value missing
        $report['total_missing_value'] = array_sum(
            array_column($report['missing_orders'], 'net_amount')
        );
        
        return $report;
    }
}
