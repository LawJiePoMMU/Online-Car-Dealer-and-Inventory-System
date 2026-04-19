<?php
session_start();
include '../database.php';

$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'Admin';

if (isset($_GET['ajax']) && isset($_GET['toggle_id']) && isset($_GET['current_status'])) {
    if ($_SESSION['user_id'] != 1)
        exit();
    $id = (int) $_GET['toggle_id'];
    if ($id != 1) {
        $new_status = ($_GET['current_status'] == 'Active') ? 'Inactive' : 'Active';
        mysqli_query($conn, "UPDATE users SET user_status = '$new_status' WHERE user_id = $id");
    }
    exit();
}

if (isset($_POST['save_user'])) {
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
$count_admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE user_role = 'Admin'"))['c'];
$count_cust = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE user_role = 'Customer'"))['c'];

$where = "WHERE 1=1";
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

if (!empty($search)) {
    $where .= " AND (user_name LIKE '%$search%' OR user_email LIKE '%$search%' OR user_ic LIKE '%$search%')";
}
if ($role_filter == 'admin') {
    $where .= " AND user_role = 'Admin'";
} elseif ($role_filter == 'customer') {
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

$order_by = "ORDER BY CASE WHEN user_id = 1 THEN 0 ELSE 1 END, user_created_at DESC";

$users_result = mysqli_query($conn, "SELECT * FROM users $where $order_by LIMIT $limit OFFSET $offset");

$all_users_result = mysqli_query($conn, "SELECT * FROM users $where $order_by");
$all_users = [];
if ($all_users_result) {
    while ($row = mysqli_fetch_assoc($all_users_result)) {
        $all_users[] = $row;
    }
}

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
    <link rel="stylesheet" href="../../CSS/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        #printAllContainer {
            display: none;
        }

        @media print {
            body {
                background: white !important;
                margin: 0;
                padding: 0;
            }

            .sidebar,
            .topbar,
            .page-tabs,
            form,
            .btn-add-blue,
            .btn-export,
            .pagination-container,
            .print-hide {
                display: none !important;
            }

            .main-content {
                margin: 0 !important;
                padding: 20px !important;
                width: 100% !important;
            }

            .table-card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                width: 100% !important;
            }

            table {
                width: 100% !important;
                border-collapse: collapse !important;
                border: 2px solid #000 !important;
            }

            th,
            td {
                border: 1px solid #000 !important;
                padding: 10px 8px !important;
                color: #000 !important;
                text-align: left !important;
                font-size: 13px !important;
            }

            th {
                background-color: #f3f4f6 !important;
                -webkit-print-color-adjust: exact;
                font-weight: bold !important;
                text-transform: uppercase;
            }

            .badge,
            .dot,
            .status-cell span {
                color: #000 !important;
                background: none !important;
            }

            tr.no-print-row {
                display: none !important;
            }

            body.print-all-mode #userTable {
                display: none !important;
            }

            body.print-all-mode #printAllContainer {
                display: block !important;
            }
        }
    </style>
</head>

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
            <div style="display: flex; gap: 12px;">
                <button type="button" class="btn-export" onclick="printSelected()"><i class="fas fa-print"></i> Print
                    List</button>
                <button onclick="openModal()" class="btn-add-blue"><i class="fas fa-user-plus"></i> Add Admin</button>
            </div>
        </div>

        <div class="table-card" style="padding: 0; border: none;">
            <table class="admin-table" id="userTable">
                <thead>
                    <tr>
                        <th class="print-hide" style="width: 40px; padding-left: 20px;"><input type="checkbox"
                                id="selectAll" style="cursor: pointer;"></th>
                        <th style="text-align: left;">Role</th>
                        <th style="text-align: left;">User ID</th>
                        <th style="text-align: left;">Avatar</th>
                        <th style="text-align: left;">Full Name</th>
                        <th style="text-align: left;">IC Number</th>
                        <th style="text-align: left;">Phone Number</th>
                        <th style="text-align: left;">Email</th>
                        <th style="text-align: left;">Date Created</th>
                        <th style="width: 100px; text-align: left;">Status</th>
                        <th style="width: 90px; text-align: center;" class="print-hide">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($users_result && $total_filtered_rows > 0) {
                        while ($row = mysqli_fetch_assoc($users_result)) {
                            $role = !empty($row['user_role']) ? $row['user_role'] : 'Customer';
                            if ($row['user_id'] == 1) {
                                $role = 'Super Admin';
                            }

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
                            echo "<td class='print-hide' style='padding-left: 20px;'><input type='checkbox' class='row-checkbox' style='cursor: pointer;'></td>";
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

                            echo "<td class='print-hide' style='text-align: center;'>";
                            if ($_SESSION['user_id'] == 1) {
                                if ($row['user_id'] == 1) {
                                    echo "<div style='color: #9ca3af;'>-</div>";
                                } else {
                                    $toggle_icon = ($status == 'Active') ? 'fa-lock' : 'fa-unlock';
                                    $toggle_color = ($status == 'Active') ? '#ef4444' : '#10b981';
                                    $action_icon = ($role == 'Customer') ? 'fa-eye' : 'fa-pen';
                                    echo "<div style='display: flex; justify-content: center; gap: 16px;'>
                                            <a href='javascript:void(0)' onclick='editUser(" . htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') . ")' style='color: #9ca3af;'><i class='fas {$action_icon}'></i></a>
                                            <a href='javascript:void(0)' onclick='toggleStatus({$row['user_id']}, \"{$status}\", this)' style='color: {$toggle_color};'><i class='fas {$toggle_icon}'></i></a>
                                          </div>";
                                }
                            } else {
                                echo "<div style='color: #9ca3af;'>-</div>";
                            }
                            echo "</td>";
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

        <div id="printAllContainer">
            <?php
            $chunks = array_chunk($all_users, 10);
            foreach ($chunks as $chunk) {
                echo '<table style="page-break-after: always; margin-bottom: 20px;">';
                echo '<thead><tr>
                        <th style="text-align: left;">Role</th>
                        <th style="text-align: left;">User ID</th>
                        <th style="text-align: left;">Full Name</th>
                        <th style="text-align: left;">IC Number</th>
                        <th style="text-align: left;">Phone Number</th>
                        <th style="text-align: left;">Email</th>
                        <th style="text-align: left;">Date Created</th>
                        <th style="width: 100px; text-align: left;">Status</th>
                      </tr></thead><tbody>';
                foreach ($chunk as $r) {
                    $p_role = ($r['user_id'] == 1) ? 'Super Admin' : (!empty($r['user_role']) ? $r['user_role'] : 'Customer');
                    $p_status = !empty($r['user_status']) ? $r['user_status'] : 'Active';
                    $p_ic = !empty($r['user_ic']) ? htmlspecialchars($r['user_ic']) : '-';
                    $p_phone = !empty($r['user_phone']) ? htmlspecialchars($r['user_phone']) : '-';

                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($p_role) . "</td>";
                    echo "<td>UA" . str_pad($r['user_id'], 3, '0', STR_PAD_LEFT) . "</td>";
                    echo "<td>" . htmlspecialchars($r['user_name']) . "</td>";
                    echo "<td>" . $p_ic . "</td>";
                    echo "<td>" . $p_phone . "</td>";
                    echo "<td>" . htmlspecialchars($r['user_email']) . "</td>";
                    echo "<td>" . date('d M Y', strtotime($r['user_created_at'])) . "</td>";
                    echo "<td>" . ucfirst($p_status) . "</td>";
                    echo "</tr>";
                }
                echo '</tbody></table>';
            }
            ?>
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