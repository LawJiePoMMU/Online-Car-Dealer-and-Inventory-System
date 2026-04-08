<?php
session_start();
include '../database.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

mysqli_query($conn, "INSERT IGNORE INTO car_types (car_type_id, car_type_name) VALUES 
(1, 'Sedan'), (2, 'SUV'), (3, 'Hatchback'), (4, 'EV'), (5, 'Exora (MPV)')");

if (isset($_GET['ajax']) && isset($_GET['toggle_id']) && isset($_GET['current_status'])) {
    $id = (int) $_GET['toggle_id'];
    $new_status = ($_GET['current_status'] == 'Active') ? 'Inactive' : 'Active';
    mysqli_query($conn, "UPDATE car_status SET car_status_status = '$new_status' WHERE car_id = $id");
    exit();
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

if (isset($_POST['save_car'])) {
    try {
        $car_id = isset($_POST['car_id']) ? (int) $_POST['car_id'] : 0;
        $brand = mysqli_real_escape_string($conn, $_POST['car_brand']);
        $model = mysqli_real_escape_string($conn, $_POST['car_model']);
        $year = (int) $_POST['car_year'];
        $origin = mysqli_real_escape_string($conn, $_POST['car_origin']);
        $price = (float) $_POST['car_price'];
        $type_id = (int) $_POST['car_type_id'];

        $car_plate = ($origin === 'Used Car' && isset($_POST['car_plate'])) ? mysqli_real_escape_string($conn, $_POST['car_plate']) : '';
        $status = 'Active';

        $location_id = 1;
        if ($origin === 'Used Car' && !empty($_POST['location_state']) && !empty($_POST['location_city'])) {
            $state = mysqli_real_escape_string($conn, $_POST['location_state']);
            $city = mysqli_real_escape_string($conn, $_POST['location_city']);

            $loc_query = mysqli_query($conn, "SELECT location_id FROM locations WHERE location_state='$state' AND location_city='$city'");
            if (mysqli_num_rows($loc_query) > 0) {
                $location_id = mysqli_fetch_assoc($loc_query)['location_id'];
            } else {
                mysqli_query($conn, "INSERT INTO locations (location_state, location_city) VALUES ('$state', '$city')");
                $location_id = mysqli_insert_id($conn);
            }
        }

        if ($origin === 'Used Car' && !empty($car_plate)) {
            $check_query = "SELECT * FROM cars WHERE car_plate='$car_plate' AND car_id != $car_id";
            if (mysqli_num_rows(mysqli_query($conn, $check_query)) > 0) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?tab=$active_tab&error=duplicate_plate");
                exit();
            }
        }

        mysqli_begin_transaction($conn);

        if ($car_id > 0) {
            $update_car = "UPDATE cars SET car_type_id=$type_id, location_id=$location_id, car_brand='$brand', car_model='$model', car_year=$year, car_origin='$origin', car_plate='$car_plate' WHERE car_id=$car_id";
            mysqli_query($conn, $update_car);

            $update_status = "UPDATE car_status SET car_status_price=$price WHERE car_id=$car_id";
            mysqli_query($conn, $update_status);
            $target_car_id = $car_id;
        } else {
            $insert_car = "INSERT INTO cars (car_type_id, location_id, car_brand, car_model, car_year, car_origin, car_plate, car_created_at) 
                           VALUES ($type_id, $location_id, '$brand', '$model', $year, '$origin', '$car_plate', NOW())";
            mysqli_query($conn, $insert_car);
            $target_car_id = mysqli_insert_id($conn);

            $insert_status = "INSERT INTO car_status (car_id, car_status_price, car_status_stock_quantity, car_status_status, car_status_updated_at) 
                              VALUES ($target_car_id, $price, 1, '$status', NOW())";
            mysqli_query($conn, $insert_status);
        }

        if (isset($_FILES['car_images']) && !empty($_FILES['car_images']['name'][0])) {
            $target_dir = "../../uploads/cars/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            foreach ($_FILES['car_images']['name'] as $key => $val) {
                if ($_FILES['car_images']['error'][$key] == 0) {
                    $file_name = time() . '_' . $key . '_' . basename($_FILES["car_images"]["name"][$key]);
                    $target_file = $target_dir . $file_name;
                    if (move_uploaded_file($_FILES["car_images"]["tmp_name"][$key], $target_file)) {
                        $insert_image = "INSERT INTO car_image (car_id, car_image_url) VALUES ($target_car_id, '$target_file')";
                        mysqli_query($conn, $insert_image);
                    }
                }
            }
        }

        mysqli_commit($conn);
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=$active_tab&success=1");
        exit();

    } catch (Exception $e) {
        if (isset($conn))
            mysqli_rollback($conn);
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=$active_tab&error=system");
        exit();
    }
}

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$search_condition = "";
if (!empty($search)) {
    $search_condition = " AND (c.car_brand LIKE '%$search%' OR c.car_model LIKE '%$search%' OR c.car_plate LIKE '%$search%') ";
}
if ($status_filter !== 'all') {
    $search_condition .= " AND stat.car_status_status = '$status_filter' ";
}

