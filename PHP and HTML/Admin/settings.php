<?php
session_start();
include '../Config/database.php';

$admin_id = $_SESSION['user_id'] ?? 1;

if (isset($_GET['ajax']) || isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');

    try {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $query = "SELECT setting_key, setting_value FROM system_settings";
            $result = mysqli_query($conn, $query);
            $settings = [];

            while ($row = mysqli_fetch_assoc($result)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            $user_query = mysqli_query($conn, "SELECT user_name, user_email, user_avatar FROM users WHERE user_id = $admin_id");
            $user = mysqli_fetch_assoc($user_query);

            echo json_encode(['success' => true, 'data' => $settings, 'user' => $user]);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'update_settings') {
                $updates = json_decode($_POST['settings_data'], true);

                foreach ($updates as $key => $value) {
                    if (is_numeric($value) && floatval($value) < 0) {
                        echo json_encode(['success' => false, 'message' => 'System rejected negative value.']);
                        exit;
                    }
                }

                mysqli_begin_transaction($conn);
                $stmt = mysqli_prepare($conn, "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

                foreach ($updates as $key => $value) {
                    mysqli_stmt_bind_param($stmt, "ss", $key, $value);
                    mysqli_stmt_execute($stmt);
                }

                mysqli_commit($conn);
                echo json_encode(['success' => true, 'message' => 'System logic updated!']);
                exit;
            }

            if ($action === 'update_profile') {
                $name = mysqli_real_escape_string($conn, $_POST['user_name']);
                $email = mysqli_real_escape_string($conn, $_POST['user_email']);
                $password = trim($_POST['new_password'] ?? '');

                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET user_name='$name', user_email='$email', user_password='$hashed' WHERE user_id=$admin_id";
                } else {
                    $sql = "UPDATE users SET user_name='$name', user_email='$email' WHERE user_id=$admin_id";
                }

                if (mysqli_query($conn, $sql)) {
                    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = '../../Images/Uploads/'; 
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                        $fileName = time() . '_' . basename($_FILES['profile_picture']['name']);
                        $targetPath = $uploadDir . $fileName;

                        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
                            mysqli_query($conn, "UPDATE users SET user_avatar='$targetPath' WHERE user_id=$admin_id");
                        }
                    }
                    mysqli_commit($conn);
                    echo json_encode(['success' => true, 'message' => 'Admin profile updated!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error.']);
                }
                exit;
            }
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>System Settings</title>
    <link rel="stylesheet" href="../../CSS/Admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        .settings-layout {
            display: flex;
            gap: 30px;
            margin-top: 20px;
            font-family: 'Inter', 'Poppins', sans-serif;
        }

        .settings-sidebar {
            width: 250px;
            flex-shrink: 0;
        }

        .settings-tab {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #4b5563;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.2s;
            margin-bottom: 4px;
        }

        .settings-tab:hover {
            background: #f3f4f6;
        }

        .settings-tab.active {
            background: #eff6ff;
            color: #1e3a8a;
            border-left: 4px solid #1e3a8a;
        }

        .settings-content {
            flex: 1;
            max-width: 850px;
        }

        .settings-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            display: none;
        }

        .settings-card.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-header {
            border-bottom: 1px solid #f3f4f6;
            margin-bottom: 25px;
            padding-bottom: 10px;
        }

        .section-header h3 {
            margin: 0;
            font-size: 18px;
            color: #111827;
        }

        .setting-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 20px;
        }

        .setting-group {
            margin-bottom: 20px;
        }

        .setting-group label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 8px;
        }

        .setting-group input[type="text"],
        .setting-group input[type="email"],
        .setting-group input[type="number"],
        .setting-group input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            background: #f9fafb;
            transition: 0.2s;
            box-sizing: border-box;
        }

        .setting-group input:focus {
            border-color: #1e3a8a;
            background: #fff;
            outline: none;
            box-shadow: 0 0 0 4px rgba(30, 58, 138, 0.05);
        }

        .helper-text {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-save {
            background: #1e3a8a;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-save:hover {
            background: #1e40af;
            transform: translateY(-1px);
        }

        .profile-header-layout {
            display: flex;
            gap: 30px;
            align-items: center;
            margin-bottom: 30px;
        }

        .profile-avatar-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            width: 120px;
        }

        .avatar-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #eff6ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #1e3a8a;
            border: 2px solid #bfdbfe;
        }

        .role-badge {
            background: #1e3a8a;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .profile-info-section {
            flex: 1;
        }

        .security-section {
            background: #fffcf5;
            border: 1px solid #ffedd5;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #f97316;
        }

        .security-section .sub-heading {
            color: #9a3412;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toggle-switch {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .toggle-switch-text strong {
            display: block;
            font-size: 14px;
            color: #111827;
            margin-bottom: 4px;
        }

        .toggle-switch-text span {
            font-size: 12px;
            color: #6b7280;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: .3s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: #1e3a8a;
        }

        input:checked+.slider:before {
            transform: translateX(20px);
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar" style="margin-bottom: 24px;">
            <h1 style="font-size: 24px; font-weight: 800; color: #111827;">Settings</h1>
        </header>

        <div class="settings-layout">
            <div class="settings-sidebar">
                <div class="settings-tab active" data-target="tab-profile"><i class="fas fa-user-circle"></i> My Profile
                </div>
                <div class="settings-tab" data-target="tab-business"><i class="fas fa-building"></i> Business Profile
                </div>
                <div class="settings-tab" data-target="tab-system"><i class="fas fa-calculator"></i> System Variables
                </div>
                <div class="settings-tab" data-target="tab-notif"><i class="fas fa-bell"></i> Notifications</div>
            </div>

            <div class="settings-content">

                <div class="settings-card active" id="tab-profile">
                    <form id="form-profile">
                        <div class="profile-header-layout">
                            <div class="profile-avatar-section">
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/*"
                                    style="display: none;" onchange="previewAvatar(event)">
                                <div class="avatar-circle" onclick="document.getElementById('profile_picture').click();"
                                    style="cursor: pointer; overflow: hidden; position: relative;">
                                    <img id="avatar_preview_img" src=""
                                        style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                    <i class="fas fa-user-tie" id="avatar_icon"></i>
                                </div>
                                <span class="role-badge">SYSTEM ADMIN</span>
                                <div class="helper-text" style="cursor: pointer; margin-top: 5px;"
                                    onclick="document.getElementById('profile_picture').click();">Click to Change</div>
                            </div>
                            <div class="profile-info-section">
                                <div class="setting-row">
                                    <div class="setting-group">
                                        <label>Full Name</label>
                                        <input type="text" id="user_name" name="user_name" required>
                                    </div>
                                    <div class="setting-group">
                                        <label>Login Email</label>
                                        <input type="email" id="user_email" name="user_email" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="security-section">
                            <h4 class="sub-heading"><i class="fas fa-shield-alt"></i> Security & Authentication</h4>
                            <div class="setting-group" style="max-width: 50%;">
                                <label>New Password</label>
                                <input type="password" name="new_password"
                                    placeholder="Leave blank to keep current password">
                                <div class="helper-text"><i class="fas fa-info-circle"></i> Password will be encrypted
                                    securely.</div>
                            </div>
                        </div>

                        <div style="text-align: right; margin-top: 25px;">
                            <button type="button" class="btn-save" onclick="saveProfile('form-profile')">Save
                                Profile</button>
                        </div>
                    </form>
                </div>

             <div class="settings-card" id="tab-business">
                    <div class="section-header">
                        <h3>Company Information</h3>
                    </div>
              <form id="form-business">
                        <div class="setting-group">
                            <label>Company Display Name</label>
                            <input type="text" id="company_name" name="company_name">
                        </div>
                        
                        <div class="setting-row">
                            <div class="setting-group">
                                <label>Registration Number (SSM)</label>
                                <input type="text" id="company_ssm" name="company_ssm" placeholder="e.g. 202301012345">
                            </div>
                            <div class="setting-group">
                                <label>Sales Hotline / WhatsApp</label>
                                <input type="text" id="contact_phone" name="contact_phone" placeholder="+60 12-345 6789">
                            </div>
                        </div>

                        <div class="setting-group">
                            <label>Showroom Address</label>
                            <textarea id="company_address" name="company_address" style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-family: inherit; resize: vertical; min-height: 80px;" placeholder="Enter full physical address"></textarea>
                        </div>

                        <div class="setting-row">
                            <div class="setting-group">
                                <label>Official Email</label>
                                <input type="email" id="contact_email" name="contact_email">
                            </div>
                            <div class="setting-group">
                                <label>Currency Symbol</label>
                                <input type="text" id="currency" name="currency">
                            </div>
                        </div>
                        
                        <div style="text-align: right; margin-top: 20px; border-top: 1px solid #f3f4f6; padding-top: 20px;">
                            <button type="button" class="btn-save" onclick="saveSettings('form-business')">Update Business</button>
                        </div>
                    </form>
                </div>

                <div class="settings-card" id="tab-system">
                    <div class="section-header">
                        <h3>Financial Calculation Defaults</h3>
                    </div>
                    <form id="form-system">
                        <div class="setting-row">
                            <div class="setting-group">
                                <label>Default Down Payment (%)</label>
                                <input type="number" step="0.1" min="0"
                                    onkeydown="if(['-','e'].includes(event.key)) event.preventDefault();"
                                    id="default_dp_percent" name="default_dp_percent">
                                <div class="helper-text"><i class="fas fa-info-circle"></i> Standard rate for new
                                    bookings.</div>
                            </div>
                            <div class="setting-group">
                                <label>Interest Rate (% p.a.)</label>
                                <input type="number" step="0.01" min="0"
                                    onkeydown="if(['-','e'].includes(event.key)) event.preventDefault();"
                                    id="default_loan_rate" name="default_loan_rate">
                                <div class="helper-text"><i class="fas fa-percentage"></i> Default bank interest rate.
                                </div>
                            </div>
                        </div>
                        <div
                            style="text-align: right; margin-top: 20px; border-top: 1px solid #f3f4f6; padding-top: 20px;">
                            <button type="button" class="btn-save" onclick="saveSettings('form-system')">Save Finance
                                Logic</button>
                        </div>
                    </form>
                </div>

                <div class="settings-card" id="tab-notif">
                    <div class="section-header">
                        <h3>System Alerts & Rules</h3>
                    </div>
                    <form id="form-notif">
                        <div class="setting-group" style="max-width: 50%; margin-bottom: 25px;">
                            <label>Low Stock Warning Threshold</label>
                            <input type="number" min="0"
                                onkeydown="if(['-','e'].includes(event.key)) event.preventDefault();"
                                id="low_stock_threshold" name="low_stock_threshold">
                            <div class="helper-text"><i class="fas fa-exclamation-triangle"></i> Highlight cars when
                                stock hits this value.</div>
                        </div>

                        <div class="toggle-switch">
                            <div class="toggle-switch-text">
                                <strong>New Booking Alert</strong>
                                <span>Show red dot in sidebar when a new order arrives.</span>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="alert_new_booking" name="alert_new_booking" value="1">
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="toggle-switch">
                            <div class="toggle-switch-text">
                                <strong>Document Verification</strong>
                                <span>Notify admin when a customer uploads required documents.</span>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="alert_doc_verification" name="alert_doc_verification"
                                    value="1">
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="toggle-switch">
                            <div class="toggle-switch-text">
                                <strong>System Maintenance Mode</strong>
                                <span>Temporarily disable customer frontend.</span>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="system_maintenance" name="system_maintenance" value="1">
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div
                            style="text-align: right; margin-top: 20px; border-top: 1px solid #f3f4f6; padding-top: 20px;">
                            <button type="button" class="btn-save" onclick="saveSettings('form-notif')">Update Alert
                                Rules</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../JAVA SCRIPT/settings.js"></script>
</body>

</html>