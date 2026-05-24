<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

require '../Config/database.php';

// ======================================================
// SECURITY CHECK
// ======================================================

if (
    !isset($_SESSION['user_id']) &&
    !isset($_SESSION['id'])
) {

    header("Location: Auth/login.php");
    exit();

}

$user_id = intval(
    $_SESSION['user_id']
    ?? $_SESSION['id']
    ?? 0
);

// ======================================================
// FETCH USER
// ======================================================

$user_sql = "
SELECT *
FROM users
WHERE user_id = ?
LIMIT 1
";

$user_stmt = mysqli_prepare($conn, $user_sql);

if (!$user_stmt) {
    die("User Query Prepare Failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $user_stmt,
    "i",
    $user_id
);

if (!mysqli_stmt_execute($user_stmt)) {
    die("User Query Execute Failed: " . mysqli_stmt_error($user_stmt));
}

$user_result = mysqli_stmt_get_result($user_stmt);

$user = mysqli_fetch_assoc($user_result);

mysqli_stmt_close($user_stmt);

if (!$user) {
    die("User account not found.");
}

// ======================================================
// CHECK SELECTED CAR SESSION
// ======================================================

$car_id =
intval($_SESSION['booking_car_id'] ?? 0);

if ($car_id <= 0) {
    header("Location: index.php");
    exit();
}

// ======================================================
// FETCH CAR
// ======================================================

$car_sql = "
SELECT
    c.*,

    cs.car_status_price

FROM cars c

LEFT JOIN car_status cs
ON cs.car_id = c.car_id

WHERE c.car_id = ?
LIMIT 1
";

$car_stmt = mysqli_prepare($conn, $car_sql);

if (!$car_stmt) {
    die("Car Query Prepare Failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $car_stmt,
    "i",
    $car_id
);

if (!mysqli_stmt_execute($car_stmt)) {
    die("Car Query Execute Failed: " . mysqli_stmt_error($car_stmt));
}

$car_result = mysqli_stmt_get_result($car_stmt);

$car = mysqli_fetch_assoc($car_result);

mysqli_stmt_close($car_stmt);

if (!$car) {
    die("Selected vehicle not found.");
}

// ======================================================
// FETCH CAR IMAGE
// ======================================================

$image_sql = "
SELECT car_image_url
FROM car_image
WHERE car_id = ?
LIMIT 1
";

$image_stmt = mysqli_prepare($conn, $image_sql);

if (!$image_stmt) {
    die("Image Query Prepare Failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $image_stmt,
    "i",
    $car_id
);

mysqli_stmt_execute($image_stmt);

$image_result = mysqli_stmt_get_result($image_stmt);

$image_data = mysqli_fetch_assoc($image_result);

mysqli_stmt_close($image_stmt);

$car_image =
    !empty($image_data['car_image_url'])
    ? $image_data['car_image_url']
    : 'https://via.placeholder.com/500x300.png?text=Vehicle+Image';

// ======================================================
// USED CAR DETAILS
// ======================================================

$used_car = null;

if (($car['car_origin'] ?? '') === 'Used Car') {

    $used_sql = "
    SELECT *
    FROM used_car_details
    WHERE car_id = ?
    LIMIT 1
    ";

    $used_stmt = mysqli_prepare($conn, $used_sql);

    if ($used_stmt) {

        mysqli_stmt_bind_param(
            $used_stmt,
            "i",
            $car_id
        );

        mysqli_stmt_execute($used_stmt);

        $used_result =
            mysqli_stmt_get_result($used_stmt);

        $used_car =
            mysqli_fetch_assoc($used_result);

        mysqli_stmt_close($used_stmt);
    }
}

// ======================================================
// FINANCIAL CALCULATION
// ======================================================

$car_price =
    floatval($car['car_status_price'] ?? 0);

$insurance_amount = 3000.00;
$booking_fee      = 500.00;

$total_price =
    $car_price +
    $insurance_amount +
    $booking_fee;

$display_years =
    intval($_POST['loan_years'] ?? 5);

if ($display_years <= 0) {
    $display_years = 5;
}

$estimated_monthly =
    $total_price /
    ($display_years * 12);

// ======================================================
// FORM VALUES
// ======================================================

$errors = [];

$res_name =
    trim(
        $_POST['res_name']
        ?? ($user['user_name'] ?? '')
    );

$res_ic =
    trim(
        $_POST['res_ic']
        ?? ''
    );

$res_phone =
    trim(
        $_POST['res_phone']
        ?? ($user['user_phone'] ?? '')
    );

$res_email =
    trim(
        $_POST['res_email']
        ?? ($user['user_email'] ?? '')
    );

$car_color =
    trim(
        $_POST['car_color']
        ?? ''
    );

$car_variant =
    trim(
        $_POST['car_variant']
        ?? ''
    );

// ======================================================
// FILE UPLOAD FUNCTION
// ======================================================

function uploadFile($field_name, $upload_dir)
{
    if (
        isset($_FILES[$field_name]) &&
        $_FILES[$field_name]['error'] === UPLOAD_ERR_OK
    ) {

        // FILE SIZE LIMIT
        if (
            $_FILES[$field_name]['size']
            > 5 * 1024 * 1024
        ) {
            return false;
        }

        $ext = strtolower(
            pathinfo(
                $_FILES[$field_name]['name'],
                PATHINFO_EXTENSION
            )
        );

        $allowed_ext =
            ['jpg', 'jpeg', 'png', 'pdf'];

        if (!in_array($ext, $allowed_ext)) {
            return false;
        }

        // MIME VALIDATION
        $finfo =
            finfo_open(FILEINFO_MIME_TYPE);

        $mime =
            finfo_file(
                $finfo,
                $_FILES[$field_name]['tmp_name']
            );

        finfo_close($finfo);

        $allowed_mime = [
            'image/jpeg',
            'image/png',
            'application/pdf'
        ];

        if (!in_array($mime, $allowed_mime)) {
            return false;
        }

        $filename =
            time() .
            '_' .
            bin2hex(random_bytes(5)) .
            '.' .
            $ext;

        $target =
            $upload_dir .
            $filename;

        if (
            move_uploaded_file(
                $_FILES[$field_name]['tmp_name'],
                $target
            )
        ) {
            return $target;
        }
    }

    return null;
}

// ======================================================
// HANDLE FORM SUBMISSION
// ======================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['submit_booking'])
) {

    // ==================================================
    // BASIC VALIDATION
    // ==================================================

    if (empty($res_name)) {
        $errors[] = "Full Name is required.";
    }

    if (empty($res_ic)) {
        $errors[] = "IC Number is required.";
    }

    if (
        empty($res_phone) ||
        strlen($res_phone) < 9
    ) {
        $errors[] = "Valid phone number is required.";
    }

    if (
        empty($res_email) ||
        !filter_var(
            $res_email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        $errors[] = "Valid email address is required.";
    }

    // ==================================================
    // NEW CAR VALIDATION
    // ==================================================

    if (($car['car_origin'] ?? '') === 'New Car') {

        if (empty($car_color)) {
            $errors[] =
            "Please select a car color.";
        }

        if (empty($car_variant)) {
            $errors[] =
            "Please select a car variant.";
        }
    }

    // ==================================================
    // UPLOAD DIRECTORY
    // ==================================================

    $upload_dir =
        'uploads/documents/';

    if (!is_dir($upload_dir)) {

        mkdir(
            $upload_dir,
            0755,
            true
        );
    }

    if (!is_writable($upload_dir)) {
        $errors[] =
        "Upload directory is not writable.";
    }

    // ==================================================
    // HANDLE IC DOCUMENT
    // ==================================================

    $ic_document =
        uploadFile(
            'ic_document',
            $upload_dir
        );

    if ($ic_document === false) {

        $errors[] =
        "IC Document must be JPG, PNG, or PDF under 5MB.";

    } elseif ($ic_document) {

        $_SESSION['tmp_ic_document'] =
            $ic_document;
    }

    // ==================================================
    // HANDLE LICENSE DOCUMENT
    // ==================================================

    $license_document =
        uploadFile(
            'license_document',
            $upload_dir
        );

    if ($license_document === false) {

        $errors[] =
        "Driving License must be JPG, PNG, or PDF under 5MB.";

    } elseif ($license_document) {

        $_SESSION['tmp_license_document'] =
            $license_document;
    }

    // ==================================================
    // HANDLE PAYSLIP DOCUMENT
    // ==================================================

    $payslip_document =
        uploadFile(
            'payslip_document',
            $upload_dir
        );

    if ($payslip_document === false) {

        $errors[] =
        "Payslip must be JPG, PNG, or PDF under 5MB.";

    } elseif ($payslip_document) {

        $_SESSION['tmp_payslip_document'] =
            $payslip_document;
    }

    // ==================================================
    // HANDLE BANK STATEMENT
    // ==================================================

    $bank_document =
        uploadFile(
            'bank_statement_document',
            $upload_dir
        );

    if ($bank_document === false) {

        $errors[] =
        "Bank Statement must be JPG, PNG, or PDF under 5MB.";

    } elseif ($bank_document) {

        $_SESSION['tmp_bank_statement_document'] =
            $bank_document;
    }

    // ==================================================
    // REQUIRED FILE CHECK
    // ==================================================

    if (empty($_SESSION['tmp_ic_document'])) {
        $errors[] =
        "IC Document upload is required.";
    }

    if (empty($_SESSION['tmp_license_document'])) {
        $errors[] =
        "Driving License upload is required.";
    }

    if (empty($_SESSION['tmp_payslip_document'])) {
        $errors[] =
        "Payslip upload is required.";
    }

    if (empty($_SESSION['tmp_bank_statement_document'])) {
        $errors[] =
        "Bank Statement upload is required.";
    }

    // ==================================================
    // FINAL PROCESSING
    // ==================================================

    if (empty($errors)) {

        $loan_years =
            intval($_POST['loan_years'] ?? 5);

        if ($loan_years <= 0) {
            $loan_years = 5;
        }

        $monthly_payment =
            $total_price /
            ($loan_years * 12);

        // ==============================================
        // SESSION STORAGE
        // ==============================================

        $_SESSION['res_name'] =
            $res_name;

        $_SESSION['res_ic'] =
            $res_ic;

        $_SESSION['res_phone'] =
            $res_phone;

        $_SESSION['res_email'] =
            $res_email;

        $_SESSION['car_color'] =
            (($car['car_origin'] ?? '') === 'Used Car')
            ? ($used_car['car_color'] ?? 'Default')
            : $car_color;

        $_SESSION['car_variant'] =
            (($car['car_origin'] ?? '') === 'Used Car')
            ? 'Used Vehicle'
            : $car_variant;

        $_SESSION['loan_years'] =
            $loan_years;

        $_SESSION['insurance_amount'] =
            round($insurance_amount, 2);

        $_SESSION['monthly_payment'] =
            round($monthly_payment, 2);

        $_SESSION['total_price'] =
            round($total_price, 2);

        $_SESSION['pay_amount'] =
            round($booking_fee, 2);

        $_SESSION['res_brand'] =
            $car['car_brand'] ?? '';

        $_SESSION['res_model'] =
            $car['car_model'] ?? '';

        $_SESSION['res_year'] =
            $car['car_year'] ?? '';

        $_SESSION['res_origin'] =
            $car['car_origin'] ?? '';

        $_SESSION['res_image'] =
            $car_image;

        $_SESSION['car_plate'] =
            $used_car['car_plate'] ?? '';

        // ==============================================
        // DOCUMENT STORAGE
        // ==============================================

        $_SESSION['ic_document'] =
            $_SESSION['tmp_ic_document'];

        $_SESSION['license_document'] =
            $_SESSION['tmp_license_document'];

        $_SESSION['payslip_document'] =
            $_SESSION['tmp_payslip_document'];

        $_SESSION['bank_statement_document'] =
            $_SESSION['tmp_bank_statement_document'];

        // ==============================================
        // CLEAN TEMP SESSION
        // ==============================================

        unset(
            $_SESSION['tmp_ic_document'],
            $_SESSION['tmp_license_document'],
            $_SESSION['tmp_payslip_document'],
            $_SESSION['tmp_bank_statement_document']
        );

        // ==============================================
        // REDIRECT TO PAYMENT
        // ==============================================
// ==============================================
// CREATE BOOKING RECORD
// ==============================================

$insert_booking_sql = "
INSERT INTO bookings (

    user_id,
    car_id,
    booking_status

)
VALUES (

    ?,
    ?,
    'Pending Payment'

)
";

$insert_booking_stmt =
mysqli_prepare(
    $conn,
    $insert_booking_sql
);

if (!$insert_booking_stmt) {

    die(
        'Booking Prepare Failed: '
        . mysqli_error($conn)
    );

}

mysqli_stmt_bind_param(
    $insert_booking_stmt,
    "ii",
    $user_id,
    $car_id
);

$booking_execute =
mysqli_stmt_execute(
    $insert_booking_stmt
);

if (!$booking_execute) {

    die(
        'Booking Insert Failed: '
        . mysqli_error($conn)
    );

}

$_SESSION['booking_id'] =
mysqli_insert_id($conn);

$_SESSION['pay_source'] =
'booking';
        header("Location: payment.php");
        exit();
    }
}

?>

<!-- KEEP YOUR ORIGINAL HTML/UI BELOW THIS LINE -->
 <!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    Vehicle Financing Checkout
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
    background:#f5f7fb;
    color:#333;
    padding-bottom:60px;
}

.navbar{
    background:white;
    padding:18px 40px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 2px 12px rgba(0,0,0,0.05);
    margin-bottom:30px;
}

.nav-logo{
    font-size:24px;
    font-weight:700;
    color:#2563eb;
    text-decoration:none;
}

.container{
    max-width:850px;
    margin:0 auto;
    padding:30px;
    background:white;
    border-radius:18px;
    border:1px solid #e2e8f0;
    box-shadow:0 4px 18px rgba(0,0,0,0.03);
}

.vehicle-preview{
    display:flex;
    gap:20px;
    align-items:center;
    margin-bottom:30px;
    padding-bottom:25px;
    border-bottom:2px solid #f1f5f9;
}

.vehicle-image{
    width:240px;
    height:160px;
    object-fit:cover;
    border-radius:14px;
    border:1px solid #e2e8f0;
}

.vehicle-info h2{
    font-size:28px;
    margin-bottom:8px;
    color:#1e293b;
}

.vehicle-info p{
    color:#64748b;
    font-size:14px;
    margin-bottom:5px;
}

.badge{
    display:inline-block;
    margin-top:8px;
    padding:7px 14px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
}

.badge-new{
    background:#dcfce7;
    color:#166534;
}

.badge-used{
    background:#fef3c7;
    color:#92400e;
}

.section-title{
    margin-top:32px;
    margin-bottom:18px;
    color:#2563eb;
    font-size:18px;
    font-weight:700;
    border-bottom:2px solid #f1f5f9;
    padding-bottom:8px;
}

.form-group{
    margin-bottom:18px;
    display:flex;
    flex-direction:column;
}

.form-group label{
    font-size:14px;
    font-weight:600;
    color:#475569;
    margin-bottom:8px;
}

.form-input{
    width:100%;
    padding:13px 15px;
    border:1px solid #cbd5e1;
    border-radius:10px;
    font-size:14px;
    transition:0.2s;
}

.form-input:focus{
    outline:none;
    border-color:#2563eb;
    box-shadow:0 0 0 3px rgba(37,99,235,0.10);
}

.readonly-box{
    background:#f1f5f9;
    color:#64748b;
    font-weight:600;
    cursor:not-allowed;
}

.summary-box{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    padding:22px;
    border-radius:14px;
    margin-top:28px;
}

.summary-box h3{
    margin-bottom:16px;
    font-size:18px;
    color:#1e293b;
}

.summary-item{
    display:flex;
    justify-content:space-between;
    padding:10px 0;
    font-size:14px;
    color:#475569;
}

.summary-total{
    display:flex;
    justify-content:space-between;
    margin-top:15px;
    padding-top:15px;
    border-top:1px solid #dbe3ee;
    font-size:17px;
    font-weight:700;
    color:#2563eb;
}

.error-box{
    background:#fef2f2;
    border:1px solid #fecaca;
    color:#991b1b;
    padding:16px;
    border-radius:12px;
    margin-bottom:24px;
}

.error-box p{
    margin:5px 0;
    font-size:14px;
}

.upload-status{
    font-size:12px;
    color:#16a34a;
    margin-top:-10px;
    margin-bottom:14px;
    font-weight:600;
}

.btn-submit{
    width:100%;
    background:#2563eb;
    color:white;
    border:none;
    padding:16px;
    border-radius:12px;
    font-size:16px;
    font-weight:700;
    cursor:pointer;
    transition:0.2s;
    margin-top:30px;
}

.btn-submit:hover{
    background:#1d4ed8;
}

.note-box{
    margin-top:28px;
    background:#eff6ff;
    border-left:5px solid #2563eb;
    padding:18px;
    border-radius:12px;
}

.note-box p{
    font-size:14px;
    line-height:1.7;
    color:#475569;
}

@media(max-width:768px){

    .container{
        padding:24px 18px;
    }

    .vehicle-preview{
        flex-direction:column;
        align-items:flex-start;
    }

    .vehicle-image{
        width:100%;
        height:220px;
    }
}

</style>

</head>

<body>

<nav class="navbar">

    <a href="index.php"
       class="nav-logo">
        AutoDeal
    </a>

</nav>

<div class="container">

    <!-- ERROR DISPLAY -->

    <?php if (!empty($errors)): ?>

        <div class="error-box">

            <?php foreach ($errors as $error): ?>

                <p>
                    • <?php echo htmlspecialchars($error); ?>
                </p>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <!-- FORM -->

    <form
        method="POST"
        enctype="multipart/form-data"
    >

        <!-- VEHICLE PREVIEW -->

        <div class="vehicle-preview">

            <img
                src="<?php echo htmlspecialchars($car_image); ?>"
                class="vehicle-image"
                alt="Vehicle Image"
            >

            <div class="vehicle-info">

                <h2>
                    <?php
                    echo htmlspecialchars(
                        $car['car_brand'] . ' ' .
                        $car['car_model']
                    );
                    ?>
                </h2>

                <p>
                    <?php
                    echo htmlspecialchars(
                        $car['car_origin']
                    );
                    ?>
                </p>

                <p>
                    RM <?php
                    echo number_format(
                        $car_price,
                        2
                    );
                    ?>
                </p>

                <?php if ($car['car_origin'] === 'New Car'): ?>

                    <span class="badge badge-new">
                        Brand New Vehicle
                    </span>

                <?php else: ?>

                    <span class="badge badge-used">
                        Used Vehicle Inventory
                    </span>

                <?php endif; ?>

            </div>

        </div>

        <!-- CUSTOMER DETAILS -->

        <h3 class="section-title">
            1. Customer Details
        </h3>

        <div class="form-group">

            <label>
                Full Name
            </label>

            <input
                type="text"
                name="res_name"
                class="form-input"
                value="<?php echo htmlspecialchars($res_name); ?>"
                required
            >

        </div>

        <div class="form-group">

            <label>
                IC / Passport Number
            </label>

            <input
                type="text"
                name="res_ic"
                class="form-input"
                value="<?php echo htmlspecialchars($res_ic); ?>"
                required
            >

        </div>

        <div class="form-group">

            <label>
                Phone Number
            </label>

            <input
                type="text"
                name="res_phone"
                class="form-input"
                value="<?php echo htmlspecialchars($res_phone); ?>"
                required
            >

        </div>

        <div class="form-group">

            <label>
                Email Address
            </label>

            <input
                type="email"
                name="res_email"
                class="form-input"
                value="<?php echo htmlspecialchars($res_email); ?>"
                required
            >

        </div>

        <!-- VEHICLE CONFIGURATION -->

        <h3 class="section-title">
            2. Vehicle Configuration
        </h3>

        <?php if ($car['car_origin'] === 'New Car'): ?>

            <div class="form-group">

                <label>
                    Exterior Color
                </label>

                <select
                    name="car_color"
                    class="form-input"
                    required
                >

                    <option value="">
                        Choose Exterior Color
                    </option>

                    <option
                        value="White"
                        <?php echo ($car_color === 'White') ? 'selected' : ''; ?>
                    >
                        White
                    </option>

                    <option
                        value="Black"
                        <?php echo ($car_color === 'Black') ? 'selected' : ''; ?>
                    >
                        Black
                    </option>

                    <option
                        value="Red"
                        <?php echo ($car_color === 'Red') ? 'selected' : ''; ?>
                    >
                        Red
                    </option>

                    <option
                        value="Blue"
                        <?php echo ($car_color === 'Blue') ? 'selected' : ''; ?>
                    >
                        Blue
                    </option>

                </select>

            </div>

            <div class="form-group">

                <label>
                    Vehicle Variant
                </label>

                <select
                    name="car_variant"
                    class="form-input"
                    required
                >

                    <option value="">
                        Choose Variant
                    </option>

                    <option
                        value="Standard"
                        <?php echo ($car_variant === 'Standard') ? 'selected' : ''; ?>
                    >
                        Standard
                    </option>

                    <option
                        value="Premium"
                        <?php echo ($car_variant === 'Premium') ? 'selected' : ''; ?>
                    >
                        Premium
                    </option>

                    <option
                        value="Full Spec"
                        <?php echo ($car_variant === 'Full Spec') ? 'selected' : ''; ?>
                    >
                        Full Spec
                    </option>

                </select>

            </div>

        <?php else: ?>

            <div class="form-group">

                <label>
                    Vehicle Color
                </label>

                <input
                    type="text"
                    class="form-input readonly-box"
                    value="<?php echo htmlspecialchars($used_car['car_color'] ?? 'Unknown'); ?>"
                    readonly
                >

            </div>

            <div class="form-group">

                <label>
                    Vehicle Plate Reference
                </label>

                <input
                    type="text"
                    class="form-input readonly-box"
                    value="<?php echo htmlspecialchars($used_car['car_plate'] ?? 'N/A'); ?>"
                    readonly
                >

            </div>

            <input
                type="hidden"
                name="car_color"
                value="<?php echo htmlspecialchars($used_car['car_color'] ?? ''); ?>"
            >

            <input
                type="hidden"
                name="car_variant"
                value="Used Vehicle"
            >

        <?php endif; ?>

        <!-- FINANCING -->

        <h3 class="section-title">
            3. Financing Preferences
        </h3>

        <div class="form-group">

            <label>
                Financing Loan Tenure
            </label>

            <select
                name="loan_years"
                id="loan_years"
                class="form-input"
                required
            >

                <option
                    value="5"
                    <?php echo ($display_years === 5) ? 'selected' : ''; ?>
                >
                    5 Years
                </option>

                <option
                    value="7"
                    <?php echo ($display_years === 7) ? 'selected' : ''; ?>
                >
                    7 Years
                </option>

                <option
                    value="9"
                    <?php echo ($display_years === 9) ? 'selected' : ''; ?>
                >
                    9 Years
                </option>

            </select>

        </div>

        <!-- DOCUMENTS -->

        <h3 class="section-title">
            4. Mandatory Financing Documents
        </h3>

        <div class="form-group">

            <label>
                IC Document Copy
            </label>

            <input
                type="file"
                name="ic_document"
                class="form-input"
                <?php echo empty($_SESSION['tmp_ic_document']) ? 'required' : ''; ?>
            >

            <?php if (!empty($_SESSION['tmp_ic_document'])): ?>

                <span class="upload-status">
                    ✓ IC Document cached from previous upload
                </span>

            <?php endif; ?>

        </div>

        <div class="form-group">

            <label>
                Driving License
            </label>

            <input
                type="file"
                name="license_document"
                class="form-input"
                <?php echo empty($_SESSION['tmp_license_document']) ? 'required' : ''; ?>
            >

            <?php if (!empty($_SESSION['tmp_license_document'])): ?>

                <span class="upload-status">
                    ✓ Driving License cached from previous upload
                </span>

            <?php endif; ?>

        </div>

        <div class="form-group">

            <label>
                Recent Payslip
            </label>

            <input
                type="file"
                name="payslip_document"
                class="form-input"
                <?php echo empty($_SESSION['tmp_payslip_document']) ? 'required' : ''; ?>
            >

            <?php if (!empty($_SESSION['tmp_payslip_document'])): ?>

                <span class="upload-status">
                    ✓ Payslip cached from previous upload
                </span>

            <?php endif; ?>

        </div>

        <div class="form-group">

            <label>
                3-Month Bank Statement
            </label>

            <input
                type="file"
                name="bank_statement_document"
                class="form-input"
                <?php echo empty($_SESSION['tmp_bank_statement_document']) ? 'required' : ''; ?>
            >

            <?php if (!empty($_SESSION['tmp_bank_statement_document'])): ?>

                <span class="upload-status">
                    ✓ Bank Statement cached from previous upload
                </span>

            <?php endif; ?>

        </div>

        <!-- SUMMARY -->

        <div class="summary-box">

            <h3>
                Estimated Billing Summary
            </h3>

            <div class="summary-item">

                <span>
                    Vehicle Asset Price
                </span>

                <strong>
                    RM <?php echo number_format($car_price, 2); ?>
                </strong>

            </div>

            <div class="summary-item">

                <span>
                    Comprehensive Insurance
                </span>

                <strong>
                    RM <?php echo number_format($insurance_amount, 2); ?>
                </strong>

            </div>

            <div class="summary-item">

                <span>
                    Booking Fee (Due Now)
                </span>

                <strong style="color:#16a34a;">
                    RM <?php echo number_format($booking_fee, 2); ?>
                </strong>

            </div>

            <div class="summary-total">

                <span>
                    Estimated Monthly Installment
                </span>

                <span id="monthly_payment">
                    RM <?php echo number_format($estimated_monthly, 2); ?> / mo
                </span>

            </div>

        </div>

        <!-- NOTE -->

        <div class="note-box">

            <p>
                After payment confirmation,
                your submitted financing documents
                will be reviewed by our financing department.
                Loan approval status will be updated
                inside your vehicle booking status dashboard.
            </p>

        </div>

        <!-- BUTTON -->

        <button
            type="submit"
            name="submit_booking"
            value="1"
            class="btn-submit"
        >
            Confirm & Proceed to Payment →
        </button>

    </form>

</div>

<script>

const loanSelect =
document.getElementById('loan_years');

const monthlyText =
document.getElementById('monthly_payment');

const totalPrice =
<?php echo json_encode($total_price); ?>;

function updateLoanCalculation(){

    const years =
    parseInt(loanSelect.value);

    const monthly =
    totalPrice / (years * 12);

    monthlyText.innerHTML =
    'RM ' +
    monthly.toFixed(2) +
    ' / mo';
}

loanSelect.addEventListener(
    'change',
    updateLoanCalculation
);

</script>

</body>
</html>