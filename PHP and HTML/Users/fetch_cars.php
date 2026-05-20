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

// 接收前端 AJAX 发送过来的 4 个过滤参数
$model    = isset($_POST['model']) ? trim($_POST['model']) : 'AllModels';
$bodyType = isset($_POST['bodyType']) ? trim($_POST['bodyType']) : 'All';
$search   = isset($_POST['search']) ? trim($_POST['search']) : '';
$sort     = isset($_POST['sort']) ? trim($_POST['sort']) : 'Latest';

// 统一使用 $_SESSION['id'] 作为用户 ID
$user_id = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;

// 构建 SQL 查询语句
$sql = "
    SELECT 
        c.car_id, c.car_brand, c.car_model, c.car_year, c.car_origin,
        c.transmission, c.fuel_type, c.car_mileage, c.body_type, c.car_created_at,
        t.car_type_name, l.location_city, s.car_status_price,
        (SELECT car_image_url FROM car_image WHERE car_id = c.car_id LIMIT 1) as car_image_url,
        (SELECT COUNT(*) FROM wishlist WHERE user_id = $user_id AND car_id = c.car_id) as is_liked
    FROM cars c
    LEFT JOIN car_types t ON c.car_type_id = t.car_type_id
    LEFT JOIN locations l ON c.location_id = l.location_id
    LEFT JOIN car_status s ON c.car_id = s.car_id
    WHERE s.car_status_status = 'Active' 
";

$params = [];

// ==========================================
// 1. Model (上方车型 Tabs) 过滤
// ==========================================
if ($model !== 'AllModels') {
    // 比如选了 Saga, Persona, e.MAS 7
    $sql .= " AND c.car_model LIKE ?";
    $params[] = "%$model%";
}

// ==========================================
// 2. Body Type (左侧 Sidebar) 过滤
// ==========================================
if ($bodyType !== 'All') {
    if ($bodyType === 'EV') {
        // 如果选了 EV，检查燃料类型或者在 car_ev_specs 表中是否有记录
        $sql .= " AND (c.fuel_type IN ('Electric', 'EV') OR (SELECT COUNT(*) FROM car_ev_specs ev WHERE ev.car_id = c.car_id) > 0)";
    } else {
        // 比如 Sedan, SUV, Hatchback
        $sql .= " AND t.car_type_name = ?"; 
        $params[] = $bodyType;
    }
}

// ==========================================
// 3. Search (搜索框) 过滤
// ==========================================
if (!empty($search)) {
    $sql .= " AND (c.car_brand LIKE ? OR c.car_model LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// ==========================================
// 4. Sort (下拉框排序) 过滤
// ==========================================
if ($sort === 'Price: Low to High') {
    $sql .= " ORDER BY s.car_status_price ASC";
} elseif ($sort === 'Price: High to Low') {
    $sql .= " ORDER BY s.car_status_price DESC";
} else {
    // 默认排序：最新发布的车 (Latest)
    $sql .= " ORDER BY c.car_created_at DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 5. 渲染输出 HTML (保留你原有的精美排版和 Class)
// ==========================================
if (count($cars) > 0) {
    foreach ($cars as $car) {
        $id = $car['car_id'];
        
        // 确保名字没写时有个默认值，避免前端空出来很难看
        $brandModel = trim($car['car_brand'] . ' ' . $car['car_model']);
        if (empty($brandModel)) $brandModel = 'Proton Vehicle';
        
        $title = strtoupper(htmlspecialchars($brandModel)); 
        $year = htmlspecialchars($car['car_year']);
        
        $originRaw = !empty($car['car_origin']) ? $car['car_origin'] : 'Used Car';
        $condition = strtoupper(str_replace(' Car', '', $originRaw)); 
        
        $location = !empty($car['location_city']) ? htmlspecialchars($car['location_city']) : 'N/A';
        $price = !empty($car['car_status_price']) ? number_format($car['car_status_price'], 0) : 'TBA';
        
        $mileage = !empty($car['car_mileage']) ? number_format($car['car_mileage']) . ' KM' : '0 KM';
        $transmission = !empty($car['transmission']) ? htmlspecialchars($car['transmission']) : 'Auto';
        $fuel = !empty($car['fuel_type']) ? htmlspecialchars($car['fuel_type']) : 'Petrol';
        
        $img = !empty($car['car_image_url']) ? htmlspecialchars($car['car_image_url']) : 'https://images.unsplash.com/photo-1550486014-9f88c39d8dc9?auto=format&fit=crop&w=800&q=80';

        $likedClass = ($car['is_liked'] > 0) ? 'liked' : '';

        // 这里的 HTML 结构和你的原版一模一样，完美兼容你的 CSS
        echo '
        <a href="javascript:void(0);" onclick="openCarDetails('.$id.')" class="pro-car-card">
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
    // 找不到车的时候显示的提示（稍微美化了一点）
    echo '
    <div style="grid-column: 1 / -1; text-align: center; padding: 80px 20px;">
        <h3 style="color: #0f172a; margin-bottom: 5px;">No vehicles found</h3>
        <p style="color: #64748b; font-size: 14px;">Try adjusting your filters or search criteria.</p>
    </div>';
}
?>