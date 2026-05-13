<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../Config/database.php";

header('Content-Type: application/json');

// 🔥 判断使用的是 $_SESSION['id']
// 🔥 修复：如果没登录，返回专属状态 'not_logged_in'
if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'not_logged_in', 'message' => 'Please login to add cars to your wishlist!']);
    exit;
}

$user_id = $_SESSION['id'];
$car_id = isset($_POST['car_id']) ? intval($_POST['car_id']) : 0;

if ($car_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Car ID']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $checkStmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND car_id = ?");
    $checkStmt->execute([$user_id, $car_id]);
    
    if ($checkStmt->rowCount() > 0) {
        $delStmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND car_id = ?");
        $delStmt->execute([$user_id, $car_id]);
        echo json_encode(['status' => 'removed']);
    } else {
        $insStmt = $pdo->prepare("INSERT INTO wishlist (user_id, car_id) VALUES (?, ?)");
        $insStmt->execute([$user_id, $car_id]);
        echo json_encode(['status' => 'added']);
    }

} catch(PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>