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
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $entered_otp = $conn->real_escape_string(trim($_POST['otp']));
    
    $sql = "SELECT user_otp_code, user_otp_expiry FROM users WHERE user_email = '$email'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $db_otp = $row['user_otp_code'];
        $expiry = $row['user_otp_expiry'];
        $current_time = date("Y-m-d H:i:s");
        
        if ($entered_otp === $db_otp && $current_time <= $expiry) {
            $_SESSION['otp_verified'] = true;
            header("Location: reset_password.php");
            exit();
        } elseif ($current_time > $expiry) {
            $error = "This OTP has expired. Please request a new one.";
        } else {
            $error = "Incorrect OTP. Please try again.";
        }
    } else {
        $error = "Error: User not found.";
    }
}

include '../Includes/header.php';
?>

<div class="auth-page-override">
    <div class="auth-wrapper">
        <div class="auth-container" style="text-align: center;">
            <h1 class="auth-title">Verify OTP</h1>
            <p style="color: #64748b; font-size: 14px; margin-bottom: 5px;">We've sent a 6-digit code to:</p>
            <div style="background: #f1f5f9; color: #0f172a; padding: 12px; border-radius: 8px; font-weight: 700; margin-bottom: 25px; word-break: break-all;">
                <?php echo htmlspecialchars($email); ?>
            </div>
            
            <?php if(!empty($error)): ?>
                <div class="auth-error" style="background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 600;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <input type="text" name="otp" maxlength="6" pattern="\d{6}" placeholder="------" required autocomplete="off" style="width: 100%; padding: 15px; margin-bottom: 20px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 32px; text-align: center; letter-spacing: 15px; font-weight: 800; color: #0f172a; outline: none; box-sizing: border-box; transition: 0.3s;" onfocus="this.style.borderColor='#0f172a'">
                
                <button type="submit" class="auth-btn auth-btn-primary" style="width: 100%;">Verify Code</button>
            </form>

            <div style="margin-top: 25px; font-size: 14px; color: #64748b;">
                <span id="countdownText">You can resend code in <span id="timer" style="color: #dc2626; font-weight: bold;">60</span>s</span>
               <a href="resend_otp.php" id="resendLink"style="color: #0f172a; font-weight: 600; text-decoration: none; display: none;">Didn't receive it? Resend OTP</a>
            </div>
        </div>
    </div>
</div>

<script>
    const timerElement = document.getElementById('timer');
    const countdownText = document.getElementById('countdownText');
    const resendLink = document.getElementById('resendLink');

    // 1. 获取当前时间（秒）
    let now = Math.floor(Date.now() / 1000);
    
    // 2. 去 sessionStorage 里面找，看看之前有没有存过倒数结束的时间？
    let targetTime = sessionStorage.getItem('otpCooldownTarget');

    // 3. 如果没存过（第一次进页面），或者之前存的时间已经过期很久了，就重新设定一个新的结束时间（当前时间 + 60秒）
    if (!targetTime || targetTime < now - 60) {
        targetTime = now + 60;
        sessionStorage.setItem('otpCooldownTarget', targetTime);
    }

    // 4. 计算还剩下多少秒
    let timeLeft = targetTime - now;

    // 5. 倒数逻辑
    function updateTimer() {
        if (timeLeft <= 0) {
            clearInterval(countdown); // 停止倒数
            countdownText.style.display = 'none'; // 隐藏 "You can resend in..."
            resendLink.style.display = 'inline-block'; // 显示 Resend 按钮
        } else {
            timerElement.textContent = timeLeft; // 更新画面上的数字
            timeLeft--;
        }
    }

    // 进网页立刻执行一次，避免闪烁
    updateTimer(); 
    
    // 开启每秒钟执行一次的计时器
    const countdown = setInterval(updateTimer, 1000);

    // 🔥 核心逻辑：当用户点击 "Resend OTP" 时，清空记忆！
    // 这样重新跳转回来的时候，系统才会给它一个全新的 60 秒！
    resendLink.addEventListener('click', function() {
        sessionStorage.removeItem('otpCooldownTarget');
    });
</script>

<?php include '../Includes/footer.php'; ?>