$count_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM cars c LEFT JOIN car_status stat ON c.car_id = stat.car_id WHERE 1=1 $search_condition"))['c'];
$count_new = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM cars c LEFT JOIN car_status stat ON c.car_id = stat.car_id WHERE c.car_origin = 'New Car' $search_condition"))['c'];
$count_used = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM cars c LEFT JOIN car_status stat ON c.car_id = stat.car_id WHERE c.car_origin = 'Used Car' $search_condition"))['c'];

$limit_new = ($active_tab === 'all') ? 5 : 10;
$limit_used = ($active_tab === 'all') ? 5 : 10;

$p_new = isset($_GET['p_new']) ? (int) $_GET['p_new'] : 1;
if ($p_new < 1)
    $p_new = 1;
$offset_new = ($p_new - 1) * $limit_new;
$pages_new = ceil($count_new / $limit_new);

$p_used = isset($_GET['p_used']) ? (int) $_GET['p_used'] : 1;
if ($p_used < 1)
    $p_used = 1;
$offset_used = ($p_used - 1) * $limit_used;
$pages_used = ceil($count_used / $limit_used);

try {
    $query_new = "SELECT c.*, stat.car_status_price, stat.car_status_status, loc.location_state, loc.location_city, ct.car_type_name 
                  FROM cars c 
                  LEFT JOIN car_status stat ON c.car_id = stat.car_id 
                  LEFT JOIN locations loc ON c.location_id = loc.location_id
                  LEFT JOIN car_types ct ON c.car_type_id = ct.car_type_id
                  WHERE c.car_origin = 'New Car' $search_condition ORDER BY c.car_id DESC LIMIT $limit_new OFFSET $offset_new";
    $result_new = mysqli_query($conn, $query_new);

    $query_used = "SELECT c.*, stat.car_status_price, stat.car_status_status, loc.location_state, loc.location_city, ct.car_type_name 
                   FROM cars c 
                   LEFT JOIN car_status stat ON c.car_id = stat.car_id 
                   LEFT JOIN locations loc ON c.location_id = loc.location_id
                   LEFT JOIN car_types ct ON c.car_type_id = ct.car_type_id
                   WHERE c.car_origin = 'Used Car' $search_condition ORDER BY c.car_id DESC LIMIT $limit_used OFFSET $offset_used";
    $result_used = mysqli_query($conn, $query_used);
} catch (Exception $e) {
}

$car_types_query = mysqli_query($conn, "SELECT * FROM car_types ORDER BY car_type_id ASC");
$car_types = [];
if ($car_types_query) {
    while ($row = mysqli_fetch_assoc($car_types_query)) {
        $car_types[] = $row;
    }
}

