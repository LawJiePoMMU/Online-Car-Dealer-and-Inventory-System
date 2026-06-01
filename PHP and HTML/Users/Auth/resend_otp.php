<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. 连接数据库
require_once "../../Config/database.php";

// 2. 检查是否有邮箱记录，如果没有，踢回忘记密码页面
if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot-password.php");
    exit();
}

$email = $_SESSION['reset_email'];

// 3. 生成新 OTP，这次设定 5 分钟过期
$new_otp = rand(100000, 999999);
$expiry = date("Y-m-d H:i:s", strtotime("+5 minutes"));

// 4. 更新数据库里的 OTP
$sql = "UPDATE users 
        SET user_otp_code='$new_otp',
            user_otp_expiry='$expiry'
        WHERE user_email='$email'";

$conn->query($sql);

// 5. 引入 PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    
    // 你的全新官方客服邮箱和密码！
    $mail->Username   = 'lcwcar.support@gmail.com'; 
    $mail->Password   = 'isxuzhsgirepjkfs'; 
    
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->Timeout    = 60;

    // 发件人显示 LCWcar No-Reply，并加上黑洞防止回复
    $mail->setFrom('lcwcar.support@gmail.com', 'LCWcar Support');
    $mail->addReplyTo('do-not-reply@lcwcar.com', 'No Reply');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Resend: Your Password Reset OTP Code';
    
    // LCWcar 专属高级邮件排版
    $mail->Body    = "
        <div style='font-family: Arial; padding: 20px; border: 1px solid #eee;'>
            <h2 style='color: #0f172a;'>Password Reset Request (Resend)</h2>
            <p>You requested a new OTP code. Please use the following code to proceed:</p>
            <div style='background: #f1f5f9; padding: 15px; font-size: 28px; font-weight: bold; text-align: center; letter-spacing: 10px; color: #dc2626; border-radius: 8px;'>
                {$new_otp}
            </div>
            <p style='color: #64748b; font-size: 0.9rem;'>This new code will expire in 5 minutes.</p>
            <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='font-size: 0.8rem; color: #94a3b8;'>If you did not request this, please ignore this email.</p>
        </div>";

    $mail->send();
    
    // 记录成功状态，如果你想在 verify_otp 里显示 "Resend successful" 可以用到
    $_SESSION['resend_status'] = "success";

} catch (Exception $e) {
    // 记录失败状态
    $_SESSION['resend_status'] = "error";
}

// 最后顺滑地跳回输入验证码的页面
header("Location: verify_otp.php");
exit();
?>