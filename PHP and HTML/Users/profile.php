<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["id"])){
    header("location: Auth/login.php");
    exit;
}

require_once "../Config/database.php"; 

$user_id = $_SESSION["id"];
$name = $email = $ic = $phone = $created_at = $avatar = "";
$address = $city = $state = $postcode = "";

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

<style>
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
        color: #0f172a; /
    }
    
    .profile-menu li a.logout-link {
        color: #dc2626;
    }
    .profile-menu li a.logout-link:hover {
        background-color: #fef2f2;
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
                    <li>
                        <a href="profile.php" class="active">
                            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            My Profile
                        </a>
                    </li>
                    <li>
                        <a href="wishlist.php">
                            <svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                            My Wishlist
                        </a>
                    </li>
                    <li>
                        <a href="view_status.php">
                            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            My Bookings
                        </a>
                    </li>
                    <li>
                        <a href="Auth/logout.php" class="logout-link">
                            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                            Logout
                        </a>
                    </li>
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