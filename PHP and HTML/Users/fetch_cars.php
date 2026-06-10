<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../Config/database.php";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("PDO Connection failed: " . $e->getMessage());
}

$model        = $_POST['model'] ?? 'AllModels';
$bodyType     = $_POST['bodyType'] ?? 'All';
$condition    = $_POST['condition'] ?? 'All';
$transmission = $_POST['transmission'] ?? 'All';
$year         = $_POST['year'] ?? 'All';
$price        = $_POST['price'] ?? 'All';
$sort         = $_POST['sort'] ?? 'Latest';
$keyword      = $_POST['keyword'] ?? '';
$page         = isset($_POST['page']) ? (int)$_POST['page'] : 1;

$user_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

$limit = 20; 
$offset = ($page - 1) * $limit;

$baseSql = "FROM cars c
            LEFT JOIN car_types t ON c.car_type_id = t.car_type_id
            LEFT JOIN car_status s ON c.car_id = s.car_id
            LEFT JOIN locations l ON c.location_id = l.location_id
            WHERE s.car_status_status = 'Active' 
            AND s.car_status_price > 0 
            AND s.car_status_stock_quantity > 0 
            AND c.car_model != ''";

$params = [];

if ($model !== 'AllModels') { 
    $baseSql .= " AND c.car_model = ?"; 
    $params[] = $model; 
}

if ($bodyType !== 'All') { 
    if ($bodyType === 'EV') { 
        $baseSql .= " AND (c.fuel_type IN ('Electric', 'EV') OR c.body_type = 'EV')"; 
    } else { 
        $baseSql .= " AND t.car_type_name = ?"; 
        $params[] = $bodyType; 
    }
}

if ($condition !== 'All') { 
    $baseSql .= " AND c.car_origin = ?"; 
    $params[] = $condition; 
}

if ($transmission !== 'All') { 
    $baseSql .= " AND c.transmission = ?"; 
    $params[] = $transmission; 
}

if ($year === '2023-2024') { 
    $baseSql .= " AND c.car_year >= 2023 AND c.car_year <= 2024"; 
} elseif ($year === '2020-2022') { 
    $baseSql .= " AND c.car_year >= 2020 AND c.car_year <= 2022"; 
} elseif ($year === 'Before 2020') { 
    $baseSql .= " AND c.car_year < 2020"; 
}

if ($price === 'Under 50k') { 
    $baseSql .= " AND s.car_status_price < 50000"; 
} elseif ($price === '50k-100k') { 
    $baseSql .= " AND s.car_status_price >= 50000 AND s.car_status_price <= 100000"; 
} elseif ($price === 'Above 100k') { 
    $baseSql .= " AND s.car_status_price > 100000"; 
}

if ($keyword !== '') {
    $kw_nospace = str_replace(' ', '', $keyword);
    $wildcard = "%$keyword%";
    $wildcard_nospace = "%$kw_nospace%";

    $baseSql .= " AND (
        CONCAT(c.car_brand, ' ', c.car_model) LIKE ?
        OR REPLACE(CONCAT(c.car_brand, c.car_model), ' ', '') LIKE ?
        OR c.car_year LIKE ?
        OR SOUNDEX(c.car_model) = SOUNDEX(?)
        OR SOUNDEX(REPLACE(c.car_brand, 'Proton ', '')) = SOUNDEX(?)
    )";
    
    $params[] = $wildcard;
    $params[] = $wildcard_nospace;
    $params[] = $wildcard;
    $params[] = $keyword;
    $params[] = $keyword;
}

$countSql = "SELECT COUNT(c.car_id) " . $baseSql;
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalCars = $stmtCount->fetchColumn();
$totalPages = ceil($totalCars / $limit);

$orderSql = " ORDER BY c.car_created_at DESC";
if ($sort === 'Price: Low to High') {
    $orderSql = " ORDER BY s.car_status_price ASC";
} elseif ($sort === 'Price: High to Low') {
    $orderSql = " ORDER BY s.car_status_price DESC";
}
$finalSql = "SELECT c.*, t.car_type_name, s.car_status_price, l.location_city,
             (SELECT car_image_url FROM car_image WHERE car_id = c.car_id LIMIT 1) as car_image_url,
             (SELECT COUNT(*) FROM wishlist WHERE user_id = " . (int)$user_id . " AND car_id = c.car_id) as is_liked 
             " . $baseSql . $orderSql . " LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($finalSql);
$stmt->execute($params);
$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($cars) > 0) {
    foreach ($cars as $car) {
        $id = $car['car_id'];
        $brandModel = trim($car['car_brand'] . ' ' . $car['car_model']);
        if (empty($brandModel)) $brandModel = 'Proton Vehicle';
        
        $title = strtoupper(htmlspecialchars($brandModel));
        $year_display = htmlspecialchars($car['car_year']);
        $originRaw = !empty($car['car_origin']) ? $car['car_origin'] : 'Used Car';
        $condition_display = strtoupper(str_replace(' Car', '', $originRaw));
        $location = !empty($car['location_city']) ? htmlspecialchars($car['location_city']) : 'N/A';
        $price_display = !empty($car['car_status_price']) ? number_format($car['car_status_price'], 0) : 'TBA';
        $mileage = !empty($car['car_mileage']) ? number_format($car['car_mileage']) . ' KM' : '0 KM';
        $transmission = !empty($car['transmission']) ? htmlspecialchars($car['transmission']) : 'Auto';
        $fuel = !empty($car['fuel_type']) ? htmlspecialchars($car['fuel_type']) : 'Petrol';
        $img = !empty($car['car_image_url']) ? htmlspecialchars($car['car_image_url']) : 'https://images.unsplash.com/photo-1550486014-9f88c39d8dc9?auto=format&fit=crop&w=800&q=80';
        $likedClass = ($car['is_liked'] > 0) ? 'liked' : '';

        echo '
        <a href="javascript:void(0);" onclick="openCarDetails('.$id.')" class="pro-car-card">
            <div class="card-img-container">
                <img src="'.$img.'" alt="'.$title.'" onerror="this.onerror=null; this.src=\'https://images.unsplash.com/photo-1550486014-9f88c39d8dc9?auto=format&fit=crop&w=800&q=80\';">
                <span class="badge-condition">'.$condition_display.'</span>
                <button class="btn-wishlist '.$likedClass.'" onclick="addToWishlist(event, this, '.$id.')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                </button>
            </div>
            <div class="card-content">
                <h3 class="card-title">'.$title.'</h3>
                <div class="card-price">RM '.$price_display.'</div>
                <div class="card-specs">
                    <div class="spec-row"><span>'.$year_display.'</span><span class="spec-dot">•</span><span>'.$mileage.'</span></div>
                    <div class="spec-row"><span>'.$transmission.'</span><span class="spec-dot">•</span><span>'.$fuel.'</span></div>
                </div>
                <div class="card-footer">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; margin-bottom: 2px;">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span>'.$location.'</span>
                </div>
            </div>
        </a>';
    }

    if ($totalPages > 1) {
        echo '<div class="pagination-container">';
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i === $page) ? 'active' : '';
            echo "<button class='page-btn $active' onclick='changePage($i)'>$i</button>";
        }
        echo '</div>';
    }

} else {
    echo '
    <div style="grid-column: 1 / -1; text-align: center; padding: 80px 20px;">
        <h3 style="color: #0f172a; margin-bottom: 5px;">No vehicles found</h3>
        <p style="color: #64748b; font-size: 14px;">Try adjusting your filters or search criteria.</p>
    </div>';
}
?>