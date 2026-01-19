<?php
class ShipmentService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function saveShipments($shipments) {
        $this->db->beginTransaction();
        
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO shipments 
                (order_code, customer_name, status, amount, shipping_fee, net_amount, delivered_date) 
                VALUES (:order_code, :customer_name, :status, :amount, :shipping_fee, :net_amount, :delivered_date)
                ON DUPLICATE KEY UPDATE
                customer_name = VALUES(customer_name),
                status = VALUES(status),
                amount = VALUES(amount),
                shipping_fee = VALUES(shipping_fee),
                net_amount = VALUES(net_amount),
                delivered_date = VALUES(delivered_date)"
            );
            
            foreach ($shipments as $shipment) {
                // Clean and validate data
                $orderCode = trim($shipment['order_code']);
                $customerName = trim($shipment['customer_name']);
                $status = trim($shipment['status']);
                $amount = floatval($shipment['amount']);
                $shippingFee = floatval($shipment['shipping_fee']);
                $netAmount = $amount - $shippingFee;
                
                // Parse delivered date
                $deliveredDate = null;
                if (!empty($shipment['delivered_date'])) {
                    $deliveredDate = date('Y-m-d', strtotime($shipment['delivered_date']));
                }
                
                $stmt->execute([
                    ':order_code' => $orderCode,
                    ':customer_name' => $customerName,
                    ':status' => $status,
                    ':amount' => $amount,
                    ':shipping_fee' => $shippingFee,
                    ':net_amount' => $netAmount,
                    ':delivered_date' => $deliveredDate
                ]);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function getShipments($filters = []) {
        $sql = "SELECT * FROM shipments WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND delivered_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND delivered_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (order_code LIKE :search OR customer_name LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $sql .= " ORDER BY delivered_date DESC, order_code";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getDeliveredShipments() {
        $stmt = $this->db->prepare(
            "SELECT * FROM shipments 
             WHERE LOWER(status) = 'delivered' 
             ORDER BY delivered_date DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getTotalShipments() {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM shipments");
        return $stmt->fetch()['total'];
    }
    
    public function getShipmentStats() {
        $stats = [];
        
        // Total shipments
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM shipments");
        $stats['total'] = $stmt->fetch()['total'];
        
        // Delivered shipments
        $stmt = $this->db->query(
            "SELECT COUNT(*) as delivered FROM shipments 
             WHERE LOWER(status) = 'delivered'"
        );
        $stats['delivered'] = $stmt->fetch()['delivered'];
        
        // Total shipping fees
        $stmt = $this->db->query("SELECT SUM(shipping_fee) as total_fees FROM shipments");
        $stats['total_fees'] = $stmt->fetch()['total_fees'] ?? 0;
        
        // Total net amount
        $stmt = $this->db->query("SELECT SUM(net_amount) as total_net FROM shipments");
        $stats['total_net'] = $stmt->fetch()['total_net'] ?? 0;
        
        return $stats;
    }
    
    public function getShipmentByOrderCode($orderCode) {
        $stmt = $this->db->prepare(
            "SELECT * FROM shipments WHERE order_code = :order_code"
        );
        $stmt->execute([':order_code' => $orderCode]);
        return $stmt->fetch();
    }
}
