# 星罗授权管理系统 (AuthQuery System)

正版授权查询 + 邮箱验证码自助更绑 + 后台管理的一体化系统。

## 🛠 技术栈

| 层 | 技术 |
|---|---|
| 前端 | React 18 + Vite 5 + Tailwind CSS（玻璃拟态 / 科技蓝） |
| 后端 | **PHP 8.2+** + Apache（PDO 预处理 + PHPMailer 6.9，Composer 自动加载） |
| 数据库 | **MySQL 8.0**（utf8mb4） |
| 部署 | Docker / Docker Compose |

## 🔗 默认服务地址

| 服务 | 地址 | 说明 |
|---|---|---|
| 前端页面 | http://localhost:3891 | nginx 容器，`/api` 反代到后端 |
| 后端 API | http://localhost:8891 | Apache + PHP |
| MySQL | localhost:18891 | 端口、口令均可在 `.env` 中修改 |

---

## 🔐 安全说明

系统在设计上遵循以下安全约定，**部署时请不要绕过**：

1. **管理员密码只保存哈希**
   - 数据库 `admins.password` 列保存的是 PHP `password_hash($pwd, PASSWORD_BCRYPT)` 生成的 `$2y$` 哈希（60 字符），任何位置都不存明文密码。
   - 登录使用 `password_verify()` 校验；后台新建管理员同样以 BCRYPT 入库。
   - 修改密码请生成新哈希后执行 `UPDATE`，**切勿直接写明文**：
     ```bash
     php -r "echo password_hash('你的新密码', PASSWORD_BCRYPT), PHP_EOL;"
     ```

2. **数据库连接信息放在配置文件，敏感项走环境变量**
   - 连接参数集中在 [`backend/src/Config/config.php`](backend/src/Config/config.php)，主机、库名有默认值；
   - **数据库密码、SMTP 授权码**通过环境变量注入：Docker 部署读根目录 `.env`（由 `.env.example` 复制），不在代码和 Git 历史中出现明文密钥。
   - `.env` 已被 `.gitignore` 忽略；`Config/` 目录通过 `.htaccess` 规则禁止浏览器直接访问。

3. **SQL 注入防护**：所有数据库操作均使用 PDO 预处理语句（参数绑定），关闭模拟预处理（`ATTR_EMULATE_PREPARES = false`）。

4. **邮件验证码**
   - 使用密码学安全的 `random_int()` 生成 6 位验证码，有效期 10 分钟，校验成功后立即删除（一次性，防重放）；
   - SMTP 授权码是邮箱后台生成的**专用授权码，不是邮箱登录密码**；
   - 生产模式默认校验 SMTP 服务器 SSL 证书（`MAIL_VERIFY_SSL=true`）。

5. **生产环境必须关闭调试模式**：设置 `APP_DEBUG=false` 后，SMTP 失败不再把验证码（`mock_code`）和错误细节返回给前端，数据库异常也只返回通用错误提示。

6. **CORS**：开发环境为 `*`，生产环境请把 `CORS_ALLOW_ORIGIN` 设为前端实际域名。

> ⚠️ 已知限制（演示项目遗留，上生产前建议补齐）：登录 token 目前是 `base64(username:time)` 的简易令牌，管理类接口（列表/增删）未做 token 校验与角色权限控制；验证码接口无限频。生产部署请替换为签名 JWT 并在管理接口增加鉴权中间件 + 发送频率限制。

---

## 🚀 部署方式一：Docker Compose（推荐）

### 环境要求

- Docker Engine 20.10+ 与 Docker Compose v2（`docker compose version` 可查）
- 本机端口 3891 / 8891 / 18891 未被占用（或在 `.env` 中改端口）

### 安装步骤

1. **准备配置文件**

   ```bash
   cp .env.example .env
   ```

   编辑 `.env`，至少确认/修改：
   - `MYSQL_ROOT_PASSWORD`、`DB_PASS`：数据库口令（两者保持一致）；
   - `APP_DEBUG`：本机体验可留 `true`，**正式上线改 `false`**；
   - `MAIL_USERNAME` / `MAIL_PASSWORD`：发信邮箱与 SMTP 授权码（不配置则开发模式下走模拟验证码）。

2. **启动服务**（首次会构建镜像，并自动完成建库建表与初始数据导入）

   ```bash
   docker compose up -d --build
   ```

   或使用一键脚本（会清掉旧容器并无缓存重建）：

   ```bash
   ./run.sh
   ```

3. **等待数据库就绪**：compose 已配置健康检查，backend 会在 MySQL `healthy` 后启动。查看状态：

   ```bash
   docker compose ps
   docker compose logs -f db backend
   ```

4. 访问 http://localhost:3891 。

> 数据表由 [`db/init.sql`](db/init.sql) 在 MySQL 数据卷**首次初始化**时自动导入。若修改过 `init.sql` 想重新导入，需删除旧卷：`docker compose down -v` 后重新 `up`（会清空数据）。

---

## 🧑‍💻 部署方式二：手动安装（无 Docker）

### 1. 环境准备

