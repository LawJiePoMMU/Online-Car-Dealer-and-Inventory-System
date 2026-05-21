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
                echo json_encode(['success' => true, 'message' => 'Settings updated!']);
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
                        if (!is_dir($uploadDir))
                            mkdir($uploadDir, 0777, true);
                        $fileName = time() . '_' . basename($_FILES['profile_picture']['name']);
                        $targetPath = $uploadDir . $fileName;
                        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
                            mysqli_query($conn, "UPDATE users SET user_avatar='$targetPath' WHERE user_id=$admin_id");
                        }
                    }
                    mysqli_commit($conn);
                    echo json_encode(['success' => true, 'message' => 'Profile updated!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error.']);
                }
                exit;
            }

            if ($action === 'upload_logo') {
                if (!isset($_FILES['logo_file']) || $_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'message' => 'Upload failed.']);
                    exit;
                }
                $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, WEBP allowed.']);
                    exit;
                }
                $uploadDir = '../../Images/Uploads/';
                if (!is_dir($uploadDir))
                    mkdir($uploadDir, 0777, true);
                $filename = 'company_logo.' . $ext;
                $targetPath = $uploadDir . $filename;
                move_uploaded_file($_FILES['logo_file']['tmp_name'], $targetPath);
                $web_path = '../../Images/Uploads/' . $filename;
                $stmt = mysqli_prepare($conn, "INSERT INTO system_settings (setting_key, setting_value) VALUES ('company_logo', ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
                mysqli_stmt_bind_param($stmt, "s", $web_path);
                mysqli_stmt_execute($stmt);
                echo json_encode(['success' => true, 'path' => $web_path]);
                exit;
            }

            if ($action === 'upload_banner') {
                $display_order = intval($_POST['display_order']);
                $is_active = mysqli_real_escape_string($conn, $_POST['is_active']);

                $check_order = mysqli_query($conn, "SELECT Banner_ID FROM homepage_banners WHERE Display_Order = $display_order");
                if (mysqli_num_rows($check_order) > 0) {
                    echo json_encode(['success' => false, 'message' => "Display Order '$display_order' is already in use."]);
                    exit;
                }

                if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                    $file_name = time() . '_' . basename($_FILES['banner_image']['name']);
                    $uploadDir = '../../Images/Uploads/Banners/';

                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $target_file = $uploadDir . $file_name;
                    $ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];

                    if (in_array($ext, $allowed_extensions)) {
                        if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $target_file)) {
                            $sql = "INSERT INTO homepage_banners (Image_Path, Display_Order, Is_Active) VALUES ('$file_name', '$display_order', '$is_active')";
                            mysqli_query($conn, $sql);
                            echo json_encode(['success' => true, 'message' => 'Banner uploaded successfully!']);
                            exit;
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Invalid file type! Only JPG, PNG, and WEBP are allowed.']);
                        exit;
                    }
                }
                echo json_encode(['success' => false, 'message' => 'Please select a valid image file.']);
                exit;
            }
            if ($action === 'delete_banner') {
                $id = intval($_POST['banner_id']);
                $img_sql = mysqli_query($conn, "SELECT Image_Path FROM homepage_banners WHERE Banner_ID = $id");

                if ($row = mysqli_fetch_assoc($img_sql)) {
                    $file_path = "../../Images/Uploads/Banners/" . $row['Image_Path'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }

                mysqli_query($conn, "DELETE FROM homepage_banners WHERE Banner_ID = $id");
                echo json_encode(['success' => true, 'message' => 'Banner deleted successfully.']);
                exit;
            }
            if ($action === 'toggle_banner_status') {
                $id = intval($_POST['banner_id']);
                $current_status = mysqli_real_escape_string($conn, $_POST['current_status']);
                $new_status = ($current_status === 'Yes') ? 'No' : 'Yes';

                $update_sql = "UPDATE homepage_banners SET Is_Active = '$new_status' WHERE Banner_ID = $id";
                if (mysqli_query($conn, $update_sql)) {
                    echo json_encode(['success' => true, 'message' => 'Status updated!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
                }
                exit;
            }
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
$sql_banners = mysqli_query($conn, "SELECT * FROM homepage_banners ORDER BY Display_Order ASC, Created_At DESC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>System Settings</title>
    <link rel="stylesheet" href="/Online-Car-Dealer-and-Inventory-System/CSS/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>
<style>
    .settings-layout {
        display: flex;
        gap: 24px;
        margin-top: 24px;
        align-items: flex-start;
    }

    .settings-sidebar {
        width: 240px;
        flex-shrink: 0;
        background: #ffffff;
        border-radius: 18px;
        padding: 16px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03), 0 8px 24px rgba(0, 0, 0, 0.04);
        border: 1px solid #f1f5f9;
    }

    .settings-tab {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        color: #4b5563;
        font-size: 14px;
        font-weight: 600;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s ease;
        margin-bottom: 4px;
        border: none;
        background: transparent;
        width: 100%;
        text-align: left;
    }

    .settings-tab:hover {
        background: #f8fafc;
        color: #2563eb;
        transform: translateX(4px);
    }

    .settings-tab.active {
        background: #eff6ff;
        color: #2563eb;
        font-weight: 700;
    }

    .settings-tab.active i {
        color: #2563eb;
    }

    .settings-content {
        flex: 1;
        max-width: 900px;
    }

    .settings-card {
        background: #ffffff;
        border-radius: 18px;
        padding: 32px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03), 0 8px 24px rgba(0, 0, 0, 0.04);
        border: 1px solid #f1f5f9;
        display: none;
    }

    .settings-card.active {
        display: block;
        animation: fadeIn 0.3s ease forwards;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .section-header {
        border-bottom: 1px solid #f1f5f9;
        margin-bottom: 24px;
        padding-bottom: 16px;
    }

    .section-header h3 {
        margin: 0;
        font-size: 18px;
        color: #111827;
        font-weight: 700;
        display: flex;
        align-items: center;
    }

    .setting-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-bottom: 6px;
    }

    .setting-group {
        margin-bottom: 20px;
    }

    .setting-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
    }

    .setting-group input[type="text"],
    .setting-group input[type="email"],
    .setting-group input[type="number"],
    .setting-group input[type="password"],
    .setting-group select {
        width: 100%;
        height: 46px;
        padding: 0 14px;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        font-size: 14px;
        background: #ffffff;
        transition: all 0.2s ease;
        box-sizing: border-box;
        color: #111827;
    }

    .setting-group input:focus,
    .setting-group select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
        outline: none;
    }

    .helper-text {
        font-size: 12px;
        color: #6b7280;
        margin-top: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .btn-save {
        height: 44px;
        padding: 0 24px;
        border-radius: 10px;
        background: #2563eb;
        color: white;
        font-size: 14px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-save:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(37, 99, 235, 0.18);
        background: #1d4ed8;
    }

    .profile-header-layout {
        display: flex;
        gap: 32px;
        align-items: center;
        margin-bottom: 32px;
    }

    .profile-avatar-section {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        width: 120px;
        flex-shrink: 0;
    }

    .avatar-circle {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        background: #eff6ff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 36px;
        color: #2563eb;
        border: 3px solid #bfdbfe;
        overflow: hidden;
        position: relative;
        transition: all 0.2s ease;
    }

    .avatar-circle:hover {
        transform: scale(1.05);
        border-color: #2563eb;
    }

    .role-badge {
        background: #2563eb;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    .profile-info-section {
        flex: 1;
    }

    .security-section {
        background: #fffaf0;
        border: 1px solid #ffedd5;
        padding: 24px;
        border-radius: 16px;
        margin-top: 24px;
        border-left: 4px solid #f97316;
    }

    .security-section .sub-heading {
        color: #9a3412;
        margin: 0 0 16px;
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
        padding: 16px 20px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        margin-bottom: 12px;
        transition: all 0.2s ease;
    }

    .toggle-switch:hover {
        border-color: #cbd5e1;
        background: #f1f5f9;
    }

    .toggle-switch-text strong {
        display: block;
        font-size: 14px;
        color: #111827;
        margin-bottom: 4px;
    }

    .toggle-switch-text span {
        font-size: 12px;
        color: #64748b;
    }

    .switch {
        position: relative;
        display: inline-block;
        width: 48px;
        height: 26px;
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
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
    }

    input:checked+.slider {
        background-color: #2563eb;
    }

    input:checked+.slider:before {
        transform: translateX(22px);
    }

    .upload-section {
        margin-bottom: 32px;
    }

    .upload-drop-zone {
        border: 2px dashed #cbd5e1;
        border-radius: 16px;
        padding: 40px 20px;
        text-align: center;
        background: #f8fafc;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-bottom: 24px;
    }

    .upload-drop-zone:hover,
    .upload-drop-zone.dragover {
        border-color: #2563eb;
        background: #eff6ff;
    }

    .upload-icon {
        font-size: 42px;
        color: #94a3b8;
        margin-bottom: 12px;
        display: block;
        transition: 0.3s;
    }

    .upload-drop-zone:hover .upload-icon {
        color: #2563eb;
        transform: translateY(-5px);
    }

    .upload-text {
        font-size: 15px;
        color: #334155;
        margin-bottom: 6px;
        font-weight: 600;
    }

    .upload-subtext {
        color: #64748b;
        font-size: 13px;
    }

    .upload-controls-grid {
        display: grid;
        grid-template-columns: 1fr 1fr auto;
        gap: 20px;
        align-items: end;
    }

    @media (max-width: 900px) {
        .settings-layout {
            flex-direction: column;
        }

        .settings-sidebar {
            width: 100%;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 12px;
        }

        .settings-tab {
            width: auto;
            flex: 1;
            min-width: 150px;
            justify-content: center;
            margin-bottom: 0;
        }

        .setting-row,
        .upload-controls-grid {
            grid-template-columns: 1fr;
        }
    }

    .upload-drop-zone input[type="file"],
    #file-input {
        display: none !important;
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background: #ffffff;
    }

    .admin-table th,
    .admin-table td {
        padding: 16px;
        text-align: left;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .banner-preview {
        max-width: 120px;
        max-height: 80px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }

    .badge:hover {
        transform: scale(1.05);
    }

    .badge-active {
        background: #dcfce7;
        color: #166534;
    }

    .badge-hidden {
        background: #f1f5f9;
        color: #475569;
    }

    .btn-action {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
        background: #ffffff;
    }

    .btn-delete {
        color: #ef4444;
        border: 1px solid #fca5a5;
        background: #fef2f2;
    }

    .btn-delete:hover {
        background: #ef4444;
        color: #ffffff;
        border-color: #ef4444;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(239, 68, 68, 0.2);
    }
</style>

<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar" style="margin-bottom: 24px;">
            <h1 style="font-size: 24px; font-weight: 800; color: #111827;">Settings</h1>
        </header>

        <div class="settings-layout">

            <div class="settings-sidebar">
                <div class="settings-tab active" data-target="tab-profile">
                    <i class="fas fa-user-circle" style="width:16px;"></i> My Profile
                </div>
                <div class="settings-tab" data-target="tab-business">
                    <i class="fas fa-building" style="width:16px;"></i> Business Profile
                </div>
                <div class="settings-tab" data-target="tab-banners">
                    <i class="fas fa-images" style="width:16px;"></i> Manage Banners
                </div>
                <div class="settings-tab" data-target="tab-system">
                    <i class="fas fa-calculator" style="width:16px;"></i> System Variables
                </div>
                <div class="settings-tab" data-target="tab-notif">
                    <i class="fas fa-bell" style="width:16px;"></i> Notifications
                </div>
            </div>

            <div class="settings-content">

                <div class="settings-card active" id="tab-profile">
                    <div class="section-header">
                        <h3><i class="fas fa-user-circle" style="color:#1e3a8a;margin-right:8px;"></i>My Profile</h3>
                    </div>
                    <form id="form-profile">
                        <div class="profile-header-layout">
                            <div class="profile-avatar-section">
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/*"
                                    style="display:none;" onchange="previewAvatar(event)">
                                <div class="avatar-circle" onclick="document.getElementById('profile_picture').click();"
                                    style="cursor:pointer;">
                                    <img id="avatar_preview_img" src=""
                                        style="width:100%;height:100%;object-fit:cover;display:none;">
                                    <i class="fas fa-user-tie" id="avatar_icon"></i>
                                </div>
                                <span class="role-badge">SYSTEM ADMIN</span>
                                <div class="helper-text" style="cursor:pointer;"
                                    onclick="document.getElementById('profile_picture').click();">
                                    <i class="fas fa-camera"></i> Click to Change
                                </div>
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
                            <div class="setting-group" style="max-width:48%; margin-bottom:0;">
                                <label>New Password</label>
                                <input type="password" name="new_password"
                                    placeholder="Leave blank to keep current password">
                                <div class="helper-text"><i class="fas fa-info-circle"></i> Password will be encrypted
                                    securely.</div>
                            </div>
                        </div>

                        <div style="text-align:right; margin-top:24px;">
                            <button type="button" class="btn-save" onclick="saveProfile('form-profile')">
                                <i class="fas fa-save" style="margin-right:6px;"></i>Save Profile
                            </button>
                        </div>
                    </form>
                </div>

                <div class="settings-card" id="tab-business">
                    <div class="section-header">
                        <h3><i class="fas fa-building" style="color:#1e3a8a;margin-right:8px;"></i>Company Information
                        </h3>
                    </div>
                    <form id="form-business">
                        <div class="setting-group">
                            <label>Company Logo</label>
                            <div style="display:flex; align-items:center; gap:20px; margin-bottom:8px;">
                                <input type="file" id="company_logo_file" accept="image/*" style="display:none;"
                                    onchange="previewLogo(event)">
                                <div onclick="document.getElementById('company_logo_file').click();"
                                    class="upload-logo-zone"
                                    style="width:120px; height:90px; border-radius:12px; border:2px dashed #d1d5db; background:#f9fafb; display:flex; align-items:center; justify-content:center; overflow:hidden; cursor:pointer; transition:0.2s; flex-shrink:0;">
                                    <img id="logoPreview" src=""
                                        style="width:100%; height:100%; object-fit:contain; display:none; border-radius:10px;">
                                    <div id="logoPlaceholder" style="text-align:center; color:#9ca3af;">
                                        <i class="fas fa-image"
                                            style="font-size:28px; display:block; margin-bottom:6px;"></i>
                                        <span style="font-size:11px; font-weight:600;">Click to Upload</span>
                                    </div>
                                </div>
                                <div>
                                    <div style="font-size:13px; font-weight:600; color:#374151; margin-bottom:4px;">
                                        Company Logo</div>
                                    <div style="font-size:12px; color:#6b7280; line-height:1.6;">
                                        Recommended: PNG with transparent background<br>
                                        Size: 300 x 200px or larger
                                    </div>
                                    <button type="button"
                                        onclick="document.getElementById('company_logo_file').click();"
                                        style="margin-top:10px; background:#eff6ff; color:#1e3a8a; border:none; padding:7px 14px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer;">
                                        <i class="fas fa-upload" style="margin-right:4px;"></i> Change Logo
                                    </button>
                                </div>
                            </div>
                            <div class="helper-text"><i class="fas fa-info-circle"></i> Logo will appear on printed
                                invoices.</div>
                        </div>
                        <div class="setting-group">
                            <label>Company Display Name</label>
                            <input type="text" id="company_name" name="company_name"
                                placeholder="e.g. My Auto Dealership Sdn Bhd">
                        </div>
                        <div class="setting-row">
                            <div class="setting-group">
                                <label>Registration Number (SSM)</label>
                                <input type="text" id="company_ssm" name="company_ssm" placeholder="e.g. 202301012345"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                <div class="helper-text"><i class="fas fa-info-circle"></i> Company-level registration,
                                    one number only.</div>
                            </div>
                            <div class="setting-group">
                                <label>SST Registration Number</label>
                                <input type="text" id="company_sst" name="company_sst"
                                    placeholder="e.g. W10-2301-12345678">
                            </div>
                        </div>
                        <div class="setting-row">
                            <div class="setting-group">
                                <label>Official Email</label>
                                <input type="email" id="contact_email" name="contact_email"
                                    placeholder="e.g. contact@mydealer.com">
                            </div>
                            <div class="setting-group">
                                <label>Sales Hotline / WhatsApp</label>
                                <input type="text" id="contact_phone" name="contact_phone" placeholder="012-3456789"
                                    maxlength="12"
                                    oninput="this.value=this.value.replace(/\D/g,'').substring(0,11).replace(/^(\d{3})(\d+)/,'$1-$2')">
                            </div>
                        </div>
                        <div class="setting-group">
                            <label>Company Address</label>
                            <input type="text" id="company_address" name="company_address"
                                placeholder="e.g. No. 123, Jalan ABC, 47810 Petaling Jaya, Selangor">
                        </div>
                        <div style="text-align:right; margin-top:20px; border-top:1px solid #f3f4f6; padding-top:20px;">
                            <button type="button" class="btn-save" onclick="saveSettings('form-business')">
                                <i class="fas fa-save" style="margin-right:6px;"></i>Update Business
                            </button>
                        </div>
                    </form>
                </div>

                <div class="settings-card" id="tab-banners">
                    <div class="section-header">
                        <h3><i class="fas fa-images" style="color:#1e3a8a;margin-right:8px;"></i>Manage Homepage Banners
                        </h3>
                    </div>

                    <div class="upload-section">
                        <form id="form-banner" enctype="multipart/form-data">
                            <div class="upload-drop-zone" id="drop-zone">
                                <input type="file" name="banner_image" id="file-input" accept=".jpg,.jpeg,.png,.webp"
                                    required>
                                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                <p class="upload-text" id="file-name-display">Drag and drop the banner image here, or
                                    click to
                                    select <span style="color: #1e3a8a; text-decoration: underline;">Browse</span></p>
                                <p class="upload-subtext">Recommended Format: 16:9 aspect ratio (JPG, PNG, WEBP)</p>
                            </div>

                            <div class="upload-controls-grid">
                                <div class="setting-group">
                                    <label>Display Order (1 is first)</label>
                                    <input type="number" id="display_order" name="display_order" min="1" max="100"
                                        required placeholder="Example: 1">
                                </div>
                                <div class="setting-group">
                                    <label>Visibility Status</label>
                                    <select name="is_active" id="visibility_status"
                                        style="width: 100%; padding: 11px 13px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; background: #f9fafb;">
                                        <option value="Yes">Active</option>
                                        <option value="No">Inactive</option>
                                    </select>
                                </div>
                                <div class="setting-group" style="display: flex; align-items: flex-end;">
                                    <button type="button" class="btn-save" onclick="submitBannerForm()">
                                        <i class="fas fa-cloud-upload-alt" style="margin-right:6px;"></i> Upload Banner
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Preview Image</th>
                                    <th>Order</th>
                                    <th>Status</th>
                                    <th>Upload Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($sql_banners && mysqli_num_rows($sql_banners) > 0) {
                                    while ($row = mysqli_fetch_assoc($sql_banners)) {
                                        echo "<tr>";
                                        echo "<td><img src='/Online-Car-Dealer-and-Inventory-System/Images/Uploads/Banners/" . htmlspecialchars($row['Image_Path']) . "' class='banner-preview' alt='Banner'></td>";
                                        echo "<td><span style='font-weight: 700; color: #1e3a8a;'>" . $row['Display_Order'] . "</span></td>";
                                        if ($row['Is_Active'] == 'Yes') {
                                            echo "<td><button type='button' class='badge badge-active' onclick='toggleBannerStatus(" . $row['Banner_ID'] . ", \"Yes\")'><i class='fas fa-eye'></i> Active</button></td>";
                                        } else {
                                            echo "<td><button type='button' class='badge badge-hidden' onclick='toggleBannerStatus(" . $row['Banner_ID'] . ", \"No\")'><i class='fas fa-eye-slash'></i> Inactive</button></td>";
                                        }
                                        $date = date("M j, Y, g:i a", strtotime($row['Created_At']));
                                        echo "<td><small class='text-muted'>" . $date . "</small></td>";
                                        echo "<td><button class='btn-action btn-delete' onclick='deleteBanner(" . $row['Banner_ID'] . ")'><i class='fas fa-trash-alt'></i> Delete</button></td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' style='text-align:center; padding:40px; color: #64748b;'>
                                              <i class='fas fa-images' style='font-size: 2rem; opacity:0.3; display:block; margin-bottom:10px;'></i>
                                              No banners found. Upload your first banner above.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="settings-card" id="tab-system">
                    <div class="section-header">
                        <h3><i class="fas fa-calculator" style="color:#1e3a8a;margin-right:8px;"></i>Financial
                            Calculation Defaults</h3>
                    </div>
                    <form id="form-system">
                        <div class="setting-row">
                            <div class="setting-group">
                                <label>Default Down Payment (%)</label>
                                <input type="number" step="0.1" min="0"
                                    onkeydown="if(['-','e'].includes(event.key)) event.preventDefault();"
                                    id="default_dp_percent" name="default_dp_percent">
                                <div class="helper-text"><i class="fas fa-info-circle"></i> Standard DP rate applied to
                                    new bookings.</div>
                            </div>
                            <div class="setting-group">
                                <label>Interest Rate (% p.a.)</label>
                                <input type="number" step="0.01" min="0"
                                    onkeydown="if(['-','e'].includes(event.key)) event.preventDefault();"
                                    id="default_loan_rate" name="default_loan_rate">
                                <div class="helper-text"><i class="fas fa-percentage"></i> Default bank interest rate
                                    for loan calculations.</div>
                            </div>
                        </div>
                        <div style="text-align:right; margin-top:20px; border-top:1px solid #f3f4f6; padding-top:20px;">
                            <button type="button" class="btn-save" onclick="saveSettings('form-system')">
                                <i class="fas fa-save" style="margin-right:6px;"></i>Save Finance Logic
                            </button>
                        </div>
                    </form>
                </div>

                <div class="settings-card" id="tab-notif">
                    <div class="section-header">
                        <h3><i class="fas fa-bell" style="color:#1e3a8a;margin-right:8px;"></i>System Alerts & Rules
                        </h3>
                    </div>
                    <form id="form-notif">
                        <div class="setting-group" style="max-width:48%; margin-bottom:24px;">
                            <label>Low Stock Warning Threshold</label>
                            <input type="number" min="0"
                                onkeydown="if(['-','e'].includes(event.key)) event.preventDefault();"
                                id="low_stock_threshold" name="low_stock_threshold">
                            <div class="helper-text"><i class="fas fa-exclamation-triangle"></i> Cars with stock at or
                                below this number will be highlighted.</div>
                        </div>

                        <div class="toggle-switch">
                            <div class="toggle-switch-text">
                                <strong>New Booking Alert</strong>
                                <span>Send notification when a new booking is created.</span>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="alert_new_booking" name="alert_new_booking" value="1">
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="toggle-switch">
                            <div class="toggle-switch-text">
                                <strong>New Reservation Alert</strong>
                                <span>Send notification when a new reservation is received.</span>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="alert_new_reservation" name="alert_new_reservation"
                                    value="1">
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="toggle-switch">
                            <div class="toggle-switch-text">
                                <strong>New Down Payment Alert</strong>
                                <span>Send notification when a new down payment is received.</span>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="alert_new_down_payment" name="alert_new_down_payment"
                                    value="1">
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div style="text-align:right; margin-top:20px; border-top:1px solid #f3f4f6; padding-top:20px;">
                            <button type="button" class="btn-save" onclick="saveSettings('form-notif')">
                                <i class="fas fa-save" style="margin-right:6px;"></i>Update Alert Rules
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../JAVA SCRIPT/settings.js?v=2"></script>
</body>

</html>