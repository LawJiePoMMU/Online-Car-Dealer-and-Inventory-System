<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../Config/database.php";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("PDO Connection failed: " . $e->getMessage());
}

$category = isset($_POST['category']) ? $_POST['category'] : 'All';
$brand = isset($_POST['brand']) ? $_POST['brand'] : 'AllBrands';
$search = isset($_POST['search']) ? trim($_POST['search']) : '';

// 🔥 修复：统一使用 $_SESSION['id']
$user_id = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;

$sql = "
    SELECT 
        c.car_id, c.car_brand, c.car_model, c.car_year, c.car_origin,
        c.transmission, c.fuel_type,
        t.car_type_name, l.location_city, i.car_image_url, s.car_status_price,
        h.used_mileage,
        (SELECT COUNT(*) FROM wishlist WHERE user_id = $user_id AND car_id = c.car_id) as is_liked
    FROM cars c
    LEFT JOIN car_types t ON c.car_type_id = t.car_type_id
    LEFT JOIN locations l ON c.location_id = l.location_id
    LEFT JOIN car_status s ON c.car_id = s.car_id
    LEFT JOIN car_history h ON c.car_id = h.car_id
    LEFT JOIN (SELECT car_id, MIN(car_image_url) as car_image_url FROM car_image GROUP BY car_id) i ON c.car_id = i.car_id
    WHERE 1=1 
    AND s.car_status_status = 'Active'
";

$params = [];

if ($category !== 'All') {
    $sql .= " AND t.car_type_name = ?"; 
    $params[] = $category;
}
if ($brand !== 'AllBrands') {
    $sql .= " AND c.car_brand = ?";
    $params[] = $brand;
}
if (!empty($search)) {
    $sql .= " AND (c.car_brand LIKE ? OR c.car_model LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($cars) > 0) {
    foreach ($cars as $car) {
        $id = $car['car_id'];
        $title = strtoupper(htmlspecialchars($car['car_brand'] . ' ' . $car['car_model'])); 
        $year = htmlspecialchars($car['car_year']);
        
        $originRaw = !empty($car['car_origin']) ? $car['car_origin'] : 'Used Car';
        $condition = strtoupper(str_replace(' Car', '', $originRaw)); 
        
        $location = !empty($car['location_city']) ? htmlspecialchars($car['location_city']) : 'N/A';
        $price = !empty($car['car_status_price']) ? number_format($car['car_status_price'], 0) : 'TBA';
        $mileage = !empty($car['used_mileage']) ? number_format($car['used_mileage']) . ' KM' : '0 KM';
        $transmission = !empty($car['transmission']) ? htmlspecialchars($car['transmission']) : 'Auto';
        $fuel = !empty($car['fuel_type']) ? htmlspecialchars($car['fuel_type']) : 'Petrol';
        
        $img = !empty($car['car_image_url']) ? htmlspecialchars($car['car_image_url']) : 'https://images.unsplash.com/photo-1550486014-9f88c39d8dc9?auto=format&fit=crop&w=800&q=80';

        // 🔥 如果已经点过收藏，直接赋予 liked 属性
        $likedClass = ($car['is_liked'] > 0) ? 'liked' : '';

        echo '
        <a href="car_details.php?id='.$id.'" class="pro-car-card">
            <div class="card-img-container">
                <img src="'.$img.'" alt="'.$title.'" onerror="this.onerror=null; this.src=\'https://images.unsplash.com/photo-1550486014-9f88c39d8dc9?auto=format&fit=crop&w=800&q=80\';">
                <span class="badge-condition">'.$condition.'</span>
                
                <button class="btn-wishlist '.$likedClass.'" onclick="addToWishlist(event, this, '.$id.')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                </button>
            </div>
            
            <div class="card-content">
                <h3 class="card-title">'.$title.'</h3>
                <div class="card-price">RM '.$price.'</div>
                <div class="card-specs">
                    <div class="spec-row">
                        <span>'.$year.'</span> <span class="spec-dot">•</span> <span>'.$mileage.'</span>
                    </div>
                    <div class="spec-row">
                        <span>'.$transmission.'</span> <span class="spec-dot">•</span> <span>'.$fuel.'</span>
                    </div>
                </div>
                <div class="card-footer">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; margin-bottom: 2px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    <span>'.$location.'</span>
                </div>
            </div>
        </a>';
    }
} else {
    echo '<div style="grid-column: 1 / -1; text-align: center; color: #64748b; padding: 50px;">No vehicles found.</div>';
}
?>