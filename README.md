# 星罗授权管理系统 (AuthQuery System)

一个集正版授权查询、自助更绑、后台管理于一体的轻量授权管理系统。

---

## 🛠 技术栈

| 层 | 技术 |
|---|---|
| 前端 | React + Vite + Tailwind CSS（Glassmorphism / Tech Blue） |
| 后端 | PHP 8.2 + Apache（MVC 架构 + PHPMailer） |
| 数据库 | MySQL 8.0 |
| 部署 | Docker + Docker Compose + Nginx |

---

## 🔐 安全设计说明

### 1. 管理员密码哈希存储
- 数据库 `admins` 表的 `password` 字段**只存储密码哈希，不存储明文**。
- 使用 PHP 内置 `password_hash()` / `password_verify()` 算法（**BCRYPT，cost = 10**），自带随机盐，可抵御彩虹表攻击。
- 新增/修改管理员时，后端自动对明文密码做哈希后入库。

### 2. 数据库连接信息走配置文件
- 数据库主机、端口、库名、账号、密码**不再硬编码**在代码中，统一由 `backend/src/Config/config.php` 读取。
- 该文件已被 `.gitignore` 忽略，**不会被提交到代码仓库**。
- 仓库中仅提供模板 `backend/src/Config/config.example.php`。
- 支持通过环境变量覆盖（优先级：环境变量 > config.php > 默认值）。

### 3. 邮件 SMTP 凭据保护
- 邮箱验证码发送所需的 SMTP 服务器、账号、授权码等参数同样存放在 `config.php` 中，代码中不出现任何明文授权码。

### 4. 管理员接口鉴权
- 所有后台管理接口（授权增删查、管理员增删查）均需在请求头携带 `Authorization: Bearer <token>`。
- 登录成功后签发 **HMAC-SHA25 签名令牌**（含用户名与过期时间），密钥来自配置项 `app.secret`。
- 令牌有效期由 `app.token_ttl` 控制（默认 3600 秒），过期需重新登录。
- 前端在令牌失效时会自动退出登录并提示重新登录。

### 5. 其他安全建议（生产环境务必处理）
- ⚠️ **CORS**：当前后端 `Access-Control-Allow-Origin: *` 仅适用于本地开发，生产环境请改为指定域名。
- ⚠️ **默认密码**：部署后请立即登录后台修改默认管理员密码（admin / 123456）。
- ⚠️ **APP 密钥**：务必将 `app.secret` 修改为随机字符串，否则令牌可被伪造。
- ⚠️ **数据库端口**：生产环境请勿将 MySQL 端口（18891）暴露到公网。
- ⚠️ **HTTPS**：生产环境请在 Nginx 层配置 HTTPS，避免令牌与密码明文传输。
- 建议定期备份数据库 `db_data` 卷。

---

## 🚀 部署安装步骤

### 环境要求

- **PHP**：>= 8.2（需开启 `pdo_mysql`、`mysqli` 扩展）
- **MySQL**：>= 8.0（或兼容的 MariaDB 10.5+）
- **Composer**：>= 2.x（用于安装 PHPMailer）
- **Node.js**：>= 18（仅构建前端时需要）
- 或直接使用 **Docker**（推荐，无需手动配置上述环境）

### 方式一：Docker 部署（推荐）

1. 确保已安装并启动 Docker Desktop / Docker Engine。
2. 克隆代码后，复制配置文件并按需修改：
   ```bash
   cp backend/src/Config/config.example.php backend/src/Config/config.php
   ```
   编辑 `config.php`，重点修改：
   - `app.secret`：随机字符串（用于签名令牌）
   - `db.*`：数据库连接信息（与 `docker-compose.yml` 中 MySQL 配置对应）
   - `mail.*`：SMTP 邮件参数（用于发送验证码）
3. 在项目根目录执行：
   ```bash
   docker compose up --build
   ```
   数据库首次启动时会自动执行 `db/init.sql`，完成建表与初始管理员写入。
4. 访问：
   - 前端页面：http://localhost:3891
   - API 接口：http://localhost:8891
   - MySQL：localhost:18891（root / root）

