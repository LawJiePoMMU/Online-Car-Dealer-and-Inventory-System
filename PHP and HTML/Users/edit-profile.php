<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: Auth/login.php");
    exit;
}

require_once "../Config/database.php";

$user_id = $_SESSION["id"];
$success = $error = "";

// --- 1. 处理表单提交 (Update) ---
if($_SERVER["REQUEST_METHOD"] == "POST"){
    $new_name = trim($_POST["user_name"]);
    $new_ic = trim($_POST["user_ic"]);
    $new_phone = trim($_POST["user_phone"]);
    $new_address = trim($_POST["user_address"]);
    $new_city = trim($_POST["user_city"]);
    $new_state = trim($_POST["user_state"]);
    $new_postcode = trim($_POST["user_postcode"]);

    $update_sql = "UPDATE users SET user_name=?, user_ic=?, user_phone=?, user_address=?, user_city=?, user_state=?, user_postcode=? WHERE user_id=?";
    
    if($stmt = mysqli_prepare($conn, $update_sql)){
        mysqli_stmt_bind_param($stmt, "sssssssi", $new_name, $new_ic, $new_phone, $new_address, $new_city, $new_state, $new_postcode, $user_id);
        
        if(mysqli_stmt_execute($stmt)){
            $success = "Profile updated successfully!";
            // 同时更新 Session 里的名字（如果 header 有显示名字的话）
            $_SESSION["name"] = $new_name; 
        } else {
            $error = "Something went wrong. Please try again.";
        }
        mysqli_stmt_close($stmt);
    }
}

// --- 2. 抓取当前资料以填充表单 (Fetch) ---
$sql = "SELECT user_name, user_email, user_ic, user_phone, user_address, user_city, user_state, user_postcode FROM users WHERE user_id = ?";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $name, $email, $ic, $phone, $address, $city, $state, $postcode);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}

include 'Includes/header.php';
?>

<div class="inventory-page">
    <div class="inventory-wrapper">
        <div class="profile-layout">
            
            <aside class="profile-sidebar">
                <div class="profile-avatar"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
                <h3>Edit Profile</h3>
                <ul class="profile-menu">
                    <li><a href="profile.php">👤 Back to Profile</a></li>
                </ul>
            </aside>

            <main class="profile-content">
                <div class="content-card">
                    <h2>Edit Personal Details</h2>
                    
                    <?php if($success): ?>
                        <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                             <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="edit-form">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="user_name" value="<?php echo htmlspecialchars($name); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email (Read-only)</label>
                                <input type="text" value="<?php echo htmlspecialchars($email); ?>" disabled style="background: #f1f5f9; cursor: not-allowed;">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>IC Number</label>
                                <input type="text" name="user_ic" value="<?php echo htmlspecialchars($ic); ?>">
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="user_phone" value="<?php echo htmlspecialchars($phone); ?>">
                            </div>
                        </div>

                        <h3 style="margin: 30px 0 15px 0; border-top: 1px solid #f1f5f9; padding-top: 20px;">Address Information</h3>
                        
                        <div class="form-group">
                            <label>Street Address</label>
                            <input type="text" name="user_address" value="<?php echo htmlspecialchars($address); ?>" placeholder="Unit, House No, Street name...">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>City</label>
                                <input type="text" name="user_city" value="<?php echo htmlspecialchars($city); ?>">
                            </div>
                            <div class="form-group">
                                <label>State</label>
                                <input type="text" name="user_state" value="<?php echo htmlspecialchars($state); ?>">
                            </div>
                            <div class="form-group">
                                <label>Postcode</label>
                                <input type="text" name="user_postcode" value="<?php echo htmlspecialchars($postcode); ?>">
                            </div>
                        </div>

                        <div style="margin-top: 30px; display: flex; gap: 15px;">
                            <button type="submit" class="auth-btn auth-btn-primary" style="width: auto; padding: 12px 40px;">Save Changes</button>
                            <a href="profile.php" class="auth-btn auth-btn-secondary" style="width: auto; padding: 12px 40px; text-decoration: none; text-align: center;">Cancel</a>
                        </div>

                    </form>
                </div>
            </main>

        </div>
    </div>
