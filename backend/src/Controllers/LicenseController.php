<?php
namespace Controllers;

use Config\Database;
use PDO;

class LicenseController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // Public Query
    public function query() {
        if (!isset($_GET['qq']) || !isset($_GET['owner'])) {
            http_response_code(400);
            echo json_encode(["message" => "Missing parameters"]);
            return;
        }

        $qq = $_GET['qq'];
        $owner = $_GET['owner'];

        $query = "SELECT * FROM licenses WHERE qq = :qq AND owner_name = :owner LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":qq", $qq);
        $stmt->bindParam(":owner", $owner);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            // Check if expired logic? The prompt says just show info.
            // But prompt also lists reasons for failure: "1.授权开通不足60分钟内" (implies < 60 mins from creation?) - this is weird, maybe it means 'just created'? or 'not synced'?
            // Usually "Authorization not found" reasons are generic boilerplate.
            // Let's just return the data.
            
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "data" => [
                    "qq" => $row['qq'],
                    "owner" => $row['owner_name'],
                    "product" => $row['product_name'],
                    "upline" => $row['upline'],
                    "expiration" => $row['expiration_date'],
                    "created_at" => $row['created_at']
                ]
            ]);
        } else {
            // Failure with specific message
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "暂未查询到您的授权信息 请查证后再次查询！",
                "reasons" => [
                    "1.授权开通不足60分钟内",
                    "2.未购买正版授权，可能是盗版程序授权",
                    "3.恭喜你，被圈钱了！"
                ]
            ]);
        }
    }

    // Admin: List All
    public function listAll() {
        $query = "SELECT * FROM licenses ORDER BY created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
    }

    // Admin: Create
    public function create() {
        $data = json_decode(file_get_contents("php://input"));
        // Need: qq, owner_name, product_name, upline, expiration_date
        $query = "INSERT INTO licenses (qq, owner_name, product_name, upline, expiration_date) VALUES (:qq, :owner, :product, :upline, :exp)";
        $stmt = $this->db->prepare($query);
        
        $params = [
            ":qq" => $data->qq,
            ":owner" => $data->owner_name,
            ":product" => $data->product_name,
            ":upline" => $data->upline,
            ":exp" => $data->expiration_date
        ];
        
        if($stmt->execute($params)) {
             echo json_encode(["message" => "Created successfully"]);
        } else {
             http_response_code(500);
             echo json_encode(["message" => "Create failed"]);
        }
    }
    
    // Admin: Delete
    public function delete() {
         $data = json_decode(file_get_contents("php://input"));
         if(!isset($data->id)) { return; }
         $query = "DELETE FROM licenses WHERE id = :id";
         $stmt = $this->db->prepare($query);
         $stmt->bindParam(":id", $data->id);
         $stmt->execute();
         echo json_encode(["message" => "Deleted"]);
    }

    // Update Flow: Step 1 - Send Code
    public function sendVerificationCode() {
        $data = json_decode(file_get_contents("php://input"));
        if (!isset($data->qq) || !preg_match('/^[1-9][0-9]{4,11}$/', $data->qq)) {
            http_response_code(400);
            echo json_encode(["message" => "QQ号码格式不正确"]);
            return;
        }
        $qq = $data->qq;
        $email = $qq . "@qq.com";

        $config = require __DIR__ . '/../Config/config.php';
        $mailCfg = $config['mail'];
        $debug = $config['app']['debug'];

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Save code
        $stmt = $this->db->prepare("INSERT INTO verification_codes (type, identifier, code, expires_at) VALUES ('update_license', :email, :code, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        $stmt->execute([':email' => $email, ':code' => $code]);

        // 未配置 SMTP 账号时：开发模式返回模拟验证码，生产模式直接报错
        if ($mailCfg['user'] === '' || $mailCfg['pass'] === '') {
            if ($debug) {
                echo json_encode(["message" => "未配置SMTP（模拟模式）", "mock_code" => $code]);
            } else {
                error_log("SMTP not configured");
                http_response_code(503);
                echo json_encode(["message" => "邮件服务未配置，请联系管理员"]);
            }
            return;
        }

        // Real Email Sending via PHPMailer
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            //Server settings
            $mail->SMTPDebug = $debug ? 2 : 0; // 调试输出仅在开发模式开启
            $mail->Debugoutput = 'error_log'; // Output to stderr
            $mail->isSMTP();
            $mail->Host       = $mailCfg['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $mailCfg['user'];
            $mail->Password   = $mailCfg['pass'];
            $mail->SMTPSecure = strtolower($mailCfg['encryption']) === 'tls'
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = $mailCfg['port'];
            $mail->CharSet    = 'UTF-8';

            // 生产环境默认校验证书；仅在配置允许时放宽（自签证书内网场景）
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer'       => $mailCfg['verify_ssl'],
                    'verify_peer_name'  => $mailCfg['verify_ssl'],
                    'allow_self_signed' => !$mailCfg['verify_ssl']
                )
            );

            $mail->setFrom($mailCfg['from'] ?: $mailCfg['user']);
            $mail->addAddress($email);

            // Set HELO to localhost to avoid Docker container ID rejection
            $mail->Hostname = 'localhost';

            //Content
            $mail->isHTML(true);
            $mail->Subject = '【授权系统】验证码';
            $mail->Body    = "您的验证码是 <b>$code</b>，请在10分钟内完成验证。<br>如非本人操作请忽略。";

            $mail->send();
            echo json_encode(["message" => "验证码已发送至QQ邮箱"]);
        } catch (\Exception $e) {
            // Fallback for demo/dev if SMTP fails
            error_log("SMTP Error: {$mail->ErrorInfo}");
            if ($debug) {
                echo json_encode([
                     "message" => "邮件发送失败 (转为模拟模式)",
                     "mock_code" => $code,
                     "debug_error" => $mail->ErrorInfo
                ]);
            } else {
                http_response_code(502);
                echo json_encode(["message" => "验证码发送失败，请稍后重试"]);
            }
        }
    }

    // Update Flow: Step 2 - Verify & Update
    public function update() {
        $data = json_decode(file_get_contents("php://input"));
        if (!isset($data->qq, $data->code, $data->owner_name) || trim($data->owner_name) === '') {
            http_response_code(400);
            echo json_encode(["message" => "参数不完整"]);
            return;
        }

        $email = $data->qq . "@qq.com";
        $code = $data->code;

        // Verify Code（只取最新一条有效记录）
        $stmt = $this->db->prepare("SELECT id FROM verification_codes WHERE identifier=:email AND code=:code AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->execute([':email' => $email, ':code' => $code]);

        if ($stmt->rowCount() == 0) {
            http_response_code(400);
            echo json_encode(["message" => "验证码无效或已过期"]);
            return;
        }

        // 验证码一次性使用，校验通过后立即删除该邮箱的全部记录
        $this->db->prepare("DELETE FROM verification_codes WHERE identifier = :email")
                 ->execute([':email' => $email]);

        // Update License
        $updateQ = "UPDATE licenses SET owner_name = :new_owner WHERE qq = :qq";
        $ustmt = $this->db->prepare($updateQ);
        $ustmt->execute([':new_owner' => $data->owner_name, ':qq' => $data->qq]); // assuming we update owner
        
        echo json_encode(["message" => "Update successful"]);
    }
}
