<?php

class Database {
    private static $instance = null;
    private $connection;
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $charset;

    private function __construct() {
        // Load environment variables
        $this->loadEnv(__DIR__ . '/../.env');
        
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->dbname = $_ENV['DB_NAME'] ?? 'mailfabriek_nl';
        $this->username = $_ENV['DB_USER'] ?? 'root';
        $this->password = $_ENV['DB_PASS'] ?? '';
        $this->charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
        
        $this->validateCredentials();
        $this->connect();
    }

    /**
     * Get the instance of the Database class
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load environment variables from .env file
     */
    private function loadEnv($path) {
        if (!file_exists($path)) {
            return false;
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) continue;
            
            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value, " \t\n\r\0\x0B\"'"); // Remove quotes and whitespace
                
                $_ENV[$name] = $value;
                putenv("$name=$value");
            }
        }
        return true;
    }

    /**
     * Validate database credentials
     */
    private function validateCredentials() {
        if (empty($this->host)) {
            throw new Exception("Database host is required");
        }
        if (empty($this->dbname)) {
            throw new Exception("Database name is required");
        }
        if (empty($this->username)) {
            throw new Exception("Database username is required");
        }
        // Note: Password can be empty for local development, but warn about it
        if (empty($this->password) && $this->host !== 'localhost') {
            error_log("Warning: Database password is empty for non-localhost connection");
        }
    }

    /**
     * Connect to database
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }


    /**
     * This method prepares and executes a SQL statement with parameters.
     * @param string $sql
     * @param array $params
     * @return PDOStatement
     * @throws Exception
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }

    /**
     * This method executes a SQL statement without returning results.
     * @param string $sql
     * @param array $params
     * @return bool
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch a single row from the database
     * @param string $sql
     * @param array $params
     * @return array|false
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
}