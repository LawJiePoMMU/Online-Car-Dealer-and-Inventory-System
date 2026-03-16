<?php
require_once 'database.php';

$res_cars = mysqli_query($conn, "SELECT COUNT(*) AS total_cars FROM cars");
$total_cars = mysqli_fetch_assoc($res_cars)['total_cars'];

$res_reservations = mysqli_query($conn, "SELECT COUNT(*) AS total_reservations FROM reservations");
$total_reservations = mysqli_fetch_assoc($res_reservations)['total_reservations'];

$res_users = mysqli_query($conn, "SELECT COUNT(*) AS total_users FROM users");
$total_users = mysqli_fetch_assoc($res_users)['total_users'];

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="assets/CSS/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <h1>Dashboard</h1>
        <div class="dashboard-cards">
            <div class="card">
                <div class="card-icon"><i class="fas fa-car"></i></div>
                <div class="card-info">
                    <h2><?php echo $total_cars; ?></h2>
                    <p>Total Cars</p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="card-info">
                    <h2><?php echo $total_reservations; ?></h2>
                    <p>Total Reservations</p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon"><i class="far fa-users"></i></div>
                <div class="card-info">
                    <h2><?php echo $total_users; ?></h2>
                    <p>Total Users</p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-box