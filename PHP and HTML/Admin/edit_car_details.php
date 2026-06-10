<?php
session_name("AdminSession");
session_start();
include '../Config/database.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
mysqli_query($conn, "INSERT IGNORE INTO car_types (car_type_id, car_type_name) VALUES 
    (1, 'Sedan'), (2, 'SUV'), (3, 'Hatchback'), (4, 'MPV')");
mysqli_query($conn, "INSERT IGNORE INTO locations (location_id, location_state, location_city) VALUES 
    (1, 'Kuala Lumpur', 'Kuala Lumpur')");

if (isset($_POST['action']) && $_POST['action'] === 'delete_image' && isset($_POST['img_id'])) {
    header('Content-Type: application/json');
    $img_id = (int) $_POST['img_id'];
    $q_path = mysqli_query($conn, "SELECT car_image_url FROM car_image WHERE car_image_id = $img_id");
    if ($row_path = mysqli_fetch_assoc($q_path)) {
        if (!empty($row_path['car_image_url']) && file_exists($row_path['car_image_url']))
            @unlink($row_path['car_image_url']);
    }
    mysqli_query($conn, "DELETE FROM car_image WHERE car_image_id = $img_id");
    echo json_encode(['status' => 'success']);
    exit();
}

if (isset($_FILES['ajax_car_images']) && isset($_POST['car_id_ajax'])) {
    header('Content-Type: application/json');
    $car_id_ajax = (int) $_POST['car_id_ajax'];
    $uploaded = [];
    $upload_dir = ($car_id_ajax == 0) ? "../../uploads/temp_cars/" : "../../uploads/cars/";
    if (!is_dir($upload_dir))
        @mkdir($upload_dir, 0777, true);
    if ($car_id_ajax == 0 && !isset($_SESSION['temp_car_images']))
        $_SESSION['temp_car_images'] = [];

    foreach ($_FILES['ajax_car_images']['name'] as $key => $filename) {
        if ($_FILES['ajax_car_images']['error'][$key] !== 0)
            continue;
        $tmp_name = $_FILES['ajax_car_images']['tmp_name'][$key];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']))
            continue;
        $new_name = time() . "_" . uniqid() . "." . $ext;
        $destination = $upload_dir . $new_name;
        if (!move_uploaded_file($tmp_name, $destination))
            continue;
        if ($car_id_ajax == 0) {
            $_SESSION['temp_car_images'][] = $destination;
            $uploaded[] = ['id' => 'temp_' . (count($_SESSION['temp_car_images']) - 1), 'url' => $destination];
        } else {
            $dest_e = mysqli_real_escape_string($conn, $destination);
            mysqli_query($conn, "INSERT INTO car_image (car_id, car_image_url) VALUES ($car_id_ajax, '$dest_e')");
            $uploaded[] = ['id' => mysqli_insert_id($conn), 'url' => $destination];
        }
    }
    echo json_encode(['status' => 'success', 'images' => $uploaded]);
    exit();
}

if (isset($_POST['action']) && $_POST['action'] === 'delete_temp_image' && isset($_POST['temp_id'])) {
    header('Content-Type: application/json');
    $temp_index = (int) str_replace('temp_', '', $_POST['temp_id']);
    if (isset($_SESSION['temp_car_images'][$temp_index])) {
        $path = $_SESSION['temp_car_images'][$temp_index];
        if (file_exists($path))
            @unlink($path);
        unset($_SESSION['temp_car_images'][$temp_index]);
        $_SESSION['temp_car_images'] = array_values($_SESSION['temp_car_images']);
    }
    echo json_encode(['status' => 'success']);
    exit();
}

$car_id = isset($_GET['id']) && !empty($_GET['id']) ? (int) $_GET['id'] : 0;
$is_edit = false;
$car_images = [];
$inventory_rows = [];
$display_id = "000";
$save_error = '';
$car_variant = '';

$car = [
    'car_brand' => 'Proton',
    'car_model' => '',
    'car_year' => date('Y'),
    'body_type' => 'Sedan',
    'seats' => 5,
    'fuel_type' => 'RON95',
    'transmission' => 'Auto',
    'car_mileage' => 0,
    'car_origin' => 'New Car',
    'description' => '',
    'car_type_name' => 'Sedan',
    'location_state' => '',
    'location_city' => '',
    'car_status_price' => '',
    'car_status_stock_quantity' => 0,
    'car_status_status' => 'Draft',
    'engine_cc' => '',
    'compression_ratio' => '',
    'peak_power_kw' => '',
    'peak_torque_nm' => '',
    'engine_type' => 'Petrol',
    'length' => '',
    'width' => '',
    'height' => '',
    'wheelbase' => '',
    'fuel_tank' => '',
    'weight' => '',
    'int_color' => '',
    'seat_mat' => 'Fabric',
    'wheel_size' => '',
    'headlights' => 'Halogen',
    'screen' => '',
    'feat_conf' => '',
    'airbags_count' => '',
    'battery_range' => '',
    'front_brakes' => '',
    'rear_brakes' => '',
    'steering_type' => '',
    'front_suspension' => '',
    'rear_suspension' => '',
    'front_tyres' => '',
    'rear_tyres' => '',
    'front_rim_inches' => '',
    'rear_rim_inches' => '',
    'car_plate' => '',
    'owners' => '',
    'accident' => 'None',
    'flood' => 'No',
    'service_hist' => '',
    'last_service' => '',
    'next_service' => '',
    'roadtax' => '',
    'puspakom' => '',
    'rem_warranty' => 'No',
    'defects' => '',
    'inspection_pdf' => '',
];

if ($car_id > 0) {
    $display_id = str_pad($car_id, 3, '0', STR_PAD_LEFT);
    unset($_SESSION['temp_car_images']);

    $result = mysqli_query($conn, "
        SELECT c.*, ct.car_type_name, loc.location_state, loc.location_city,
            stat.car_status_price, stat.car_status_status, stat.car_status_stock_quantity,
            eng.engine_cc, eng.compression_ratio, eng.peak_power_kw, eng.peak_torque_nm, eng.engine_type,
            dim.length, dim.width, dim.height, dim.wheelbase, dim.fuel_tank, dim.weight,
            feat.int_color, feat.seat_mat, feat.wheel_size, feat.headlights, feat.screen, feat.feat_conf, feat.airbags_count,
            ev.battery_range,
            brk.front_brakes, brk.rear_brakes,
            strg.steering_type,
            sus.front_suspension, sus.rear_suspension,
            tyr.front_tyres, tyr.rear_tyres, tyr.front_rim_inches, tyr.rear_rim_inches,
            ud.car_plate, ud.owners, ud.accident, ud.flood, ud.service_hist, ud.last_service,
            ud.next_service, ud.roadtax, ud.puspakom, ud.rem_warranty, ud.defects, ud.inspection_pdf
        FROM cars c
        LEFT JOIN car_types ct ON c.car_type_id = ct.car_type_id
        LEFT JOIN locations loc ON c.location_id = loc.location_id
        LEFT JOIN car_status stat ON c.car_id = stat.car_id
        LEFT JOIN car_engine_specs eng ON c.car_id = eng.car_id
        LEFT JOIN car_dimensions dim ON c.car_id = dim.car_id
        LEFT JOIN car_features feat ON c.car_id = feat.car_id
        LEFT JOIN car_ev_specs ev ON c.car_id = ev.car_id
        LEFT JOIN car_brake_specs brk ON c.car_id = brk.car_id
        LEFT JOIN car_steering_specs strg ON c.car_id = strg.car_id
        LEFT JOIN car_suspension_specs sus ON c.car_id = sus.car_id
        LEFT JOIN car_tyre_specs tyr ON c.car_id = tyr.car_id
        LEFT JOIN used_car_details ud ON c.car_id = ud.car_id
        WHERE c.car_id = $car_id LIMIT 1
    ");

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        foreach ($row as $k => $v) {
            if ($v !== null)
                $car[$k] = $v;
        }
        $is_edit = true;

        $img_q = mysqli_query($conn, "SELECT * FROM car_image WHERE car_id = $car_id ORDER BY car_image_id ASC");
        while ($img_row = mysqli_fetch_assoc($img_q))
            $car_images[] = $img_row;

        $inv_q = mysqli_query($conn, "SELECT * FROM car_inventory WHERE car_id = $car_id ORDER BY inventory_id ASC");
        while ($inv_row = mysqli_fetch_assoc($inv_q)) {
            $inventory_rows[] = $inv_row;
            if (empty($car_variant) && !empty($inv_row['variant']))
                $car_variant = $inv_row['variant'];
        }
    } else {
        header("Location: manage cars.php");
        exit();
    }
} else {
    $q_next = mysqli_query($conn, "SELECT MAX(car_id) AS max_id FROM cars");
    $row_next = mysqli_fetch_assoc($q_next);
    $next_id = ((int) ($row_next['max_id'] ?? 0)) + 1;
    $display_id = str_pad($next_id, 3, '0', STR_PAD_LEFT);
}

$is_used_car = ($car['car_origin'] === 'Used Car');
if (isset($_POST['save_all_details'])) {
    try {
        mysqli_begin_transaction($conn);

        $esc = function ($v) use ($conn) {
            return mysqli_real_escape_string($conn, $v ?? '');
        };
        $strNull = function ($v) use ($conn) {
            return ($v === '' || $v === null) ? 'NULL' : "'" . mysqli_real_escape_string($conn, $v) . "'";
        };
        $intNull = function ($v) {
            return ($v === '' || $v === null) ? 'NULL' : (int) $v;
        };
        $dateNull = function ($v) use ($conn) {
            return ($v === '' || $v === null) ? 'NULL' : "'" . mysqli_real_escape_string($conn, $v) . "'";
        };

        $upsert = function ($table, $car_id_, $cols_vals) use ($conn) {
            $exists = mysqli_query($conn, "SELECT 1 FROM $table WHERE car_id = $car_id_ LIMIT 1");
            if ($exists && mysqli_num_rows($exists) > 0) {
                $sets = [];
                foreach ($cols_vals as $col => $val)
                    $sets[] = "`$col` = $val";
                mysqli_query($conn, "UPDATE $table SET " . implode(', ', $sets) . " WHERE car_id = $car_id_");
            } else {
                $cols = ['car_id'];
                $vals = [$car_id_];
                foreach ($cols_vals as $col => $val) {
                    $cols[] = "`$col`";
                    $vals[] = $val;
                }
                mysqli_query($conn, "INSERT INTO $table (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")");
            }
        };
        $is_updating = ($car_id > 0);
        $car_origin = $esc($_POST['car_origin'] ?? 'New Car');
        $state = 'Kuala Lumpur';
        $city = 'Kuala Lumpur';
        if ($car_origin === 'Used Car') {
            $state = $esc($_POST['location_state'] ?? 'Kuala Lumpur');
            $city = $esc($_POST['location_city'] ?? 'Kuala Lumpur');
            if ($state === '')
                $state = 'Kuala Lumpur';
            if ($city === '')
                $city = 'Kuala Lumpur';
        }
        $loc_q = mysqli_query($conn, "SELECT location_id FROM locations WHERE location_state='$state' AND location_city='$city' LIMIT 1");
        if ($loc_q && mysqli_num_rows($loc_q) > 0) {
            $location_id = (int) mysqli_fetch_assoc($loc_q)['location_id'];
        } else {
            mysqli_query($conn, "INSERT INTO locations (location_state, location_city) VALUES ('$state', '$city')");
            $location_id = (int) mysqli_insert_id($conn);
        }

        $body_type = $esc($_POST['body_type'] ?? 'Sedan');
        $type_q = mysqli_query($conn, "SELECT car_type_id FROM car_types WHERE car_type_name = '$body_type' LIMIT 1");
        $type_id = ($type_q && mysqli_num_rows($type_q) > 0) ? (int) mysqli_fetch_assoc($type_q)['car_type_id'] : 1;

        $brand = $esc(trim($_POST['car_brand'] ?? ''));
        $model = $esc(trim($_POST['car_model'] ?? ''));
        $year = max(1985, (int) ($_POST['car_year'] ?? date('Y')));
        $seats = $intNull($_POST['seats'] ?? '');
        $fuel_type = $esc($_POST['fuel_type'] ?? '');
        $transmission = $esc($_POST['transmission'] ?? '');
        $car_mileage = max(0, (int) ($_POST['car_mileage'] ?? 0));
        $description = $esc(trim($_POST['description'] ?? ''));

        if ($is_updating) {
            mysqli_query($conn, "UPDATE cars SET car_type_id=$type_id, location_id=$location_id,
                car_brand='$brand', car_model='$model', car_year=$year,
                body_type='$body_type', seats=$seats, fuel_type='$fuel_type',
                transmission='$transmission', car_mileage=$car_mileage,
                car_origin='$car_origin', description='$description' WHERE car_id=$car_id");
            $target_id = $car_id;
        } else {
            mysqli_query($conn, "INSERT INTO cars 
                (car_type_id, location_id, car_brand, car_model, car_year, body_type, seats, fuel_type, transmission, car_mileage, car_origin, description, car_created_at)
                VALUES ($type_id, $location_id, '$brand', '$model', $year, '$body_type', $seats, '$fuel_type', '$transmission', $car_mileage, '$car_origin', '$description', NOW())");
            $target_id = (int) mysqli_insert_id($conn);
        }

        mysqli_query($conn, "DELETE FROM car_inventory WHERE car_id = $target_id");
        $total_stock = 0;
        $variant = $esc(trim($_POST['car_variant'] ?? ''));

        if (isset($_POST['inv_color']) && is_array($_POST['inv_color'])) {
            $n = count($_POST['inv_color']);
            for ($i = 0; $i < $n; $i++) {
                $cname = $esc(trim($_POST['inv_color'][$i] ?? ''));
                if ($cname === '')
                    continue;
                $hex = $esc($_POST['inv_color_hex'][$i] ?? '#ffffff');

                if ($car_origin === 'Used Car') {
                    $qty = 1;
                    mysqli_query($conn, "INSERT INTO car_inventory (car_id, variant, color_name, color_hex, quantity) 
                        VALUES ($target_id, '$variant', '$cname', '$hex', $qty)");
                    $total_stock += $qty;
                    break;
                } else {
                    $qty = min(100, max(0, (int) ($_POST['inv_qty'][$i] ?? 0)));
                    mysqli_query($conn, "INSERT INTO car_inventory (car_id, variant, color_name, color_hex, quantity) 
                        VALUES ($target_id, '$variant', '$cname', '$hex', $qty)");
                    $total_stock += $qty;
                }
            }
        }
        $final_stock = ($car_origin === 'Used Car') ? 1 : $total_stock;
        $price = max(0, (float) ($_POST['car_status_price'] ?? 0));
        $status = $esc($_POST['car_status_status'] ?? 'Draft');
        $upsert('car_status', $target_id, [
            'car_status_price' => $price,
            'car_status_stock_quantity' => $final_stock,
            'car_status_status' => "'$status'",
            'car_status_updated_at' => 'NOW()',
        ]);

        $engine_type_val = $esc($_POST['engine_type'] ?? '');
        $upsert('car_engine_specs', $target_id, [
            'engine_cc' => $intNull($_POST['engine_cc'] ?? ''),
            'compression_ratio' => $strNull($_POST['compression_ratio'] ?? ''),
            'peak_power_kw' => $intNull($_POST['peak_power_kw'] ?? ''),
            'peak_torque_nm' => $intNull($_POST['peak_torque_nm'] ?? ''),
            'engine_type' => $strNull($_POST['engine_type'] ?? ''),
        ]);
        $upsert('car_dimensions', $target_id, [
            'length' => $intNull($_POST['length'] ?? ''),
            'width' => $intNull($_POST['width'] ?? ''),
            'height' => $intNull($_POST['height'] ?? ''),
            'wheelbase' => $intNull($_POST['wheelbase'] ?? ''),
            'fuel_tank' => $intNull($_POST['fuel_tank'] ?? ''),
            'weight' => $intNull($_POST['weight'] ?? ''),
        ]);
        $upsert('car_features', $target_id, [
            'int_color' => $strNull($_POST['int_color'] ?? ''),
            'seat_mat' => $strNull($_POST['seat_mat'] ?? ''),
            'wheel_size' => $strNull($_POST['wheel_size'] ?? ''),
            'headlights' => $strNull($_POST['headlights'] ?? ''),
            'screen' => $strNull($_POST['screen'] ?? ''),
            'feat_conf' => $strNull($_POST['feat_conf'] ?? ''),
            'airbags_count' => $intNull($_POST['airbags_count'] ?? ''),
        ]);
        $upsert('car_brake_specs', $target_id, [
            'front_brakes' => $strNull($_POST['front_brakes'] ?? ''),
            'rear_brakes' => $strNull($_POST['rear_brakes'] ?? ''),
        ]);
        $upsert('car_suspension_specs', $target_id, [
            'front_suspension' => $strNull($_POST['front_suspension'] ?? ''),
            'rear_suspension' => $strNull($_POST['rear_suspension'] ?? ''),
        ]);
        $upsert('car_steering_specs', $target_id, [
            'steering_type' => $strNull($_POST['steering_type'] ?? ''),
        ]);
        $upsert('car_tyre_specs', $target_id, [
            'front_tyres' => $strNull($_POST['front_tyres'] ?? ''),
            'rear_tyres' => $strNull($_POST['rear_tyres'] ?? ''),
            'front_rim_inches' => $strNull($_POST['front_rim_inches'] ?? ''),
            'rear_rim_inches' => $strNull($_POST['rear_rim_inches'] ?? ''),
        ]);

        $is_ev = ($engine_type_val === 'EV' || $fuel_type === 'Electric');
        if ($is_ev) {
            $upsert('car_ev_specs', $target_id, ['battery_range' => $intNull($_POST['battery_range'] ?? '')]);
        } else {
            mysqli_query($conn, "DELETE FROM car_ev_specs WHERE car_id = $target_id");
        }

        if ($car_origin === 'Used Car') {
            $inspection_pdf_val = $car['inspection_pdf'] ?? '';
            if (isset($_POST['remove_inspection_pdf']) && $_POST['remove_inspection_pdf'] === '1') {
                if (!empty($inspection_pdf_val) && file_exists($inspection_pdf_val))
                    @unlink($inspection_pdf_val);
                $inspection_pdf_val = '';
            }
            if (isset($_FILES['inspection_pdf']) && $_FILES['inspection_pdf']['error'] === UPLOAD_ERR_OK) {
                $pdf_ext = strtolower(pathinfo($_FILES['inspection_pdf']['name'], PATHINFO_EXTENSION));
                if ($pdf_ext === 'pdf' && $_FILES['inspection_pdf']['size'] <= 5 * 1024 * 1024) {
                    $pdf_dir = "../../uploads/inspection_pdfs/";
                    if (!is_dir($pdf_dir))
                        @mkdir($pdf_dir, 0777, true);
                    $pdf_name = $pdf_dir . time() . "_" . uniqid() . ".pdf";
                    if (move_uploaded_file($_FILES['inspection_pdf']['tmp_name'], $pdf_name)) {
                        if (!empty($inspection_pdf_val) && file_exists($inspection_pdf_val))
                            @unlink($inspection_pdf_val);
                        $inspection_pdf_val = $pdf_name;
                    }
                }
            }
            $upsert('used_car_details', $target_id, [
                'car_plate' => $strNull($_POST['car_plate'] ?? ''),
                'owners' => $intNull($_POST['owners'] ?? ''),
                'accident' => $strNull($_POST['accident'] ?? 'None'),
                'flood' => $strNull($_POST['flood'] ?? 'No'),
                'service_hist' => $strNull($_POST['service_hist'] ?? ''),
                'last_service' => $dateNull($_POST['last_service'] ?? ''),
                'next_service' => $intNull($_POST['next_service'] ?? ''),
                'roadtax' => $dateNull($_POST['roadtax'] ?? ''),
                'puspakom' => $dateNull($_POST['puspakom'] ?? ''),
                'rem_warranty' => $strNull($_POST['rem_warranty'] ?? 'No'),
                'defects' => $strNull($_POST['defects'] ?? ''),
                'inspection_pdf' => $strNull($inspection_pdf_val),
            ]);
        } else {
            mysqli_query($conn, "DELETE FROM used_car_details WHERE car_id = $target_id");
        }

        if (!$is_updating && !empty($_SESSION['temp_car_images'])) {
            $real_dir = "../../uploads/cars/";
            if (!is_dir($real_dir))
                @mkdir($real_dir, 0777, true);
            foreach ($_SESSION['temp_car_images'] as $temp_path) {
                if (file_exists($temp_path)) {
                    $new_path = $real_dir . basename($temp_path);
                    if (rename($temp_path, $new_path)) {
                        $np = mysqli_real_escape_string($conn, $new_path);
                        mysqli_query($conn, "INSERT INTO car_image (car_id, car_image_url) VALUES ($target_id, '$np')");
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
        $save_error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit' : 'Add' ?> Car Details — CAR<?= $display_id ?></title>
    <link rel="stylesheet" href="/Online-Car-Dealer-and-Inventory-System/CSS/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body {
            background: #f8fafc;
        }

        .topbar {
            backdrop-filter: blur(10px);
        }

        .page-title h1 {
            letter-spacing: -0.4px;
        }

        .form-control {
            width: 100%;
            height: 46px;
            border-radius: 12px;
            border: 1px solid #d1d5db;
            padding: 0 14px;
            font-size: 14px;
            background: #ffffff;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
            outline: none;
        }

        textarea.form-control {
            height: auto;
            padding: 12px 14px;
        }

        .form-control[readonly] {
            background: #f3f4f6;
            color: #6b7280;
            cursor: not-allowed;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }

        .form-group label .hint {
            color: #9ca3af;
            font-weight: 400;
            font-size: 12px;
            margin-left: 4px;
        }

        .btn-add-blue {
            border-radius: 12px;
            padding: 12px 18px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
            background: #2563eb;
            color: #fff;
            cursor: pointer;
        }

        .btn-add-blue:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.15);
        }

        .form-section {
            background: #ffffff;
            border-radius: 18px;
            padding: 24px 28px;
            margin-bottom: 24px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03), 0 8px 24px rgba(0, 0, 0, 0.04);
        }

        .section-header {
            font-size: 16px;
            font-weight: 700;
            color: #111827;
            border-bottom: 1px solid #f3f4f6;
            padding-bottom: 14px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header i {
            color: #6366f1;
        }

        .section-header.warning {
            color: #c2410c;
            border-bottom-color: #fed7aa;
        }

        .section-header.warning i {
            color: #ea580c;
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

        .form-section .form-group {
            margin-bottom: 0;
        }

        .inner-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 24px;
            background: #ffffff;
            padding: 8px;
            border-radius: 14px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03), 0 4px 12px rgba(0, 0, 0, 0.03);
            overflow-x: auto;
        }

        .inner-tab-btn {
            padding: 10px 18px;
            background: transparent;
            border: none;
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.2s ease;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .inner-tab-btn:hover {
            background: #f3f4f6;
            color: #111827;
        }

        .inner-tab-btn.active {
            background: #1e3a8a;
            color: #ffffff;
        }

        .inner-tab-btn.warning {
            color: #c2410c;
            background: #fff7ed;
        }

        .inner-tab-btn.warning.active {
            background: #c2410c;
            color: #ffffff;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.25s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #inventory-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 16px;
        }

        .inv-row {
            display: grid;
            grid-template-columns: minmax(220px, 1fr) 60px 130px 48px;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #f9fafb;
        }

        .inv-row .form-control {
            margin: 0;
        }

        .inv-qty-input {
            text-align: center !important;
            font-weight: 700 !important;
            color: #111827 !important;
            font-size: 15px !important;
        }

        .selected-color-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 2px solid #cbd5e1;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: 0.2s;
        }

        .selected-color-circle:hover {
            border-color: #9ca3af;
        }

        .custom-color-picker {
            position: relative;
        }

        .color-palette-popup {
            display: none;
            position: absolute;
            top: 50px;
            left: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            z-index: 50;
            width: 220px;
        }

        .color-palette-popup.active {
            display: grid;
        }

        .palette-swatch {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 1px solid #d1d5db;
            cursor: pointer;
            transition: transform 0.1s;
        }

        .palette-swatch:hover {
            transform: scale(1.2);
            border-color: #4b5563;
        }

        .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            border: none;
            background: #fee2e2;
            color: #ef4444;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .icon-btn:hover {
            background: #fecaca;
            transform: scale(1.05);
        }

        .image-gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 16px;
        }

        .gallery-item {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            height: 130px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
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
            top: 8px;
            right: 8px;
            background: rgba(239, 68, 68, 0.92);
            color: white;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            opacity: 0.7;
            transition: 0.2s;
        }

        .gallery-item:hover .delete-img-btn {
            opacity: 1;
        }

        .custom-file-upload {
            border: 2px dashed #cbd5e1;
            border-radius: 14px;
            padding: 32px;
            text-align: center;
            cursor: pointer;
            transition: 0.2s;
            background: #f9fafb;
            display: block;
            margin-bottom: 20px;
        }

        .custom-file-upload:hover {
            border-color: #6366f1;
            background: #eef2ff;
        }

        .custom-file-upload i {
            font-size: 28px;
            color: #9ca3af;
            margin-bottom: 8px;
        }

        .custom-file-upload span {
            display: block;
            font-size: 14px;
            color: #6b7280;
            font-weight: 500;
        }

        .origin-select {
            border: none;
            outline: none;
            text-transform: uppercase;
            cursor: pointer;
            font-weight: 700;
            font-size: 12px;
            padding: 6px 14px;
            border-radius: 999px;
        }

        .origin-select.new {
            background: #dbeafe;
            color: #1e40af;
        }

        .origin-select.used {
            background: #f3f4f6;
            color: #4b5563;
        }

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 999px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }

        .custom-dropdown-container {
            position: relative;
            width: 100%;
        }

        .custom-dropdown-selected {
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            user-select: none;
            background: #ffffff;
        }

        .custom-dropdown-selected::after {
            content: "";
            border: solid #6b7280;
            border-width: 0 2px 2px 0;
            display: inline-block;
            padding: 3px;
            transform: rotate(45deg);
            margin-right: 4px;
        }

        .custom-dropdown-list {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            margin-top: 4px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            z-index: 999;
            max-height: 160px;
            overflow-y: auto;
        }

        .custom-dropdown-list.active {
            display: block;
        }

        .custom-dropdown-item {
            padding: 10px 14px;
            cursor: pointer;
            font-size: 14px;
            color: #111827;
            transition: background 0.2s;
        }

        .custom-dropdown-item:hover {
            background: #f3f4f6;
            color: #2563eb;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <?php if (!empty($save_error)): ?>
            <div
                style="background:#fee2e2;color:#991b1b;padding:16px 20px;border-radius:12px;margin-bottom:20px;border:1px solid #fca5a5;">
                <strong><i class="fas fa-exclamation-circle"></i> Save failed:</strong> <?= htmlspecialchars($save_error) ?>
            </div>
        <?php endif; ?>

        <form id="mainCarForm" method="POST" enctype="multipart/form-data" novalidate>

            <header class="topbar"
                style="margin-bottom:24px;position:sticky;top:0;background:#f8fafc;z-index:100;padding-top:16px;padding-bottom:16px;border-bottom:1px solid #e5e7eb;">
                <div class="page-title" style="display:flex;align-items:center;gap:16px;">
                    <a href="manage cars.php" style="color:#6b7280;font-size:18px;"><i
                            class="fas fa-arrow-left"></i></a>
                    <h1 style="font-size:24px;font-weight:700;color:#111827;">
                        <?= $is_edit ? 'Edit Car Details' : 'Add New Car' ?>
                        <span
                            style="font-size:13px;color:#9ca3af;font-weight:500;margin-left:8px;">CAR<?= $display_id ?></span>
                    </h1>
                    <select id="car_origin_select" name="car_origin"
                        class="origin-select <?= $is_used_car ? 'used' : 'new' ?>">
                        <option value="New Car" <?= $car['car_origin'] === 'New Car' ? 'selected' : '' ?>>NEW CAR</option>
                        <option value="Used Car" <?= $car['car_origin'] === 'Used Car' ? 'selected' : '' ?>>USED CAR
                        </option>
                    </select>
                </div>
                <div style="display:flex;gap:12px;">
                    <button type="submit" name="save_all_details" class="btn-add-blue" style="border:none;">
                        <i class="fas fa-save"></i> Save All
                    </button>
                </div>
            </header>

            <div class="inner-tabs">
                <button type="button" class="inner-tab-btn active" data-target="tab-basic"><i
                        class="fas fa-info-circle"></i> Basic &amp; Pricing</button>
                <button type="button" class="inner-tab-btn" data-target="tab-specs"><i class="fas fa-cogs"></i>
                    Specifications</button>
                <button type="button" class="inner-tab-btn" data-target="tab-chassis"><i class="fas fa-car-side"></i>
                    Chassis</button>
                <button type="button" class="inner-tab-btn" data-target="tab-media"><i class="fas fa-images"></i>
                    Media</button>
                <button type="button" id="tab-history-btn" class="inner-tab-btn warning" data-target="tab-history"><i
                        class="fas fa-history"></i> Used Car Details</button>
            </div>

            <div id="tab-basic" class="tab-content active">
                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-car"></i> Basic Information</h3>
                    <div class="grid-3" style="margin-bottom:16px;">
                        <div class="form-group">
                            <label>Brand</label>
                            <input type="text" name="car_brand" class="form-control"
                                value="<?= htmlspecialchars($car['car_brand']) ?>" placeholder="e.g., Proton">
                        </div>
                        <div class="form-group">
                            <label>Model</label>
                            <input type="text" name="car_model" class="form-control"
                                value="<?= htmlspecialchars($car['car_model']) ?>" placeholder="e.g., X50">
                        </div>
                        <div class="form-group">
                            <label>Variant</label>
                            <input type="text" name="car_variant" class="form-control"
                                value="<?= htmlspecialchars($car_variant) ?>" placeholder="e.g., 1.5 TGDi Flagship">
                        </div>
                    </div>

                    <div class="grid-4" style="margin-bottom:16px;">
                        <div class="form-group">
                            <label>Body Type</label>
                            <select name="body_type" class="form-control">
                                <?php
                                $type_options = mysqli_query($conn, "SELECT car_type_name FROM car_types ORDER BY car_type_id");
                                while ($t = mysqli_fetch_assoc($type_options)):
                                    $sel = ($car['body_type'] === $t['car_type_name'] || $car['car_type_name'] === $t['car_type_name']) ? 'selected' : ''; ?>
                                    <option value="<?= htmlspecialchars($t['car_type_name']) ?>" <?= $sel ?>>
                                        <?= htmlspecialchars($t['car_type_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Year</label>
                            <input type="number" min="1985" max="2100" placeholder="<?= date('Y') ?>" step="1"
                                onkeydown="
            if(['-','e','.'].includes(event.key)) event.preventDefault();
            if(event.key === '0' && this.value === '') event.preventDefault();
        " oninput="
            this.value = this.value.replace(/^0+/, '');
            if (this.value !== '' && +this.value > 2100) this.value = 2100;
        " onblur="
            let currentYear = new Date().getFullYear();
            this.value = (this.value === '' || isNaN(this.value) || +this.value < 1985) ? currentYear : parseInt(this.value, 10);
        " name="car_year" class="form-control" value="<?= htmlspecialchars($car['car_year']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Seats</label>
                            <input type="number" name="seats" min="2" max="9"
                                oninput="this.value=this.value.replace(/[^0-9]/g,'')" class="form-control"
                                value="<?= htmlspecialchars($car['seats']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Mileage <span class="hint">(km)</span></label>
                            <input type="number" name="car_mileage" min="0" max="10000000"
                                oninput="this.value=this.value.replace(/[^0-9]/g,'')" name="car_mileage"
                                class="form-control" value="<?= htmlspecialchars($car['car_mileage']) ?>">
                        </div>
                    </div>

                    <div class="grid-2" style="margin-bottom:16px;">
                        <div class="form-group">
                            <label>Fuel Type</label>
                            <select name="fuel_type" class="form-control" id="fuel_type_select">
                                <?php foreach (['RON95', 'RON97', 'Diesel', 'Hybrid', 'Electric'] as $ft): ?>
                                    <option <?= $car['fuel_type'] === $ft ? 'selected' : '' ?>><?= $ft ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Transmission</label>
                            <select name="transmission" class="form-control">
                                <?php foreach (['Auto', 'Manual', 'CVT', 'DCT', 'AMT'] as $tr): ?>
                                    <option <?= $car['transmission'] === $tr ? 'selected' : '' ?>><?= $tr ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control"
                            rows="4"><?= htmlspecialchars($car['description']) ?></textarea>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-tag"></i> Pricing &amp; Status</h3>
                    <div class="grid-3">
                        <div class="form-group">
                            <label>Price (RM)</label>
                            <input type="number" inputmode="decimal" step="0.01" min="0" max="1000000" onkeydown="
            if(['-','e'].includes(event.key)) event.preventDefault();
            if(event.key === '0' && this.value === '0') event.preventDefault();
        " oninput="
            this.value = this.value.replace(/^0+(?=\d)/, '');
            if (this.value.includes('.')) this.value = this.value.substring(0, this.value.indexOf('.') + 3);
            if (this.value !== '' && +this.value > 1000000) this.value = 1000000;
        " onblur="this.value = (this.value === '' || isNaN(this.value) || +this.value < 0) ? 0 : +this.value;"
                                name="car_status_price" class="form-control"
                                value="<?= htmlspecialchars($car['car_status_price']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="car_status_status" class="form-control">
                                <?php foreach (['Active', 'Inactive'] as $st): ?>
                                    <option <?= $car['car_status_status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Total Stock <span class="hint">(auto-calculated)</span></label>
                            <input type="number" class="form-control" id="display_stock" min="0" max="1000" oninput="this.value=this.value.replace(/[^0-9]/g,'')
                                value=" <?= htmlspecialchars($car['car_status_stock_quantity']) ?>" readonly>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-palette"></i> Colors &amp; Stock</h3>
                    <p style="color:#6b7280;font-size:13px;margin-bottom:18px;">
                        For used cars, only one color entry is allowed with quantity fixed at 1. For new cars, you can
                        add multiple colors and specify stock quantity for each.
                    </p>
                    <div id="inventory-container"></div>
                    <button type="button" id="btnAddColor" class="btn-add-blue" style="margin-top:6px;border:none;"
                        onclick="addInventoryRow()">
                        <i class="fas fa-plus"></i> Add Color
                    </button>

                    <?php if (!empty($inventory_rows)): ?>
                        <script>window.__PRELOAD_INVENTORY = <?= json_encode($inventory_rows) ?>;</script>
                    <?php endif; ?>
                </div>
            </div>

            <div id="tab-specs" class="tab-content">
                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-bolt"></i> Engine Specifications</h3>
                    <div class="grid-3" style="margin-bottom:16px;">
                        <div class="form-group">
                            <label>Fuel Type</label>
                            <select name="engine_type" class="form-control" id="engine_type_select">
                                <?php foreach (['Petrol', 'Diesel', 'Hybrid', 'EV'] as $et): ?>
                                    <option <?= $car['engine_type'] === $et ? 'selected' : '' ?>><?= $et ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Engine Displacement <span class="hint">(cc)</span></label>
                            <input type="number" inputmode="numeric"
                                oninput="this.value=this.value.replace(/[^0-9]/g,'')" name="engine_cc"
                                class="form-control" value="<?= htmlspecialchars($car['engine_cc']) ?>" min="0"
                                max="5000">
                        </div>
                        <div class="form-group">
                            <label>Compression Ratio</label>
                            <input type="text" name="compression_ratio" class="form-control" placeholder="e.g.10.5:1"
                                value="<?= htmlspecialchars($car['compression_ratio']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Peak Power <span class="hint">(kW)</span></label>
                            <input type="number" inputmode="numeric"
                                oninput="this.value=this.value.replace(/[^0-9]/g,'')" name="peak_power_kw"
                                class="form-control" value="<?= htmlspecialchars($car['peak_power_kw']) ?>" min="0"
                                max="2000">
                        </div>
                        <div class="form-group">
                            <label>Peak Torque <span class="hint">(Nm)</span></label>
                            <input type="number" inputmode="numeric"
                                oninput="this.value=this.value.replace(/[^0-9]/g,'')" name="peak_torque_nm"
                                class="form-control" value="<?= htmlspecialchars($car['peak_torque_nm']) ?>" min="0"
                                max="2400">
                        </div>
                    </div>
                </div>

                <div class="form-section" id="ev_section" style="display:none;">
                    <h3 class="section-header"><i class="fas fa-plug"></i> EV Specifications</h3>
                    <div class="form-group" style="max-width:50%;">
                        <label>Battery Range <span class="hint">(km)</span></label>
                        <input type="number" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                            name="battery_range" class="form-control"
                            value="<?= htmlspecialchars($car['battery_range']) ?>" min="0" max="1000">
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-ruler-combined"></i> Dimensions</h3>
                    <div class="grid-3">
                        <div class="form-group"><label>Length <span class="hint">(mm)</span></label><input type="number"
                                inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')" name="length"
                                class="form-control" value="<?= htmlspecialchars($car['length']) ?>" min="0"
                                max="10000"></div>
                        <div class="form-group"><label>Width <span class="hint">(mm)</span></label><input type="number"
                                inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')" name="width"
                                class="form-control" value="<?= htmlspecialchars($car['width']) ?>" min="0" max="10000">
                        </div>
                        <div class="form-group"><label>Height <span class="hint">(mm)</span></label><input type="number"
                                inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')" name="height"
                                class="form-control" value="<?= htmlspecialchars($car['height']) ?>" min="0"
                                max="10000"></div>
                        <div class="form-group"><label>Wheelbase <span class="hint">(mm)</span></label><input
                                type="number" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                                name="wheelbase" class="form-control" value="<?= htmlspecialchars($car['wheelbase']) ?>"
                                min="0" max="10000">
                        </div>
                        <div class="form-group"><label>Fuel Tank <span class="hint">(L)</span></label><input
                                type="number" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                                name="fuel_tank" class="form-control" value="<?= htmlspecialchars($car['fuel_tank']) ?>"
                                min="0" max="10000"></div>
                        <div class="form-group"><label>Kerb Weight <span class="hint">(kg)</span></label><input
                                type="number" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                                name="weight" class="form-control" value="<?= htmlspecialchars($car['weight']) ?>"
                                min="0" max="10000">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-couch"></i> Features &amp; Equipment</h3>
                    <div class="grid-3" style="margin-bottom:16px;">
                        <div class="form-group"><label>Interior Color</label><input type="text" name="int_color"
                                class="form-control" value="<?= htmlspecialchars($car['int_color']) ?>"></div>
                        <div class="form-group">
                            <label>Seat Material</label>
                            <select name="seat_mat" class="form-control">
                                <?php foreach (['Fabric', 'Leather', 'Half-leather', 'Synthetic Leather'] as $sm): ?>
                                    <option <?= $car['seat_mat'] === $sm ? 'selected' : '' ?>><?= $sm ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Wheel Size</label><input type="number" name="wheel_size"
                                class="form-control" oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                                value="<?= htmlspecialchars($car['wheel_size']) ?>" min="0" max="18"></div>
                        <div class="form-group">
                            <label>Headlights</label>
                            <select name="headlights" class="form-control">
                                <?php foreach (['Halogen', 'LED', 'Matrix LED', 'Laser'] as $hl): ?>
                                    <option <?= $car['headlights'] === $hl ? 'selected' : '' ?>><?= $hl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Infotainment Screen <span class="hint">(inches)</span></label>
                            <input type="number" name="screen" placeholder="10.25" class="form-control"
                                inputmode="decimal" step="0.01" min="0" max="20" onkeydown="
            if(['-','e'].includes(event.key)) event.preventDefault();
            if(event.key === '0' && this.value === '0') event.preventDefault();
        " oninput="
            this.value = this.value.replace(/^0+(?=\d)/, '');
            if (this.value.includes('.')) this.value = this.value.substring(0, this.value.indexOf('.') + 3);
            if (this.value !== '' && +this.value > 20) this.value = 20;
        " onblur="this.value = (this.value === '' || isNaN(this.value) || +this.value < 0) ? '' : +this.value;"
                                value="<?= htmlspecialchars($car['screen']) ?>">
                        </div>
                        <div class="form-group"><label>Airbags Count</label><input type="number" inputmode="numeric"
                                placeholder="20" oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                                name="airbags_count" class="form-control"
                                value="<?= htmlspecialchars($car['airbags_count']) ?>" min="0" max="20"></div>
                    </div>
                    <div class="form-group">
                        <label>Comfort &amp; Convenience Features</label>
                        <textarea name="feat_conf" class="form-control" rows="3"
                            placeholder="e.g. Sunroof, Wireless Charging, Auto AC, Ambient Lighting"><?= htmlspecialchars($car['feat_conf']) ?></textarea>
                    </div>
                </div>
            </div>

            <div id="tab-chassis" class="tab-content">
                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-circle-notch"></i> Brake Specifications</h3>
                    <div class="grid-2">
                        <div class="form-group"><label>Front Brakes</label><input type="text" name="front_brakes"
                                class="form-control" placeholder="e.g., Ventilated Disc"
                                value="<?= htmlspecialchars($car['front_brakes']) ?>"></div>
                        <div class="form-group"><label>Rear Brakes</label><input type="text" name="rear_brakes"
                                class="form-control" placeholder="e.g., Solid Disc"
                                value="<?= htmlspecialchars($car['rear_brakes']) ?>"></div>
                    </div>
                </div>
                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-wave-square"></i> Suspension Specifications</h3>
                    <div class="grid-2">
                        <div class="form-group"><label>Front Suspension</label><input type="text"
                                name="front_suspension" class="form-control" placeholder="e.g., MacPherson Strut"
                                value="<?= htmlspecialchars($car['front_suspension']) ?>"></div>
                        <div class="form-group"><label>Rear Suspension</label><input type="text" name="rear_suspension"
                                class="form-control" placeholder="e.g., Multi-link"
                                value="<?= htmlspecialchars($car['rear_suspension']) ?>"></div>
                    </div>
                </div>
                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-arrows-alt-h"></i> Steering Specifications</h3>
                    <div class="form-group" style="max-width:50%;">
                        <label>Steering Type</label>
                        <input type="text" name="steering_type" class="form-control"
                            placeholder="e.g., Electric Power-Assisted (EPS)"
                            value="<?= htmlspecialchars($car['steering_type']) ?>">
                    </div>
                </div>
                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-circle"></i> Tyre Specifications</h3>
                    <div class="grid-2" style="margin-bottom:16px;">
                        <div class="form-group"><label>Front Tyres</label><input type="text" name="front_tyres"
                                class="form-control" placeholder="215/55 R18"
                                value="<?= htmlspecialchars($car['front_tyres']) ?>"></div>
                        <div class="form-group"><label>Rear Tyres</label><input type="text" name="rear_tyres"
                                class="form-control" placeholder="215/55 R18"
                                value="<?= htmlspecialchars($car['rear_tyres']) ?>"></div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group"><label>Front Rim <span class="hint">(inches)</span></label><input
                                type="number" name="front_rim_inches" class="form-control" placeholder="18"
                                value="<?= htmlspecialchars($car['front_rim_inches']) ?>" step="0.1" min="0" max="18"></div>
                        <div class="form-group"><label>Rear Rim <span class="hint">(inches)</span></label><input
                                type="number" name="rear_rim_inches" class="form-control" placeholder="18"
                                value="<?= htmlspecialchars($car['rear_rim_inches']) ?>" step="0.1" min="0" max="18"></div>
                    </div>
                </div>
            </div>

            <div id="tab-media" class="tab-content">
                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-camera"></i> Car Gallery <span
                            style="color:#9ca3af;font-weight:400;font-size:13px;margin-left:8px;">Changes save
                            instantly</span></h3>
                    <input type="file" id="ajaxImageInput" name="ajax_car_images[]" multiple accept="image/*"
                        style="display:none;">
                    <label for="ajaxImageInput" class="custom-file-upload">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Click to Upload New Images (Multiple allowed)</span>
                        <span style="color:#9ca3af;font-size:12px;">JPG, PNG, WEBP, GIF</span>
                    </label>
                    <div id="imageGalleryGrid" class="image-gallery-grid">
                        <?php if ($is_edit && !empty($car_images)): ?>
                            <?php foreach ($car_images as $img): ?>
                                <div class="gallery-item" id="img_container_<?= $img['car_image_id'] ?>">
                                    <img src="<?= htmlspecialchars($img['car_image_url']) ?>">
                                    <button type="button" class="delete-img-btn"
                                        onclick="deleteExistingImage(<?= $img['car_image_id'] ?>)"><i
                                            class="fas fa-trash-alt"></i></button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if (!$is_edit && !empty($_SESSION['temp_car_images'])): ?>
                            <?php foreach ($_SESSION['temp_car_images'] as $index => $temp_path): ?>
                                <div class="gallery-item" id="img_container_temp_<?= $index ?>">
                                    <img src="<?= htmlspecialchars($temp_path) ?>">
                                    <button type="button" class="delete-img-btn"
                                        onclick="deleteTempImage('temp_<?= $index ?>')"><i
                                            class="fas fa-trash-alt"></i></button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="tab-history" class="tab-content">
                <div class="form-section">
                    <h3 class="section-header warning">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        Location
                    </h3>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>State</label>
                            <div class="custom-dropdown-container" id="state_wrapper">
                                <div class="form-control custom-dropdown-selected" id="state_display">Select State</div>
                                <div class="custom-dropdown-list" id="state_list"></div>
                                <input type="hidden" id="state_input" name="location_state"
                                    value="<?= htmlspecialchars($car['location_state']) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>City</label>
                            <div class="custom-dropdown-container" id="city_wrapper">
                                <div class="form-control custom-dropdown-selected" id="city_display">Select City</div>
                                <div class="custom-dropdown-list" id="city_list"></div>
                                <input type="hidden" id="city_input" name="location_city"
                                    value="<?= htmlspecialchars($car['location_city']) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-header warning"><i class="fas fa-file-contract"></i> Used Car History</h3>
                    <div class="grid-3" style="margin-bottom:16px;">
                        <div class="form-group"><label>Plate Number</label><input type="text" name="car_plate"
                                autocomplete="off" class="form-control"
                                value="<?= htmlspecialchars($car['car_plate']) ?>"></div>
                        <div class="form-group"><label>Previous Owners</label><input type="number" inputmode="numeric"
                                oninput="this.value=this.value.replace(/[^0-9]/g,'')" name="owners" class="form-control"
                                value="<?= htmlspecialchars($car['owners']) ?>" min="0" max="20"></div>
                        <div class="form-group">
                            <label>Remaining Warranty</label>
                            <select name="rem_warranty" class="form-control">
                                <?php foreach (['No', 'Yes'] as $rw): ?>
                                    <option <?= $car['rem_warranty'] === $rw ? 'selected' : '' ?>><?= $rw ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid-2" style="margin-bottom:16px;">
                        <div class="form-group">
                            <label>Accident History</label>
                            <select name="accident" class="form-control">
                                <?php foreach (['None', 'Minor', 'Major'] as $ac): ?>
                                    <option <?= $car['accident'] === $ac ? 'selected' : '' ?>><?= $ac ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Flood / Fire</label>
                            <select name="flood" class="form-control">
                                <?php foreach (['No', 'Flood', 'Fire'] as $fl): ?>
                                    <option <?= $car['flood'] === $fl ? 'selected' : '' ?>><?= $fl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:16px;">
                        <label>Service History</label>
                        <input type="text" name="service_hist" class="form-control"
                            placeholder="e.g., Full service records available"
                            value="<?= htmlspecialchars($car['service_hist']) ?>">
                    </div>
                    <div class="grid-2" style="margin-bottom:16px;">
                        <div class="form-group"><label>Last Service Date</label><input type="date" name="last_service"
                                class="form-control" value="<?= htmlspecialchars($car['last_service']) ?>"></div>
                        <div class="form-group"><label>Next Service <span class="hint">(km)</span></label><input
                                type="number" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                                name="next_service" class="form-control"
                                value="<?= htmlspecialchars($car['next_service']) ?>" min="0" max="1000000"></div>
                    </div>
                    <div class="grid-2" style="margin-bottom:16px;">
                        <div class="form-group"><label>Roadtax Expiry</label><input type="date" name="roadtax"
                                class="form-control" value="<?= htmlspecialchars($car['roadtax']) ?>"></div>
                        <div class="form-group"><label>Puspakom Date</label><input type="date" name="puspakom"
                                class="form-control" value="<?= htmlspecialchars($car['puspakom']) ?>"></div>
                    </div>
                    <div class="form-group" style="margin-bottom:16px;">
                        <label>Known Defects</label>
                        <textarea name="defects" class="form-control"
                            rows="3"><?= htmlspecialchars($car['defects']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Inspection Report PDF <span class="hint">(max 5MB)</span></label>
                        <?php if (!empty($car['inspection_pdf'])): ?>
                            <div
                                style="display:flex;align-items:center;gap:12px;margin-bottom:10px;padding:12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;">
                                <i class="fas fa-file-pdf" style="color:#ef4444;font-size:20px;"></i>
                                <a href="<?= htmlspecialchars($car['inspection_pdf']) ?>" target="_blank"
                                    style="color:#15803d;font-weight:600;text-decoration:none;flex:1;">View Current
                                    Inspection PDF</a>
                                <label
                                    style="display:flex;align-items:center;gap:6px;cursor:pointer;color:#b91c1c;font-size:13px;font-weight:600;">
                                    <input type="checkbox" name="remove_inspection_pdf" value="1"> Remove
                                </label>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="inspection_pdf" accept="application/pdf" class="form-control"
                            style="padding:10px 14px;height:auto;background:#f9fafb;border:1px dashed #cbd5e1;cursor:pointer;">
                    </div>
                </div>
            </div>
        </form>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>window.EDIT_CAR_ID = <?= $car_id ?>;</script>
    <script src="../../JAVA SCRIPT/edit_car_details.js?v=<?= time() ?>"></script>
</body>

</html>