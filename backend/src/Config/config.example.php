<?php
/**
 * 运行时配置文件模板
 *
 * 使用方法：
 *   1. 复制本文件为 config.php（与本文件同目录）
 *   2. 根据实际环境修改下方数据库与邮件参数
 *
 * 安全说明：
 *   - config.php 已被 .gitignore 忽略，不会被提交到代码仓库
 *   - 敏感信息（数据库密码、SMTP 授权码）只应存在于 config.php 或环境变量中
 *   - 也可通过环境变量覆盖，优先级：环境变量 > config.php > 默认值
 */

return [
    // 应用密钥（用于签名登录令牌，请务必修改为随机字符串）
    'app' => [
        'secret'    => getenv('APP_SECRET') ?: 'change_me_to_a_random_64_char_string',
        'token_ttl' => getenv('APP_TOKEN_TTL') ?: 3600, // 令牌有效期（秒）
    ],

    // 数据库连接配置
    'db' => [
        'host' => getenv('DB_HOST') ?: 'db',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'auth_system',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: 'root',
    ],

    // 邮件 SMTP 配置（用于自助更绑时发送验证码）
    'mail' => [
        'host'       => getenv('MAIL_HOST') ?: 'smtp.163.com',
        'port'       => getenv('MAIL_PORT') ?: 465,
        'username'   => getenv('MAIL_USERNAME') ?: 'your_account@163.com',
        'password'   => getenv('MAIL_PASSWORD') ?: 'your_smtp_auth_code', // SMTP 授权码，非登录密码
        'encryption' => getenv('MAIL_ENCRYPTION') ?: 'ssl', // ssl | tls
        'from_name'  => getenv('MAIL_FROM_NAME') ?: '星罗授权系统',
    ],
];
