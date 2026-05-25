<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

require '../Config/database.php';

// ======================================================
// CONSTANTS
// ======================================================

define('INSURANCE_FEE',  3000.00);   // RM 3000 fixed insurance
define('PLATE_REG_FEE',  10.00);     // RM 10 fixed plate registration
define('TEMPLATE_PDF',   '../documents/Motor_Insurance_Contract_Summary.pdf');

// ======================================================
// SESSION CHECK
// ======================================================

if (
    !isset($_SESSION['loggedin']) ||
    $_SESSION['loggedin'] !== true ||
    !isset($_SESSION['id']) ||
    strcasecmp($_SESSION['role'] ?? '', 'Customer') !== 0
) {
    header("Location: Auth/login.php");
    exit();
}

$user_id    = (int) $_SESSION['id'];
$booking_id = intval($_GET['id'] ?? $_POST['booking_id'] ?? 0);

// ======================================================
// FRIENDLY ERROR PAGE
// ======================================================

function show_error_page($title, $message, $btn_text = 'Return Home', $btn_href = 'index.php')
{
    include 'Includes/header.php';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">';
    echo '<div style="min-height:60vh;display:flex;align-items:center;justify-content:center;padding:40px 20px;background:#f8fafc;">
        <div style="max-width:480px;background:#fff;border:1px solid #f1f5f9;border-radius:18px;padding:40px;text-align:center;box-shadow:0 8px 24px rgba(0,0,0,0.05);">
            <div style="width:70px;height:70px;background:#fef3c7;color:#d97706;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 20px;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2 style="color:#1e293b;font-size:22px;margin-bottom:10px;">' . htmlspecialchars($title) . '</h2>
            <p style="color:#64748b;font-size:14px;line-height:1.6;margin-bottom:24px;">' . htmlspecialchars($message) . '</p>
            <a href="' . htmlspecialchars($btn_href) . '" style="display:inline-block;background:#1e293b;color:white;padding:12px 28px;border-radius:10px;text-decoration:none;font-weight:600;">' . htmlspecialchars($btn_text) . '</a>
        </div>
    </div>';
    include 'Includes/footer.php';
    exit;
}

if ($booking_id <= 0) {
    show_error_page(
        'Invalid Booking Reference',
        'No valid booking was specified for down payment processing.',
        'View My Activity',
        'view_status.php'
    );
}

// ======================================================
// FETCH BOOKING + DOWN PAYMENT
// ======================================================

$sql = "
SELECT
    b.*,
    c.car_brand, c.car_model, c.car_year, c.car_origin,
    cs.car_status_price AS car_price_live,
    (SELECT car_image_url FROM car_image WHERE car_id = b.car_id LIMIT 1) AS car_image_live,
    dp.id           AS dp_id,
    dp.dp_amount,
    dp.dp_status,
    dp.insurance_pdf_url,
    dp.insurance_fee,
    dp.plate_number,
    dp.plate_option,
    dp.plate_registration_fee,
    dp.paid_at      AS dp_paid_at
FROM bookings b
LEFT JOIN cars c ON c.car_id = b.car_id
LEFT JOIN car_status cs ON cs.car_id = b.car_id
LEFT JOIN down_payments dp ON dp.booking_id = b.booking_id
WHERE b.booking_id = ? AND b.user_id = ?
LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $booking_id, $user_id);
mysqli_stmt_execute($stmt);
$booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$booking) {
    show_error_page(
        'Unauthorized Access',
        'This booking does not belong to your account, or it no longer exists.',
        'View My Activity',
        'view_status.php'
    );
}

// ======================================================
// PRECONDITIONS
// ======================================================

if ($booking['booking_status'] !== 'Approved') {
    show_error_page(
        'Booking Not Yet Approved',
        'Your booking application is still under review. The down payment will be available once approved.',
        'View My Activity',
        'view_status.php'
    );
}
if (empty($booking['dp_id'])) {
    show_error_page(
        'Down Payment Not Generated',
        'Your down payment record has not been created yet. Please contact support.',
        'View My Activity',
        'view_status.php'
    );
}
if ($booking['dp_status'] !== 'Pending') {
    show_error_page(
        'Down Payment Already Processed',
        'This down payment has already been submitted or processed. View its status in My Activity.',
        'View My Activity',
        'view_status.php'
    );
}
if (!empty($booking['dp_paid_at'])) {
    show_error_page(
        'Down Payment Already Paid',
        'You have already submitted your down payment. Awaiting admin verification.',
        'View My Activity',
        'view_status.php'
    );
}

// ======================================================
// SNAPSHOT + DERIVED FIELDS
// ======================================================

$snap = json_decode($booking['snapshot_data'] ?: '{}', true);
if (!is_array($snap)) $snap = [];

