<?php
require_once "../Config/database.php";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

$bodyType = isset($_POST['bodyType']) ? trim($_POST['bodyType']) : 'All';

$sql = "SELECT DISTINCT c.car_model, c.car_brand 
        FROM cars c
        LEFT JOIN car_types t ON c.car_type_id = t.car_type_id
        JOIN car_status s ON c.car_id = s.car_id  /* 👈 重点：把 LEFT 删掉，直接用 JOIN */
        WHERE s.car_status_status = 'Active' 
        AND s.car_status_stock_quantity > 0 
        AND c.car_model IS NOT NULL 
        AND c.car_model != ''";

$params = [];
if ($bodyType !== 'All') {
    if ($bodyType === 'EV') {
        $sql .= " AND (c.fuel_type IN ('Electric', 'EV') OR c.body_type = 'EV')";
    } else {
        $sql .= " AND t.car_type_name = ?";
        $params[] = $bodyType;
    }
}
$sql .= " ORDER BY c.car_model ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($results);
?>