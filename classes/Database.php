<?php
// classes/Database.php - PDO Singleton Database Wrapper

class Database {
    private static $instance = null;
    private $pdo;
    private $mysqli;

    private $host = 'localhost';
    private $user = 'root';
    private $pass = '';
    private $dbname = 'estate';

    private function __construct() {
        // Initialize PDO
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
        } catch (PDOException $e) {
            die("Database Connection Error: " . $e->getMessage());
        }

        // Initialize MySQLi bridge for legacy compatibility
        $this->mysqli = new mysqli($this->host, $this->user, $this->pass, $this->dbname);
        if ($this->mysqli->connect_error) {
            die("MySQLi Connection Error: " . $this->mysqli->connect_error);
        }
        $this->mysqli->set_charset("utf8mb4");
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo() {
        return $this->pdo;
    }

    public function getMysqli() {
        return $this->mysqli;
    }

    // Convenience method for prepared statements
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function execute($sql, $params = []) {
        return $this->query($sql, $params)->rowCount();
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollBack() {
        return $this->pdo->rollBack();
    }
}
