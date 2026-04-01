<?php
session_start();
include '../database.php'; // 引入数据库连接

// ==========================================
// 1. 处理删除 (Delete)
// ==========================================
if (isset($_GET['delete_id'])) {
    $id = (int) $_GET['delete_id'];
    mysqli_query($conn, "DELETE FROM users WHERE user_id = $id");
    header("Location: users.php?msg=deleted");
    exit();
}

// ==========================================
// 2. 处理添加或修改 (Add / Update)
// ==========================================
if (isset($_POST['save_user'])) {
    $name = mysqli_real_escape_string($conn, $_POST['user_name']);
    $email = mysqli_real_escape_string($conn, $_POST['user_email']);
    $phone = mysqli_real_escape_string($conn, $_POST['user_phone']);
    $role = $_POST['user_role'];
    $status = $_POST['user_status'];

    if (!empty($_POST['user_id'])) {
        $id = $_POST['user_id'];
        $sql = "UPDATE users SET user_name='$name', user_email='$email', user_phone='$phone', user_role='$role', user_status='$status' WHERE user_id=$id";
    } else {
        $pass = password_hash("123456", PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (user_name, user_email, user_phone, user_password, user_role, user_status, user_created_at) 
                VALUES ('$name', '$email', '$phone', '$pass', '$role', '$status', NOW())";
    }
    mysqli_query($conn, $sql);
    header("Location: users.php?msg=success");
    exit();
}

// ==========================================
// 3. 处理查询、搜索与 Tabs 过滤 (Search & Filter)
// ==========================================
$where = "WHERE 1=1";
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

if (!empty($search)) {
    $where .= " AND (user_name LIKE '%$search%' OR user_email LIKE '%$search%')";
}
if ($role_filter == 'admin') {
    $where .= " AND user_role IN ('Super Admin', 'Admin')";
} elseif ($role_filter == 'customer') {
    $where .= " AND user_role = 'Customer'";
}
if ($status_filter != 'all') {
    $where .= " AND user_status = '$status_filter'";
}

$users_result = mysqli_query($conn, "SELECT * FROM users $where ORDER BY user_created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Panel</title>
    <link rel="stylesheet" href="../../CSS/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
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
            <a href="users.php" class="tab-item <?= $role_filter == 'all' ? 'active' : '' ?>">All Users</a>
            <a href="users.php?role=admin" class="tab-item <?= $role_filter == 'admin' ? 'active' : '' ?>">Staff /
                Admins</a>
            <a href="users.php?role=customer"
                class="tab-item <?= $role_filter == 'customer' ? 'active' : '' ?>">Customers</a>
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
                <button class="btn-export"><i class="fas fa-file-export"></i> Export</button>
                <button onclick="openModal()" class="btn-add-blue"><i class="fas fa-plus"></i> Add User</button>
            </div>
        </div>

        <div class="table-card" style="padding: 0; border: none;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center; padding-left: 20px;"><input type="checkbox"></th>
                        <th>Role <i class="fas fa-sort"></i></th>
                        <th>User ID <i class="fas fa-sort"></i></th>
                        <th>Full Name <i class="fas fa-sort"></i></th>
                        <th>Email <i class="fas fa-sort"></i></th>
                        <th>Phone Number <i class="fas fa-sort"></i></th>
                        <th>Date Created <i class="fas fa-sort"></i></th>
                        <th>Status <i class="fas fa-sort"></i></th>
                        <th style="text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($users_result && mysqli_num_rows($users_result) > 0) {
                        while ($row = mysqli_fetch_assoc($users_result)) {
                            $role = !empty($row['user_role']) ? $row['user_role'] : 'Customer';
                            $status = !empty($row['user_status']) ? $row['user_status'] : 'Active';

                            $dot_class = (strtolower($status) == 'active') ? 'dot-active' : 'dot-inactive';
                            $text_class = (strtolower($status) == 'active') ? 'text-active' : 'text-inactive';

                            echo "<tr>";
                            echo "<td style='text-align: center; padding-left: 20px;'><input type='checkbox'></td>";
                            echo "<td style='color: #4b5563; font-weight: 500;'>" . htmlspecialchars($role) . "</td>";
                            echo "<td style='color: #6b7280;'>UA" . str_pad($row['user_id'], 3, '0', STR_PAD_LEFT) . "</td>";
                            echo "<td style='font-weight: 500; color: #111827;'>" . htmlspecialchars($row['user_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['user_email']) . "</td>";
                            echo "<td>" . (!empty($row['user_phone']) ? htmlspecialchars($row['user_phone']) : '-') . "</td>";
                            echo "<td>" . date('d Mar Y H:i', strtotime($row['user_created_at'])) . "</td>";
                            echo "<td>
                                    <div class='status-cell'>
                                        <span class='dot {$dot_class}'></span>
                                        <span class='{$text_class}'>" . ucfirst($status) . "</span>
                                    </div>
                                  </td>";
                            echo "<td style='text-align: center;' class='action-icons'>
                                    <a href='javascript:void(0)' onclick='editUser(" . json_encode($row) . ")' title='Edit' style='color: #9ca3af; margin-right:16px;'><i class='fas fa-pen'></i></a>
                                    <a href='users.php?delete_id={$row['user_id']}' onclick=\"return confirm('Are you sure you want to delete this user?');\" title='Delete' style='color: #9ca3af;'><i class='fas fa-trash-alt'></i></a>
                                  </td>";
                            echo "</tr>";
                        }
                    } else {
                        // 完美的 Empty State
                        echo "<tr><td colspan='9' style='text-align: center; padding: 48px 0; color: #6b7280; font-size: 14px;'>No users found in the system.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>

            <div
                style="padding: 20px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e5e7eb;">
                <div class="page-info" style="color: #6b7280; font-size: 13px;">
                    Show
                    <select
                        style="border: 1px solid #d1d5db; border-radius: 4px; padding: 2px 4px; margin: 0 4px; outline: none;">
                        <option>10</option>
                        <option>20</option>
                    </select>
                    from <?php echo mysqli_num_rows($users_result); ?> data
                </div>
                <div class="page-controls">
                    <button class="page-btn"><i class="fas fa-angle-double-left"></i> First</button>
                    <button class="page-btn"><i class="fas fa-angle-left"></i> Previous</button>
                    <button class="page-btn active" style="background-color: #111827; color: white;">1</button>
                    <button class="page-btn">Next <i class="fas fa-angle-right"></i></button>
                    <button class="page-btn">Last <i class="fas fa-angle-double-right"></i></button>
                </div>
            </div>
        </div>
    </main>

    <div id="userModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle" style="font-size: 20px; margin-bottom: 20px;">Add New User</h2>
            <form method="POST">
                <input type="hidden" name="user_id" id="form_user_id">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="user_name" id="form_user_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="user_email" id="form_user_email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="user_phone" id="form_user_phone" class="form-control">
                </div>
                <div style="display:flex; gap: 16px;">
                    <div class="form-group" style="flex:1;">
                        <label>System Role</label>
                        <select name="user_role" id="form_user_role" class="form-control">
                            <option value="Customer">Customer</option>
                            <option value="Admin">Admin</option>
                            <option value="Super Admin">Super Admin</option>
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
                    <button type="button" onclick="closeModal()" class="btn-export">Cancel</button>
                    <button type="submit" name="save_user" class="btn-add-blue">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../JS/admin.js"></script>
    <script>
        const modal = document.getElementById('userModal');
        function openModal() {
            document.getElementById('modalTitle').innerText = "Add New User";
            document.getElementById('form_user_id').value = "";
            document.getElementById('form_user_name').value = "";
            document.getElementById('form_user_email').value = "";
            document.getElementById('form_user_phone').value = "";
            modal.classList.add('active');
        }
        function closeModal() { modal.classList.remove('active'); }

        function editUser(data) {
            document.getElementById('modalTitle').innerText = "Edit User Details";
            document.getElementById('form_user_id').value = data.user_id;
            document.getElementById('form_user_name').value = data.user_name;
            document.getElementById('form_user_email').value = data.user_email;
            document.getElementById('form_user_phone').value = data.user_phone;
            document.getElementById('form_user_role').value = data.user_role;
            document.getElementById('form_user_status').value = data.user_status;
            modal.classList.add('active');
        }
    </script>
</body>

</html>