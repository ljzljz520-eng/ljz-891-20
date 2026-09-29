<?php
/**
 * 全局配置文件
 *
 * 敏感信息（数据库密码、SMTP 授权码）统一从环境变量读取，
 * 不在代码中保存明文密钥：
 *   - Docker 部署：根目录 .env（参考 .env.example）经 docker-compose 注入
 *   - 本地部署：export 环境变量，或直接修改本文件中的默认值
 *
 * 本文件通过 .htaccess 规则禁止被浏览器直接访问。
 */

return [
    // 应用
    'app' => [
        // true = 开发/测试模式：邮件发送失败时返回模拟验证码，并输出 SMTP 调试日志
        // 生产环境必须为 false
        'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL),
        // 允许跨域访问的来源，生产环境建议设置为前端域名，如 https://auth.example.com
        'cors_origin' => getenv('CORS_ALLOW_ORIGIN') ?: '*',
    ],

    // 数据库连接
    'db' => [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'port'    => getenv('DB_PORT') ?: '3306',
        'name'    => getenv('DB_NAME') ?: 'auth_system',
        'user'    => getenv('DB_USER') ?: 'root',
        'pass'    => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],

    // SMTP 邮件（验证码）
    'mail' => [
        'host'       => getenv('MAIL_HOST') ?: 'smtp.163.com',
        'port'       => (int) (getenv('MAIL_PORT') ?: 465),
        // 发信邮箱账号
        'user'       => getenv('MAIL_USERNAME') ?: '',
        // 邮箱 SMTP 授权码（注意：不是邮箱登录密码）
        'pass'       => getenv('MAIL_PASSWORD') ?: '',
        // 发件人地址，缺省时与登录账号一致
        'from'       => getenv('MAIL_FROM') ?: (getenv('MAIL_USERNAME') ?: ''),
        // 加密方式：ssl（端口 465）或 tls（端口 587）
        'encryption' => getenv('MAIL_ENCRYPTION') ?: 'ssl',
        // 是否校验 SMTP 服务器证书；仅在内网自建邮件服务器自签证书时可设为 false
        'verify_ssl' => filter_var(getenv('MAIL_VERIFY_SSL') ?: 'true', FILTER_VALIDATE_BOOL),
    ],
];
