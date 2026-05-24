<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. 检查登录
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["id"])){
    header("location: Auth/login.php");
    exit;
}

require_once "../Config/database.php"; // ⚠️ 请确保这里的数据库路径是对的

$user_id = $_SESSION["id"];
$name = $email = $ic = $phone = $created_at = $avatar = "";
$address = $city = $state = $postcode = "";

// 2. 抓取完整资料
$sql = "SELECT user_name, user_email, user_ic, user_phone, user_created_at, user_avatar, user_address, user_city, user_state, user_postcode FROM users WHERE user_id = ?";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if(mysqli_stmt_execute($stmt)){
        mysqli_stmt_bind_result($stmt, $name, $email, $ic, $phone, $created_at, $avatar, $address, $city, $state, $postcode);
        if(mysqli_stmt_fetch($stmt)){
            $formatted_date = date("F j, Y", strtotime($created_at));
        }
    }
    mysqli_stmt_close($stmt);
}

include 'Includes/header.php';
?>

<div class="inventory-page" style="background-color: #f8fafc; min-height: 100vh; padding: 20px 0;">
    <div class="inventory-wrapper">
        <div class="profile-layout">
            
            <aside class="profile-sidebar">
                
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

                <h3><?php echo htmlspecialchars($name); ?></h3>
                <p style="color: #64748b; font-size: 14px;">Customer</p>

                <ul class="profile-menu">
                    <li><a href="profile.php" class="active">👤 My Profile</a></li>
                    <li><a href="wishlist.php">❤️ My Wishlist</a></li>
                    <li><a href="bookings.php">📅 My Bookings</a></li>
                    <li><a href="Auth/logout.php" style="color: #dc2626;">🚪 Logout</a></li>
                </ul>
            </aside>

            <main class="profile-content">
                <div class="content-card">
                    <div class="content-header">
                        <h2>Personal Information</h2>
                        <a href="edit-profile.php" class="btn-edit-link">Edit Profile</a>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-box">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($name); ?></div>
                        </div>
                        <div class="info-box">
                            <div class="info-label">Email Address</div>
                            <div class="info-value"><?php echo htmlspecialchars($email); ?></div>
                        </div>
                        <div class="info-box">
                            <div class="info-label">IC Number</div>
                            <div class="info-value"><?php echo !empty($ic) ? htmlspecialchars($ic) : '<span style="color: #94a3b8;">Not provided</span>'; ?></div>
                        </div>
                        <div class="info-box">
                            <div class="info-label">Phone Number</div>
                            <div class="info-value"><?php echo !empty($phone) ? htmlspecialchars($phone) : '<span style="color: #94a3b8;">Not provided</span>'; ?></div>
                        </div>
                    </div>

                    <h2 style="margin-top: 40px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">Address Details</h2>
                    <div class="info-grid">
                        <div class="info-box full-width">
                            <div class="info-label">Street Address</div>
                            <div class="info-value"><?php echo !empty($address) ? htmlspecialchars($address) : '<span style="color: #94a3b8;">Not provided</span>'; ?></div>
                        </div>
                        <div class="info-box">
                            <div class="info-label">City</div>
                            <div class="info-value"><?php echo !empty($city) ? htmlspecialchars($city) : '<span style="color: #94a3b8;">Not provided</span>'; ?></div>
                        </div>
                        <div class="info-box">
                            <div class="info-label">State</div>
                            <div class="info-value"><?php echo !empty($state) ? htmlspecialchars($state) : '<span style="color: #94a3b8;">Not provided</span>'; ?></div>
                        </div>
                        <div class="info-box">
                            <div class="info-label">Postcode</div>
                            <div class="info-value"><?php echo !empty($postcode) ? htmlspecialchars($postcode) : '<span style="color: #94a3b8;">Not provided</span>'; ?></div>
                        </div>
                        <div class="info-box">
                            <div class="info-label">Member Since</div>
                            <div class="info-value"><?php echo $formatted_date; ?></div>
                        </div>
                    </div>
                </div>
            </main>

        </div>
    </div>
</div>

<script>
    // 🔥 处理无刷新上传图片
    function uploadAvatar(input) {
        if (input.files && input.files[0]) {
            let formData = new FormData();
            formData.append('avatar', input.files[0]);

            let overlay = document.querySelector('.avatar-overlay');
            overlay.innerHTML = '⏳'; // Loading 状态

            fetch('upload_avatar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                overlay.innerHTML = '📷'; // 恢复相机图标

                if (data.status === 'success') {
                    // 马上更新网页上的图片
                    let img = document.getElementById('avatar-img');
                    let text = document.getElementById('avatar-text');
                    
                    img.src = data.filepath + '?t=' + new Date().getTime(); // 加上时间戳防止浏览器缓存旧图片
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