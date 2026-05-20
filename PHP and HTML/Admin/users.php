<?php
session_start();
include '../Config/database.php';
$isSuperAdmin = ($_SESSION['user_role'] === 'Super Admin');
if (isset($_GET['ajax']) && isset($_GET['toggle_id']) && isset($_GET['current_status'])) {
    if (!$isSuperAdmin)
        exit();
    $id = (int) $_GET['toggle_id'];
    $check_user = mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT user_role FROM users WHERE user_id = $id")
    );

    if ($check_user['user_role'] !== 'Super Admin') {

        $new_status = ($_GET['current_status'] == 'Active')
            ? 'Inactive'
            : 'Active';

        mysqli_query(
            $conn,
            "UPDATE users
         SET user_status = '$new_status'
         WHERE user_id = $id"
        );
    }
    exit();
}

if (isset($_POST['save_user'])) {

    if (!$isSuperAdmin) {
        exit('Access Denied');
    }
    $name = mysqli_real_escape_string($conn, $_POST['user_name']);
    $ic = mysqli_real_escape_string($conn, $_POST['user_ic']);
    $email = mysqli_real_escape_string($conn, $_POST['user_email']);
    $phone = mysqli_real_escape_string($conn, $_POST['user_phone']);
    $role = mysqli_real_escape_string($conn, $_POST['user_role']);
    $status = mysqli_real_escape_string($conn, $_POST['user_status']);
    $password_input = $_POST['user_password'];
    $avatar = isset($_POST['user_avatar']) ? mysqli_real_escape_string($conn, $_POST['user_avatar']) : '';
    $id = !empty($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $redirect_url = isset($_POST['current_url']) ? $_POST['current_url'] : 'users.php';

    $check_query = "SELECT * FROM users WHERE (user_name='$name' OR user_email='$email' OR user_phone='$phone' OR user_ic='$ic') AND user_id != $id";
    if (mysqli_num_rows(mysqli_query($conn, $check_query)) > 0) {
        $redirect_url = preg_replace('/(&|\?)success=1/', '', $redirect_url);
        $sep = (strpos($redirect_url, '?') !== false) ? '&' : '?';
        header("Location: " . $redirect_url . $sep . "error=duplicate");
        exit();
    }

    $avatar_path = "";
    if (isset($_FILES['user_avatar_file']) && $_FILES['user_avatar_file']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "../../uploads/avatars/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $target_dir = "../../uploads/avatars/";

        if (!is_dir($target_dir)) {
            if (!mkdir($target_dir, 0777, true)) {
                die("Error: Failed to create directory: " . $target_dir);
            }
        }

        $file_extension = strtolower(pathinfo($_FILES['user_avatar_file']['name'], PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($file_extension, $allowed_types)) {
            $new_filename = uniqid("avatar_") . "." . $file_extension;
            $target_file = $target_dir . $new_filename;

            if (move_uploaded_file($_FILES['user_avatar_file']['tmp_name'], $target_file)) {
                $avatar_path = $target_file;
            }
        } else {
            $redirect_url = preg_replace('/(&|\?)error=duplicate/', '', $redirect_url);
            $sep = (strpos($redirect_url, '?') !== false) ? '&' : '?';
            header("Location: " . $redirect_url . $sep . "error=filetype");
            exit();
        }
    }

    if ($id > 0) {
        if (!empty($password_input) && $password_input !== '********') {
            $pass = password_hash($password_input, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET user_status='$status', user_password='$pass' WHERE user_id=$id";
        } else {
            $sql = "UPDATE users SET user_status='$status' WHERE user_id=$id";
        }
    } else {
        $pass = password_hash($password_input, PASSWORD_DEFAULT);
        $avatar_val = !empty($avatar_path) ? "'$avatar_path'" : "NULL";
        $sql = "INSERT INTO users (user_name, user_ic, user_email, user_phone, user_password, user_role, user_status, user_avatar, user_created_at) 
        VALUES ('$name', '$ic', '$email', '$phone', '$pass', '$role', '$status', $avatar_val, NOW())";
    }
    mysqli_query($conn, $sql);

    $redirect_url = preg_replace('/(&|\?)error=duplicate/', '', $redirect_url);
    $redirect_url = preg_replace('/(&|\?)success=1/', '', $redirect_url);
    $sep = (strpos($redirect_url, '?') !== false) ? '&' : '?';
    header("Location: " . $redirect_url . $sep . "success=1");
    exit();
}

$count_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users"))['c'];
$count_admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE user_role IN ('Admin', 'Super Admin')"))['c'];
$count_cust = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE user_role = 'Customer'"))['c'];

$where = "WHERE 1=1";
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

if (!empty($search)) {
    $where .= " AND (user_name LIKE '%$search%' OR user_email LIKE '%$search%' OR user_ic LIKE '%$search%')";
}
if ($role_filter == 'admin') {
    $where .= " AND user_role IN ('Admin', 'Super Admin')";
}
elseif ($role_filter == 'customer') {
    $where .= " AND user_role = 'Customer'";
}
if ($status_filter != 'all') {
    $where .= " AND user_status = '$status_filter'";
}

$limit = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

$total_filtered_rows = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM users $where"))['t'];
$total_pages = ceil($total_filtered_rows / $limit);

$order_by = "ORDER BY CASE WHEN user_id = {$_SESSION['user_id']} THEN 0 ELSE 1 END, user_id ASC";

$users_result = mysqli_query($conn, "SELECT * FROM users $where $order_by LIMIT $limit OFFSET $offset");

$existing_users_query = mysqli_query($conn, "SELECT user_id, user_name, user_email, user_phone, user_ic FROM users");
$existing_users = [];
while ($r = mysqli_fetch_assoc($existing_users_query)) {
    $existing_users[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="/Online-Car-Dealer-and-Inventory-System/CSS/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>
<style>
    .table-card {
        background: #ffffff;
        border-radius: 18px;
        overflow: hidden;

        box-shadow:
            0 1px 2px rgba(0, 0, 0, 0.03),
            0 8px 24px rgba(0, 0, 0, 0.04);
    }

    .admin-table th {
        color: #374151;
        font-weight: 700;
        font-size: 12px;
        letter-spacing: 0.4px;
        text-transform: uppercase;
    }

    .admin-table tbody tr {
        transition: all 0.2s ease;
    }

    .admin-table tbody tr:hover {
        background-color: #f9fafb;
    }

    .admin-table td {
        vertical-align: middle;
    }

    .sidebar-menu a {
        gap: 12px;
        transition: all 0.2s ease;
    }

    .sidebar-menu a:hover {
        transform: translateX(2px);
    }

    .btn-add-blue {
        border-radius: 12px;
        padding: 12px 18px;

        font-weight: 600;
        font-size: 14px;

        transition: all 0.2s ease;
    }

    .btn-add-blue:hover {
        transform: translateY(-1px);

        box-shadow:
            0 10px 20px rgba(37, 99, 235, 0.15);
    }

    .form-control {
        border-radius: 12px;
        transition: all 0.2s ease;
    }

    .form-control:focus {
        border-color: #2563eb;
        box-shadow:
            0 0 0 4px rgba(37, 99, 235, 0.08);
    }

    .status-cell {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
    }

    .text-active {
        color: #16a34a;
    }

    .text-inactive {
        color: #dc2626;
    }

    .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }

    .dot-active {
        background: #10b981;
    }

    .dot-inactive {
        background: #ef4444;
    }

    .online-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-top: 6px;
        padding: 4px 8px;
        border-radius: 999px;
        background: #d1fae5;
        color: #10b981;
        font-size: 11px;
        font-weight: 600;
    }

    .avatar {
        transition: all 0.2s ease;
    }

    .avatar:hover {
        transform: scale(1.05);
    }

    .action-btn {
        transition: all 0.2s ease;
    }

    .action-btn:hover {
        transform: scale(1.12);
    }

    .pagination-container {
        padding-top: 24px;
    }

    .page-btn {
        border-radius: 10px;
        transition: all 0.2s ease;
    }

    .page-btn:hover {
        background: #f3f4f6;
    }

    .modal-content {
        border-radius: 18px;

        box-shadow:
            0 10px 30px rgba(0, 0, 0, 0.08);
    }

    .tab-item {
        transition: all 0.2s ease;
    }

    .tab-item:hover {
        color: #2563eb;
    }

    .page-title h1 {
        letter-spacing: -0.4px;
    }

    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    ::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 999px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #9ca3af;
    }


    .topbar {
        backdrop-filter: blur(10px);
    }

    body {
        background: #f8fafc;
    }

    .modal {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(6px);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        transition: all 0.25s ease;
        z-index: 9999;
    }

    .modal.active {
        opacity: 1;
        visibility: visible;
    }

    .modal-content {
        width: 100%;
        max-width: 520px;
        max-height: 88vh;
        overflow-y: auto;
        background: #ffffff;
        border-radius: 20px;
        padding: 24px 24px 20px;
        position: relative;
        box-shadow:
            0 20px 60px rgba(0, 0, 0, 0.18);
        transform: translateY(20px) scale(0.97);
        transition: all 0.2s ease;
    }

    .modal.active .modal-content {
        transform: translateY(0px) scale(1);
    }

    .modal-content h2 {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 22px;
        color: #111827;
    }

    .modal-close {
        position: absolute;
        top: 18px;
        right: 18px;
        width: 42px;
        height: 42px;
        border: none;
        border-radius: 12px;
        background: #f3f4f6;
        color: #6b7280;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .modal-close:hover {
        background: #e5e7eb;
        transform: rotate(90deg);
    }

    .form-group {
        margin-bottom: 18px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
    }

    .form-control {
        width: 100%;
        height: 46px;
        border-radius: 12px;
        border: 1px solid #d1d5db;
        padding: 0 14px;
        font-size: 14px;
        background: #ffffff;
        transition: all 0.2s ease;
    }

    .form-control:focus {
        border-color: #2563eb;
        box-shadow:
            0 0 0 3px rgba(37, 99, 235, 0.08);
        outline: none;
    }

    #togglePasswordIcon {
        position: absolute;
        right: 16px;
        top: 16px;
        color: #9ca3af;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    #togglePasswordIcon:hover {
        color: #2563eb;
    }

    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 14px;
        margin-top: 32px;
    }

    .btn-cancel {
        height: 44px;
        padding: 0 18px;
        border-radius: 10px;
        background: #f3f4f6;
        font-size: 14px;
    }

    .btn-cancel:hover {
        background: #e5e7eb;
    }

    .btn-save {
        height: 44px;
        padding: 0 22px;
        border-radius: 10px;
        background: #2563eb;
        font-size: 14px;
        font-weight: 600;
    }

    .btn-save:hover {
        transform: translateY(-1px);
        box-shadow:
            0 10px 20px rgba(37, 99, 235, 0.18);
    }

    .modal-content::-webkit-scrollbar {
        width: 8px;
    }

    .modal-content::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 999px;
    }

    input[type="file"] {
        padding: 10px !important;
        background: #f9fafb;
        border: 1px dashed #cbd5e1;
        cursor: pointer;
    }
