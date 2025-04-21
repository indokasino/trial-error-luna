<?php
/**
 * Database Connection Module
 * 
 * Establishes secure PDO connection to MariaDB
 * Used throughout the Luna Chatbot system
 */

// Prevent direct access
if (!defined('LUNA_ROOT')) {
    die('Access denied');
}

class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        $config = [
            'host' => getenv('DB_HOST') ?: 'localhost',
            'dbname' => getenv('DB_NAME') ?: 'admin_luna_gpt',
            'username' => getenv('DB_USER') ?: 'admin_luna_gpt',
            'password' => getenv('DB_PASS') ?: 'MioSmile5566@@',
            'charset' => 'utf8mb4'
        ];
        
        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']}"
            ];
            
            $this->conn = new PDO($dsn, $config['username'], $config['password'], $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please check the configuration.");
        }
    }
    
    // Singleton pattern - Get instance
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Get PDO connection
    public function getConnection() {
        return $this->conn;
    }
    
    // Prepare and execute query with params
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database Query Error: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }
    
    // Get single row
    public function fetchOne($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("FetchOne Error: " . $e->getMessage());
            return false;
        }
    }
    
    // Get all rows
    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("FetchAll Error: " . $e->getMessage());
            return [];
        }
    }
    
    // Insert and return last insert ID
    public function insert($sql, $params = []) {
        try {
            $this->query($sql, $params);
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("Insert Error: " . $e->getMessage());
            return false;
        }
    }
    
    // Update and return affected rows
    public function update($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Update Error: " . $e->getMessage());
            return false;
        }
    }
    
    // Delete and return affected rows
    public function delete($sql, $params = []) {
        return $this->update($sql, $params);
    }
    
    // Count rows
    public function count($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Count Error: " . $e->getMessage());
            return 0;
        }
    }
}

// Get database instance
function db() {
    return Database::getInstance();
}