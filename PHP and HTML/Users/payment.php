<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

require '../Config/database.php';

<<<<<<< HEAD
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
=======
// =====================================================
// SECURITY CHECK
// =====================================================

if (
    !isset($_SESSION['user_id']) &&
    !isset($_SESSION['id'])
) {

    header("Location: ../Auth/login.php");
>>>>>>> 4d505bf2c2e91fca970c71d3c1dc125fff21378c
    exit();
}

<<<<<<< HEAD
$user_id = (int) $_SESSION['id'];

// ======================================================
// RESOLVE BOOKING ID
// ======================================================
=======
$user_id = intval(
    $_SESSION['user_id']
    ?? $_SESSION['id']
    ?? 0
);

// =====================================================
// PAYMENT VARIABLES
// =====================================================
>>>>>>> 4d505bf2c2e91fca970c71d3c1dc125fff21378c

$booking_id = intval(
    $_GET['id']
    ?? $_POST['booking_id']
    ?? $_SESSION['booking_id']
    ?? 0
);

$pay_source = trim($_SESSION['pay_source'] ?? 'booking');
$pay_label  = trim($_SESSION['pay_label']  ?? 'Booking Fee');

<<<<<<< HEAD
// ======================================================
// FRIENDLY ERROR PAGE
// ======================================================
=======
$payment_label = trim(
    $_POST['payment_label']
    ?? ($_SESSION['pay_label'] ?? '')
);

$source_type = trim(
    $_POST['source']
    ?? ($_SESSION['pay_source'] ?? 'booking')
);

// =====================================================
// PAGE TITLES
// =====================================================

$page_title = 'Vehicle Payment';

$button_text = 'Pay Now';

if ($source_type === 'booking') {

    $page_title =
    'Vehicle Booking Fee';

    $button_text =
    'Confirm Booking';

}
elseif ($source_type === 'downpayment') {

    $page_title =
    'Vehicle Downpayment';

    $button_text =
    'Pay Downpayment';

}
elseif ($source_type === 'installment') {

    $page_title =
    'Monthly Installment Payment';

    $button_text =
    'Pay Installment';

}

// =====================================================
// PAYMENT LABEL FALLBACK
// =====================================================

if (empty($payment_label)) {

    if ($source_type === 'booking') {

        $payment_label =
        'Booking Fee';

    }
    elseif ($source_type === 'downpayment') {

        $payment_label =
        'Down Payment';

    }
    else {

        $payment_label =
        'Monthly Installment';

    }

}

// =====================================================
// VEHICLE IMAGE FALLBACK
// =====================================================

$vehicle_image =
$_SESSION['res_image']
?? 'https://via.placeholder.com/500x300';

// =====================================================
// SHOW PAYMENT FORM
// =====================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    <?php echo $page_title; ?>
</title>

<link
href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
rel="stylesheet"
>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Poppins',sans-serif;
}

body{
    background:#f1f5f9;
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:40px;
}

.payment-wrapper{
    width:100%;
    max-width:1150px;
    display:grid;
    grid-template-columns:1fr 420px;
    background:white;
    border-radius:26px;
    overflow:hidden;
    box-shadow:0 12px 40px rgba(0,0,0,0.08);
}

/* LEFT */

.left-side{
    padding:45px;
    display:flex;
    flex-direction:column;
    justify-content:center;
}

.payment-title{
    font-size:32px;
    font-weight:700;
    color:#0f172a;
    margin-bottom:8px;
}

.payment-subtitle{
    color:#64748b;
    margin-bottom:50px;
}

/* FORM */

form{
    width:100%;
    max-width:500px;
}

.form-group{
    margin-bottom:20px;
}

label{
    display:block;
    margin-bottom:8px;
    font-size:14px;
    font-weight:600;
    color:#334155;
}

input{
    width:100%;
    padding:15px;
    border:1px solid #dbe3ee;
    border-radius:14px;
    font-size:15px;
    transition:0.2s;
}

input:focus{
    outline:none;
    border-color:#2563eb;
    box-shadow:0 0 0 4px rgba(37,99,235,0.10);
}

.row{
    display:flex;
    gap:15px;
}

