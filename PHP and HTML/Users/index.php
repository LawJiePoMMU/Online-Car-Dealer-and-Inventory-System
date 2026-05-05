<?php
session_start();

// 1. 引入你的 database.php (请确保路径正确，如果是 index.php 通常在根目录，可能只需要 'Config/database.php')
require_once "../Config/database.php"; 

// 2. 建立高级 PDO 连接来抓取数据
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("PDO Connection failed: " . $e->getMessage());
}

// 3. 从数据库里抓取 3 辆热销车
// 🔥【重要】请把 'cars' 换成你数据库里存车子的 Table 名字！
// 如果你想按销量排序，可以加上 ORDER BY sales DESC
$sql = "SELECT * FROM cars LIMIT 3"; 
$stmt = $pdo->prepare($sql);
$stmt->execute();
$top_cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'Includes/header.php'; 
?>

<!-- 主图区域 (Hero Section) 保持不变 -->
<section class="hero-section">
    <div class="hero-content">
        <h1>Find Your Dream Second-Hand Car</h1>
        <p>Quality inspected vehicles, trusted dealers, and the best prices in Malaysia.</p>
        <a href="cars.php" class="btn-primary" style="font-size: 18px; padding: 15px 35px; margin-top: 10px;">Browse Inventory</a>
    </div>
</section>

<!-- =========================================
   2. 热销车款区域 (🔥 动态读取 Database) 
   ========================================= -->
<section class="container" style="margin-top: 80px; margin-bottom: 80px;">
    <h2 class="section-title text-center">Top Sale Cars</h2>
    <p class="text-center" style="color: var(--text-light); margin-bottom: 40px;">Check out our most popular and highly-rated vehicles this week.</p>
    
    <div class="grid grid-3">
        
        <?php 
        // 检查数据库里有没有车
        if(count($top_cars) > 0) {
            
            // 🔥 开始循环！数据库里有几辆车，就自动生成几张卡片 (LIMIT 3 限制了最多 3 张)
            foreach($top_cars as $car) { 
        ?>
            <div class="card">
                <!-- 🔥 请把 'image_url' 换成你数据库里存图片路径的 column 名字 -->
                <!-- 如果你还没做上传图片功能，可以暂时保留网上的假图片来测试 -->
                <img src="<?php echo !empty($car['image_url']) ? htmlspecialchars($car['image_url']) : 'https://images.unsplash.com/photo-1550486014-9f88c39d8dc9?auto=format&fit=crop&w=800&q=80'; ?>" alt="Car Image">
                
                <div class="card-body">
                    <!-- 🔥 请把 'car_name' 和 'price' 换成你数据库里的 column 名字 -->
                    <h3 class="card-title"><?php echo htmlspecialchars($car['car_name']); ?></h3>
                    <p class="card-price">RM <?php echo number_format($car['price']); ?></p>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; font-size: 13px; color: var(--text-light); border-top: 1px solid #f1f5f9; padding-top: 12px;">
                        <!-- 🔥 请把你数据库里对应的 里程数、燃油类型、变速箱 column 名字填进来 -->
                        <span>🏎️ <?php echo htmlspecialchars($car['mileage']); ?> km</span>
                        <span>⛽ <?php echo htmlspecialchars($car['fuel_type']); ?></span>
                        <span>⚙️ <?php echo htmlspecialchars($car['transmission']); ?></span>
                    </div>
                    
                    <!-- 跳转到详情页，并带上这辆车的 ID -->
                    <a href="car_details.php?id=<?php echo $car['id']; ?>" class="btn-primary" style="width: 100%;">View Details</a>
                </div>
            </div>
        <?php 
            } // 结束 foreach 循环
        } else {
            // 如果数据库是空的，显示这句话
            echo "<p style='grid-column: 1 / -1; text-align: center; color: var(--text-light);'>No cars available at the moment. Please check back later!</p>";
        }
        ?>

    </div>
    
    <!-- 查看全部的副按钮 -->
    <div class="text-center" style="margin-top: 40px;">
        <a href="cars.php" class="auth-btn-secondary" style="display: inline-block; width: auto; padding: 12px 35px; border-radius: 8px; text-decoration: none; font-weight: 600;">View All Inventory</a>
    </div>
</section>

<!-- 信任背书区域 (Why Choose Us) 保持不变 -->
<section class="features-section">
    <div class="container">
        <h2 class="section-title text-center" style="margin-top: 0;">Why Choose Us?</h2>
        <div class="grid grid-3">
            <div class="feature-box">
                <div class="feature-icon">🛡️</div>
                <h3 style="color: var(--text-dark);">Verified Dealers</h3>
                <p style="color: var(--text-light); font-size: 14px; margin-top: 10px;">All our cars come from trusted and verified dealers in Malaysia.</p>
            </div>
            <div class="feature-box">
                <div class="feature-icon">🔍</div>
                <h3 style="color: var(--text-dark);">175-Point Inspection</h3>
                <p style="color: var(--text-light); font-size: 14px; margin-top: 10px;">Every vehicle undergoes a strict quality check before listing.</p>
            </div>
            <div class="feature-box">
                <div class="feature-icon">💰</div>
                <h3 style="color: var(--text-dark);">Best Price Guarantee</h3>
                <p style="color: var(--text-light); font-size: 14px; margin-top: 10px;">No hidden fees. Transparent pricing for your peace of mind.</p>
            </div>
        </div>
    </div>
</section>

<?php include 'Includes/footer.php'; ?>