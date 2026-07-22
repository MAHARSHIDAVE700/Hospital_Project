<?php

class NeonDB {
    private $pdo;
    public $error = '';
    public $connect_error = null;
    
    public function __construct($host, $user, $pass, $db) {
        $endpoint = "ep-blue-boat-auib76so-pooler";
        
        try {
            // Attempt standard connection with endpoint in options
            $dsn = "pgsql:host=$host;port=5432;dbname=$db;sslmode=require;options='endpoint=$endpoint'";
            $this->pdo = new PDO($dsn, $user, $pass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        } catch (PDOException $e) {
            try {
                // Attempt connection with endpoint prefixed in username
                $dsn = "pgsql:host=$host;port=5432;dbname=$db;sslmode=require";
                $prefixed_user = $endpoint . "$" . $user;
                $this->pdo = new PDO($dsn, $prefixed_user, $pass);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            } catch (PDOException $e2) {
                $this->connect_error = $e2->getMessage();
                $this->error = $e2->getMessage();
                throw $e2;
            }
        }
    }
    
    public function prepare($sql) {
        try {
            // PostgreSQL uses standard SQL, but just in case, strip MySQL backticks
            $sql = str_replace('`', '', $sql);
            $stmt = $this->pdo->prepare($sql);
            return new NeonDBStmt($stmt);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }
    
    public function query($sql) {
        try {
            $sql = str_replace('`', '', $sql);
            $stmt = $this->pdo->query($sql);
            return new NeonDBResult($stmt);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }
    
    public function real_escape_string($str) {
        return addslashes($str);
    }

    public function __get($name) {
        if ($name === 'insert_id') {
            return $this->pdo->lastInsertId();
        }
        return null;
    }

    public static function connect_error() {
        return "Neon PostgreSQL connection error.";
    }
}

class NeonDBStmt {
    private $stmt;
    private $params = [];
    private $result = null;
    public $num_rows = 0;
    
    public function __construct($stmt) {
        $this->stmt = $stmt;
    }
    
    public function bind_param($types, &...$params) {
        $this->params = [];
        for ($i = 0; $i < count($params); $i++) {
            $this->params[$i] = &$params[$i];
        }
        return true;
    }
    
    public function execute() {
        try {
            for ($i = 0; $i < count($this->params); $i++) {
                $this->stmt->bindParam($i + 1, $this->params[$i]);
            }
            $success = $this->stmt->execute();
            // Pre-create the result object
            $this->result = new NeonDBResult($this->stmt);
            $this->num_rows = $this->result->num_rows;
            return $success;
        } catch (PDOException $e) {
            error_log("Postgres Execute failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function get_result() {
        return $this->result;
    }
    
    public function store_result() {
        return true;
    }
    
    public function close() {
        $this->stmt = null;
        return true;
    }
    
    public function __get($name) {
        if ($name === 'num_rows') {
            return $this->num_rows;
        }
        return null;
    }
}

class NeonDBResult {
    private $stmt;
    private $rows = [];
    private $index = 0;
    public $num_rows = 0;
    
    public function __construct($stmt) {
        $this->stmt = $stmt;
        if ($stmt) {
            try {
                if ($stmt->columnCount() > 0) {
                    $this->rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $this->num_rows = count($this->rows);
                } else {
                    $this->num_rows = $stmt->rowCount();
                }
            } catch (PDOException $e) {
                // If it's an UPDATE or INSERT statement that doesn't return rows
                $this->num_rows = $stmt->rowCount();
            }
        }
    }
    
    public function fetch_assoc() {
        if ($this->index < count($this->rows)) {
            return $this->rows[$this->index++];
        }
        return null;
    }
    
    public function data_seek($offset) {
        $this->index = intval($offset);
        return true;
    }

    public function __get($name) {
        if ($name === 'num_rows') {
            return $this->num_rows;
        }
        return null;
    }
}
