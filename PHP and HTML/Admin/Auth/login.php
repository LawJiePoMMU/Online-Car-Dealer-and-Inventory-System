<?php
session_name("AdminSession"); 
session_start();
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && isset($_SESSION["user_role"]) && ($_SESSION["user_role"] === "Admin" || $_SESSION["user_role"] === "Super Admin")) {
    header("location: ../../Admin/dashboard.php");
    exit;
}

require_once "../../Config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = strtolower(trim($_POST["email"]));
    $password = trim($_POST["password"]);
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {

        $sql = "SELECT user_id, user_email, user_password, user_role, user_status 
                FROM users 
                WHERE user_email = ?";

        if ($stmt = mysqli_prepare($conn, $sql)) {

            mysqli_stmt_bind_param($stmt, "s", $email);

            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) == 1) {

                    mysqli_stmt_bind_result($stmt, $db_id, $db_email, $db_password, $db_role, $db_status);

                    if (mysqli_stmt_fetch($stmt)) {

                        if (strcasecmp($db_status, "Active") !== 0) {
                            $error = "Your account is inactive.";
                        } elseif (strcasecmp($db_role, "Admin") !== 0 && strcasecmp($db_role, "Super Admin") !== 0) {
                            $error = "Access denied. Only admins can login here.";
                        } elseif (password_verify($password, $db_password)) {

                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $db_id;
                            $_SESSION["user_email"] = $db_email;
                            $_SESSION["user_role"] = $db_role;
                            $_SESSION["user_status"] = $db_status;
                            $_SESSION["success"] = "Login Successful!";
                            header("location: ../../Admin/dashboard.php");
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
?>
<link rel="stylesheet" href="/Online-Car-Dealer-and-Inventory-System/CSS/cus.css?v=<?php echo time(); ?>">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<div class="auth-page-override">
    <div class="auth-wrapper">
        <div class="auth-container">

            <h1 class="auth-title">Admin Sign In</h1>
            <?php if (!empty($error)): ?>
                <script>
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'error',
                        title: '<?php echo $error; ?>',
                        showConfirmButton: false,
                        timer: 2500,
                        timerProgressBar: true,

                        didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer);
                            toast.addEventListener('mouseleave', Swal.resumeTimer);
                        }
                    });
                </script>
            <?php endif; ?>

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
</div>