</div>

<?php include 'Includes/footer.php'; ?><?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: Auth/login.php");
    exit;
}

require_once "../Config/database.php";

$user_id = $_SESSION["id"];
$success = $error = "";

// --- 1. 处理表单提交 (Update) ---
if($_SERVER["REQUEST_METHOD"] == "POST"){
    $new_name = trim($_POST["user_name"]);
    $new_ic = trim($_POST["user_ic"]);
    $new_phone = trim($_POST["user_phone"]);
    $new_address = trim($_POST["user_address"]);
    $new_city = trim($_POST["user_city"]);
    $new_state = trim($_POST["user_state"]);
    $new_postcode = trim($_POST["user_postcode"]);

    $update_sql = "UPDATE users SET user_name=?, user_ic=?, user_phone=?, user_address=?, user_city=?, user_state=?, user_postcode=? WHERE user_id=?";
    
    if($stmt = mysqli_prepare($conn, $update_sql)){
        mysqli_stmt_bind_param($stmt, "sssssssi", $new_name, $new_ic, $new_phone, $new_address, $new_city, $new_state, $new_postcode, $user_id);
        
        if(mysqli_stmt_execute($stmt)){
            $success = "Profile updated successfully!";
            // 同时更新 Session 里的名字（如果 header 有显示名字的话）
            $_SESSION["name"] = $new_name; 
        } else {
            $error = "Something went wrong. Please try again.";
        }
        mysqli_stmt_close($stmt);
    }
}

// --- 2. 抓取当前资料以填充表单 (Fetch) ---
$sql = "SELECT user_name, user_email, user_ic, user_phone, user_address, user_city, user_state, user_postcode FROM users WHERE user_id = ?";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $name, $email, $ic, $phone, $address, $city, $state, $postcode);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}

include 'Includes/header.php';
?>

<div class="inventory-page">
    <div class="inventory-wrapper">
        <div class="profile-layout">
            
            <aside class="profile-sidebar">
                <div class="profile-avatar"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
                <h3>Edit Profile</h3>
                <ul class="profile-menu">
                    <li><a href="profile.php">👤 Back to Profile</a></li>
                </ul>
            </aside>

            <main class="profile-content">
                <div class="content-card">
                    <h2>Edit Personal Details</h2>
                    
                    <?php if($success): ?>
                        <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                            ✅ <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="edit-form">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="user_name" value="<?php echo htmlspecialchars($name); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email (Read-only)</label>
                                <input type="text" value="<?php echo htmlspecialchars($email); ?>" disabled style="background: #f1f5f9; cursor: not-allowed;">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>IC Number</label>
                                <input type="text" name="user_ic" value="<?php echo htmlspecialchars($ic); ?>">
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="user_phone" value="<?php echo htmlspecialchars($phone); ?>">
                            </div>
                        </div>

                        <h3 style="margin: 30px 0 15px 0; border-top: 1px solid #f1f5f9; padding-top: 20px;">Address Information</h3>
                        
                        <div class="form-group">
                            <label>Street Address</label>
                            <input type="text" name="user_address" value="<?php echo htmlspecialchars($address); ?>" placeholder="Unit, House No, Street name...">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>City</label>
                                <input type="text" name="user_city" value="<?php echo htmlspecialchars($city); ?>">
                            </div>
                            <div class="form-group">
                                <label>State</label>
                                <input type="text" name="user_state" value="<?php echo htmlspecialchars($state); ?>">
                            </div>
                            <div class="form-group">
                                <label>Postcode</label>
                                <input type="text" name="user_postcode" value="<?php echo htmlspecialchars($postcode); ?>">
                            </div>
                        </div>

                        <div style="margin-top: 30px; display: flex; gap: 15px;">
                            <button type="submit" class="auth-btn auth-btn-primary" style="width: auto; padding: 12px 40px;">Save Changes</button>
                            <a href="profile.php" class="auth-btn auth-btn-secondary" style="width: auto; padding: 12px 40px; text-decoration: none; text-align: center;">Cancel</a>
                        </div>

                    </form>
                </div>
            </main>

        </div>
    </div>
</div>

