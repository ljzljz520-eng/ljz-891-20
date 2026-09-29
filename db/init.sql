SET NAMES utf8mb4;
SET TIME_ZONE = '+08:00';

CREATE DATABASE IF NOT EXISTS auth_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE auth_system;

-- ============================================================
-- 管理员表：password 列只保存 PHP password_hash() 生成的 BCRYPT 哈希
-- （$2y$ 开头，长度 60），任何场景都不要写入明文密码
-- ============================================================
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 授权信息表
CREATE TABLE IF NOT EXISTS licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    qq VARCHAR(20) NOT NULL,
    owner_name VARCHAR(50) NOT NULL,
    product_name VARCHAR(100) NOT NULL,
    upline VARCHAR(50) NOT NULL COMMENT '上级代理',
    expiration_date DATETIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_qq (qq)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 邮箱验证码表（自助更绑流程）
CREATE TABLE IF NOT EXISTS verification_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(20) NOT NULL COMMENT '业务类型，如 update_license',
    identifier VARCHAR(100) NOT NULL COMMENT '收件邮箱（QQ号@qq.com）',
    code VARCHAR(10) NOT NULL COMMENT 'BCRYPT 之外的临时验证码，10分钟有效',
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier_code (identifier, code),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 初始管理员
-- 默认账号 admin / 密码 123456，下方为 password_hash('123456', PASSWORD_BCRYPT) 的哈希
-- 生产环境请自行生成并替换（在安装了 PHP 的机器上执行）：
--   php -r "echo password_hash('你的新密码', PASSWORD_BCRYPT), PHP_EOL;"
-- 或容器启动后用 SQL 修改：
--   UPDATE admins SET password='<新哈希>' WHERE username='admin';
-- INSERT IGNORE 保证重复导入时不会因 username 唯一键报错
-- ============================================================
INSERT IGNORE INTO admins (username, password) VALUES
('admin', '$2y$10$eLYd0HGc9JM0qxzPkpLtDuL1UZRAS6XAwgVNO7oL9R0M/f/6bkEcW');

-- 演示用授权数据（仅在数据卷首次初始化时写入；重复导入前请先清理）
INSERT INTO licenses (qq, owner_name, product_name, upline, expiration_date) VALUES
('123456789', '张三', '超级授权系统VIP版', '总代理', '2026-12-31 23:59:59'),
('987654321', '李四', '企业级管理后台', '核心代理', '2025-06-30 23:59:59'),
('11111', '王五', '测试过期产品', '测试员', '2023-01-01 00:00:00');
