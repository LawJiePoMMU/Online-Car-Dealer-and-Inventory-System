<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../../Config/database.php";

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot-password.php");
    exit();
}

$email = $_SESSION['reset_email'];

$new_otp = rand(100000, 999999);
$expiry = date("Y-m-d H:i:s", strtotime("+5 minutes"));

$sql = "UPDATE users 
        SET user_otp_code='$new_otp',
            user_otp_expiry='$expiry'
        WHERE user_email='$email'";

$conn->query($sql);

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
    $mail->Username   = 'lcwcar.support@gmail.com'; 
    $mail->Password   = 'isxuzhsgirepjkfs'; 
    
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->Timeout    = 60;

    $mail->setFrom('lcwcar.support@gmail.com', 'LCWcar Support');
    $mail->addReplyTo('do-not-reply@lcwcar.com', 'No Reply');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Resend: Your Password Reset OTP Code';

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
    
    $_SESSION['resend_status'] = "success";

} catch (Exception $e) {
    $_SESSION['resend_status'] = "error";
}

header("Location: verify_otp.php");
exit();
?>