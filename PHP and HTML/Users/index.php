<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_id = $_SESSION['id'] ?? 0;

require_once "../Config/database.php";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("PDO Connection failed: " . $e->getMessage());
}

$sql_banner = "SELECT * FROM homepage_banners WHERE Is_Active = 'Yes' ORDER BY Display_Order ASC";
$stmt_b = $pdo->prepare($sql_banner);
$stmt_b->execute();
$banners = $stmt_b->fetchAll(PDO::FETCH_ASSOC);

$sql_models = "
    SELECT 
        c.car_model, 
        c.car_brand
    FROM cars c
    JOIN car_status s ON c.car_id = s.car_id
    WHERE s.car_status_status = 'Active' 
    AND s.car_status_stock_quantity > 0 
    AND c.car_model != ''
    GROUP BY c.car_model, c.car_brand
    ORDER BY c.car_brand, c.car_model ASC
";
$stmt_m = $pdo->prepare($sql_models);
$stmt_m->execute();
$all_models = $stmt_m->fetchAll(PDO::FETCH_ASSOC);

$premium_images = [
    'SAGA' => 'Model_image/Saga.png',
    'PERSONA' => 'Model_image/Persona.png',
    'IRIZ' => 'Model_image/Iriz.png',
    'X50' => 'Model_image/X50.png',
    'X70' => 'Model_image/X70.png',
    'X90' => 'Model_image/X90.png',
    'S70' => 'Model_image/S70.png',
    'E.MAS 7' => 'Model_image/Emas7.png',
    'EMAS 7' => 'Model_image/Emas7.png',
    'EMAS7' => 'Model_image/Emas7.png',
    '7' => 'Model_image/Emas7.png',
    'E.MAS 5' => 'Model_image/Emas5.png',
    'EMAS 5' => 'Model_image/Emas5.png',
    'EMAS5' => 'Model_image/Emas5.png',
    '5' => 'Model_image/Emas5.png'
];

include 'Includes/header.php';
?>

