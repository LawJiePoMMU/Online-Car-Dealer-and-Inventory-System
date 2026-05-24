<?php
session_start();

// ======================================================
// VALIDATE CAR ID
// ======================================================

if (!isset($_GET['car_id'])) {
    header("Location: index.php");
    exit();
}

$car_id = intval($_GET['car_id']);

if ($car_id <= 0) {
    header("Location: index.php");
    exit();
}

// ======================================================
// STORE BOOKING SESSION
// ======================================================

$_SESSION['booking_car_id'] = $car_id;

// ======================================================
// REDIRECT TO BOOKING PAGE
// ======================================================

header("Location: booking.php");
exit();
?>