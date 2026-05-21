<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

require 'database.php';

// ======================================================
// SECURITY CHECK
// ======================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (
    !isset($_SESSION['reservation_id']) ||
    empty($_SESSION['reservation_id'])
) {
    die("Reservation session expired.");
}

$reservation_id = intval($_SESSION['reservation_id']);

if ($reservation_id <= 0) {
    die("Invalid reservation reference.");
}

$user_id = $_SESSION['user_id'];

// ======================================================
// FETCH RESERVATION
// ======================================================

$reservation_sql = "
SELECT
    reservation_id,
    reservation_status,
    reservation_created_at,
    preferred_test_drive_at,
    snapshot_data
FROM reservations
WHERE reservation_id = ?
AND user_id = ?
LIMIT 1
";

$reservation_stmt = mysqli_prepare($conn, $reservation_sql);

if (!$reservation_stmt) {
    die("Reservation Query Prepare Failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $reservation_stmt,
    "ii",
    $reservation_id,
    $user_id
);

if (!mysqli_stmt_execute($reservation_stmt)) {
    die(
        "Reservation Query Execute Failed: " .
        mysqli_stmt_error($reservation_stmt)
    );
}

$reservation_result =
    mysqli_stmt_get_result($reservation_stmt);

if (!$reservation_result) {
    die("Reservation Query Result Failed.");
}

$reservation =
    mysqli_fetch_assoc($reservation_result);

mysqli_stmt_close($reservation_stmt);

// ======================================================
// VALIDATE RESERVATION
// ======================================================

if (!$reservation) {
    die("Reservation record not found.");
}

// ======================================================
// DECODE SNAPSHOT DATA
// ======================================================

$snapshot =
    json_decode(
        $reservation['snapshot_data'],
        true
    );

if (!is_array($snapshot)) {
    $snapshot = [];
}

// ======================================================
// SAFE DATA FALLBACKS
// ======================================================

$car_brand   = $snapshot['car_brand'] ?? 'Unknown Brand';
$car_model   = $snapshot['car_model'] ?? 'Unknown Model';
$car_year    = $snapshot['car_year'] ?? 'N/A';
$car_origin  = $snapshot['car_origin'] ?? 'N/A';
$car_price   = $snapshot['car_price'] ?? 0;
$car_variant = $snapshot['car_variant'] ?? 'Standard';

$user_name   = $snapshot['user_name'] ?? 'Customer';
$user_email  = $snapshot['user_email'] ?? 'N/A';
$user_phone  = $snapshot['user_phone'] ?? 'N/A';
$user_ic     = $snapshot['user_ic'] ?? 'N/A';

// Safe price type
$car_price = floatval($car_price);

// Safe image fallback
$car_image =
    !empty($snapshot['car_image'])
    ? $snapshot['car_image']
    : 'https://via.placeholder.com/500x300.png?text=Vehicle+Image';

// ======================================================
// FORMAT REFERENCE NUMBER
// ======================================================

$reservation_ref =
    $_SESSION['ref_number']
    ?? (
        'RSV-' .
        str_pad(
            $reservation_id,
            6,
            '0',
            STR_PAD_LEFT
        )
    );

// ======================================================
// STATUS
// ======================================================

$reservation_status =
    $reservation['reservation_status'] ?? 'Pending';

$status_lower =
    strtolower($reservation_status);

// ======================================================
// FORMAT CREATED DATE
// ======================================================

$created_at = 'N/A';

if (!empty($reservation['reservation_created_at'])) {

    $created_timestamp =
        strtotime($reservation['reservation_created_at']);

    if ($created_timestamp !== false) {

        $created_at =
            date(
                'd M Y, h:i A',
                $created_timestamp
            );
    }
}

// ======================================================
// FORMAT TEST DRIVE DATE
// ======================================================

$test_drive_at = 'Not Scheduled';

if (!empty($reservation['preferred_test_drive_at'])) {

    $timestamp =
        strtotime($reservation['preferred_test_drive_at']);

    if ($timestamp !== false) {

        $test_drive_at =
            date(
                'd M Y, h:i A',
                $timestamp
            );
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    Reservation Successful
</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
      rel="stylesheet">

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Poppins',sans-serif;
}

body{
    background:#f4f7fb;
    color:#1e293b;
    padding:40px 20px;
}

.success-container{
    max-width:850px;
    margin:0 auto;
    background:white;
    border-radius:22px;
    overflow:hidden;
    border:1px solid #e2e8f0;
    box-shadow:0 4px 25px rgba(0,0,0,0.04);
}

.success-header{
    background:linear-gradient(
        135deg,
        #2563eb,
        #1d4ed8
    );
    color:white;
    padding:40px;
    text-align:center;
}

.success-icon{
    width:85px;
    height:85px;
    background:white;
    color:#2563eb;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:40px;
    font-weight:bold;
    margin:0 auto 20px;
}

.success-header h1{
    font-size:34px;
    margin-bottom:10px;
}

.success-header p{
    opacity:0.92;
    font-size:15px;
}

.content-wrapper{
    padding:35px;
}

.reference-box{
    background:#eff6ff;
    border:1px solid #bfdbfe;
    padding:18px;
    border-radius:14px;
    margin-bottom:28px;
}

.reference-box h3{
    color:#1d4ed8;
    font-size:14px;
    margin-bottom:6px;
}

.reference-box p{
    font-size:24px;
    font-weight:700;
    letter-spacing:1px;
}

.vehicle-section{
    display:flex;
    gap:25px;
    align-items:flex-start;
    margin-bottom:35px;
}

.vehicle-image{
    width:320px;
    height:220px;
    object-fit:cover;
    border-radius:16px;
    border:1px solid #e2e8f0;
}

.vehicle-info{
    flex:1;
}

.vehicle-info h2{
    font-size:30px;
    margin-bottom:10px;
    color:#0f172a;
}

.info-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:18px;
    margin-top:25px;
}

.info-card{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    padding:18px;
    border-radius:14px;
}

.info-card h4{
    font-size:13px;
    color:#64748b;
    margin-bottom:8px;
    text-transform:uppercase;
    letter-spacing:0.5px;
}

.info-card p{
    font-size:15px;
    font-weight:600;
    color:#1e293b;
}

.status-badge{
    display:inline-block;
    padding:8px 16px;
    border-radius:999px;
    font-size:13px;
    font-weight:700;
    margin-top:12px;
}

.status-pending{
    background:#fef3c7;
    color:#92400e;
}

.status-approved{
    background:#dcfce7;
    color:#166534;
}

.status-rejected{
    background:#fee2e2;
    color:#991b1b;
}

.customer-section{
    margin-top:35px;
}

.customer-section h3{
    margin-bottom:20px;
    font-size:24px;
}

.note-box{
    margin-top:30px;
    background:#f8fafc;
    border-left:5px solid #2563eb;
    padding:20px;
    border-radius:12px;
}

.note-box p{
    font-size:14px;
    line-height:1.7;
    color:#475569;
}

.button-group{
    display:flex;
    gap:15px;
    margin-top:35px;
    flex-wrap:wrap;
}

.action-btn{
    flex:1;
    min-width:220px;
    text-align:center;
    padding:15px;
    border-radius:12px;
    text-decoration:none;
    font-weight:700;
    transition:0.2s;
}

.primary-btn{
    background:#2563eb;
    color:white;
}

.primary-btn:hover{
    background:#1d4ed8;
}

.secondary-btn{
    background:#f1f5f9;
    color:#1e293b;
    border:1px solid #cbd5e1;
}

.secondary-btn:hover{
    background:#e2e8f0;
}

@media(max-width:768px){

    .content-wrapper{
        padding:25px;
    }

    .vehicle-section{
        flex-direction:column;
    }

    .vehicle-image{
        width:100%;
        height:240px;
    }

    .success-header h1{
        font-size:28px;
    }

    .button-group{
        flex-direction:column;
    }
}

</style>

</head>

<body>

<div class="success-container">

    <!-- HEADER -->

    <div class="success-header">

        <div class="success-icon">
            ✓
        </div>

        <h1>
            Reservation Submitted Successfully
        </h1>

        <p>
            Thank you,
            <?php echo htmlspecialchars($user_name); ?>.
            Your test drive reservation request
            has been submitted successfully.
        </p>

    </div>

    <!-- CONTENT -->

    <div class="content-wrapper">

        <!-- REFERENCE -->

        <div class="reference-box">

            <h3>
                Reservation Reference Number
            </h3>

            <p>
                <?php echo htmlspecialchars($reservation_ref); ?>
            </p>

        </div>

        <!-- VEHICLE -->

        <div class="vehicle-section">

            <img
                src="<?php echo htmlspecialchars($car_image); ?>"
                class="vehicle-image"
                alt="Vehicle Image"
            >

            <div class="vehicle-info">

                <h2>
                    <?php
                    echo htmlspecialchars(
                        $car_brand . ' ' . $car_model
                    );
                    ?>
                </h2>

                <p>
                    <?php
                    echo htmlspecialchars(
                        $car_origin
                    );
                    ?>
                </p>

                <div class="status-badge
                    <?php

                    if ($status_lower === 'approved') {
                        echo 'status-approved';
                    }
                    elseif ($status_lower === 'rejected') {
                        echo 'status-rejected';
                    }
                    else {
                        echo 'status-pending';
                    }

                    ?>
                ">

                    <?php
                    echo htmlspecialchars($reservation_status);
                    ?>

                </div>

                <div class="info-grid">

                    <div class="info-card">

                        <h4>
                            Vehicle Year
                        </h4>

                        <p>
                            <?php echo htmlspecialchars($car_year); ?>
                        </p>

                    </div>

                    <div class="info-card">

                        <h4>
                            Preferred Variant
                        </h4>

                        <p>
                            <?php echo htmlspecialchars($car_variant); ?>
                        </p>

                    </div>

                    <div class="info-card">

                        <h4>
                            Vehicle Price
                        </h4>

                        <p>
                            RM <?php echo number_format($car_price, 2); ?>
                        </p>

                    </div>

                    <div class="info-card">

                        <h4>
                            Reservation Date
                        </h4>

                        <p>
                            <?php echo htmlspecialchars($created_at); ?>
                        </p>

                    </div>

                    <div class="info-card">

                        <h4>
                            Test Drive Schedule
                        </h4>

                        <p>
                            <?php echo htmlspecialchars($test_drive_at); ?>
                        </p>

                    </div>

                </div>

            </div>

        </div>

        <!-- CUSTOMER -->

        <div class="customer-section">

            <h3>
                Customer Information
            </h3>

            <div class="info-grid">

                <div class="info-card">

                    <h4>
                        Full Name
                    </h4>

                    <p>
                        <?php echo htmlspecialchars($user_name); ?>
                    </p>

                </div>

                <div class="info-card">

                    <h4>
                        Email Address
                    </h4>

                    <p>
                        <?php echo htmlspecialchars($user_email); ?>
                    </p>

                </div>

                <div class="info-card">

                    <h4>
                        Phone Number
                    </h4>

                    <p>
                        <?php echo htmlspecialchars($user_phone); ?>
                    </p>

                </div>

                <div class="info-card">

                    <h4>
                        IC / Passport
                    </h4>

                    <p>
                        <?php echo htmlspecialchars($user_ic); ?>
                    </p>

                </div>

            </div>

        </div>

        <!-- NOTE -->

        <div class="note-box">

            <p>
                Our sales advisor will contact you shortly
                to confirm your test drive appointment.
                Please keep your reservation reference number
                for future tracking and verification.
            </p>

        </div>

        <!-- BUTTONS -->

        <div class="button-group">

            <a
                href="cars.php"
                class="action-btn primary-btn"
            >
                Browse More Vehicles
            </a>

            <a
                href="index.php"
                class="action-btn secondary-btn"
            >
                Return Home
            </a>

        </div>

    </div>

</div>

<?php

// ======================================================
// CLEAN SESSION AFTER PAGE RENDERS
// ======================================================

unset($_SESSION['reservation_id']);
unset($_SESSION['ref_number']);

?>

</body>
</html>