- **PHP ≥ 8.2**（8.1 及以下不支持），并启用扩展：`pdo_mysql`、`mbstring`、`openssl`、`curl`
  ```bash
  php -v                      # 确认版本 >= 8.2
  php -m | grep -E 'pdo_mysql|mbstring|openssl'
  ```
- MySQL 8.0（或兼容的 MariaDB 10.6+）
- Composer 2.x；Apache（需开启 `rewrite` 模块）或 Nginx + PHP-FPM

### 2. 创建数据表与初始管理员

```bash
# 创建库、表，并写入初始管理员（密码为 BCRYPT 哈希）与演示数据
mysql -h 127.0.0.1 -u root -p < db/init.sql
```

`init.sql` 完成三件事：

- 创建 `auth_system` 库及 `admins` / `licenses` / `verification_codes` 三张表；
- 写入初始管理员：用户名 `admin`，对应哈希由 `password_hash('123456')` 生成，即**初始密码 `123456`**；
- 写入 3 条演示授权数据（含 1 条已过期数据）。

如需自定义初始密码，先在 PHP 环境生成哈希，替换 SQL 中的哈希串后再导入：

```bash
php -r "echo password_hash('你的强密码', PASSWORD_BCRYPT), PHP_EOL;"
# 把输出的 $2y$... 填入 init.sql 的 INSERT IGNORE INTO admins ...
```

系统已运行后也可以直接改：

```sql
UPDATE admins SET password='<新生成的哈希>' WHERE username='admin';
```

### 3. 安装后端依赖并配置连接信息

```bash
cd backend/src
composer install --no-dev --optimize-autoloader
```

配置二选一：

- **方式 A（推荐）**：用环境变量注入（`export DB_HOST=... DB_PASS=... MAIL_USERNAME=... ...`，可用 php-fpm 的 `env[...]` 或 systemd EnvironmentFile）；
- **方式 B**：直接编辑 `Config/config.php` 中的默认值（注意该文件不要提交到公开仓库）。