> 重新构建（无缓存）可执行：`bash run.sh`

### 方式二：手动部署（非 Docker）

#### 1. 创建数据表

登录 MySQL，执行建表脚本：

```bash
mysql -u root -p < db/init.sql
```

该脚本会：
- 创建数据库 `auth_system`（utf8mb4）
- 创建 `admins`（管理员）、`licenses`（授权）、`verification_codes`（验证码）三张表
- 写入初始管理员与示例授权数据

#### 2. 配置数据库连接

```bash
cp backend/src/Config/config.example.php backend/src/Config/config.php
```

编辑 `backend/src/Config/config.php`，将 `db` 段改为实际数据库地址：

```php
'db' => [
    'host' => '127.0.0.1',   // 数据库主机
    'port' => '3306',
    'name' => 'auth_system',
    'user' => 'root',
    'pass' => '你的数据库密码',
],
```

#### 3. 安装 PHP 依赖

```bash
cd backend/src
composer install --no-dev --optimize-autoloader
```

确保 PHP 已安装 `pdo_mysql` 与 `mysqli` 扩展：

```bash
php -m | grep -E 'pdo_mysql|mysqli'
```

#### 4. 配置 Web 服务器

将站点根目录指向 `backend/src`，并确保开启 `mod_rewrite`（Apache）或配置伪静态（Nginx）。
Apache 已随附 `.htaccess`，Nginx 可参考以下规则：

```nginx
location / {
    root /path/to/backend/src;
    index index.php;
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ \.php$ {
    fastcgi_pass unix:/path/to/php-fpm.sock;
    fastcgi_index index.php;
    include fastcgi.conf;
}
```

#### 5. 初始管理员

系统已内置一个初始管理员（见 `db/init.sql`）：

| 账号 | 密码 | 说明 |
|---|---|---|
| `admin` | `123456` | 超级管理员，部署后请立即修改密码 |

**修改密码**：登录后台 → 「管理员管理」→ 对应管理员修改；或生成新哈希后更新数据库：

```bash
php -r "echo password_hash('你的新密码', PASSWORD_BCRYPT, ['cost' => 10]);"
```

**新增管理员**：登录后台 → 「管理员管理」→ 填写用户名/密码 → 「添加管理员」。

#### 6. 配置邮件参数（验证码）

自助更绑功能依赖邮箱发送验证码。编辑 `config.php` 的 `mail` 段：

```php
'mail' => [
    'host'       => 'smtp.163.com',        // SMTP 服务器
    'port'       => 465,                   // 端口（SSL 通常 465，TLS 通常 587）
    'username'   => 'your@163.com',        // 发件邮箱账号
    'password'   => 'your_smtp_auth_code', // SMTP 授权码（非邮箱登录密码）
    'encryption' => 'ssl',                 // ssl 或 tls
    'from_name'  => '星罗授权系统',
],
```

> 以 163 邮箱为例：需在邮箱设置中开启「SMTP 服务」并获取**客户端授权码**填入 `password`。
> 若 SMTP 发送失败，系统会在响应中返回 `mock_code`（模拟验证码），便于本地开发调试，同时在服务端日志记录 SMTP 错误。

---

## 🧪 本地验证指南

服务启动后（Docker 方式），可通过以下方式验证三大核心流程。

### 验证一：授权查询流程

**方式 A：页面操作**
1. 打开 http://localhost:3891 ，默认进入「授权查询」页。
2. 输入示例数据：
   - 授权QQ：`123456789`
   - 授权主人：`张三`
3. 点击「立即查询」，应显示查询成功与授权详情（产品、上级、有效期等）。
4. 输入不存在的 QQ/主人（如 `999999999` / `测试`），应显示「查询失败」及原因列表。

**方式 B：接口验证（curl）**
```bash
# 查询成功
curl "http://localhost:8891/api/license/query?qq=123456789&owner=张三"

# 查询失败
curl "http://localhost:8891/api/license/query?qq=999999999&owner=测试"
```

### 验证二：管理员登录流程