$existing_plates_query = mysqli_query($conn, "SELECT car_plate, car_id FROM cars WHERE car_origin = 'Used Car' AND car_plate != ''");
$existing_plates = [];
if ($existing_plates_query) {
    while ($r = mysqli_fetch_assoc($existing_plates_query)) {
        $existing_plates[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Cars</title>
    <link rel="stylesheet" href="../../CSS/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        @media print {
            body {
                background: white !important;
                margin: 0;
                padding: 0;
            }

            .sidebar,
            .topbar,
            form,
            .btn-add-blue,
            .btn-export,
            .print-hide,
            .pagination-container,
            .modal,
            .page-tabs {
                display: none !important;
            }

            .main-content {
                margin: 0 !important;
                padding: 20px !important;
                width: 100% !important;
            }

            .table-card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                width: 100% !important;
                margin-bottom: 30px !important;
            }

            table {
                width: 100% !important;
                border-collapse: collapse !important;
                border: 2px solid #000 !important;
            }

            th,
            td {
                border: 1px solid #000 !important;
                padding: 10px 8px !important;
                color: #000 !important;
                text-align: left !important;
                font-size: 13px !important;
            }

            th {
                background-color: #f3f4f6 !important;
                -webkit-print-color-adjust: exact;
                font-weight: bold !important;
                text-transform: uppercase;
            }

            .badge,
            .dot,
            .status-cell span {
                color: #000 !important;
                background: none !important;
            }

            tr.no-print-row {
                display: none !important;
            }
        }

        .page-tabs {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 24px;
            gap: 32px;
            padding-left: 8px;
        }

        .tab-item {
            padding-bottom: 12px;
            color: var(--text-muted);
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            text-decoration: none;
            transition: all 0.2s;
        }

        .tab-item:hover {
            color: var(--text-dark);
        }

        .tab-item.active {
            color: var(--text-dark);
            border-bottom-color: #f59e0b;
            font-weight: 700;
        }

        .column-header {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 4px;
        }

        .page-btn {
            padding: 6px 12px;
            border: 1px solid #d1d5db;
            background: white;
            color: #374151;
            font-size: 13px;
            font-weight: 500;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
        }

        .page-btn.active {
            background: #1e3a8a;
            color: white;
            border-color: #1e3a8a;
        }

        .page-btn.disabled {
            color: #9ca3af;
            pointer-events: none;
            opacity: 0.5;
        }

        .form-origin-btn-group {
            display: flex;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            overflow: hidden;
            width: 180px;
        }

        .origin-btn {
            flex: 1;
            background-color: #f9fafb;
            border: none;
            padding: 8px 0;
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.2s ease;
            outline: none;
        }

        .origin-btn:first-child {
            border-right: 1px solid #d1d5db;
        }

        .origin-btn.active {
            background-color: var(--primary-color);
            color: #ffffff;
        }

        .table-section {
            margin-bottom: 48px;
        }

        .custom-select {
            position: relative;
        }

        .custom-select select {
            width: 100%;
        }

        .custom-select select:focus {
            position: absolute;
            z-index: 1000;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar" style="margin-bottom: 24px;">
            <div class="page-title">
                <h1 style="font-size: 24px; font-weight: 700; color: #111827;">Manage Cars</h1>
            </div>
        </header>

        <div class="page-tabs">
            <a href="?tab=all" class="tab-item <?= $active_tab == 'all' ? 'active' : '' ?>">All Cars
                (<?= $count_all ?>)</a>
            <a href="?tab=new" class="tab-item <?= $active_tab == 'new' ? 'active' : '' ?>">New Cars
                (<?= $count_new ?>)</a>
            <a href="?tab=used" class="tab-item <?= $active_tab == 'used' ? 'active' : '' ?>">Used Cars
                (<?= $count_used ?>)</a>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
            <form method="GET" style="display: flex; gap: 16px;">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
                <div style="position: relative;">
                    <i class="fas fa-search"
                        style="position: absolute; left: 14px; top: 11px; color: #9ca3af; font-size: 14px;"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search Brand, Model or Plate..."
                        style="padding-left: 38px; width: 280px; font-size: 13px;"
                        value="<?= htmlspecialchars($search) ?>">
                </div>
                <select name="status" class="form-control" style="width: 150px; font-size: 13px;"
                    onchange="this.form.submit()">
                    <option value="all">All Status</option>
                    <option value="Active" <?= $status_filter == 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= $status_filter == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </form>
            <div style="display: flex; gap: 12px;">
                <button type="button" class="btn-export" onclick="printSelected()"><i class="fas fa-print"></i> Print
                    Selected</button>
                <button onclick="openAddCarModal()" class="btn-add-blue"><i class="fas fa-plus"></i> Add Car</button>
            </div>
        </div>

        <?php if ($active_tab === 'all' || $active_tab === 'new'): ?>
            <div id="section_new" class="table-section">
                <div class="column-header">
                    <i class="fas fa-car" style="color: var(--primary-color);"></i> New Cars
                </div>
                <div class="table-card"
                    style="padding: 0; border: none; background: white; border-radius: 12px; border: 1px solid var(--border-color);">
                    <table class="admin-table" id="table_new" style="width: 100%;">
                        <thead>
                            <tr>
                                <th class="print-hide" style="width: 4%; padding-left: 16px;"><input type="checkbox"
                                        class="selectAllColumn" style="cursor: pointer;"></th>
                                <th style="width: 10%; text-align: left;">CAR ID</th>
                                <th style="width: 30%; text-align: left;">CAR DETAILS</th>
                                <th style="width: 15%; text-align: left;">TYPE</th>
                                <th style="width: 10%; text-align: left;">YEAR</th>
                                <th style="width: 10%; text-align: left;">PRICE</th>
                                <th style="width: 10%; text-align: left;">STATUS</th>
                                <th style="width: 11%; text-align: center;" class="print-hide">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result_new && mysqli_num_rows($result_new) > 0) {
                                while ($row = mysqli_fetch_assoc($result_new)) {
                                    $price = !empty($row['car_status_price']) ? "RM " . number_format($row['car_status_price']) : "TBA";
                                    $status = (!empty($row['car_status_status']) && in_array($row['car_status_status'], ['Active', 'Inactive'])) ? $row['car_status_status'] : 'Active';
                                    $dot_class = ($status == 'Active') ? 'dot-active' : 'dot-inactive';
                                    $text_class = ($status == 'Active') ? 'text-active' : 'text-inactive';

                                    $c_id = $row['car_id'];
                                    $img_query = mysqli_query($conn, "SELECT car_image_url FROM car_image WHERE car_id = $c_id LIMIT 1");
                                    $img_row = mysqli_fetch_assoc($img_query);
                                    $img_url = !empty($img_row['car_image_url']) ? htmlspecialchars($img_row['car_image_url']) : '../../images/default-car.png';
                                    $type = !empty($row['car_type_name']) ? htmlspecialchars($row['car_type_name']) : '-';

                                    echo "<tr class='data-row print-new-car'>";
                                    echo "<td class='print-hide' style='padding-left: 16px;'><input type='checkbox' class='row-checkbox' style='cursor: pointer;'></td>";
                                    echo "<td style='text-align: left; color: #6b7280;'>CAR" . str_pad($row['car_id'], 3, '0', STR_PAD_LEFT) . "</td>";
                                    echo "<td style='text-align: left;'>
                                        <div style='display: flex; align-items: center; gap: 12px;'>
                                            <div class='print-hide' style='width: 60px; height: 40px; border-radius: 6px; overflow: hidden; background: #f3f4f6; flex-shrink: 0; border: 1px solid #e5e7eb;'>
                                                <img src='{$img_url}' style='width: 100%; height: 100%; object-fit: cover;'>
                                            </div>
                                            <div style='display: flex; flex-direction: column;'>
                                                <strong style='color: #111827; font-size: 13px;'>" . htmlspecialchars($row['car_brand']) . " <span style='color: #6b7280; font-weight: normal;'>" . htmlspecialchars($row['car_model']) . "</span></strong>
                                            </div>
                                        </div>
                                      </td>";
                                    echo "<td style='text-align: left;'>" . $type . "</td>";
                                    echo "<td style='text-align: left;'>" . htmlspecialchars($row['car_year']) . "</td>";
                                    echo "<td style='text-align: left; font-weight: 600;'>" . $price . "</td>";
                                    echo "<td style='text-align: left;'><div class='status-cell' style='width: 85px;'><span class='dot {$dot_class} print-hide'></span><span class='{$text_class}'>" . ucfirst($status) . "</span></div></td>";

                                    $toggle_icon = ($status == 'Active') ? 'fa-lock' : 'fa-unlock';
                                    $toggle_color = ($status == 'Active') ? '#ef4444' : '#10b981';
                                    $json_data = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');

                                    echo "<td class='print-hide' style='text-align: center;'>
                                        <div style='display: flex; justify-content: center; gap: 12px;'>
                                            <a href='javascript:void(0);' onclick='editCarModal({$json_data})' style='color: #9ca3af;'><i class='fas fa-pen'></i></a>
                                            <a href='javascript:void(0);' onclick='toggleStatus({$row['car_id']}, \"{$status}\", this)' style='color: {$toggle_color};'><i class='fas {$toggle_icon}'></i></a>
                                        </div>
                                      </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='8' style='text-align: center; padding: 40px; color: var(--text-muted);'>No new cars found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    <?php if ($pages_new > 1): ?>
                        <div class="pagination-container print-hide"
                            style="padding: 16px; border-top: 1px solid #e5e7eb; display: flex; justify-content: center; gap: 8px;">
                            <?php $base_q = "?search=" . urlencode($search) . "&status=" . urlencode($status_filter) . "&tab=" . $active_tab; ?>
                            <a href="<?= $base_q ?>&p_new=<?= max(1, $p_new - 1) ?>&p_used=<?= $p_used ?>"
                                class="page-btn <?= ($p_new == 1) ? 'disabled' : '' ?>">Prev</a>
                            <?php for ($i = 1; $i <= $pages_new; $i++): ?>
                                <a href="<?= $base_q ?>&p_new=<?= $i ?>&p_used=<?= $p_used ?>"
                                    class="page-btn <?= ($p_new == $i) ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <a href="<?= $base_q ?>&p_new=<?= min($pages_new, $p_new + 1) ?>&p_used=<?= $p_used ?>"
                                class="page-btn <?= ($p_new == $pages_new) ? 'disabled' : '' ?>">Next</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($active_tab === 'all' || $active_tab === 'used'): ?>
            <div id="section_used" class="table-section">
                <div class="column-header">
                    <i class="fas fa-car-side" style="color: var(--text-muted);"></i> Used Cars
                </div>
                <div class="table-card"
                    style="padding: 0; border: none; background: white; border-radius: 12px; border: 1px solid var(--border-color);">
                    <table class="admin-table" id="table_used" style="width: 100%;">
                        <thead>
                            <tr>
                                <th class="print-hide" style="width: 4%; padding-left: 16px;"><input type="checkbox"
                                        class="selectAllColumn" style="cursor: pointer;"></th>
                                <th style="width: 9%; text-align: left;">CAR ID</th>
                                <th style="width: 20%; text-align: left;">CAR DETAILS</th>
                                <th style="width: 10%; text-align: left;">TYPE</th>
                                <th style="width: 15%; text-align: left;">LOCATION</th>
                                <th style="width: 10%; text-align: left;">PLATE NO.</th>
                                <th style="width: 8%; text-align: left;">YEAR</th>
                                <th style="width: 8%; text-align: left;">PRICE</th>
                                <th style="width: 8%; text-align: left;">STATUS</th>
                                <th style="width: 8%; text-align: center;" class="print-hide">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result_used && mysqli_num_rows($result_used) > 0) {
                                while ($row = mysqli_fetch_assoc($result_used)) {
                                    $price = !empty($row['car_status_price']) ? "RM " . number_format($row['car_status_price']) : "TBA";
                                    $status = (!empty($row['car_status_status']) && in_array($row['car_status_status'], ['Active', 'Inactive'])) ? $row['car_status_status'] : 'Active';
                                    $dot_class = ($status == 'Active') ? 'dot-active' : 'dot-inactive';
                                    $text_class = ($status == 'Active') ? 'text-active' : 'text-inactive';
                                    $plate = !empty($row['car_plate']) ? htmlspecialchars($row['car_plate']) : '-';

                                    $c_id = $row['car_id'];
                                    $img_query = mysqli_query($conn, "SELECT car_image_url FROM car_image WHERE car_id = $c_id LIMIT 1");
                                    $img_row = mysqli_fetch_assoc($img_query);
                                    $img_url = !empty($img_row['car_image_url']) ? htmlspecialchars($img_row['car_image_url']) : '../../images/default-car.png';

                                    $type = !empty($row['car_type_name']) ? htmlspecialchars($row['car_type_name']) : '-';
                                    $location = !empty($row['location_city']) ? htmlspecialchars($row['location_city'] . ', ' . $row['location_state']) : '-';

                                    echo "<tr class='data-row print-used-car'>";
                                    echo "<td class='print-hide' style='padding-left: 16px;'><input type='checkbox' class='row-checkbox' style='cursor: pointer;'></td>";
                                    echo "<td style='text-align: left; color: #6b7280;'>CAR" . str_pad($row['car_id'], 3, '0', STR_PAD_LEFT) . "</td>";
                                    echo "<td style='text-align: left;'>
                                        <div style='display: flex; align-items: center; gap: 12px;'>
                                            <div class='print-hide' style='width: 60px; height: 40px; border-radius: 6px; overflow: hidden; background: #f3f4f6; flex-shrink: 0; border: 1px solid #e5e7eb;'>
                                                <img src='{$img_url}' style='width: 100%; height: 100%; object-fit: cover;'>
                                            </div>
                                            <div style='display: flex; flex-direction: column;'>
                                                <strong style='color: #111827; font-size: 13px;'>" . htmlspecialchars($row['car_brand']) . " <span style='color: #6b7280; font-weight: normal;'>" . htmlspecialchars($row['car_model']) . "</span></strong>
                                            </div>
                                        </div>
                                      </td>";
                                    echo "<td style='text-align: left;'>" . $type . "</td>";
                                    echo "<td style='text-align: left;'>" . $location . "</td>";
                                    echo "<td style='text-align: left; font-weight: 500;'>" . $plate . "</td>";
                                    echo "<td style='text-align: left;'>" . htmlspecialchars($row['car_year']) . "</td>";
                                    echo "<td style='text-align: left; font-weight: 600;'>" . $price . "</td>";
                                    echo "<td style='text-align: left;'><div class='status-cell' style='width: 85px;'><span class='dot {$dot_class} print-hide'></span><span class='{$text_class}'>" . ucfirst($status) . "</span></div></td>";

                                    $toggle_icon = ($status == 'Active') ? 'fa-lock' : 'fa-unlock';
                                    $toggle_color = ($status == 'Active') ? '#ef4444' : '#10b981';
                                    $json_data = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');

                                    echo "<td class='print-hide' style='text-align: center;'>
                                        <div style='display: flex; justify-content: center; gap: 12px;'>
                                            <a href='javascript:void(0);' onclick='editCarModal({$json_data})' style='color: #9ca3af;'><i class='fas fa-pen'></i></a>
                                            <a href='javascript:void(0);' onclick='toggleStatus({$row['car_id']}, \"{$status}\", this)' style='color: {$toggle_color};'><i class='fas {$toggle_icon}'></i></a>
                                        </div>
                                      </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='10' style='text-align: center; padding: 40px; color: var(--text-muted);'>No used cars found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    <?php if ($pages_used > 1): ?>
                        <div class="pagination-container print-hide"
                            style="padding: 16px; border-top: 1px solid #e5e7eb; display: flex; justify-content: center; gap: 8px;">
                            <?php $base_q = "?search=" . urlencode($search) . "&status=" . urlencode($status_filter) . "&tab=" . $active_tab; ?>
                            <a href="<?= $base_q ?>&p_new=<?= $p_new ?>&p_used=<?= max(1, $p_used - 1) ?>"
                                class="page-btn <?= ($p_used == 1) ? 'disabled' : '' ?>">Prev</a>
                            <?php for ($i = 1; $i <= $pages_used; $i++): ?>
                                <a href="<?= $base_q ?>&p_new=<?= $p_new ?>&p_used=<?= $i ?>"
                                    class="page-btn <?= ($p_used == $i) ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <a href="<?= $base_q ?>&p_new=<?= $p_new ?>&p_used=<?= min($pages_used, $p_used + 1) ?>"
                                class="page-btn <?= ($p_used == $pages_used) ? 'disabled' : '' ?>">Next</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <div id="carModal" class="modal">
        <div class="modal-content" style="width: 500px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 id="modalTitle" style="font-size: 20px; margin: 0;">Add New Car</h2>
                <div class="form-origin-btn-group" id="originButtonGroup">
                    <button type="button" class="origin-btn active" id="btn_new_car"
                        onclick="switchCarType('New Car')">New</button>
                    <button type="button" class="origin-btn" id="btn_used_car"
                        onclick="switchCarType('Used Car')">Used</button>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" id="carForm">
                <input type="hidden" name="car_id" id="form_car_id" value="">
                <input type="hidden" name="car_origin" id="form_car_origin" value="New Car">

                <div class="form-group" id="group_car_id" style="display: none;">
                    <label>Car ID</label>
                    <input type="text" id="display_car_id" class="form-control" readonly
                        style="background-color: #f3f4f6; color: #6b7280; font-weight: 600;">
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex:1;">
                        <label>Brand <small style="color:#9ca3af; font-weight:normal;">(e.g., Proton)</small></label>
                        <input type="text" name="car_brand" id="form_car_brand" class="form-control" required>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Model <small style="color:#9ca3af; font-weight:normal;">(e.g., X50)</small></label>
                        <input type="text" name="car_model" id="form_car_model" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex:1;">
                        <label>Manufacturing Year</label>
                        <input type="number" name="car_year" id="form_car_year" class="form-control" min="1990"
                            max="2026" required>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Price (RM)</label>
                        <input type="number" step="0.01" min="1" name="car_price" id="form_car_price"
                            class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Car Type</label>
                    <select name="car_type_id" id="form_car_type_id" class="form-control" required>
                        <?php foreach ($car_types as $type): ?>
                            <option value="<?= $type['car_type_id'] ?>"><?= htmlspecialchars($type['car_type_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="used_car_fields" style="display: none;">
                    <div class="form-row">
                        <div class="form-group custom-select" style="flex: 1;">
                            <label>State</label>
                            <select name="location_state" id="form_location_state" class="form-control"
                                onmousedown="if(this.options.length>4){this.size=4;}"
                                onchange="this.size=1; this.blur(); updateCities();" onblur="this.size=1;">
                                <option value="">Select State</option>
                            </select>
                        </div>
                        <div class="form-group custom-select" style="flex: 1;">
                            <label>City</label>
                            <select name="location_city" id="form_location_city" class="form-control"
                                onmousedown="if(this.options.length>4){this.size=4;}"
                                onchange="this.size=1; this.blur();" onblur="this.size=1;">
                                <option value="">Select City</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Plate Number <small style="color:#9ca3af; font-weight:normal;">(e.g., VHA
                                1234)</small></label>
                        <input type="text" name="car_plate" id="form_car_plate" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label>Upload Car Images <small style="color:#9ca3af; font-weight:normal;">(Multiple files
                            allowed)</small></label>
                    <input type="file" name="car_images[]" id="form_car_image" class="form-control" accept="image/*"
                        style="padding: 7px;" multiple>
                    <small style="color:#9ca3af; font-weight:normal; display:block; margin-top:4px;"
                        id="image_edit_note"></small>
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                    <button type="button" class="btn-export" onclick="closeCarModal()">Cancel</button>
                    <button type="submit" name="save_car" class="btn-add-blue" style="border:none;">Save Car</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const existingPlates = <?php echo json_encode($existing_plates); ?>;
    </script>
    <script src="../../JAVA SCRIPT/manage cars.js?v=<?php echo time(); ?>"></script>
</body>

</html>