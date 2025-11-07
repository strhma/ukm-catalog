<?php
class Database {
    private $host = "localhost";
    private $db_name = "ukm_catalog";
    private $username = "root";
    private $password = "";
    private $conn;
    private $maxRetries = 3;
    private $retryDelay = 1; // seconds

    public function getConnection() {
        if ($this->conn !== null) {
            // Check if connection is still alive
            try {
                $this->conn->query("SELECT 1");
                return $this->conn;
            } catch (PDOException $e) {
                // Connection lost, reconnect
                $this->conn = null;
            }
        }

        $attempts = 0;
        
        while ($attempts < $this->maxRetries) {
            try {
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_PERSISTENT => false, // Non-persistent connection
                        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                    ]
                );
                
                // Set timezone
                $this->conn->exec("SET time_zone = '+00:00'");
                
                return $this->conn;
                
            } catch (PDOException $e) {
                $attempts++;
                
                if ($attempts >= $this->maxRetries) {
                    throw new Exception("Database connection failed after {$this->maxRetries} attempts: " . $e->getMessage());
                }
                
                // Wait before retry
                sleep($this->retryDelay);
            }
        }
        
        throw new Exception("Unable to establish database connection");
    }

    // Method untuk test koneksi
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->query("SELECT 1");
            return $stmt !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    // Method untuk mendapatkan error info
    public function getLastError() {
        if ($this->conn) {
            return $this->conn->errorInfo();
        }
        return null;
    }
}
?>