</style>

<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">

        <header class="topbar" style="margin-bottom: 24px;">
            <div class="page-title">
                <h1 style="font-size: 24px; font-weight: 700; color: #111827;">User Management</h1>
            </div>
        </header>

        <div class="page-tabs">
            <a href="users.php" class="tab-item <?= $role_filter == 'all' ? 'active' : '' ?>">All Users
                (<?= $count_all ?>)</a>
            <a href="users.php?role=admin" class="tab-item <?= $role_filter == 'admin' ? 'active' : '' ?>">Admins
                (<?= $count_admin ?>)</a>
            <a href="users.php?role=customer"
                class="tab-item <?= $role_filter == 'customer' ? 'active' : '' ?>">Customers (<?= $count_cust ?>)</a>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <form method="GET" style="display: flex; gap: 16px;">
                <div style="position: relative;">
                    <i class="fas fa-search"
                        style="position: absolute; left: 14px; top: 11px; color: #9ca3af; font-size: 14px;"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search Name or IC..."
                        style="padding-left: 38px; width: 280px; font-size: 13px;"
                        value="<?= htmlspecialchars($search) ?>">
                </div>
                <select name="status" class="form-control" style="width: 150px; font-size: 13px;"
                    onchange="this.form.submit()">
                    <option value="all">All Status</option>
                    <option value="Active" <?= $status_filter == 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= $status_filter == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                <?php if (isset($_GET['role']))
                    echo '<input type="hidden" name="role" value="' . $_GET['role'] . '">'; ?>
            </form>
            <?php if ($isSuperAdmin): ?>
                <button onclick="openModal()" class="btn-add-blue">
                    <i class="fas fa-user-plus"></i> Add Admin
                </button>
            <?php endif; ?>
        </div>
        </div>

        <div class="table-card" style="padding: 0; border: none;">
            <table class="admin-table" id="userTable">
                <thead>
                    <tr>
                        <th style="text-align: left;">Role</th>
                        <th style="text-align: left;">User ID</th>
                        <th style="text-align: left;">Avatar</th>
                        <th style="text-align: left;">Full Name</th>
                        <th style="text-align: left;">IC Number</th>
                        <th style="text-align: left;">Phone Number</th>
                        <th style="text-align: left;">Email</th>
                        <th style="text-align: left;">Date Created</th>
                        <th style="width: 100px; text-align: left;">Status</th>
                        <?php if ($isSuperAdmin): ?>
                            <th style="width: 90px; text-align: center;" class="print-hide">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($users_result && $total_filtered_rows > 0) {
                        while ($row = mysqli_fetch_assoc($users_result)) {
                            $role = !empty($row['user_role']) ? $row['user_role'] : 'Customer';
                            $status = !empty($row['user_status']) ? $row['user_status'] : 'Active';
                            $dot_class = ($status == 'Active') ? 'dot-active' : 'dot-inactive';
                            $text_class = ($status == 'Active') ? 'text-active' : 'text-inactive';
                            $ic = !empty($row['user_ic']) ? htmlspecialchars($row['user_ic']) : '-';
                            $phone = !empty($row['user_phone']) ? htmlspecialchars($row['user_phone']) : '-';

                            $online_badge = '';
                            if ($row['user_id'] == $_SESSION['user_id']) {
                                $online_badge = "<br><span class='print-hide' style='font-size: 11px; color: #10b981; background: #d1fae5; padding: 2px 6px; border-radius: 10px; display: inline-block; margin-top: 4px;'><i class='fas fa-circle' style='font-size: 8px;'></i> Online</span>";
                            }

                            $avatar_content = '';
                            if (!empty($row['user_avatar'])) {
                                $avatar_url = htmlspecialchars($row['user_avatar']);
                                $avatar_content = "<img src='{$avatar_url}' alt='Avatar' style='width: 100%; height: 100%; object-fit: cover; border-radius: 50%; display: block;'>";
                            } else {
                                $name_parts = explode(' ', trim($row['user_name']));
                                $initials = '';
                                foreach ($name_parts as $part) {
                                    if (!empty($part)) {
                                        $initials .= strtoupper(mb_substr($part, 0, 1, 'UTF-8'));
                                    }
                                }
                                $avatar_content = mb_substr($initials, 0, 3, 'UTF-8');
                            }

                            echo "<tr class='data-row'>";
                            echo "<td style='text-align: left; color: #4b5563; font-weight: 500;'>" . htmlspecialchars($role) . "</td>";
                            echo "<td style='text-align: left; color: #6b7280;'>UA" . str_pad($row['user_id'], 3, '0', STR_PAD_LEFT) . "</td>";
                            echo "<td style='text-align: center;'>
                                    <div class='avatar' style='width: 42px; height: 42px; font-size: 18px; font-weight: 700; letter-spacing: 0.5px; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; border-radius: 50%; background-color: #e0e7ff; color: #6366f1; margin: 0 auto;'>" . $avatar_content . "</div>
                                </td>";
                            echo "<td style='text-align: left; font-weight: 500; color: #111827;'>" . htmlspecialchars($row['user_name']) . $online_badge . "</td>";
                            echo "<td style='text-align: left;'>" . $ic . "</td>";
                            echo "<td style='text-align: left;'>" . $phone . "</td>";
                            echo "<td style='text-align: left;'>" . htmlspecialchars($row['user_email']) . "</td>";
                            echo "<td style='text-align: left;'>" . date('d M Y', strtotime($row['user_created_at'])) . "</td>";
                            echo "<td style='text-align: left;'><div class='status-cell' style='width: 85px;'><span class='dot {$dot_class} print-hide'></span><span class='{$text_class}'>" . ucfirst($status) . "</span></div></td>";
                            if ($isSuperAdmin) {

                                echo "<td class='print-hide' style='text-align: center;'>";

                                if ($row['user_id'] == $_SESSION['user_id']) {

                                    echo "<div style='height:20px;'></div>";

                                } else {

                                    $toggle_icon = ($status == 'Active') ? 'fa-lock' : 'fa-unlock';
                                    $toggle_color = ($status == 'Active') ? '#ef4444' : '#10b981';
                                    $action_icon = ($role == 'Customer') ? 'fa-eye' : 'fa-pen';

                                    echo "
                                            <div style='display:flex; justify-content:center; gap:16px;'>
                                            <a href='javascript:void(0)'onclick='editUser(" . htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') . ")'style='color:#9ca3af;'>
                                                <i class='fas {$action_icon}'></i>
                                            </a>

                                             <a href='javascript:void(0)'onclick='toggleStatus({$row['user_id']}, \"{$status}\", this)'style='color:{$toggle_color};'>
                                                <i class='fas {$toggle_icon}'></i>
                                            </a>
                                            </div>";
                                }

                                echo "</td>";
                            }
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='10' style='text-align: center; padding: 48px 0; color: #6b7280; font-size: 14px;'>No users found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>

            <div class="pagination-container"
                style="padding: 20px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e5e7eb;">
                <div class="page-info" style="color: #6b7280; font-size: 13px;">
                    Showing Page <?= $page ?> of <?= ($total_pages > 0) ? $total_pages : 1 ?>
                </div>
                <?php if ($total_pages > 1): ?>
                    <div class="page-controls" style="display: flex; gap: 8px;">
                        <?php $base_url = "?search=" . urlencode($search) . "&role=" . urlencode($role_filter) . "&status=" . urlencode($status_filter); ?>
                        <a href="users.php<?= $base_url ?>&page=<?= max(1, $page - 1) ?>" class="page-btn"
                            style="text-decoration:none;"><i class="fas fa-angle-left"></i> Prev</a>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="users.php<?= $base_url ?>&page=<?= $i ?>"
                                class="page-btn <?= ($page == $i) ? 'active' : '' ?>"
                                style="text-decoration:none;"><?= $i ?></a>
                        <?php endfor; ?>
                        <a href="users.php<?= $base_url ?>&page=<?= min($total_pages, $page + 1) ?>" class="page-btn"
                            style="text-decoration:none;">Next <i class="fas fa-angle-right"></i></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div id="userModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle" style="font-size: 20px; margin-bottom: 20px;">Add New Admin</h2>
            <form method="POST" id="userForm" enctype="multipart/form-data">
                <input type="hidden" name="user_id" id="form_user_id">
                <input type="hidden" name="current_url" id="current_url"
                    value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">


                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="user_name" id="form_user_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:8px;">
                        <label style="margin:0;">IC Number <medium style="color:#9ca3af;">(e.g., 123456-78-9101)
                            </medium></label>
                    </div>
                    <input type="text" name="user_ic" id="form_user_ic" class="form-control" required>
                </div>

                <div class="form-group">
                    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:8px;">
                        <label style="margin:0;">Email Address <medium style="color:#9ca3af;">(@gmail.com)</medium>
                        </label>
                    </div>
                    <input type="text" name="user_email" id="form_user_email" class="form-control" required>
                </div>

                <div class="form-group">
                    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:8px;">
                        <label style="margin:0;">Phone Number <medium style="color:#9ca3af;">(e.g., 012-3456789)
                            </medium></label>
                    </div>
                    <input type="text" name="user_phone" id="form_user_phone" class="form-control" required>
                </div>

                <div class="form-group" id="password_group">
                    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:8px;">
                        <label style="margin:0;">Password <medium style="color:#9ca3af;">(Leave
                                blank to keep current)</medium></label>
                    </div>
                    <div style="position: relative;">
                        <input type="password" name="user_password" id="form_user_password" class="form-control">
                        <i class="fas fa-eye" id="togglePasswordIcon"
                            style="position: absolute; right: 14px; top: 12px; cursor: pointer; color: #9ca3af;"></i>
                    </div>
                </div>

                <div class="form-group" id="avatar_group">
                    <label>Upload Avatar <medium style="color:#9ca3af;">(JPG, PNG, GIF)</medium></label>
                    <input type="file" name="user_avatar_file" id="form_user_avatar" class="form-control"
                        accept="image/jpeg, image/png, image/gif" style="padding: 7px 14px;">
                </div>

                <div style="display:flex; gap: 16px;">
                    <div class="form-group" style="flex:1;">
                        <label>System Role</label>
                        <input type="text" name="user_role" id="form_user_role" class="form-control" value="Admin"
                            readonly style="background-color: #f3f4f6; color: #6b7280; cursor: default;">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Account Status</label>
                        <select name="user_status" id="form_user_status" class="form-control">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                    <button type="button" id="btnCancel" class="btn-export">Cancel</button>
                    <button type="submit" name="save_user" class="btn-add-blue" style="border:none;">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const existingUsers = <?php echo json_encode($existing_users); ?>;
    </script>
    <script src="../../JAVA SCRIPT/users.js?v=<?php echo time(); ?>"></script>
</body>

</html>