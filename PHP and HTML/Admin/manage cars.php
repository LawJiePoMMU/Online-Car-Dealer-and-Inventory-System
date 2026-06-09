<?php
session_name("AdminSession");
session_start();
include '../Config/database.php';
include '../Config/functions.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$threshold_query = mysqli_query($conn, "SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold'");
$res_threshold = mysqli_fetch_assoc($threshold_query);
$low_stock_limit = isset($res_threshold['setting_value']) ? (int) $res_threshold['setting_value'] : 2;
if (isset($_GET['highlight']) && !isset($_GET['p_new']) && !isset($_GET['p_used'])) {
    $hl_id = (int) $_GET['highlight'];
    $car_q = mysqli_query($conn, "SELECT car_origin FROM cars WHERE car_id = $hl_id LIMIT 1");
    if ($cr = mysqli_fetch_assoc($car_q)) {
        $target_tab = ($cr['car_origin'] === 'Used Car') ? 'used' : 'new';
        if ($target_tab === 'new') {
            $order_q = mysqli_query($conn, "
                SELECT c.car_id FROM cars c 
                WHERE c.car_origin = 'New Car' 
                ORDER BY (CASE WHEN IFNULL((SELECT SUM(quantity) FROM car_inventory WHERE car_id = c.car_id), 0) <= $low_stock_limit THEN 0 ELSE 1 END) ASC, c.car_brand ASC, c.car_model ASC, c.car_id DESC
            ");
            $page_param = 'p_new';
        } else {
            $order_q = mysqli_query($conn, "
                SELECT c.car_id FROM cars c 
                LEFT JOIN car_status stat ON c.car_id = stat.car_id 
                WHERE c.car_origin = 'Used Car' 
                ORDER BY FIELD(stat.car_status_status, 'Active', 'Inactive') ASC, c.car_brand ASC, c.car_model ASC, c.car_id DESC
            ");
            $page_param = 'p_used';
        }
        $pos = 0;
        $found = 0;
        while ($r = mysqli_fetch_assoc($order_q)) {
            $pos++;
            if ($r['car_id'] == $hl_id) {
                $found = $pos;
                break;
            }
        }
        if ($found > 0) {
            $target_page = (int) ceil($found / 10);
            header("Location: ?tab=$target_tab&$page_param=$target_page&highlight=$hl_id");
            exit();
        }
    }
}
$low_stock_query = mysqli_query($conn, "
    SELECT c.car_id, c.car_brand, c.car_model, 
           GROUP_CONCAT(DISTINCT inv.variant SEPARATOR ', ') as variant,
           GROUP_CONCAT(DISTINCT CONCAT(inv.color_name, ' (', inv.quantity, ' unit)') ORDER BY inv.color_name SEPARATOR ', ') as low_colors
    FROM cars c
    LEFT JOIN car_status stat ON c.car_id = stat.car_id
    LEFT JOIN car_inventory inv ON c.car_id = inv.car_id
    WHERE c.car_origin = 'New Car'
    AND stat.car_status_status = 'Active'
    AND inv.quantity <= $low_stock_limit
    AND inv.quantity >= 0
    GROUP BY c.car_id, c.car_brand, c.car_model
");
while ($low_car = mysqli_fetch_assoc($low_stock_query)) {
    $car_id_check = (int) $low_car['car_id'];
    $existing = mysqli_query($conn, "
        SELECT notification_id FROM notifications 
        WHERE notification_message LIKE '%[car_id:{$car_id_check}]%'
        AND notification_created_at >= NOW() - INTERVAL 24 HOUR
        LIMIT 1
    ");
    if (mysqli_num_rows($existing) > 0)
        continue;
    $brand = mysqli_real_escape_string($conn, $low_car['car_brand']);
    $model = mysqli_real_escape_string($conn, $low_car['car_model']);
    $variant = !empty($low_car['variant']) ? mysqli_real_escape_string($conn, $low_car['variant']) : '';
    $low_colors = $low_car['low_colors'];
    $variant_part = !empty($variant) ? " ({$variant})" : '';
    $msg = "Stock Alert [car_id:{$car_id_check}]: {$brand} {$model}{$variant_part} - low stock on {$low_colors}.";
    broadcast_notification_to_admins($conn, $msg);
}

mysqli_query($conn, "INSERT IGNORE INTO car_types (car_type_id, car_type_name) VALUES 
    (1, 'Sedan'), (2, 'SUV'), (3, 'Hatchback'), (4, 'MPV')");

if (isset($_GET['ajax'], $_GET['toggle_id'], $_GET['current_status'])) {
    $tid = (int) $_GET['toggle_id'];
    $newSt = ($_GET['current_status'] === 'Active') ? 'Inactive' : 'Active';
    mysqli_query($conn, "UPDATE car_status SET car_status_status='$newSt', car_status_updated_at=NOW() WHERE car_id=$tid");
    echo json_encode(['status' => 'success', 'new_status' => $newSt]);
    exit();
}

if (isset($_GET['ajax'], $_GET['action']) && $_GET['action'] === 'copy_cars') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['car_ids']) && is_array($input['car_ids'])) {
        try {
            mysqli_begin_transaction($conn);
            $new_car_id = null;
            foreach ($input['car_ids'] as $old_id) {
                $old_id = (int) $old_id;
                mysqli_query($conn, "
                    INSERT INTO cars (car_type_id, location_id, car_brand, car_model, car_year, body_type, seats, fuel_type, transmission, car_mileage, car_origin, description, car_created_at)
                    SELECT car_type_id, location_id, car_brand, car_model, car_year, body_type, seats, fuel_type, transmission, 0, car_origin, description, NOW()
                    FROM cars WHERE car_id = $old_id
                ");
                $new_car_id = mysqli_insert_id($conn);
                mysqli_query($conn, "INSERT INTO car_engine_specs (car_id, engine_cc, compression_ratio, peak_power_kw, peak_torque_nm, engine_type) SELECT $new_car_id, engine_cc, compression_ratio, peak_power_kw, peak_torque_nm, engine_type FROM car_engine_specs WHERE car_id = $old_id");
                mysqli_query($conn, "INSERT INTO car_dimensions (car_id, length, width, height, wheelbase, fuel_tank, weight) SELECT $new_car_id, length, width, height, wheelbase, fuel_tank, weight FROM car_dimensions WHERE car_id = $old_id");
                mysqli_query($conn, "INSERT INTO car_features (car_id, int_color, seat_mat, wheel_size, headlights, screen, feat_conf, airbags_count) SELECT $new_car_id, int_color, seat_mat, wheel_size, headlights, screen, feat_conf, airbags_count FROM car_features WHERE car_id = $old_id");
                mysqli_query($conn, "INSERT INTO car_ev_specs (car_id, battery_range) SELECT $new_car_id, battery_range FROM car_ev_specs WHERE car_id = $old_id");
                mysqli_query($conn, "INSERT INTO car_brake_specs (car_id, front_brakes, rear_brakes) SELECT $new_car_id, front_brakes, rear_brakes FROM car_brake_specs WHERE car_id = $old_id");
                mysqli_query($conn, "INSERT INTO car_steering_specs (car_id, steering_type) SELECT $new_car_id, steering_type FROM car_steering_specs WHERE car_id = $old_id");
                mysqli_query($conn, "INSERT INTO car_suspension_specs (car_id, front_suspension, rear_suspension) SELECT $new_car_id, front_suspension, rear_suspension FROM car_suspension_specs WHERE car_id = $old_id");
                mysqli_query($conn, "INSERT INTO car_tyre_specs (car_id, front_tyres, rear_tyres, front_rim_inches, rear_rim_inches) SELECT $new_car_id, front_tyres, rear_tyres, front_rim_inches, rear_rim_inches FROM car_tyre_specs WHERE car_id = $old_id");
                $origin_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT car_origin FROM cars WHERE car_id = $new_car_id"));
                if ($origin_check && $origin_check['car_origin'] === 'Used Car') {
                    mysqli_query($conn, "INSERT INTO used_car_details (car_id, car_plate, owners, accident, flood, service_hist, last_service, next_service, roadtax, puspakom, rem_warranty, defects, inspection_pdf) VALUES ($new_car_id, NULL, NULL, 'None', 'No', NULL, NULL, NULL, NULL, NULL, 'No', NULL, NULL)");
                }
                mysqli_query($conn, "INSERT INTO car_inventory (car_id, variant, color_name, color_hex, quantity) SELECT $new_car_id, variant, color_name, color_hex, 0 FROM car_inventory WHERE car_id = $old_id");
                mysqli_query($conn, "INSERT INTO car_image (car_id, car_image_url) SELECT $new_car_id, car_image_url FROM car_image WHERE car_id = $old_id");
                mysqli_query($conn, "INSERT INTO car_status (car_id, car_status_price, car_status_stock_quantity, car_status_status, car_status_updated_at) SELECT $new_car_id, car_status_price, 0, 'Draft', NOW() FROM car_status WHERE car_id = $old_id");
            }
            mysqli_commit($conn);
            echo json_encode(['status' => 'success', 'new_id' => $new_car_id]);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No IDs received.']);
    }
    exit();
}
if (isset($_GET['ajax'], $_GET['action'], $_GET['car_id']) && $_GET['action'] === 'quick_view') {
    $vid = (int) $_GET['car_id'];
    $query = "
    SELECT 
        c.*,
        ct.car_type_name,
        loc.location_state, loc.location_city,
        stat.car_status_price, stat.car_status_status,

        eng.engine_cc, eng.compression_ratio, eng.peak_power_kw, eng.peak_torque_nm, eng.engine_type,
        dim.length, dim.width, dim.height, dim.wheelbase, dim.fuel_tank, dim.weight,
        feat.int_color, feat.seat_mat, feat.wheel_size, feat.headlights, feat.screen, feat.feat_conf, feat.airbags_count,
        ev.battery_range,
        brk.front_brakes, brk.rear_brakes,
        strg.steering_type,
        sus.front_suspension, sus.rear_suspension,
        tyr.front_tyres, tyr.rear_tyres, tyr.front_rim_inches, tyr.rear_rim_inches,

        ud.car_plate, ud.owners, ud.accident, ud.flood, ud.service_hist, ud.last_service, ud.next_service, 
        ud.roadtax, ud.puspakom, ud.rem_warranty, ud.defects, ud.inspection_pdf,

        (SELECT IFNULL(SUM(quantity),0) FROM car_inventory WHERE car_id = c.car_id) AS total_stock,
        (SELECT GROUP_CONCAT(DISTINCT CONCAT_WS('::', color_name, color_hex, quantity) SEPARATOR '||') FROM car_inventory WHERE car_id = c.car_id) AS color_data,
        (SELECT GROUP_CONCAT(DISTINCT variant SEPARATOR ', ') FROM car_inventory WHERE car_id = c.car_id) AS all_variants
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
    WHERE c.car_id = $vid
    LIMIT 1
    ";
    $q = mysqli_query($conn, $query);
    if ($r = mysqli_fetch_assoc($q)) {
        echo json_encode(['status' => 'success', 'data' => $r]);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit();
}
$active_tab = (isset($_GET['tab']) && in_array($_GET['tab'], ['new', 'used', 'history']))
    ? $_GET['tab']
    : 'new';
$allowed_status = ['all', 'Active', 'Inactive'];
$status_filter = (isset($_GET['status']) && in_array($_GET['status'], $allowed_status))
    ? $_GET['status']
    : 'all';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$count_new = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM cars WHERE car_origin = 'New Car'"))['c'];
$count_used = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(c.car_id) as c FROM cars c LEFT JOIN car_status stat ON c.car_id = stat.car_id WHERE c.car_origin = 'Used Car' AND IFNULL(stat.car_status_status,'') <> 'Sold'"))['c'];
$count_sold = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(c.car_id) as c FROM cars c LEFT JOIN car_status stat ON c.car_id = stat.car_id WHERE stat.car_status_status = 'Sold'"))['c'];
$search_condition_new = "";
if (!empty($search)) {
    $search_condition_new = " AND (c.car_brand LIKE '%$search%' OR c.car_model LIKE '%$search%') ";
}

$search_condition_used = "";
if (!empty($search)) {
    $search_condition_used = " AND (c.car_brand LIKE '%$search%' OR c.car_model LIKE '%$search%' OR ud.car_plate LIKE '%$search%') ";
}

if ($status_filter !== 'all') {
    $search_condition_new .= " AND stat.car_status_status = '$status_filter' ";
    $search_condition_used .= " AND stat.car_status_status = '$status_filter' ";
}

$base_search = "";
if (!empty($search)) {
    $base_search = " AND (c.car_brand LIKE '%$search%' OR c.car_model LIKE '%$search%' OR ud.car_plate LIKE '%$search%') ";
}

$limit = 10;
$p_new = max(1, (int) ($_GET['p_new'] ?? 1));
$p_used = max(1, (int) ($_GET['p_used'] ?? 1));
$p_hist = max(1, (int) ($_GET['p_hist'] ?? 1));
$offset_new = ($p_new - 1) * $limit;
$offset_used = ($p_used - 1) * $limit;
$offset_hist = ($p_hist - 1) * $limit;

$pages_new = $pages_used = $pages_hist = 1;
$result_new = $result_used = $result_history = null;

try {
    if ($active_tab === 'new') {
        $cf = (int) mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT COUNT(DISTINCT c.car_id) as c FROM cars c
            LEFT JOIN car_status stat ON c.car_id = stat.car_id
            LEFT JOIN used_car_details ud ON c.car_id = ud.car_id
            WHERE c.car_origin = 'New Car' $search_condition_new"))['c'];
        $pages_new = max(1, (int) ceil($cf / $limit));
        $result_new = mysqli_query($conn, "
            SELECT c.*, stat.car_status_price, stat.car_status_status, 
                   loc.location_state, loc.location_city, ct.car_type_name,
                   IFNULL((SELECT SUM(quantity) FROM car_inventory WHERE car_id = c.car_id), 0) as total_stock,
                   GROUP_CONCAT(DISTINCT inv.variant SEPARATOR ', ') as all_variants,
                   GROUP_CONCAT(DISTINCT CONCAT_WS('::', inv.color_name, inv.color_hex, inv.quantity) SEPARATOR '||') as color_data
            FROM cars c 
            LEFT JOIN car_status stat ON c.car_id = stat.car_id 
            LEFT JOIN locations loc ON c.location_id = loc.location_id
            LEFT JOIN car_types ct ON c.car_type_id = ct.car_type_id
            LEFT JOIN car_inventory inv ON c.car_id = inv.car_id
            LEFT JOIN used_car_details ud ON c.car_id = ud.car_id
            WHERE c.car_origin = 'New Car' $search_condition_new 
            GROUP BY c.car_id 
            ORDER BY (CASE WHEN IFNULL((SELECT SUM(quantity) FROM car_inventory WHERE car_id = c.car_id), 0) <= $low_stock_limit THEN 0 ELSE 1 END) ASC, c.car_brand ASC, c.car_model ASC, c.car_id DESC
            LIMIT $limit OFFSET $offset_new
        ");
    } elseif ($active_tab === 'used') {
        $cf = (int) mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT COUNT(DISTINCT c.car_id) as c FROM cars c
            LEFT JOIN car_status stat ON c.car_id = stat.car_id
            LEFT JOIN used_car_details ud ON c.car_id = ud.car_id
            WHERE c.car_origin = 'Used Car' AND IFNULL(stat.car_status_status,'') <> 'Sold' $search_condition_used"))['c'];
        $pages_used = max(1, (int) ceil($cf / $limit));
        $result_used = mysqli_query($conn, "
            SELECT c.*, stat.car_status_price, stat.car_status_status, 
                   loc.location_state, loc.location_city, ct.car_type_name, ud.car_plate,
                   IFNULL((SELECT SUM(quantity) FROM car_inventory WHERE car_id = c.car_id), 0) as total_stock,
                   GROUP_CONCAT(DISTINCT inv.variant SEPARATOR ', ') as all_variants,
                   GROUP_CONCAT(DISTINCT CONCAT_WS('::', inv.color_name, inv.color_hex, inv.quantity) SEPARATOR '||') as color_data
            FROM cars c
            LEFT JOIN car_status stat ON c.car_id = stat.car_id 
            LEFT JOIN locations loc ON c.location_id = loc.location_id
            LEFT JOIN car_types ct ON c.car_type_id = ct.car_type_id
            LEFT JOIN car_inventory inv ON c.car_id = inv.car_id
            LEFT JOIN used_car_details ud ON c.car_id = ud.car_id
            WHERE c.car_origin = 'Used Car' AND IFNULL(stat.car_status_status,'') <> 'Sold' $search_condition_used 
            GROUP BY c.car_id
            ORDER BY FIELD(stat.car_status_status, 'Active', 'Inactive') ASC, c.car_brand ASC, c.car_model ASC, c.car_id DESC
            LIMIT $limit OFFSET $offset_used
        ");
    } else {
        $cf = (int) mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT COUNT(DISTINCT c.car_id) as c FROM cars c
            LEFT JOIN car_status stat ON c.car_id = stat.car_id
            LEFT JOIN used_car_details ud ON c.car_id = ud.car_id
            WHERE stat.car_status_status = 'Sold' $base_search"))['c'];
        $pages_hist = max(1, (int) ceil($cf / $limit));
        $result_history = mysqli_query($conn, "
            SELECT c.*, stat.car_status_price, stat.car_status_status, stat.car_status_updated_at,
                   loc.location_state, loc.location_city, ct.car_type_name, ud.car_plate,
                   GROUP_CONCAT(DISTINCT inv.variant SEPARATOR ', ') as all_variants,
                   GROUP_CONCAT(DISTINCT CONCAT_WS('::', inv.color_name, inv.color_hex, inv.quantity) SEPARATOR '||') as color_data
            FROM cars c
            LEFT JOIN car_status stat ON c.car_id = stat.car_id 
            LEFT JOIN locations loc ON c.location_id = loc.location_id
            LEFT JOIN car_types ct ON c.car_type_id = ct.car_type_id
            LEFT JOIN car_inventory inv ON c.car_id = inv.car_id
            LEFT JOIN used_car_details ud ON c.car_id = ud.car_id
            WHERE stat.car_status_status = 'Sold' $base_search 
            GROUP BY c.car_id 
            ORDER BY stat.car_status_updated_at DESC, c.car_id DESC
            LIMIT $limit OFFSET $offset_hist
        ");
    }
} catch (Exception $e) {
}

function getCarImgUrl($conn, $car_id)
{
    $q = mysqli_query($conn, "SELECT car_image_url FROM car_image WHERE car_id=" . (int) $car_id . " LIMIT 1");
    $r = mysqli_fetch_assoc($q);
    return ($r && !empty($r['car_image_url']))
        ? htmlspecialchars($r['car_image_url'])
        : '../../images/default-car.png';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Cars</title>
    <link rel="stylesheet" href="/Online-Car-Dealer-and-Inventory-System/CSS/admin.css">
    <link rel="stylesheet" href="/Online-Car-Dealer-and-Inventory-System/CSS/manage cars.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar" style="margin-bottom:24px;">
            <div class="page-title">
                <h1 style="font-size:24px;font-weight:700;color:#111827;">Manage Cars</h1>
            </div>
        </header>

        <div class="page-tabs">
            <a href="?tab=new" class="tab-item <?= $active_tab === 'new' ? 'active' : '' ?>">New Cars
                (<?= $count_new ?>)</a>
            <a href="?tab=used" class="tab-item <?= $active_tab === 'used' ? 'active' : '' ?>">Used Cars
                (<?= $count_used ?>)</a>
            <a href="?tab=history" class="tab-item <?= $active_tab === 'history' ? 'active' : '' ?>">History
                (
                <?= $count_sold ?>)
            </a>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <form method="GET" style="display:flex;gap:16px;">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
                <div style="position:relative;">
                    <i class="fas fa-search"
                        style="position:absolute;left:14px;top:11px;color:#9ca3af;font-size:14px;"></i>
                    <input type="text" name="search" class="form-control"
                        placeholder="<?= $active_tab === 'new' ? 'Search Brand or Model' : 'Search Brand, Model or Plate' ?>"
                        style="padding-left:38px;width:280px;font-size:13px;" value="<?= htmlspecialchars($search) ?>">
                </div>
                <?php if ($active_tab !== 'history'): ?>
                    <select name="status" class="form-control" style="width:150px;font-size:13px;"
                        onchange="this.form.submit()">
                        <option value="all">All Status</option>
                        <option value="Active" <?= $status_filter === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= $status_filter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                <?php endif; ?>
            </form>
            <?php if ($active_tab !== 'history'): ?>
                <div style="display:flex;gap:12px;">
                    <button type="button" class="btn-export" id="copyBtn" onclick="toggleCopyMode()"><i
                            class="fas fa-copy"></i> Copy Selected</button>
                    <button type="button" class="btn-export" id="cancelCopyBtn" onclick="cancelCopyMode()"
                        style="display:none;background-color:#64748b;color:white;border-color:#64748b;"><i
                            class="fas fa-times"></i> Cancel</button>
                    <a href="edit_car_details.php?origin=<?= htmlspecialchars($active_tab) ?>" class="btn-add-blue"
                        style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;box-sizing:border-box;"><i
                            class="fas fa-plus"></i> Add Car</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($active_tab === 'new'): ?>
            <div id="section_new" class="table-section">
                <div class="column-header"><i class="fas fa-car" style="color:#2563eb;"></i> New Cars</div>
                <div class="table-card" style="padding:0;border:none;">
                    <table class="admin-table" id="table_new" style="width:100%;">
                        <thead>
                            <tr>
                                <th class="print-hide" style="width:4%;padding-left:16px;"><input type="checkbox"
                                        class="selectAllColumn" style="cursor:pointer; display:none;"></th>
                                <th style="width:9%;text-align:left;">CAR ID</th>
                                <th style="width:25%;text-align:left;">CAR DETAILS</th>
                                <th style="width:10%;text-align:left;">TYPE</th>
                                <th style="width:12%;text-align:center;">INVENTORY</th>
                                <th style="width:10%;text-align:left;">YEAR</th>
                                <th style="width:12%;text-align:left;">PRICE</th>
                                <th style="width:10%;text-align:left;">STATUS</th>
                                <th style="width:8%;text-align:center;" class="print-hide">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result_new && mysqli_num_rows($result_new) > 0) {
                                while ($row = mysqli_fetch_assoc($result_new)) {
                                    $stock = (int) $row['total_stock'];
                                    $is_incomplete = empty($row['car_brand']) || empty($row['car_model']) || empty($row['car_year']) || empty($row['car_status_price']);
                                    $is_preorder = ($stock <= $low_stock_limit);
                                    $price = !empty($row['car_status_price']) ? "RM " . number_format($row['car_status_price']) : "TBA";
                                    $status = (!empty($row['car_status_status']) && in_array($row['car_status_status'], ['Active', 'Inactive'])) ? $row['car_status_status'] : 'Active';
                                    $dot_class = ($status == 'Active') ? 'dot-active' : 'dot-inactive';
                                    $text_class = ($status == 'Active') ? 'text-active' : 'text-inactive';
                                    $img_url = getCarImgUrl($conn, $row['car_id']);
                                    $type = !empty($row['car_type_name']) ? htmlspecialchars($row['car_type_name']) : '-';
                                    $toggle_icon = ($status == 'Active') ? 'fa-lock' : 'fa-unlock';
                                    $toggle_color = ($status == 'Active') ? '#ef4444' : '#10b981';
                                    $variants_text = !empty($row['all_variants']) ? htmlspecialchars($row['all_variants']) : 'No Variants';

                                    $color_html = "";
                                    if (!empty($row['color_data'])) {
                                        $color_items = explode('||', $row['color_data']);
                                        foreach ($color_items as $c_item) {
                                            $parts = explode('::', $c_item);
                                            if (count($parts) === 3) {
                                                $c_name = htmlspecialchars($parts[0]);
                                                $c_hex = htmlspecialchars($parts[1]);
                                                $c_qty = (int) $parts[2];
                                                $warning_class = ($c_qty <= $low_stock_limit) ? "pulse-warning" : "";
                                                $badge_bg_color = ($c_qty <= $low_stock_limit) ? "#ef4444" : "#6b7280";
                                                $color_html .= "<div style='position:relative;display:inline-block;margin-right:12px;margin-top:4px;' title='{$c_name} (Qty: {$c_qty})'>
                                                    <div class='{$warning_class}' style='background-color:{$c_hex};width:18px;height:18px;border-radius:50%;border:1px solid #d1d5db;box-shadow:0 1px 2px rgba(0,0,0,0.1);cursor:pointer;'></div>
                                                    <span style='position:absolute;top:-8px;right:-8px;background:{$badge_bg_color};color:white;font-size:9px;font-weight:bold;width:14px;height:14px;display:flex;align-items:center;justify-content:center;border-radius:50%;border:1px solid white;'>{$c_qty}</span>
                                                </div>";
                                            }
                                        }
                                    } else {
                                        $color_html = "<span style='background:#f3f4f6;color:#9ca3af;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:600;border:1px dashed #d1d5db;'>No Colors</span>";
                                    }

                                    $badge_bg = ($stock == 0) ? "#fee2e2" : (($stock <= $low_stock_limit) ? "#fef3c7" : "#dcfce7");
                                    $badge_text = ($stock == 0) ? "#991b1b" : (($stock <= $low_stock_limit) ? "#92400e" : "#166534");

                                    echo "<tr class='data-row print-new-car'>";
                                    echo "<td class='print-hide' style='padding-left:16px;'><input type='checkbox' class='row-checkbox' style='cursor:pointer; display:none;'></td>";
                                    echo "<td style='text-align:left;color:#6b7280;'>CAR" . str_pad($row['car_id'], 3, '0', STR_PAD_LEFT) . "</td>";
                                    echo "<td style='text-align:left;'>
                                        <div style='display:flex;align-items:center;gap:12px;'>
                                            <div class='print-hide' style='width:60px;height:40px;border-radius:6px;overflow:hidden;background:#f3f4f6;flex-shrink:0;border:1px solid #e5e7eb;'>
                                                <img src='{$img_url}' style='width:100%;height:100%;object-fit:cover;'>
                                            </div>
                                            <div style='display:flex;flex-direction:column;'>";
                                    if ($is_incomplete || $is_preorder) {
                                        $icon_color = $is_preorder ? "#ef4444" : "#f59e0b";
                                        $icon_title = $is_preorder ? "Low Stock (≤ {$low_stock_limit} units)" : "Information Incomplete";
                                        echo "<div style='display:flex;align-items:center;gap:5px;margin-bottom:2px;'>
                                                <i class='fas fa-exclamation-triangle' style='color:{$icon_color};font-size:12px;' title='{$icon_title}'></i>
                                                <strong style='color:#111827;font-size:13px;'>" . htmlspecialchars($row['car_brand']) . "</strong>
                                              </div>";
                                    } else {
                                        echo "<strong style='color:#111827;font-size:13px;margin-bottom:2px;'>" . htmlspecialchars($row['car_brand']) . "</strong>";
                                    }
                                    echo "<span style='color:#6b7280;font-weight:normal;font-size:12px;'>" . htmlspecialchars($row['car_model']) . "</span>";
                                    echo "<div style='display:flex;flex-direction:column;gap:4px;margin-top:6px;'>
                                            <div style='display:flex;flex-wrap:wrap;'><span style='background:#f3f4f6;color:#4b5563;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:600;' title='Variants'><i class='fas fa-car-side' style='margin-right:3px;'></i> {$variants_text}</span></div>
                                            <div style='display:flex;align-items:center;flex-wrap:wrap;'>{$color_html}</div>
                                          </div>
                                        </div>
                                    </div></td>";
                                    echo "<td style='text-align:left;'>{$type}</td>";
                                    echo "<td style='text-align:center;'><span style='background:{$badge_bg};color:{$badge_text};padding:4px 10px;border-radius:12px;font-size:11px;font-weight:700;'>{$stock} Units</span></td>";
                                    echo "<td style='text-align:left;'>" . htmlspecialchars($row['car_year']) . "</td>";
                                    echo "<td style='text-align:left;font-weight:600;'>{$price}</td>";
                                    echo "<td style='text-align:left;'><div class='status-cell' style='width:85px;'><span class='dot {$dot_class} print-hide'></span><span class='{$text_class}'>" . ucfirst($status) . "</span></div></td>";
                                    echo "<td class='print-hide' style='text-align:center;'>
                                        <div style='display:flex;justify-content:center;gap:16px;'>
                                            <a href='javascript:void(0);' onclick='quickView({$row['car_id']})' style='color:#9ca3af;' title='Quick View'><i class='fas fa-eye'></i></a>
                                            <a href='edit_car_details.php?id={$row['car_id']}' style='color:#9ca3af;' title='Edit'><i class='fas fa-pen'></i></a>
                                            <a href='javascript:void(0);' onclick='toggleStatus({$row['car_id']}, \"{$status}\", this)' style='color:{$toggle_color};' title='Toggle Status'><i class='fas {$toggle_icon}'></i></a>
                                        </div>
                                    </td></tr>";
                                }
                            } else {
                                echo "<tr><td colspan='9' style='text-align:center;padding:48px 0;color:#6b7280;font-size:14px;'>No new cars found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    <div class="pagination-container print-hide"
                        style="padding:20px;display:flex;justify-content:space-between;align-items:center;border-top:1px solid #e5e7eb;">
                        <div class="page-info" style="color:#6b7280;font-size:13px;">
                            Showing Page <?= $p_new ?> of <?= $pages_new ?>
                        </div>
                        <?php if ($pages_new > 1):
                            $base_q = "?tab=new&search=" . urlencode($search) . "&status=" . urlencode($status_filter); ?>
                            <div class="page-controls" style="display:flex;gap:8px;">
                                <a href="<?= $base_q ?>&p_new=<?= max(1, $p_new - 1) ?>"
                                    class="page-btn <?= ($p_new == 1) ? 'disabled' : '' ?>"><i class="fas fa-angle-left"></i>
                                    Prev</a>
                                <?php for ($i = 1; $i <= $pages_new; $i++): ?>
                                    <a href="<?= $base_q ?>&p_new=<?= $i ?>"
                                        class="page-btn <?= ($p_new == $i) ? 'active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                                <a href="<?= $base_q ?>&p_new=<?= min($pages_new, $p_new + 1) ?>"
                                    class="page-btn <?= ($p_new == $pages_new) ? 'disabled' : '' ?>">Next <i
                                        class="fas fa-angle-right"></i></a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($active_tab === 'used'): ?>
            <div id="section_used" class="table-section">
                <div class="column-header"><i class="fas fa-car-side" style="color:#6b7280;"></i> Used Cars</div>
                <div class="table-card" style="padding:0;border:none;">
                    <table class="admin-table" id="table_used" style="width:100%;">
                        <thead>
                            <tr>
                                <th class="print-hide" style="width:4%;padding-left:16px;"><input type="checkbox"
                                        class="selectAllColumn" style="cursor:pointer; display:none;"></th>
                                <th style="width:8%;text-align:left;">CAR ID</th>
                                <th style="width:20%;text-align:left;">CAR DETAILS</th>
                                <th style="width:9%;text-align:left;">TYPE</th>
                                <th style="width:10%;text-align:center;">INVENTORY</th>
                                <th style="width:13%;text-align:left;">LOCATION</th>
                                <th style="width:10%;text-align:left;">PLATE NO.</th>
                                <th style="width:8%;text-align:left;">YEAR</th>
                                <th style="width:10%;text-align:left;">PRICE</th>
                                <th style="width:8%;text-align:left;">STATUS</th>
                                <th style="width:8%;text-align:center;" class="print-hide">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result_used && mysqli_num_rows($result_used) > 0) {
                                while ($row = mysqli_fetch_assoc($result_used)) {
                                    $stock = (int) $row['total_stock'];
                                    $is_incomplete = empty($row['car_brand']) || empty($row['car_model']) || empty($row['car_year']) || empty($row['car_status_price']) || empty($row['car_plate']) || empty($row['location_city']);
                                    $price = !empty($row['car_status_price']) ? "RM " . number_format($row['car_status_price']) : "TBA";
                                    $status = (!empty($row['car_status_status']) && in_array($row['car_status_status'], ['Active', 'Inactive', 'Sold'])) ? $row['car_status_status'] : 'Active';
                                    $dot_class = ($status == 'Active') ? 'dot-active' : 'dot-inactive';
                                    $text_class = ($status == 'Active') ? 'text-active' : 'text-inactive';
                                    $plate = !empty($row['car_plate']) ? htmlspecialchars($row['car_plate']) : '-';
                                    $img_url = getCarImgUrl($conn, $row['car_id']);
                                    $type = !empty($row['car_type_name']) ? htmlspecialchars($row['car_type_name']) : '-';
                                    $location = !empty($row['location_city']) ? htmlspecialchars($row['location_city'] . ', ' . $row['location_state']) : '-';
                                    $toggle_icon = ($status == 'Active') ? 'fa-lock' : 'fa-unlock';
                                    $toggle_color = ($status == 'Active') ? '#ef4444' : '#10b981';
                                    $variants_text = !empty($row['all_variants']) ? htmlspecialchars($row['all_variants']) : 'No Variants';

                                    $color_html = "";
                                    if (!empty($row['color_data'])) {
                                        $color_items = explode('||', $row['color_data']);
                                        foreach ($color_items as $c_item) {
                                            $parts = explode('::', $c_item);
                                            if (count($parts) === 3) {
                                                $c_name = htmlspecialchars($parts[0]);
                                                $c_hex = htmlspecialchars($parts[1]);
                                                $c_qty = ($row['car_status_status'] === 'Sold') ? 0 : (int) $parts[2];
                                                $dot_opacity = ($row['car_status_status'] === 'Sold') ? '0.35' : '1';
                                                $color_html .= "<div style='position:relative;display:inline-block;margin-right:12px;margin-top:4px;' title='{$c_name} (Qty: {$c_qty})'>
                                                    <div style='background-color:{$c_hex};width:18px;height:18px;border-radius:50%;border:1px solid #d1d5db;box-shadow:0 1px 2px rgba(0,0,0,0.1);cursor:pointer;opacity:{$dot_opacity};'></div>
                                                    <span style='position:absolute;top:-8px;right:-8px;background:#6b7280;color:white;font-size:9px;font-weight:bold;width:14px;height:14px;display:flex;align-items:center;justify-content:center;border-radius:50%;border:1px solid white;'>{$c_qty}</span>
                                                </div>";
                                            }
                                        }
                                    } else {
                                        $color_html = "<span style='background:#f3f4f6;color:#9ca3af;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:600;border:1px dashed #d1d5db;'>No Colors</span>";
                                    }
                                    $is_sold = ($row['car_status_status'] === 'Sold');
                                    $badge_bg = $is_sold ? "#fee2e2" : "#dcfce7";
                                    $badge_text = $is_sold ? "#991b1b" : "#166534";
                                    $display_qty = $is_sold ? "SOLD" : "Available";

                                    echo "<tr class='data-row print-used-car'>";
                                    echo "<td class='print-hide' style='padding-left:16px;'><input type='checkbox' class='row-checkbox' style='cursor:pointer; display:none;'></td>";
                                    echo "<td style='text-align:left;color:#6b7280;'>CAR" . str_pad($row['car_id'], 3, '0', STR_PAD_LEFT) . "</td>";
                                    echo "<td style='text-align:left;'>
                                        <div style='display:flex;align-items:center;gap:12px;'>
                                            <div class='print-hide' style='position:relative;width:60px;height:40px;border-radius:6px;overflow:hidden;background:#f3f4f6;flex-shrink:0;border:1px solid #e5e7eb;'>
                                                <img src='{$img_url}' style='width:100%;height:100%;object-fit:cover;'>";
                                    if ($is_sold) {
                                        echo "<div style='position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);color:white;font-size:10px;display:flex;align-items:center;justify-content:center;font-weight:bold;'>SOLD</div>";
                                    }
                                    echo "          </div>
                                            <div style='display:flex;flex-direction:column;'>";
                                    if ($is_incomplete) {
                                        echo "<div style='display:flex;align-items:center;gap:5px;margin-bottom:2px;'>
                                                <i class='fas fa-exclamation-triangle' style='color:#f59e0b;font-size:12px;' title='Information Incomplete'></i>
                                                <strong style='color:#111827;font-size:13px;'>" . htmlspecialchars($row['car_brand']) . "</strong>
                                              </div>";
                                    } else {
                                        echo "<strong style='color:#111827;font-size:13px;margin-bottom:2px;'>" . htmlspecialchars($row['car_brand']) . "</strong>";
                                    }
                                    echo "<span style='color:#6b7280;font-weight:normal;font-size:12px;'>" . htmlspecialchars($row['car_model']) . "</span>";
                                    echo "<div style='display:flex;flex-direction:column;gap:4px;margin-top:6px;'>
                                            <div style='display:flex;flex-wrap:wrap;'><span style='background:#f3f4f6;color:#4b5563;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:600;' title='Variants'><i class='fas fa-car-side' style='margin-right:3px;'></i> {$variants_text}</span></div>
                                            <div style='display:flex;align-items:center;flex-wrap:wrap;'>{$color_html}</div>
                                          </div>
                                        </div>
                                    </div></td>";
                                    echo "<td style='text-align:left;'>{$type}</td>";
                                    echo "<td style='text-align:center;'><span style='background:{$badge_bg};color:{$badge_text};padding:4px 10px;border-radius:12px;font-size:11px;font-weight:700;'>{$display_qty}</span></td>";
                                    echo "<td style='text-align:left;'>{$location}</td>";
                                    echo "<td style='text-align:left;font-weight:500;'>{$plate}</td>";
                                    echo "<td style='text-align:left;'>" . htmlspecialchars($row['car_year']) . "</td>";
                                    echo "<td style='text-align:left;font-weight:600;'>{$price}</td>";
                                    echo "<td style='text-align:left;'><div class='status-cell' style='width:85px;'><span class='dot {$dot_class} print-hide'></span><span class='{$text_class}'>" . ucfirst($status) . "</span></div></td>";
                                    echo "<td class='print-hide' style='text-align:center;'>
                                        <div style='display:flex;justify-content:center;gap:16px;'>
                                            <a href='javascript:void(0);' onclick='quickView({$row['car_id']})' style='color:#9ca3af;' title='Quick View'><i class='fas fa-eye'></i></a>
                                            <a href='edit_car_details.php?id={$row['car_id']}' style='color:#9ca3af;' title='Edit'><i class='fas fa-pen'></i></a>
                                            <a href='javascript:void(0);' onclick='toggleStatus({$row['car_id']}, \"{$status}\", this)' style='color:{$toggle_color};' title='Toggle Status'><i class='fas {$toggle_icon}'></i></a>
                                        </div>
                                    </td></tr>";
                                }
                            } else {
                                echo "<tr><td colspan='11' style='text-align:center;padding:48px 0;color:#6b7280;font-size:14px;'>No used cars found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    <div class="pagination-container print-hide"
                        style="padding:20px;display:flex;justify-content:space-between;align-items:center;border-top:1px solid #e5e7eb;">
                        <div class="page-info" style="color:#6b7280;font-size:13px;">
                            Showing Page <?= $p_used ?> of <?= $pages_used ?>
                        </div>
                        <?php if ($pages_used > 1):
                            $base_q = "?tab=used&search=" . urlencode($search) . "&status=" . urlencode($status_filter); ?>
                            <div class="page-controls" style="display:flex;gap:8px;">
                                <a href="<?= $base_q ?>&p_used=<?= max(1, $p_used - 1) ?>"
                                    class="page-btn <?= ($p_used == 1) ? 'disabled' : '' ?>"><i class="fas fa-angle-left"></i>
                                    Prev</a>
                                <?php for ($i = 1; $i <= $pages_used; $i++): ?>
                                    <a href="<?= $base_q ?>&p_used=<?= $i ?>"
                                        class="page-btn <?= ($p_used == $i) ? 'active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                                <a href="<?= $base_q ?>&p_used=<?= min($pages_used, $p_used + 1) ?>"
                                    class="page-btn <?= ($p_used == $pages_used) ? 'disabled' : '' ?>">Next <i
                                        class="fas fa-angle-right"></i></a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($active_tab === 'history'): ?>
            <div id="section_history" class="table-section">
                <div class="column-header"><i class="fas fa-history" style="color:#16a34a;"></i> Sold Cars (History)</div>
                <div class="table-card" style="padding:0;border:none;">
                    <table class="admin-table" id="table_history" style="width:100%;">
                        <thead>
                            <tr>
                                <th style="width:9%;text-align:left;padding-left:16px;">CAR ID</th>
                                <th style="width:24%;text-align:left;">CAR DETAILS</th>
                                <th style="width:10%;text-align:left;">TYPE</th>
                                <th style="width:15%;text-align:left;">LOCATION</th>
                                <th style="width:11%;text-align:left;">PLATE NO.</th>
                                <th style="width:8%;text-align:left;">YEAR</th>
                                <th style="width:11%;text-align:left;">PRICE</th>
                                <th style="width:12%;text-align:left;">SOLD ON</th>
                                <th style="width:8%;text-align:center;" class="print-hide">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result_history && mysqli_num_rows($result_history) > 0) {
                                while ($row = mysqli_fetch_assoc($result_history)) {
                                    $price = !empty($row['car_status_price']) ? "RM " . number_format($row['car_status_price']) : "TBA";
                                    $plate = !empty($row['car_plate']) ? htmlspecialchars($row['car_plate']) : '-';
                                    $img_url = getCarImgUrl($conn, $row['car_id']);
                                    $type = !empty($row['car_type_name']) ? htmlspecialchars($row['car_type_name']) : '-';
                                    $location = !empty($row['location_city']) ? htmlspecialchars($row['location_city'] . ', ' . $row['location_state']) : '-';
                                    $variants_text = !empty($row['all_variants']) ? htmlspecialchars($row['all_variants']) : 'No Variants';
                                    $sold_on = !empty($row['car_status_updated_at']) ? date('d M Y', strtotime($row['car_status_updated_at'])) : '-';

                                    $color_html = "";
                                    if (!empty($row['color_data'])) {
                                        foreach (explode('||', $row['color_data']) as $c_item) {
                                            $parts = explode('::', $c_item);
                                            if (count($parts) === 3) {
                                                $c_name = htmlspecialchars($parts[0]);
                                                $c_hex = htmlspecialchars($parts[1]);
                                                $color_html .= "<div style='position:relative;display:inline-block;margin-right:12px;margin-top:4px;' title='{$c_name}'>
                                                    <div style='background-color:{$c_hex};width:18px;height:18px;border-radius:50%;border:1px solid #d1d5db;opacity:0.5;'></div>
                                                </div>";
                                            }
                                        }
                                    }

                                    echo "<tr class='data-row'>";
                                    echo "<td style='text-align:left;padding-left:16px;color:#6b7280;'>CAR" . str_pad($row['car_id'], 3, '0', STR_PAD_LEFT) . "</td>";
                                    echo "<td style='text-align:left;'>
                                        <div style='display:flex;align-items:center;gap:12px;'>
                                            <div class='print-hide' style='position:relative;width:60px;height:40px;border-radius:6px;overflow:hidden;background:#f3f4f6;flex-shrink:0;border:1px solid #e5e7eb;'>
                                                <img src='{$img_url}' style='width:100%;height:100%;object-fit:cover;'>
                                                <div style='position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);color:white;font-size:10px;display:flex;align-items:center;justify-content:center;font-weight:bold;'>SOLD</div>
                                            </div>
                                            <div style='display:flex;flex-direction:column;'>
                                                <strong style='color:#111827;font-size:13px;margin-bottom:2px;'>" . htmlspecialchars($row['car_brand']) . "</strong>
                                                <span style='color:#6b7280;font-weight:normal;font-size:12px;'>" . htmlspecialchars($row['car_model']) . "</span>
                                                <div style='display:flex;flex-direction:column;gap:4px;margin-top:6px;'>
                                                    <div style='display:flex;flex-wrap:wrap;'><span style='background:#f3f4f6;color:#4b5563;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:600;'><i class='fas fa-car-side' style='margin-right:3px;'></i> {$variants_text}</span></div>
                                                    <div style='display:flex;align-items:center;flex-wrap:wrap;'>{$color_html}</div>
                                                </div>
                                            </div>
                                        </div></td>";
                                    echo "<td style='text-align:left;'>{$type}</td>";
                                    echo "<td style='text-align:left;'>{$location}</td>";
                                    echo "<td style='text-align:left;font-weight:500;'>{$plate}</td>";
                                    echo "<td style='text-align:left;'>" . htmlspecialchars($row['car_year']) . "</td>";
                                    echo "<td style='text-align:left;font-weight:600;'>{$price}</td>";
                                    echo "<td style='text-align:left;color:#6b7280;'>{$sold_on}</td>";
                                    echo "<td class='print-hide' style='text-align:center;'>
                                        <a href='javascript:void(0);' onclick='quickView({$row['car_id']})' style='color:#9ca3af;' title='Quick View'><i class='fas fa-eye'></i></a>
                                    </td></tr>";
                                }
                            } else {
                                echo "<tr><td colspan='9' style='text-align:center;padding:48px 0;color:#6b7280;font-size:14px;'>No sold cars yet.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    <div class="pagination-container print-hide"
                        style="padding:20px;display:flex;justify-content:space-between;align-items:center;border-top:1px solid #e5e7eb;">
                        <div class="page-info" style="color:#6b7280;font-size:13px;">
                            Showing Page <?= $p_hist ?> of <?= $pages_hist ?>
                        </div>
                        <?php if ($pages_hist > 1):
                            $base_q = "?tab=history&search=" . urlencode($search); ?>
                            <div class="page-controls" style="display:flex;gap:8px;">
                                <a href="<?= $base_q ?>&p_hist=<?= max(1, $p_hist - 1) ?>"
                                    class="page-btn <?= ($p_hist == 1) ? 'disabled' : '' ?>"><i class="fas fa-angle-left"></i>
                                    Prev</a>
                                <?php for ($i = 1; $i <= $pages_hist; $i++): ?>
                                    <a href="<?= $base_q ?>&p_hist=<?= $i ?>"
                                        class="page-btn <?= ($p_hist == $i) ? 'active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                                <a href="<?= $base_q ?>&p_hist=<?= min($pages_hist, $p_hist + 1) ?>"
                                    class="page-btn <?= ($p_hist == $pages_hist) ? 'disabled' : '' ?>">Next <i
                                        class="fas fa-angle-right"></i></a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <div id="carViewModal" class="modal">
        <div class="modal-content" style="max-width:1100px;padding:24px 28px;background:#f8fafc;">
            <div
                style="display:flex;align-items:center;gap:16px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid #e5e7eb;">
                <h2 id="carModalTitle" style="font-size:22px;font-weight:700;color:#111827;margin:0;flex:1;">View Car
                </h2>
                <span id="cv_origin_badge" class="origin-select new" style="cursor:default;">NEW CAR</span>
                <button type="button" onclick="closeCarModal()"
                    style="background:transparent;border:none;font-size:20px;color:#6b7280;cursor:pointer;padding:4px 8px;"><i
                        class="fas fa-times"></i></button>
            </div>
            <div class="qv-inner-tabs">
                <button type="button" class="qv-tab-btn active" data-qv-target="qv-tab-basic"><i
                        class="fas fa-info-circle"></i> Basic &amp; Pricing</button>
                <button type="button" class="qv-tab-btn" data-qv-target="qv-tab-specs"><i class="fas fa-cogs"></i>
                    Specifications</button>
                <button type="button" class="qv-tab-btn" data-qv-target="qv-tab-chassis"><i class="fas fa-car-side"></i>
                    Chassis</button>
                <button type="button" class="qv-tab-btn" data-qv-target="qv-tab-media"><i class="fas fa-images"></i>
                    Media</button>
                <button type="button" id="qv-tab-history-btn" class="qv-tab-btn warning"
                    data-qv-target="qv-tab-history"><i class="fas fa-history"></i> Used Car Details</button>
            </div>
            <div id="qv-tab-basic" class="qv-tab-content active">
                <div class="qv-section">
                    <h3 class="qv-section-header"><i class="fas fa-car"></i> Basic Information</h3>
                    <div class="qv-grid-3" style="margin-bottom:16px;">
                        <div class="qv-fg"><label>Brand</label><input type="text" id="cv_brand" class="form-control"
                                readonly></div>
                        <div class="qv-fg"><label>Model</label><input type="text" id="cv_model" class="form-control"
                                readonly></div>
                        <div class="qv-fg"><label>Variant</label><input type="text" id="cv_variant" class="form-control"
                                readonly></div>
                    </div>
                    <div class="qv-grid-4" style="margin-bottom:16px;">
                        <div class="qv-fg"><label>Body Type</label><input type="text" id="cv_body_type"
                                class="form-control" readonly></div>
                        <div class="qv-fg"><label>Year</label><input type="text" id="cv_year" class="form-control"
                                readonly></div>
                        <div class="qv-fg"><label>Seats</label><input type="text" id="cv_seats" class="form-control"
                                readonly></div>
                        <div class="qv-fg"><label>Mileage <span class="hint">(km)</span></label><input type="text"
                                id="cv_mileage_basic" class="form-control" readonly></div>
                    </div>
                    <div class="qv-grid-2" style="margin-bottom:16px;">
                        <div class="qv-fg"><label>Fuel Type</label><input type="text" id="cv_fuel_type"
                                class="form-control" readonly></div>
                        <div class="qv-fg"><label>Transmission</label><input type="text" id="cv_transmission"
                                class="form-control" readonly></div>
                    </div>
                    <div class="qv-fg"><label>Description</label><textarea id="cv_description" class="form-control"
                            readonly rows="4"></textarea></div>
                </div>

                <div class="qv-section">
                    <h3 class="qv-section-header"><i class="fas fa-tag"></i> Pricing &amp; Status</h3>
                    <div class="qv-grid-3">
                        <div class="qv-fg"><label>Price (RM)</label><input type="text" id="cv_price"
                                class="form-control" readonly style="font-weight:700;color:#059669;"></div>
                        <div class="qv-fg"><label>Status</label><input type="text" id="cv_status" class="form-control"
                                readonly></div>
                        <div class="qv-fg"><label>Total Stock</label><input type="text" id="cv_stock"
                                class="form-control" readonly></div>
                    </div>
                </div>

                <div class="qv-section">
                    <h3 class="qv-section-header"><i class="fas fa-palette"></i> Colors &amp; Stock</h3>
                    <div id="cv_colors_container"
                        style="display:flex;flex-wrap:wrap;align-items:center;min-height:50px;"></div>
                </div>
            </div>

            <div id="qv-tab-specs" class="qv-tab-content">
                <div class="qv-section">
                    <h3 class="qv-section-header"><i class="fas fa-bolt"></i> Engine Specifications</h3>
                    <div class="qv-grid-3">
                        <div class="qv-fg"><label>Engine Type</label><input type="text" id="cv_engine_type"
                                class="form-control" readonly></div>
                        <div class="qv-fg"><label>Engine Displacement <span class="hint">(cc)</span></label><input
                                type="text" id="cv_engine_cc" class="form-control" readonly></div>
                        <div class="qv-fg"><label>Compression Ratio</label><input type="text" id="cv_compression"
                                class="form-control" readonly></div>
                        <div class="qv-fg"><label>Peak Power <span class="hint">(kW)</span></label><input type="text"
                                id="cv_peak_power" class="form-control" readonly></div>
                        <div class="qv-fg"><label>Peak Torque <span class="hint">(Nm)</span></label><input type="text"
                                id="cv_peak_torque" class="form-control" readonly></div>
                    </div>
                </div>

                <div class="qv-section" id="cv_ev_section" style="display:none;">
                    <h3 class="qv-section-header"><i class="fas fa-plug"></i> EV Specifications</h3>
                    <div class="qv-fg" style="max-width:50%;"><label>Battery Range <span
                                class="hint">(km)</span></label><input type="text" id="cv_battery_range"
                            class="form-control" readonly></div>
                </div>

                <div class="qv-section">
                    <h3 class="qv-section-header"><i class="fas fa-ruler-combined"></i> Dimensions</h3>
                    <div class="qv-grid-3">
                        <div class="qv-fg"><label>Length <span class="hint">(mm)</span></label><input type="text"
                                id="cv_length" class="form-control" readonly></div>
                        <div class="qv-fg"><label>Width <span class="hint">(mm)</span></label><input type="text"
                                id="cv_width" class="form-control" readonly></div>
                        <div class="qv-fg"><label>Height <span class="hint">(mm)</span></label><input type="text"
                                id="cv_height" class="form-control" readonly></div>
                        <div class="qv-fg"><label>Wheelbase <span class="hint">(mm)</span></label><input type="text"
                                id="cv_wheelbase" class="form-control" readonly></div>
                        <div class="qv-fg"><label>Fuel Tank <span class="hint">(L)</span></label><input type="text"
                                id="cv_fuel_tank" class="form-control" readonly></div>
                        <div class="qv-fg"><label>Kerb Weight <span class="hint">(kg)</span></label><input type="text"
                                id="cv_weight" class="form-control" readonly></div>
                    </div>
                </div>

                <div class="qv-section">
                    <h3 class="qv-section-header"><i class="fas fa-couch"></i> Features &amp; Equipment</h3>
                    <div class="qv-grid-3" style="margin-bottom:16px;">
                        <div class="qv-fg"><label>Interior Color</label><input type="text" id="cv_int_color"
                                class="form-control" readonly></div>
                        <div class="qv-fg"><label>Seat Material</label><input type="text" id="cv_seat_mat"
                                class="form-control" readonly></div>
                        <div class="qv-fg"><label>Wheel Size</label><input type="text" id="cv_wheel_size"
                                class="form-control" readonly></div>
                        <div class="qv-fg"><label>Headlights</label><input type="text" id="cv_headlights"
                                class="form-control" readonly></div>
                        <div class="qv-fg"><label>Infotainment Screen</label><input type="text" id="cv_screen"
                                class="form-control" readonly></div>
                        <div class="qv-fg"><label>Airbags Count</label><input type="text" id="cv_airbags"
                                class="form-control" readonly></div>
                    </div>
                    <div class="qv-fg"><label>Comfort &amp; Convenience Features</label><textarea id="cv_feat_conf"
                            class="form-control" readonly rows="3"></textarea></div>
                </div>
            </div>

            <div id="qv-tab-chassis" class="qv-tab-content">
                <div class="qv-section">
                    <h3 class="qv-section-header"><i class="fas fa-circle-notch"></i> Brake Specifications</h3>
                    <div class="qv-grid-2">
                        <div class="qv-fg"><label>Front Brakes</label><input type="text" id="cv_front_brakes"
                                class="form-control" readonly></div>
                        <div class="qv-fg"><label>Rear Brakes</label><input type="text" id="cv_rear_brakes"
                                class="form-control" readonly></div>
                    </div>
                </div>
                <div class="qv-section">
                    <h3 class="qv-section-header"><i class="fas fa-wave-square"></i> Suspension Specifications</h3>
                    <div class="qv-grid-2">
                        <div class="qv-fg"><label>Front Suspension</label><input type="text" id="cv_front_susp"
                                class="form-control" readonly></div>
                        <div class="qv-fg"><label>Rear Suspension</label><input type="text" id="cv_rear_susp"
                                class="form-control" readonly></div>
                    </div>
                </div>
                <div class="qv-section">
                    <h3 class="qv-section-header"><i class="fas fa-arrows-alt-h"></i> Steering Specifications</h3>
                    <div class="qv-fg" style="max-width:50%;"><label>Steering Type</label><input type="text"
                            id="cv_steering" class="form-control" readonly></div>
                </div>
                <div class="qv-section">
                    <h3 class="qv-section-header"><i class="fas fa-circle"></i> Tyre Specifications</h3>
                    <div class="qv-grid-2" style="margin-bottom:16px;">
                        <div class="qv-fg"><label>Front Tyres</label><input type="text" id="cv_front_tyres"
                                class="form-control" readonly></div>
                        <div class="qv-fg"><label>Rear Tyres</label><input type="text" id="cv_rear_tyres"
                                class="form-control" readonly></div>
                    </div>
                    <div class="qv-grid-2">
                        <div class="qv-fg"><label>Front Rim <span class="hint">(inches)</span></label><input type="text"
                                id="cv_front_rim" class="form-control" readonly></div>
                        <div class="qv-fg"><label>Rear Rim <span class="hint">(inches)</span></label><input type="text"
                                id="cv_rear_rim" class="form-control" readonly></div>
                    </div>
                </div>
            </div>

            <div id="qv-tab-media" class="qv-tab-content">
                <div class="qv-section">
                    <h3 class="qv-section-header"><i class="fas fa-camera"></i> Car Gallery</h3>
                    <div id="cv_image_gallery"
                        style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;"></div>
                </div>
            </div>

            <div id="qv-tab-history" class="qv-tab-content">
                <div class="qv-section">
                    <h3 class="qv-section-header warning"><i class="fas fa-map-marker-alt"></i> Location</h3>
                    <div class="qv-grid-2">
                        <div class="qv-fg"><label>State</label><input type="text" id="cv_state" class="form-control"
                                readonly></div>
                        <div class="qv-fg"><label>City</label><input type="text" id="cv_city" class="form-control"
                                readonly></div>
                    </div>
                </div>
                <div class="qv-section" id="cv_history_section">
                    <h3 class="qv-section-header warning"><i class="fas fa-file-contract"></i> Used Car History</h3>
                    <div class="qv-grid-3" style="margin-bottom:16px;">
                        <div class="qv-fg"><label>Plate Number</label><input type="text" id="cv_plate"
                                class="form-control" readonly></div>
                        <div class="qv-fg"><label>Previous Owners</label><input type="text" id="cv_owners"
                                class="form-control" readonly></div>
                        <div class="qv-fg"><label>Remaining Warranty</label><input type="text" id="cv_rem_warranty"
                                class="form-control" readonly></div>
                    </div>
                    <div class="qv-grid-2" style="margin-bottom:16px;">
                        <div class="qv-fg"><label>Accident History</label><input type="text" id="cv_accident"
                                class="form-control" readonly></div>
                        <div class="qv-fg"><label>Flood / Fire</label><input type="text" id="cv_flood"
                                class="form-control" readonly></div>
                    </div>
                    <div class="qv-fg" style="margin-bottom:16px;"><label>Service History</label><input type="text"
                            id="cv_service_hist" class="form-control" readonly></div>
                    <div class="qv-grid-2" style="margin-bottom:16px;">
                        <div class="qv-fg"><label>Last Service Date</label><input type="text" id="cv_last_service"
                                class="form-control" readonly></div>
                        <div class="qv-fg"><label>Next Service <span class="hint">(km)</span></label><input type="text"
                                id="cv_next_service" class="form-control" readonly></div>
                    </div>
                    <div class="qv-grid-2" style="margin-bottom:16px;">
                        <div class="qv-fg"><label>Roadtax Expiry</label><input type="text" id="cv_roadtax"
                                class="form-control" readonly></div>
                        <div class="qv-fg"><label>Puspakom Date</label><input type="text" id="cv_puspakom"
                                class="form-control" readonly></div>
                    </div>
                    <div class="qv-fg" style="margin-bottom:16px;"><label>Known Defects</label><textarea id="cv_defects"
                            class="form-control" readonly rows="3"></textarea></div>
                    <div class="qv-fg" id="cv_inspection_pdf_group" style="display:none;">
                        <label>Inspection Report</label>
                        <div
                            style="display:flex;align-items:center;gap:12px;padding:12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;">
                            <i class="fas fa-file-pdf" style="color:#ef4444;font-size:20px;"></i>
                            <a id="cv_inspection_pdf_link" href="#" target="_blank"
                                style="color:#15803d;font-weight:600;text-decoration:none;flex:1;">View Inspection
                                PDF</a>
                        </div>
                    </div>
                </div>
            </div>
            <div
                style="display:flex;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #e5e7eb;">
                <button type="button" onclick="closeCarModal()" class="btn-add-blue" style="border:none;">Close</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>window.GLOBAL_LOW_STOCK = <?= $low_stock_limit ?>;</script>
    <script src="../../JAVA SCRIPT/manage cars.js?v=<?= time() ?>"></script>
</body>

</html>