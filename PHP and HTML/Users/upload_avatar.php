<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. 引入你的 database.php (确保退一层路径正确)
require_once "../Config/database.php"; 

header('Content-Type: application/json');

// 2. 检查是否登录以及是否有文件传过来
if (!isset($_SESSION['id']) || !isset($_FILES['avatar'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please login or select a file.']);
    exit;
}

$user_id = $_SESSION['id'];
$file = $_FILES['avatar'];

// 3. 检查文件格式
$allowed = ['jpg', 'jpeg', 'png', 'gif'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed)) {
    echo json_encode(['status' => 'error', 'message' => 'Only JPG, PNG, and GIF are allowed.']);
    exit;
}

// 4. 检查文件大小 (最大 2MB)
if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode(['status' => 'error', 'message' => 'File is too large! Maximum size is 2MB.']);
    exit;
}

// 5. 设置保存路径
$upload_dir = '../Uploads/Avatars/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true); 
}

// 6. 生成新文件名
$new_filename = 'avatar_user_' . $user_id . '_' . time() . '.' . $ext;
$dest_path = $upload_dir . $new_filename;

// 7. 保存文件并写入数据库
if (move_uploaded_file($file['tmp_name'], $dest_path)) {
    
    // 给前端显示的路径
    $db_path = '../Uploads/Avatars/' . $new_filename;

    // 🔥 这里使用了你的 $conn 和 mysqli 语法
    $sql = "UPDATE users SET user_avatar = ? WHERE user_id = ?";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "si", $db_path, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success', 'filepath' => $db_path]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update database.']);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database SQL error.']);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save image to folder.']);
}
?>