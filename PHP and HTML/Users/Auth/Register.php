<?php
session_start();

// 如果已经登录，直接跳到 profile
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: profile.php");
    exit;
}

require_once "../../Config/database.php";

// 定义变量并初始化为空
$name = $ic = $email = $phone = $password = $confirm_password = "";
$name_err = $email_err = $password_err = $confirm_password_err = $register_err = "";

// 当表单提交时处理数据
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // 获取并清理输入数据
    $name = trim($_POST["name"]);
    $ic = trim($_POST["ic"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    // 1. 验证必填项
    if(empty($name)){ $name_err = "Please enter your full name."; }
    if(empty($email)){ $email_err = "Please enter your email."; }
    
    // 2. 验证密码
    if(empty($password)){
        $password_err = "Please enter a password.";     
    } elseif(strlen($password) < 6){
        $password_err = "Password must have at least 6 characters.";
    }

    // 3. 验证确认密码
    if(empty($confirm_password)){
        $confirm_password_err = "Please confirm password.";     
    } else {
        if(empty($password_err) && ($password != $confirm_password)){
            $confirm_password_err = "Password did not match.";
        }
    }

    // 4. 检查 Email 是否已经被注册过了
    if(empty($email_err)){
        $sql = "SELECT user_id FROM users WHERE user_email = ?";
        
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $param_email);
            $param_email = $email;
            
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) == 1){
                    $email_err = "This email is already taken.";
                }
            } else{
                $register_err = "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // 5. 如果没有任何错误，开始把资料写进数据库
    if(empty($name_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)){
        
        // 准备 Insert 语句 (user_role 默认 Customer, user_status 默认 Active, user_created_at 默认当前时间)
        $sql = "INSERT INTO users (user_name, user_ic, user_email, user_phone, user_password, user_role, user_status, user_created_at) VALUES (?, ?, ?, ?, ?, 'Customer', 'Active', NOW())";
         
        if($stmt = mysqli_prepare($conn, $sql)){
            // 绑定参数 (5个字符串 "sssss")
            mysqli_stmt_bind_param($stmt, "sssss", $param_name, $param_ic, $param_email, $param_phone, $param_password);
            
            // 设置参数并加密密码
            $param_name = $name;
            $param_ic = $ic;
            $param_email = $email;
            $param_phone = $phone;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // 加密
            
            // 执行
            if(mysqli_stmt_execute($stmt)){
                // 注册成功，跳回登录页面
                header("location: login.php");
                exit;
            } else{
                $register_err = "Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

include '../Includes/header.php';
?>

<div class="auth-page-override">
    <div class="auth-wrapper">
        <div class="auth-container" style="max-width: 400px;">
            
            <h1 class="auth-title">Create Account</h1>

            <?php 
            if(!empty($register_err)){
                echo '<div class="auth-error">' . $register_err . '</div>';
            }        
            ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">

                <div class="form-group">
                    <label class="auth-label">Full Name *</label>
                    <input type="text" name="name" class="auth-input" value="<?php echo $name; ?>" required>
                    <span class="auth-error" style="font-size: 12px; margin-top:-15px; display:block;"><?php echo $name_err; ?></span>
                </div>

                <div class="form-group">
                    <label class="auth-label">IC Number (Optional)</label>
                    <input type="text" name="ic" id="ic-input" class="auth-input" value="<?php echo $ic; ?>" placeholder="e.g. 050606-06-0548" maxlength="14">
                </div>

                <div class="form-group">
                    <label class="auth-label">Email Address *</label>
                    <input type="email" name="email" class="auth-input" value="<?php echo $email; ?>" required>
                    <span class="auth-error" style="font-size: 12px; margin-top:-15px; display:block;"><?php echo $email_err; ?></span>
                </div>

                <div class="form-group">
                    <label class="auth-label">Phone Number (Optional)</label>
                    <input type="text" name="phone" id="phone-input" class="auth-input" value="<?php echo $phone; ?>" placeholder="e.g. 011-35666968" maxlength="12">
                </div>

                <div class="form-group">
                    <label class="auth-label">Password *</label>
                    <input type="password" name="password" class="auth-input" required>
                    <span class="auth-error" style="font-size: 12px; margin-top:-15px; display:block;"><?php echo $password_err; ?></span>
                </div>

                <div class="form-group">
                    <label class="auth-label">Confirm Password *</label>
                    <input type="password" name="confirm_password" class="auth-input" required>
                    <span class="auth-error" style="font-size: 12px; margin-top:-15px; display:block;"><?php echo $confirm_password_err; ?></span>
                </div>

                <button type="submit" class="auth-btn auth-btn-primary" style="margin-top: 10px;">Register</button>
            </form>

            <div class="auth-divider">
                <span>Already have an account?</span>
            </div>

            <a href="login.php" class="auth-btn auth-btn-secondary">Sign In Here</a>

        </div>
    </div>
</div>

<script>
    // 1. 自动格式化 IC Number
    const icInput = document.getElementById('ic-input');
    if(icInput) {
        icInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, ''); // 过滤非数字
            let formattedValue = '';
            
            if (value.length > 0) formattedValue += value.substring(0, 6);
            if (value.length > 6) formattedValue += '-' + value.substring(6, 8);
            if (value.length > 8) formattedValue += '-' + value.substring(8, 12);
            
            e.target.value = formattedValue;
        });
    }

    // 2. 自动格式化 Phone Number
    const phoneInput = document.getElementById('phone-input');
    if(phoneInput) {
        phoneInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, ''); // 过滤非数字
            let formattedValue = '';
            
            if (value.length > 0) formattedValue += value.substring(0, 3);
            if (value.length > 3) formattedValue += '-' + value.substring(3, 11);
            
            e.target.value = formattedValue;
        });
    }
</script>