**方式 A：页面操作**
1. 打开 http://localhost:3891/admin 进入后台登录页。
2. 输入账号 `admin`、密码 `123456`，点击「登录」。
3. 登录成功后进入「授权管理中心」，可看到授权列表。
4. 错误密码应提示「登录失败: 用户名或密码错误」。

**方式 B：接口验证（curl）**
```bash
# 登录成功，返回签名令牌 token
curl -X POST http://localhost:8891/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"123456"}'

# 登录失败
curl -X POST http://localhost:8891/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"wrong"}'
```

**验证鉴权**：未携带令牌访问管理接口应返回 401：
```bash
# 未授权 → 401
curl -i http://localhost:8891/api/license/list

# 携带令牌（将 <token> 替换为登录返回的 token）→ 200
curl http://localhost:8891/api/license/list \
  -H "Authorization: Bearer <token>"
```

### 验证三：邮箱验证码流程（自助更绑）

**方式 A：页面操作**
1. 打开 http://localhost:3891/update 进入「自助更绑」页。
2. 输入授权 QQ（如 `123456789`），点击「发送验证码」。
3. 验证码会发送至 `123456789@qq.com`（即 QQ 邮箱）。
   - 若 SMTP 配置正确，查收邮箱中的 6 位验证码。
   - 若 SMTP 发送失败（本地调试），页面会通过 toast 显示「测试环境验证码」，也可在浏览器控制台或 `docker logs auth_backend` 中查看 `mock_code`。
4. 输入收到的验证码与新的授权主人名称，点击「确认更改」。
5. 验证成功后提示「更绑成功」；验证码错误或过期会提示「验证失败或验证码过期」。

**方式 B：接口验证（curl）**
```bash
# 发送验证码（SMTP 失败时返回 mock_code）
curl -X POST http://localhost:8891/api/license/send-code \
  -H "Content-Type: application/json" \
  -d '{"qq":"123456789"}'

# 校验验证码并更新（将 <code> 替换为收到的验证码）
curl -X POST http://localhost:8891/api/license/update \
  -H "Content-Type: application/json" \
  -d '{"qq":"123456789","code":"<code>","owner_name":"新主人"}'
```

> 验证码有效期为 **10 分钟**，过期需重新发送。

---

## 🔗 服务端口一览

| 服务 | 地址 | 说明 |
|---|---|---|
| 前端页面 | http://localhost:3891 | 用户查询/自助更绑/后台管理入口 |
| 后端 API | http://localhost:8891 | PHP 接口服务 |
| MySQL | localhost:18891 | 数据库（root / root） |

---

## 📝 核心功能

1. **正版查询**：动态极光背景，支持 QQ / 主人 双重验证。
2. **自助更绑**：集成 PHPMailer 发送真实 QQ 邮箱验证码（SMTP 参数在 `config.php` 中配置）。
   - 若发送失败，请在 `docker logs auth_backend` 查看 SMTP 错误日志。
3. **后台管理**：
   - 现代化表格设计（头像/状态徽章）。
   - 自定义玻璃拟态弹窗（Modal）代替原生 Alert。
   - 完备的 CRUD 功能，管理员接口需令牌鉴权。

---

## 📂 目录结构

```
.
├── backend/
│   ├── Dockerfile
│   └── src/
│       ├── Config/
│       │   ├── config.example.php   # 配置模板（提交到仓库）
│       │   ├── config.php           # 实际配置（gitignore，不提交）
│       │   ├── Database.php          # 数据库连接
│       │   └── Settings.php          # 配置加载器
│       ├── Controllers/
│       │   ├── AuthController.php    # 登录/管理员管理
│       │   └── LicenseController.php # 授权查询/CRUD/验证码
│       ├── Middleware/
│       │   └── Auth.php              # 令牌签发与校验
│       ├── composer.json
│       └── index.php                 # 入口与路由
├── frontend/
│   ├── Dockerfile
│   ├── nginx.conf
│   └── src/
│       ├── App.jsx
│       ├── pages/                   # QueryPage / UpdatePage / AdminPage
│       └── components/
├── db/
│   └── init.sql                     # 建表 + 初始数据
├── docker-compose.yml
└── README.md
```
