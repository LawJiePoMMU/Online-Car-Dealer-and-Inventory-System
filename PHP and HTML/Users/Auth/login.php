<?php
session_start();

if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: profile.php");
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
        $sql = "SELECT id, email, password FROM users WHERE email = :email";
        
        if($stmt = $pdo->prepare($sql)){
            $stmt->bindParam(":email", $email, PDO::PARAM_STR);
            
            if($stmt->execute()){
                if($stmt->rowCount() == 1){
                    if($row = $stmt->fetch()){
                        if(password_verify($password, $row["password"])){
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $row["id"];
                            $_SESSION["email"] = $row["email"];
                            header("location: profile.php");
                            exit;
                        } else {
                            $error = "Invalid email or password.";
                        }
                    }
                } else {
                    $error = "Invalid email or password.";
                }
            } else{
                $error = "Something went wrong.";
            }
            unset($stmt);
        }
    }
    unset($pdo);
}

include '../Includes/header.php';
?>

<div class="auth-page-override">
    <div class="auth-wrapper">
        <div class="auth-container">
            
            <h1 class="auth-title">Sign In</h1>

            <?php 
            if(!empty($error)){
                echo '<div class="auth-error">' . $error . '</div>';
            }        
            ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">

                <!-- Email -->
                <div class="form-group">
                    <label class="auth-label">Email</label>
                    <input type="email" name="email" class="auth-input" required>
                </div>

                <!-- Password -->
                <div class="form-group password-wrapper">
                    <label class="auth-label">Password</label>
                    <input type="password" name="password" class="auth-input" required>

                    <div class="forgot-link">
                        <a href="forgot-password.php">Forgot Password?</a>
                    </div>
                </div>

                <button type="submit" class="auth-btn auth-btn-primary">Sign In</button>
            </form>

            <div class="auth-divider">
                <span>Or</span>
            </div>

            <a href="register.php" class="auth-btn auth-btn-secondary">Create Account</a>

        </div>
    </div>
</div>