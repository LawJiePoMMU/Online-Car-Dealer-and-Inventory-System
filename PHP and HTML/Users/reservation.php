<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

require '../Config/database.php';

// ======================================================
// SECURITY CHECK
// ======================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: Auth/login.php");
exit();
}

$user_id = $_SESSION['user_id'];

// ======================================================
// VALIDATE VEHICLE ID
// ======================================================

if (!isset($_GET['car_id']) || empty($_GET['car_id'])) {
    die("System Error: No vehicle was specified.");
}

$car_id = intval($_GET['car_id']);

if ($car_id <= 0) {
    die("Invalid vehicle reference.");
}

// ======================================================
// VERIFY VEHICLE EXISTS
// ======================================================

$car_sql = "
SELECT
    c.*,

    (
        SELECT car_image_url
        FROM car_image
        WHERE car_id = c.car_id
        LIMIT 1
    ) AS car_image

FROM cars c
WHERE c.car_id = ?
LIMIT 1
";

$car_stmt = mysqli_prepare($conn, $car_sql);

if (!$car_stmt) {
    die("Vehicle Query Prepare Failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($car_stmt, "i", $car_id);

if (!mysqli_stmt_execute($car_stmt)) {
    die("Vehicle Query Execute Failed: " . mysqli_stmt_error($car_stmt));
}

$car_result = mysqli_stmt_get_result($car_stmt);

if (!$car_result) {
    die("Vehicle Query Result Failed.");
}

$car = mysqli_fetch_assoc($car_result);

mysqli_stmt_close($car_stmt);

if (!$car) {
    die("The selected vehicle does not exist.");
}

// ======================================================
// FETCH USER DATA
// ======================================================

$user_sql = "
SELECT
    user_name,
    user_email,
    user_phone
FROM users
WHERE user_id = ?
LIMIT 1
";

$user_stmt = mysqli_prepare($conn, $user_sql);

if (!$user_stmt) {
    die("User Query Prepare Failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($user_stmt, "i", $user_id);

if (!mysqli_stmt_execute($user_stmt)) {
    die("User Query Execute Failed: " . mysqli_stmt_error($user_stmt));
}

$user_result = mysqli_stmt_get_result($user_stmt);

if (!$user_result) {
    die("User Query Result Failed.");
}

$user = mysqli_fetch_assoc($user_result);

mysqli_stmt_close($user_stmt);

// ======================================================
// FORM STATE
// ======================================================

$errors = [];

$res_name  = $_POST['user_name'] ?? ($user['user_name'] ?? '');
$res_email = $_POST['user_email'] ?? ($user['user_email'] ?? '');
$res_phone = $_POST['user_phone'] ?? ($user['user_phone'] ?? '');
$res_ic    = $_POST['user_ic'] ?? '';

$preferred_test_drive_at =
    $_POST['preferred_test_drive_at']
    ?? '';

$car_variant =
    $_POST['car_variant']
    ?? '';

// ======================================================
// PROCESS RESERVATION SUBMISSION
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ==========================
    // VALIDATION
    // ==========================

    if (empty(trim($res_name))) {
        $errors[] = "Full Name is required.";
    }

    if (empty(trim($res_email))) {
        $errors[] = "Email Address is required.";
    }

    if (empty(trim($res_phone))) {
        $errors[] = "Phone Number is required.";
    }

    if (empty(trim($res_ic))) {
        $errors[] = "IC / Passport Number is required.";
    }

    if (empty($car_variant)) {
        $errors[] = "Please select a preferred variant.";
    }

    if (empty($preferred_test_drive_at)) {
        $errors[] = "Please select your preferred test drive date.";
    }

    // Prevent Past Date
    if (
        !empty($preferred_test_drive_at) &&
        strtotime($preferred_test_drive_at) < time()
    ) {
        $errors[] =
        "Test drive date cannot be in the past.";
    }

    // ==========================
    // DUPLICATE RESERVATION CHECK
    // ==========================

    $duplicate_sql = "
    SELECT reservation_id
    FROM reservations
    WHERE user_id = ?
    AND car_id = ?
    AND reservation_status IN (
        'Pending',
        'Approved'
    )
    LIMIT 1
    ";

    $duplicate_stmt = mysqli_prepare($conn, $duplicate_sql);

    if ($duplicate_stmt) {

        mysqli_stmt_bind_param(
            $duplicate_stmt,
            "ii",
            $user_id,
            $car_id
        );

        mysqli_stmt_execute($duplicate_stmt);

        $duplicate_result =
            mysqli_stmt_get_result($duplicate_stmt);

        if (
            $duplicate_result &&
            mysqli_num_rows($duplicate_result) > 0
        ) {
            $errors[] =
            "You already have an active reservation for this vehicle.";
        }

        mysqli_stmt_close($duplicate_stmt);
    }

    // ==========================
    // SAVE RESERVATION
    // ==========================

    if (empty($errors)) {

        // Snapshot Data
        $snapshot_data = [

            // Customer
            'user_name'  => $res_name,
            'user_email' => $res_email,
            'user_phone' => $res_phone,
            'user_ic'    => $res_ic,

            // Vehicle
            'car_brand'   => $car['car_brand'],
            'car_model'   => $car['car_model'],
            'car_year'    => $car['car_year'],
            'car_origin'  => $car['car_origin'],
            'car_price'   => $car['car_price'],
            'car_variant' => $car_variant,
            'car_image'   => $car['car_image'] ?? ''
        ];

        // Encode Snapshot
        $snapshot_json = json_encode($snapshot_data);

        if ($snapshot_json === false) {
            die("Snapshot encoding failed.");
        }

        // Insert Reservation
        $insert_sql = "
        INSERT INTO reservations (

            user_id,
            car_id,
            reservation_status,
            preferred_test_drive_at,
            snapshot_data

        ) VALUES (?, ?, ?, ?, ?)
        ";

        $insert_stmt = mysqli_prepare($conn, $insert_sql);

        if (!$insert_stmt) {
            die("Reservation Prepare Failed: " . mysqli_error($conn));
        }

        $default_status = 'Pending';

        mysqli_stmt_bind_param(
            $insert_stmt,
            "iisss",
            $user_id,
            $car_id,
            $default_status,
            $preferred_test_drive_at,
            $snapshot_json
        );

        if (!mysqli_stmt_execute($insert_stmt)) {
            die("Reservation Insert Failed: " . mysqli_stmt_error($insert_stmt));
        }

        $reservation_id = mysqli_insert_id($conn);

        mysqli_stmt_close($insert_stmt);

        // ==========================
        // SESSION STORAGE
        // ==========================

        $_SESSION['reservation_id'] = $reservation_id;

        $_SESSION['ref_number'] =
            'RSV-' .
            str_pad(
                $reservation_id,
                6,
                '0',
                STR_PAD_LEFT
            );

        // ==========================
        // REDIRECT
        // ==========================

        header("Location: reservation_success.php");
        exit();
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
    Vehicle Test Drive Reservation
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
    color:#333;
    padding:40px 20px;
}

.form-container{
    max-width:700px;
    margin:0 auto;
    background:white;
    padding:40px;
    border-radius:20px;
    border:1px solid #e2e8f0;
    box-shadow:0 4px 20px rgba(0,0,0,0.03);
}

.form-header{
    margin-bottom:30px;
    border-bottom:2px solid #f1f5f9;
    padding-bottom:20px;
}

.form-header h2{
    font-size:30px;
    color:#1e293b;
    font-weight:700;
    margin-bottom:8px;
}

.form-header p{
    font-size:14px;
    color:#64748b;
    line-height:1.6;
}

.vehicle-preview{
    display:flex;
    gap:20px;
    margin-top:20px;
    align-items:center;
}

.vehicle-image{
    width:160px;
    height:110px;
    object-fit:cover;
    border-radius:14px;
    border:1px solid #e2e8f0;
}

.vehicle-info h3{
    font-size:22px;
    margin-bottom:6px;
    color:#1e293b;
}

.vehicle-info p{
    color:#64748b;
    font-size:14px;
    margin-bottom:5px;
}

.form-group{
    margin-bottom:22px;
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
    padding:13px 16px;
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

.btn-submit{
    width:100%;
    background:#2563eb;
    color:white;
    border:none;
    padding:15px;
    border-radius:12px;
    font-size:15px;
    font-weight:700;
    cursor:pointer;
    transition:0.2s;
    margin-top:10px;
}

.btn-submit:hover{
    background:#1d4ed8;
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

@media(max-width:768px){

    .form-container{
        padding:28px 20px;
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

<div class="form-container">

    <div class="form-header">

        <h2>
            Vehicle Test Drive Reservation
        </h2>

        <p>
            Submit your reservation request
            and schedule your preferred test drive session.
        </p>

        <div class="vehicle-preview">

            <img
                src="<?php echo !empty($car['car_image']) 
                ? htmlspecialchars($car['car_image']) 
                : 'https://via.placeholder.com/400x250?text=Vehicle+Image'; ?>"
                class="vehicle-image"
                alt="Vehicle Image"
            >

            <div class="vehicle-info">

                <h3>
                    <?php
                    echo htmlspecialchars(
                        $car['car_brand'] . ' ' .
                        $car['car_model']
                    );
                    ?>
                </h3>

                <p>
                    <?php
                    echo htmlspecialchars(
                        $car['car_origin']
                    );
                    ?>
                </p>

                <p>
                    RM <?php echo number_format($car['car_price'], 2); ?>
                </p>

            </div>

        </div>

    </div>

    <?php if (!empty($errors)): ?>

        <div class="error-box">

            <?php foreach ($errors as $error): ?>

                <p>
                    • <?php echo htmlspecialchars($error); ?>
                </p>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <form method="POST">

        <div class="form-group">

            <label>
                Full Name
            </label>

            <input
                type="text"
                name="user_name"
                class="form-input"
                value="<?php echo htmlspecialchars($res_name); ?>"
                required
            >

        </div>

        <div class="form-group">

            <label>
                Email Address
            </label>

            <input
                type="email"
                name="user_email"
                class="form-input"
                value="<?php echo htmlspecialchars($res_email); ?>"
                required
            >

        </div>

        <div class="form-group">

            <label>
                Phone Number
            </label>

            <input
                type="text"
                name="user_phone"
                class="form-input"
                value="<?php echo htmlspecialchars($res_phone); ?>"
                required
            >

        </div>

        <div class="form-group">

            <label>
                IC / Passport Number
            </label>

            <input
                type="text"
                name="user_ic"
                class="form-input"
                value="<?php echo htmlspecialchars($res_ic); ?>"
                required
            >

        </div>

        <div class="form-group">

            <label>
                Preferred Variant
            </label>

            <select
                name="car_variant"
                class="form-input"
                required
            >

                <option value="">
                    -- Select Variant --
                </option>

                <option
                    value="Standard Base"
                    <?php echo ($car_variant === 'Standard Base') ? 'selected' : ''; ?>
                >
                    Standard Base
                </option>

                <option
                    value="Executive Spec"
                    <?php echo ($car_variant === 'Executive Spec') ? 'selected' : ''; ?>
                >
                    Executive Spec
                </option>

                <option
                    value="Premium Sport RS"
                    <?php echo ($car_variant === 'Premium Sport RS') ? 'selected' : ''; ?>
                >
                    Premium Sport RS
                </option>

            </select>

        </div>

        <div class="form-group">

            <label>
                Preferred Test Drive Date & Time
            </label>

            <input
                type="datetime-local"
                name="preferred_test_drive_at"
                class="form-input"
                value="<?php echo htmlspecialchars($preferred_test_drive_at); ?>"
                required
            >

        </div>

        <button
            type="submit"
            class="btn-submit"
        >
            Submit Reservation Request
        </button>

    </form>

</div>

</body>
</html>