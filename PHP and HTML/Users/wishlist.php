<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'Includes/header.php'; 
require_once "../Config/database.php";

// 检查是否登录
if (!isset($_SESSION['id'])) {
    echo "<script>alert('Please login to view your wishlist.'); window.location.href='Auth/login.php';</script>";
    exit;
}

$user_id = $_SESSION['id'];

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "
        SELECT 
            c.car_id, c.car_brand, c.car_model, c.car_year, c.car_origin,
            c.transmission, c.fuel_type,
            t.car_type_name, l.location_city, i.car_image_url, s.car_status_price,
            h.used_mileage
        FROM wishlist w
        JOIN cars c ON w.car_id = c.car_id
        LEFT JOIN car_types t ON c.car_type_id = t.car_type_id
        LEFT JOIN locations l ON c.location_id = l.location_id
        LEFT JOIN car_status s ON c.car_id = s.car_id
        LEFT JOIN car_history h ON c.car_id = h.car_id
        LEFT JOIN (SELECT car_id, MIN(car_image_url) as car_image_url FROM car_image GROUP BY car_id) i ON c.car_id = i.car_id
        WHERE w.user_id = ?
        ORDER BY w.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $wishlist_cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<div class="inventory-page">
    <div class="inventory-wrapper">
        <main class="inventory-main">
            <div class="top-action-bar" style="margin-bottom: 30px;">
                <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0;">MY WISHLIST</h1>
                <p id="wishlist-count-text" style="color: #64748b; font-size: 14px; margin-top: 5px;">You have <?php echo count($wishlist_cars); ?> saved vehicles.</p>
            </div>

            <div class="inventory-grid" id="wishlist-container">
                <?php if (count($wishlist_cars) > 0): ?>
                    <?php foreach ($wishlist_cars as $car): ?>
                        <?php 
                            $id = $car['car_id'];
                            $title = strtoupper(htmlspecialchars($car['car_brand'] . ' ' . $car['car_model']));
                            $price = number_format($car['car_status_price'], 0);
                            $img = !empty($car['car_image_url']) ? htmlspecialchars($car['car_image_url']) : 'https://images.unsplash.com/photo-1550486014-9f88c39d8dc9?auto=format&fit=crop&w=800&q=80';
                            $condition = strtoupper(str_replace(' Car', '', $car['car_origin'] ?? 'USED'));
                        ?>
                        
                        <div class="pro-car-card" id="car-card-<?php echo $id; ?>">
                            <a href="car_details.php?id=<?php echo $id; ?>" style="text-decoration: none;">
                                <div class="card-img-container">
                                    <img src="<?php echo $img; ?>" alt="<?php echo $title; ?>">
                                    <span class="badge-condition"><?php echo $condition; ?></span>
                                    
                                    <button class="btn-wishlist liked" onclick="removeFromWishlist(event, this, <?php echo $id; ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                                    </button>
                                </div>
                                
                                <div class="card-content">
                                    <h3 class="card-title"><?php echo $title; ?></h3>
                                    <div class="card-price">RM <?php echo $price; ?></div>
                                    
                                    <div class="card-specs">
                                        <div class="spec-row">
                                            <span><?php echo $car['car_year']; ?></span> <span class="spec-dot">•</span> <span><?php echo number_format($car['used_mileage']); ?> KM</span>
                                        </div>
                                        <div class="spec-row">
                                            <span><?php echo $car['transmission']; ?></span> <span class="spec-dot">•</span> <span><?php echo $car['fuel_type']; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="card-footer">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; margin-bottom: 2px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                        <span><?php echo htmlspecialchars($car['location_city']); ?></span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 100px 0;">
                        <div style="font-size: 50px; margin-bottom: 20px;">Empty</div>
                        <h2 style="color: #0f172a;">Your wishlist is empty</h2>
                        <p style="color: #64748b; margin-bottom: 30px;">Start exploring and save your favorite cars!</p>
                        <a href="cars.php" style="padding: 12px 30px; background: #0f172a; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">Browse Cars</a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script>
    function removeFromWishlist(event, btnElement, carId) {
        event.preventDefault();
        
        // 🔥 已经把 confirm() 弹窗删掉了，现在一点击就直接执行删除！

        fetch('toggle_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `car_id=${carId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'removed') {
                const card = document.getElementById(`car-card-${carId}`);
                card.style.opacity = '0';
                card.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    // 1. 删掉卡片
                    card.remove();
                    
                    // 2. 动态计算剩下的卡片数量，并更新上面的文字
                    let remainingCars = document.querySelectorAll('.pro-car-card').length;
                    let countTextElement = document.getElementById('wishlist-count-text');
                    
                    if(countTextElement) {
                        countTextElement.innerText = "You have " + remainingCars + " saved vehicles.";
                    }

                    // 如果删光了，刷新页面显示 Empty 状态
                    if(remainingCars === 0) {
                        location.reload();
                    }
                }, 300); // 300毫秒的丝滑消失动画
            }
        })
        .catch(error => console.error('Error:', error));
    }
</script>

<?php include 'Includes/footer.php'; ?>