<?php include 'Includes/footer.php'; ?><?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: Auth/login.php");
    exit;
}

require_once "../Config/database.php";

$user_id = $_SESSION["id"];
$success = $error = "";

// --- 1. 处理表单提交 (Update) ---
if($_SERVER["REQUEST_METHOD"] == "POST"){
    $new_name = trim($_POST["user_name"]);
    $new_ic = trim($_POST["user_ic"]);
    $new_phone = trim($_POST["user_phone"]);
    $new_address = trim($_POST["user_address"]);
    $new_city = trim($_POST["user_city"]);
    $new_state = trim($_POST["user_state"]);
    $new_postcode = trim($_POST["user_postcode"]);

    $update_sql = "UPDATE users SET user_name=?, user_ic=?, user_phone=?, user_address=?, user_city=?, user_state=?, user_postcode=? WHERE user_id=?";
    
    if($stmt = mysqli_prepare($conn, $update_sql)){
        mysqli_stmt_bind_param($stmt, "sssssssi", $new_name, $new_ic, $new_phone, $new_address, $new_city, $new_state, $new_postcode, $user_id);
        
        if(mysqli_stmt_execute($stmt)){
            $success = "Profile updated successfully!";
            // 同时更新 Session 里的名字（如果 header 有显示名字的话）
            $_SESSION["name"] = $new_name; 
        } else {
            $error = "Something went wrong. Please try again.";
        }
        mysqli_stmt_close($stmt);
    }
}

// --- 2. 抓取当前资料以填充表单 (Fetch) ---
$sql = "SELECT user_name, user_email, user_ic, user_phone, user_address, user_city, user_state, user_postcode FROM users WHERE user_id = ?";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $name, $email, $ic, $phone, $address, $city, $state, $postcode);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}

include 'Includes/header.php';
?>

<div class="inventory-page">
    <div class="inventory-wrapper">
        <div class="profile-layout">
            
            <aside class="profile-sidebar">
                <div class="profile-avatar"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
                <h3>Edit Profile</h3>
                <ul class="profile-menu">
                    <li><a href="profile.php">👤 Back to Profile</a></li>
                </ul>
            </aside>

            <main class="profile-content">
                <div class="content-card">
                    <h2>Edit Personal Details</h2>
                    
                    <?php if($success): ?>
                        <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                            ✅ <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="edit-form">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="user_name" value="<?php echo htmlspecialchars($name); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email (Read-only)</label>
                                <input type="text" value="<?php echo htmlspecialchars($email); ?>" disabled style="background: #f1f5f9; cursor: not-allowed;">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>IC Number</label>
                                <input type="text" name="user_ic" value="<?php echo htmlspecialchars($ic); ?>">
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="user_phone" value="<?php echo htmlspecialchars($phone); ?>">
                            </div>
                        </div>

                        <h3 style="margin: 30px 0 15px 0; border-top: 1px solid #f1f5f9; padding-top: 20px;">Address Information</h3>
                        
                        <div class="form-group">
                            <label>Street Address</label>
                            <input type="text" name="user_address" value="<?php echo htmlspecialchars($address); ?>" placeholder="Unit, House No, Street name...">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>City</label>
                                <input type="text" name="user_city" value="<?php echo htmlspecialchars($city); ?>">
                            </div>
                            <div class="form-group">
                                <label>State</label>
                                <input type="text" name="user_state" value="<?php echo htmlspecialchars($state); ?>">
                            </div>
                            <div class="form-group">
                                <label>Postcode</label>
                                <input type="text" name="user_postcode" value="<?php echo htmlspecialchars($postcode); ?>">
                            </div>
                        </div>

                        <div style="margin-top: 30px; display: flex; gap: 15px;">
                            <button type="submit" class="auth-btn auth-btn-primary" style="width: auto; padding: 12px 40px;">Save Changes</button>
                            <a href="profile.php" class="auth-btn auth-btn-secondary" style="width: auto; padding: 12px 40px; text-decoration: none; text-align: center;">Cancel</a>
                        </div>

                    </form>
                </div>
            </main>

        </div>
    </div>
</div>

<?php include 'Includes/footer.php'; ?>