<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../../Config/database.php";

if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true || !isset($_SESSION['reset_email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['reset_email'];
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = $conn->real_escape_string($_POST['new_password']);
    $confirm_password = $conn->real_escape_string($_POST['confirm_password']);

    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match. Please try again.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_sql = "UPDATE users SET user_password = '$hashed_password', user_otp_code = NULL, user_otp_expiry = NULL WHERE user_email = '$email'";

        if ($conn->query($update_sql)) {
            unset($_SESSION['otp_verified']);
            unset($_SESSION['reset_email']);
            $success = "Password successfully reset! You can now log in.";
        } else {
            $error = "Error updating password. Please try again later.";
        }
    }
}

include '../Includes/header.php';
?>

<div class="auth-page-override">
    <div class="auth-wrapper">
        <div class="auth-container">
            <h1 class="auth-title">Create New Password</h1>
            <p style="text-align: center; color: #64748b; font-size: 14px; margin-bottom: 25px;">
                Please enter a secure new password for your account.
            </p>

            <?php if(!empty($error)): ?>
                <div class="auth-error" style="background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 14px; font-weight: 600;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if(!empty($success)): ?>
                <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 14px; font-weight: 600;">
                    ✅ <?php echo $success; ?>
                </div>
                <a href="login.php" class="auth-btn auth-btn-primary" style="display: block; text-align: center; text-decoration: none;">Go to Login Page</a>
            <?php else: ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                    <div class="form-group">
                        <label class="auth-label">New Password</label>
                        <input type="password" name="new_password" class="auth-input" placeholder="At least 6 characters" required>
                    </div>
                    <div class="form-group">
                        <label class="auth-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="auth-input" placeholder="Re-enter new password" required>
                    </div>
                    <button type="submit" class="auth-btn auth-btn-primary" style="margin-top: 10px;">Update Password</button>
                </form>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php include '../../Includes/footer.php'; ?>