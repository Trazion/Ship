<?php
class ReferenceMapper {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function saveMappings($mappings) {
        $this->db->beginTransaction();
        
        try {
            // Clear existing mappings
            $this->db->exec("DELETE FROM reference_schema");
            
            $stmt = $this->db->prepare(
                "INSERT INTO reference_schema (file_type, source_column, system_column) 
                 VALUES (:file_type, :source_column, :system_column)"
            );
            
            foreach ($mappings as $mapping) {
                $stmt->execute([
                    ':file_type' => $mapping['file_type'],
                    ':source_column' => trim($mapping['source_column']),
                    ':system_column' => trim($mapping['system_column'])
                ]);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function getMappings($fileType = null) {
        $sql = "SELECT * FROM reference_schema";
        $params = [];
        
        if ($fileType) {
            $sql .= " WHERE file_type = :file_type";
            $params[':file_type'] = $fileType;
        }
        
        $sql .= " ORDER BY file_type, source_column";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getMappingByFileType($fileType) {
        $mappings = $this->getMappings($fileType);
        $result = [];
        
        foreach ($mappings as $mapping) {
            $result[$mapping['source_column']] = $mapping['system_column'];
        }
        
        return $result;
    }
    
    public function getRequiredColumns($fileType) {
        $required = [];
        
        if ($fileType === 'shipment') {
            $required = ['order_code', 'customer_name', 'status', 'amount', 'shipping_fee', 'delivered_date'];
        } elseif ($fileType === 'invoice') {
            $required = ['orderNumber', 'order_amount'];
        }
        
        return $required;
    }
    
    public function validateUploadedData($fileType, $data, $columnNames) {
        $mappings = $this->getMappingByFileType($fileType);
        $requiredColumns = $this->getRequiredColumns($fileType);
        
        // Check if all required system columns are mapped
        $mappedSystemColumns = array_values($mappings);
        
        foreach ($requiredColumns as $required) {
            if (!in_array($required, $mappedSystemColumns)) {
                throw new Exception("Required column '$required' is not mapped in reference file");
            }
        }
        
        // Check if all mapped source columns exist in uploaded file
        foreach ($mappings as $source => $system) {
            if (!in_array($source, $columnNames)) {
                throw new Exception("Mapped column '$source' not found in uploaded file");
            }
        }
        
        // Transform data using mappings
        $transformedData = [];
        
        foreach ($data as $row) {
            $transformedRow = [];
            
            foreach ($mappings as $source => $system) {
                if (isset($row[$source])) {
                    $transformedRow[$system] = $row[$source];
                }
            }
            
            // Validate required fields in transformed row
            foreach ($requiredColumns as $required) {
                if (!isset($transformedRow[$required]) || trim($transformedRow[$required]) === '') {
                    throw new Exception("Missing required field '$required' in data row");
                }
            }
            
            $transformedData[] = $transformedRow;
        }
        
        return $transformedData;
    }
    
    public function hasValidMappings() {
        $shipmentMappings = $this->getMappings('shipment');
        $invoiceMappings = $this->getMappings('invoice');
        
        if (empty($shipmentMappings) || empty($invoiceMappings)) {
            return false;
        }
        
        // Check for critical mapping: order_code for shipments and orderNumber for invoices
        $shipmentSystemCols = array_column($shipmentMappings, 'system_column');
        $invoiceSystemCols = array_column($invoiceMappings, 'system_column');
        
        return in_array('order_code', $shipmentSystemCols) && 
               in_array('orderNumber', $invoiceSystemCols);
    }
}
