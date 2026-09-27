<?php
require_once 'config.php';

class Database {
    private $host = DB_HOST;
    private $port = DB_PORT;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            return $this->conn;
        } catch(PDOException $exception) {
            // Loggear el error y relanzarlo para que el llamador lo maneje
            error_log('Database connection error: ' . $exception->getMessage());
            throw $exception;
        }
    }
}
?>
