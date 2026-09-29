<?php
namespace Config;

/**
 * 配置加载器
 * 优先读取同目录下的 config.php（已被 .gitignore 忽略），
 * 缺失时回退到环境变量与默认值。
 */
class Settings {

    public static function load() {
        $configFile = __DIR__ . '/config.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            if (is_array($config)) {
                return self::mergeWithDefaults($config);
            }
        }
        return self::defaults();
    }

    private static function defaults() {
        return [
            'app' => [
                'secret'    => getenv('APP_SECRET') ?: 'change_me_to_a_random_64_char_string',
                'token_ttl' => getenv('APP_TOKEN_TTL') ?: 3600,
            ],
            'db' => [
                'host' => getenv('DB_HOST') ?: 'db',
                'port' => getenv('DB_PORT') ?: '3306',
                'name' => getenv('DB_NAME') ?: 'auth_system',
                'user' => getenv('DB_USER') ?: 'root',
                'pass' => getenv('DB_PASS') ?: 'root',
            ],
            'mail' => [
                'host'       => getenv('MAIL_HOST') ?: 'smtp.163.com',
                'port'       => getenv('MAIL_PORT') ?: 465,
                'username'   => getenv('MAIL_USERNAME') ?: '',
                'password'   => getenv('MAIL_PASSWORD') ?: '',
                'encryption' => getenv('MAIL_ENCRYPTION') ?: 'ssl',
                'from_name'  => getenv('MAIL_FROM_NAME') ?: '星罗授权系统',
            ],
        ];
    }

    private static function mergeWithDefaults(array $config) {
        $defaults = self::defaults();
        if (isset($config['app']) && is_array($config['app'])) {
            $defaults['app'] = array_merge($defaults['app'], $config['app']);
        }
        if (isset($config['db']) && is_array($config['db'])) {
            $defaults['db'] = array_merge($defaults['db'], $config['db']);
        }
        if (isset($config['mail']) && is_array($config['mail'])) {
            $defaults['mail'] = array_merge($defaults['mail'], $config['mail']);
        }
        return $defaults;
    }
}
