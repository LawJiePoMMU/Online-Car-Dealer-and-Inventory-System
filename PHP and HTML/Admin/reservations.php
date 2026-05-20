<?php
session_start();
include '../Config/database.php';
include '../Config/functions.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$admin_id = $_SESSION['user_id'] ?? 1;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  ob_clean();
  header('Content-Type: application/json');
  $action = $_POST['action'];

  try {
    switch ($action) {
      case 'approve_reservation':
        $res_id = intval($_POST['reservation_id'] ?? 0);
        $cur = mysqli_fetch_assoc(mysqli_query($conn, "SELECT reservation_status, preferred_test_drive_at FROM reservations WHERE reservation_id=$res_id"));
        if (!$cur || $cur['reservation_status'] !== 'Pending Viewing') {
          echo json_encode(['success' => false, 'message' => 'Reservation is not pending.']);
          break;
        }

        $td_at = $cur['preferred_test_drive_at'];
        if (empty($td_at) || $td_at === '0000-00-00 00:00:00') {
          echo json_encode(['success' => false, 'message' => 'Customer test drive time missing.']);
          break;
        }

        if (strtotime($td_at) < time()) {
          echo json_encode(['success' => false, 'message' => 'Test drive time is in the past.']);
          break;
        }

        mysqli_begin_transaction($conn);
        mysqli_query($conn, "UPDATE reservations SET reservation_status='Approved' WHERE reservation_id=$res_id");
        mysqli_query($conn, "INSERT INTO test_drives (reservation_id, test_drive_at, test_drive_status, created_at) VALUES ($res_id, '$td_at', 'Scheduled', NOW())");
        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Reservation approved & scheduled automatically.']);
        break;

      case 'reject_reservation':
        $res_id = intval($_POST['reservation_id'] ?? 0);
        $reason = mysqli_real_escape_string($conn, trim($_POST['reason'] ?? ''));
        if (!$res_id) {
          echo json_encode(['success' => false, 'message' => 'Invalid reservation.']);
          break;
        }
        if ($reason === '') {
          echo json_encode(['success' => false, 'message' => 'Rejection reason is required.']);
          break;
        }
        mysqli_query($conn, "UPDATE reservations SET reservation_status='Rejected', reservation_cancel_reason='$reason' WHERE reservation_id=$res_id AND reservation_status='Pending Viewing'");
        echo json_encode(['success' => true, 'message' => 'Reservation rejected.']);
        break;

      case 'mark_test_drive_completed':
        $td_id = intval($_POST['test_drive_id'] ?? 0);
        if (!$td_id) {
          echo json_encode(['success' => false, 'message' => 'Invalid test drive.']);
          break;
        }
        $row = mysqli_fetch_assoc(mysqli_query($conn, "
                    SELECT t.test_drive_status, r.driving_licence_url 
                    FROM test_drives t LEFT JOIN reservations r ON t.reservation_id=r.reservation_id 
                    WHERE t.test_drive_id=$td_id
                "));
        if (!$row) {
          echo json_encode(['success' => false, 'message' => 'Test drive not found.']);
          break;
        }
        if ($row['test_drive_status'] !== 'Scheduled') {
          echo json_encode(['success' => false, 'message' => 'Only scheduled test drives can be completed.']);
          break;
        }
        if (empty($row['driving_licence_url'])) {
          echo json_encode(['success' => false, 'message' => 'Driving licence must be uploaded first.']);
          break;
        }
        mysqli_query($conn, "UPDATE test_drives SET test_drive_status='Completed', test_drive_done_at=NOW() WHERE test_drive_id=$td_id");
        echo json_encode(['success' => true, 'message' => 'Test drive marked completed.']);
        break;

      case 'cancel_test_drive':
        $td_id = intval($_POST['test_drive_id'] ?? 0);
        $reason = mysqli_real_escape_string($conn, trim($_POST['reason'] ?? ''));
        if (!$td_id) {
          echo json_encode(['success' => false, 'message' => 'Invalid test drive.']);
          break;
        }
        if ($reason === '') {
          echo json_encode(['success' => false, 'message' => 'Cancellation reason is required.']);
          break;
        }
        mysqli_query($conn, "UPDATE test_drives SET test_drive_status='Cancelled', test_drive_cancel_reason='$reason', test_drive_done_at=NOW() WHERE test_drive_id=$td_id AND test_drive_status='Scheduled'");
        echo json_encode(['success' => true, 'message' => 'Test drive cancelled.']);
        break;

      case 'upload_licence':
        $res_id = intval($_POST['reservation_id'] ?? 0);
        if (!$res_id) {
          echo json_encode(['success' => false, 'message' => 'Invalid reservation.']);
          break;
        }
        if (!isset($_FILES['doc_file']) || $_FILES['doc_file']['error'] !== UPLOAD_ERR_OK) {
          echo json_encode(['success' => false, 'message' => 'Upload failed.']);
          break;
        }
        if (strtolower(pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION)) !== 'pdf') {
          echo json_encode(['success' => false, 'message' => 'Only PDF allowed.']);
          break;
        }
        $target_dir = "../../uploads/documents/";
        if (!is_dir($target_dir))
          mkdir($target_dir, 0777, true);
        $filename = 'licence_res' . $res_id . '_' . time() . '.pdf';
        if (!move_uploaded_file($_FILES['doc_file']['tmp_name'], $target_dir . $filename)) {
          echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
          break;
        }
        mysqli_query($conn, "UPDATE reservations SET driving_licence_url='../../uploads/documents/$filename' WHERE reservation_id=$res_id");
        echo json_encode(['success' => true, 'message' => 'Driving licence uploaded.']);
        break;

      default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
  } catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
  }
  exit;
}

