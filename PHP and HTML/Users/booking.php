<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

require '../Config/database.php';
require '../Config/functions.php';


if (
    !isset($_SESSION['loggedin']) ||
    $_SESSION['loggedin'] !== true ||
    !isset($_SESSION['id']) ||
    strcasecmp($_SESSION['role'] ?? '', 'Customer') !== 0
) {
    header("Location: Auth/login.php");
    exit();

}

$user_id = (int) $_SESSION['id'];


$car_id = intval($_SESSION['booking_car_id'] ?? 0);

if ($car_id <= 0) {
    header("Location: index.php");
    exit();
}


$car_sql = "
SELECT
    c.*,
    cs.car_status_price AS car_price,
    cs.car_status_stock_quantity AS stock,
    ucd.car_plate,
    (SELECT car_image_url FROM car_image WHERE car_id = c.car_id LIMIT 1) AS car_image
FROM cars c
LEFT JOIN car_status cs ON cs.car_id = c.car_id
LEFT JOIN used_car_details ucd ON ucd.car_id = c.car_id
WHERE c.car_id = ?
LIMIT 1
";

$car_stmt = mysqli_prepare($conn, $car_sql);
mysqli_stmt_bind_param($car_stmt, "i", $car_id);
mysqli_stmt_execute($car_stmt);
$car_result = mysqli_stmt_get_result($car_stmt);
$car = mysqli_fetch_assoc($car_result);
mysqli_stmt_close($car_stmt);

if (!$car) {
    die("Selected vehicle not found.");
}

$is_used_car = (strcasecmp($car['car_origin'] ?? '', 'Used Car') === 0);
$car_price = floatval($car['car_price'] ?? 0);


$variants = [];
$colors = [];

$inv_q = mysqli_query(
    $conn,
    "SELECT variant, color_name
     FROM car_inventory
     WHERE car_id = $car_id
     ORDER BY inventory_id ASC"
);

if ($inv_q) {
    while ($r = mysqli_fetch_assoc($inv_q)) {
        if (!empty($r['variant']))
            $variants[$r['variant']] = $r['variant'];
        if (!empty($r['color_name']))
            $colors[$r['color_name']] = $r['color_name'];
    }
}

$variants = array_values($variants);
$colors = array_values($colors);

$default_variant = $variants[0] ?? '';
$default_color = $colors[0] ?? '';


$user_sql = "
SELECT
    user_name, user_email, user_phone, user_ic,
    COALESCE(user_address, '')  AS user_address,
    COALESCE(user_city, '')     AS user_city,
    COALESCE(user_state, '')    AS user_state,
    COALESCE(user_postcode, '') AS user_postcode
FROM users
WHERE user_id = ?
LIMIT 1
";

$user_stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($user_stmt);


$sys = [];
$sys_q = mysqli_query(
    $conn,
    "SELECT setting_key, setting_value
     FROM system_settings
     WHERE setting_key IN ('default_loan_rate', 'default_dp_percent')"
);
if ($sys_q) {
    while ($r = mysqli_fetch_assoc($sys_q)) {
        $sys[$r['setting_key']] = $r['setting_value'];
    }
}

$current_loan_rate = (float) ($sys['default_loan_rate'] ?? 3.0);
$current_dp_pct = (float) ($sys['default_dp_percent'] ?? 10.0);
$locked_loan_rate = isset($_POST['locked_loan_rate'])
    ? (float) $_POST['locked_loan_rate']
    : $current_loan_rate;

$locked_dp_pct = isset($_POST['locked_dp_pct'])
    ? (float) $_POST['locked_dp_pct']
    : $current_dp_pct;

if ($locked_loan_rate < 0 || $locked_loan_rate > 100)
    $locked_loan_rate = $current_loan_rate;
if ($locked_dp_pct < 0 || $locked_dp_pct > 100)
    $locked_dp_pct = $current_dp_pct;


$errors = [];

