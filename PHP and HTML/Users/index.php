<?php
session_start();

require_once "../Config/database.php"; 

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("PDO Connection failed: " . $e->getMessage());
}

$sql = "SELECT * FROM cars LIMIT 3"; 
$stmt = $pdo->prepare($sql);
$stmt->execute();
$top_cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
$sql_banner = "SELECT * FROM homepage_banners WHERE Is_Active = 'Yes' ORDER BY Display_Order ASC";
$stmt_b = $pdo->prepare($sql_banner);
$stmt_b->execute();
$banners = $stmt_b->fetchAll(PDO::FETCH_ASSOC);

include 'Includes/header.php'; 
?>
<?php if (count($banners) > 0): ?>
<section class="hero-banner">
    <div class="banner-slides">
        <?php foreach($banners as $i => $b): ?>
            <div class="banner-slide <?php echo $i === 0 ? 'active' : ''; ?>"
                 style="background-image: url('/Online-Car-Dealer-and-Inventory-System/Images/Uploads/Banners/<?php echo htmlspecialchars($b['Image_Path']); ?>');">
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (count($banners) > 1): ?>
    <button class="banner-arrow banner-prev" onclick="changeBanner(-1)">&#10094;</button>
    <button class="banner-arrow banner-next" onclick="changeBanner(1)">&#10095;</button>
    <div class="banner-dots">
        <?php foreach($banners as $i => $b): ?>
            <span class="banner-dot <?php echo $i === 0 ? 'active' : ''; ?>" onclick="goToBanner(<?php echo $i; ?>)"></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<?php else: ?>
<section class="hero-section">
    <div class="hero-content">
        <h1>Find Your Dream Second-Hand Car</h1>
        <p>Quality inspected vehicles, trusted dealers, and the best prices in Malaysia.</p>
        <a href="cars.php" class="btn-primary" style="font-size: 18px; padding: 15px 35px; margin-top: 10px;">Browse Inventory</a>
    </div>
</section>
<?php endif; ?>

<style>
.hero-banner { 
    position: relative; 
    width: 100%; 
    aspect-ratio: 16 / 4;    
    max-height: 600px;        
    overflow: hidden; 
    background: #0d1f3c; 
}
.banner-slides { position: relative; width: 100%; height: 100%; }
.banner-slide {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    background-size: cover; background-position: center; background-repeat: no-repeat;
    opacity: 0; transition: opacity 1s ease-in-out;
}
.banner-slide.active { opacity: 1; }
.banner-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(rgba(15,23,42,0.65), rgba(15,23,42,0.75));
    display: flex; align-items: center; justify-content: center;
}
.banner-content { text-align: center; color: #fff; max-width: 900px; padding: 0 20px; }
.banner-content h1 {
    font-size: 48px; font-weight: 800; margin-bottom: 18px;
    color: #fff; line-height: 1.2; text-shadow: 0 2px 10px rgba(0,0,0,0.4);
}
.banner-content p { font-size: 18px; margin-bottom: 28px; color: #e2e8f0; line-height: 1.6; }
.banner-btn { display: inline-block; font-size: 18px; padding: 15px 40px; margin-top: 10px; }
.banner-arrow {
    position: absolute; top: 50%; transform: translateY(-50%);
    background: rgba(255,255,255,0.2); color: #fff; border: none;
    width: 50px; height: 50px; border-radius: 50%; font-size: 20px;
    cursor: pointer; transition: background 0.25s; z-index: 5; backdrop-filter: blur(4px);
}
.banner-arrow:hover { background: rgba(255,255,255,0.4); }
.banner-prev { left: 25px; }
.banner-next { right: 25px; }
.banner-dots {
    position: absolute; bottom: 25px; left: 50%; transform: translateX(-50%);
    display: flex; gap: 10px; z-index: 5;
}
.banner-dot {
    width: 12px; height: 12px; border-radius: 50%;
    background: rgba(255,255,255,0.4); cursor: pointer; transition: all 0.25s;
}
.banner-dot.active { background: #fff; width: 32px; border-radius: 6px; }
@media (max-width: 768px) {
    .hero-banner { height: 250px; }
    .banner-content h1 { font-size: 30px; }
    .banner-content p { font-size: 15px; }
    .banner-arrow { width: 40px; height: 40px; font-size: 16px; }
    .banner-prev { left: 10px; } .banner-next { right: 10px; }
}
</style>

<script>
let currentBanner = 0;
const slides = document.querySelectorAll('.banner-slide');
const dots = document.querySelectorAll('.banner-dot');
const totalSlides = slides.length;
let bannerInterval;
function showBanner(index) {
    slides.forEach(s => s.classList.remove('active'));
    dots.forEach(d => d.classList.remove('active'));
    slides[index].classList.add('active');
    if (dots[index]) dots[index].classList.add('active');
    currentBanner = index;
}
function changeBanner(direction) {
    let next = (currentBanner + direction + totalSlides) % totalSlides;
    showBanner(next); resetInterval();
}
function goToBanner(index) { showBanner(index); resetInterval(); }
function resetInterval() {
    clearInterval(bannerInterval);
    bannerInterval = setInterval(() => changeBanner(1), 5000);
}
if (totalSlides > 1) { bannerInterval = setInterval(() => changeBanner(1), 5000); }
</script>
<section class="container" style="margin-top: 80px; margin-bottom: 80px;">
    <h2 class="section-title text-center">Top Sale Cars</h2>
    <p class="text-center" style="color: var(--text-light); margin-bottom: 40px;">Check out our most popular and highly-rated vehicles this week.</p>
    
    <div class="grid grid-3">
        <?php 
        if(count($top_cars) > 0) {
            foreach($top_cars as $car) { 
        ?>
            <div class="card">
                <img src="<?php echo !empty($car['image_url']) ? htmlspecialchars($car['image_url']) : 'https://images.unsplash.com/photo-1550486014-9f88c39d8dc9?auto=format&fit=crop&w=800&q=80'; ?>" alt="Car Image">
                <div class="card-body">
                    <h3 class="card-title"><?php echo htmlspecialchars($car['car_name']); ?></h3>
                    <p class="card-price">RM <?php echo number_format($car['price']); ?></p>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; font-size: 13px; color: var(--text-light); border-top: 1px solid #f1f5f9; padding-top: 12px;">
                        <span>🏎️ <?php echo htmlspecialchars($car['mileage']); ?> km</span>
                        <span>⛽ <?php echo htmlspecialchars($car['fuel_type']); ?></span>
                        <span>⚙️ <?php echo htmlspecialchars($car['transmission']); ?></span>
                    </div>
                    <a href="car_details.php?id=<?php echo $car['id']; ?>" class="btn-primary" style="width: 100%;">View Details</a>
                </div>
            </div>
        <?php 
            }
        } else {
            echo "<p style='grid-column: 1 / -1; text-align: center; color: var(--text-light);'>No cars available at the moment. Please check back later!</p>";
        }
        ?>
    </div>
    
    <div class="text-center" style="margin-top: 40px;">
        <a href="cars.php" class="auth-btn-secondary" style="display: inline-block; width: auto; padding: 12px 35px; border-radius: 8px; text-decoration: none; font-weight: 600;">View All Inventory</a>
    </div>
</section>

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