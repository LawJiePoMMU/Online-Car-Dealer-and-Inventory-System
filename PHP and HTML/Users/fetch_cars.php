<?php
// 1. 引入数据库 (注意路径：退一层找 Config)
require_once "../Config/database.php";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("PDO Connection failed: " . $e->getMessage());
}

// 2. 接收前端 AJAX 传过来的筛选条件
$category = isset($_POST['category']) ? $_POST['category'] : 'All';
$brand = isset($_POST['brand']) ? $_POST['brand'] : 'AllBrands';

// 3. 准备高级 SQL 连表查询 (根据你真实的 Database 结构量身打造 🔥)
$sql = "
    SELECT 
        c.car_id, 
        c.car_brand, 
        c.car_model, 
        c.car_year,
        c.car_origin,        -- 抓取 New Car / Used Car
        t.car_type_name,     -- 真实的类型名称列
        l.location_city,     -- 真实的城市名称列
        i.car_image_url,     -- 真实的图片列
        s.car_status_price   -- 真实的价格列
        
    FROM cars c
    LEFT JOIN car_types t ON c.car_type_id = t.car_type_id
    LEFT JOIN locations l ON c.location_id = l.location_id
    LEFT JOIN car_status s ON c.car_id = s.car_id
    
    -- 使用 MIN() 防止数据库 strict mode 报错，确保每辆车只拿第一张图
    LEFT JOIN (SELECT car_id, MIN(car_image_url) as car_image_url FROM car_image GROUP BY car_id) i ON c.car_id = i.car_id
    
    WHERE 1=1 
    AND s.car_status_status = 'Active' -- 🔥 额外加码：只显示状态为 Active 的车子！
";

$params = [];

// 4. 动态拼接筛选条件
if ($category !== 'All') {
    $sql .= " AND t.car_type_name = ?"; 
    $params[] = $category;
}

if ($brand !== 'AllBrands') {
    $sql .= " AND c.car_brand = ?";
    $params[] = $brand;
}

// 5. 执行查询
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. 渲染成 HTML 卡片
if (count($cars) > 0) {
    foreach ($cars as $car) {
        $id = $car['car_id'];
        $brandName = strtoupper(htmlspecialchars($car['car_brand'])); 
        $modelName = strtoupper(htmlspecialchars($car['car_model'])); 
        $year = htmlspecialchars($car['car_year']);
        
        // 匹配你数据库里的真实列名
        $type = !empty($car['car_type_name']) ? htmlspecialchars($car['car_type_name']) : 'N/A';
        $location = !empty($car['location_city']) ? htmlspecialchars($car['location_city']) : 'N/A';
        $condition = !empty($car['car_origin']) ? htmlspecialchars($car['car_origin']) : 'Used Car';
        
        // 格式化价格
        $price = !empty($car['car_status_price']) ? number_format($car['car_status_price'], 2) : 'TBA';
        
        $img = !empty($car['car_image_url']) ? htmlspecialchars($car['car_image_url']) : 'https://images.unsplash.com/photo-1550486014-9f88c39d8dc9?auto=format&fit=crop&w=800&q=80';

        // 🚗 经典图片置顶排版 HTML 输出
        echo '
        <a href="car_details.php?id='.$id.'" class="flat-car-card">
            
            <!-- 1. 车辆图片 (置顶) -->
            <img src="'.$img.'" alt="'.$brandName.' '.$modelName.'" class="flat-car-img" onerror="this.onerror=null; this.src=\'https://images.unsplash.com/photo-1550486014-9f88c39d8dc9?auto=format&fit=crop&w=800&q=80\';">
            
            <!-- 2. Brand | Model 标题 -->
            <h3 class="flat-car-title">
                '.$brandName.' <span>| '.$modelName.'</span>
            </h3>
            
            <!-- 3. 价格展示 -->
            <div class="flat-car-price">
                RM '.$price.'
            </div>
            
            <!-- 4. 年份与新旧标签 -->
            <div class="card-badges">
                <span class="badge-primary">'.$year.'</span>
                <span class="badge-secondary">'.$condition.'</span>
            </div>
            
            <!-- 5. 类型与地点 (自动推到底部对齐) -->
            <div class="flat-car-footer">
                <span>🚙 '.$type.'</span>
                <span>📍 '.$location.'</span>
            </div>
            
        </a>';
    }
} else {
    echo '<div style="grid-column: 1 / -1; text-align: center; color: #64748b; padding: 50px; font-size: 16px; font-weight: 500;">No vehicles found matching your criteria.</div>';
}
?>