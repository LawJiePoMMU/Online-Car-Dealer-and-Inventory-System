<?php
session_start();

// 如果已经登录，直接跳到 profile
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: ../index.php");
    exit;
}

require_once "../../Config/database.php";

$error = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if(empty($email) || empty($password)){
        $error = "Please enter both email and password.";
    } else {
        // 🔥 使用 ? 代替了原来的 :email (这是 mysqli 的写法)
        $sql = "SELECT user_id, user_email, user_password, user_role, user_status FROM users WHERE user_email = ?";
        
        // 🔥 使用 mysqli_prepare 和 $conn
        if($stmt = mysqli_prepare($conn, $sql)){
            
            // 绑定参数 "s" 代表 string (字符串)
            mysqli_stmt_bind_param($stmt, "s", $email);
            
            // 执行查询
            if(mysqli_stmt_execute($stmt)){
                
                // 存储结果以便检查行数
                mysqli_stmt_store_result($stmt);
                
                // 检查有没有找到这个 email
                if(mysqli_stmt_num_rows($stmt) == 1){
                    
                    // 将数据库里的字段绑定到变量上
                    mysqli_stmt_bind_result($stmt, $db_id, $db_email, $db_password, $db_role, $db_status);
                    
                    if(mysqli_stmt_fetch($stmt)){
                        
                        // 🔥 1. 检查账号是否 Active
                        if(strcasecmp($db_status, "Active") !== 0){
                            $error = "Your account is currently inactive. Cannot login.";
                        } 
                        // 🔥 2. 检查身份 (Admin 不可以在这里登录)
                        elseif(strcasecmp($db_role, "Admin") !== 0){
                            $error = "Access denied. Only admins can use this login page.";
                        } 
                        // 🔥 3. 验证密码
                        elseif(password_verify($password, $db_password)){
                            // 密码正确，允许登录
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $db_id;
                            $_SESSION["email"] = $db_email;
                            $_SESSION["role"] = $db_role; 
                            
                            header("location: ../dashboard.php");
                            exit;
                        } else {
                            // 密码错误
                            $error = "Incorrect password or email.";
                        }
                    }
                } else {
                    // 🔥 找不到 Email
                    $error = "No account found with this email. Please register.";
                }
            } else{
                $error = "Something went wrong. Please try again later.";
            }
            // 关闭语句
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<link rel="stylesheet" href="/Online-Car-Dealer-and-Inventory-System/CSS/cus.css">

<div class="auth-page-override">
    <div class="auth-wrapper">
        <div class="auth-container">
            
            <h1 class="auth-title">Sign In</h1>

            <?php 
            // 打印错误信息
            if(!empty($error)){
                echo '<div class="auth-error">' . $error . '</div>';
            }        
            ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">

                <div class="form-group">
                    <label class="auth-label">Email</label>
                    <input type="email" name="email" class="auth-input" required>
                </div>

                <div class="form-group password-wrapper">
                    <label class="auth-label">Password</label>
                    <input type="password" name="password" class="auth-input" required>
                </div>

                <button type="submit" class="auth-btn auth-btn-primary">Sign In</button>
            </form>
    </div>
</div>