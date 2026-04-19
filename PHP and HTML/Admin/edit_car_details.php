<?php
session_start();
include '../database.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

mysqli_query($conn, "INSERT IGNORE INTO car_types (car_type_id, car_type_name) VALUES (1, 'Sedan'), (2, 'SUV'), (3, 'Hatchback'), (4, 'EV'), (5, 'MPV')");
mysqli_query($conn, "INSERT IGNORE INTO locations (location_id, location_state, location_city) VALUES (1, 'Kuala Lumpur', 'Kuala Lumpur')");

if (isset($_POST['action']) && $_POST['action'] == 'delete_image' && isset($_POST['img_id'])) {
    $img_id = (int) $_POST['img_id'];

    $q_path = mysqli_query($conn, "SELECT car_image_url FROM car_image WHERE car_image_id = $img_id");
    if ($row_path = mysqli_fetch_assoc($q_path)) {
        if (!empty($row_path['car_image_url']) && file_exists($row_path['car_image_url'])) {
            unlink($row_path['car_image_url']);
        }
    }
    mysqli_query($conn, "DELETE FROM car_image WHERE car_image_id = $img_id");
    echo json_encode(['status' => 'success']);
    exit();
}

if (isset($_FILES['ajax_car_images']) && isset($_POST['car_id_ajax'])) {
    $car_id = (int) $_POST['car_id_ajax'];
    $uploaded_images = [];

    if ($car_id == 0) {
        $upload_dir = "../../uploads/temp_cars/";
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0777, true);
        if (!isset($_SESSION['temp_car_images']))
            $_SESSION['temp_car_images'] = [];
    } else {
        $upload_dir = "../../uploads/cars/";
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0777, true);
    }

    foreach ($_FILES['ajax_car_images']['name'] as $key => $filename) {
        if ($_FILES['ajax_car_images']['error'][$key] == 0) {
            $tmp_name = $_FILES['ajax_car_images']['tmp_name'][$key];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($ext, $allowed)) {
                $new_name = time() . "_" . uniqid() . "." . $ext;
                $destination = $upload_dir . $new_name;

                if (move_uploaded_file($tmp_name, $destination)) {
                    if ($car_id == 0) {
                        $_SESSION['temp_car_images'][] = $destination;
                        $uploaded_images[] = ['id' => 'temp_' . (count($_SESSION['temp_car_images']) - 1), 'url' => $destination];
                    } else {
                        mysqli_query($conn, "INSERT INTO car_image (car_id, car_image_url) VALUES ($car_id, '$destination')");
                        $new_id = mysqli_insert_id($conn);
                        $uploaded_images[] = ['id' => $new_id, 'url' => $destination];
                    }
                }
            }
        }
    }
    echo json_encode(['status' => 'success', 'images' => $uploaded_images]);
    exit();
}

if (isset($_POST['action']) && $_POST['action'] == 'delete_temp_image' && isset($_POST['temp_id'])) {
    $temp_index = (int) str_replace('temp_', '', $_POST['temp_id']);

    if (isset($_SESSION['temp_car_images'][$temp_index])) {
        $path = $_SESSION['temp_car_images'][$temp_index];
        if (file_exists($path)) {
            unlink($path);
        }
        unset($_SESSION['temp_car_images'][$temp_index]);
        $_SESSION['temp_car_images'] = array_values($_SESSION['temp_car_images']);
    }
    echo json_encode(['status' => 'success']);
    exit();
}

$is_edit = false;
$car_id = 0;
$display_id = "000";
$car_images = [];

$car = [
    'car_id' => '',
    'car_brand' => 'Proton',
    'car_model' => '',
    'car_type_name' => 'Sedan',
    'car_year' => date('Y'),
    'car_status_status' => 'Draft',
    'car_status_price' => '',
    'car_status_stock_quantity' => 1,
    'car_origin' => 'New Car',
    'car_plate' => '',
    'location_state' => '',
    'location_city' => ''
];

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $car_id = (int) $_GET['id'];
    $display_id = str_pad($car_id, 3, '0', STR_PAD_LEFT);
    if (isset($_SESSION['temp_car_images']))
        unset($_SESSION['temp_car_images']);

    $query = "SELECT c.*, stat.car_status_price, stat.car_status_status, stat.car_status_stock_quantity, ct.car_type_name, loc.location_state, loc.location_city,
              h.used_mileage, h.owners, h.accident, h.flood, h.service_hist, h.last_service, h.next_service, h.roadtax, h.puspakom, h.rem_warranty, h.defects,
              s.score_ext, s.score_int, s.score_mech, s.score_tyre, s.score_avg, s.inspector_notes
              FROM cars c 
              LEFT JOIN car_status stat ON c.car_id = stat.car_id 
              LEFT JOIN car_types ct ON c.car_type_id = ct.car_type_id
              LEFT JOIN locations loc ON c.location_id = loc.location_id
              LEFT JOIN car_history h ON c.car_id = h.car_id
              LEFT JOIN car_admin_scores s ON c.car_id = s.car_id
              WHERE c.car_id = $car_id";

    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) > 0) {
        $car = mysqli_fetch_assoc($result);
        $is_edit = true;

        $img_q = mysqli_query($conn, "SELECT * FROM car_image WHERE car_id = $car_id ORDER BY car_image_id ASC");
        while ($img_row = mysqli_fetch_assoc($img_q)) {
            $car_images[] = $img_row;
        }
    } else {
        header("Location: manage cars.php");
        exit();
    }
} else {
    $car_id = 0;
    $q_next = mysqli_query($conn, "SELECT MAX(car_id) AS max_id FROM cars");
    $row_next = mysqli_fetch_assoc($q_next);
    $next_id = ($row_next['max_id'] ? $row_next['max_id'] : 0) + 1;
    $display_id = str_pad($next_id, 3, '0', STR_PAD_LEFT);
}