<style>
    .hero-banner {
        position: relative;
        width: 100%;
        aspect-ratio: 16 / 4;
        max-height: 600px;
        overflow: hidden;
        background: #0d1f3c;
    }

    .banner-slides {
        position: relative;
        width: 100%;
        height: 100%;
    }

    .banner-slide {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        opacity: 0;
        transition: opacity 1s ease-in-out;
    }

    .banner-slide.active {
        opacity: 1;
    }

    .banner-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(255, 255, 255, 0.2);
        color: #fff;
        border: none;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        font-size: 20px;
        cursor: pointer;
        transition: background 0.25s;
        z-index: 5;
        backdrop-filter: blur(4px);
    }

    .banner-arrow:hover {
        background: rgba(255, 255, 255, 0.4);
    }

    .banner-prev {
        left: 25px;
    }

    .banner-next {
        right: 25px;
    }

    .banner-dots {
        position: absolute;
        bottom: 25px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 10px;
        z-index: 5;
    }

    .banner-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.4);
        cursor: pointer;
        transition: all 0.25s;
    }

    .banner-dot.active {
        background: #fff;
        width: 32px;
        border-radius: 6px;
    }

    .home-container {
        width: 100%;
        padding: 0 4%;
        margin: 0 auto;
        box-sizing: border-box;
    }

    .section-title {
        font-size: 36px;
        font-weight: 900;
        color: #0f172a;
        margin-bottom: 8px;
        letter-spacing: -0.5px;
    }

    .section-subtitle {
        color: #64748b;
        font-size: 16px;
        font-weight: 500;
        margin-bottom: 0;
    }

    .premium-slider-section {
        margin: 80px 0;
        overflow: hidden;
    }

    .slider-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 40px;
    }

    .slider-nav {
        display: flex;
        gap: 12px;
    }

    .slider-nav button {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        border: 1px solid #cbd5e1;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        color: #0f172a;
    }

    .slider-nav button:hover {
        background: #0f172a;
        border-color: #0f172a;
        color: #fff;
        transform: scale(1.05);
    }

    .models-slider-container {
        display: flex;
        gap: 24px;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        scrollbar-width: none;
        scroll-behavior: smooth;
        padding-bottom: 30px;
    }

    .models-slider-container::-webkit-scrollbar {
        display: none;
    }

    .premium-model-card {
        width: calc(25% - 18px);
        flex: 0 0 calc(25% - 18px);
        aspect-ratio: 16 / 9;
        height: auto;
        border-radius: 16px;
        position: relative;
        overflow: hidden;
        scroll-snap-align: start;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        display: block;
        background: #0f172a;
    }

    .premium-model-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(15, 23, 42, 0.18);
    }

    .card-bg-placeholder {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0.1;
        color: #ffffff;
    }

    .premium-model-card img {
        position: absolute;
        inset: 0;
        z-index: 1;
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        opacity: 0;
        transition: transform 0.7s cubic-bezier(0.25, 0.8, 0.25, 1), opacity 0.5s ease;
    }

    .premium-model-card img.loaded {
        opacity: 1;
    }

    .premium-model-card:hover img {
        transform: scale(1.05);
    }

    .model-card-overlay {
        position: absolute;
        inset: 0;
        z-index: 2;
        background: linear-gradient(to top, rgba(15, 23, 42, 0.95) 0%, rgba(15, 23, 42, 0.3) 50%, rgba(15, 23, 42, 0) 100%);
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 25px 20px;
    }

    .model-card-title {
        color: #ffffff;
        font-size: 22px;
        font-weight: 900;
        margin: 0 0 6px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transform: translateY(10px);
        transition: transform 0.4s ease;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
    }

    .model-card-link {
        color: #94a3b8;
        font-size: 13px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.4s ease;
        opacity: 0.9;
        transform: translateY(10px);
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
    }

    .premium-model-card:hover .model-card-title {
        transform: translateY(0);
    }

    .premium-model-card:hover .model-card-link {
        color: #ffffff;
        gap: 12px;
        opacity: 1;
        transform: translateY(0);
    }

    .steps-section {
        padding: 80px 0;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }

    .steps-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 30px;
        margin-top: 50px;
    }

    .step-card {
        background: #fff;
        padding: 40px 20px 30px;
        border-radius: 16px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        position: relative;
        transition: transform 0.3s ease;
    }

    .step-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(15, 23, 42, 0.08);
    }

    .step-number {
        position: absolute;
        top: -20px;
        left: 50%;
        transform: translateX(-50%);
        width: 40px;
        height: 40px;
        background: #0f172a;
        color: #fff;
        font-size: 20px;
        font-weight: 900;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        border: 4px solid #f8fafc;
    }

    .step-icon-wrapper {
        width: 64px;
        height: 64px;
        background: #f1f5f9;
        color: #0f172a;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        transition: 0.3s ease;
    }

    .step-card:hover .step-icon-wrapper {
        background: #0f172a;
        color: #ffffff;
        transform: scale(1.1);
    }

    .step-card h3 {
        font-size: 18px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 10px;
        text-transform: uppercase;
    }

    .step-card p {
        font-size: 14px;
        color: #64748b;
        line-height: 1.6;
        margin: 0;
    }

    .features-section {
        padding: 80px 0;
        background: #fff;
        border-top: 1px solid #e2e8f0;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-top: 40px;
    }

    .feature-box {
        text-align: center;
        padding: 30px 20px;
        border-radius: 16px;
        transition: 0.3s ease;
        border: 1px solid transparent;
    }

    .feature-box:hover {
        background: #ffffff;
        border-color: #e2e8f0;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        transform: translateY(-5px);
    }

    .feature-icon-wrapper {
        width: 72px;
        height: 72px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #2563eb;
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        transition: 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
    }

    .feature-box:hover .feature-icon-wrapper {
        background: #2563eb;
        color: #ffffff;
        border-color: #2563eb;
        transform: translateY(-8px) rotate(5deg);
        box-shadow: 0 15px 25px rgba(37, 99, 235, 0.25);
    }

    .feature-box h3 {
        color: #0f172a;
        font-weight: 800;
        font-size: 20px;
        margin-bottom: 12px;
    }

    .feature-box p {
        color: #64748b;
        font-size: 15px;
        line-height: 1.6;
        margin: 0;
    }

    @media (max-width: 1400px) {
        .premium-model-card {
            width: calc(33.333% - 16px);
            flex: 0 0 calc(33.333% - 16px);
        }
    }

    @media (max-width: 1024px) {
        .steps-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 50px 20px;
            margin-top: 40px;
        }

        .premium-model-card {
            width: calc(50% - 12px);
            flex: 0 0 calc(50% - 12px);
        }
    }

    @media (max-width: 768px) {
        .hero-banner {
            height: 250px;
        }

        .slider-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 20px;
        }

        .features-grid {
            grid-template-columns: 1fr;
        }

        .premium-model-card {
            width: 100%;
            flex: 0 0 100%;
        }

        .steps-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php if (count($banners) > 0): ?>
    <section class="hero-banner">
        <div class="banner-slides">
            <?php foreach ($banners as $i => $b): ?>
                <div class="banner-slide <?php echo $i === 0 ? 'active' : ''; ?>"
                    style="background-image: url('/Online-Car-Dealer-and-Inventory-System/Images/Uploads/Banners/<?php echo htmlspecialchars($b['Image_Path']); ?>');">
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($banners) > 1): ?>
            <button class="banner-arrow banner-prev" onclick="changeBanner(-1)">&#10094;</button>
            <button class="banner-arrow banner-next" onclick="changeBanner(1)">&#10095;</button>
            <div class="banner-dots">
                <?php foreach ($banners as $i => $b): ?>
                    <span class="banner-dot <?php echo $i === 0 ? 'active' : ''; ?>" onclick="goToBanner(<?php echo $i; ?>)"></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <script>
        let currentBanner = 0; const slides = document.querySelectorAll('.banner-slide'); const dots = document.querySelectorAll('.banner-dot'); const totalSlides = slides.length; let bannerInterval;
        function showBanner(index) { slides.forEach(s => s.classList.remove('active')); dots.forEach(d => d.classList.remove('active')); slides[index].classList.add('active'); if (dots[index]) dots[index].classList.add('active'); currentBanner = index; }
        function changeBanner(direction) { let next = (currentBanner + direction + totalSlides) % totalSlides; showBanner(next); resetInterval(); }
        function goToBanner(index) { showBanner(index); resetInterval(); }
        function resetInterval() { clearInterval(bannerInterval); bannerInterval = setInterval(() => changeBanner(1), 5000); }
        if (totalSlides > 1) { bannerInterval = setInterval(() => changeBanner(1), 5000); }
    </script>
