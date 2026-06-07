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
$avatar = ""; // 新增 avatar 变量

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
            if(isset($_SESSION["name"])) $_SESSION["name"] = $new_name; 
        } else {
            $error = "Something went wrong. Please try again.";
        }
        mysqli_stmt_close($stmt);
    }
}

// --- 2. 抓取当前资料以填充表单 (Fetch) ---
// 🔥 修复：这里加入了 user_avatar
$sql = "SELECT user_name, user_email, user_ic, user_phone, user_address, user_city, user_state, user_postcode, user_avatar FROM users WHERE user_id = ?";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    // 🔥 修复：绑定 $avatar
    mysqli_stmt_bind_result($stmt, $name, $email, $ic, $phone, $address, $city, $state, $postcode, $avatar);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}

include 'Includes/header.php';
?>

<style>
    /* 高级感侧边栏菜单样式 */
    .profile-menu {
        list-style: none;
        padding: 0;
        margin: 20px 0 0 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .profile-menu li a {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        color: #475569;
        font-weight: 500;
        font-size: 15px;
        padding: 12px 16px;
        border-radius: 8px;
        transition: all 0.2s ease;
    }
    .profile-menu li a:hover, .profile-menu li a.active {
        background-color: #f1f5f9;
        color: #0f172a;
    }
    .profile-menu li a svg {
        width: 20px;
        height: 20px;
        stroke: currentColor;
        stroke-width: 2;
        fill: none;
        stroke-linecap: round;
        stroke-linejoin: round;
    }
</style>

<div class="inventory-page" style="background-color: #f8fafc; min-height: 100vh; padding: 20px 0;">
    <div class="inventory-wrapper">
        <div class="profile-layout">
            
            <aside class="profile-sidebar">
                <!-- 🔥 修复：加入和 Profile 一样的头像展示和上传功能 -->
                <div class="profile-avatar" onclick="document.getElementById('avatar-upload').click();" title="Click to change avatar">
                    <?php if(!empty($avatar)): ?>
                        <img id="avatar-img" src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar">
                        <span id="avatar-text" style="display: none;"><?php echo strtoupper(substr($name, 0, 1)); ?></span>
                    <?php else: ?>
                        <img id="avatar-img" src="" alt="Avatar" style="display: none;">
                        <span id="avatar-text"><?php echo strtoupper(substr($name, 0, 1)); ?></span>
                    <?php endif; ?>
                    <div class="avatar-overlay">📷</div>
                </div>
                
                <input type="file" id="avatar-upload" accept="image/png, image/jpeg, image/jpg, image/gif" style="display: none;" onchange="uploadAvatar(this)">

                <h3>Edit Profile</h3>
                
                <ul class="profile-menu">
                    <li>
                        <a href="profile.php">
                            <!-- 高级 SVG 箭头 -->
                            <svg viewBox="0 0 24 24"><path d="M19 12H5"></path><polyline points="12 19 5 12 12 5"></polyline></svg>
                            Back to Profile
                        </a>
                    </li>
                </ul>
            </aside>

            <main class="profile-content">
                <div class="content-card">
                    <h2 style="margin-top: 0; margin-bottom: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">Edit Personal Details</h2>
                    
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
                                <input type="text" value="<?php echo htmlspecialchars($email); ?>" disabled style="background: #f1f5f9; cursor: not-allowed; border-color: #cbd5e1;">
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
                            <button type="submit" class="auth-btn auth-btn-primary" style="width: auto; padding: 12px 40px; background: #0f172a; color: white; border: none; border-radius: 8px; cursor: pointer;">Save Changes</button>
                            <a href="profile.php" class="auth-btn auth-btn-secondary" style="width: auto; padding: 12px 40px; background: #f1f5f9; color: #0f172a; text-decoration: none; border-radius: 8px; font-weight: 600; text-align: center;">Cancel</a>
                        </div>

                    </form>
                </div>
            </main>

        </div>
    </div>
</div>

<!-- 🔥 修复：加入上传头像的 JS 逻辑，让 Edit 页面也能改图 -->
<script>
    function uploadAvatar(input) {
        if (input.files && input.files[0]) {
            let formData = new FormData();
            formData.append('avatar', input.files[0]);

            let overlay = document.querySelector('.avatar-overlay');
            overlay.innerHTML = '⏳'; 

            fetch('upload_avatar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                overlay.innerHTML = '📷'; 

                if (data.status === 'success') {
                    let img = document.getElementById('avatar-img');
                    let text = document.getElementById('avatar-text');
                    
                    img.src = data.filepath + '?t=' + new Date().getTime(); 
                    img.style.display = 'block';
                    if(text) text.style.display = 'none';
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                overlay.innerHTML = '📷';
                alert('Something went wrong. Please try again.');
            });
        }
    }
</script>

<?php include 'Includes/footer.php'; ?>