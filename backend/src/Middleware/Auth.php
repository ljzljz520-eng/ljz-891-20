<?php
namespace Middleware;

use Config\Settings;

/**
 * 管理员鉴权中间件
 * 登录成功后签发 HMAC 签名令牌，受保护接口通过 Authorization: Bearer <token> 校验。
 */
class Auth {

    /**
     * 签发签名令牌
     */
    public static function issueToken($username) {
        $config = Settings::load();
        $secret = $config['app']['secret'];
        $ttl    = (int)$config['app']['token_ttl'];

        $payload = base64_encode(json_encode([
            'username' => $username,
            'exp'      => time() + $ttl,
        ]));
        $signature = hash_hmac('sha256', $payload, $secret);

        return $payload . '.' . $signature;
    }

    /**
     * 校验当前请求的令牌，返回管理员用户名或 false
     */
    public static function check() {
        $header = self::getAuthorizationHeader();
        if (!$header || !preg_match('/Bearer\s+(\S+)/', $header, $matches)) {
            return false;
        }

        $token = $matches[1];
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }

        list($payload, $signature) = $parts;
        $config = Settings::load();
        $expected = hash_hmac('sha256', $payload, $config['app']['secret']);

        if (!hash_equals($expected, $signature)) {
            return false;
        }

        $data = json_decode(base64_decode($payload), true);
        if (!is_array($data) || empty($data['username']) || empty($data['exp'])) {
            return false;
        }

        if ($data['exp'] < time()) {
            return false; // 令牌过期
        }

        return $data['username'];
    }

    /**
     * 要求管理员身份，否则返回 401 并终止请求
     */
    public static function requireAdmin() {
        $user = self::check();
        if ($user === false) {
            http_response_code(401);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(["message" => "未授权访问，请先登录"]);
            exit;
        }
        return $user;
    }

    private static function getAuthorizationHeader() {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                return $headers['Authorization'];
            }
        }
        return null;
    }
}
