<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

require '../Config/database.php';

if (
    !isset($_SESSION['loggedin']) ||
    $_SESSION['loggedin'] !== true ||
    !isset($_SESSION['id']) ||
    strcasecmp($_SESSION['role'] ?? '', 'Customer') !== 0
) {
    header("Location: Auth/login.php");
    exit();
}


if (!isset($_GET['car_id']) || empty($_GET['car_id'])) {
    header("Location: index.php");
    exit();
}

$car_id = intval($_GET['car_id']);

if ($car_id <= 0) {
    header("Location: index.php");
    exit();
}


$check_sql = "
SELECT
    c.car_id,
    c.car_brand,
    c.car_model,
    COALESCE(cs.car_status_stock_quantity, 0) AS stock,
    COALESCE(cs.car_status_status, 'Inactive') AS car_status
FROM cars c
LEFT JOIN car_status cs ON cs.car_id = c.car_id
WHERE c.car_id = ?
LIMIT 1
";

$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "i", $car_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);
$car_check = mysqli_fetch_assoc($check_result);
mysqli_stmt_close($check_stmt);

if (!$car_check) {
    die("Selected vehicle does not exist.");
}

if (intval($car_check['stock']) <= 0) {
    die("Sorry, this vehicle is currently out of stock.");
}

$_SESSION['booking_car_id'] = $car_id;

unset(
    $_SESSION['booking_id'],
    $_SESSION['pay_amount'],
    $_SESSION['pay_source'],
    $_SESSION['pay_label'],
    $_SESSION['pay_ref']
);


header("Location: booking.php");
exit();