.pay-btn{
    width:100%;
    padding:16px;
    background:#2563eb;
    color:white;
    border:none;
    border-radius:16px;
    font-size:16px;
    font-weight:700;
    cursor:pointer;
    margin-top:10px;
    transition:0.2s;
}

.pay-btn:hover{
    background:#1d4ed8;
}

/* RIGHT */

.right-side{
    background:#2563eb;
    color:white;
    padding:40px;
}

.summary-title{
    font-size:28px;
    font-weight:700;
    margin-bottom:30px;
}

/* VEHICLE */

.vehicle-box{
    background:rgba(255,255,255,0.12);
    padding:18px;
    border-radius:20px;
    margin-bottom:30px;
}

.vehicle-image{
    width:100%;
    height:190px;
    object-fit:cover;
    border-radius:14px;
    margin-bottom:15px;
}

.vehicle-title{
    font-size:22px;
    font-weight:700;
    margin-bottom:10px;
}

.vehicle-detail{
    margin-bottom:8px;
    font-size:14px;
}

/* SUMMARY */

.summary-item{
    display:flex;
    justify-content:space-between;
    margin-bottom:18px;
    font-size:15px;
}

.total-box{
    margin-top:30px;
    padding-top:20px;
    border-top:1px solid rgba(255,255,255,0.25);
}

.total-title{
    font-size:15px;
    opacity:0.9;
}

.total-amount{
    font-size:36px;
    font-weight:700;
    margin-top:10px;
}

.secure-box{
    margin-top:35px;
    background:rgba(255,255,255,0.12);
    padding:18px;
    border-radius:14px;
    font-size:14px;
    line-height:1.6;
}

@media(max-width:950px){

    .payment-wrapper{
        grid-template-columns:1fr;
    }

    .right-side{
        order:-1;
    }

}

</style>

</head>

<body>

<div class="payment-wrapper">

    <!-- LEFT -->

    <div class="left-side">

        <h1 class="payment-title">
            <?php echo $page_title; ?>
        </h1>

        <p class="payment-subtitle">
            Complete your vehicle payment securely.
        </p>

        <!-- FORM -->

        <form method="POST" action="">

            <input
                type="hidden"
                name="booking_id"
                value="<?php echo $booking_id; ?>"
            >

            <input
                type="hidden"
                name="payment_amount"
                value="<?php echo $payment_amount; ?>"
            >

            <input
                type="hidden"
                name="source"
                value="<?php echo htmlspecialchars($source_type); ?>"
            >

            <div class="form-group">

                <label>
                    Card Holder Name
                </label>

                <input
                    type="text"
                    name="card_name"
                    placeholder="John Doe"
                    required
                >

            </div>

            <div class="form-group">

                <label>
                    Card Number
                </label>

                <input
                    type="text"
                    name="card_number"
                    maxlength="19"
                    placeholder="1234 5678 9012 3456"
                    required
                >

            </div>

            <div class="row">

                <div class="form-group" style="flex:1;">

                    <label>
                        Expiry Date
                    </label>

                    <input
                        type="text"
                        name="expiry"
                        maxlength="5"
                        placeholder="MM/YY"
                        required
                    >

                </div>

                <div class="form-group" style="flex:1;">

                    <label>
                        CVV
                    </label>

                    <input
                        type="password"
                        name="cvv"
                        maxlength="4"
                        placeholder="123"
                        required
                    >

                </div>

            </div>

            <button
                type="submit"
                class="pay-btn"
            >
                <?php echo $button_text; ?>
            </button>

        </form>

    </div>

    <!-- RIGHT -->

    <div class="right-side">

        <h2 class="summary-title">
            Payment Summary
        </h2>

        <!-- VEHICLE -->

        <div class="vehicle-box">

            <img
                src="<?php echo htmlspecialchars($vehicle_image); ?>"
                class="vehicle-image"
            >

            <div class="vehicle-title">

                <?php
                echo htmlspecialchars(
                    ($_SESSION['res_brand'] ?? '')
                    . ' ' .
                    ($_SESSION['res_model'] ?? '')
                );
                ?>

            </div>

            <div class="vehicle-detail">

                Vehicle Year:
                <strong>
                    <?php
                    echo htmlspecialchars(
                        $_SESSION['res_year'] ?? ''
                    );
                    ?>
                </strong>

            </div>

            <div class="vehicle-detail">

                Vehicle Type:
                <strong>
                    <?php
                    echo htmlspecialchars(
                        $_SESSION['res_origin'] ?? ''
                    );
                    ?>
                </strong>

            </div>

            <div class="vehicle-detail">

                Loan Tenure:
                <strong>
                    <?php
                    echo htmlspecialchars(
                        $_SESSION['loan_years'] ?? ''
                    );
                    ?>
                    Years
                </strong>

            </div>

            <div class="vehicle-detail">

                Selected Color:
                <strong>
                    <?php
                    echo htmlspecialchars(
                        $_SESSION['car_color'] ?? ''
                    );
                    ?>
                </strong>

            </div>

        </div>

        <!-- SUMMARY -->

        <div class="summary-item">

            <span>
                Payment Type
            </span>

            <strong>
                <?php echo htmlspecialchars($payment_label); ?>
            </strong>

        </div>

        <div class="summary-item">

            <span>
                Booking Reference
            </span>

            <strong>
                #<?php echo $booking_id; ?>
            </strong>

        </div>

        <div class="summary-item">

            <span>
                Insurance
            </span>

            <strong>

                RM <?php
                echo number_format(
                    $_SESSION['insurance_amount']
                    ?? 0,
                    2
                );
                ?>

            </strong>

        </div>

        <div class="total-box">

            <div class="total-title">
                Total Payment
            </div>

            <div class="total-amount">

                RM <?php
                echo number_format(
                    $payment_amount,
                    2
                );
                ?>

            </div>

        </div>

        <div class="secure-box">

            🔒 Your payment is protected using
            simulated SSL encryption for
            educational project purposes only.

        </div>

    </div>

