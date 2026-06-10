<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../../Config/database.php"; 

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string(trim($_POST['email']));

    $check_user = "SELECT * FROM users WHERE user_email = '$email'";
    $result = $conn->query($check_user);

    if ($result->num_rows > 0) {
        $otp = rand(100000, 999999);
        $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));

        $update_otp = "UPDATE users SET user_otp_code = '$otp', user_otp_expiry = '$expiry' WHERE user_email = '$email'";
        
        if ($conn->query($update_otp)) {
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
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = "Your Password Reset OTP Code";
                $mail->Body    = "
                    <div style='font-family: Arial; padding: 20px; border: 1px solid #eee;'>
                        <h2 style='color: #0f172a;'>Password Reset Request</h2>
                        <p>You requested to reset your password. Please use the following OTP code to proceed:</p>
                        <div style='background: #f1f5f9; padding: 15px; font-size: 28px; font-weight: bold; text-align: center; letter-spacing: 10px; color: #dc2626; border-radius: 8px;'>
                            {$otp}
                        </div>
                        <p style='color: #64748b; font-size: 0.9rem;'>This code will expire in 15 minutes.</p>
                        <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                        <p style='font-size: 0.8rem; color: #94a3b8;'>If you did not request this, please ignore this email.</p>
                    </div>";

                $mail->send();
                $_SESSION['reset_email'] = $email;
                header("Location: verify_otp.php"); 
                exit();
            } catch (Exception $e) {
                $error = "Email failed to send. Error details: " . $mail->ErrorInfo;
            }
        }
    } else {
        $error = "Email address not found in our system.";
    }
}

include '../Includes/header.php';
?>

<div class="auth-page-override">
    <div class="auth-wrapper">
        <div class="auth-container">
            <h1 class="auth-title">Forgot Password</h1>
            <p style="text-align: center; color: #64748b; font-size: 14px; margin-bottom: 25px;">
                Enter your registered email address and we'll send you a 6-digit OTP code to reset your password.
            </p>

            <?php if(!empty($error)): ?>
                <div class="auth-error" style="background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 14px; font-weight: 600;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <div class="form-group">
                    <label class="auth-label">Email Address</label>
                    <input type="email" name="email" class="auth-input" placeholder="e.g. name@example.com" required>
                </div>
                <button type="submit" class="auth-btn auth-btn-primary" style="margin-top: 10px;">Send OTP Code</button>
            </form>

            <div style="margin-top: 25px; text-align: center;">
                <a href="login.php" style="color: #0f172a; text-decoration: none; font-size: 14px; font-weight: 600;">&larr; Back to Login</a>
            </div>
        </div>
    </div>
</div>

<?php include '../Includes/footer.php'; ?>