$car_brand   = $snap['car_brand']   ?? $booking['car_brand']   ?? '';
$car_model   = $snap['car_model']   ?? $booking['car_model']   ?? '';
$car_year    = $snap['car_year']    ?? $booking['car_year']    ?? '';
$car_origin  = $snap['car_origin']  ?? $booking['car_origin']  ?? '';
$car_image   = $snap['car_image']   ?? $booking['car_image_live'] ?? 'https://via.placeholder.com/600x400.png?text=Vehicle';
$car_variant = $snap['car_variant'] ?? '-';
$car_color   = $snap['car_color']   ?? '-';
$car_price   = floatval($snap['car_price'] ?? $booking['car_price_live'] ?? 0);

$dp_amount   = floatval($booking['dp_amount']);
$booking_fee = floatval($booking['booking_fee']);
$is_used_car = (strcasecmp($car_origin, 'Used Car') === 0);

// Loan amount calculation (for display)
$loan_amount = max(0, $car_price - $booking_fee - $dp_amount);

// ======================================================
// PROCESS POST
// ======================================================

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {

    // --- Validate plate (only 'new' or 'used' allowed; no custom) ---
    $plate_option = $_POST['plate_option'] ?? '';
    if (!in_array($plate_option, ['new', 'used'])) {
        $errors[] = "Please select a valid plate option.";
    }
    if ($plate_option === 'used' && !$is_used_car) {
        $errors[] = "Only used cars can keep an existing plate.";
    }

    // Plate fee
    $plate_fee = ($plate_option === 'used') ? 0.00 : PLATE_REG_FEE;

    // --- Validate insurance upload ---
    $insurance_db_url = '';
    if (!isset($_FILES['insurance_pdf']) || $_FILES['insurance_pdf']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Please upload your signed insurance document.";
    } else {
        $f = $_FILES['insurance_pdf'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $allowed_ext  = ['pdf', 'jpg', 'jpeg', 'png'];
        $allowed_mime = ['application/pdf', 'image/jpeg', 'image/png'];
        $mime = mime_content_type($f['tmp_name']);

        if (!in_array($ext, $allowed_ext)) {
            $errors[] = "Insurance file must be PDF, JPG, or PNG.";
        } elseif (!in_array($mime, $allowed_mime)) {
            $errors[] = "Insurance file content is invalid.";
        } elseif ($f['size'] > 5 * 1024 * 1024) {
            $errors[] = "Insurance file exceeds 5MB.";
        }
    }

    // --- Validate card ---
    $card_number = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $card_name   = trim($_POST['card_name'] ?? '');
    $card_expiry = trim($_POST['card_expiry'] ?? '');
    $card_cvv    = preg_replace('/\D/', '', $_POST['card_cvv'] ?? '');

    if (strlen($card_number) !== 16) $errors[] = "Card number must be 16 digits.";
    if (empty($card_name) || !preg_match('/^[A-Za-z\s\.\-]{2,}$/', $card_name)) {
        $errors[] = "Valid name on card is required.";
    }
    if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $card_expiry, $m)) {
        $errors[] = "Card expiry must be MM/YY.";
    } else {
        $em = (int)$m[1]; $ey = 2000 + (int)$m[2];
        $ny = (int)date('Y'); $nm = (int)date('n');
        if ($ey < $ny || ($ey === $ny && $em < $nm)) $errors[] = "Card has expired.";
    }
    if (strlen($card_cvv) !== 3) $errors[] = "CVV must be 3 digits.";

    // --- Process if no errors ---
    if (empty($errors)) {

        $total_amount = $dp_amount + INSURANCE_FEE + $plate_fee;

        // Save insurance file FIRST (outside transaction so failed upload doesn't block rollback)
        $insurance_dir_fs = __DIR__ . '/../../uploads/insurance/';
        if (!is_dir($insurance_dir_fs)) @mkdir($insurance_dir_fs, 0777, true);

        $ext_save = strtolower(pathinfo($_FILES['insurance_pdf']['name'], PATHINFO_EXTENSION));
        $filename = 'insurance_BK' . $booking_id . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext_save;
        $target_fs = $insurance_dir_fs . $filename;
        $insurance_db_url = '../../uploads/insurance/' . $filename;

        if (!move_uploaded_file($_FILES['insurance_pdf']['tmp_name'], $target_fs)) {
            $errors[] = "Failed to save insurance document. Please try again.";
        } else {

            mysqli_begin_transaction($conn);
            try {
                $receipt_num = 'DP-' . date('Ymd') . '-' . str_pad($booking_id, 4, '0', STR_PAD_LEFT);
                $pay_ref     = 'TXN-' . strtoupper(bin2hex(random_bytes(5)));
                $last4       = substr($card_number, -4);
                $remarks     = sprintf(
                    'Down Payment + Insurance (RM %s) + Plate Reg (RM %s) (Card ending **** %s)',
                    number_format(INSURANCE_FEE, 2),
                    number_format($plate_fee, 2),
                    $last4
                );

                // UPDATE down_payments
                // NOTE: plate_number is NOT set here - admin assigns it later
                $upd = mysqli_prepare(
                    $conn,
                    "UPDATE down_payments
                     SET insurance_pdf_url      = ?,
                         insurance_fee          = ?,
                         plate_registration_fee = ?,
                         plate_option           = ?,
                         paid_at                = NOW(),
                         dp_receipt_number      = ?
                     WHERE id = ?"
                );
                $ins_fee_v   = INSURANCE_FEE;
                $plate_fee_v = $plate_fee;
                $dp_id_v     = (int) $booking['dp_id'];
                mysqli_stmt_bind_param(
                    $upd,
                    "sddssi",
                    $insurance_db_url,
                    $ins_fee_v,
                    $plate_fee_v,
                    $plate_option,
                    $receipt_num,
                    $dp_id_v
                );
                if (!mysqli_stmt_execute($upd)) {
                    throw new Exception('DP update failed: ' . mysqli_stmt_error($upd));
                }
                mysqli_stmt_close($upd);

                // INSERT payment
                $ins = mysqli_prepare(
                    $conn,
                    "INSERT INTO payments
                     (payment_type, reference_id, payment_amount, payment_status,
                      receipt_number, payment_reference, remarks, payment_date, created_at)
                     VALUES ('Down Payment', ?, ?, 'Paid', ?, ?, ?, NOW(), NOW())"
                );
                mysqli_stmt_bind_param(
                    $ins,
                    "idsss",
                    $booking_id,
                    $total_amount,
                    $receipt_num,
                    $pay_ref,
                    $remarks
                );
                if (!mysqli_stmt_execute($ins)) {
                    throw new Exception('Payment insert failed: ' . mysqli_stmt_error($ins));
                }
                $payment_id = mysqli_insert_id($conn);
                mysqli_stmt_close($ins);

                mysqli_commit($conn);

                header("Location: payment_confirm.php?id=" . $payment_id);
                exit();

            } catch (Exception $e) {
                mysqli_rollback($conn);
                // Cleanup uploaded file if DB failed
                if (file_exists($target_fs)) @unlink($target_fs);
                $errors[] = "Payment processing failed. Please try again.";
            }
        }
    }
}

