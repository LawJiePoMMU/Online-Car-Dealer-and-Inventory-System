<?php
session_start();
include '../database.php';

if (isset($_GET['toggle_id']) && isset($_GET['current_status'])) {
    $id = (int) $_GET['toggle_id'];
    $new_status = ($_GET['current_status'] == 'Active') ? 'Inactive' : 'Active';
    mysqli_query($conn, "UPDATE users SET user_status = '$new_status' WHERE user_id = $id");
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

if (isset($_POST['save_user'])) {
    $name = mysqli_real_escape_string($conn, $_POST['user_name']);
    $email = mysqli_real_escape_string($conn, $_POST['user_email']);
    $phone = mysqli_real_escape_string($conn, $_POST['user_phone']);
    $role = $_POST['user_role'];
    $status = $_POST['user_status'];
    $password_input = $_POST['user_password'];
    $id = !empty($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

    $check_query = "SELECT * FROM users WHERE (user_name='$name' OR user_email='$email' OR user_phone='$phone') AND user_id != $id";
    if (mysqli_num_rows(mysqli_query($conn, $check_query)) > 0) {
        header("Location: users.php?error=duplicate");
        exit();
    }

    if ($id > 0) {
        if (!empty($password_input)) {
            $pass = password_hash($password_input, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET user_name='$name', user_email='$email', user_phone='$phone', user_role='$role', user_status='$status', user_password='$pass' WHERE user_id=$id";
        } else {
            $sql = "UPDATE users SET user_name='$name', user_email='$email', user_phone='$phone', user_role='$role', user_status='$status' WHERE user_id=$id";
        }
    } else {
        $pass = password_hash($password_input, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (user_name, user_email, user_phone, user_password, user_role, user_status, user_created_at) 
                VALUES ('$name', '$email', '$phone', '$pass', '$role', '$status', NOW())";
    }
    mysqli_query($conn, $sql);
    header("Location: users.php");
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
    $where .= " AND (user_name LIKE '%$search%' OR user_email LIKE '%$search%')";
}
if ($role_filter == 'admin') {
    $where .= " AND user_role = 'Admin'";
} elseif ($role_filter == 'customer') {
    $where .= " AND user_role = 'Customer'";
}
if ($status_filter != 'all') {
    $where .= " AND user_status = '$status_filter'";
}

$limit = 12;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

$total_filtered_rows = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM users $where"))['t'];
$total_pages = ceil($total_filtered_rows / $limit);

$order_by = "ORDER BY FIELD(user_role, 'Admin', 'Customer'), user_created_at DESC";
$users_result = mysqli_query($conn, "SELECT * FROM users $where $order_by LIMIT $limit OFFSET $offset");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="../../CSS/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        @media print {
            body {
                background: white !important;
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
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .table-card {
                border: none !important;
                box-shadow: none !important;
            }

            tr.no-print-row {
                display: none !important;
            }
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <?php if (isset($_GET['error']) && $_GET['error'] == 'duplicate'): ?>
            <div
                style="background-color: #fee2e2; color: #991b1b; padding: 16px; border-radius: 8px; margin-bottom: 24px; border-left: 4px solid #ef4444;">
                <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>
                <strong>Error:</strong> The User Name, Email, or Phone Number already exists. Please use unique details.
            </div>
        <?php endif; ?>
        <header class="topbar" style="margin-bottom: 24px;">
            <?php
            $current_admin_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Law Jie Po';
            $avatar_letter = strtoupper(substr($current_admin_name, 0, 1));
            ?>
            <div class="page-title">
                <h1 style="font-size: 24px; font-weight: 700; color: #111827;">User Management</h1>
            </div>
            <div class="user-profile">
                <span
                    style="font-size: 14px; font-weight: 500; color: #1f1f1f;"><?php echo htmlspecialchars($current_admin_name); ?></span>
                <div class="avatar" style="background-color: #e0e7ff; color: #3730a3;"><?php echo $avatar_letter; ?>
                </div>
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
                    <input type="text" name="search" class="form-control" placeholder="Search Username..."
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
                        <th class="print-hide" style="width: 40px; text-align: center; padding-left: 20px;">
                            <input type="checkbox" id="selectAll">
                        </th>
                        <th>Role</th>
                        <th>User ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone Number</th>
                        <th>Date Created</th>
                        <th style="width: 120px;">Status</th>
                        <th style="width: 100px; text-align: center;" class="print-hide">Action</th>
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

                            echo "<tr class='data-row'>";
                            echo "<td class='print-hide' style='text-align: center; padding-left: 20px;'><input type='checkbox' class='row-checkbox'></td>";
                            echo "<td style='color: #4b5563; font-weight: 500;'>" . htmlspecialchars($role) . "</td>";
                            echo "<td style='color: #6b7280;'>UA" . str_pad($row['user_id'], 3, '0', STR_PAD_LEFT) . "</td>";
                            echo "<td style='font-weight: 500; color: #111827;'>" . htmlspecialchars($row['user_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['user_email']) . "</td>";
                            echo "<td>" . (!empty($row['user_phone']) ? htmlspecialchars($row['user_phone']) : '-') . "</td>";
                            echo "<td>" . date('d M Y', strtotime($row['user_created_at'])) . "</td>";
                            echo "<td><div class='status-cell'><span class='dot {$dot_class}'></span><span class='{$text_class}'>" . ucfirst($status) . "</span></div></td>";

                            $toggle_icon = ($status == 'Active') ? 'fa-lock' : 'fa-unlock';
                            $toggle_color = ($status == 'Active') ? '#ef4444' : '#10b981';

                            echo "<td class='action-icons print-hide' style='text-align: center;'>
                                    <a href='javascript:void(0)' onclick='editUser(" . json_encode($row) . ")' style='color: #9ca3af; margin-right:16px;'><i class='fas fa-pen'></i></a>
                                    <a href='users.php?toggle_id={$row['user_id']}&current_status={$status}' style='color: {$toggle_color};'><i class='fas {$toggle_icon}'></i></a>
                                  </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='9' style='text-align: center; padding: 48px 0; color: #6b7280; font-size: 14px;'>No users found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            <div class="pagination-container"
                style="padding: 20px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e5e7eb;">
                <div class="page-info" style="color: #6b7280; font-size: 13px;">
                    Showing Page <?= $page ?> of <?= ($total_pages > 0) ? $total_pages : 1 ?> (Total
                    <?= $total_filtered_rows ?> records)
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
            <form method="POST">
                <input type="hidden" name="user_id" id="form_user_id">
                <div class="form-group"><label>Full Name</label><input type="text" name="user_name" id="form_user_name"
                        class="form-control" required></div>
                <div class="form-group">
                    <label>Email Address <small style="color:#ef4444;">(@gmail.com)</small></label>
                    <input type="email" name="user_email" id="form_user_email" class="form-control"
                        pattern="^[a-zA-Z0-9._%+-]+@gmail\.com$" required>
                </div>
                <div class="form-group">
                    <label>Phone Number <small style="color:#9ca3af;">(e.g., 012-3456789)</small></label>
                    <input type="text" name="user_phone" id="form_user_phone" class="form-control"
                        pattern="^01[0-9]-?[0-9]{7,8}$" required>
                </div>
                <div class="form-group" id="password_group">
                    <label>Password <small style="color:#9ca3af; font-weight:normal;">(Leave blank to keep
                            current)</small></label>
                    <div style="position: relative;">
                        <input type="password" name="user_password" id="form_user_password" class="form-control"
                            pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}">
                        <i class="fas fa-eye" id="togglePasswordIcon"
                            style="position: absolute; right: 14px; top: 12px; cursor: pointer; color: #9ca3af;"></i>
                    </div>
                </div>
                <div style="display:flex; gap: 16px;">
                    <div class="form-group" style="flex:1;">
                        <label>System Role</label>
                        <select name="user_role" id="form_user_role" class="form-control">
                            <option value="Admin">Admin</option>
                        </select>
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
                    <button type="submit" name="save_user" class="btn-add-blue">Save</button>
                </div>
            </form>
        </div>
    </div>
    <script src="../../JAVA SCRIPT/users.js"></script>
</body>

</html>