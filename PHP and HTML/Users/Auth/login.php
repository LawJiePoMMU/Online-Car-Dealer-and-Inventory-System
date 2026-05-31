<?php
session_name("CustomerSession");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && isset($_SESSION["role"]) && strcasecmp($_SESSION["role"], "Customer") === 0){
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
        $sql = "SELECT user_id, user_email, user_password, user_role, user_status 
                FROM users WHERE user_email = ?";

        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $email);
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) == 1){
                    mysqli_stmt_bind_result($stmt, $db_id, $db_email, $db_password, $db_role, $db_status);
                    if(mysqli_stmt_fetch($stmt)){
                        if(strcasecmp($db_status, "Active") !== 0){
                            $error = "Your account is inactive.";
                        } elseif(strcasecmp($db_role, "Customer") !== 0){
                            $error = "Access denied. Only customers can login here.";
                        } elseif(password_verify($password, $db_password)){
                            // 登录成功
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $db_id; 
                            $_SESSION["email"] = $db_email;
                            $_SESSION["role"] = $db_role; // 这里存的是 "Customer"

                            header("location: ../index.php");
                            exit;
                        } else {
                            $error = "Incorrect password.";
                        }
                    }
                } else {
                    $error = "No account found with this email.";
                }
            } else {
                $error = "Something went wrong. Try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

include '../Includes/header.php';
?>

<div class="auth-page-override">
    <div class="auth-wrapper">
        <div class="auth-container">

            <h1 class="auth-title">Sign In</h1>

            <?php 
            if(!empty($error)){
                echo '<div class="auth-error" style="background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 14px; font-weight: 600;">' . $error . '</div>';
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

                    <div class="forgot-link">
                        <a href="forgot_password.php">Forgot Password?</a>
                    </div>
                </div>

                <button type="submit" class="auth-btn auth-btn-primary">Sign In</button>
            </form>

            <div class="auth-divider"><span>Or</span></div>

            <a href="Register.php" class="auth-btn auth-btn-secondary">Create Account</a>

        </div>
    </div>
</div>

<?php include '../Includes/footer.php'; ?>