</div>

</body>
</html>

<?php
exit();
}

// =====================================================
// PAYMENT VALIDATION
// =====================================================

if (
    $booking_id <= 0
    ||
    $payment_amount <= 0
) {

    die(
        "Error: Invalid payment transaction."
    );
>>>>>>> 4d505bf2c2e91fca970c71d3c1dc125fff21378c

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

<<<<<<< HEAD
if ($booking_id <= 0) {
    show_error_page(
        'Invalid Payment Reference',
        'No valid booking was specified for payment processing.',
        'Browse Vehicles',
        'cars.php'
    );
}

// ======================================================
// VERIFY OWNERSHIP + LOAD BOOKING
// ======================================================

$verify_sql = "
SELECT
    b.*,
    c.car_brand, c.car_model, c.car_year, c.car_origin,
    cs.car_status_price AS car_price,
    (SELECT car_image_url FROM car_image WHERE car_id = b.car_id LIMIT 1) AS car_image
FROM bookings b
LEFT JOIN cars c ON c.car_id = b.car_id
LEFT JOIN car_status cs ON cs.car_id = b.car_id
WHERE b.booking_id = ? AND b.user_id = ?
LIMIT 1
";

$verify_stmt = mysqli_prepare($conn, $verify_sql);
mysqli_stmt_bind_param($verify_stmt, "ii", $booking_id, $user_id);
mysqli_stmt_execute($verify_stmt);
$verify_result = mysqli_stmt_get_result($verify_stmt);
$booking = mysqli_fetch_assoc($verify_result);
mysqli_stmt_close($verify_stmt);

if (!$booking) {
    show_error_page(
        'Unauthorized Access',
        'This booking does not belong to your account, or it no longer exists.',
        'Return Home',
        'index.php'
    );
}

// ======================================================
// SNAPSHOT
// ======================================================
=======
// =====================================================
// VERIFY BOOKING OWNERSHIP
// =====================================================

$verify_sql = "
SELECT *
FROM bookings
WHERE booking_id = ?
AND user_id = ?
LIMIT 1
";

$verify_stmt =
mysqli_prepare(
    $conn,
    $verify_sql
);

mysqli_stmt_bind_param(
    $verify_stmt,
    "ii",
    $booking_id,
    $user_id
);
>>>>>>> 4d505bf2c2e91fca970c71d3c1dc125fff21378c

$snap = json_decode($booking['snapshot_data'] ?: '{}', true);
if (!is_array($snap)) $snap = [];

$car_brand   = $snap['car_brand']   ?? $booking['car_brand']   ?? '';
$car_model   = $snap['car_model']   ?? $booking['car_model']   ?? '';
$car_year    = $snap['car_year']    ?? $booking['car_year']    ?? '';
$car_origin  = $snap['car_origin']  ?? $booking['car_origin']  ?? '';
$car_image   = $snap['car_image']   ?? $booking['car_image']   ?? '';
$car_variant = $snap['car_variant'] ?? '-';
$car_color   = $snap['car_color']   ?? '-';
$car_price   = floatval($snap['car_price'] ?? $booking['car_price'] ?? 0);

if (empty($car_image)) {
    $car_image = 'https://via.placeholder.com/600x400.png?text=Vehicle';
}

// ======================================================
// PAYMENT TYPE + AMOUNT
// ======================================================

$payment_type = 'Booking Fee';
if ($pay_source === 'downpayment')  $payment_type = 'Down Payment';
if ($pay_source === 'installment')  $payment_type = 'Monthly Installment';

$pay_amount = 0;
if ($payment_type === 'Booking Fee') {
    $pay_amount = floatval($booking['booking_fee']);
} else {
    $pay_amount = floatval($_SESSION['pay_amount'] ?? 0);
}

if ($pay_amount <= 0) {
    show_error_page(
        'Invalid Payment Amount',
        'The payment amount could not be determined. Please restart the process.',
        'Return Home',
        'index.php'
    );
}

<<<<<<< HEAD
// ======================================================
// ALREADY PAID — redirect to receipt
// ======================================================

if ($payment_type === 'Booking Fee') {
    $check = mysqli_query(
        $conn,
        "SELECT payment_id
         FROM payments
         WHERE reference_id = $booking_id
         AND payment_type = 'Booking Fee'
         AND payment_status = 'Paid'
         ORDER BY payment_id DESC
         LIMIT 1"
=======
// =====================================================
// PAYMENT TYPE
// =====================================================

$payment_type = 'Booking Fee';

if ($source_type === 'downpayment') {

    $payment_type =
    'Down Payment';

}
elseif ($source_type === 'installment') {

    $payment_type =
    'Monthly Installment';

}

// =====================================================
// PREVENT DUPLICATE BOOKING PAYMENT
// =====================================================

if ($payment_type === 'Booking Fee') {

    $check_sql = "
    SELECT payment_id
    FROM payments
    WHERE reference_id = ?
    AND payment_type = ?
    LIMIT 1
    ";

    $check_stmt =
    mysqli_prepare(
        $conn,
        $check_sql
>>>>>>> 4d505bf2c2e91fca970c71d3c1dc125fff21378c
    );
    if ($check && mysqli_num_rows($check) > 0) {
        $existing = mysqli_fetch_assoc($check);
        header("Location: payment_confirm.php?id=" . $existing['payment_id']);
        exit();
    }
}

<<<<<<< HEAD
// ======================================================
// PROCESS PAYMENT (POST)
// ======================================================

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {

    // ---- card field validation ----
    $card_number = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $card_name   = trim($_POST['card_name'] ?? '');
    $card_expiry = trim($_POST['card_expiry'] ?? '');
    $card_cvv    = preg_replace('/\D/', '', $_POST['card_cvv'] ?? '');
=======
    mysqli_stmt_bind_param(
        $check_stmt,
        "is",
        $booking_id,
        $payment_type
    );

    mysqli_stmt_execute(
        $check_stmt
    );

    $check_result =
    mysqli_stmt_get_result(
        $check_stmt
    );

    if (
        mysqli_num_rows(
            $check_result
        ) > 0
    ) {

        die(
            "Booking payment already completed."
        );
>>>>>>> 4d505bf2c2e91fca970c71d3c1dc125fff21378c

    if (strlen($card_number) !== 16) {
        $errors[] = "Card number must be exactly 16 digits.";
    }
    if (empty($card_name) || !preg_match('/^[A-Za-z\s\.\-]{2,}$/', $card_name)) {
        $errors[] = "Name on card is required (letters only).";
    }
    if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $card_expiry, $m)) {
        $errors[] = "Expiry must be in MM/YY format.";
    } else {
        $exp_month = (int) $m[1];
        $exp_year  = 2000 + (int) $m[2];
        $now_year  = (int) date('Y');
        $now_month = (int) date('n');
        if ($exp_year < $now_year || ($exp_year === $now_year && $exp_month < $now_month)) {
            $errors[] = "Card has expired.";
        }
    }
    if (strlen($card_cvv) !== 3) {
        $errors[] = "CVV must be 3 digits.";
    }

    if (empty($errors)) {

        mysqli_begin_transaction($conn);

        try {
            $prefix = 'BF';
            if ($payment_type === 'Down Payment')        $prefix = 'DP';
            if ($payment_type === 'Monthly Installment') $prefix = 'INS';

            $receipt_num = $prefix . '-' . date('Ymd') . '-' . str_pad($booking_id, 4, '0', STR_PAD_LEFT);
            $pay_ref     = 'TXN-' . strtoupper(bin2hex(random_bytes(5)));
            $last4       = substr($card_number, -4);
            $remarks     = $pay_label . ' (Card ending **** ' . $last4 . ')';

            $ins_sql = "
            INSERT INTO payments (
                payment_type, reference_id, payment_amount, payment_status,
                receipt_number, payment_reference, remarks, payment_date, created_at
            ) VALUES (?, ?, ?, 'Paid', ?, ?, ?, NOW(), NOW())
            ";
            $ins_stmt = mysqli_prepare($conn, $ins_sql);
            mysqli_stmt_bind_param(
                $ins_stmt,
                "sidsss",
                $payment_type,
                $booking_id,
                $pay_amount,
                $receipt_num,
                $pay_ref,
                $remarks
            );
            if (!mysqli_stmt_execute($ins_stmt)) {
                throw new Exception('Payment insert failed: ' . mysqli_stmt_error($ins_stmt));
            }
            $payment_id = mysqli_insert_id($conn);
            mysqli_stmt_close($ins_stmt);

            if ($payment_type === 'Booking Fee') {
                $upd = mysqli_prepare(
                    $conn,
                    "UPDATE bookings
                     SET booking_paid_at = NOW(), receipt_number = ?
                     WHERE booking_id = ?"
                );
                mysqli_stmt_bind_param($upd, "si", $receipt_num, $booking_id);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
            }

            mysqli_commit($conn);

            unset(
                $_SESSION['pay_amount'],
                $_SESSION['pay_source'],
                $_SESSION['pay_label'],
                $_SESSION['booking_id']
            );

            header("Location: payment_confirm.php?id=" . $payment_id);
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = "Payment processing failed. Please try again.";
        }
    }
}

<<<<<<< HEAD
// ======================================================
// RENDER
// ======================================================

include 'Includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

<style>
.pay-page{
    background:#f8fafc;
    min-height:calc(100vh - 80px);
    padding:40px 20px 60px;
}
.pay-wrapper{
    max-width:1100px;
    margin:0 auto;
    display:grid;
    grid-template-columns:1fr 1.3fr;
    gap:24px;
}
@media(max-width:900px){ .pay-wrapper{ grid-template-columns:1fr; } }

.page-heading{ grid-column:1 / -1; margin-bottom:6px; }
.page-heading h1{
    font-size:28px; font-weight:700; color:#1e293b;
    letter-spacing:-0.5px; margin-bottom:6px;
}
.page-heading p{ color:#64748b; font-size:14px; }

.pay-card{
    background:#fff;
    border:1px solid #f1f5f9;
    border-radius:16px;
    box-shadow:0 1px 2px rgba(0,0,0,0.03), 0 8px 24px rgba(0,0,0,0.04);
    padding:28px;
}

.left-col{
    display:flex; flex-direction:column; gap:20px;
    position:sticky; top:24px; align-self:flex-start;
}
@media(max-width:900px){ .left-col{ position:static; } }
=======
// =====================================================
// INSERT PAYMENT
// =====================================================

$insert_sql = "
INSERT INTO payments (

    reference_id,
    payment_amount,
    payment_type,
    payment_status,
    payment_date,
    remarks

)
VALUES (

    ?,
    ?,
    ?,
    'Paid',
    NOW(),
    ?

)
";

$insert_stmt =
mysqli_prepare(
    $conn,
    $insert_sql
);

mysqli_stmt_bind_param(
    $insert_stmt,
    "idss",
    $booking_id,
    $payment_amount,
    $payment_type,
    $payment_label
);

$insert_execute =
mysqli_stmt_execute(
    $insert_stmt
);

if (!$insert_execute) {

    die(
        "Payment Insert Failed: "
        . mysqli_error($conn)
    );
>>>>>>> 4d505bf2c2e91fca970c71d3c1dc125fff21378c

.vehicle-preview img{
    width:100%; height:180px; object-fit:cover;
    border-radius:12px; border:1px solid #e2e8f0; margin-bottom:14px;
}
.vehicle-preview h2{
    font-size:18px; color:#0f172a; font-weight:700;
    margin-bottom:4px; text-transform:uppercase;
}
.vp-origin{
    color:#64748b; font-size:12px; margin-bottom:8px;
    display:inline-block; padding:3px 10px;
    background:#f1f5f9; border-radius:999px; font-weight:500;
}

<<<<<<< HEAD
.summary-card h3{
    font-size:13px; color:#1e293b; text-transform:uppercase;
    letter-spacing:0.8px; font-weight:700; margin-bottom:14px;
    padding-bottom:10px; border-bottom:2px solid #f1f5f9;
    display:flex; align-items:center; gap:8px;
}
.summary-row{
    display:flex; justify-content:space-between;
    padding:8px 0; font-size:13px; color:#475569;
}
.summary-row strong{ color:#1e293b; font-weight:700; }
.summary-row.total{
    border-top:2px dashed #cbd5e1;
    padding-top:14px; margin-top:8px; font-size:16px;
}
.summary-row.total span:first-child{ font-weight:700; color:#1e293b; }
.summary-row.total strong{
    color:#dc2626; font-size:22px; font-weight:800;
}

.book-id-pill{
    display:inline-block; background:#1e293b; color:#fff;
    padding:4px 12px; border-radius:8px;
    font-family:monospace; font-size:12px;
    font-weight:700; margin-top:6px; letter-spacing:0.5px;
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

/* ==== Live Card Visual ==== */
.card-visual{
    background:linear-gradient(135deg, #334155 0%, #1e293b 50%, #0f172a 100%);
    color:#fff;
    border-radius:16px;
    padding:24px;
    height:200px;
    position:relative;
    box-shadow:0 10px 30px rgba(15,23,42,0.2);
    margin-bottom:24px;
    overflow:hidden;
}
.card-visual::before{
    content:""; position:absolute;
    top:-50%; right:-30%; width:300px; height:300px;
    background:radial-gradient(circle, rgba(255,255,255,0.06) 0%, transparent 70%);
}
.card-visual .brand{
    position:absolute; top:24px; right:24px;
    font-size:20px; font-weight:800; letter-spacing:-0.5px;
    font-style:italic;
}
.card-visual .chip{
    width:46px; height:34px;
    background:linear-gradient(135deg,#fde68a 0%,#fbbf24 100%);
    border-radius:6px; margin-bottom:32px;
    box-shadow:inset 0 0 0 1px rgba(0,0,0,0.1);
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

.form-section{ margin-bottom:24px; }
.form-section h3{
    font-size:13px; color:#1e293b; text-transform:uppercase;
    letter-spacing:0.8px; font-weight:700; margin-bottom:14px;
    padding-bottom:10px; border-bottom:2px solid #f1f5f9;
    display:flex; align-items:center; gap:8px;
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
.security-note i{ color:#16a34a; font-size:14px; }

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

.test-hint{
    background:#fffbeb; border:1px solid #fde68a; color:#92400e;
    padding:10px 14px; border-radius:8px; font-size:11px;
    margin-bottom:18px; display:flex; align-items:center; gap:8px;
}
.test-hint i{ color:#d97706; }
</style>

<div class="pay-page">
    <div class="pay-wrapper">

        <div class="page-heading">
            <h1>Complete Your Payment</h1>
            <p>Secure card payment. Your booking will be sent to our team for review after confirmation.</p>
        </div>

        <!-- LEFT: Vehicle + Summary -->
        <div class="left-col">
            <div class="pay-card vehicle-preview">
                <img src="<?= htmlspecialchars($car_image) ?>" alt="Vehicle">
                <h2><?= htmlspecialchars($car_brand . ' ' . $car_model) ?></h2>
                <span class="vp-origin"><?= htmlspecialchars($car_origin) ?></span>
                <div style="margin-top:10px;font-size:13px;color:#64748b;">
                    <?= htmlspecialchars($car_year) ?> &middot;
                    <?= htmlspecialchars($car_variant) ?> &middot;
                    <?= htmlspecialchars($car_color) ?>
                </div>
                <div class="book-id-pill">BK<?= str_pad($booking_id, 4, '0', STR_PAD_LEFT) ?></div>
            </div>

            <div class="pay-card summary-card">
                <h3><i class="fas fa-receipt"></i> Order Summary</h3>
                <div class="summary-row">
                    <span>Vehicle Price</span>
                    <strong>RM <?= number_format($car_price, 2) ?></strong>
                </div>
                <div class="summary-row">
                    <span>Payment Type</span>
                    <strong><?= htmlspecialchars($payment_type) ?></strong>
                </div>
                <div class="summary-row">
                    <span>Description</span>
                    <strong style="font-size:12px;"><?= htmlspecialchars($pay_label) ?></strong>
                </div>
                <div class="summary-row total">
                    <span>Total Due Now</span>
                    <strong>RM <?= number_format($pay_amount, 2) ?></strong>
                </div>
            </div>
        </div>

        <!-- RIGHT: Card Form -->
        <div class="pay-card">

            <div class="amount-banner">
                <div class="lbl">Amount to Pay</div>
                <div class="amt">RM <?= number_format($pay_amount, 2) ?></div>
                <div class="sub"><?= htmlspecialchars($payment_type) ?> &middot; BK<?= str_pad($booking_id, 4, '0', STR_PAD_LEFT) ?></div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <?php foreach ($errors as $err): ?>
                        <p><?= htmlspecialchars($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Live Card Visual -->
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

            <div class="test-hint">
                <i class="fas fa-flask"></i>
                <span><strong>Test mode:</strong> Use any 16-digit number, future MM/YY, and 3-digit CVV.</span>
            </div>

            <form method="POST" autocomplete="off" id="payForm">

                <div class="form-section">
                    <h3><i class="fas fa-credit-card"></i> Card Details</h3>

                    <div class="form-row single">
                        <div class="form-group">
                            <label>Card Number</label>
                            <input
                                type="text"
                                name="card_number"
                                id="card_number"
                                class="form-input mono"
                                inputmode="numeric"
                                placeholder="1234 5678 9012 3456"
                                maxlength="19"
                                value="<?= htmlspecialchars($_POST['card_number'] ?? '') ?>"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-row single">
                        <div class="form-group">
                            <label>Name on Card</label>
                            <input
                                type="text"
                                name="card_name"
                                id="card_name"
                                class="form-input"
                                placeholder="JOHN DOE"
                                style="text-transform:uppercase;"
                                value="<?= htmlspecialchars($_POST['card_name'] ?? '') ?>"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Expiry (MM/YY)</label>
                            <input
                                type="text"
                                name="card_expiry"
                                id="card_expiry"
                                class="form-input mono"
                                inputmode="numeric"
                                placeholder="MM/YY"
                                maxlength="5"
                                value="<?= htmlspecialchars($_POST['card_expiry'] ?? '') ?>"
                                required
                            >
                        </div>
                        <div class="form-group">
                            <label>CVV</label>
                            <input
                                type="text"
                                name="card_cvv"
                                id="card_cvv"
                                class="form-input mono"
                                inputmode="numeric"
                                placeholder="•••"
                                maxlength="3"
                                value=""
                                required
                            >
                        </div>
                    </div>
                </div>

                <div class="security-note">
                    <i class="fas fa-lock"></i>
                    Your payment is secured with end-to-end encryption. Card details are never stored in plain text.
                </div>

                <button type="submit" name="confirm_payment" value="1" class="btn-pay" id="btnPay">
                    <i class="fas fa-shield-alt"></i>
                    Pay RM <?= number_format($pay_amount, 2) ?> Now
                </button>

                <div class="cancel-link">
                    <a href="index.php"><i class="fas fa-arrow-left"></i> Cancel and return home</a>
                </div>

            </form>

        </div>

    </div>
</div>

<!-- SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// ============== Input formatters + live card preview ==============
const num   = document.getElementById('card_number');
const name  = document.getElementById('card_name');
const exp   = document.getElementById('card_expiry');
const cvv   = document.getElementById('card_cvv');
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

// pre-fill display on POST error reload
if (num.value)   num.dispatchEvent(new Event('input'));
if (name.value)  name.dispatchEvent(new Event('input'));
if (exp.value)   exp.dispatchEvent(new Event('input'));

// ============== Submit with confirmation + loading ==============
const form = document.getElementById('payForm');
const btn  = document.getElementById('btnPay');
const AMOUNT_TEXT = 'RM <?= number_format($pay_amount, 2) ?>';

form.addEventListener('submit', async function(e) {
    e.preventDefault();

    // Quick client-side check (server still validates as the source of truth)
    const rawNum = num.value.replace(/\s/g, '');
    if (rawNum.length !== 16) {
        Swal.fire({ icon: 'error', title: 'Invalid Card Number', text: 'Card number must be exactly 16 digits.' });
        return;
    }
    if (!/^[A-Za-z\s\.\-]{2,}$/.test(name.value.trim())) {
        Swal.fire({ icon: 'error', title: 'Invalid Name', text: 'Please enter the name on your card (letters only).' });
        return;
    }
    if (!/^(0[1-9]|1[0-2])\/([0-9]{2})$/.test(exp.value)) {
        Swal.fire({ icon: 'error', title: 'Invalid Expiry', text: 'Expiry must be in MM/YY format.' });
        return;
    }
    // Check not expired
    const [mm, yy] = exp.value.split('/').map(s => parseInt(s, 10));
    const fullYear = 2000 + yy;
    const now = new Date();
    if (fullYear < now.getFullYear() || (fullYear === now.getFullYear() && mm < (now.getMonth() + 1))) {
        Swal.fire({ icon: 'error', title: 'Card Expired', text: 'This card has already expired.' });
        return;
    }
    if (cvv.value.length !== 3) {
        Swal.fire({ icon: 'error', title: 'Invalid CVV', text: 'CVV must be 3 digits.' });
        return;
    }

    // Confirmation popup
    const last4 = rawNum.slice(-4);
    const r = await Swal.fire({
        icon: 'question',
        title: 'Confirm Payment',
        html: `
            <div style="text-align:left;font-size:14px;">
              <div style="background:#f8fafc;padding:14px;border-radius:8px;margin:12px 0;border:1px solid #e2e8f0;">
                <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                  <span style="color:#64748b;">Amount</span>
                  <strong style="color:#dc2626;font-size:18px;">${AMOUNT_TEXT}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;">
                  <span>Card</span>
                  <span style="font-family:monospace;">**** **** **** ${last4}</span>
                </div>
              </div>
              <p style="color:#dc2626;font-size:12px;margin-top:8px;">⚠ This action cannot be undone.</p>
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

    // Lock button + processing modal
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

    // Simulate gateway delay (2 seconds), then actually submit form
    setTimeout(() => { form.submit(); }, 2000);
});
</script>

<?php include 'Includes/footer.php'; ?>
=======
// =====================================================
// UPDATE BOOKING STATUS
// =====================================================

$booking_status =
'Pending Loan Approval';

if (
    $source_type === 'downpayment'
    ||
    $source_type === 'installment'
) {

    $booking_status =
    'Installment Active';

}

$update_sql = "
UPDATE bookings
SET booking_status = ?
WHERE booking_id = ?
";

$update_stmt =
mysqli_prepare(
    $conn,
    $update_sql
);

mysqli_stmt_bind_param(
    $update_stmt,
    "si",
    $booking_status,
    $booking_id
);

$update_execute =
mysqli_stmt_execute(
    $update_stmt
);

if (!$update_execute) {

    die(
        "Booking Update Failed: "
        . mysqli_error($conn)
    );

}

// =====================================================
// PAYMENT CONFIRM SESSION
// =====================================================

$_SESSION['pay_ref'] =
'TXN-' . strtoupper(uniqid());

$_SESSION['payment_type'] =
$payment_type;

$_SESSION['pay_booking_id'] =
$booking_id;

// =====================================================
// REDIRECT
// =====================================================

header(
    "Location: payment_confirm.php"
);

exit();
>>>>>>> 4d505bf2c2e91fca970c71d3c1dc125fff21378c
