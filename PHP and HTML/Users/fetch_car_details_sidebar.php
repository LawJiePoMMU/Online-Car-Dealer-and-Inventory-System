<?php
// 1. 确保 Session 开启并读取用户角色 (没登录默认当做 Customer)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_role = $_SESSION['user_role'] ?? 'Customer';
$user_id = $_SESSION['id'] ?? 0;

require_once "../Config/database.php";

// 2. Establish Database Connection
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("PDO Connection failed: " . $e->getMessage());
}

// 3. Get Car ID
$car_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($car_id == 0) {
    echo "<div style='padding: 50px; text-align: center; color: #64748b;'>Invalid Car ID.</div>";
    exit;
}

try {
    // 4. Fetch Core Car Details
    $stmt = $pdo->prepare("
        SELECT c.*, s.car_status_price, s.car_status_stock_quantity, s.car_status_status
        FROM cars c 
        LEFT JOIN car_status s ON c.car_id = s.car_id 
        WHERE c.car_id = ? LIMIT 1
    ");
    $stmt->execute([$car_id]);
    $car = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$car) {
        echo "<div style='padding: 50px; text-align: center; color: #64748b;'>Car not found.</div>";
        exit;
    }

    // 检查当前用户是否已经把这辆车加入了 Wishlist
    $is_wished = false;
    if ($user_id > 0) {
        $wish_stmt = $pdo->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND car_id = ? LIMIT 1");
        $wish_stmt->execute([$user_id, $car_id]);
        $is_wished = (bool) $wish_stmt->fetchColumn();
    }

    // Fetch Variant & Colors
    $stmt = $pdo->prepare("SELECT variant FROM car_inventory WHERE car_id = ? AND variant != '' LIMIT 1");
    $stmt->execute([$car_id]);
    $variantData = $stmt->fetch(PDO::FETCH_ASSOC);
    $variant = $variantData ? $variantData['variant'] : '-';

    $stmt = $pdo->prepare("SELECT DISTINCT color_name, color_hex FROM car_inventory WHERE car_id = ?");
    $stmt->execute([$car_id]);
    $colors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Fetch Images
    $stmt = $pdo->prepare("SELECT car_image_url FROM car_image WHERE car_id = ?");
    $stmt->execute([$car_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $mainImg = !empty($images) ? $images[0]['car_image_url'] : 'https://images.unsplash.com/photo-1550486014-9f88c39d8dc9?auto=format&fit=crop&w=800&q=80';
    $imageUrls = !empty($images) ? array_column($images, 'car_image_url') : [$mainImg];

    // 6. Fetch ALL Specifications
    $stmt = $pdo->prepare("SELECT * FROM car_engine_specs WHERE car_id = ?");
    $stmt->execute([$car_id]);
    $engine = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare("SELECT * FROM car_dimensions WHERE car_id = ?");
    $stmt->execute([$car_id]);
    $dimension = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare("SELECT * FROM car_brake_specs WHERE car_id = ?");
    $stmt->execute([$car_id]);
    $brakes = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare("SELECT * FROM car_suspension_specs WHERE car_id = ?");
    $stmt->execute([$car_id]);
    $suspension = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare("SELECT * FROM car_steering_specs WHERE car_id = ?");
    $stmt->execute([$car_id]);
    $steering = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare("SELECT * FROM car_tyre_specs WHERE car_id = ?");
    $stmt->execute([$car_id]);
    $tyres = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare("SELECT * FROM car_features WHERE car_id = ?");
    $stmt->execute([$car_id]);
    $features = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // 7. EV Specs
    $ev = [];
    if (strcasecmp(trim($car['fuel_type'] ?? ''), 'Electric') === 0 || strcasecmp(trim($car['fuel_type'] ?? ''), 'EV') === 0) {
        $stmt = $pdo->prepare("SELECT * FROM car_ev_specs WHERE car_id = ?");
        $stmt->execute([$car_id]);
        $ev = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // 8. Used Car Details
    $used_car = [];
    if (($car['car_origin'] ?? '') === 'Used Car') {
        $stmt = $pdo->prepare("SELECT * FROM used_car_details WHERE car_id = ?");
        $stmt->execute([$car_id]);
        $used_car = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    $car_price = floatval($car['car_status_price'] ?? 0);

    $fin_defaults = [];
    foreach ($pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('default_dp_percent','default_loan_rate')")->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $fin_defaults[$s['setting_key']] = $s['setting_value'];
    }
    $default_dp_pct = isset($fin_defaults['default_dp_percent']) ? floatval($fin_defaults['default_dp_percent']) : 10;
    $default_loan_rate = isset($fin_defaults['default_loan_rate']) ? floatval($fin_defaults['default_loan_rate']) : 3.0;

} catch (PDOException $e) {
    die("<div style='padding: 50px; text-align: center; color: red;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>

<div id="gallery-data"
    data-images="<?php echo htmlspecialchars(json_encode(array_values($imageUrls)), ENT_QUOTES, 'UTF-8'); ?>"
    style="display:none;"></div>
<input type="hidden" id="car-price-data" value="<?php echo $car_price; ?>">

<style>
    /* ================= 高级感 UI 核心重置 ================= */
    .detail-wrapper {
        width: 100%;
        max-width: 1100px;
        margin: 0 auto;
        padding: 20px;
        box-sizing: border-box;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        color: #111827;
    }

    .gallery-container {
        position: relative;
        width: 100%;
        margin-bottom: 30px;
    }

    .main-img-wrapper {
        position: relative;
        width: 100%;
        aspect-ratio: 16 / 9;
        max-height: 500px;
        background: #f3f4f6;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    #main-gallery-img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        mix-blend-mode: multiply;
    }

    .arrow-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: #ffffff;
        color: #111827;
        border: 1px solid #e5e7eb;
        width: 40px;
        height: 40px;
        cursor: pointer;
        border-radius: 50%;
        font-size: 18px;
        font-weight: bold;
        z-index: 50;
        display: flex;
        justify-content: center;
        align-items: center;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        transition: 0.2s ease;
    }

    .arrow-btn:hover {
        background: #f9fafb;
        transform: translateY(-50%) scale(1.05);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }

    .arrow-left {
        left: 15px;
    }

    .arrow-right {
        right: 15px;
    }

    .thumbs-container {
        display: flex;
        gap: 10px;
        margin-top: 12px;
        overflow-x: auto;
        padding-bottom: 8px;
    }

    .thumb-img {
        width: 90px;
        height: 64px;
        object-fit: cover;
        border-radius: 6px;
        cursor: pointer;
        border: 2px solid transparent;
        opacity: 0.5;
        transition: 0.2s;
        background: #fff;
    }

    .thumb-img:hover,
    .thumb-img.active {
        border-color: #111827;
        opacity: 1;
    }

    .thumbs-container::-webkit-scrollbar {
        height: 4px;
    }

    .thumbs-container::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 4px;
    }

    /* ================= 标题与心愿单按钮 ================= */
    .title-area {
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .title-area-text {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .title-area h2 {
        margin: 0;
        font-size: 32px;
        color: #111827;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .title-area .price {
        color: #111827;
        font-size: 24px;
        font-weight: 700;
        display: block;
    }

    .clean-wishlist-btn {
        background: transparent;
        border: none;
        padding: 8px;
        cursor: pointer;
        color: #94a3b8;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: 4px;
        outline: none;
    }

    .clean-wishlist-btn svg {
        width: 28px;
        height: 28px;
        stroke: currentColor;
        stroke-width: 2;
        fill: none !important;
        transition: 0.2s;
    }

    .clean-wishlist-btn:hover {
        transform: scale(1.15);
        color: #ef4444;
    }

    .clean-wishlist-btn.liked {
        color: #ef4444;
    }

    .clean-wishlist-btn.liked svg {
        stroke: #ef4444;
        stroke-width: 2.5;
    }

    /* ================= 规格网格 ================= */
    .section-title {
        color: #111827;
        font-size: 20px;
        font-weight: 700;
        margin: 40px 0 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e5e7eb;
        letter-spacing: -0.01em;
    }

    .sub-title {
        color: #4b5563;
        font-size: 15px;
        font-weight: 600;
        margin: 20px 0 12px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .car-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 16px;
    }

    .grid-item {
        background: #ffffff;
        padding: 16px;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        display: flex;
        flex-direction: column;
        justify-content: center;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.02);
    }

    .grid-item.full-width {
        grid-column: 1 / -1;
    }

    .grid-item span {
        font-size: 12px;
        color: #6b7280;
        font-weight: 500;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .grid-item strong {
        font-size: 15px;
        color: #111827;
        font-weight: 600;
        line-height: 1.4;
    }

    .badge-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 30px;
    }

    @media (max-width: 600px) {
        .badge-row {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    .btn-toggle {
        background: #ffffff;
        color: #111827;
        border: 1px solid #d1d5db;
        padding: 12px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        width: 100%;
        margin-top: 20px;
        text-align: center;
        transition: 0.2s;
        font-size: 14px;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.03);
    }

    .btn-toggle:hover {
        background: #f9fafb;
        border-color: #9ca3af;
    }

    .color-swatch-wrapper {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: 30px;
    }

    .color-swatch {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: 1px solid #d1d5db;
        cursor: pointer;
        transition: 0.2s;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .color-swatch:hover {
        transform: scale(1.1);
        border-color: #111827;
    }

    /* ================= Finance Calculator ================= */
    .fin-wrapper {
        display: flex;
        flex-wrap: wrap;
        gap: 24px;
        margin-top: 20px;
        align-items: stretch;
    }

    .fin-left {
        flex: 1.2;
        min-width: 300px;
        border: 1px solid #e5e7eb;
        padding: 24px;
        border-radius: 12px;
        background: #ffffff;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    }

    .fin-price-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px dashed #d1d5db;
        padding-bottom: 16px;
        margin-bottom: 24px;
    }

    .fin-price-row span {
        font-size: 15px;
        color: #6b7280;
        font-weight: 500;
    }

    .fin-price-row strong {
        font-size: 20px;
        color: #111827;
        font-weight: 700;
    }

    .fin-form-group {
        margin-bottom: 24px;
    }

    .fin-form-group label {
        display: flex;
        justify-content: space-between;
        font-weight: 600;
        font-size: 14px;
        color: #374151;
        margin-bottom: 8px;
        align-items: center;
    }

    .custom-combo-input {
        display: flex;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        overflow: hidden;
        background: #fff;
        transition: border-color 0.2s;
    }

    .custom-combo-input:focus-within {
        border-color: #111827;
    }

    .custom-combo-input input {
        border: none;
        padding: 12px 16px;
        font-size: 15px;
        font-weight: 500;
        color: #111827;
        outline: none;
        background: transparent;
    }

    .custom-combo-input #dp-amt {
        flex: 1;
        min-width: 0;
    }

    .custom-combo-input .vertical-divider {
        width: 1px;
        background: #e5e7eb;
        margin: 8px 0;
    }

    .custom-combo-input .pct-section {
        display: flex;
        align-items: center;
        background: #fff;
        padding-right: 12px;
    }

    .custom-combo-input #dp-pct {
        width: 65px;
        text-align: right;
        padding-right: 4px;
    }

    .custom-combo-input .pct-sign {
        color: #6b7280;
        font-size: 14px;
        font-weight: 500;
    }

    .basic-input {
        width: 100%;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 12px 16px;
        font-size: 15px;
        outline: none;
        font-weight: 500;
        color: #111827;
        transition: 0.2s;
    }

    .basic-input:focus {
        border-color: #111827;
    }

    input[type=range] {
        -webkit-appearance: none;
        width: 100%;
        background: transparent;
        margin-top: 10px;
    }

    input[type=range]::-webkit-slider-thumb {
        -webkit-appearance: none;
        height: 20px;
        width: 20px;
        border-radius: 50%;
        background: #ffffff;
        border: 4px solid #111827;
        cursor: pointer;
        margin-top: -8px;
    }

    input[type=range]::-webkit-slider-runnable-track {
        width: 100%;
        height: 4px;
        cursor: pointer;
        background: #d1d5db;
        border-radius: 2px;
    }

    .fin-right {
        flex: 1;
        min-width: 300px;
        background: #27272a;
        color: #ffffff;
        border-radius: 12px;
        padding: 32px 24px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .fin-right-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .fin-right-header span {
        font-size: 13px;
        font-weight: 500;
        color: #a1a1aa;
    }

    .reset-btn {
        background: transparent;
        color: #ffffff;
        border: 1px solid #52525b;
        border-radius: 20px;
        padding: 6px 14px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 500;
        transition: 0.2s;
    }

    .reset-btn:hover {
        background: #3f3f46;
        border-color: #71717a;
    }

    .fin-result {
        font-size: 42px;
        font-weight: 800;
        margin: 0 0 20px 0;
        color: #ffffff;
        letter-spacing: -0.02em;
    }

    .fin-disclaimer {
        font-size: 12px;
        color: #71717a;
        line-height: 1.5;
        margin-top: auto;
    }

    .action-buttons {
        display: flex;
        gap: 16px;
        margin-top: 40px;
        margin-bottom: 20px;
    }

    .btn-primary,
    .btn-secondary {
        flex: 1;
        padding: 16px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 16px;
        text-align: center;
        text-decoration: none;
        transition: 0.2s;
    }

    .btn-primary {
        background: #111827;
        color: #ffffff;
        border: 1px solid #111827;
    }

    .btn-primary:hover {
        background: #374151;
        border-color: #374151;
    }

    .btn-secondary {
        background: #ffffff;
        color: #111827;
        border: 1px solid #d1d5db;
    }

    .btn-secondary:hover {
        background: #f9fafb;
        border-color: #9ca3af;
    }
</style>

<div class="detail-wrapper">

    <div class="gallery-container">
        <div class="main-img-wrapper">
            <button type="button" class="arrow-btn arrow-left" onclick="prevImg()">❮</button>
            <img id="main-gallery-img" src="<?php echo htmlspecialchars($mainImg); ?>" alt="Car"
                onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1550486014-9f88c39d8dc9?auto=format&fit=crop&w=800&q=80';">
            <button type="button" class="arrow-btn arrow-right" onclick="nextImg()">❯</button>
        </div>

        <?php if (count($images) > 1): ?>
            <div class="thumbs-container" id="thumbs-list">
                <?php foreach ($images as $index => $img): ?>
                    <img src="<?php echo htmlspecialchars($img['car_image_url']); ?>"
                        class="thumb-img <?php echo $index === 0 ? 'active' : ''; ?>" onclick="showImg(<?php echo $index; ?>)">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="title-area">
        <div class="title-area-text">
            <h2><?php echo htmlspecialchars(($car['car_brand'] ?? 'TBA') . ' ' . ($car['car_model'] ?? '')); ?></h2>
            <span class="price">RM <?php echo number_format($car_price, 2); ?></span>
        </div>

        <button type="button" class="clean-wishlist-btn <?php echo $is_wished ? 'liked' : ''; ?>"
            onclick="toggleSidebarWishlist(event, this, <?php echo $car_id; ?>)" title="Add to Wishlist">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                <path
                    d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z">
                </path>
            </svg>
        </button>
    </div>

    <div class="badge-row car-details-grid">
        <div class="grid-item">
            <span>Year</span><strong><?php echo !empty($car['car_year']) ? htmlspecialchars($car['car_year']) : '-'; ?></strong>
        </div>
        <div class="grid-item"><span>Mileage</span><strong><?php echo number_format($car['car_mileage'] ?? 0); ?>
                KM</strong></div>
        <div class="grid-item">
            <span>Transmission</span><strong><?php echo htmlspecialchars($car['transmission'] ?? '-'); ?></strong>
        </div>
        <div class="grid-item">
            <span>Fuel</span><strong><?php echo htmlspecialchars($car['fuel_type'] ?? '-'); ?></strong>
        </div>
    </div>

    <?php if (!empty($colors)): ?>
        <div class="color-swatch-wrapper">
            <span style="font-size: 14px; font-weight: 600; color: #374151; margin-right: 8px;">Available Colors:</span>
            <?php foreach ($colors as $c): ?>
                <div class="color-swatch" style="background-color: <?php echo htmlspecialchars($c['color_hex']); ?>;"
                    title="<?php echo htmlspecialchars($c['color_name']); ?>"></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h3 class="section-title">Car Specifications</h3>

    <div class="sub-title">General Information</div>
    <div class="car-details-grid">
        <div class="grid-item">
            <span>Brand</span><strong><?php echo htmlspecialchars($car['car_brand'] ?? '-'); ?></strong>
        </div>
        <div class="grid-item">
            <span>Model</span><strong><?php echo htmlspecialchars($car['car_model'] ?? '-'); ?></strong>
        </div>
        <div class="grid-item"><span>Variant</span><strong><?php echo htmlspecialchars($variant); ?></strong></div>
        <div class="grid-item"><span>Body
                Type</span><strong><?php echo htmlspecialchars($car['body_type'] ?? '-'); ?></strong></div>
        <div class="grid-item"><span>Seats</span><strong><?php echo htmlspecialchars($car['seats'] ?? '-'); ?></strong>
        </div>
        <div class="grid-item">
            <span>Origin</span><strong><?php echo htmlspecialchars($car['car_origin'] ?? '-'); ?></strong>
        </div>
    </div>

    <div id="more-specs-box" style="display: none;">

        <div class="sub-title" style="margin-top: 24px;">Engine & Performance</div>
        <div class="car-details-grid">
            <div class="grid-item"><span>Engine
                    CC</span><strong><?php echo !empty($engine['engine_cc']) ? htmlspecialchars($engine['engine_cc']) : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Engine
                    Type</span><strong><?php echo htmlspecialchars($engine['engine_type'] ?? '-'); ?></strong></div>
            <div class="grid-item"><span>Peak
                    Power</span><strong><?php echo !empty($engine['peak_power_kw']) ? htmlspecialchars($engine['peak_power_kw'] . ' KW') : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Peak
                    Torque</span><strong><?php echo !empty($engine['peak_torque_nm']) ? htmlspecialchars($engine['peak_torque_nm'] . ' NM') : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Compression
                    Ratio</span><strong><?php echo !empty($engine['compression_ratio']) ? htmlspecialchars($engine['compression_ratio']) : '-'; ?></strong>
            </div>
        </div>

        <div class="sub-title">Dimensions</div>
        <div class="car-details-grid">
            <div class="grid-item">
                <span>Length</span><strong><?php echo !empty($dimension['length']) ? htmlspecialchars($dimension['length'] . ' mm') : '-'; ?></strong>
            </div>
            <div class="grid-item">
                <span>Width</span><strong><?php echo !empty($dimension['width']) ? htmlspecialchars($dimension['width'] . ' mm') : '-'; ?></strong>
            </div>
            <div class="grid-item">
                <span>Height</span><strong><?php echo !empty($dimension['height']) ? htmlspecialchars($dimension['height'] . ' mm') : '-'; ?></strong>
            </div>
            <div class="grid-item">
                <span>Wheelbase</span><strong><?php echo !empty($dimension['wheelbase']) ? htmlspecialchars($dimension['wheelbase'] . ' mm') : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Kerb
                    Weight</span><strong><?php echo !empty($dimension['weight']) ? htmlspecialchars($dimension['weight'] . ' kg') : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Fuel
                    Tank</span><strong><?php echo !empty($dimension['fuel_tank']) ? htmlspecialchars($dimension['fuel_tank'] . ' L') : '-'; ?></strong>
            </div>
        </div>

        <div class="sub-title">Features & Equipment</div>
        <div class="car-details-grid">
            <div class="grid-item"><span>Interior
                    Color</span><strong><?php echo !empty($features['int_color']) ? htmlspecialchars($features['int_color']) : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Seat
                    Material</span><strong><?php echo !empty($features['seat_mat']) ? htmlspecialchars($features['seat_mat']) : '-'; ?></strong>
            </div>
            <div class="grid-item">
                <span>Headlights</span><strong><?php echo !empty($features['headlights']) ? htmlspecialchars($features['headlights']) : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Infotainment
                    Screen</span><strong><?php echo !empty($features['screen']) ? htmlspecialchars($features['screen'] . ' Inch') : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Airbags
                    Count</span><strong><?php echo !empty($features['airbags_count']) ? htmlspecialchars($features['airbags_count']) : '-'; ?></strong>
            </div>
            <div class="grid-item full-width"><span>Comfort &
                    Convenience</span><strong><?php echo !empty($features['feat_conf']) ? htmlspecialchars($features['feat_conf']) : '-'; ?></strong>
            </div>
        </div>

        <div class="sub-title">Brakes & Chassis</div>
        <div class="car-details-grid">
            <div class="grid-item"><span>Front
                    Brakes</span><strong><?php echo !empty($brakes['front_brakes']) ? htmlspecialchars($brakes['front_brakes']) : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Rear
                    Brakes</span><strong><?php echo !empty($brakes['rear_brakes']) ? htmlspecialchars($brakes['rear_brakes']) : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Front
                    Suspension</span><strong><?php echo !empty($suspension['front_suspension']) ? htmlspecialchars($suspension['front_suspension']) : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Rear
                    Suspension</span><strong><?php echo !empty($suspension['rear_suspension']) ? htmlspecialchars($suspension['rear_suspension']) : '-'; ?></strong>
            </div>
            <div class="grid-item full-width"><span>Steering
                    Type</span><strong><?php echo !empty($steering['steering_type']) ? htmlspecialchars($steering['steering_type']) : '-'; ?></strong>
            </div>
        </div>

        <div class="sub-title">Tyres & Wheels</div>
        <div class="car-details-grid">
            <div class="grid-item"><span>Front
                    Tyres</span><strong><?php echo !empty($tyres['front_tyres']) ? htmlspecialchars($tyres['front_tyres']) : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Rear
                    Tyres</span><strong><?php echo !empty($tyres['rear_tyres']) ? htmlspecialchars($tyres['rear_tyres']) : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Front
                    Rim</span><strong><?php echo !empty($tyres['front_rim_inches']) ? htmlspecialchars($tyres['front_rim_inches'] . ' Inch') : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Rear
                    Rim</span><strong><?php echo !empty($tyres['rear_rim_inches']) ? htmlspecialchars($tyres['rear_rim_inches'] . ' Inch') : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Wheel
                    Size</span><strong><?php echo !empty($features['wheel_size']) ? htmlspecialchars($features['wheel_size'] . ' Inch') : '-'; ?></strong>
            </div>
        </div>
    </div>
    <button type="button" class="btn-toggle" id="toggleSpecsBtn" onclick="toggleMoreSpecs()">View Full
        Specifications</button>

    <?php if (!empty($ev)): ?>
        <h3 class="section-title">⚡ EV Specifications</h3>
        <div class="car-details-grid">
            <div class="grid-item"><span>Battery
                    Range</span><strong><?php echo isset($ev['battery_range']) ? htmlspecialchars($ev['battery_range'] . ' KM') : 'TBA'; ?></strong>
            </div>
        </div>
    <?php endif; ?>

    <?php if (($car['car_origin'] ?? '') === 'Used Car' && !empty($used_car)): ?>
        <h3 class="section-title">Used Car History</h3>
        <div class="car-details-grid">
            <div class="grid-item"><span>Plate
                    No.</span><strong><?php echo !empty($used_car['car_plate']) ? htmlspecialchars($used_car['car_plate']) : '-'; ?></strong>
            </div>
            <div class="grid-item">
                <span>Owners</span><strong><?php echo !empty($used_car['owners']) ? htmlspecialchars($used_car['owners']) : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Accident
                    History</span><strong><?php echo !empty($used_car['accident']) ? htmlspecialchars($used_car['accident']) : 'None'; ?></strong>
            </div>
            <div class="grid-item"><span>Flood /
                    Fire</span><strong><?php echo !empty($used_car['flood']) ? htmlspecialchars($used_car['flood']) : 'No'; ?></strong>
            </div>
            <div class="grid-item"><span>Service
                    Record</span><strong><?php echo !empty($used_car['service_hist']) ? htmlspecialchars($used_car['service_hist']) : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Rem.
                    Warranty</span><strong><?php echo !empty($used_car['rem_warranty']) ? htmlspecialchars($used_car['rem_warranty']) : 'No'; ?></strong>
            </div>
            <div class="grid-item"><span>Last
                    Service</span><strong><?php echo !empty($used_car['last_service']) ? htmlspecialchars($used_car['last_service']) : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Next
                    Service</span><strong><?php echo !empty($used_car['next_service']) ? number_format($used_car['next_service']) . ' KM' : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Road Tax
                    Expiry</span><strong><?php echo !empty($used_car['roadtax']) ? htmlspecialchars($used_car['roadtax']) : '-'; ?></strong>
            </div>
            <div class="grid-item"><span>Puspakom
                    Date</span><strong><?php echo !empty($used_car['puspakom']) ? htmlspecialchars($used_car['puspakom']) : '-'; ?></strong>
            </div>
            <div class="grid-item full-width">
                <span>Defects</span><strong><?php echo !empty($used_car['defects']) ? htmlspecialchars($used_car['defects']) : 'None'; ?></strong>
            </div>
        </div>

        <?php if (!empty($used_car['inspection_pdf'])): ?>
            <a href="<?php echo htmlspecialchars($used_car['inspection_pdf']); ?>" target="_blank" download class="btn-toggle"
                style="display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; margin-top: 16px; border-color: #d1d5db; background: #f9fafb; color: #111827;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Download Inspection Report (PDF)
            </a>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($car['description'])): ?>
        <h3 class="section-title">Description</h3>
        <p style="color: #4b5563; line-height: 1.6; font-size: 14px; margin-bottom: 20px;">
            <?php echo nl2br(htmlspecialchars($car['description'])); ?>
        </p>
    <?php endif; ?>

    <h3 class="section-title">Applying for a Loan?</h3>
    <div class="fin-wrapper">
        <input type="hidden" id="current-user-role" value="<?php echo htmlspecialchars($user_role); ?>">

        <div class="fin-left">
            <div class="fin-price-row">
                <span>Car Price</span>
                <strong>RM <?php echo number_format($car_price); ?></strong>
            </div>

            <div class="fin-form-group">
                <label style="color: #111827;">Down Payment</label>
                <div class="custom-combo-input" style="background-color: #f9fafb;">
                    <input type="number" id="dp-amt" value="<?php echo $car_price * ($default_dp_pct / 100); ?>" 
                        readonly style="color: #6b7280; cursor: not-allowed;">
                    <div class="vertical-divider"></div>
                    <div class="pct-section" style="background-color: #f9fafb;">
                        <input type="number" id="dp-pct" value="<?php echo $default_dp_pct; ?>" 
                            readonly style="color: #6b7280; cursor: not-allowed; background-color: transparent;">
                        <span class="pct-sign">%</span>
                    </div>
                </div>
                <small style="color: #9ca3af; font-size: 12px; margin-top: 6px; display: block;" id="dp-warning-text">
                    Min. <?php echo $default_dp_pct; ?>% down payment
                </small>
            </div>

            <div class="fin-form-group">
                <label style="color: #111827;">Loan Tenure <span id="tenure-val"
                        style="color: #4b5563; font-weight: 500;">9 Years</span></label>
                <input type="range" id="tenure-slider" min="5" max="9" value="9" step="2" oninput="updateTenure()">
            </div>

            <div class="fin-form-group" style="margin-bottom: 0;">
                <label style="color: #111827;">Interest Rate (%)</label>
                <input type="number" id="int-rate" value="<?php echo $default_loan_rate; ?>" 
                    class="basic-input" readonly style="background-color: #f9fafb; color: #6b7280; cursor: not-allowed;">
            </div>
        </div>

        <div class="fin-right">
            <div class="fin-right-header">
                <span>Your Estimated Monthly Payment:</span>
                <button type="button" class="reset-btn" onclick="resetCalc()">↻ Reset</button>
            </div>
            <div class="fin-result" id="monthly-result">RM 0</div>
            <p class="fin-disclaimer">
                All interest rates and calculated amounts are estimations only. Actual amounts may differ based on your
                individual credit profile.
            </p>
        </div>
    </div>

    <div class="action-buttons">
        <a href="start_booking.php?car_id=<?php echo $car_id; ?>" class="btn-primary">
            Booking
        </a>
        <a href="reservation.php?car_id=<?php echo $car_id; ?>" class="btn-secondary">
            Test Drive
        </a>
    </div>

</div>

<script>
    // 1. 当用户拖动年份滑块时触发
    function updateTenure() {
        let tenure = document.getElementById('tenure-slider').value;
        document.getElementById('tenure-val').innerText = tenure + " Years";
        calculateMonthlyLoan();
    }

    // 2. 计算月供的核心逻辑
    function calculateMonthlyLoan() {
        let carPrice = parseFloat(document.getElementById('car-price-data').value) || 0;
        let dpAmt = parseFloat(document.getElementById('dp-amt').value) || 0;
        let intRate = parseFloat(document.getElementById('int-rate').value) || 0;
        let years = parseInt(document.getElementById('tenure-slider').value) || 9;

        let loanAmount = carPrice - dpAmt;
        
        if (loanAmount <= 0) {
            document.getElementById('monthly-result').innerText = "RM 0";
            return;
        }

        let totalInterest = loanAmount * (intRate / 100) * years;
        let totalToPay = loanAmount + totalInterest;
        let monthlyPayment = totalToPay / (years * 12);

        document.getElementById('monthly-result').innerText = "RM " + Math.round(monthlyPayment).toLocaleString();
    }

    // 3. Reset 按钮逻辑
    function resetCalc() {
        document.getElementById('tenure-slider').value = 9;
        updateTenure();
    }

    // 4. 当页面刚加载完成时，自动算一次
    document.addEventListener("DOMContentLoaded", function() {
        updateTenure();
    });

    // -------------------------------------------------------------
    // 以下为你页面原本需要用到的 UI 控制函数 (Gallery/Specs等)
    // -------------------------------------------------------------
    let currentImgIndex = 0;
    
    function showImg(index) {
        let galleryData = document.getElementById('gallery-data');
        if (!galleryData) return;
        
        let images = JSON.parse(galleryData.getAttribute('data-images'));
        if (!images || images.length === 0) return;
        
        currentImgIndex = index;
        document.getElementById('main-gallery-img').src = images[currentImgIndex];
        
        // 更新小图的 active 样式
        let thumbs = document.querySelectorAll('.thumb-img');
        thumbs.forEach((thumb, i) => {
            if (i === index) thumb.classList.add('active');
            else thumb.classList.remove('active');
        });
    }

    function prevImg() {
        let galleryData = document.getElementById('gallery-data');
        if (!galleryData) return;
        let images = JSON.parse(galleryData.getAttribute('data-images'));
        if (!images) return;

        currentImgIndex = (currentImgIndex - 1 + images.length) % images.length;
        showImg(currentImgIndex);
    }

    function nextImg() {
        let galleryData = document.getElementById('gallery-data');
        if (!galleryData) return;
        let images = JSON.parse(galleryData.getAttribute('data-images'));
        if (!images) return;

        currentImgIndex = (currentImgIndex + 1) % images.length;
        showImg(currentImgIndex);
    }

    function toggleMoreSpecs() {
        let box = document.getElementById('more-specs-box');
        let btn = document.getElementById('toggleSpecsBtn');
        if (box.style.display === 'none') {
            box.style.display = 'block';
            btn.innerText = 'Hide Full Specifications';
        } else {
            box.style.display = 'none';
            btn.innerText = 'View Full Specifications';
        }
    }

    // Wishlist Toggle (占位，如果你外部有定义可以删除这个)
    function toggleSidebarWishlist(event, btn, carId) {
        event.preventDefault();
        // 这里放你原本 AJAX 加入 wishlist 的逻辑
        btn.classList.toggle('liked');
    }
</script>