if (isset($_GET['highlight']) && !isset($_GET['page']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $hl_id = (int) $_GET['highlight'];
    $find_q = mysqli_query($conn, "
        SELECT reservation_id FROM reservations 
        WHERE reservation_status='Pending Viewing' 
        ORDER BY reservation_id ASC
    ");
    $pos = 0; $found = 0;
    while ($r = mysqli_fetch_assoc($find_q)) {
        $pos++;
        if ($r['reservation_id'] == $hl_id) { $found = $pos; break; }
    }
    if ($found > 0) {
        $target_page = (int) ceil($found / 10);
        header("Location: reservations.php?tab=reservations&page=$target_page&highlight=$hl_id");
        exit();
    }
}

$tab = $_GET['tab'] ?? 'reservations';
if (!in_array($tab, ['reservations', 'test_drives', 'history']))
  $tab = 'reservations';

$sub_tab = $_GET['sub_tab'] ?? 'test_drives';
if ($tab === 'history' && !in_array($sub_tab, ['reservations', 'test_drives'])) {
  $sub_tab = 'reservations';
}

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$search_cond = '';
if (!empty($search)) {
  $search_cond = " AND (u.user_name LIKE '%$search%' OR c.car_brand LIKE '%$search%' OR c.car_model LIKE '%$search%') ";
}

$count_reservations = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM reservations WHERE reservation_status='Pending Viewing'"))['c'];
$count_test_drives = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM test_drives WHERE test_drive_status='Scheduled'"))['c'];
$count_history_res = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM reservations WHERE reservation_status='Rejected'"))['c'];
$count_history_td = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM test_drives WHERE test_drive_status IN ('Completed','Cancelled')"))['c'];
$count_history = $count_history_res + $count_history_td;

$limit = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$result = null;
$total_rows = 0;

if ($tab === 'reservations') {
  $where = "WHERE r.reservation_status='Pending Viewing' $search_cond";
  $total_rows = (int) mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COUNT(*) AS c FROM reservations r 
        LEFT JOIN users u ON r.user_id=u.user_id 
        LEFT JOIN cars c ON r.car_id=c.car_id 
        LEFT JOIN used_car_details ucd ON c.car_id=ucd.car_id
        $where
    "))['c'];

  $result = mysqli_query($conn, "
        SELECT r.*, u.user_name, u.user_ic, u.user_phone, u.user_email,
               c.car_brand, c.car_model, c.car_year, c.car_origin,
               ucd.car_plate,
               (SELECT car_image_url FROM car_image WHERE car_id=c.car_id LIMIT 1) AS car_image,
               (SELECT variant FROM car_inventory WHERE car_id=c.car_id LIMIT 1) AS car_variant,
               (SELECT color_name   FROM car_inventory WHERE car_id=c.car_id LIMIT 1) AS car_color
        FROM reservations r
        LEFT JOIN users u ON r.user_id=u.user_id
        LEFT JOIN cars  c ON r.car_id=c.car_id
        LEFT JOIN used_car_details ucd ON c.car_id=ucd.car_id
        $where
        ORDER BY r.reservation_id ASC
        LIMIT $limit OFFSET $offset
    ");
} elseif ($tab === 'test_drives') {
  $where = "WHERE t.test_drive_status='Scheduled' $search_cond";
  $total_rows = (int) mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COUNT(*) AS c FROM test_drives t
        LEFT JOIN reservations r ON t.reservation_id=r.reservation_id
        LEFT JOIN users u ON r.user_id=u.user_id
        LEFT JOIN cars  c ON r.car_id=c.car_id
        LEFT JOIN used_car_details ucd ON c.car_id=ucd.car_id
        $where
    "))['c'];
  $result = mysqli_query($conn, "
        SELECT t.*, r.user_id, r.car_id, r.driving_licence_url, r.reservation_status, r.reservation_created_at, 
               r.snapshot_data, r.preferred_test_drive_at,
               u.user_name, u.user_ic, u.user_phone, u.user_email,
               c.car_brand, c.car_model, c.car_year, c.car_origin,
               ucd.car_plate,
               (SELECT car_image_url FROM car_image WHERE car_id=c.car_id LIMIT 1) AS car_image,
               (SELECT variant FROM car_inventory WHERE car_id=c.car_id LIMIT 1) AS car_variant,
               (SELECT color_name   FROM car_inventory WHERE car_id=c.car_id LIMIT 1) AS car_color
        FROM test_drives t
        LEFT JOIN reservations r ON t.reservation_id=r.reservation_id
        LEFT JOIN users u ON r.user_id=u.user_id
        LEFT JOIN cars  c ON r.car_id=c.car_id
        LEFT JOIN used_car_details ucd ON c.car_id=ucd.car_id
        $where
        ORDER BY ABS(TIMESTAMPDIFF(SECOND, NOW(), t.test_drive_at)) ASC
        LIMIT $limit OFFSET $offset
    ");
} else {
  if ($sub_tab === 'reservations') {
    $where = "WHERE r.reservation_status='Rejected' $search_cond";
    $total_rows = (int) mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT COUNT(*) AS c FROM reservations r 
            LEFT JOIN users u ON r.user_id=u.user_id 
            LEFT JOIN cars c ON r.car_id=c.car_id 
            $where
        "))['c'];

    $result = mysqli_query($conn, "
            SELECT r.*, u.user_name, u.user_ic, u.user_phone, u.user_email,
                   c.car_brand, c.car_model, c.car_year, c.car_origin,
                   ucd.car_plate,
                   (SELECT car_image_url FROM car_image WHERE car_id=c.car_id LIMIT 1) AS car_image,
                   (SELECT variant FROM car_inventory WHERE car_id=c.car_id LIMIT 1) AS car_variant,
                   (SELECT color_name   FROM car_inventory WHERE car_id=c.car_id LIMIT 1) AS car_color
            FROM reservations r
            LEFT JOIN users u ON r.user_id=u.user_id
            LEFT JOIN cars  c ON r.car_id=c.car_id
            LEFT JOIN used_car_details ucd ON c.car_id=ucd.car_id
            $where
            ORDER BY r.reservation_created_at DESC
            LIMIT $limit OFFSET $offset
        ");
  } else {
    $where = "WHERE t.test_drive_status IN ('Completed','Cancelled') $search_cond";
    $total_rows = (int) mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT COUNT(*) AS c FROM test_drives t
            LEFT JOIN reservations r ON t.reservation_id=r.reservation_id
            LEFT JOIN users u ON r.user_id=u.user_id
            LEFT JOIN cars  c ON r.car_id=c.car_id
            $where
        "))['c'];

    $result = mysqli_query($conn, "
            SELECT t.*, r.user_id, r.car_id, r.driving_licence_url, r.reservation_status, r.reservation_created_at,
                   r.snapshot_data, r.preferred_test_drive_at,
                   u.user_name, u.user_ic, u.user_phone, u.user_email,
                   c.car_brand, c.car_model, c.car_year, c.car_origin, ucd.car_plate,
                   (SELECT car_image_url FROM car_image WHERE car_id=c.car_id LIMIT 1) AS car_image,
                   (SELECT variant FROM car_inventory WHERE car_id=c.car_id LIMIT 1) AS car_variant,
                   (SELECT color_name   FROM car_inventory WHERE car_id=c.car_id LIMIT 1) AS car_color
            FROM test_drives t
            LEFT JOIN reservations r ON t.reservation_id=r.reservation_id
            LEFT JOIN users u ON r.user_id=u.user_id
            LEFT JOIN cars  c ON r.car_id=c.car_id
            LEFT JOIN used_car_details ucd ON c.car_id=ucd.car_id
            $where
            ORDER BY COALESCE(t.test_drive_done_at, t.test_drive_at) DESC
            LIMIT $limit OFFSET $offset
        ");
  }
}

$total_pages = max(1, (int) ceil($total_rows / $limit));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reservations &amp; Test Drives</title>
  <link rel="stylesheet" href="/Online-Car-Dealer-and-Inventory-System/CSS/admin.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>
<style>
  body {
    background: #f8fafc;
  }

  .table-card {
    background: #ffffff;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03), 0 8px 24px rgba(0, 0, 0, 0.04);
  }

  .admin-table th {
    color: #374151;
    font-weight: 700;
    font-size: 12px;
    letter-spacing: 0.4px;
    text-transform: uppercase;
  }

  .admin-table tbody tr {
    transition: all 0.2s ease;
    cursor: pointer;
  }

  .admin-table tbody tr:hover {
    background-color: #f9fafb;
  }

  .admin-table td {
    vertical-align: middle;
  }

  .btn-add-blue {
    border-radius: 12px;
    padding: 12px 18px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s ease;
  }

  .btn-add-blue:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 20px rgba(37, 99, 235, 0.15);
  }

  .btn-export {
    border-radius: 12px;
    padding: 12px 18px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s ease;
  }

  .btn-export:hover {
    transform: translateY(-1px);
  }

  .form-control {
    border-radius: 12px;
    transition: all 0.2s ease;
  }

  .form-control:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
    outline: none;
  }

  .status-cell {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
  }

  .dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
  }

  .dot-pending-viewing {
    background: #f59e0b;
  }

  .dot-approved {
    background: #10b981;
  }

  .dot-rejected {
    background: #ef4444;
  }

  .dot-scheduled {
    background: #3b82f6;
  }

  .dot-completed {
    background: #10b981;
  }

  .dot-cancelled {
    background: #ef4444;
  }

  .text-pending-viewing {
    color: #d97706;
  }

  .text-approved {
    color: #16a34a;
  }

  .text-rejected {
    color: #dc2626;
  }

  .text-scheduled {
    color: #2563eb;
  }

  .text-completed {
    color: #16a34a;
  }

  .text-cancelled {
    color: #dc2626;
  }

  .tab-item {
    padding-bottom: 12px;
    color: #6b7280;
    font-weight: 500;
    font-size: 14px;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    text-decoration: none;
    transition: all 0.2s ease;
  }

  .tab-item:hover {
    color: #2563eb;
  }

  .tab-item.active {
    color: #111827;
    border-bottom-color: #2563eb;
    font-weight: 700;
  }

  .page-tabs {
    display: flex;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 24px;
    gap: 32px;
    padding-left: 8px;
  }

  .sub-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
  }

  .sub-tab {
    padding: 8px 16px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 999px;
    color: #6b7280;
    font-weight: 500;
    font-size: 13px;
    text-decoration: none;
    transition: all 0.2s ease;
  }

  .sub-tab:hover {
    background: #f3f4f6;
    color: #2563eb;
  }

  .sub-tab.active {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
  }

  .page-btn {
    border-radius: 10px;
    padding: 6px 12px;
    border: 1px solid #d1d5db;
    background: white;
    color: #374151;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s ease;
  }

  .page-btn:hover {
    background: #f3f4f6;
  }

  .page-btn.active {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
  }

  .topbar {
    backdrop-filter: blur(10px);
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

  .modal {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(6px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
  }

  .modal-content {
    background: white;
    border-radius: 18px;
    padding: 24px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.18);
  }

  .layout-grid {
    display: flex;
    gap: 18px;
    margin-bottom: 20px;
  }

  .info-card {
    flex: 1;
    background: #f9fafb;
    border-radius: 12px;
    padding: 16px;
    border: 1px solid #e5e7eb;
  }

  .grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 10px;
  }

  .detail-item label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
    margin-bottom: 4px;
  }

  .detail-item p {
    font-size: 13px;
    color: #111827;
    font-weight: 600;
    margin: 0;
  }
</style>

<body>
  <?php include 'sidebar.php'; ?>

  <main class="main-content">
    <header class="topbar" style="margin-bottom:24px;">
      <div class="page-title">
        <h1 style="font-size:24px;font-weight:700;color:#111827;">Reservations &amp; Test Drives</h1>
      </div>
    </header>

    <div class="page-tabs">
      <a href="?tab=reservations" class="tab-item <?= $tab === 'reservations' ? 'active' : '' ?>">
        Reservations (<?= $count_reservations ?>)
      </a>
      <a href="?tab=test_drives" class="tab-item <?= $tab === 'test_drives' ? 'active' : '' ?>">
        Test Drives (<?= $count_test_drives ?>)
      </a>
      <a href="?tab=history" class="tab-item <?= $tab === 'history' ? 'active' : '' ?>">
        History (<?= $count_history ?>)
      </a>
    </div>

    <?php if ($tab === 'history'): ?>
      <div class="sub-tabs">
        <a href="?tab=history&sub_tab=test_drives" class="sub-tab <?= $sub_tab === 'test_drives' ? 'active' : '' ?>">
          <i class="fas fa-flag-checkered"></i> Past Test Drives (<?= $count_history_td ?>)
        </a>
        <a href="?tab=history&sub_tab=reservations" class="sub-tab <?= $sub_tab === 'reservations' ? 'active' : '' ?>">
          <i class="fas fa-times-circle"></i> Rejected Reservations (<?= $count_history_res ?>)
        </a>
      </div>
    <?php endif; ?>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <form method="GET" style="display:flex;gap:16px;">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <?php if ($tab === 'history'): ?>
          <input type="hidden" name="sub_tab" value="<?= htmlspecialchars($sub_tab) ?>">
        <?php endif; ?>
        <div style="position:relative;">
          <i class="fas fa-search" style="position:absolute;left:14px;top:11px;color:#9ca3af;font-size:14px;"></i>
          <input type="text" name="search" class="form-control" placeholder="Search customer or car..."
            style="padding-left:38px;width:280px;font-size:13px;" value="<?= htmlspecialchars($search) ?>">
        </div>
      </form>
    </div>

    <div class="table-card" style="padding:0;border:none;">
      <table class="admin-table">
        <thead>
          <tr>
            <th style="text-align:left">
              <?php
              if ($tab === 'reservations')
                echo 'Reservation ID';
              elseif ($tab === 'test_drives')
                echo 'Test Drive ID';
              else
                echo ($sub_tab === 'reservations' ? 'Reservation ID' : 'Test Drive ID');
              ?>
            </th>
            <th style="text-align:left;">Customer</th>
            <th style="text-align:left;">Car</th>
            <th style="text-align:left;">
              <?php
              if ($tab === 'reservations')
                echo 'Submitted At';
              elseif ($tab === 'test_drives')
                echo 'Test Drive At';
              else
                echo ($sub_tab === 'reservations' ? 'Rejected At' : 'Date');
              ?>
            </th>
            <th style="text-align:left;">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result && mysqli_num_rows($result) > 0):
            while ($row = mysqli_fetch_assoc($result)):
              $res_id = $row['reservation_id'];
              if (empty($row['snapshot_data'])) {
                $snap = [
                  'user_name' => $row['user_name'] ?? 'Unknown',
                  'user_phone' => $row['user_phone'] ?? '-',
                  'user_email' => $row['user_email'] ?? '-',
                  'user_ic' => $row['user_ic'] ?? '-',
                  'car_brand' => $row['car_brand'] ?? 'Unknown',
                  'car_model' => $row['car_model'] ?? '-',
                  'car_year' => $row['car_year'] ?? '-',
                  'car_origin' => $row['car_origin'] ?? '-',
                  'car_plate' => $row['car_plate'] ?? '-',
                  'car_image' => $row['car_image'] ?? '',
                  'car_variant' => $row['car_variant'] ?? '-',
                  'car_color' => $row['car_color'] ?? '-'
                ];
                $snap_json = json_encode($snap, JSON_UNESCAPED_UNICODE);
                mysqli_query($conn, "UPDATE reservations SET snapshot_data = '" . mysqli_real_escape_string($conn, $snap_json) . "' WHERE reservation_id = $res_id");
                foreach ($snap as $k => $v) {
                  $row[$k] = $v;
                }
              } else {
                $snap = json_decode($row['snapshot_data'], true);
                if (is_array($snap)) {
                  foreach ($snap as $k => $v) {
                    if ($v !== null && $v !== '') {
                      $row[$k] = $v;
                    }
                  }
                }
              }

              $row['clash_users'] = '';
              $row['suggested_time'] = '';
              if (!empty($row['preferred_test_drive_at']) && $row['preferred_test_drive_at'] !== '0000-00-00 00:00' && $tab === 'reservations') {
                $pref_time = $row['preferred_test_drive_at'];
                $clash_query = mysqli_query($conn, "
                      SELECT u.user_name
                      FROM test_drives t
                      JOIN reservations r ON t.reservation_id = r.reservation_id
                      JOIN users u ON r.user_id = u.user_id
                      WHERE t.test_drive_status = 'Scheduled'
                      AND ABS(TIMESTAMPDIFF(MINUTE, t.test_drive_at, '$pref_time')) <= 60
                  ");
                $clash_names = [];
                while ($c = mysqli_fetch_assoc($clash_query)) {
                  $clash_names[] = $c['user_name'];
                }
                if (!empty($clash_names)) {
                  $row['clash_users'] = implode(', ', $clash_names);
                  $row['suggested_time'] = date('Y-m-d\TH:i', strtotime($pref_time . ' +2 hours'));
                }
              }
              $display_name = !empty($row['user_name']) ? $row['user_name'] : 'Unknown User';
              $display_phone = !empty($row['user_phone']) ? $row['user_phone'] : '-';
              $display_brand = !empty($row['car_brand']) ? $row['car_brand'] : 'Unknown Car';
              $display_model = !empty($row['car_model']) ? $row['car_model'] : '-';
              $display_year = !empty($row['car_year']) ? $row['car_year'] : '-';
              $display_origin = !empty($row['car_origin']) ? $row['car_origin'] : '-';

              $date_display = '-';

              if ($tab === 'reservations') {
                $status_label = $row['reservation_status'];
                $status_slug = 'pending-viewing';

                if (!empty($row['reservation_created_at']) && $row['reservation_created_at'] !== '0000-00-00 00:00') {
                  $date_display = date('d M Y, g:ia', strtotime($row['reservation_created_at']));
                }
                $order_id = 'RES' . str_pad($row['reservation_id'], 3, '0', STR_PAD_LEFT);

              } elseif ($tab === 'test_drives') {
                $status_label = $row['test_drive_status'];
                $status_slug = strtolower($row['test_drive_status']);
                if (!empty($row['test_drive_at']) && $row['test_drive_at'] !== '0000-00-00 00:00') {
                  $date_display = date('d M Y, g:ia', strtotime($row['test_drive_at']));
                }
                $order_id = 'TD' . str_pad($row['test_drive_id'], 3, '0', STR_PAD_LEFT);

              } else {
                if ($sub_tab === 'reservations') {
                  $status_label = $row['reservation_status'];
                  $status_slug = 'rejected';
                  if (!empty($row['reservation_created_at']) && $row['reservation_created_at'] !== '0000-00-00 00:00') {
                    $date_display = date('d M Y, g:ia', strtotime($row['reservation_created_at']));
                  }
                  $order_id = 'RES' . str_pad($row['reservation_id'], 3, '0', STR_PAD_LEFT);
                } else {
                  $status_label = $row['test_drive_status'];
                  $status_slug = strtolower($row['test_drive_status']);
                  $date_ref = $row['test_drive_done_at'] ?? $row['test_drive_at'];
                  if (!empty($date_ref) && $date_ref !== '0000-00-00 00:00:00') {
                    $date_display = date('d M Y, g:ia', strtotime($date_ref));
                  }
                  $order_id = 'TD' . str_pad($row['test_drive_id'], 3, '0', STR_PAD_LEFT);
                }
              }
              $status_slug = strtolower(str_replace(' ', '-', $status_label));
              $row_json = json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
              ?>
              <tr class="data-row" onclick='openModal(<?= $row_json ?>, "<?= $tab ?>", "<?= $sub_tab ?>")'>
                <td style="text-align:left;padding-left:24px;color:#6b7280;font-weight:500;"><?= $order_id ?></td>
                <td>
                  <div style="font-weight:500;color:#111827;font-size:14px;">
                    <?= htmlspecialchars($display_name) ?>
                  </div>
                  <div style="font-size:12px;color:#6b7280;"><?= htmlspecialchars($display_phone) ?></div>
                </td>
                <td>
                  <div style="font-weight:500;color:#111827;font-size:14px;">
                    <?= htmlspecialchars($display_brand . ' ' . $display_model) ?>
                  </div>
                  <div style="font-size:12px;color:#6b7280;">
                    <?= htmlspecialchars($display_year . ' | ' . $display_origin) ?>
                  </div>
                </td>
                <td style="text-align:left;color:#4b5563;font-weight:500;"><?= $date_display ?></td>
                <td style="text-align:left;">
                  <div class="status-cell">
                    <span class="dot dot-<?= $status_slug ?>"></span>
                    <span class="text-<?= $status_slug ?>"><?= $status_label ?></span>
                  </div>
                </td>
              </tr>
            <?php endwhile; else: ?>
            <tr>
              <td colspan="5" style="text-align:center;padding:48px 0;color:#6b7280;font-size:14px;">No records found.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>

      <div class="pagination-container"
        style="padding:20px;display:flex;justify-content:space-between;align-items:center;border-top:1px solid #e5e7eb;">
        <div class="page-info" style="color:#6b7280;font-size:13px;">
          Showing Page <?= $page ?> of <?= $total_pages ?>
        </div>
        <?php if ($total_pages > 1):
          $base_q = "?tab=" . urlencode($tab) . ($tab === 'history' ? "&sub_tab=" . urlencode($sub_tab) : '') . "&search=" . urlencode($search);
          ?>
          <div class="page-controls" style="display:flex;gap:8px;">
            <a href="<?= $base_q ?>&page=<?= max(1, $page - 1) ?>" class="page-btn"><i class="fas fa-angle-left"></i>
              Prev</a>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
              <a href="<?= $base_q ?>&page=<?= $i ?>" class="page-btn <?= ($page == $i) ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="<?= $base_q ?>&page=<?= min($total_pages, $page + 1) ?>" class="page-btn">Next <i
                class="fas fa-angle-right"></i></a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <div id="splitModal" class="modal">
    <div class="modal-content" style="width:800px;">
      <h2 style="font-size:20px;margin-bottom:20px;">
        <i class="fas fa-file-alt" style="margin-right:8px;color:#3b82f6;"></i>
        <span id="modalTitleText">Details</span>
      </h2>

      <div class="layout-grid">
        <div class="info-card">
          <h3 style="font-size:14px;margin-bottom:10px;color:#111827;"><i class="fas fa-user-circle"></i> Customer</h3>
          <div class="grid-2">
            <div class="detail-item"><label>Name</label>
              <p id="detName">-</p>
            </div>
            <div class="detail-item"><label>Phone</label>
              <p id="detContact">-</p>
            </div>
            <div class="detail-item"><label>Email</label>
              <p id="detEmail">-</p>
            </div>
            <div class="detail-item"><label>IC</label>
              <p id="detIC">-</p>
            </div>
          </div>
          <div class="detail-item" style="margin-top:15px;">
            <label>Driving Licence (PDF)</label>
            <div style="display:flex;align-items:center;gap:12px;margin-top:5px;">
              <button type="button" id="btnViewLicence" onclick="togglePdf('frameLicence')" class="btn-export"
                style="padding:6px 12px;display:none;background:#f3f4f6;"><i class="fas fa-eye"
                  style="color:#2563eb;"></i> View Document</button>
              <span id="lblLicenceMissing" style="color:#ef4444;font-size:12px;font-weight:600;display:none;"><i
                  class="fas fa-times-circle"></i> Not Provided</span>
            </div>
            <iframe id="frameLicence" src=""
              style="width:100%;height:250px;display:none;border:1px solid #d1d5db;border-radius:6px;margin-top:10px;"></iframe>
          </div>
        </div>

        <div class="info-card">
          <h3 style="font-size:14px;margin-bottom:10px;color:#111827;"><i class="fas fa-car"></i> Car</h3>
          <div style="display:flex;gap:12px;margin-bottom:15px;">
            <img id="detCarImage" src="" alt="Car"
              style="width:100px;height:70px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;">
            <div style="flex:1;">
              <p id="detCarBrand" style="font-weight:700;color:#111827;margin:0;">-</p>
              <p id="detCarModel" style="color:#6b7280;font-size:13px;margin:0;">-</p>
            </div>
          </div>
          <div class="grid-2">
            <div class="detail-item"><label>Variant</label>
              <p id="detCarVariant">-</p>
            </div>
            <div class="detail-item"><label>Colour</label>
              <p id="detCarColor">-</p>
            </div>
            <div class="detail-item"><label>Year</label>
              <p id="detCarYear">-</p>
            </div>
            <div class="detail-item" id="detCarPlateWrap" style="display:none;">
              <label>Plate Number</label>
              <p id="detCarPlate" style="color:#dc2626;font-weight:700;">-</p>
            </div>
          </div>
        </div>
      </div>

      <div class="info-card" style="margin-bottom:20px;">
        <h3 style="font-size:14px;margin-bottom:10px;color:#111827;">
          <i class="fas fa-calendar-alt"></i> Status &amp; Schedule
        </h3>
        <div class="grid-2">
          <div class="detail-item"><label>Reservation Status</label>
            <p id="detResStatus" style="color:#3b82f6;">-</p>
          </div>
          <div class="detail-item"><label>Test Drive Status</label>
            <p id="detTDStatus" style="color:#3b82f6;">-</p>
          </div>
          <div class="detail-item"><label>Test Drive At</label>
            <p id="detTDAt">-</p>
          </div>
          <div class="detail-item"><label>Done / Cancelled At</label>
            <p id="detTDDone">-</p>
          </div>
        </div>

        <div id="scheduleWrap" style="margin-top:15px;display:none;"></div>

        <div id="reasonWrap" style="margin-top:15px;display:none;">
          <div class="detail-item">
            <label id="reasonLabel">Reason</label>
            <p id="detReason" style="color:#dc2626;">-</p>
          </div>
        </div>
      </div>

      <div
        style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px;padding-top:20px;border-top:1px solid #e5e7eb;">
        <button type="button" class="btn-export" onclick="closeModal()">Close</button>
        <button type="button" id="btnRejectRes" class="btn-export" onclick="rejectReservation()"
          style="color:#ef4444;border-color:#ef4444;display:none;">Reject</button>
        <button type="button" id="btnApproveRes" class="btn-add-blue" onclick="approveReservation()"
          style="display:none;border:none;background:#10b981;">Approve</button>
        <button type="button" id="btnCancelTD" class="btn-export" onclick="cancelTestDrive()"
          style="color:#ef4444;border-color:#ef4444;display:none;">Cancel Test Drive</button>
        <button type="button" id="btnMarkCompleted" class="btn-add-blue" onclick="markCompleted()"
          style="display:none;border:none;background:#10b981;">Mark Completed</button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../../JAVA SCRIPT/reservations.js?v=<?= time() ?>"></script>
</body>

</html>