<?php endif; ?>

<section class="premium-slider-section">
    <div class="home-container">

        <div class="slider-header">
            <div>
                <h2 class="section-title">Explore Our Models</h2>
                <p class="section-subtitle">Discover the perfect vehicle for your lifestyle.</p>
            </div>

            <div class="slider-nav">
                <button onclick="slideModels(-1)">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                <button onclick="slideModels(1)">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            </div>
        </div>

        <div class="models-slider-container" id="modelsSlider">
            <?php
            if (count($all_models) > 0) {
                foreach ($all_models as $m):
                    $modelName = htmlspecialchars($m['car_model']);
                    $brandName = htmlspecialchars($m['car_brand']);
                    $displayName = (stripos($brandName, 'e.mas') !== false) ? 'e.MAS ' . $modelName : $brandName . ' ' . $modelName;

                    $key = strtoupper(trim($m['car_model']));
                    $img = isset($premium_images[$key]) ? $premium_images[$key] : '';
                    ?>
                    <a href="cars.php?model=<?php echo urlencode($modelName); ?>" class="premium-model-card">

                        <div class="card-bg-placeholder">
                            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path
                                    d="M14 16H9m10 0h3v-3.15a1 1 0 0 0-.84-.99L16 11l-2.7-3.6a2 2 0 0 0-1.6-.8H9.3a2 2 0 0 0-1.6.8L5 11l-5.16.86a1 1 0 0 0-.84.99V16h3m10 0a3 3 0 1 1-6 0m10 0a3 3 0 1 1-6 0M3 16a3 3 0 1 1-6 0">
                                </path>
                            </svg>
                        </div>

                        <img src="<?php echo $img; ?>" alt="<?php echo $displayName; ?>" onload="this.classList.add('loaded')"
                            onerror="this.style.display='none'">

                        <div class="model-card-overlay">
                            <h3 class="model-card-title"><?php echo $displayName; ?></h3>
                            <span class="model-card-link">
                                View Available Cars
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                    <polyline points="12 5 19 12 12 19"></polyline>
                                </svg>
                            </span>
                        </div>
                    </a>
                <?php
                endforeach;
            } else {
                echo "<div style='width: 100%; text-align: center; padding: 40px; color: #64748b;'>No models available right now.</div>";
            }
            ?>
        </div>
    </div>