完整参数见下文 [配置项说明](#-配置项说明)。

### 4. 配置 Web 服务器

- Apache：`backend/src/.htaccess` 已提供路由重写与配置目录保护，需确保站点 `AllowOverride All` 且已 `a2enmod rewrite`；文档根目录指向 `backend/src`。
- Nginx 参考：

  ```nginx
  root /var/www/auth-system/backend/src;
  location / { try_files $uri /index.php?$query_string; }
  location ~ \.php$ {
      fastcgi_pass unix:/run/php/php8.2-fpm.sock;
      include fastcgi_params;
      fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
  }
  location ^~ /Config/ { deny all; }
  ```

### 5. 构建前端（可选，也可直接跑 Docker 的前端容器）

```bash
cd frontend
npm install
npm run build      # 产物在 dist/，交给 nginx 托管
```

---

## ⚙️ 配置项说明

根目录 `.env`（或对应环境变量）：

| 变量 | 默认值 | 说明 |
|---|---|---|
| `MYSQL_ROOT_PASSWORD` | `root` | MySQL root 口令，**首次初始化生效** |
| `MYSQL_DATABASE` | `auth_system` | 自动创建的数据库名 |
| `MYSQL_USER` / `MYSQL_PASSWORD` | 空 | 可填写以创建专用数据库账号；留空则用 root |
| `DB_HOST` / `DB_PORT` | `db` / `3306` | 后端连接数据库的地址（容器内服务名为 `db`） |
| `DB_NAME` / `DB_USER` / `DB_PASS` | `auth_system` / `root` / `root` | 后端实际使用的库连接信息 |
| `FRONTEND_PORT` / `BACKEND_PORT` / `DB_PORT_HOST` | 3891 / 8891 / 18891 | 宿主机映射端口 |
| `APP_DEBUG` | `true` | 调试开关，**生产必须 false** |
| `CORS_ALLOW_ORIGIN` | `*` | 允许的跨域来源 |
| `MAIL_HOST` / `MAIL_PORT` / `MAIL_ENCRYPTION` | `smtp.163.com` / `465` / `ssl` | SMTP 服务器；QQ 邮箱用 `smtp.qq.com` |
| `MAIL_USERNAME` | 空 | 发信邮箱地址 |
| `MAIL_PASSWORD` | 空 | SMTP **授权码**（非登录密码） |
| `MAIL_FROM` | 同账号 | 发件人地址 |
| `MAIL_VERIFY_SSL` | `true` | 是否校验 SMTP 证书 |

---

## 🧪 本地验证指南

以下流程均可在 Docker 启动完成后直接验证。API 直连用 `http://localhost:8891`；页面操作访问 `http://localhost:3891`。

### 1. 服务健康检查

```bash
curl -i http://localhost:8891/api/license/query?qq=123456789&owner=张三
```

能返回 JSON 即说明 PHP 与 MySQL 连接正常。

### 2. 正版查询流程

**页面方式**：打开首页「授权查询」，输入 QQ 与主人名称双重验证。

- 命中数据（种子数据）：

  ```bash
  curl "http://localhost:8891/api/license/query?qq=123456789&owner=张三"
  # 200 + data：qq / owner / product / upline / expiration / created_at
  ```

- 未命中（任意不存在的组合）：

  ```bash
  curl -i "http://localhost:8891/api/license/query?qq=999&owner=不存在"
  # 404，返回 message 与 reasons 提示
  ```

- 缺参数：

  ```bash
  curl -i "http://localhost:8891/api/license/query?qq=123456789"
  # 400 Missing parameters
  ```

页面会分别渲染「查询成功」卡片和「查询失败 + 原因列表」。

### 3. 管理员登录流程

**页面方式**：访问 http://localhost:3891/admin ，输入初始账号 `admin` / `123456`，成功后进入管理面板（授权 CRUD、管理员管理）。

命令行验证：

```bash
# 正确凭据 → 200，返回 token
curl -i -X POST http://localhost:8891/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"123456"}'

# 错误密码 → 401 Login failed
curl -i -X POST http://localhost:8891/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"wrong"}'
```

验证数据库里保存的是哈希而非明文：

```bash
docker compose exec db mysql -uroot -p"$MYSQL_ROOT_PASSWORD" auth_system \
  -e "SELECT username, LEFT(password,4) AS hash_prefix, LENGTH(password) AS len FROM admins;"
# hash_prefix 应为 $2y$，len 为 60
```

> 首次登录后请立即在后台或用 SQL 修改默认密码。

### 4. 邮箱验证码 / 自助更绑流程

页面「自助更绑」分两步：输入授权 QQ → 点「发送验证码」→ 查收 QQ 邮箱 → 填验证码和新主人名称提交。

**方式 A：已配置真实 SMTP（`.env` 填好 `MAIL_USERNAME`/`MAIL_PASSWORD`）**

```bash
curl -X POST http://localhost:8891/api/license/send-code \
  -H 'Content-Type: application/json' -d '{"qq":"123456789"}'
# 200 {"message":"验证码已发送至QQ邮箱"}，登录 123456789@qq.com 收信
```

**方式 B：未配置 SMTP 或发送失败（`APP_DEBUG=true`）**

接口返回模拟验证码，页面也会弹出「测试环境验证码」提示：

```bash
curl -X POST http://localhost:8891/api/license/send-code \
  -H 'Content-Type: application/json' -d '{"qq":"123456789"}'
# {"message":"...(转为模拟模式)", "mock_code":"482913", ...}
```

也可直接从数据库取最新验证码：

```bash
docker compose exec db mysql -uroot -proot auth_system \
  -e "SELECT identifier, code, expires_at FROM verification_codes ORDER BY id DESC LIMIT 1;"
```

提交更绑（用上一步拿到的 6 位码）：

```bash
curl -i -X POST http://localhost:8891/api/license/update \
  -H 'Content-Type: application/json' \
  -d '{"qq":"123456789","code":"482913","owner_name":"新主人"}'
# 200 Update successful；重复使用同一验证码会返回 400（一次性失效）
```

再用新主人名称走一次查询接口，确认信息已更新：

```bash
curl "http://localhost:8891/api/license/query?qq=123456789&owner=新主人"
```

### 5. 前端本地开发（热更新）

只起数据库和后端，前端用 Vite 本地跑（已配置 `/api` 代理到 `http://localhost:8891`）：

```bash
docker compose up -d db backend
cd frontend
npm install
VITE_API_TARGET=http://localhost:8891 npm run dev
# 打开 http://localhost:3000
```

---

## 📋 上线检查清单

- [ ] `.env` 中 `APP_DEBUG=false`，`CORS_ALLOW_ORIGIN` 改为正式域名
- [ ] 数据库改掉 `root/root` 弱口令，优先使用 `MYSQL_USER` 专用账号并最小授权
- [ ] 登录后立即修改初始管理员 `admin/123456`，密码哈希强度符合要求
- [ ] 正确配置 SMTP 授权码，`MAIL_VERIFY_SSL=true`
- [ ] 确认 18891（MySQL）等端口不对公网开放，只通过前端/后端容器互通
- [ ] 补齐管理接口的 token 鉴权与验证码接口限频（见「已知限制」）
- [ ] 定期清理过期验证码（可用定时任务 `DELETE FROM verification_codes WHERE expires_at < NOW()`）

## 🧯 常见问题

- **后端日志报 Database connection error**：MySQL 首次启动初始化较慢，compose 健康检查通常会自动等待；手动部署时确认 `DB_HOST/DB_PORT/DB_PASS` 与库内账号一致。
- **邮件发送失败**：`docker compose logs backend` 查看 `SMTP Error`；163/QQ 邮箱需在账号设置中开启 SMTP 并使用**授权码**而非登录密码；确认容器能访问外网 465 端口。
- **改了 `init.sql` 没生效**：该文件只在数据卷首次创建时执行，需 `docker compose down -v` 重建（数据会清空）。
- **`Config/config.php` 能否被下载**：`.htaccess` 已对 `Config/` 目录返回 403；Nginx 部署请自行加上等价的 `deny all` 规则。
