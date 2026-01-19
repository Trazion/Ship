<?php
class InvoiceService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function saveInvoice($invoiceData, $orders) {
        $this->db->beginTransaction();
        
        try {
            // Save invoice
            $stmt = $this->db->prepare(
                "INSERT INTO invoices (invoice_number, invoice_date, total_amount) 
                 VALUES (:invoice_number, :invoice_date, :total_amount)
                 ON DUPLICATE KEY UPDATE
                 invoice_date = VALUES(invoice_date),
                 total_amount = VALUES(total_amount)"
            );
            
            $totalAmount = array_sum(array_column($orders, 'order_amount'));
            
            $stmt->execute([
                ':invoice_number' => trim($invoiceData['invoice_number']),
                ':invoice_date' => date('Y-m-d', strtotime($invoiceData['invoice_date'])),
                ':total_amount' => $totalAmount
            ]);
            
            $invoiceId = $this->db->lastInsertId();
            
            // If duplicate key, get existing ID
            if ($invoiceId == 0) {
                $stmt = $this->db->prepare(
                    "SELECT id FROM invoices WHERE invoice_number = :invoice_number"
                );
                $stmt->execute([':invoice_number' => trim($invoiceData['invoice_number'])]);
                $invoiceId = $stmt->fetch()['id'];
            }
            
            // Save invoice orders
            $orderStmt = $this->db->prepare(
                "INSERT INTO invoice_orders (invoice_id, orderNumber, order_amount) 
                 VALUES (:invoice_id, :orderNumber, :order_amount)
                 ON DUPLICATE KEY UPDATE
                 order_amount = VALUES(order_amount)"
            );
            
            foreach ($orders as $order) {
                $orderStmt->execute([
                    ':invoice_id' => $invoiceId,
                    ':orderNumber' => trim($order['orderNumber']),
                    ':order_amount' => floatval($order['order_amount'])
                ]);
            }
            
            $this->db->commit();
            return $invoiceId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function getInvoices($filters = []) {
        $sql = "SELECT i.*, 
                COUNT(io.id) as order_count,
                SUM(io.order_amount) as calculated_total
                FROM invoices i
                LEFT JOIN invoice_orders io ON i.id = io.invoice_id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND i.invoice_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND i.invoice_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (i.invoice_number LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $sql .= " GROUP BY i.id ORDER BY i.invoice_date DESC, i.invoice_number";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getInvoice($invoiceId) {
        $stmt = $this->db->prepare(
            "SELECT * FROM invoices WHERE id = :id"
        );
        $stmt->execute([':id' => $invoiceId]);
        return $stmt->fetch();
    }
    
    public function getInvoiceOrders($invoiceId) {
        $stmt = $this->db->prepare(
            "SELECT * FROM invoice_orders 
             WHERE invoice_id = :invoice_id 
             ORDER BY orderNumber"
        );
        $stmt->execute([':invoice_id' => $invoiceId]);
        return $stmt->fetchAll();
    }
    
    public function getAllInvoicedOrderNumbers() {
        $stmt = $this->db->query(
            "SELECT DISTINCT orderNumber FROM invoice_orders"
        );
        return array_column($stmt->fetchAll(), 'orderNumber');
    }
    
    public function getTotalInvoices() {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM invoices");
        return $stmt->fetch()['total'];
    }
    
    public function getTotalInvoiceAmount() {
        $stmt = $this->db->query("SELECT SUM(total_amount) as total FROM invoices");
        return $stmt->fetch()['total'] ?? 0;
    }
    
    public function getInvoiceStats() {
        $stats = [];
        
        // Total invoices
        $stats['total_invoices'] = $this->getTotalInvoices();
        
        // Total invoiced amount
        $stats['total_amount'] = $this->getTotalInvoiceAmount();
        
        // Average invoice amount
        $stmt = $this->db->query(
            "SELECT AVG(total_amount) as avg_amount FROM invoices"
        );
        $stats['avg_amount'] = $stmt->fetch()['avg_amount'] ?? 0;
        
        // Total orders invoiced
        $stmt = $this->db->query(
            "SELECT COUNT(DISTINCT orderNumber) as total_orders FROM invoice_orders"
        );
        $stats['total_orders'] = $stmt->fetch()['total_orders'];
        
        return $stats;
    }
}
