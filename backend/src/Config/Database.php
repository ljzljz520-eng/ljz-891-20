<?php
namespace Config;

use PDO;
use PDOException;

class Database {
    private $config;
    public $conn;

    public function __construct() {
        // 数据库连接信息统一来自配置文件（敏感项由环境变量注入）
        $this->config = require __DIR__ . '/config.php';
        $this->config = $this->config['db'];
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                $this->config['host'],
                $this->config['port'],
                $this->config['name'],
                $this->config['charset']
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->conn = new PDO($dsn, $this->config['user'], $this->config['pass'], $options);
            $this->conn->exec("set names " . $this->config['charset']);
        } catch(PDOException $exception) {
            // 生产环境不应把数据库原始报错回显给客户端
            error_log("Database connection error: " . $exception->getMessage());
            http_response_code(500);
            echo json_encode(["message" => "Database connection error"]);
        }
        return $this->conn;
    }
}
