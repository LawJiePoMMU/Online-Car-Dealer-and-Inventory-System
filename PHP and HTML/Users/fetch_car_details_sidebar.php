<?php
require_once "../Config/database.php";

$car_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($car_id == 0) die("<div style='padding:50px; text-align:center;'>Invalid Car ID</div>");

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 🔥 修复点 1：字段名改成了真正的 length, width, height...
    // 🔥 修复点 2：表名改成了 car_dimensions
    $sql = "
        SELECT 
            c.*, 
            s.car_status_price,
            t.car_type_name, l.location_city,
            inv.variant, inv.color_name,
            cdim.length, cdim.width, cdim.height, cdim.wheelbase, cdim.weight, cdim.fuel_tank,
            cb.front_brakes, cb.rear_brakes,
            csus.front_suspension, csus.rear_suspension,
            cst.steering_type,
            ct.front_tyres, ct.rear_tyres, ct.front_rim_inches, ct.rear_rim_inches,
            uc.owners, uc.accident, uc.flood, uc.rem_warranty
        FROM cars c
        LEFT JOIN car_status s ON c.car_id = s.car_id
        LEFT JOIN car_types t ON c.car_type_id = t.car_type_id
        LEFT JOIN locations l ON c.location_id = l.location_id
        LEFT JOIN car_inventory inv ON c.car_id = inv.car_id
        LEFT JOIN car_dimensions cdim ON c.car_id = cdim.car_id
        LEFT JOIN car_brake_specs cb ON c.car_id = cb.car_id
        LEFT JOIN car_suspension_specs csus ON c.car_id = csus.car_id
        LEFT JOIN car_steering_specs cst ON c.car_id = cst.car_id
        LEFT JOIN car_tyre_specs ct ON c.car_id = ct.car_id
        LEFT JOIN used_car_details uc ON c.car_id = uc.car_id
        WHERE c.car_id = ? 
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$car_id]);
    $car = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$car) die("<div style='padding:50px; text-align:center;'>Car not found.</div>");

    // 抓取图片
    $imgStmt = $pdo->prepare("SELECT car_image_url FROM car_image WHERE car_id = ?");
    $imgStmt->execute([$car_id]);
    $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

    // 整理基本数据
    $brand_model = strtoupper($car['car_brand'] . ' ' . $car['car_model']);
    $variant = !empty($car['variant']) ? strtoupper($car['variant']) : '';
    $full_title = trim("$brand_model $variant");
    
    $price = !empty($car['car_status_price']) ? number_format($car['car_status_price'], 0) : 'TBA';
    $condition = strtoupper(str_replace(' Car', '', !empty($car['car_origin']) ? $car['car_origin'] : 'Used'));
    $main_img = !empty($images[0]['car_image_url']) ? htmlspecialchars($images[0]['car_image_url']) : 'https://images.unsplash.com/photo-1550486014-9f88c39d8dc9?w=800';

    // 🚀 开始输出包含所有规格的 HTML
    echo '
    <button class="close-drawer-btn" onclick="closeCarDetails()">✖</button>
    
    <div class="gallery-main">
        <img id="main-gallery-img" src="'.$main_img.'" alt="'.$full_title.'">
    </div>
    <div class="gallery-thumbs">';
        foreach ($images as $img) {
            $src = htmlspecialchars($img['car_image_url']);
            echo '<img src="'.$src.'" onclick="changeMainImg(\''.$src.'\')">';
        }
    echo '
    </div>

    <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: flex-start;">
        <h2 style="margin: 0; font-size: 22px; font-weight: 800; color: #0f172a; line-height: 1.2;">'.$full_title.'</h2>
        <span style="border: 1px solid #cbd5e1; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; color: #475569; margin-left: 10px;">'.$condition.'</span>
    </div>
    <h3 style="color: #dc2626; font-size: 24px; font-weight: bold; margin: 10px 0;">RM '.$price.'</h3>

    <div style="display: flex; gap: 15px; color: #475569; font-size: 14px; font-weight: 500; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px;">
        <span>📅 '.$car['car_year'].'</span>
        <span>🛣️ '.number_format($car['car_mileage']).' KM</span>
        <span>⚙️ '.$car['transmission'].'</span>
        <span>⛽ '.$car['fuel_type'].'</span>
    </div>

    <h4 style="margin-top: 20px; font-size: 16px; font-weight: bold; color: #0f172a;">OVERVIEW</h4>
    <div class="car-details-grid">
        <div class="grid-item"><span>Category</span> <strong>'.($car['car_type_name'] ?? 'N/A').'</strong></div>
        <div class="grid-item"><span>Color</span> <strong>'.($car['color_name'] ?? 'N/A').'</strong></div>
        <div class="grid-item"><span>Body Type</span> <strong>'.($car['body_type'] ?? 'N/A').'</strong></div>
        <div class="grid-item"><span>Seat</span> <strong>'.($car['seats'] ?? 'N/A').'</strong></div>
    </div>

    <h4 style="margin-top: 25px; font-size: 16px; font-weight: bold; color: #0f172a;">DIMENSIONS & WEIGHT</h4>
    <div class="car-details-grid" style="background: #f8fafc; padding: 15px; border-radius: 8px;">
        <div class="grid-item"><span>Length</span> <strong>'.($car['length'] ? $car['length'].' mm' : 'N/A').'</strong></div>
        <div class="grid-item"><span>Width</span> <strong>'.($car['width'] ? $car['width'].' mm' : 'N/A').'</strong></div>
        <div class="grid-item"><span>Height</span> <strong>'.($car['height'] ? $car['height'].' mm' : 'N/A').'</strong></div>
        <div class="grid-item"><span>Wheelbase</span> <strong>'.($car['wheelbase'] ? $car['wheelbase'].' mm' : 'N/A').'</strong></div>
        <div class="grid-item"><span>Kerb Wt.</span> <strong>'.($car['weight'] ? $car['weight'].' kg' : 'N/A').'</strong></div>
        <div class="grid-item"><span>Fuel Tank</span> <strong>'.($car['fuel_tank'] ? $car['fuel_tank'].' L' : 'N/A').'</strong></div>
    </div>

    <h4 style="margin-top: 25px; font-size: 16px; font-weight: bold; color: #0f172a;">CHASSIS & BRAKES</h4>
    <div class="car-details-grid">
        <div class="grid-item" style="grid-column: 1 / -1;"><span>Front Sus.</span> <strong>'.($car['front_suspension'] ?? 'N/A').'</strong></div>
        <div class="grid-item" style="grid-column: 1 / -1;"><span>Rear Sus.</span> <strong>'.($car['rear_suspension'] ?? 'N/A').'</strong></div>
        <div class="grid-item"><span>Front Brake</span> <strong>'.($car['front_brakes'] ?? 'N/A').'</strong></div>
        <div class="grid-item"><span>Rear Brake</span> <strong>'.($car['rear_brakes'] ?? 'N/A').'</strong></div>
        <div class="grid-item" style="grid-column: 1 / -1;"><span>Steering</span> <strong>'.($car['steering_type'] ?? 'N/A').'</strong></div>
    </div>

    <h4 style="margin-top: 25px; font-size: 16px; font-weight: bold; color: #0f172a;">WHEELS & TYRES</h4>
    <div class="car-details-grid" style="background: #f8fafc; padding: 15px; border-radius: 8px;">
        <div class="grid-item"><span>Front Tyre</span> <strong>'.($car['front_tyres'] ?? 'N/A').'</strong></div>
        <div class="grid-item"><span>Rear Tyre</span> <strong>'.($car['rear_tyres'] ?? 'N/A').'</strong></div>
        <div class="grid-item"><span>Front Rim</span> <strong>'.($car['front_rim_inches'] ? $car['front_rim_inches'].'"' : 'N/A').'</strong></div>
        <div class="grid-item"><span>Rear Rim</span> <strong>'.($car['rear_rim_inches'] ? $car['rear_rim_inches'].'"' : 'N/A').'</strong></div>
    </div>';

    // 5. 如果是二手车，展示历史记录
    if ($condition === 'USED') {
        echo '
        <h4 style="margin-top: 25px; font-size: 16px; font-weight: bold; color: #0f172a;">VEHICLE HISTORY (PUSPAKOM)</h4>
        <div class="car-details-grid" style="border-left: 3px solid #3b82f6; padding-left: 15px;">
            <div class="grid-item"><span>Prev. Owners</span> <strong>'.($car['owners'] ?? 'N/A').'</strong></div>
            <div class="grid-item"><span>Accident Free</span> <strong>'.($car['accident'] === 'None' ? '✅ Yes' : '❌ No').'</strong></div>
            <div class="grid-item"><span>Flood Free</span> <strong>'.($car['flood'] === 'No' ? '✅ Yes' : '❌ No').'</strong></div>
            <div class="grid-item"><span>Warranty</span> <strong>'.($car['rem_warranty'] === 'Yes' ? '✅ Active' : '❌ Expired').'</strong></div>
        </div>';
    }

    echo '
    <h4 style="margin-top: 25px; font-size: 16px; font-weight: bold; color: #0f172a;">DESCRIPTION</h4>
    <p style="color: #475569; font-size: 14px; line-height: 1.6;">
        '.nl2br(htmlspecialchars($car['description'] ?? 'No description available for this vehicle.')).'
    </p>

    <h4 style="margin-top: 25px; font-size: 16px; font-weight: bold; color: #0f172a;">SELLER INFORMATION</h4>
    <div class="seller-card">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="width: 50px; height: 50px; background: #cbd5e1; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px;">👤</div>
            <div>
                <div style="font-weight: bold; color: #0f172a; font-size: 16px;">LCW Auto Dealer</div>
                <div style="font-size: 13px; color: #64748b; margin-top: 2px;">📍 '.($car['location_city'] ?? 'Malaysia').'</div>
            </div>
        </div>
        <div class="seller-buttons" style="width: 140px;">
            <button class="btn-whatsapp" onclick="window.open(\'https://wa.me/60123456789?text=Hi, I am interested in '.$full_title.' (RM '.$price.')\', \'_blank\')">💬 WhatsApp</button>
            <button class="btn-call">📞 Call Seller</button>
        </div>
    </div>
    <br><br>
    ';

} catch (PDOException $e) {
    echo "<div style='padding:20px; color:red;'>Error: " . $e->getMessage() . "</div>";
}
?>