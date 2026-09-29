<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    // Fallback if composer not run (should not happen in Docker)
    include_once './Config/Settings.php';
    include_once './Config/Database.php';
    include_once './Middleware/Auth.php';
    include_once './Controllers/AuthController.php';
    include_once './Controllers/LicenseController.php';
}

use Config\Database;
use Controllers\AuthController;
use Controllers\LicenseController;
use Middleware\Auth;

$database = new Database();
$db = $database->getConnection();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uriParts = explode('/', $uri);

// Simple Router
// 公开接口（无需登录）:
//   POST /api/auth/login          管理员登录
//   GET  /api/license/query       授权查询
//   POST /api/license/send-code   发送邮箱验证码
//   POST /api/license/update      自助更绑（验证码校验）
//
// 管理员接口（需携带 Authorization: Bearer <token>）:
//   GET  /api/license/list        授权列表
//   POST /api/license/create      新增授权
//   POST /api/license/delete      删除授权
//   GET  /api/auth/list           管理员列表
//   POST /api/auth/create         新增管理员
//   POST /api/auth/delete         删除管理员

if ($uri === '/api/auth/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new AuthController($db);
    $auth->login();
}
elseif ($uri === '/api/license/query' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $license = new LicenseController($db);
    $license->query();
}
elseif ($uri === '/api/license/send-code' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $license = new LicenseController($db);
    $license->sendVerificationCode();
}
elseif ($uri === '/api/license/update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $license = new LicenseController($db);
    $license->update();
}
// ===== 以下为管理员接口，需鉴权 =====
elseif ($uri === '/api/license/create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $license = new LicenseController($db);
    $license->create();
}
elseif ($uri === '/api/license/list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    Auth::requireAdmin();
    $license = new LicenseController($db);
    $license->listAll();
}
elseif ($uri === '/api/license/delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $license = new LicenseController($db);
    $license->delete();
}
elseif ($uri === '/api/auth/list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    Auth::requireAdmin();
    $auth = new AuthController($db);
    $auth->list();
}
elseif ($uri === '/api/auth/create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $auth = new AuthController($db);
    $auth->create();
}
elseif ($uri === '/api/auth/delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $auth = new AuthController($db);
    $auth->delete();
}
else {
    http_response_code(404);
    echo json_encode(["message" => "Endpoint Not Found", "uri" => $uri]);
}