$res_name = $_POST['res_name'] ?? ($user['user_name'] ?? '');
$res_email = $_POST['res_email'] ?? ($user['user_email'] ?? '');
$res_phone = $_POST['res_phone'] ?? ($user['user_phone'] ?? '');
$res_ic = $_POST['res_ic'] ?? ($user['user_ic'] ?? '');
$res_address = $_POST['res_address'] ?? ($user['user_address'] ?? '');
$res_city = $_POST['res_city'] ?? ($user['user_city'] ?? '');
$res_state = $_POST['res_state'] ?? ($user['user_state'] ?? '');
$res_postcode = $_POST['res_postcode'] ?? ($user['user_postcode'] ?? '');

$car_variant = $is_used_car
    ? $default_variant
    : ($_POST['car_variant'] ?? $default_variant);

$car_color = $is_used_car
    ? $default_color
    : ($_POST['car_color'] ?? $default_color);

// Loan tenure
$loan_years = intval($_POST['loan_years'] ?? 5);
if (!in_array($loan_years, [5, 7, 9])) {
    $loan_years = 5;
}

$booking_fee = 500.00;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    if (empty(trim($res_name)))
        $errors[] = "Full Name is required.";
    if (empty(trim($res_email)) || !filter_var($res_email, FILTER_VALIDATE_EMAIL))
        $errors[] = "Valid email address is required.";
    if (empty(trim($res_phone)) || strlen(trim($res_phone)) < 9)
        $errors[] = "Valid phone number is required.";
    if (empty(trim($res_ic)))
        $errors[] = "IC / Passport Number is required.";
    if (empty(trim($res_address)))
        $errors[] = "Billing Address is required.";
    if (empty(trim($res_city)))
        $errors[] = "City is required.";
    if (empty(trim($res_state)))
        $errors[] = "State is required.";
    if (empty(trim($res_postcode)))
        $errors[] = "Postcode is required.";
    if (empty($car_variant))
        $errors[] = "Vehicle variant is required.";
    if (empty($car_color))
        $errors[] = "Vehicle color is required.";

    // --- duplicate check ---
    $dup_stmt = mysqli_prepare(
        $conn,
        "SELECT booking_id FROM bookings
         WHERE user_id = ? AND car_id = ?
         AND booking_status IN ('Pending','Approved')
         LIMIT 1"
    );
    mysqli_stmt_bind_param($dup_stmt, "ii", $user_id, $car_id);
    mysqli_stmt_execute($dup_stmt);
    $dup_res = mysqli_stmt_get_result($dup_stmt);
    if ($dup_res && mysqli_num_rows($dup_res) > 0) {
        $errors[] = "You already have an active booking for this vehicle.";
    }
    mysqli_stmt_close($dup_stmt);
    $file_fields = [
        'ic_document' => 'IC Document',
        'license_document' => 'Driving Licence',
        'payslip_document' => 'Latest Payslip',
        'bank_document' => 'Bank Statement (3 months)',
    ];

    $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];
    $allowed_mime = ['application/pdf', 'image/jpeg', 'image/png'];
    $max_size = 5 * 1024 * 1024;

    foreach ($file_fields as $field => $label) {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = "$label is required.";
            continue;
        }
        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "$label upload failed. Please try again.";
            continue;
        }
        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) {
            $errors[] = "$label must be PDF, JPG, or PNG.";
            continue;
        }
        if ($_FILES[$field]['size'] > $max_size) {
            $errors[] = "$label exceeds 5MB.";
            continue;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES[$field]['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed_mime)) {
            $errors[] = "$label has an invalid file type.";
        }
    }

    if (empty($errors)) {

        $target_dir = __DIR__ . '/../../uploads/documents/';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $uploaded_paths = []; // for rollback if any one fails
        $stored_urls = []; // for DB

        $rollback_files = function () use (&$uploaded_paths) {
            foreach ($uploaded_paths as $p) {
                if (file_exists($p))
                    @unlink($p);
            }
        };

        // Move all 4 files
        foreach ($file_fields as $field => $label) {
            $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
            $filename =
                $field . '_user' . $user_id .
                '_' . time() .
                '_' . bin2hex(random_bytes(4)) .
                '.' . $ext;

            $full_path = $target_dir . $filename;
            $url = '../../uploads/documents/' . $filename;

            if (!move_uploaded_file($_FILES[$field]['tmp_name'], $full_path)) {
                $rollback_files();
                die("Failed to save $label file.");
            }

            $uploaded_paths[] = $full_path;
            $stored_urls[$field] = $url;
        }

        $snapshot_data = [
            'user_name' => $res_name,
            'user_email' => $res_email,
            'user_phone' => $res_phone,
            'user_ic' => $res_ic,
            'user_address' => $res_address,
            'user_city' => $res_city,
            'user_state' => $res_state,
            'user_postcode' => $res_postcode,
            'car_brand' => $car['car_brand'],
            'car_model' => $car['car_model'],
            'car_year' => $car['car_year'],
            'car_origin' => $car['car_origin'],
            'car_price' => $car_price,
            'car_variant' => $car_variant,
            'car_color' => $car_color,
            'car_plate' => $car['car_plate'] ?? '',
            'car_image' => $car['car_image'] ?? '',

            // finance snapshot (so admin sees the exact rate user agreed to)
            'locked_loan_rate' => $locked_loan_rate,
            'locked_dp_pct' => $locked_dp_pct,
            'booking_fee' => $booking_fee,
            'loan_years' => $loan_years,
        ];

        $snapshot_json = json_encode($snapshot_data, JSON_UNESCAPED_UNICODE);
        mysqli_begin_transaction($conn);
        try {
            $ins_sql = "
            INSERT INTO bookings (
                user_id, car_id, booking_fee, installment_years,
                interest_rate, booking_status, snapshot_data, created_at
            ) VALUES (?, ?, ?, ?, ?, 'Pending', ?, NOW())
            ";

            $loan_years_str = (string) $loan_years; // enum stores as string
            $ins_stmt = mysqli_prepare($conn, $ins_sql);
            mysqli_stmt_bind_param(
                $ins_stmt,
                "iidsds",
                $user_id,
                $car_id,
                $booking_fee,
                $loan_years_str,
                $locked_loan_rate,
                $snapshot_json
            );
            if (!mysqli_stmt_execute($ins_stmt)) {
                throw new Exception("Booking insert failed: " . mysqli_stmt_error($ins_stmt));
            }
            $new_booking_id = mysqli_insert_id($conn);
            mysqli_stmt_close($ins_stmt);
            $doc_sql = "
            INSERT INTO loan_installment_documents (
                booking_id, ic_url, driving_license_url, payslip_url, bank_statement_url, uploaded_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
            ";
            $doc_stmt = mysqli_prepare($conn, $doc_sql);
            mysqli_stmt_bind_param(
                $doc_stmt,
                "issss",
                $new_booking_id,
                $stored_urls['ic_document'],
                $stored_urls['license_document'],
                $stored_urls['payslip_document'],
                $stored_urls['bank_document']
            );
            if (!mysqli_stmt_execute($doc_stmt)) {
                throw new Exception("Documents insert failed: " . mysqli_stmt_error($doc_stmt));
            }
            mysqli_stmt_close($doc_stmt);
            $upd_sql = "
            UPDATE users
            SET user_address = ?, user_city = ?, user_state = ?, user_postcode = ?
            WHERE user_id = ?
            ";
            $upd_stmt = mysqli_prepare($conn, $upd_sql);
            mysqli_stmt_bind_param(
                $upd_stmt,
                "ssssi",
                $res_address,
                $res_city,
                $res_state,
                $res_postcode,
                $user_id
            );
            mysqli_stmt_execute($upd_stmt);
            mysqli_stmt_close($upd_stmt);
            if ($is_used_car) {
                mysqli_query($conn, "UPDATE car_status SET car_status_status = 'Inactive' WHERE car_id = $car_id");
            } else {

                $variant_esc = mysqli_real_escape_string($conn, $car_variant);
                $color_esc = mysqli_real_escape_string($conn, $car_color);
                mysqli_query($conn, "UPDATE car_inventory SET quantity = GREATEST(quantity - 1, 0) WHERE car_id = $car_id AND IFNULL(variant,'') = '$variant_esc' AND color_name = '$color_esc' LIMIT 1");
                mysqli_query($conn, "UPDATE car_status SET car_status_stock_quantity = (SELECT IFNULL(SUM(quantity),0) FROM car_inventory WHERE car_id = $car_id) WHERE car_id = $car_id");
            }

            mysqli_commit($conn);
            try {
                trigger_new_booking_alert($conn, $new_booking_id, $res_name);
            } catch (Throwable $e) {
            }
            $_SESSION['booking_id'] = $new_booking_id;
            $_SESSION['pay_amount'] = $booking_fee;
            $_SESSION['pay_source'] = 'booking';
            $_SESSION['pay_label'] = 'Booking Fee';
            unset($_SESSION['booking_car_id']);

            header("Location: payment.php?id=" . $new_booking_id);
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $rollback_files();
            die($e->getMessage());
        }
    }
}

