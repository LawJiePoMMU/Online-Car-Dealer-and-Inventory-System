<?php
session_start();
include '../Config/database.php';
include '../Config/functions.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$threshold_query = mysqli_query($conn, "SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold'");
$res_threshold = mysqli_fetch_assoc($threshold_query);
$low_stock_limit = isset($res_threshold['setting_value']) ? (int) $res_threshold['setting_value'] : 2;

$low_stock_query = mysqli_query($conn, "
    SELECT c.car_id, c.car_brand, c.car_model, c.variant,
           GROUP_CONCAT(CONCAT(inv.color_name, ' (', inv.quantity, ' unit)') ORDER BY inv.color_name SEPARATOR ', ') as low_colors
    FROM cars c
    LEFT JOIN car_status stat ON c.car_id = stat.car_id
    LEFT JOIN car_inventory inv ON c.car_id = inv.car_id
    WHERE c.car_origin = 'New Car'
    AND inv.quantity <= $low_stock_limit
    AND inv.quantity >= 0
    GROUP BY c.car_id, c.car_brand, c.car_model, c.variant
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
    (1, 'Sedan'), (2, 'SUV'), (3, 'Hatchback'), (4, 'EV'), (5, 'Exora (MPV)')");

// AJAX: toggle status
if (isset($_GET['ajax'], $_GET['toggle_id'], $_GET['current_status'])) {
    $tid = (int) $_GET['toggle_id'];
    $newSt = ($_GET['current_status'] === 'Active') ? 'Inactive' : 'Active';
    mysqli_query($conn, "UPDATE car_status SET car_status_status='$newSt', car_status_updated_at=NOW() WHERE car_id=$tid");
    echo json_encode(['status' => 'success', 'new_status' => $newSt]);
    exit();
}

// AJAX: copy cars
if (isset($_GET['ajax'], $_GET['action']) && $_GET['action'] === 'copy_cars') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['car_ids']) && is_array($input['car_ids'])) {
        try {
            mysqli_begin_transaction($conn);
            $new_car_id = null;
            foreach ($input['car_ids'] as $old_id) {
                $old_id = (int) $old_id;
                $sql_copy_car = "INSERT INTO cars (
                    car_type_id, location_id, car_brand, car_model, car_year, car_origin, car_plate, engine_type, 
                    displacement, hp, torque, acceleration, transmission, drive_type, fuel_type, fuel_consumption, 
                    battery_range, dimensions, wheelbase, boot_cap, fuel_tank, weight, seats, ext_color, int_color, 
                    seat_mat, wheel_size, headlights, screen, feat_safety, feat_tech, feat_comf, description, 
                    negotiable, monthly_installment, promotion_rebate, promo_valid_until, cylinders, top_speed, 
                    co2_emissions, width_with_mirrors, ground_clearance, airbags_count, car_created_at
                ) SELECT 
                    car_type_id, location_id, car_brand, car_model, car_year, car_origin, NULL, engine_type, 
                    displacement, hp, torque, acceleration, transmission, drive_type, fuel_type, fuel_consumption, 
                    battery_range, dimensions, wheelbase, boot_cap, fuel_tank, weight, seats, ext_color, int_color, 
                    seat_mat, wheel_size, headlights, screen, feat_safety, feat_tech, feat_comf, description, 
                    negotiable, monthly_installment, promotion_rebate, promo_valid_until, cylinders, top_speed, 
                    co2_emissions, width_with_mirrors, ground_clearance, airbags_count, NOW()
                FROM cars WHERE car_id = $old_id";
                mysqli_query($conn, $sql_copy_car) or throw new Exception(mysqli_error($conn));
                $new_car_id = mysqli_insert_id($conn);
                mysqli_query($conn, "INSERT INTO car_status (car_id, car_status_price, car_status_stock_quantity, car_status_status, car_status_updated_at)
                    SELECT $new_car_id, car_status_price, 1, 'Draft', NOW() FROM car_status WHERE car_id = $old_id")
                    or throw new Exception(mysqli_error($conn));
                mysqli_query($conn, "INSERT INTO car_image (car_id, car_image_url) 
                    SELECT $new_car_id, car_image_url FROM car_image WHERE car_id = $old_id");
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

// AJAX: quick view
if (isset($_GET['ajax'], $_GET['action'], $_GET['car_id']) && $_GET['action'] === 'quick_view') {
    $vid = (int) $_GET['car_id'];
    $query = "SELECT c.*, stat.car_status_price, stat.car_status_status, stat.car_status_stock_quantity, ct.car_type_name, loc.location_state, loc.location_city,
              h.used_mileage, h.owners, h.accident, h.flood, h.service_hist, h.last_service, h.next_service, h.roadtax, h.puspakom, h.rem_warranty, h.defects,
              (SELECT GROUP_CONCAT(DISTINCT CONCAT_WS('::', color_name, color_hex, quantity) SEPARATOR '||') FROM car_inventory WHERE car_id = $vid) as color_data
              FROM cars c 
              LEFT JOIN car_status stat ON c.car_id = stat.car_id 
              LEFT JOIN car_types ct ON c.car_type_id = ct.car_type_id
              LEFT JOIN locations loc ON c.location_id = loc.location_id
              LEFT JOIN car_history h ON c.car_id = h.car_id
              WHERE c.car_id = $vid";
    $q = mysqli_query($conn, $query);
    if ($r = mysqli_fetch_assoc($q)) {
        echo json_encode(['status' => 'success', 'data' => $r]);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit();
}

// ── Tab: 'new' or 'used' only (All Cars removed) ──
$active_tab = (isset($_GET['tab']) && $_GET['tab'] === 'used') ? 'used' : 'new';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$search_condition = "";
if (!empty($search))
    $search_condition = " AND (c.car_brand LIKE '%$search%' OR c.car_model LIKE '%$search%' OR c.car_plate LIKE '%$search%') ";
if ($status_filter !== 'all')
    $search_condition .= " AND stat.car_status_status = '$status_filter' ";

$count_new = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT c.car_id) as c FROM cars c LEFT JOIN car_status stat ON c.car_id = stat.car_id WHERE c.car_origin = 'New Car' $search_condition"))['c'];
$count_used = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT c.car_id) as c FROM cars c LEFT JOIN car_status stat ON c.car_id = stat.car_id WHERE c.car_origin = 'Used Car' $search_condition"))['c'];

$limit = 10;

$p_new = max(1, (int) ($_GET['p_new'] ?? 1));
$p_used = max(1, (int) ($_GET['p_used'] ?? 1));
$offset_new = ($p_new - 1) * $limit;
$offset_used = ($p_used - 1) * $limit;
$pages_new = max(1, (int) ceil($count_new / $limit));
$pages_used = max(1, (int) ceil($count_used / $limit));

$result_new = $result_used = null;
try {
    $result_new = mysqli_query($conn, "SELECT c.*, stat.car_status_price, stat.car_status_status, stat.car_status_stock_quantity, 
                  loc.location_state, loc.location_city, ct.car_type_name,
                  GROUP_CONCAT(DISTINCT inv.variant_name SEPARATOR ', ') as all_variants,
                  GROUP_CONCAT(DISTINCT CONCAT_WS('::', inv.color_name, inv.color_hex, inv.quantity) SEPARATOR '||') as color_data
                  FROM cars c 
                  LEFT JOIN car_status stat ON c.car_id = stat.car_id 
                  LEFT JOIN locations loc ON c.location_id = loc.location_id
                  LEFT JOIN car_types ct ON c.car_type_id = ct.car_type_id
                  LEFT JOIN car_inventory inv ON c.car_id = inv.car_id
                  WHERE c.car_origin = 'New Car' $search_condition 
                  GROUP BY c.car_id 
                  ORDER BY (CASE WHEN stat.car_status_stock_quantity <= 1 THEN 0 ELSE 1 END) ASC, c.car_brand ASC, c.car_model ASC, c.car_id DESC
                  LIMIT $limit OFFSET $offset_new");

    $result_used = mysqli_query($conn, "SELECT c.*, stat.car_status_price, stat.car_status_status, stat.car_status_stock_quantity, 
               loc.location_state, loc.location_city, ct.car_type_name,
               c.variant as all_variants, c.ext_color as color_data
               FROM cars c 
               LEFT JOIN car_status stat ON c.car_id = stat.car_id 
               LEFT JOIN locations loc ON c.location_id = loc.location_id
               LEFT JOIN car_types ct ON c.car_type_id = ct.car_type_id
               LEFT JOIN car_inventory inv ON c.car_id = inv.car_id
               WHERE c.car_origin = 'Used Car' $search_condition 
               GROUP BY c.car_id 
               ORDER BY FIELD(stat.car_status_status, 'Active', 'Inactive') ASC, c.car_brand ASC, c.car_model ASC, c.car_id DESC
               LIMIT $limit OFFSET $offset_used");
} catch (Exception $e) {
}

function getCarImgUrl($conn, $car_id)
{
    $q = mysqli_query($conn, "SELECT car_image_url FROM car_image WHERE car_id=" . (int) $car_id . " LIMIT 1");
    $r = mysqli_fetch_assoc($q);
    return ($r && !empty($r['car_image_url']) && file_exists($r['car_image_url']))
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        @keyframes pulse-red {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
            }

            70% {
                box-shadow: 0 0 0 6px rgba(239, 68, 68, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }

        .pulse-warning {
            animation: pulse-red 1.5s infinite;
            border-color: #ef4444 !important;
        }

        .row-checkbox,
        .selectAllColumn {
            display: none;
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

        .table-section {
            margin-bottom: 48px;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar" style="margin-bottom:24px;">
            <div class="page-title">
                <h1 style="font-size:24px;font-weight:700;color:#111827;">Manage Cars</h1>
            </div>
        </header>

        <!-- Tabs: New Cars / Used Cars only — All Cars removed -->
        <div class="page-tabs">
            <a href="?tab=new&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>"
                class="tab-item <?= $active_tab === 'new' ? 'active' : '' ?>">
                New Cars (<?= $count_new ?>)
            </a>
            <a href="?tab=used&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>"
                class="tab-item <?= $active_tab === 'used' ? 'active' : '' ?>">
                Used Cars (<?= $count_used ?>)
            </a>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;">
            <form method="GET" style="display:flex;gap:16px;">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
                <div style="position:relative;">
                    <i class="fas fa-search"
                        style="position:absolute;left:14px;top:11px;color:#9ca3af;font-size:14px;"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search Brand, Model or Plate..."
                        style="padding-left:38px;width:280px;font-size:13px;" value="<?= htmlspecialchars($search) ?>">
                </div>
                <select name="status" class="form-control" style="width:150px;font-size:13px;"
                    onchange="this.form.submit()">
                    <option value="all">All Status</option>
                    <option value="Active" <?= $status_filter === 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= $status_filter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </form>
            <div style="display:flex;gap:12px;">
                <button type="button" class="btn-export" id="copyBtn" onclick="toggleCopyMode()">
                    <i class="fas fa-copy"></i> Copy Selected
                </button>
                <button type="button" class="btn-export" id="cancelCopyBtn" onclick="cancelCopyMode()"
                    style="display:none;background-color:#64748b;color:white;border-color:#64748b;">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <a href="edit_car_details.php" class="btn-add-blue"
                    style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;box-sizing:border-box;">
                    <i class="fas fa-plus"></i> Add Car
                </a>
            </div>
        </div>

        <?php if ($active_tab === 'new'): ?>
            <div id="section_new" class="table-section">
                <div class="column-header"><i class="fas fa-car" style="color:var(--primary-color);"></i> New Cars</div>
                <div class="table-card"
                    style="padding:0;border:none;background:white;border-radius:12px;border:1px solid var(--border-color);">
                    <table class="admin-table" id="table_new" style="width:100%;">
                        <thead>
                            <tr>
                                <th class="print-hide" style="width:4%;padding-left:16px;"><input type="checkbox"
                                        class="selectAllColumn" style="cursor:pointer;"></th>
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
                                    $stock = (int) $row['car_status_stock_quantity'];
                                    $is_incomplete = empty($row['car_brand']) || empty($row['car_model']) || empty($row['car_year']) || empty($row['car_status_price']);
                                    $is_preorder = ($stock <= 1);
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
                                                $badge_bg = ($c_qty <= $low_stock_limit) ? "#ef4444" : "#6b7280";
                                                $color_html .= "<div style='position:relative;display:inline-block;margin-right:12px;margin-top:4px;' title='{$c_name} (Qty: {$c_qty})'>
                                        <div class='{$warning_class}' style='background-color:{$c_hex};width:18px;height:18px;border-radius:50%;border:1px solid #d1d5db;box-shadow:0 1px 2px rgba(0,0,0,0.1);cursor:pointer;'></div>
                                        <span style='position:absolute;top:-8px;right:-8px;background:{$badge_bg};color:white;font-size:9px;font-weight:bold;width:14px;height:14px;display:flex;align-items:center;justify-content:center;border-radius:50%;border:1px solid white;'>{$c_qty}</span>
                                    </div>";
                                            }
                                        }
                                    } else {
                                        $color_html = "<span style='background:#f3f4f6;color:#9ca3af;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:600;border:1px dashed #d1d5db;'>No Colors</span>";
                                    }

                                    if ($stock == 0) {
                                        $badge_bg = "#fee2e2";
                                        $badge_text = "#991b1b";
                                    } elseif ($stock <= $low_stock_limit) {
                                        $badge_bg = "#fef3c7";
                                        $badge_text = "#92400e";
                                    } else {
                                        $badge_bg = "#dcfce7";
                                        $badge_text = "#166534";
                                    }

                                    echo "<tr class='data-row print-new-car'>";
                                    echo "<td class='print-hide' style='padding-left:16px;'><input type='checkbox' class='row-checkbox' style='cursor:pointer;'></td>";
                                    echo "<td style='text-align:left;color:#6b7280;'>CAR" . str_pad($row['car_id'], 3, '0', STR_PAD_LEFT) . "</td>";
                                    echo "<td style='text-align:left;'>
                                <div style='display:flex;align-items:center;gap:12px;'>
                                    <div class='print-hide' style='width:60px;height:40px;border-radius:6px;overflow:hidden;background:#f3f4f6;flex-shrink:0;border:1px solid #e5e7eb;'>
                                        <img src='{$img_url}' style='width:100%;height:100%;object-fit:cover;'>
                                    </div>
                                    <div style='display:flex;flex-direction:column;'>";
                                    if ($is_incomplete || $is_preorder) {
                                        $icon_color = $is_preorder ? "#ef4444" : "#f59e0b";
                                        $icon_title = $is_preorder ? "Pre-order Mode (Low Stock)" : "Information Incomplete";
                                        echo "<div style='display:flex;align-items:center;gap:5px;margin-bottom:2px;'>
                                    <i class='fas fa-exclamation-triangle' style='color:{$icon_color};font-size:12px;' title='{$icon_title}'></i>
                                    <strong style='color:#111827;font-size:13px;'>" . htmlspecialchars($row['car_brand']) . "</strong>
                                  </div>";
                                    } else {
                                        echo "<strong style='color:#111827;font-size:13px;margin-bottom:2px;'>" . htmlspecialchars($row['car_brand']) . "</strong>";
                                    }
                                    echo "<span style='color:#6b7280;font-weight:normal;font-size:12px;'>" . htmlspecialchars($row['car_model']) . "</span>";
                                    echo "<div style='display:flex;flex-direction:column;gap:4px;margin-top:6px;'>
                                <div style='display:flex;flex-wrap:wrap;'>
                                    <span style='background:#f3f4f6;color:#4b5563;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:600;' title='Variants'>
                                        <i class='fas fa-car-side' style='margin-right:3px;'></i> {$variants_text}
                                    </span>
                                </div>
                                <div style='display:flex;align-items:center;flex-wrap:wrap;'>{$color_html}</div>
                              </div>";
                                    echo "      </div>
                                </div>
                              </td>";
                                    echo "<td style='text-align:left;'>{$type}</td>";
                                    echo "<td style='text-align:center;'><span style='background:{$badge_bg};color:{$badge_text};padding:4px 10px;border-radius:12px;font-size:11px;font-weight:700;'>{$stock} Units</span></td>";
                                    echo "<td style='text-align:left;'>" . htmlspecialchars($row['car_year']) . "</td>";
                                    echo "<td style='text-align:left;font-weight:600;'>{$price}</td>";
                                    echo "<td style='text-align:left;'><div class='status-cell' style='width:85px;'><span class='dot {$dot_class} print-hide'></span><span class='{$text_class}'>" . ucfirst($status) . "</span></div></td>";
                                    echo "<td class='print-hide' style='text-align:center;'>
                                <div style='display:flex;justify-content:center;gap:12px;'>
                                    <a href='javascript:void(0);' onclick='quickView({$row['car_id']})' style='color:#3b82f6;' title='Quick View'><i class='fas fa-eye'></i></a>
                                    <a href='edit_car_details.php?id={$row['car_id']}' style='color:#9ca3af;' title='Edit'><i class='fas fa-pen'></i></a>
                                    <a href='javascript:void(0);' onclick='toggleStatus({$row['car_id']}, \"{$status}\", this)' style='color:{$toggle_color};' title='Toggle Status'><i class='fas {$toggle_icon}'></i></a>
                                </div>
                              </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='9' style='text-align:center;padding:40px;color:var(--text-muted);'>No new cars found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    <?php if ($pages_new > 1):
                        $base_q = "?tab=new&search=" . urlencode($search) . "&status=" . urlencode($status_filter); ?>
                        <div class="pagination-container print-hide"
                            style="padding:16px;border-top:1px solid #e5e7eb;display:flex;justify-content:center;gap:8px;">
                            <a href="<?= $base_q ?>&p_new=<?= max(1, $p_new - 1) ?>"
                                class="page-btn <?= ($p_new == 1) ? 'disabled' : '' ?>">Prev</a>
                            <?php for ($i = 1; $i <= $pages_new; $i++): ?>
                                <a href="<?= $base_q ?>&p_new=<?= $i ?>"
                                    class="page-btn <?= ($p_new == $i) ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <a href="<?= $base_q ?>&p_new=<?= min($pages_new, $p_new + 1) ?>"
                                class="page-btn <?= ($p_new == $pages_new) ? 'disabled' : '' ?>">Next</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($active_tab === 'used'):
            $ext_color_map = [
                'Solid White' => '#ffffff',
                'Pearl White' => '#f8fafc',
                'Silver' => '#d1d5db',
                'Meteor Grey' => '#6b7280',
                'Black' => '#111827',
                'Matte Black' => '#1f2937',
                'Ruby Red' => '#ef4444',
                'Maroon' => '#991b1b',
                'Orange' => '#f97316',
                'Yellow' => '#eab308',
                'Champagne Gold' => '#d97706',
                'Bronze' => '#b45309',
                'Ocean Blue' => '#3b82f6',
                'Cyan' => '#0ea5e9',
                'Navy Blue' => '#1e3a8a',
                'Green' => '#22c55e',
                'Dark Green' => '#064e3b',
                'Purple' => '#8b5cf6',
            ];
            ?>
            <div id="section_used" class="table-section">
                <div class="column-header"><i class="fas fa-car-side" style="color:var(--text-muted);"></i> Used Cars</div>
                <div class="table-card"
                    style="padding:0;border:none;background:white;border-radius:12px;border:1px solid var(--border-color);">
                    <table class="admin-table" id="table_used" style="width:100%;">
                        <thead>
                            <tr>
                                <th class="print-hide" style="width:4%;padding-left:16px;"><input type="checkbox"
                                        class="selectAllColumn" style="cursor:pointer;"></th>
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
                                    $stock = (int) $row['car_status_stock_quantity'];
                                    $is_incomplete = empty($row['car_brand']) || empty($row['car_model']) || empty($row['car_year']) || empty($row['car_status_price']) || empty($row['car_plate']) || empty($row['location_city']);
                                    $price = !empty($row['car_status_price']) ? "RM " . number_format($row['car_status_price']) : "TBA";
                                    $status = (!empty($row['car_status_status']) && in_array($row['car_status_status'], ['Active', 'Inactive'])) ? $row['car_status_status'] : 'Active';
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
                                        $c_name = htmlspecialchars($row['color_data']);
                                        $c_hex = isset($ext_color_map[$row['color_data']]) ? $ext_color_map[$row['color_data']] : '#d1d5db';
                                        $color_html = "<div style='display:inline-flex;align-items:center;background:#f8fafc;padding:3px 10px;border-radius:20px;border:1px solid #e2e8f0;gap:6px;margin-top:4px;'>
                                <div style='width:12px;height:12px;border-radius:50%;background-color:{$c_hex};border:1px solid rgba(0,0,0,0.1);flex-shrink:0;'></div>
                                <span style='font-size:11px;color:#374151;font-weight:500;'>{$c_name}</span>
                            </div>";
                                    } else {
                                        $color_html = "<span style='background:#f3f4f6;color:#9ca3af;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:600;border:1px dashed #d1d5db;'>No Colors</span>";
                                    }

                                    $badge_bg = ($stock > 0) ? "#dcfce7" : "#fee2e2";
                                    $badge_text = ($stock > 0) ? "#166534" : "#991b1b";
                                    $display_qty = ($stock > 0) ? "Available" : "SOLD";

                                    echo "<tr class='data-row print-used-car'>";
                                    echo "<td class='print-hide' style='padding-left:16px;'><input type='checkbox' class='row-checkbox' style='cursor:pointer;'></td>";
                                    echo "<td style='text-align:left;color:#6b7280;'>CAR" . str_pad($row['car_id'], 3, '0', STR_PAD_LEFT) . "</td>";
                                    echo "<td style='text-align:left;'>
                                <div style='display:flex;align-items:center;gap:12px;'>
                                    <div class='print-hide' style='position:relative;width:60px;height:40px;border-radius:6px;overflow:hidden;background:#f3f4f6;flex-shrink:0;border:1px solid #e5e7eb;'>
                                        <img src='{$img_url}' style='width:100%;height:100%;object-fit:cover;'>";
                                    if ($stock == 0) {
                                        echo "<div style='position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);color:white;font-size:10px;display:flex;align-items:center;justify-content:center;font-weight:bold;'>SOLD</div>";
                                    }
                                    echo "      </div>
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
                                <div style='display:flex;flex-wrap:wrap;'>
                                    <span style='background:#f3f4f6;color:#4b5563;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:600;' title='Variants'>
                                        <i class='fas fa-car-side' style='margin-right:3px;'></i> {$variants_text}
                                    </span>
                                </div>
                                <div style='display:flex;align-items:center;flex-wrap:wrap;'>{$color_html}</div>
                              </div>";
                                    echo "      </div>
                                </div>
                              </td>";
                                    echo "<td style='text-align:left;'>{$type}</td>";
                                    echo "<td style='text-align:center;'><span style='background:{$badge_bg};color:{$badge_text};padding:4px 10px;border-radius:12px;font-size:11px;font-weight:700;'>{$display_qty}</span></td>";
                                    echo "<td style='text-align:left;'>{$location}</td>";
                                    echo "<td style='text-align:left;font-weight:500;'>{$plate}</td>";
                                    echo "<td style='text-align:left;'>" . htmlspecialchars($row['car_year']) . "</td>";
                                    echo "<td style='text-align:left;font-weight:600;'>{$price}</td>";
                                    echo "<td style='text-align:left;'><div class='status-cell' style='width:85px;'><span class='dot {$dot_class} print-hide'></span><span class='{$text_class}'>" . ucfirst($status) . "</span></div></td>";
                                    echo "<td class='print-hide' style='text-align:center;'>
                                <div style='display:flex;justify-content:center;gap:12px;'>
                                    <a href='javascript:void(0);' onclick='quickView({$row['car_id']})' style='color:#3b82f6;' title='Quick View'><i class='fas fa-eye'></i></a>
                                    <a href='edit_car_details.php?id={$row['car_id']}' style='color:#9ca3af;' title='Edit'><i class='fas fa-pen'></i></a>
                                    <a href='javascript:void(0);' onclick='toggleStatus({$row['car_id']}, \"{$status}\", this)' style='color:{$toggle_color};' title='Toggle Status'><i class='fas {$toggle_icon}'></i></a>
                                </div>
                              </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='11' style='text-align:center;padding:40px;color:var(--text-muted);'>No used cars found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    <?php if ($pages_used > 1):
                        $base_q = "?tab=used&search=" . urlencode($search) . "&status=" . urlencode($status_filter); ?>
                        <div class="pagination-container print-hide"
                            style="padding:16px;border-top:1px solid #e5e7eb;display:flex;justify-content:center;gap:8px;">
                            <a href="<?= $base_q ?>&p_used=<?= max(1, $p_used - 1) ?>"
                                class="page-btn <?= ($p_used == 1) ? 'disabled' : '' ?>">Prev</a>
                            <?php for ($i = 1; $i <= $pages_used; $i++): ?>
                                <a href="<?= $base_q ?>&p_used=<?= $i ?>"
                                    class="page-btn <?= ($p_used == $i) ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <a href="<?= $base_q ?>&p_used=<?= min($pages_used, $p_used + 1) ?>"
                                class="page-btn <?= ($p_used == $pages_used) ? 'disabled' : '' ?>">Next</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </main>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>window.GLOBAL_LOW_STOCK = <?= $low_stock_limit ?>;</script>
    <script src="../../JAVA SCRIPT/manage cars.js?v=<?= time() ?>"></script>
</body>

</html>