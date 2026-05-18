<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../Config/database.php";

header('Content-Type: application/json');

// 🔥 检查是否登录
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

    // 🔥 修复：把 id 换成了 wishlist_id (或者写 SELECT 1 性能更好，我这里帮你用 wishlist_id)
    $checkStmt = $pdo->prepare("SELECT wishlist_id FROM wishlist WHERE user_id = ? AND car_id = ?");
    $checkStmt->execute([$user_id, $car_id]);
    
    if ($checkStmt->rowCount() > 0) {
        // 如果存在，就删除 (取消爱心)
        $delStmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND car_id = ?");
        $delStmt->execute([$user_id, $car_id]);
        echo json_encode(['status' => 'removed']);
    } else {
        // 如果不存在，就添加 (点亮爱心)
        $insStmt = $pdo->prepare("INSERT INTO wishlist (user_id, car_id) VALUES (?, ?)");
        $insStmt->execute([$user_id, $car_id]);
        echo json_encode(['status' => 'added']);
    }

} catch(PDOException $e) {
    // 遇到错误，返回 JSON 格式的报错信息
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>