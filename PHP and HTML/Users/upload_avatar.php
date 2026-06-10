<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../Config/database.php"; 

header('Content-Type: application/json');

if (!isset($_SESSION['id']) || !isset($_FILES['avatar'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please login or select a file.']);
    exit;
}

$user_id = $_SESSION['id'];
$file = $_FILES['avatar'];

$allowed = ['jpg', 'jpeg', 'png', 'gif'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed)) {
    echo json_encode(['status' => 'error', 'message' => 'Only JPG, PNG, and GIF are allowed.']);
    exit;
}

if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode(['status' => 'error', 'message' => 'File is too large! Maximum size is 2MB.']);
    exit;
}

$upload_dir = '../Uploads/Avatars/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true); 
}

$new_filename = 'avatar_user_' . $user_id . '_' . time() . '.' . $ext;
$dest_path = $upload_dir . $new_filename;

if (move_uploaded_file($file['tmp_name'], $dest_path)) {
    
    $db_path = '../Uploads/Avatars/' . $new_filename;

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