$is_used_car = (isset($car['car_origin']) && $car['car_origin'] === 'Used Car');

if (isset($_POST['save_all_details'])) {
    try {
        mysqli_begin_transaction($conn);

        $car_id_post = isset($_POST['car_id_post']) ? (int) $_POST['car_id_post'] : 0;
        if ($car_id_post === 0 && isset($_GET['id'])) {
            $car_id_post = (int) $_GET['id'];
        }
        $is_updating = ($car_id_post > 0);

        $feat_safety = isset($_POST['feat_safety']) ? implode(',', $_POST['feat_safety']) : '';
        $feat_tech = isset($_POST['feat_tech']) ? implode(',', $_POST['feat_tech']) : '';
        $feat_comf = isset($_POST['feat_comf']) ? implode(',', $_POST['feat_comf']) : '';

        $brand = mysqli_real_escape_string($conn, $_POST['brand'] ?? 'Proton');
        $model = mysqli_real_escape_string($conn, $_POST['model'] ?? '');
        $variant = mysqli_real_escape_string($conn, $_POST['variant'] ?? '');
        $year = max(1990, (int) ($_POST['year'] ?? date('Y')));

        $negotiable = mysqli_real_escape_string($conn, $_POST['negotiable'] ?? 'No');
        $monthly_installment = !empty($_POST['monthly_installment']) ? (float) $_POST['monthly_installment'] : 'NULL';
        $promotion_rebate = mysqli_real_escape_string($conn, $_POST['promotion_rebate'] ?? '');
        $promo_valid_until = !empty($_POST['promo_valid_until']) ? "'" . mysqli_real_escape_string($conn, $_POST['promo_valid_until']) . "'" : 'NULL';

        $engine_type = mysqli_real_escape_string($conn, $_POST['engine_type'] ?? '');
        $displacement = max(0, (int) ($_POST['displacement'] ?? 0)) ?: 'NULL';
        $hp = max(0, (int) ($_POST['hp'] ?? 0)) ?: 'NULL';
        $torque = max(0, (int) ($_POST['torque'] ?? 0)) ?: 'NULL';
        $acceleration = max(0, (float) ($_POST['acceleration'] ?? 0)) ?: 'NULL';
        $transmission = mysqli_real_escape_string($conn, $_POST['transmission'] ?? '');
        $drive_type = mysqli_real_escape_string($conn, $_POST['drive_type'] ?? '');
        $fuel_type = mysqli_real_escape_string($conn, $_POST['fuel_type'] ?? '');
        $fuel_consump = max(0, (float) ($_POST['fuel_consumption'] ?? 0)) ?: 'NULL';
        $battery = max(0, (int) ($_POST['battery_range'] ?? 0)) ?: 'NULL';

        $cylinders = max(0, (int) ($_POST['cylinders'] ?? 0)) ?: 'NULL';
        $top_speed = max(0, (int) ($_POST['top_speed'] ?? 0)) ?: 'NULL';
        $co2_emissions = max(0, (int) ($_POST['co2_emissions'] ?? 0)) ?: 'NULL';

        $dimensions = mysqli_real_escape_string($conn, $_POST['dimensions'] ?? '');
        $wheelbase = max(0, (int) ($_POST['wheelbase'] ?? 0)) ?: 'NULL';
        $boot_cap = max(0, (int) ($_POST['boot_cap'] ?? 0)) ?: 'NULL';
        $fuel_tank = max(0, (int) ($_POST['fuel_tank'] ?? 0)) ?: 'NULL';
        $weight = max(0, (int) ($_POST['weight'] ?? 0)) ?: 'NULL';
        $seats = max(0, (int) ($_POST['seats'] ?? 0)) ?: 'NULL';

        $width_with_mirrors = max(0, (int) ($_POST['width_with_mirrors'] ?? 0)) ?: 'NULL';
        $ground_clearance = max(0, (int) ($_POST['ground_clearance'] ?? 0)) ?: 'NULL';
        $airbags_count = max(0, (int) ($_POST['airbags_count'] ?? 0)) ?: 'NULL';

        $ext_color = mysqli_real_escape_string($conn, $_POST['ext_color'] ?? '');
        $int_color = mysqli_real_escape_string($conn, $_POST['int_color'] ?? '');
        $seat_mat = mysqli_real_escape_string($conn, $_POST['seat_mat'] ?? '');
        $wheel_size = mysqli_real_escape_string($conn, $_POST['wheel_size'] ?? '');
        $headlights = mysqli_real_escape_string($conn, $_POST['headlights'] ?? '');
        $screen = mysqli_real_escape_string($conn, $_POST['screen'] ?? '');
        $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');

        $price = max(0, (float) ($_POST['price'] ?? 0));
        $stock = max(0, (int) ($_POST['stock'] ?? 1));
        $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Draft');
        $origin = mysqli_real_escape_string($conn, $_POST['car_origin'] ?? 'New Car');
        $plate = mysqli_real_escape_string($conn, $_POST['plate_no'] ?? '');

        $body_type = mysqli_real_escape_string($conn, $_POST['body_type'] ?? 'Sedan');
        $type_query = mysqli_query($conn, "SELECT car_type_id FROM car_types WHERE car_type_name = '$body_type' LIMIT 1");
        $type_id = (mysqli_num_rows($type_query) > 0) ? mysqli_fetch_assoc($type_query)['car_type_id'] : 1;

        $state = mysqli_real_escape_string($conn, $_POST['location_state'] ?? 'Kuala Lumpur');
        $city = mysqli_real_escape_string($conn, $_POST['location_city'] ?? 'Kuala Lumpur');

        $loc_query = mysqli_query($conn, "SELECT location_id FROM locations WHERE location_state='$state' AND location_city='$city' LIMIT 1");
        if (mysqli_num_rows($loc_query) > 0) {
            $location_id = mysqli_fetch_assoc($loc_query)['location_id'];
        } else {
            mysqli_query($conn, "INSERT INTO locations (location_state, location_city) VALUES ('$state', '$city')");
            $location_id = mysqli_insert_id($conn);
        }

        if ($is_updating) {
            $update_cars = "UPDATE cars SET 
                car_type_id='$type_id', location_id='$location_id', car_brand='$brand', car_model='$model', car_year='$year', car_origin='$origin', car_plate='$plate', 
                engine_type='$engine_type', displacement=$displacement, hp=$hp, torque=$torque, acceleration=$acceleration, transmission='$transmission', drive_type='$drive_type', fuel_type='$fuel_type', fuel_consumption=$fuel_consump, battery_range=$battery, 
                dimensions='$dimensions', wheelbase=$wheelbase, boot_cap=$boot_cap, fuel_tank=$fuel_tank, weight=$weight, seats=$seats, 
                ext_color='$ext_color', int_color='$int_color', seat_mat='$seat_mat', wheel_size='$wheel_size', headlights='$headlights', screen='$screen', 
                feat_safety='$feat_safety', feat_tech='$feat_tech', feat_comf='$feat_comf', description='$description',
                negotiable='$negotiable', monthly_installment=$monthly_installment, promotion_rebate='$promotion_rebate', promo_valid_until=$promo_valid_until,
                cylinders=$cylinders, top_speed=$top_speed, co2_emissions=$co2_emissions, width_with_mirrors=$width_with_mirrors, ground_clearance=$ground_clearance, airbags_count=$airbags_count
                WHERE car_id=$car_id_post";
            mysqli_query($conn, $update_cars) or throw new Exception(mysqli_error($conn));
            $target_car_id = $car_id_post;
        } else {
            $insert_cars = "INSERT INTO cars (
                car_type_id, location_id, car_brand, car_model, car_year, car_origin, car_plate, car_created_at, 
                engine_type, displacement, hp, torque, acceleration, transmission, drive_type, fuel_type, fuel_consumption, battery_range, 
                dimensions, wheelbase, boot_cap, fuel_tank, weight, seats, 
                ext_color, int_color, seat_mat, wheel_size, headlights, screen, 
                feat_safety, feat_tech, feat_comf, description,
                negotiable, monthly_installment, promotion_rebate, promo_valid_until, cylinders, top_speed, co2_emissions, width_with_mirrors, ground_clearance, airbags_count
            ) VALUES (
                '$type_id', '$location_id', '$brand', '$model', '$year', '$origin', '$plate', NOW(), 
                '$engine_type', $displacement, $hp, $torque, $acceleration, '$transmission', '$drive_type', '$fuel_type', $fuel_consump, $battery, 
                '$dimensions', $wheelbase, $boot_cap, $fuel_tank, $weight, $seats, 
                '$ext_color', '$int_color', '$seat_mat', '$wheel_size', '$headlights', '$screen', 
                '$feat_safety', '$feat_tech', '$feat_comf', '$description',
                '$negotiable', $monthly_installment, '$promotion_rebate', $promo_valid_until, $cylinders, $top_speed, $co2_emissions, $width_with_mirrors, $ground_clearance, $airbags_count
            )";
            mysqli_query($conn, $insert_cars) or throw new Exception(mysqli_error($conn));
            $target_car_id = mysqli_insert_id($conn);
        }

        $check_stat = mysqli_query($conn, "SELECT 1 FROM car_status WHERE car_id = $target_car_id");
        if (mysqli_num_rows($check_stat) > 0) {
            mysqli_query($conn, "UPDATE car_status SET car_status_price=$price, car_status_stock_quantity=$stock, car_status_status='$status', car_status_updated_at=NOW() WHERE car_id=$target_car_id") or throw new Exception(mysqli_error($conn));
        } else {
            mysqli_query($conn, "INSERT INTO car_status (car_id, car_status_price, car_status_stock_quantity, car_status_status, car_status_updated_at) VALUES ($target_car_id, $price, $stock, '$status', NOW())") or throw new Exception(mysqli_error($conn));
        }

        if ($origin === 'Used Car') {
            $used_mileage = max(0, (int) ($_POST['used_mileage'] ?? 0));
            $owners = max(0, (int) ($_POST['owners'] ?? 0));
            $accident = mysqli_real_escape_string($conn, $_POST['accident'] ?? 'None');
            $flood = mysqli_real_escape_string($conn, $_POST['flood_fire_history'] ?? 'None');
            $service_hist = mysqli_real_escape_string($conn, $_POST['service_hist'] ?? 'None');
            $last_service = !empty($_POST['last_service']) ? "'" . mysqli_real_escape_string($conn, $_POST['last_service']) . "'" : "NULL";
            $next_service = max(0, (int) ($_POST['next_service'] ?? 0)) ?: 'NULL';
            $roadtax = !empty($_POST['road_tax_expiry']) ? "'" . mysqli_real_escape_string($conn, $_POST['road_tax_expiry']) . "'" : "NULL";
            $puspakom = !empty($_POST['puspakom_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['puspakom_date']) . "'" : "NULL";
            $rem_warranty = mysqli_real_escape_string($conn, $_POST['rem_warranty'] ?? 'No');
            $defects = mysqli_real_escape_string($conn, $_POST['known_issues'] ?? '');

            $score_ext = max(0, min(5, (int) ($_POST['score_ext'] ?? 5)));
            $score_int = max(0, min(5, (int) ($_POST['score_int'] ?? 5)));
            $score_mech = max(0, min(5, (int) ($_POST['score_mech'] ?? 5)));
            $score_tyre = max(0, min(5, (int) ($_POST['score_tyre'] ?? 5)));
            $score_avg = ($score_ext + $score_int + $score_mech + $score_tyre) / 4;
            $inspector_notes = mysqli_real_escape_string($conn, $_POST['inspector_notes'] ?? '');

            $check_hist = mysqli_query($conn, "SELECT 1 FROM car_history WHERE car_id = $target_car_id");
            if (mysqli_num_rows($check_hist) > 0) {
                mysqli_query($conn, "UPDATE car_history SET used_mileage=$used_mileage, owners=$owners, accident='$accident', flood='$flood', service_hist='$service_hist', last_service=$last_service, next_service=$next_service, roadtax=$roadtax, puspakom=$puspakom, rem_warranty='$rem_warranty', defects='$defects' WHERE car_id=$target_car_id") or throw new Exception(mysqli_error($conn));
            } else {
                mysqli_query($conn, "INSERT INTO car_history (car_id, used_mileage, owners, accident, flood, service_hist, last_service, next_service, roadtax, puspakom, rem_warranty, defects) VALUES ($target_car_id, $used_mileage, $owners, '$accident', '$flood', '$service_hist', $last_service, $next_service, $roadtax, $puspakom, '$rem_warranty', '$defects')") or throw new Exception(mysqli_error($conn));
            }

            $check_score = mysqli_query($conn, "SELECT 1 FROM car_admin_scores WHERE car_id = $target_car_id");
            if (mysqli_num_rows($check_score) > 0) {
                mysqli_query($conn, "UPDATE car_admin_scores SET score_ext=$score_ext, score_int=$score_int, score_mech=$score_mech, score_tyre=$score_tyre, score_avg=$score_avg, inspector_notes='$inspector_notes' WHERE car_id=$target_car_id") or throw new Exception(mysqli_error($conn));
            } else {
                mysqli_query($conn, "INSERT INTO car_admin_scores (car_id, score_ext, score_int, score_mech, score_tyre, score_avg, inspector_notes) VALUES ($target_car_id, $score_ext, $score_int, $score_mech, $score_tyre, $score_avg, '$inspector_notes')") or throw new Exception(mysqli_error($conn));
            }
        }

        if (!$is_updating && isset($_SESSION['temp_car_images']) && !empty($_SESSION['temp_car_images'])) {
            $real_upload_dir = "../../uploads/cars/";
            if (!is_dir($real_upload_dir))
                mkdir($real_upload_dir, 0777, true);

            foreach ($_SESSION['temp_car_images'] as $temp_path) {
                if (file_exists($temp_path)) {
                    $filename = basename($temp_path);
                    $new_path = $real_upload_dir . $filename;
                    if (rename($temp_path, $new_path)) {
                        mysqli_query($conn, "INSERT INTO car_image (car_id, car_image_url) VALUES ($target_car_id, '$new_path')");
                    }
                }
            }
            unset($_SESSION['temp_car_images']);
        }

        mysqli_commit($conn);
        header("Location: manage cars.php?success=1");
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        die("<div style='padding:20px; background:#fee2e2; color:#991b1b; border:1px solid #f87171; border-radius:8px;'>
                <h3>❌ Database Error</h3><p><strong>Reason:</strong> " . $e->getMessage() . "</p>
             </div>");
    }
}

$saved_safety = explode(',', $car['feat_safety'] ?? '');
$saved_tech = explode(',', $car['feat_tech'] ?? '');
$saved_comf = explode(',', $car['feat_comf'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit' : 'Add' ?> Details - CAR<?= $display_id ?></title>
    <link rel="stylesheet" href="../../CSS/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        .form-section {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .section-header {
            font-size: 16px;
            font-weight: 700;
            color: #111827;
            border-bottom: 2px solid #f3f4f6;
            padding-bottom: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-header i {
            color: var(--primary-color);
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
        }

        .grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .form-group {
            margin-bottom: 0;
            position: relative;
        }

        .form-control[readonly] {
            background-color: #f3f4f6;
            color: #6b7280;
            cursor: not-allowed;
            font-weight: 600;
        }

        .checkbox-group {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            margin-top: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #374151;
            cursor: pointer;
        }

        .inner-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 12px;
            overflow-x: auto;
        }

        .inner-tab-btn {
            padding: 10px 20px;
            background: none;
            border: none;
            font-size: 14px;
            font-weight: 600;
            color: #6b7280;
            cursor: pointer;
            border-radius: 6px;
            transition: 0.2s;
            white-space: nowrap;
        }

        .inner-tab-btn:hover {
            background: #f3f4f6;
            color: #111827;
        }

        .inner-tab-btn.active {
            background: #e0e7ff;
            color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .image-gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 16px;
        }

        .gallery-item {
            position: relative;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            height: 130px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.2s;
        }

        .gallery-item:hover img {
            transform: scale(1.05);
        }

        .delete-img-btn {
            position: absolute;
            top: 6px;
            right: 6px;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: 0.2s;
            opacity: 0.6;
        }

        .gallery-item:hover .delete-img-btn {
            opacity: 1;
        }

        .custom-file-upload {
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: 0.2s;
            background: #f9fafb;
            display: block;
            margin-bottom: 24px;
        }

        .custom-file-upload:hover {
            border-color: var(--primary-color);
            background: #f0f3ff;
        }

        .custom-file-upload i {
            font-size: 24px;
            color: #9ca3af;
            margin-bottom: 8px;
        }

        .custom-file-upload span {
            display: block;
            font-size: 14px;
            color: #6b7280;
            font-weight: 500;
        }

        select.custom-scroll-dropdown:focus {
            position: absolute;
            z-index: 100;
            width: 100%;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <form id="mainCarForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="car_id_post" id="car_id_post" value="<?= $car_id ?>">

            <header class="topbar"
                style="margin-bottom: 24px; position: sticky; top: 0; background: var(--bg-color); z-index: 100; padding-top: 16px; padding-bottom: 16px; border-bottom: 1px solid #e5e7eb;">
                <div class="page-title" style="display: flex; align-items: center; gap: 16px;">
                    <a href="manage cars.php" style="color: #6b7280; font-size: 18px;"><i
                            class="fas fa-arrow-left"></i></a>
                    <h1 style="font-size: 24px; font-weight: 700; color: #111827;">
                        <?= $is_edit ? 'Edit Car Details' : 'Add New Car' ?>
                    </h1>

                    <select id="car_origin_select" name="car_origin"
                        class="badge <?= $is_used_car ? 'badge-used' : 'badge-new' ?>"
                        style="border:none; outline:none; text-transform: uppercase; cursor: pointer;">
                        <option value="New Car" <?= ($car['car_origin'] == 'New Car') ? 'selected' : '' ?>>NEW CAR</option>
                        <option value="Used Car" <?= ($car['car_origin'] == 'Used Car') ? 'selected' : '' ?>>USED CAR
                        </option>
                    </select>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button type="submit" name="save_all_details" class="btn-add-blue" style="border:none;"><i
                            class="fas fa-save"></i> Save All</button>
                </div>
            </header>

            <div class="inner-tabs">
                <button type="button" class="inner-tab-btn active" data-target="tab-basic"><i
                        class="fas fa-info-circle"></i> Basic & Pricing</button>
                <button type="button" class="inner-tab-btn" data-target="tab-specs"><i class="fas fa-cogs"></i> Specs &
                    Dimensions</button>
                <button type="button" class="inner-tab-btn" data-target="tab-media"><i class="fas fa-photo-video"></i>
                    Media & Features</button>
                <button type="button" id="tab-history-btn" class="inner-tab-btn" data-target="tab-history"
                    style="color: #ea580c; background: #fff7ed; border: 1px solid #fed7aa;"><i
                        class="fas fa-history"></i> Used Car History</button>
            </div>

            <div id="tab-basic" class="tab-content active">
                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-car"></i> Basic Info</h3>
                    <div class="grid-4">
                        <div class="form-group"><label>Car ID</label>
                            <input type="text" class="form-control" value="CAR<?= $display_id ?>" readonly>
                        </div>
                        <div class="form-group"><label>Brand</label>
                            <input type="text" name="brand" class="form-control"
                                value="<?= htmlspecialchars($car['car_brand'] ?? 'Proton') ?>">
                        </div>
                        <div class="form-group"><label>Model</label>
                            <input type="text" name="model" class="form-control" placeholder="e.g., X50, Saga"
                                value="<?= htmlspecialchars($car['car_model'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Variant/Trim</label>
                            <input type="text" name="variant" class="form-control" placeholder="e.g., 1.5 TGDi Flagship"
                                value="<?= htmlspecialchars($car['variant'] ?? '') ?>">
                        </div>

                        <div class="form-group"><label>Body Type</label>
                            <select name="body_type" class="form-control">
                                <option <?= ($car['car_type_name'] ?? '') == 'Sedan' ? 'selected' : '' ?>>Sedan</option>
                                <option <?= ($car['car_type_name'] ?? '') == 'SUV' ? 'selected' : '' ?>>SUV</option>
                                <option <?= ($car['car_type_name'] ?? '') == 'Hatchback' ? 'selected' : '' ?>>Hatchback
                                </option>
                                <option <?= ($car['car_type_name'] ?? '') == 'EV' ? 'selected' : '' ?>>EV</option>
                                <option <?= ($car['car_type_name'] ?? '') == 'MPV' ? 'selected' : '' ?>>MPV</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Year</label>
                            <input type="number" name="year" min="1990" oninput="if(this.value<0)this.value=0"
                                class="form-control" value="<?= htmlspecialchars($car['car_year'] ?? date('Y')) ?>">
                        </div>
                        <div class="form-group"><label>Status</label>
                            <select name="status" class="form-control">
                                <option <?= ($car['car_status_status'] ?? '') == 'Active' ? 'selected' : '' ?>>Active
                                </option>
                                <option <?= ($car['car_status_status'] ?? '') == 'Draft' ? 'selected' : '' ?>>Draft
                                </option>
                                <option <?= ($car['car_status_status'] ?? '') == 'Inactive' ? 'selected' : '' ?>>Inactive
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-tag"></i> Pricing & Stock</h3>
                    <div class="grid-3">
                        <div class="form-group"><label>OTR Price (RM)</label>
                            <input type="number" step="0.01" min="0" oninput="if(this.value<0)this.value=0" name="price"
                                class="form-control" value="<?= htmlspecialchars($car['car_status_price'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Negotiable</label>
                            <select name="negotiable" class="form-control">
                                <option value="Yes" <?= (($car['negotiable'] ?? '') == 'Yes') ? 'selected' : '' ?>>Yes
                                </option>
                                <option value="No" <?= (($car['negotiable'] ?? '') == 'No') ? 'selected' : '' ?>>No
                                </option>
                            </select>
                        </div>
                        <div class="form-group"><label>Monthly Installment (RM)</label>
                            <input type="number" step="0.01" min="0" oninput="if(this.value<0)this.value=0"
                                name="monthly_installment" class="form-control"
                                value="<?= htmlspecialchars($car['monthly_installment'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Promotion / Rebate Details</label>
                            <input type="text" name="promotion_rebate" class="form-control"
                                value="<?= htmlspecialchars($car['promotion_rebate'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Promo Valid Until</label>
                            <input type="date" name="promo_valid_until" class="form-control"
                                value="<?= htmlspecialchars($car['promo_valid_until'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Stock Available (units)</label>
                            <input type="number" min="0" oninput="if(this.value<0)this.value=0" name="stock"
                                class="form-control"
                                value="<?= htmlspecialchars($car['car_status_stock_quantity'] ?? 1) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div id="tab-specs" class="tab-content">
                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-tachometer-alt"></i> Performance & Powertrain</h3>
                    <div class="grid-4">
                        <div class="form-group"><label>Engine Type</label>
                            <select name="engine_type" class="form-control">
                                <option <?= ($car['engine_type'] ?? '') == 'Petrol' ? 'selected' : '' ?>>Petrol</option>
                                <option <?= ($car['engine_type'] ?? '') == 'Hybrid' ? 'selected' : '' ?>>Hybrid</option>
                                <option <?= ($car['engine_type'] ?? '') == 'EV' ? 'selected' : '' ?>>EV</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Displacement (cc)</label>
                            <input type="number" min="0" oninput="if(this.value<0)this.value=0" name="displacement"
                                class="form-control" value="<?= htmlspecialchars($car['displacement'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Horsepower (hp)</label>
                            <input type="number" min="0" oninput="if(this.value<0)this.value=0" name="hp"
                                class="form-control" value="<?= htmlspecialchars($car['hp'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Torque (Nm)</label>
                            <input type="number" min="0" oninput="if(this.value<0)this.value=0" name="torque"
                                class="form-control" value="<?= htmlspecialchars($car['torque'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>0-100 km/h (s)</label>
                            <input type="number" step="0.1" min="0" oninput="if(this.value<0)this.value=0"
                                name="acceleration" class="form-control"
                                value="<?= htmlspecialchars($car['acceleration'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Transmission</label>
                            <select name="transmission" class="form-control">
                                <option <?= ($car['transmission'] ?? '') == 'Auto' ? 'selected' : '' ?>>Auto</option>
                                <option <?= ($car['transmission'] ?? '') == 'Manual' ? 'selected' : '' ?>>Manual</option>
                                <option <?= ($car['transmission'] ?? '') == 'CVT' ? 'selected' : '' ?>>CVT</option>
                                <option <?= ($car['transmission'] ?? '') == 'DCT' ? 'selected' : '' ?>>DCT</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Drive Type</label>
                            <select name="drive_type" class="form-control">
                                <option <?= ($car['drive_type'] ?? '') == 'FWD' ? 'selected' : '' ?>>FWD</option>
                                <option <?= ($car['drive_type'] ?? '') == 'RWD' ? 'selected' : '' ?>>RWD</option>
                                <option <?= ($car['drive_type'] ?? '') == 'AWD' ? 'selected' : '' ?>>AWD</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Fuel Type</label>
                            <select name="fuel_type" class="form-control">
                                <option <?= ($car['fuel_type'] ?? '') == 'RON95' ? 'selected' : '' ?>>RON95</option>
                                <option <?= ($car['fuel_type'] ?? '') == 'RON97' ? 'selected' : '' ?>>RON97</option>
                                <option <?= ($car['fuel_type'] ?? '') == 'Diesel' ? 'selected' : '' ?>>Diesel</option>
                                <option <?= ($car['fuel_type'] ?? '') == 'Electric' ? 'selected' : '' ?>>Electric</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Fuel Consumption (L/100km)</label>
                            <input type="number" step="0.1" min="0" oninput="if(this.value<0)this.value=0"
                                name="fuel_consumption" class="form-control"
                                value="<?= htmlspecialchars($car['fuel_consumption'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Battery Range (km, EV only)</label>
                            <input type="number" min="0" oninput="if(this.value<0)this.value=0" name="battery_range"
                                class="form-control" value="<?= htmlspecialchars($car['battery_range'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>No. of Cylinders</label>
                            <input type="number" min="0" oninput="if(this.value<0)this.value=0" name="cylinders"
                                class="form-control" value="<?= htmlspecialchars($car['cylinders'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Top Speed (km/h)</label>
                            <input type="number" min="0" oninput="if(this.value<0)this.value=0" name="top_speed"
                                class="form-control" value="<?= htmlspecialchars($car['top_speed'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>CO₂ Emissions (g/km)</label>
                            <input type="number" min="0" oninput="if(this.value<0)this.value=0" name="co2_emissions"
                                class="form-control" value="<?= htmlspecialchars($car['co2_emissions'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-ruler-combined"></i> Dimensions & Capacity</h3>
                    <div class="grid-3">
                        <div class="form-group"><label>L × W × H (mm)</label>
                            <input type="text" name="dimensions" class="form-control"
                                placeholder="e.g., 4330 x 1800 x 1609"
                                value="<?= htmlspecialchars($car['dimensions'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Wheelbase (mm)</label>
                            <input type="number" min="0" oninput="if(this.value<0)this.value=0" name="wheelbase"
                                class="form-control" value="<?= htmlspecialchars($car['wheelbase'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Boot Capacity (L)</label>
                            <input type="number" min="0" oninput="if(this.value<0)this.value=0" name="boot_cap"
                                class="form-control" value="<?= htmlspecialchars($car['boot_cap'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Fuel Tank (L)</label>
                            <input type="number" min="0" oninput="if(this.value<0)this.value=0" name="fuel_tank"
                                class="form-control" value="<?= htmlspecialchars($car['fuel_tank'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Kerb Weight (kg)</label>
                            <input type="number" min="0" oninput="if(this.value<0)this.value=0" name="weight"
                                class="form-control" value="<?= htmlspecialchars($car['weight'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Seating Capacity</label>
                            <input type="number" min="0" oninput="if(this.value<0)this.value=0" name="seats"
                                class="form-control" value="<?= htmlspecialchars($car['seats'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Width with Mirrors (mm)</label>
                            <input type="number" min="0" oninput="if(this.value<0)this.value=0"
                                name="width_with_mirrors" class="form-control"
                                value="<?= htmlspecialchars($car['width_with_mirrors'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Ground Clearance (mm)</label>
                            <input type="number" min="0" oninput="if(this.value<0)this.value=0" name="ground_clearance"
                                class="form-control" value="<?= htmlspecialchars($car['ground_clearance'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Airbags Count</label>
                            <input type="number" min="0" oninput="if(this.value<0)this.value=0" name="airbags_count"
                                class="form-control" value="<?= htmlspecialchars($car['airbags_count'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-fill-drip"></i> Exterior & Interior</h3>
                    <div class="grid-3">
                        <div class="form-group"><label>Exterior Colour</label>
                            <input type="text" name="ext_color" class="form-control"
                                value="<?= htmlspecialchars($car['ext_color'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Interior Colour</label>
                            <input type="text" name="int_color" class="form-control"
                                value="<?= htmlspecialchars($car['int_color'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Seat Material</label>
                            <select name="seat_mat" class="form-control">
                                <option <?= ($car['seat_mat'] ?? '') == 'Fabric' ? 'selected' : '' ?>>Fabric</option>
                                <option <?= ($car['seat_mat'] ?? '') == 'Leather' ? 'selected' : '' ?>>Leather</option>
                                <option <?= ($car['seat_mat'] ?? '') == 'Half-leather' ? 'selected' : '' ?>>Half-leather
                                </option>
                            </select>
                        </div>
                        <div class="form-group"><label>Wheel Size & Type</label>
                            <input type="text" name="wheel_size" class="form-control"
                                value="<?= htmlspecialchars($car['wheel_size'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Headlights</label>
                            <select name="headlights" class="form-control">
                                <option <?= ($car['headlights'] ?? '') == 'Halogen' ? 'selected' : '' ?>>Halogen</option>
                                <option <?= ($car['headlights'] ?? '') == 'LED' ? 'selected' : '' ?>>LED</option>
                                <option <?= ($car['headlights'] ?? '') == 'Matrix LED' ? 'selected' : '' ?>>Matrix LED
                                </option>
                            </select>
                        </div>
                        <div class="form-group"><label>Infotainment Screen (inch)</label>
                            <input type="text" name="screen" class="form-control"
                                value="<?= htmlspecialchars($car['screen'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div id="tab-media" class="tab-content">
                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-camera"></i> Car Images Gallery <medium
                            style="color:#9ca3af; font-weight:400; margin-left:10px;">(Changes are instant)</medium>
                    </h3>

                    <input type="file" id="ajaxImageInput" name="ajax_car_images[]" multiple accept="image/*"
                        style="display:none;">

                    <label for="ajaxImageInput" class="custom-file-upload">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Click to Upload New Images</span>
                        <medium style="color:#9ca3af; font-size:12px;">(JPG, PNG, WEBP, GIF)</medium>
                    </label>

                    <div id="imageGalleryGrid" class="image-gallery-grid">
                        <?php if ($is_edit && !empty($car_images)): ?>
                            <?php foreach ($car_images as $img): ?>
                                <div class="gallery-item" id="img_container_<?= $img['car_image_id'] ?>">
                                    <img src="<?= htmlspecialchars($img['car_image_url']) ?>">
                                    <button type="button" class="delete-img-btn"
                                        onclick="deleteExistingImage(<?= $img['car_image_id'] ?>)" title="Delete image">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (!$is_edit && isset($_SESSION['temp_car_images']) && !empty($_SESSION['temp_car_images'])): ?>
                            <?php foreach ($_SESSION['temp_car_images'] as $index => $temp_path): ?>
                                <div class="gallery-item" id="img_container_temp_<?= $index ?>">
                                    <img src="<?= htmlspecialchars($temp_path) ?>">
                                    <button type="button" class="delete-img-btn" onclick="deleteTempImage('temp_<?= $index ?>')"
                                        title="Delete image">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-check-square"></i> Features</h3>
                    <label style="font-weight: 700; color: #111827; margin-top: 10px; display:block;">Safety</label>
                    <div class="checkbox-group">
                        <label class="checkbox-item"><input type="checkbox" name="feat_safety[]" value="ABS"
                                <?= in_array('ABS', $saved_safety ?? []) ? 'checked' : '' ?>> ABS</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_safety[]" value="ESC"
                                <?= in_array('ESC', $saved_safety ?? []) ? 'checked' : '' ?>> ESC</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_safety[]" value="AEB"
                                <?= in_array('AEB', $saved_safety ?? []) ? 'checked' : '' ?>> AEB</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_safety[]" value="LKA"
                                <?= in_array('LKA', $saved_safety ?? []) ? 'checked' : '' ?>> Lane Keep Assist</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_safety[]" value="BSM"
                                <?= in_array('BSM', $saved_safety ?? []) ? 'checked' : '' ?>> Blind Spot Monitor</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_safety[]" value="360 Cam"
                                <?= in_array('360 Cam', $saved_safety ?? []) ? 'checked' : '' ?>> 360° Camera</label>
                    </div>

                    <label style="font-weight: 700; color: #111827; margin-top: 24px; display:block;">Technology &
                        Comfort</label>
                    <div class="checkbox-group">
                        <label class="checkbox-item"><input type="checkbox" name="feat_tech[]" value="Apple CarPlay"
                                <?= in_array('Apple CarPlay', $saved_tech ?? []) ? 'checked' : '' ?>> Apple
                            CarPlay</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_tech[]" value="Android Auto"
                                <?= in_array('Android Auto', $saved_tech ?? []) ? 'checked' : '' ?>> Android Auto</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_tech[]" value="Keyless"
                                <?= in_array('Keyless', $saved_tech ?? []) ? 'checked' : '' ?>> Keyless Entry</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_tech[]" value="Wireless Charging"
                                <?= in_array('Wireless Charging', $saved_tech ?? []) ? 'checked' : '' ?>> Wireless
                            Charging</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_tech[]" value="HUD"
                                <?= in_array('HUD', $saved_tech ?? []) ? 'checked' : '' ?>> HUD</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_tech[]" value="Push Start"
                                <?= in_array('Push Start', $saved_tech ?? []) ? 'checked' : '' ?>> Push Start</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_comf[]" value="Sunroof"
                                <?= in_array('Sunroof', $saved_comf ?? []) ? 'checked' : '' ?>> Sunroof</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_comf[]" value="Leather Seats"
                                <?= in_array('Leather Seats', $saved_comf ?? []) ? 'checked' : '' ?>> Leather
                            Seats</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_comf[]" value="Electric Tailgate"
                                <?= in_array('Electric Tailgate', $saved_comf ?? []) ? 'checked' : '' ?>> Electric
                            Tailgate</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_comf[]" value="Heated Seats"
                                <?= in_array('Heated Seats', $saved_comf ?? []) ? 'checked' : '' ?>> Heated Seats</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_comf[]" value="Memory Seat"
                                <?= in_array('Memory Seat', $saved_comf ?? []) ? 'checked' : '' ?>> Memory Seat</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_comf[]" value="Auto AC"
                                <?= in_array('Auto AC', $saved_comf ?? []) ? 'checked' : '' ?>> Auto AC</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_comf[]" value="Ambient Lighting"
                                <?= in_array('Ambient Lighting', $saved_comf ?? []) ? 'checked' : '' ?>> Ambient
                            Lighting</label>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-images"></i> Description</h3>
                    <div class="form-group">
                        <label>Selling Description</label>
                        <textarea name="description" class="form-control"
                            rows="6"><?= htmlspecialchars($car['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div id="tab-history" class="tab-content">
                <div class="form-section" style="border-color: #fdba74; background: #fffbeb;">
                    <h3 class="section-header" style="color: #c2410c; border-bottom-color: #fed7aa;"><i
                            class="fas fa-file-contract"></i> Vehicle History & Location</h3>
                    <div class="grid-3">
                        <div class="form-group"><label>Plate Number</label>
                            <input type="text" name="plate_no" class="form-control"
                                value="<?= htmlspecialchars($car['car_plate'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>State</label>
                            <select id="state_select" name="location_state" class="form-control">
                                <option value="">Select State...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>City</label>
                            <select id="city_select" name="location_city" class="form-control custom-scroll-dropdown"
                                onfocus="if(this.options.length > 3) this.size=4;" onblur="this.size=1;"
                                onchange="this.size=1; this.blur();">
                                <option value="">Select City...</option>
                            </select>
                            <input type="hidden" id="saved_state"
                                value="<?= htmlspecialchars($car['location_state'] ?? '') ?>">
                            <input type="hidden" id="saved_city"
                                value="<?= htmlspecialchars($car['location_city'] ?? '') ?>">
                        </div>

                        <div class="form-group"><label>Mileage (km)</label>
                            <input type="number" min="0" oninput="if(this.value<0)this.value=0" name="used_mileage"
                                class="form-control" value="<?= htmlspecialchars($car['used_mileage'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Previous Owners</label>
                            <input type="number" min="0" oninput="if(this.value<0)this.value=0" name="owners"
                                class="form-control" value="<?= htmlspecialchars($car['owners'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Accident History</label>
                            <select name="accident" class="form-control">
                                <option <?= ($car['accident'] ?? '') == 'None' ? 'selected' : '' ?>>None</option>
                                <option <?= ($car['accident'] ?? '') == 'Minor' ? 'selected' : '' ?>>Minor</option>
                                <option <?= ($car['accident'] ?? '') == 'Major' ? 'selected' : '' ?>>Major</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Flood/Fire History</label>
                            <select name="flood_fire_history" class="form-control">
                                <option value="None" <?= (($car['flood'] ?? '') == 'None') ? 'selected' : '' ?>>None
                                </option>
                                <option value="Flood" <?= (($car['flood'] ?? '') == 'Flood') ? 'selected' : '' ?>>Flood
                                    Record</option>
                                <option value="Fire" <?= (($car['flood'] ?? '') == 'Fire') ? 'selected' : '' ?>>Fire Record
                                </option>
                            </select>
                        </div>
                        <div class="form-group"><label>Road Tax Expiry</label>
                            <input type="date" name="road_tax_expiry" class="form-control"
                                value="<?= htmlspecialchars($car['roadtax'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label>Puspakom Date</label>
                            <input type="date" name="puspakom_date" class="form-control"
                                value="<?= htmlspecialchars($car['puspakom'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 3;"><label>Known Issues / Defects</label>
                            <input type="text" name="known_issues" class="form-control"
                                value="<?= htmlspecialchars($car['defects'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-clipboard-list"></i> Admin Condition Scoring (Internal)
                    </h3>
                    <div class="grid-4">
                        <div class="form-group"><label>Exterior (0-5)</label><input type="number" id="score_ext"
                                name="score_ext" class="form-control score-input" min="0" max="5"
                                value="<?= htmlspecialchars($car['score_ext'] ?? 5) ?>"></div>
                        <div class="form-group"><label>Interior (0-5)</label><input type="number" id="score_int"
                                name="score_int" class="form-control score-input" min="0" max="5"
                                value="<?= htmlspecialchars($car['score_int'] ?? 5) ?>"></div>
                        <div class="form-group"><label>Mechanical (0-5)</label><input type="number" id="score_mech"
                                name="score_mech" class="form-control score-input" min="0" max="5"
                                value="<?= htmlspecialchars($car['score_mech'] ?? 5) ?>"></div>
                        <div class="form-group"><label>Tyre (0-5)</label><input type="number" id="score_tyre"
                                name="score_tyre" class="form-control score-input" min="0" max="5"
                                value="<?= htmlspecialchars($car['score_tyre'] ?? 5) ?>"></div>
                    </div>
                    <div class="grid-2" style="margin-top: 24px;">
                        <div class="form-group">
                            <label style="color: var(--primary-color); font-size: 16px;">Overall Score Average</label>
                            <input type="text" id="score_avg" name="score_avg" class="form-control"
                                style="font-size: 28px; font-weight: 700; color: var(--primary-color); background: #e0e7ff; height: 60px;"
                                value="<?= htmlspecialchars($car['score_avg'] ?? '5.0') ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Inspector Notes</label>
                            <textarea name="inspector_notes" class="form-control"
                                rows="3"><?= htmlspecialchars($car['inspector_notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

        </form>
    </main>

    <script src="../../JAVA SCRIPT/edit_car_details.js?v=<?= time() ?>"></script>
</body>

</html>