include 'Includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

<style>
.dp-page{
    background:#f8fafc;
    min-height:calc(100vh - 80px);
    padding:40px 20px 60px;
}
.dp-wrapper{
    max-width:1100px;
    margin:0 auto;
    display:grid;
    grid-template-columns:1fr 1.3fr;
    gap:24px;
}
@media(max-width:900px){ .dp-wrapper{ grid-template-columns:1fr; } }

.page-heading{ grid-column:1 / -1; margin-bottom:6px; }
.page-heading h1{
    font-size:28px; font-weight:700; color:#1e293b;
    letter-spacing:-0.5px; margin-bottom:6px;
}
.page-heading p{ color:#64748b; font-size:14px; }

.dp-card{
    background:#fff;
    border:1px solid #f1f5f9;
    border-radius:16px;
    box-shadow:0 1px 2px rgba(0,0,0,0.03), 0 8px 24px rgba(0,0,0,0.04);
    padding:24px;
}

.left-col{
    display:flex; flex-direction:column; gap:16px;
    position:sticky; top:24px; align-self:flex-start;
}
@media(max-width:900px){ .left-col{ position:static; } }

.vehicle-preview img{
    width:100%; height:160px; object-fit:cover;
    border-radius:12px; border:1px solid #e2e8f0; margin-bottom:12px;
}
.vehicle-preview h2{
    font-size:17px; color:#0f172a; font-weight:700;
    margin-bottom:4px; text-transform:uppercase;
}
.vp-origin{
    color:#64748b; font-size:11px; margin-bottom:6px;
    display:inline-block; padding:3px 10px;
    background:#f1f5f9; border-radius:999px; font-weight:500;
}

.summary-card h3{
    font-size:12px; color:#1e293b; text-transform:uppercase;
    letter-spacing:0.8px; font-weight:700; margin-bottom:12px;
    padding-bottom:8px; border-bottom:2px solid #f1f5f9;
    display:flex; align-items:center; gap:8px;
}
.summary-row{
    display:flex; justify-content:space-between;
    padding:6px 0; font-size:13px; color:#475569;
}
.summary-row strong{ color:#1e293b; font-weight:700; }
.summary-row.deduction strong{ color:#16a34a; }
.summary-row.cost strong{ color:#dc2626; }

.summary-row.subtotal{
    border-top:2px dashed #cbd5e1;
    padding-top:10px; margin-top:4px; font-size:14px;
}
.summary-row.subtotal span{ font-weight:700; color:#1e293b; }
.summary-row.subtotal strong{
    color:#2563eb; font-size:18px; font-weight:800;
}
.summary-row.total{
    border-top:2px dashed #cbd5e1;
    padding-top:10px; margin-top:4px; font-size:14px;
}
.summary-row.total span:first-child{ font-weight:700; color:#1e293b; }
.summary-row.total strong{
    color:#dc2626; font-size:20px; font-weight:800;
}

.book-id-pill{
    display:inline-block; background:#1e293b; color:#fff;
    padding:4px 12px; border-radius:8px;
    font-family:monospace; font-size:11px;
    font-weight:700; margin-top:4px; letter-spacing:0.5px;
}

.amount-banner{
    background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%);
    color:#fff; padding:24px; border-radius:14px;
    margin-bottom:26px; text-align:center;
}
.amount-banner .lbl{
    font-size:11px; text-transform:uppercase;
    letter-spacing:1px; opacity:0.7; margin-bottom:6px;
}
.amount-banner .amt{
    font-size:42px; font-weight:800; letter-spacing:-1px;
}
.amount-banner .sub{ font-size:12px; opacity:0.6; margin-top:4px; }

.form-section{ margin-bottom:24px; }
.form-section h3{
    font-size:13px; color:#1e293b; text-transform:uppercase;
    letter-spacing:0.8px; font-weight:700; margin-bottom:14px;
    padding-bottom:10px; border-bottom:2px solid #f1f5f9;
    display:flex; align-items:center; gap:8px;
}

/* Insurance section */
.template-download{
    background:#eff6ff; border:1px solid #bfdbfe;
    border-radius:10px; padding:14px 18px;
    display:flex; justify-content:space-between; align-items:center;
    margin-bottom:14px; gap:14px; flex-wrap:wrap;
}
.template-download .info{ flex:1; min-width:200px; }
.template-download .info strong{
    color:#1e3a8a; font-size:14px; display:block; margin-bottom:3px;
}
.template-download .info p{ color:#475569; font-size:12px; line-height:1.5; margin:0; }
.template-download a{
    background:#1e293b; color:#fff; padding:10px 18px;
    border-radius:8px; text-decoration:none; font-size:13px;
    font-weight:600; white-space:nowrap;
    display:inline-flex; align-items:center; gap:6px;
}
.template-download a:hover{ background:#0f172a; }

.upload-zone {
    display: block; 
    border: 2px dashed #cbd5e1; 
    border-radius: 12px;
    padding: 24px; 
    text-align: center; 
    background: #f8fafc;
    transition: 0.2s; 
    cursor: pointer;
}
.upload-zone:hover{ border-color:#1e293b; background:#f1f5f9; }
.upload-zone.has-file{ border-style:solid; border-color:#16a34a; background:#f0fdf4; }
.upload-zone i.up-ic{ font-size:32px; color:#64748b; margin-bottom:8px; }
.upload-zone.has-file i.up-ic{ color:#16a34a; }
.upload-zone .uz-text{ font-size:13px; color:#475569; font-weight:600; margin-bottom:4px; }
.upload-zone .uz-sub{ font-size:11px; color:#94a3b8; }
.upload-zone input[type="file"]{ display:none; }
.upload-zone .file-name{
    background:#fff; padding:6px 12px; border-radius:6px;
    margin-top:10px; font-size:12px; color:#1e293b;
    border:1px solid #e2e8f0; display:inline-block;
}

/* Plate options */
.plate-grid{
    display:grid;
    gap:12px;
}
.plate-option input{ display:none; }
.plate-option label{
    display:flex; align-items:center; gap:16px;
    padding:18px 20px;
    border:2px solid #e2e8f0; border-radius:12px;
    cursor:pointer; transition:0.2s; background:#fff;
}
.plate-option input:checked + label{
    border-color:#1e293b; background:#f8fafc;
    box-shadow:0 0 0 3px rgba(30,41,59,0.08);
}
.plate-option label .ic{
    font-size:28px; color:#64748b;
    width:48px; height:48px;
    background:#f1f5f9; border-radius:10px;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0;
}
.plate-option input:checked + label .ic{ color:#1e293b; background:#e2e8f0; }
.plate-option label .text{ flex:1; }
.plate-option label .ti{ font-size:14px; font-weight:700; color:#1e293b; margin-bottom:4px; }
.plate-option label .sb{ font-size:12px; color:#64748b; line-height:1.4; }
.plate-option label .fee{
    font-size:13px; font-weight:800;
    color:#dc2626; padding:6px 12px;
    background:#fef2f2; border-radius:8px;
    white-space:nowrap;
}
.plate-option label .fee.free{ color:#15803d; background:#dcfce7; }

.info-note{
    background:#fffbeb; border:1px solid #fde68a;
    color:#92400e; padding:12px 14px; border-radius:10px;
    font-size:12px; line-height:1.6;
    display:flex; align-items:flex-start; gap:10px;
    margin-top:14px;
}
.info-note i{ color:#d97706; font-size:14px; margin-top:2px; }

/* Card */
.card-visual{
    background:linear-gradient(135deg, #334155 0%, #1e293b 50%, #0f172a 100%);
    color:#fff; border-radius:16px; padding:24px;
    height:200px; position:relative;
    box-shadow:0 10px 30px rgba(15,23,42,0.2);
    margin-bottom:24px; overflow:hidden;
}
.card-visual::before{
    content:""; position:absolute;
    top:-50%; right:-30%; width:300px; height:300px;
    background:radial-gradient(circle, rgba(255,255,255,0.06) 0%, transparent 70%);
}
.card-visual .brand{
    position:absolute; top:24px; right:24px;
    font-size:20px; font-weight:800; letter-spacing:-0.5px; font-style:italic;
}
.card-visual .chip{
    width:46px; height:34px;
    background:linear-gradient(135deg,#fde68a 0%,#fbbf24 100%);
    border-radius:6px; margin-bottom:32px;
}
.card-visual .num{
    font-family:'Courier New',monospace;
    font-size:21px; letter-spacing:2px;
    font-weight:600; margin-bottom:20px;
}
.card-visual .row-bottom{
    display:flex; justify-content:space-between;
    font-size:11px; text-transform:uppercase;
    letter-spacing:1px; opacity:0.85;
}
.card-visual .row-bottom .v{
    font-size:14px; letter-spacing:1px;
    margin-top:3px; opacity:1; text-transform:none;
}

.form-row{
    display:grid; grid-template-columns:1fr 1fr;
    gap:14px; margin-bottom:14px;
}
.form-row.single{ grid-template-columns:1fr; }

.form-group label{
    display:block; font-size:12px; font-weight:600;
    color:#475569; margin-bottom:6px;
    text-transform:uppercase; letter-spacing:0.3px;
}
.form-input{
    width:100%; padding:12px 14px;
    border:1.5px solid #e2e8f0; border-radius:8px;
    font-size:14px; font-family:'Poppins',sans-serif;
    background:#fff; color:#1e293b;
    transition:0.2s; outline:none;
}
.form-input:focus{
    border-color:#1e293b;
    box-shadow:0 0 0 3px rgba(30,41,59,0.08);
}
.form-input.mono{
    font-family:'Courier New',monospace;
    letter-spacing:1.5px; font-size:15px;
}

.security-note{
    background:#f0fdf4; border:1px solid #bbf7d0;
    color:#15803d; padding:12px 14px; border-radius:10px;
    font-size:12px; display:flex; align-items:center; gap:8px;
    margin-bottom:22px;
}

.btn-pay{
    width:100%; background:#1e293b; color:#fff; border:none;
    padding:16px; border-radius:10px;
    font-size:16px; font-weight:700; cursor:pointer;
    font-family:'Poppins',sans-serif; transition:0.2s;
    box-shadow:0 4px 6px -1px rgba(30,41,59,0.2);
    display:flex; align-items:center; justify-content:center; gap:10px;
}
.btn-pay:hover:not(:disabled){
    background:#0f172a; transform:translateY(-2px);
    box-shadow:0 10px 15px -3px rgba(30,41,59,0.25);
}
.btn-pay:disabled{ opacity:0.7; cursor:wait; }

.error-box{
    background:#fef2f2; border:1px solid #fecaca;
    border-left:4px solid #ef4444; color:#991b1b;
    padding:14px 18px; border-radius:10px; margin-bottom:18px;
}
.error-box p{ margin:3px 0; font-size:13px; font-weight:500; }
.error-box p::before{
    content:"\f071"; font-family:"Font Awesome 6 Free";
    font-weight:900; margin-right:8px; color:#dc2626;
}

.cancel-link{ text-align:center; margin-top:14px; font-size:13px; }
.cancel-link a{ color:#64748b; text-decoration:none; }
.cancel-link a:hover{ color:#1e293b; text-decoration:underline; }
</style>

<div class="dp-page">
    <div class="dp-wrapper">

        <div class="page-heading">
            <h1>Down Payment</h1>
            <p>Complete your down payment, insurance, and plate registration in one step.</p>
        </div>

        <!-- LEFT: Vehicle + Loan Summary + Payment Today -->
        <div class="left-col">
            <div class="dp-card vehicle-preview">
                <img src="<?= htmlspecialchars($car_image) ?>" alt="Vehicle">
                <h2><?= htmlspecialchars($car_brand . ' ' . $car_model) ?></h2>
                <span class="vp-origin"><?= htmlspecialchars($car_origin) ?></span>
                <div style="margin-top:8px;font-size:12px;color:#64748b;">
                    <?= htmlspecialchars($car_year) ?> &middot;
                    <?= htmlspecialchars($car_variant) ?> &middot;
                    <?= htmlspecialchars($car_color) ?>
                </div>
                <div class="book-id-pill">BK<?= str_pad($booking_id, 4, '0', STR_PAD_LEFT) ?></div>
            </div>

            <!-- Loan Summary - shows DP being deducted from vehicle price -->
            <div class="dp-card summary-card">
                <h3><i class="fas fa-calculator"></i> Loan Summary</h3>
                <div class="summary-row">
                    <span>Car Price</span>
                    <strong>RM <?= number_format($car_price, 2) ?></strong>
                </div>
                <div class="summary-row deduction">
                    <span>Booking Fee (Paid)</span>
                    <strong>- RM <?= number_format($booking_fee, 2) ?></strong>
                </div>
                <div class="summary-row deduction">
                    <span>Down Payment (10%)</span>
                    <strong>- RM <?= number_format($dp_amount, 2) ?></strong>
                </div>
                <div class="summary-row subtotal">
                    <span>Loan to be Financed</span>
                    <strong id="loanAmt">RM <?= number_format($loan_amount, 2) ?></strong>
                </div>
            </div>

            <!-- Payment Today -->
            <div class="dp-card summary-card">
                <h3><i class="fas fa-receipt"></i> Payment Today</h3>
                <div class="summary-row cost">
                    <span>Down Payment</span>
                    <strong>RM <?= number_format($dp_amount, 2) ?></strong>
                </div>
                <div class="summary-row cost">
                    <span>Insurance Fee</span>
                    <strong>RM <?= number_format(INSURANCE_FEE, 2) ?></strong>
                </div>
                <div class="summary-row cost" id="plateFeeRow">
                    <span>Plate Registration</span>
                    <strong id="plateFeeAmt">RM <?= number_format(PLATE_REG_FEE, 2) ?></strong>
                </div>
                <div class="summary-row total">
                    <span>Total Due Now</span>
                    <strong id="totalAmt">RM <?= number_format($dp_amount + INSURANCE_FEE + PLATE_REG_FEE, 2) ?></strong>
                </div>
            </div>
        </div>

        <!-- RIGHT: Form -->
        <div class="dp-card">

            <div class="amount-banner">
                <div class="lbl">Total Amount Due</div>
                <div class="amt" id="amountBanner">RM <?= number_format($dp_amount + INSURANCE_FEE + PLATE_REG_FEE, 2) ?></div>
                <div class="sub">Down Payment &middot; BK<?= str_pad($booking_id, 4, '0', STR_PAD_LEFT) ?></div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <?php foreach ($errors as $err): ?>
                        <p><?= htmlspecialchars($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" autocomplete="off" id="dpForm">
                <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
                <input type="hidden" name="confirm_payment" value="1">
                <!-- Insurance Section -->
                <div class="form-section">
                    <h3><i class="fas fa-shield-alt"></i> Step 1 · Insurance Document (RM 3,000)</h3>

                    <div class="template-download">
                        <div class="info">
                            <strong><i class="fas fa-file-pdf"></i> Motor Insurance Cover Note</strong>
                            <p>Download, fill in your details, sign it, and upload the completed copy below.</p>
                        </div>
                        <a href="<?= htmlspecialchars(TEMPLATE_PDF) ?>" download>
                            <i class="fas fa-download"></i> Download PDF
                        </a>
                    </div>

                    <label class="upload-zone" id="uploadZone" for="insuranceInput">
                        <i class="fas fa-cloud-upload-alt up-ic"></i>
                        <div class="uz-text" id="uzText">Click here to upload your signed insurance form</div>
                        <div class="uz-sub">Accepted: PDF, JPG, PNG (Max 5MB)</div>
                        <div class="file-name" id="fileName" style="display:none;"></div>
                        <input type="file" name="insurance_pdf" id="insuranceInput" accept=".pdf,.jpg,.jpeg,.png" required>
                    </label>
                </div>

                <!-- Plate Section -->
                <div class="form-section">
                    <h3><i class="fas fa-id-card-alt"></i> Step 2 · Car Plate Registration</h3>

                    <div class="plate-grid">

                        <!-- Option: We Register (always shown) -->
                        <div class="plate-option">
                            <input type="radio" name="plate_option" id="po_new" value="new" checked>
                            <label for="po_new">
                                <div class="ic"><i class="fas fa-id-card-alt"></i></div>
                                <div class="text">
                                    <div class="ti">Plate Registered by Dealer</div>
                                    <div class="sb">We will assign and register a new plate number for your vehicle. The plate number will be confirmed by our admin team after this payment.</div>
                                </div>
                                <div class="fee">+ RM <?= number_format(PLATE_REG_FEE, 2) ?></div>
                            </label>
                        </div>

                        <!-- Option: Keep Used Plate (ONLY shown for used cars) -->
                        <?php if ($is_used_car): ?>
                        <div class="plate-option">
                            <input type="radio" name="plate_option" id="po_used" value="used">
                            <label for="po_used">
                                <div class="ic"><i class="fas fa-recycle"></i></div>
                                <div class="text">
                                    <div class="ti">Keep Existing Plate</div>
                                    <div class="sb">Continue using the original plate number from this used vehicle. No new registration required.</div>
                                </div>
                                <div class="fee free">FREE</div>
                            </label>
                        </div>
                        <?php endif; ?>

                    </div>

                    <div class="info-note">
                        <i class="fas fa-info-circle"></i>
                        <span>
                            <?php if ($is_used_car): ?>
                                Choose whether to keep the existing plate or have us register a new one for you. Your selected option will be reviewed by admin.
                            <?php else: ?>
                                Your new vehicle requires plate registration. Our admin team will assign a plate number after verification.
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <!-- Card Payment -->
                <div class="form-section">
                    <h3><i class="fas fa-credit-card"></i> Step 3 · Card Payment</h3>

                    <div class="card-visual">
                        <div class="brand">VISA</div>
                        <div class="chip"></div>
                        <div class="num" id="cardVisualNum">•••• •••• •••• ••••</div>
                        <div class="row-bottom">
                            <div>
                                Card Holder
                                <div class="v" id="cardVisualName">YOUR NAME</div>
                            </div>
                            <div>
                                Expires
                                <div class="v" id="cardVisualExp">MM/YY</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row single">
                        <div class="form-group">
                            <label>Card Number</label>
                            <input type="text" name="card_number" id="card_number"
                                   class="form-input mono" inputmode="numeric"
                                   placeholder="1234 5678 9012 3456" maxlength="19" required>
                        </div>
                    </div>

                    <div class="form-row single">
                        <div class="form-group">
                            <label>Name on Card</label>
                            <input type="text" name="card_name" id="card_name"
                                   class="form-input" placeholder="JOHN DOE"
                                   style="text-transform:uppercase;" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Expiry (MM/YY)</label>
                            <input type="text" name="card_expiry" id="card_expiry"
                                   class="form-input mono" inputmode="numeric"
                                   placeholder="MM/YY" maxlength="5" required>
                        </div>
                        <div class="form-group">
                            <label>CVV</label>
                            <input type="text" name="card_cvv" id="card_cvv"
                                   class="form-input mono" inputmode="numeric"
                                   placeholder="•••" maxlength="3" required>
                        </div>
                    </div>
                </div>

                <div class="security-note">
                    <i class="fas fa-lock"></i>
                    Your payment & documents are encrypted. Admin will verify and approve your down payment shortly.
                </div>

                <button type="submit" class="btn-pay" id="btnPay">
                    <i class="fas fa-shield-alt"></i>
                    Pay <span id="btnAmt">RM <?= number_format($dp_amount + INSURANCE_FEE + PLATE_REG_FEE, 2) ?></span>
                </button>

                <div class="cancel-link">
                    <a href="view_status.php"><i class="fas fa-arrow-left"></i> Back to My Activity</a>
                </div>
            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
const DP_AMOUNT       = <?= $dp_amount ?>;
const INSURANCE_FEE   = <?= INSURANCE_FEE ?>;
const PLATE_REG_FEE   = <?= PLATE_REG_FEE ?>;
const IS_USED_CAR     = <?= $is_used_car ? 'true' : 'false' ?>;

const fmt = n => 'RM ' + n.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// ============ Plate option logic (only 'new' or 'used') ============
const plateRadios = document.querySelectorAll('input[name="plate_option"]');
const plateFeeAmt = document.getElementById('plateFeeAmt');
const totalAmt = document.getElementById('totalAmt');
const amountBanner = document.getElementById('amountBanner');
const btnAmt = document.getElementById('btnAmt');

function updateTotals(){
    const selected = document.querySelector('input[name="plate_option"]:checked').value;
    const plateFee = (selected === 'used') ? 0 : PLATE_REG_FEE;
    const total = DP_AMOUNT + INSURANCE_FEE + plateFee;

    plateFeeAmt.textContent = fmt(plateFee);
    plateFeeAmt.style.color = plateFee === 0 ? '#16a34a' : '#dc2626';
    totalAmt.textContent = fmt(total);
    amountBanner.textContent = fmt(total);
    btnAmt.textContent = fmt(total);
}
plateRadios.forEach(r => r.addEventListener('change', updateTotals));
updateTotals();

// ============ Insurance upload zone ============
const insInput  = document.getElementById('insuranceInput');
const uploadZone = document.getElementById('uploadZone');
const fileName  = document.getElementById('fileName');
const uzText    = document.getElementById('uzText');

insInput.addEventListener('change', () => {
    if (insInput.files.length > 0) {
        const f = insInput.files[0];
        if (f.size > 5 * 1024 * 1024) {
            Swal.fire({ icon: 'error', title: 'File Too Large', text: 'Insurance file must be under 5MB.' });
            insInput.value = '';
            return;
        }
        uploadZone.classList.add('has-file');
        uzText.textContent = 'Insurance file ready to upload';
        fileName.textContent = '📎 ' + f.name + ' (' + (f.size / 1024).toFixed(0) + ' KB)';
        fileName.style.display = 'inline-block';
    }
});

// ============ Card formatters ============
const num  = document.getElementById('card_number');
const name = document.getElementById('card_name');
const exp  = document.getElementById('card_expiry');
const cvv  = document.getElementById('card_cvv');
const vNum  = document.getElementById('cardVisualNum');
const vName = document.getElementById('cardVisualName');
const vExp  = document.getElementById('cardVisualExp');

num.addEventListener('input', e => {
    let v = e.target.value.replace(/\D/g, '').substring(0, 16);
    e.target.value = v.replace(/(.{4})/g, '$1 ').trim();
    let disp = (v + '••••••••••••••••').substring(0, 16);
    vNum.textContent = disp.match(/.{1,4}/g).join(' ');
});
name.addEventListener('input', e => {
    let v = e.target.value.replace(/[^A-Za-z\s\.\-]/g, '').toUpperCase();
    e.target.value = v;
    vName.textContent = v.trim() || 'YOUR NAME';
});
exp.addEventListener('input', e => {
    let v = e.target.value.replace(/\D/g, '').substring(0, 4);
    if (v.length >= 3) v = v.substring(0, 2) + '/' + v.substring(2);
    e.target.value = v;
    vExp.textContent = v.length === 0 ? 'MM/YY' : v;
});
cvv.addEventListener('input', e => {
    e.target.value = e.target.value.replace(/\D/g, '').substring(0, 3);
});

// ============ Submit with confirmation ============
const form = document.getElementById('dpForm');
const btn  = document.getElementById('btnPay');

form.addEventListener('submit', async function(e) {
    e.preventDefault();

    // Validations
    if (insInput.files.length === 0) {
        Swal.fire({ icon: 'error', title: 'Missing Document', text: 'Please upload your signed insurance form.' });
        return;
    }
    const rawNum = num.value.replace(/\s/g, '');
    if (rawNum.length !== 16) {
        Swal.fire({ icon: 'error', title: 'Invalid Card', text: 'Card number must be 16 digits.' });
        return;
    }
    if (!/^[A-Za-z\s\.\-]{2,}$/.test(name.value.trim())) {
        Swal.fire({ icon: 'error', title: 'Invalid Name', text: 'Please enter the name on card.' });
        return;
    }
    if (!/^(0[1-9]|1[0-2])\/([0-9]{2})$/.test(exp.value)) {
        Swal.fire({ icon: 'error', title: 'Invalid Expiry', text: 'Expiry must be MM/YY.' });
        return;
    }
    const [mm, yy] = exp.value.split('/').map(s => parseInt(s, 10));
    const ny = new Date().getFullYear(), nm = new Date().getMonth() + 1;
    if ((2000 + yy) < ny || ((2000 + yy) === ny && mm < nm)) {
        Swal.fire({ icon: 'error', title: 'Card Expired', text: 'Please use a valid card.' });
        return;
    }
    if (cvv.value.length !== 3) {
        Swal.fire({ icon: 'error', title: 'Invalid CVV', text: 'CVV must be 3 digits.' });
        return;
    }

    const plateOpt = document.querySelector('input[name="plate_option"]:checked').value;
    const plateFee = (plateOpt === 'used') ? 0 : PLATE_REG_FEE;
    const total = DP_AMOUNT + INSURANCE_FEE + plateFee;

    const last4 = rawNum.slice(-4);
    const r = await Swal.fire({
        icon: 'question',
        title: 'Confirm Down Payment',
        html: `
            <div style="text-align:left;font-size:13px;">
              <div style="background:#f8fafc;padding:14px;border-radius:8px;margin:12px 0;border:1px solid #e2e8f0;">
                <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-bottom:5px;">
                  <span>Down Payment</span><span>${fmt(DP_AMOUNT)}</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-bottom:5px;">
                  <span>Insurance</span><span>${fmt(INSURANCE_FEE)}</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-bottom:5px;">
                  <span>Plate Registration</span><span>${fmt(plateFee)}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding-top:8px;border-top:1px dashed #cbd5e1;">
                  <strong style="color:#1e293b;">Total</strong>
                  <strong style="color:#dc2626;font-size:16px;">${fmt(total)}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:11px;color:#94a3b8;margin-top:8px;">
                  <span>Card</span>
                  <span style="font-family:monospace;">**** **** **** ${last4}</span>
                </div>
              </div>
              <p style="color:#dc2626;font-size:11px;">⚠ This action cannot be undone.</p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Yes, Pay Now',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#1e293b',
        cancelButtonColor: '#94a3b8',
        reverseButtons: true
    });

    if (!r.isConfirmed) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    Swal.fire({
        title: 'Processing Payment',
        html: `
            <div style="margin:18px 0;">
              <i class="fas fa-credit-card" style="font-size:36px;color:#1e293b;margin-bottom:10px;"></i>
              <p style="color:#64748b;font-size:13px;">Connecting to bank...<br>Please do not close this window.</p>
            </div>
        `,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => { Swal.showLoading(); }
    });

    setTimeout(() => { form.submit(); }, 2000);
});
</script>

<?php include 'Includes/footer.php'; ?>