</section>

<script>
    const slider = document.getElementById('modelsSlider');
    function slideModels(direction) {
        const card = slider.querySelector('.premium-model-card');
        if (card) {
            const scrollAmount = card.offsetWidth + 24;
            slider.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
        }
    }
    let autoScroll = setInterval(() => {
        if (slider.scrollLeft + slider.clientWidth >= slider.scrollWidth - 10) {
            slider.scrollTo({ left: 0, behavior: 'smooth' });
        } else {
            slideModels(1);
        }
    }, 4000);
    slider.addEventListener('mouseenter', () => clearInterval(autoScroll));
    slider.addEventListener('mouseleave', () => {
        autoScroll = setInterval(() => {
            if (slider.scrollLeft + slider.clientWidth >= slider.scrollWidth - 10) {
                slider.scrollTo({ left: 0, behavior: 'smooth' });
            } else {
                slideModels(1);
            }
        }, 4000);
    });
</script>

<section class="steps-section">
    <div class="home-container">
        <div style="text-align: center; margin-bottom: 20px;">
            <h2 class="section-title">How It Works</h2>
            <p class="section-subtitle">Get your dream car in 4 simple and completely transparent steps.</p>
        </div>

        <div class="steps-grid">
            <div class="step-card">
                <div class="step-number">1</div>
                <div class="step-icon-wrapper">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </div>
                <h3>Browse & Select</h3>
                <p>Explore our wide inventory online, filter by your preferences, and choose your perfect Proton.</p>
            </div>

            <div class="step-card">
                <div class="step-number">2</div>
                <div class="step-icon-wrapper">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path
                            d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4">
                        </path>
                    </svg>
                </div>
                <h3>Book Test Drive</h3>
                <p>Schedule a visit to our showroom to inspect the car firsthand and experience the drive.</p>
            </div>

            <div class="step-card">
                <div class="step-number">3</div>
                <div class="step-icon-wrapper">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                        <line x1="1" y1="10" x2="23" y2="10"></line>
                    </svg>
                </div>
                <h3>Easy Financing</h3>
                <p>Apply for a loan with our fast-approval partners to get the best interest rates available.</p>
            </div>

            <div class="step-card">
                <div class="step-number">4</div>
                <div class="step-icon-wrapper">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
                <h3>Drive It Home</h3>
                <p>Sign the hassle-free paperwork and drive away with complete peace of mind and warranty.</p>
            </div>
        </div>
    </div>
</section>

<section class="features-section">
    <div class="home-container">
        <h2 class="section-title" style="text-align:center; margin-top:0;">Why Choose LCWcar?</h2>
        <p class="section-subtitle" style="text-align:center;">We provide a premium car buying experience with total
            transparency.</p>

        <div class="features-grid">
            <div class="feature-box">
                <div class="feature-icon-wrapper">
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                </div>
                <h3>Fast & Flexible Loans</h3>
                <p>We provide seamless in-house and bank financing options with low-interest rates to suit your budget.
                </p>
            </div>

            <div class="feature-box">
                <div class="feature-icon-wrapper">
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                        <line x1="7" y1="7" x2="7.01" y2="7"></line>
                    </svg>
                </div>
                <h3>Best Price Guarantee</h3>
                <p>No hidden fees. We offer straightforward and transparent pricing for your complete peace of mind.</p>
            </div>

            <div class="feature-box">
                <div class="feature-icon-wrapper">
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                </div>
                <h3>Trusted & Verified</h3>
                <p>All our cars come from reliable sources, and we handle all the tedious paperwork for you.</p>
            </div>
        </div>
    </div>
</section>

<?php include 'Includes/footer.php'; ?>