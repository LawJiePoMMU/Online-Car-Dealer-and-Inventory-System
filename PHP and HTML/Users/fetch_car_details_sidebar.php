<?php
require_once "../Config/database.php";

// 1. 建立数据库连接
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("PDO Connection failed: " . $e->getMessage());
}

// 2. 获取传递的车子 ID
$car_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($car_id == 0) {
    echo "<div style='padding: 50px; text-align: center; color: #64748b;'>Invalid Car ID.</div>";
    exit;
}

try {
    // 3. 多表联合查询核心基础数据
    $stmt = $pdo->prepare("
        SELECT c.*, s.car_status_price, s.car_status_stock_quantity, s.car_status_status, i.variant, i.color_name, i.color_hex
        FROM cars c 
        LEFT JOIN car_status s ON c.car_id = s.car_id 
        LEFT JOIN car_inventory i ON c.car_id = i.car_id 
        WHERE c.car_id = ? LIMIT 1
    ");
    $stmt->execute([$car_id]);
    $car = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$car) {
        echo "<div style='padding: 50px; text-align: center; color: #64748b;'>Car not found.</div>";
        exit;
    }

    // 4. 图片表数据
    $stmt = $pdo->prepare("SELECT car_image_url FROM car_image WHERE car_id = ?");
    $stmt->execute([$car_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $mainImg = !empty($images) ? $images[0]['car_image_url'] : 'https://images.unsplash.com/photo-1550486014-9f88c39d8dc9?auto=format&fit=crop&w=800&q=80';

    // 5. 完整抓取所有规格表
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

    // 6. EV 条件数据
    $ev = [];
    if (strcasecmp(trim($car['fuel_type'] ?? ''), 'Electric (EV)') === 0) {
        $stmt = $pdo->prepare("SELECT * FROM car_ev_specs WHERE car_id = ?");
        $stmt->execute([$car_id]);
        $ev = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // 7. Used Car 条件数据
    $used_car = [];
    if (($car['car_origin'] ?? '') === 'Used Car') {
        $stmt = $pdo->prepare("SELECT * FROM used_car_details WHERE car_id = ?");
        $stmt->execute([$car_id]);
        $used_car = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

} catch(PDOException $e) {
    die("<div style='padding: 50px; text-align: center; color: red;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>

<div class="gallery-main" style="margin-top: 10px;">
    <img id="main-gallery-img" src="<?php echo htmlspecialchars($mainImg); ?>" alt="Car">
</div>
<?php if (count($images) > 1): ?>
<div class="gallery-thumbs">
    <?php foreach ($images as $img): ?>
        <img src="<?php echo htmlspecialchars($img['car_image_url']); ?>" onclick="changeMainImg(this.src)">
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div style="margin-top: 25px;">
    <h2 style="margin: 0; font-size: 24px; color: #0f172a; font-weight: 800;">
        <?php echo htmlspecialchars(($car['car_brand'] ?? 'TBA') . ' ' . ($car['car_model'] ?? '')); ?>
    </h2>
    <div style="display: flex; align-items: center; gap: 10px; margin: 8px 0;">
        <span style="color: #e11d48; font-size: 22px; font-weight: 800;">
            RM <?php echo number_format($car['car_status_price'] ?? 0, 2); ?>
        </span>
        <?php if(!empty($car['color_hex'])): ?>
            <span style="display: inline-block; width: 15px; height: 15px; border-radius: 50%; background: <?php echo $car['color_hex']; ?>; border: 1px solid #cbd5e1;" title="<?php echo htmlspecialchars($car['color_name']); ?>"></span>
            <span style="font-size: 14px; color: #64748b;"><?php echo htmlspecialchars($car['color_name'] ?? ''); ?></span>
        <?php endif; ?>
    </div>
</div>

<hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 25px 0;">

<h3 style="color: #0f172a; font-size: 18px; margin-bottom: 15px; font-weight: 700;">Car Specifications</h3>
<div class="car-details-grid">
    <div class="grid-item"><span>Brand</span><strong><?php echo !empty($car['car_brand']) ? htmlspecialchars($car['car_brand']) : '-'; ?></strong></div>
    <div class="grid-item"><span>Model</span><strong><?php echo !empty($car['car_model']) ? htmlspecialchars($car['car_model']) : '-'; ?></strong></div>
    <div class="grid-item"><span>Year</span><strong><?php echo !empty($car['car_year']) ? htmlspecialchars($car['car_year']) : '-'; ?></strong></div>
    <div class="grid-item"><span>Variant</span><strong><?php echo htmlspecialchars($car['variant'] ?? '-'); ?></strong></div>
    <div class="grid-item"><span>Body Type</span><strong><?php echo htmlspecialchars($car['body_type'] ?? '-'); ?></strong></div>
    <div class="grid-item"><span>Fuel Type</span><strong><?php echo htmlspecialchars($car['fuel_type'] ?? '-'); ?></strong></div>
    <div class="grid-item"><span>Transmission</span><strong><?php echo htmlspecialchars($car['transmission'] ?? '-'); ?></strong></div>
    <div class="grid-item"><span>Seats</span><strong><?php echo htmlspecialchars($car['seats'] ?? '-'); ?></strong></div>
    <div class="grid-item"><span>Mileage</span><strong><?php echo number_format($car['car_mileage'] ?? 0); ?> KM</strong></div>
    <div class="grid-item"><span>Origin</span><strong><?php echo htmlspecialchars($car['car_origin'] ?? '-'); ?></strong></div>
    <div class="grid-item"><span>Stock</span><strong><?php echo htmlspecialchars($car['car_status_stock_quantity'] ?? '0'); ?> Units</strong></div>
    <div class="grid-item"><span>Status</span><strong><?php echo htmlspecialchars($car['car_status_status'] ?? '-'); ?></strong></div>
</div>

<hr style="border: 0; border-top: 1px dashed #e2e8f0; margin: 25px 0;">

<h3 style="color: #0f172a; font-size: 18px; margin-bottom: 15px; font-weight: 700;">Engine Details</h3>
<div class="car-details-grid">
    <div class="grid-item"><span>Engine CC</span><strong><?php echo htmlspecialchars($engine['engine_cc'] ?? '-'); ?></strong></div>
    <div class="grid-item"><span>Compression</span><strong><?php echo htmlspecialchars($engine['compression_ratio'] ?? '-'); ?></strong></div>
    <div class="grid-item"><span>Peak Power</span><strong><?php echo isset($engine['peak_power_kw']) ? htmlspecialchars($engine['peak_power_kw'] . ' KW') : '-'; ?></strong></div>
    <div class="grid-item"><span>Peak Torque</span><strong><?php echo isset($engine['peak_torque_nm']) ? htmlspecialchars($engine['peak_torque_nm'] . ' NM') : '-'; ?></strong></div>
    <div class="grid-item" style="grid-column: span 2;"><span>Engine Type</span><strong><?php echo htmlspecialchars($engine['engine_type'] ?? '-'); ?></strong></div>
</div>

<hr style="border: 0; border-top: 1px dashed #e2e8f0; margin: 25px 0;">

<h3 style="color: #0f172a; font-size: 18px; margin-bottom: 15px; font-weight: 700;">Dimension & Weight</h3>
<div class="car-details-grid">
    <div class="grid-item"><span>Length</span>export<strong><?php echo isset($dimension['length']) ? htmlspecialchars($dimension['length'] . ' mm') : '-'; ?></strong></div>
    <div class="grid-item"><span>Width</span><strong><?php echo isset($dimension['width']) ? htmlspecialchars($dimension['width'] . ' mm') : '-'; ?></strong></div>
    <div class="grid-item"><span>Height</span><strong><?php echo isset($dimension['height']) ? htmlspecialchars($dimension['height'] . ' mm') : '-'; ?></strong></div>
    <div class="grid-item"><span>Wheelbase</span><strong><?php echo isset($dimension['wheelbase']) ? htmlspecialchars($dimension['wheelbase'] . ' mm') : '-'; ?></strong></div>
    <div class="grid-item"><span>Fuel Tank</span><strong><?php echo isset($dimension['fuel_tank']) ? htmlspecialchars($dimension['fuel_tank'] . ' Litres') : '-'; ?></strong></div>
    <div class="grid-item"><span>Weight</span><strong><?php echo isset($dimension['weight']) ? htmlspecialchars($dimension['weight'] . ' kg') : '-'; ?></strong></div>
</div>

<hr style="border: 0; border-top: 1px dashed #e2e8f0; margin: 25px 0;">

<h3 style="color: #0f172a; font-size: 18px; margin-bottom: 15px; font-weight: 700;">Brakes</h3>
<div class="car-details-grid">
    <div class="grid-item"><span>Front Brakes</span><strong><?php echo htmlspecialchars($brakes['front_brakes'] ?? '-'); ?></strong></div>
    <div class="grid-item"><span>Rear Brakes</span><strong><?php echo htmlspecialchars($brakes['rear_brakes'] ?? '-'); ?></strong></div>
</div>

<hr style="border: 0; border-top: 1px dashed #e2e8f0; margin: 25px 0;">

<h3 style="color: #0f172a; font-size: 18px; margin-bottom: 15px; font-weight: 700;">Suspension</h3>
<div class="car-details-grid">
    <div class="grid-item" style="grid-column: span 2;"><span>Front Suspension</span><strong><?php echo htmlspecialchars($suspension['front_suspension'] ?? '-'); ?></strong></div>
    <div class="grid-item" style="grid-column: span 2;"><span>Rear Suspension</span><strong><?php echo htmlspecialchars($suspension['rear_suspension'] ?? '-'); ?></strong></div>
</div>

<hr style="border: 0; border-top: 1px dashed #e2e8f0; margin: 25px 0;">

<h3 style="color: #0f172a; font-size: 18px; margin-bottom: 15px; font-weight: 700;">Steering</h3>
<div class="car-details-grid">
    <div class="grid-item" style="grid-column: span 2;"><span>Steering Type</span><strong><?php echo htmlspecialchars($steering['steering_type'] ?? '-'); ?></strong></div>
</div>

<hr style="border: 0; border-top: 1px dashed #e2e8f0; margin: 25px 0;">

<h3 style="color: #0f172a; font-size: 18px; margin-bottom: 15px; font-weight: 700;">Tyres & Wheels</h3>
<div class="car-details-grid">
    <div class="grid-item"><span>Front Tyres</span><strong><?php echo htmlspecialchars($tyres['front_tyres'] ?? '-'); ?></strong></div>
    <div class="grid-item"><span>Rear Tyres</span><strong><?php echo htmlspecialchars($tyres['rear_tyres'] ?? '-'); ?></strong></div>
    <div class="grid-item"><span>Front Rims</span><strong><?php echo isset($tyres['front_rim_inches']) ? htmlspecialchars($tyres['front_rim_inches'] . ' inches') : '-'; ?></strong></div>
    <div class="grid-item"><span>Rear Rims</span><strong><?php echo isset($tyres['rear_rim_inches']) ? htmlspecialchars($tyres['rear_rim_inches'] . ' inches') : '-'; ?></strong></div>
</div>

<?php if (strcasecmp(trim($car['fuel_type'] ?? ''), 'Electric (EV)') === 0): ?>
<hr style="border: 0; border-top: 1px dashed #e2e8f0; margin: 25px 0;">
<h3 style="color: #10b981; font-size: 18px; margin-bottom: 15px; font-weight: 700;">EV Specifications ⚡</h3>
<div class="car-details-grid">
    <div class="grid-item"><span>Battery Range</span><strong><?php echo isset($ev['battery_range']) ? htmlspecialchars($ev['battery_range'] . ' KM') : 'TBA'; ?></strong></div>
</div>
<?php endif; ?>

<?php if (($car['car_origin'] ?? '') === 'Used Car'): ?>
<hr style="border: 0; border-top: 1px dashed #e2e8f0; margin: 25px 0;">
<h3 style="color: #f59e0b; font-size: 18px; margin-bottom: 15px; font-weight: 700;">Used Car History 🛡️</h3>
<div class="car-details-grid">
    <div class="grid-item"><span>Plate No.</span><strong><?php echo htmlspecialchars($used_car['car_plate'] ?? '-'); ?></strong></div>
    <div class="grid-item"><span>Owners</span><strong><?php echo htmlspecialchars($used_car['owners'] ?? '-'); ?></strong></div>
    <div class="grid-item"><span>Accident</span><strong><?php echo htmlspecialchars($used_car['accident'] ?? 'None'); ?></strong></div>
    <div class="grid-item"><span>Flood</span><strong><?php echo htmlspecialchars($used_car['flood'] ?? 'No'); ?></strong></div>
    <div class="grid-item"><span>Service History</span><strong><?php echo htmlspecialchars($used_car['service_hist'] ?? '-'); ?></strong></div>
    <div class="grid-item"><span>Last Service</span><strong><?php echo htmlspecialchars($used_car['last_service'] ?? '-'); ?></strong></div>
    <div class="grid-item"><span>Next Service</span><strong><?php echo isset($used_car['next_service']) ? number_format($used_car['next_service']) . ' KM' : '-'; ?></strong></div>
    <div class="grid-item"><span>Road Tax Expiry</span><strong><?php echo htmlspecialchars($used_car['roadtax'] ?? '-'); ?></strong></div>
    <div class="grid-item"><span>Puspakom Date</span><strong><?php echo htmlspecialchars($used_car['puspakom'] ?? '-'); ?></strong></div>
    <div class="grid-item"><span>Warranty Rem.</span><strong><?php echo htmlspecialchars($used_car['rem_warranty'] ?? 'No'); ?></strong></div>
    <div class="grid-item" style="grid-column: span 2;"><span>Defects</span><strong><?php echo !empty($used_car['defects']) ? htmlspecialchars($used_car['defects']) : 'None'; ?></strong></div>
</div>
<?php endif; ?>

<?php if (!empty($car['description'])): ?>
<hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 25px 0;">
<h3 style="color: #0f172a; font-size: 18px; margin-bottom: 15px; font-weight: 700;">Description</h3>
<p style="color: #475569; line-height: 1.7; font-size: 15px; margin-bottom: 25px;">
    <?php echo nl2br(htmlspecialchars($car['description'])); ?>
</p>
<?php endif; ?>

<hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 25px 0;">
<div style="display: flex; gap: 12px; margin-bottom: 40px;">
    <button onclick="alert('Proceeding to Booking...')" style="flex: 1; padding: 14px; background: #0f172a; color: white; border: none; border-radius: 8px; font-weight: 700; font-size: 15px; cursor: pointer; transition: 0.2s;">
        Booking
    </button>
    <button onclick="alert('Proceeding to Test Drive...')" style="flex: 1; padding: 14px; background: white; color: #0f172a; border: 2px solid #0f172a; border-radius: 8px; font-weight: 700; font-size: 15px; cursor: pointer; transition: 0.2s;">
        Test Drive
    </button>
</div>