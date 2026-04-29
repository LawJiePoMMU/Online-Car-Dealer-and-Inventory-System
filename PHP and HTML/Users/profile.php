<?php
session_start();

// 1. 检查用户是否已经登录，如果没有登录，踢回 login 页面
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

// 2. 引入数据库连接 (请根据你的文件夹结构调整路径)
require_once "../Config/database.php";

// 3. 准备变量
$user_id = $_SESSION["id"];
$name = $email = $ic = $phone = $created_at = "";

// 4. 从数据库抓取当前用户的所有资料
$sql = "SELECT user_name, user_email, user_ic, user_phone, user_created_at FROM users WHERE user_id = ?";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if(mysqli_stmt_execute($stmt)){
        mysqli_stmt_bind_result($stmt, $name, $email, $ic, $phone, $created_at);
        if(mysqli_stmt_fetch($stmt)){
            // 成功抓取资料，可以把日期格式化得漂亮一点
            $formatted_date = date("F j, Y", strtotime($created_at));
        }
    }
    mysqli_stmt_close($stmt);
}

// 引入 Header
include 'Includes/header.php';
?>

<div class="container">
    <div class="profile-layout">
        
        <aside class="profile-sidebar">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($name, 0, 1)); ?>
            </div>
            <h3><?php echo htmlspecialchars($name); ?></h3>
            <p style="color: var(--text-light); font-size: 14px;">Customer</p>

            <ul class="profile-menu">
                <li><a href="profile.php" class="active">👤 My Profile</a></li>
                <li><a href="wishlist.php">❤️ My Wishlist</a></li>
                <li><a href="bookings.php">📅 My Bookings</a></li>
                <li><a href="edit-profile.php">⚙️ Edit Profile</a></li>
                <li><a href="Auth/logout.php" style="color: #dc3545;">🚪 Logout</a></li>
            </ul>
        </aside>

        <main class="profile-content">
            <h2 style="margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px;">Personal Information</h2>
            
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
                    <div class="info-value">
                        <?php echo !empty($ic) ? htmlspecialchars($ic) : '<span style="color: #999;">Not provided</span>'; ?>
                    </div>
                </div>

                <div class="info-box">
                    <div class="info-label">Phone Number</div>
                    <div class="info-value">
                        <?php echo !empty($phone) ? htmlspecialchars($phone) : '<span style="color: #999;">Not provided</span>'; ?>
                    </div>
                </div>

                <div class="info-box">
                    <div class="info-label">Member Since</div>
                    <div class="info-value"><?php echo $formatted_date; ?></div>
                </div>
            </div>

            <div style="margin-top: 30px;">
                <a href="edit-profile.php" class="btn-primary">Edit Profile</a>
            </div>
        </main>

    </div>
</div>

</body>
</html>