$estimated_dp = round($car_price * ($locked_dp_pct / 100), 2);
$loan_amount = max(0, $car_price - $booking_fee - $estimated_dp);
$total_with_int = $loan_amount * (1 + ($locked_loan_rate / 100) * $loan_years);
$monthly_payment = ($loan_years > 0) ? round($total_with_int / ($loan_years * 12), 2) : 0;

include 'Includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

<style>
    .booking-page {
        background: #f8fafc;
        min-height: calc(100vh - 80px);
        padding: 40px 20px 60px;
    }

    .booking-wrapper {
        max-width: 1150px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 1fr 1.5fr;
        gap: 24px;
    }

    @media(max-width:960px) {
        .booking-wrapper {
            grid-template-columns: 1fr;
        }
    }

    .page-heading {
        grid-column: 1 / -1;
        margin-bottom: 6px;
    }

    .page-heading h1 {
        font-size: 28px;
        font-weight: 700;
        color: #1e293b;
        letter-spacing: -0.5px;
        margin-bottom: 6px;
    }

    .page-heading p {
        color: #64748b;
        font-size: 14px;
    }

    .bk-card {
        background: #ffffff;
        border: 1px solid #f1f5f9;
        border-radius: 16px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03), 0 8px 24px rgba(0, 0, 0, 0.04);
        padding: 28px;
    }

    .left-col {
        display: flex;
        flex-direction: column;
        gap: 20px;
        position: sticky;
        top: 24px;
        align-self: flex-start;
    }

    @media(max-width:960px) {
        .left-col {
            position: static;
        }
    }

    /* Vehicle preview */
    .vehicle-preview img {
        width: 100%;
        height: 200px;
        object-fit: cover;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        margin-bottom: 16px;
    }

    .vehicle-preview h2 {
        font-size: 20px;
        color: #0f172a;
        font-weight: 700;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .vp-origin {
        color: #64748b;
        font-size: 12px;
        margin-bottom: 14px;
        display: inline-block;
        padding: 3px 10px;
        background: #f1f5f9;
        border-radius: 999px;
        font-weight: 500;
    }

    .vp-price {
        font-size: 22px;
        font-weight: 800;
        color: #dc2626;
        margin: 10px 0 16px;
    }

    .vp-spec {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        padding-top: 14px;
        border-top: 1px solid #f1f5f9;
    }

    .vp-spec-item label {
        font-size: 11px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 700;
        display: block;
        margin-bottom: 3px;
    }

    .vp-spec-item p {
        font-size: 13px;
        color: #1e293b;
        font-weight: 600;
    }

    .vp-spec-item .plate {
        color: #dc2626;
    }

    /* Summary box */
    .summary-card h3 {
        font-size: 13px;
        color: #1e293b;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        font-weight: 700;
        margin-bottom: 14px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 9px 0;
        font-size: 13px;
        color: #475569;
    }

    .summary-row strong {
        color: #1e293b;
        font-weight: 700;
    }

    .summary-row .tag {
        font-size: 10px;
        padding: 2px 7px;
        border-radius: 999px;
        margin-left: 6px;
        font-weight: 700;
    }

    .tag-due {
        background: #fef3c7;
        color: #92400e;
    }

    .tag-later {
        background: #e0e7ff;
        color: #3730a3;
    }

    .summary-row.total {
        border-top: 1px dashed #cbd5e1;
        margin-top: 8px;
        padding-top: 14px;
    }

    .summary-row.total .lbl {
        font-size: 12px;
        font-weight: 700;
        color: #2563eb;
    }

    .summary-row.total .lbl small {
        display: block;
        color: #94a3b8;
        font-weight: 500;
        font-size: 10px;
    }

    .summary-row.total .val {
        font-size: 20px;
        font-weight: 800;
        color: #2563eb;
    }

    /* Form sections */
    .form-section {
        margin-bottom: 24px;
    }

    .form-section:last-of-type {
        margin-bottom: 0;
    }

    .form-section h3 {
        font-size: 13px;
        color: #1e293b;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        font-weight: 700;
        margin-bottom: 14px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .form-section h3 i {
        color: #1e293b;
        font-size: 14px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        margin-bottom: 14px;
    }

    .form-row.single {
        grid-template-columns: 1fr;
    }

    .form-row.three {
        grid-template-columns: 2fr 1fr 1fr;
    }

    @media(max-width:600px) {

        .form-row,
        .form-row.three {
            grid-template-columns: 1fr;
        }
    }

    .form-group label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .form-input {
        width: 100%;
        padding: 12px 14px;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        font-family: 'Poppins', sans-serif;
        background: #ffffff;
        color: #1e293b;
        transition: 0.2s;
        outline: none;
    }

    .form-input:focus {
        border-color: #1e293b;
        box-shadow: 0 0 0 3px rgba(30, 41, 59, 0.08);
    }

    .form-input[readonly],
    .form-input:disabled {
        background: #f8fafc;
        color: #475569;
        cursor: not-allowed;
    }

    select.form-input {
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'><path fill='%2364748b' d='M6 8L0 0h12z'/></svg>");
        background-repeat: no-repeat;
        background-position: right 14px center;
        padding-right: 36px;
    }

    .locked-hint {
        font-size: 11px;
        color: #94a3b8;
        margin-top: 4px;
        font-style: italic;
    }

    /* File upload */
    .file-upload {
        border: 2px dashed #cbd5e1;
        border-radius: 10px;
        padding: 18px;
        text-align: center;
        cursor: pointer;
        transition: 0.2s;
        background: #f8fafc;
        position: relative;
        display: block;
    }

    .file-upload:hover {
        border-color: #1e293b;
        background: #f1f5f9;
    }

    .file-upload input[type="file"] {
        position: absolute;
        inset: 0;
        opacity: 0;
        cursor: pointer;
    }

    .file-upload .icon {
        font-size: 24px;
        color: #94a3b8;
        margin-bottom: 6px;
    }

    .file-upload .label-txt {
        color: #64748b;
        font-size: 12px;
        font-weight: 600;
    }

    .file-upload .helper {
        font-size: 10px;
        color: #94a3b8;
        margin-top: 3px;
    }

    .file-upload .file-name {
        font-weight: 600;
        color: #16a34a;
        margin-top: 6px;
        font-size: 12px;
        word-break: break-all;
    }

    /* Submit button */
    .btn-submit {
        width: 100%;
        background: #1e293b;
        color: white;
        border: none;
        padding: 16px;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
        margin-top: 10px;
        font-family: 'Poppins', sans-serif;
        box-shadow: 0 4px 6px -1px rgba(30, 41, 59, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-submit:hover {
        background: #0f172a;
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(30, 41, 59, 0.25);
    }

    /* Error box */
    .error-box {
        grid-column: 1 / -1;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-left: 4px solid #ef4444;
        color: #991b1b;
        padding: 14px 18px;
        border-radius: 10px;
        margin-bottom: 6px;
    }

    .error-box p {
        margin: 3px 0;
        font-size: 13px;
        font-weight: 500;
    }

    .error-box p::before {
        content: "\f071";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        margin-right: 8px;
        color: #dc2626;
    }

    .fee-banner {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: white;
        padding: 14px 18px;
        border-radius: 10px;
        margin-bottom: 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .fee-banner .ttl {
        font-size: 11px;
        opacity: 0.7;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }

    .fee-banner .amt {
        font-size: 24px;
        font-weight: 800;
    }
</style>

<div class="booking-page">
    <div class="booking-wrapper">

        <div class="page-heading">
            <h1>Vehicle Financing Application</h1>
            <p>Complete your booking, upload financing documents, and pay the RM 500 booking fee to begin.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <?php foreach ($errors as $err): ?>
                    <p><?= htmlspecialchars($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ===== LEFT: Vehicle + Summary ===== -->
        <div class="left-col">

            <div class="bk-card vehicle-preview">
                <img src="<?= !empty($car['car_image'])
                    ? htmlspecialchars($car['car_image'])
                    : 'https://via.placeholder.com/600x400?text=Vehicle' ?>" alt="Vehicle">
                <h2><?= htmlspecialchars($car['car_brand'] . ' ' . $car['car_model']) ?></h2>
                <span class="vp-origin"><?= htmlspecialchars($car['car_origin']) ?></span>

                <div class="vp-price">RM <?= number_format($car_price, 2) ?></div>

                <div class="vp-spec">
                    <div class="vp-spec-item">
                        <label>Year</label>
                        <p><?= htmlspecialchars($car['car_year']) ?></p>
                    </div>
                    <div class="vp-spec-item">
                        <label>Default Variant</label>
                        <p><?= htmlspecialchars($default_variant ?: '-') ?></p>
                    </div>
                    <div class="vp-spec-item">
                        <label>Default Color</label>
                        <p><?= htmlspecialchars($default_color ?: '-') ?></p>
                    </div>
                    <?php if ($is_used_car && !empty($car['car_plate'])): ?>
                        <div class="vp-spec-item">
                            <label>Plate Number</label>
                            <p class="plate"><?= htmlspecialchars($car['car_plate']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bk-card summary-card">
                <h3><i class="fas fa-receipt"></i> Financial Summary</h3>

                <div class="fee-banner">
                    <div>
                        <div class="ttl">Pay Now (Booking Fee)</div>
                        <div style="font-size:11px;opacity:0.6;">Non-refundable processing fee</div>
                    </div>
                    <div class="amt">RM <?= number_format($booking_fee, 2) ?></div>
                </div>

                <div class="summary-row">
                    <span>Vehicle Price</span>
                    <strong>RM <?= number_format($car_price, 2) ?></strong>
                </div>
                <div class="summary-row">
                    <span>Booking Fee <span class="tag tag-due">Due Now</span></span>
                    <strong>RM <?= number_format($booking_fee, 2) ?></strong>
                </div>
                <div class="summary-row">
                    <span>Down Payment (<?= $locked_dp_pct ?>%) <span class="tag tag-later">After Approval</span></span>
                    <strong>RM <?= number_format($estimated_dp, 2) ?></strong>
                </div>
                <div class="summary-row">
                    <span>Loan Amount</span>
                    <strong>RM <span id="sum_loan"><?= number_format($loan_amount, 2) ?></span></strong>
                </div>
                <div class="summary-row">
                    <span>Interest Rate</span>
                    <strong><?= number_format($locked_loan_rate, 2) ?>% p.a.</strong>
                </div>

                <div class="summary-row total">
                    <div class="lbl">
                        Estimated Monthly
                        <small><span id="sum_years"><?= $loan_years ?></span> Years @
                            <?= number_format($locked_loan_rate, 2) ?>%</small>
                    </div>
                    <div class="val">RM <span id="sum_monthly"><?= number_format($monthly_payment, 2) ?></span></div>
                </div>
            </div>

        </div>

        <!-- ===== RIGHT: Form ===== -->
        <div class="bk-card">
            <form method="POST" enctype="multipart/form-data" autocomplete="off">

                <!-- Lock current page-load rate so admin can't change it on us mid-form -->
                <input type="hidden" name="locked_loan_rate" value="<?= htmlspecialchars($locked_loan_rate) ?>">
                <input type="hidden" name="locked_dp_pct" value="<?= htmlspecialchars($locked_dp_pct) ?>">

                <!-- 1. Customer Info -->
                <div class="form-section">
                    <h3><i class="fas fa-user-circle"></i> 1. Customer Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="res_name" class="form-input"
                                value="<?= htmlspecialchars($res_name) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="res_email" class="form-input"
                                value="<?= htmlspecialchars($res_email) ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="res_phone" class="form-input"
                                value="<?= htmlspecialchars($res_phone) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>IC / Passport Number</label>
                            <input type="text" name="res_ic" class="form-input" value="<?= htmlspecialchars($res_ic) ?>"
                                required>
                        </div>
                    </div>
                </div>

                <!-- 2. Billing Address -->
                <div class="form-section">
                    <h3><i class="fas fa-map-marker-alt"></i> 2. Billing Address <span
                            style="color:#dc2626;font-size:11px;margin-left:6px;">* Required for loan approval</span>
                    </h3>

                    <div class="form-row single">
                        <div class="form-group">
                            <label>Street Address</label>
                            <input type="text" name="res_address" class="form-input"
                                placeholder="No. 12, Jalan ABC, Taman XYZ" value="<?= htmlspecialchars($res_address) ?>"
                                required>
                        </div>
                    </div>

                    <div class="form-row three">
                        <div class="form-group">
                            <label>City</label>
                            <input type="text" name="res_city" class="form-input"
                                value="<?= htmlspecialchars($res_city) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>State</label>
                            <input type="text" name="res_state" class="form-input"
                                value="<?= htmlspecialchars($res_state) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Postcode</label>
                            <input type="text" name="res_postcode" class="form-input"
                                value="<?= htmlspecialchars($res_postcode) ?>" required>
                        </div>
                    </div>
                </div>

                <!-- 3. Vehicle Configuration -->
                <div class="form-section">
                    <h3><i class="fas fa-car"></i> 3. Vehicle Configuration</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Variant</label>
                            <?php if ($is_used_car || count($variants) <= 1): ?>
                                <input type="text" class="form-input"
                                    value="<?= htmlspecialchars($default_variant ?: '-') ?>" readonly>
                                <input type="hidden" name="car_variant" value="<?= htmlspecialchars($default_variant) ?>">
                                <div class="locked-hint">
                                    <?= $is_used_car ? 'Used car — fixed to this unit.' : 'Only one variant available.' ?>
                                </div>
                            <?php else: ?>
                                <select name="car_variant" class="form-input" required>
                                    <?php foreach ($variants as $v): ?>
                                        <option value="<?= htmlspecialchars($v) ?>" <?= $car_variant === $v ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($v) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label>Color</label>
                            <?php if ($is_used_car || count($colors) <= 1): ?>
                                <input type="text" class="form-input" value="<?= htmlspecialchars($default_color ?: '-') ?>"
                                    readonly>
                                <input type="hidden" name="car_color" value="<?= htmlspecialchars($default_color) ?>">
                                <div class="locked-hint">
                                    <?= $is_used_car ? 'Used car — fixed to this unit.' : 'Only one color available.' ?>
                                </div>
                            <?php else: ?>
                                <select name="car_color" class="form-input" required>
                                    <?php foreach ($colors as $cl): ?>
                                        <option value="<?= htmlspecialchars($cl) ?>" <?= $car_color === $cl ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cl) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 4. Financing -->
                <div class="form-section">
                    <h3><i class="fas fa-coins"></i> 4. Loan Tenure</h3>
                    <div class="form-row single">
                        <div class="form-group">
                            <label>Repayment Period</label>
                            <select name="loan_years" id="loan_years" class="form-input" required>
                                <option value="5" <?= $loan_years === 5 ? 'selected' : '' ?>>5 Years</option>
                                <option value="7" <?= $loan_years === 7 ? 'selected' : '' ?>>7 Years</option>
                                <option value="9" <?= $loan_years === 9 ? 'selected' : '' ?>>9 Years</option>
                            </select>
                            <div class="locked-hint">
                                Interest rate <?= number_format($locked_loan_rate, 2) ?>% p.a. is locked to your
                                application.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. Documents -->
                <div class="form-section">
                    <h3><i class="fas fa-folder-open"></i> 5. Loan Application Documents <span
                            style="color:#dc2626;font-size:11px;margin-left:6px;">All 4 required</span></h3>

                    <div class="form-row">
                        <?php
                        $docs = [
                            ['ic_document', 'IC Document', 'fa-id-card'],
                            ['license_document', 'Driving Licence', 'fa-id-badge'],
                            ['payslip_document', 'Latest Payslip', 'fa-file-invoice-dollar'],
                            ['bank_document', 'Bank Statement (3mo)', 'fa-university'],
                        ];
                        foreach ($docs as $d):
                            [$field, $label, $icon] = $d;
                            ?>
                            <div class="form-group">
                                <label><?= $label ?></label>
                                <label class="file-upload">
                                    <input type="file" name="<?= $field ?>" class="doc-input"
                                        data-target="fname_<?= $field ?>" accept=".pdf,.jpg,.jpeg,.png" required>
                                    <div class="icon"><i class="fas <?= $icon ?>"></i></div>
                                    <div class="label-txt">Click to upload</div>
                                    <div class="file-name" id="fname_<?= $field ?>"></div>
                                    <div class="helper">PDF / JPG / PNG · Max 5MB</div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" name="submit_booking" value="1" class="btn-submit">
                    <i class="fas fa-credit-card"></i>
                    Confirm &amp; Pay RM <?= number_format($booking_fee, 2) ?> Booking Fee
                </button>
            </form>
        </div>

    </div>
</div>

<script>
    // Live financial recalculation when loan tenure changes
    const carPrice = <?= json_encode($car_price) ?>;
    const bookFee = <?= json_encode($booking_fee) ?>;
    const dpPct = <?= json_encode($locked_dp_pct) ?>;
    const loanRate = <?= json_encode($locked_loan_rate) ?>;

    const estDP = carPrice * (dpPct / 100);
    const loanAmt = Math.max(0, carPrice - bookFee - estDP);

    const fmt = n => parseFloat(n).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function recalc() {
        const years = parseInt(document.getElementById('loan_years').value) || 5;
        const total = loanAmt * (1 + (loanRate / 100) * years);
        const monthly = years > 0 ? total / (years * 12) : 0;
        document.getElementById('sum_loan').textContent = fmt(loanAmt);
        document.getElementById('sum_monthly').textContent = fmt(monthly);
        document.getElementById('sum_years').textContent = years;
    }
    document.getElementById('loan_years').addEventListener('change', recalc);

    // File name preview
    document.querySelectorAll('.doc-input').forEach(input => {
        input.addEventListener('change', e => {
            const f = e.target.files[0];
            const out = document.getElementById(e.target.dataset.target);
            if (f) {
                if (f.size > 5 * 1024 * 1024) {
                    alert('File exceeds 5MB limit.');
                    e.target.value = '';
                    out.textContent = '';
                    return;
                }
                out.textContent = '✓ ' + f.name + ' (' + (f.size / 1024).toFixed(1) + ' KB)';
            } else {
                out.textContent = '';
            }
        });
    });
</script>

<?php include 'Includes/footer.php'; ?>