<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetException;

class FileReader {
    private $filePath;
    private $fileType;
    
    public function __construct($filePath, $fileType) {
        $this->filePath = $filePath;
        $this->fileType = $fileType;
    }
    
    public function readData() {
        $extension = strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION));
        
        if ($extension === 'csv') {
            return $this->readCSV();
        } elseif ($extension === 'xlsx') {
            return $this->readExcel();
        } else {
            throw new Exception("Unsupported file format: $extension");
        }
    }
    
    private function readCSV() {
        $data = [];
        
        if (($handle = fopen($this->filePath, "r")) !== FALSE) {
            $headers = fgetcsv($handle);
            
            // Clean headers
            $headers = array_map('trim', $headers);
            
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (count($row) === count($headers)) {
                    $data[] = array_combine($headers, $row);
                }
            }
            fclose($handle);
        }
        
        return $data;
    }
    
    private function readExcel() {
        try {
            $spreadsheet = IOFactory::load($this->filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();
            
            if (empty($data)) {
                return [];
            }
            
            // First row as headers
            $headers = array_map('trim', array_shift($data));
            
            // Convert to associative array
            $result = [];
            foreach ($data as $row) {
                if (count($row) === count($headers)) {
                    $result[] = array_combine($headers, $row);
                }
            }
            
            return $result;
            
        } catch (SpreadsheetException $e) {
            throw new Exception("Error reading Excel file: " . $e->getMessage());
        }
    }
    
    public function getColumnNames() {
        $extension = strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION));
        
        if ($extension === 'csv') {
            return $this->getCSVColumnNames();
        } elseif ($extension === 'xlsx') {
            return $this->getExcelColumnNames();
        }
        
        return [];
    }
    
    private function getCSVColumnNames() {
        if (($handle = fopen($this->filePath, "r")) !== FALSE) {
            $headers = fgetcsv($handle);
            fclose($handle);
            return array_map('trim', $headers);
        }
        return [];
    }
    
    private function getExcelColumnNames() {
        try {
            $spreadsheet = IOFactory::load($this->filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();
            
            if (empty($data)) {
                return [];
            }
            
            return array_map('trim', $data[0]);
            
        } catch (SpreadsheetException $e) {
            throw new Exception("Error reading Excel file: " . $e->getMessage());
        }
    }
}
