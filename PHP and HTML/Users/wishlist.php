<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'Includes/header.php';
require_once "../Config/database.php";

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
            c.transmission, c.fuel_type, c.car_mileage,
            t.car_type_name, l.location_city, s.car_status_price,
            (SELECT car_image_url FROM car_image WHERE car_id = c.car_id LIMIT 1) as car_image_url
        FROM wishlist w
        JOIN cars c ON w.car_id = c.car_id
        LEFT JOIN car_types t ON c.car_type_id = t.car_type_id
        LEFT JOIN locations l ON c.location_id = l.location_id
        LEFT JOIN car_status s ON c.car_id = s.car_id
        WHERE w.user_id = ?
        ORDER BY w.wishlist_id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $wishlist_cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<style>

    .side-drawer {
        position: fixed;
        top: 0;
        right: -100%;
        width: 100%;
        max-width: 520px;
        height: 100vh;
        background: #ffffff;
        box-shadow: -10px 0 40px rgba(15, 23, 42, 0.12);
        transition: right 0.4s cubic-bezier(0.22, 1, 0.36, 1);
        z-index: 9999;
        overflow: visible;
        border-left: 1px solid #e5e7eb;
    }

    .side-drawer.open {
        right: 0;
    }

    .drawer-content {
        padding: 0;
        position: relative;
        height: 100vh;
        overflow-y: auto;
        box-sizing: border-box;
        background: #ffffff;
    }

    .drawer-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(15, 23, 42, 0.35);
        backdrop-filter: blur(5px);
        z-index: 9998;
        opacity: 0;
        visibility: hidden;
        transition: 0.3s ease;
    }

    .drawer-overlay.show {
        opacity: 1;
        visibility: visible;
    }

    .toggle-drawer-edge-btn {
        position: absolute;
        top: 20px;
        left: 20px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: 1px solid #e5e7eb;
        background: #ffffff;
        color: #4b5563;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        transition: all 0.25s;
        z-index: 10005;
    }

    .toggle-drawer-edge-btn:hover {
        transform: scale(1.08);
        color: #111827;
        background: #f9fafb;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    }

    /* ==================== 2. 从 car.php 复制过来的 Car Card 样式 (完全一样) ==================== */
    .inventory-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 20px;
    }

    .pro-car-card {
        background: #fff;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border: 1px solid #e2e8f0;
        transition: transform 0.2s, box-shadow 0.2s;
        text-decoration: none !important;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .pro-car-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .card-img-container {
        position: relative;
        width: 100%;
        height: 160px;
    }

    .card-img-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        background: #f8fafc;
        display: block;
    }

    .badge-condition {
        position: absolute;
        bottom: 10px;
        left: 10px;
        background: rgba(255, 255, 255, 0.95);
        color: #0f172a;
        font-size: 11px;
        font-weight: 800;
        padding: 4px 8px;
        border-radius: 4px;
        text-transform: uppercase;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .btn-wishlist {
        position: absolute;
        bottom: 10px;
        right: 10px;
        background: transparent;
        border: none;
        color: #ffffff;
        cursor: pointer;
        transition: all 0.2s;
        padding: 0;
        opacity: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        filter: drop-shadow(0 2px 5px rgba(0, 0, 0, 0.5));
    }

    .pro-car-card:hover .btn-wishlist {
        opacity: 1;
    }

    .btn-wishlist:hover {
        transform: scale(1.15);
    }

    .btn-wishlist.liked {
        opacity: 1;
        color: #ef4444;
    }

    .btn-wishlist.liked svg {
        fill: none !important;
        stroke-width: 2.5;
    }

    .card-content {
        padding: 12px 15px;
        display: flex;
        flex-direction: column;
        flex: 1;
    }

    .card-title {
        font-size: 15px;
        font-weight: 800;
        color: #0f172a !important;
        margin: 0 0 4px 0;
        text-transform: uppercase;
        line-height: 1.3;
    }

    .card-price {
        font-size: 17px;
        font-weight: 800;
        color: #0f172a !important;
        margin: 0 0 10px 0;
    }

    .card-specs {
        font-size: 13px;
        color: #334155 !important;
        font-weight: 600;
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin-bottom: 12px;
    }

    .spec-row {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .spec-dot {
        color: #94a3b8;
        font-size: 12px;
    }

    .card-footer {
        margin-top: auto;
        border-top: none;
        padding-top: 0;
        font-size: 13px;
        color: #334155 !important;
        display: flex;
        align-items: center;
        text-transform: capitalize;
        font-weight: 600;
    }
</style>

<div class="inventory-page">
    <div class="inventory-wrapper">
        <main class="inventory-main">
            <div class="top-action-bar" style="margin-bottom: 30px;">
                <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0;">MY WISHLIST</h1>
                <p id="wishlist-count-text" style="color: #64748b; font-size: 14px; margin-top: 5px;">You have
                    <?php echo count($wishlist_cars); ?> saved vehicles.</p>
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
                            <a href="javascript:void(0);" onclick="openCarDetails(<?php echo $id; ?>)"
                                style="text-decoration: none; display: block; height: 100%;">
                                <div class="card-img-container">
                                    <img src="<?php echo $img; ?>" alt="<?php echo $title; ?>">
                                    <span class="badge-condition"><?php echo $condition; ?></span>

                                    <button class="btn-wishlist liked"
                                        onclick="removeFromWishlist(event, this, <?php echo $id; ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path
                                                d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z">
                                            </path>
                                        </svg>
                                    </button>
                                </div>

                                <div class="card-content">
                                    <h3 class="card-title"><?php echo $title; ?></h3>
                                    <div class="card-price">RM <?php echo $price; ?></div>

                                    <div class="card-specs">
                                        <div class="spec-row">
                                            <span><?php echo $car['car_year']; ?></span> <span class="spec-dot">•</span>
                                            <span><?php echo number_format($car['car_mileage']); ?> KM</span>
                                        </div>
                                        <div class="spec-row">
                                            <span><?php echo $car['transmission']; ?></span> <span class="spec-dot">•</span>
                                            <span><?php echo $car['fuel_type']; ?></span>
                                        </div>
                                    </div>

                                    <div class="card-footer">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" style="margin-right: 4px; margin-bottom: 2px;">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                            <circle cx="12" cy="10" r="3"></circle>
                                        </svg>
                                        <span><?php echo htmlspecialchars($car['location_city'] ?? 'N/A'); ?></span>
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
                        <a href="cars.php"
                            style="padding: 12px 30px; background: #0f172a; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">Browse
                            Cars</a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<div id="drawerOverlay" class="drawer-overlay" onclick="closeCarDetails()"></div>
<div id="car-details-sidebar" class="side-drawer">
    <button class="toggle-drawer-edge-btn" onclick="toggleMainSidebarSize()" title="Toggle Fullscreen">
        <svg id="expand-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7" />
        </svg>
    </button>
    <div class="drawer-content" id="drawer-content"></div>
</div>

<script>
    function removeFromWishlist(event, btnElement, carId) {
        event.preventDefault();
        event.stopPropagation();

        fetch('toggle_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `car_id=${carId}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'removed') {
                    const card = document.getElementById(`car-card-${carId}`);
                    if (card) {
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.9)';
                        setTimeout(() => {
                            card.remove();
                            let remainingCars = document.querySelectorAll('.pro-car-card').length;
                            let countTextElement = document.getElementById('wishlist-count-text');
                            if (countTextElement) {
                                countTextElement.innerText = "You have " + remainingCars + " saved vehicles.";
                            }
                            if (remainingCars === 0) {
                                location.reload();
                            }
                        }, 300);
                    }
                }
            })
            .catch(error => console.error('Error:', error));
    }

    window.toggleSidebarWishlist = function (event, btnElement, carId) {
        event.preventDefault();

        fetch('toggle_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `car_id=${carId}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'not_logged_in') {
                    window.location.href = 'Auth/login.php';
                    return;
                }

                if (data.status === 'added') {
                    btnElement.classList.add('liked');
                } else if (data.status === 'removed') {
                    btnElement.classList.remove('liked');

                    const card = document.getElementById(`car-card-${carId}`);
                    if (card) {
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.9)';
                        setTimeout(() => {
                            card.remove();
                            let remainingCars = document.querySelectorAll('.pro-car-card').length;
                            let countTextElement = document.getElementById('wishlist-count-text');
                            if (countTextElement) countTextElement.innerText = "You have " + remainingCars + " saved vehicles.";
                            if (remainingCars === 0) location.reload();
                        }, 300);
                    }
                }
            })
            .catch(error => console.error('Error toggling wishlist:', error));
    };

    function openCarDetails(carId) {
        const drawer = document.getElementById('car-details-sidebar');
        const content = document.getElementById('drawer-content');
        const overlay = document.getElementById('drawerOverlay');

        drawer.style.maxWidth = '520px';
        drawer.classList.add('open');
        overlay.classList.add('show');
        content.innerHTML = `<div style="text-align: center; padding: 100px; color:#64748b;">Loading details... 🚗💨</div>`;

        fetch('fetch_car_details_sidebar.php?id=' + carId)
            .then(response => response.text())
            .then(html => {
                content.innerHTML = html;
                initSidebarFeatures();
            })
            .catch(err => {
                console.error(err);
                content.innerHTML = `<div style="text-align: center; padding: 100px; color:red;">Failed to load data.</div>`;
            });
    }

    function closeCarDetails() {
        document.getElementById('car-details-sidebar').classList.remove('open');
        document.getElementById('drawerOverlay').classList.remove('show');
    }

    function toggleMainSidebarSize() {
        const sidebar = document.getElementById('car-details-sidebar');
        const iconContainer = document.getElementById('expand-icon');
        if (sidebar.style.maxWidth === '100%') {
            sidebar.style.maxWidth = '520px';
            if (iconContainer) iconContainer.innerHTML = '<path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>';
        } else {
            sidebar.style.maxWidth = '100%';
            if (iconContainer) iconContainer.innerHTML = '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>';
        }
    }

    window.imagesArray = [];
    window.CAR_PRICE = 0;
    window.currentImgIndex = 0;

    function initSidebarFeatures() {
        try {
            const dataEl = document.getElementById('gallery-data');
            const rawData = dataEl ? dataEl.getAttribute('data-images') : '[]';
            window.imagesArray = JSON.parse(rawData);
        } catch (e) { console.error("Gallery parse error:", e); }

        window.CAR_PRICE = parseFloat(document.getElementById('car-price-data')?.value) || 0;
        window.currentImgIndex = 0;

        const role = document.getElementById('current-user-role')?.value || 'Customer';
        const warningText = document.getElementById('dp-warning-text');
        if (role === 'Admin' || role === 'Super Admin') {
            if (warningText) {
                warningText.innerText = "*Admin Access: Custom down payment allowed.";
                warningText.style.color = "#3b82f6";
            }
        }

        if (window.CAR_PRICE > 0) {
            calcFromPct();
        } else {
            const amtEl = document.getElementById('dp-amt');
            const resEl = document.getElementById('monthly-result');
            if (amtEl) amtEl.value = 0;
            if (resEl) resEl.innerText = 'RM 0';
        }
    }

    function showImg(index) {
        if (window.imagesArray.length === 0) return;
        if (index < 0) index = window.imagesArray.length - 1;
        if (index >= window.imagesArray.length) index = 0;

        window.currentImgIndex = index;
        const mainImg = document.getElementById('main-gallery-img');
        if (mainImg) mainImg.src = window.imagesArray[window.currentImgIndex];

        const thumbs = document.querySelectorAll('.thumb-img');
        thumbs.forEach((t, i) => {
            if (i === window.currentImgIndex) t.classList.add('active');
            else t.classList.remove('active');
        });
    }

    function prevImg() { showImg(window.currentImgIndex - 1); }
    function nextImg() { showImg(window.currentImgIndex + 1); }

    function toggleMoreSpecs() {
        const box = document.getElementById('more-specs-box');
        const btn = document.getElementById('toggleSpecsBtn');
        if (!box || !btn) return;

        if (box.style.display === 'none' || box.style.display === '') {
            box.style.display = 'block';
            btn.innerHTML = 'View Less';
        } else {
            box.style.display = 'none';
            btn.innerHTML = 'View Full Specifications';
        }
    }

    function getMinPct() {
        const role = document.getElementById('current-user-role')?.value || 'Customer';
        return (role === 'Admin' || role === 'Super Admin') ? 0 : 10;
    }

    function enforceMinPct() {
        const pctEl = document.getElementById('dp-pct');
        if (!pctEl) return;
        let minPct = getMinPct();
        if (parseFloat(pctEl.value) < minPct || pctEl.value === '') {
            pctEl.value = minPct;
            calcFromPct();
        }
    }

    function enforceMinAmt() {
        const amtEl = document.getElementById('dp-amt');
        if (!amtEl) return;
        let minPct = getMinPct();
        let minAmt = window.CAR_PRICE * (minPct / 100);
        if (parseFloat(amtEl.value) < minAmt || amtEl.value === '') {
            amtEl.value = Math.round(minAmt);
            calcFromAmt();
        }
    }

    function calcFromPct() {
        if (window.CAR_PRICE <= 0) return;
        const pctEl = document.getElementById('dp-pct');
        const amtEl = document.getElementById('dp-amt');
        if (!pctEl || !amtEl) return;
        let pct = parseFloat(pctEl.value) || 0;
        let amt = window.CAR_PRICE * (pct / 100);
        amtEl.value = Math.round(amt);
        calculateMonthlyLoan();
    }

    function calcFromAmt() {
        if (window.CAR_PRICE <= 0) return;
        const pctEl = document.getElementById('dp-pct');
        const amtEl = document.getElementById('dp-amt');
        if (!pctEl || !amtEl) return;
        let amt = parseFloat(amtEl.value) || 0;
        let pct = (amt / window.CAR_PRICE) * 100;
        pctEl.value = pct.toFixed(1);
        calculateMonthlyLoan();
    }

    function updateTenure() {
        const slider = document.getElementById('tenure-slider');
        const valLabel = document.getElementById('tenure-val');
        if (!slider || !valLabel) return;
        let years = slider.value;
        valLabel.innerText = years + (years == 1 ? ' Year' : ' Years');
        calculateMonthlyLoan();
    }

    function calculateMonthlyLoan() {
        const resultEl = document.getElementById('monthly-result');
        if (!resultEl) return;
        if (window.CAR_PRICE <= 0) {
            resultEl.innerText = 'N/A';
            return;
        }

        let dpAmt = parseFloat(document.getElementById('dp-amt')?.value) || 0;
        let rate = parseFloat(document.getElementById('int-rate')?.value) || 0;
        let years = parseInt(document.getElementById('tenure-slider')?.value) || 9;

        let loanAmount = window.CAR_PRICE - dpAmt;
        if (loanAmount < 0) loanAmount = 0;

        let totalInterest = loanAmount * (rate / 100) * years;
        let totalPayable = loanAmount + totalInterest;
        let monthly = years > 0 ? totalPayable / (years * 12) : 0;

        resultEl.innerText = 'RM ' + Math.round(monthly).toLocaleString();
    }

    function resetCalc() {
        if (document.getElementById('dp-pct')) document.getElementById('dp-pct').value = "10";
        if (document.getElementById('tenure-slider')) document.getElementById('tenure-slider').value = 9;
        if (document.getElementById('int-rate')) document.getElementById('int-rate').value = 3.0;
        updateTenure();
        calcFromPct();
    }
</script>

<?php include 'Includes/footer.php'; ?>