<?php
namespace Config;

use PDO;
use PDOException;
use Config\Settings;

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;

    public function __construct() {
        $config = Settings::load();
        $this->host     = $config['db']['host'];
        $this->port     = $config['db']['port'];
        $this->db_name  = $config['db']['name'];
        $this->username = $config['db']['user'];
        $this->password = $config['db']['pass'];
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8mb4");
        } catch(PDOException $exception) {
            // 安全：不向前端泄露数据库连接细节，仅记录到服务端日志
            error_log("Database connection error: " . $exception->getMessage());
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=UTF-8');
            }
            echo json_encode(["message" => "服务暂时不可用，请稍后重试"]);
            exit;
        }
        return $this->conn;
    }
}
