<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Make sure reservation exists
$reservation_id = $_SESSION['reservation_id'] ?? 0;

if ($reservation_id <= 0) {
    die("Invalid reservation reference.");
}

// Fetch reservation directly from database
$stmt = $pdo->prepare("
    SELECT * 
    FROM reservations 
    WHERE reservation_id = ?
");

$stmt->execute([$reservation_id]);

$reservation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reservation) {
    die("Reservation data could not be retrieved.");
}

// Decode snapshot JSON
$snapshot = json_decode(
    $reservation['snapshot_data'],
    true
);

// Customer Information
$reserve_name   = $snapshot['user_name']   ?? '';
$reserve_phone  = $snapshot['user_phone']  ?? '';
$reserve_email  = $snapshot['user_email']  ?? '';
$reserve_ic     = $snapshot['user_ic']     ?? '';

// Vehicle Information
$reserve_brand   = $snapshot['car_brand']   ?? 'Unknown Vehicle';
$reserve_model   = $snapshot['car_model']   ?? '';
$reserve_year    = $snapshot['car_year']    ?? '';
$reserve_origin  = $snapshot['car_origin']  ?? '';
$reserve_image   = $snapshot['car_image']   ?? '';
$reserve_plate   = $snapshot['car_plate']   ?? '';
$reserve_variant = $snapshot['car_variant'] ?? '';
$reserve_color   = $snapshot['car_color']   ?? '';

// Reservation Details
$reserve_datetime = $reservation['preferred_test_drive_at'] ?? '';
$status            = $reservation['reservation_status'] ?? 'Pending Viewing';

// Format Date & Time
$formatted_date = !empty($reserve_datetime)
    ? date('d M Y', strtotime($reserve_datetime))
    : 'Not Scheduled';

$formatted_time = !empty($reserve_datetime)
    ? date('h:i A', strtotime($reserve_datetime))
    : 'Not Scheduled';

// Generate Reference Number
$reserve_ref = 'RSV-' . str_pad(
    $reservation_id,
    6,
    '0',
    STR_PAD_LEFT
);
?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8"/>
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0"/>

    <title>Reservation Confirmation</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
          rel="stylesheet"/>

    <link rel="stylesheet"
          href="styles.css"/>

    <style>

        body {
            background: #f5f7fb;
            font-family: 'Poppins', sans-serif;
        }

        .page-hero {
            background: linear-gradient(
                135deg,
                var(--primary-color, #007bff),
                #0056b3
            );

            color: white;
            padding: 45px 0 35px;
            margin-bottom: 30px;
            text-align: center;
        }

        .page-hero h1 {
            font-size: 34px;
            margin-bottom: 10px;
        }

        .page-hero p {
            opacity: 0.9;
        }

        .section-card {
            background: #fff;
            border-radius: 10px;
            padding: 28px;
            margin-bottom: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }

        .section-card h2 {
            font-size: 18px;
            color: var(--primary-color, #007bff);
            margin-bottom: 18px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }

        .success-box {
            text-align: center;
            padding: 30px;
        }

        .success-icon {
            width: 90px;
            height: 90px;
            background: #28a745;
            color: white;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            font-weight: bold;
        }

        .status-badge {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 12px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .info-table td:first-child {
            width: 180px;
            color: #666;
            font-weight: 500;
        }

        .car-image {
            width: 100%;
            max-height: 260px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .btn-group {
            display: flex;
            gap: 16px;
            margin-top: 30px;
        }

        .btn-primary,
        .btn-secondary {
            flex: 1;
            text-align: center;
            padding: 14px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
        }

        .btn-primary {
            background: var(--primary-color, #007bff);
            color: white;
        }

        .btn-secondary {
            background: #f1f1f1;
            color: #333;
        }

        @media (max-width:768px) {

            .btn-group {
                flex-direction: column;
            }

        }

    </style>

</head>
<body>

<nav class="navbar">

    <div class="nav-container">

        <a href="index.php"
           class="nav-logo">

            AutoDeal

        </a>

        <ul class="nav-links">

            <li><a href="index.php">Home</a></li>
            <li><a href="booking.php">Booking</a></li>
            <li><a href="downpayment.php">Down Payment</a></li>
            <li><a href="view_status.php">View Status</a></li>

        </ul>

    </div>

</nav>

<div class="page-hero">

    <div class="container">

        <h1>Reservation Submitted</h1>

        <p>
            Your reserve / test drive request
            has been submitted successfully.
        </p>

    </div>

</div>

<div class="container"
     style="max-width:900px;
            margin:0 auto;
            padding:0 20px;">

    <div class="section-card success-box">

        <div class="success-icon">
            ✓
        </div>

        <h2 style="border:none;
                   margin-bottom:10px;">

            Request Successfully Submitted

        </h2>

        <p>
            Our team will review your
            reservation request shortly.
        </p>

        <div class="status-badge">

            <?php echo htmlspecialchars($status); ?>

        </div>

    </div>

    <div class="section-card">

        <h2>Reservation Reference</h2>

        <table class="info-table">

            <tr>
                <td>Reference Number</td>

                <td>
                    <strong>

                        <?php echo htmlspecialchars($reserve_ref); ?>

                    </strong>
                </td>
            </tr>

            <tr>
                <td>Status</td>

                <td>

                    <?php echo htmlspecialchars($status); ?>

                </td>
            </tr>

            <tr>
                <td>Submission Date</td>

                <td>

                    <?php echo date('d M Y'); ?>

                </td>
            </tr>

        </table>

    </div>

    <div class="section-card">

        <h2>Selected Vehicle</h2>

        <?php if (!empty($reserve_image)): ?>

            <img src="<?php echo htmlspecialchars($reserve_image); ?>"
                 class="car-image"
                 alt="Car Image"/>

        <?php endif; ?>

        <table class="info-table">

            <tr>
                <td>Brand</td>
                <td><?php echo htmlspecialchars($reserve_brand); ?></td>
            </tr>

            <tr>
                <td>Model</td>
                <td><?php echo htmlspecialchars($reserve_model); ?></td>
            </tr>

            <tr>
                <td>Year</td>
                <td><?php echo htmlspecialchars($reserve_year); ?></td>
            </tr>

            <tr>
                <td>Type</td>
                <td><?php echo htmlspecialchars($reserve_origin); ?></td>
            </tr>

            <tr>
                <td>Plate Number</td>
                <td><?php echo htmlspecialchars($reserve_plate); ?></td>
            </tr>

            <tr>
                <td>Variant</td>
                <td><?php echo htmlspecialchars($reserve_variant); ?></td>
            </tr>

            <tr>
                <td>Color</td>
                <td><?php echo htmlspecialchars($reserve_color); ?></td>
            </tr>

        </table>

    </div>

    <div class="section-card">

        <h2>Customer Information</h2>

        <table class="info-table">

            <tr>
                <td>Full Name</td>
                <td><?php echo htmlspecialchars($reserve_name); ?></td>
            </tr>

            <tr>
                <td>IC Number</td>
                <td><?php echo htmlspecialchars($reserve_ic); ?></td>
            </tr>

            <tr>
                <td>Phone Number</td>
                <td><?php echo htmlspecialchars($reserve_phone); ?></td>
            </tr>

            <tr>
                <td>Email Address</td>
                <td><?php echo htmlspecialchars($reserve_email); ?></td>
            </tr>

        </table>

    </div>

    <div class="section-card">

        <h2>Reservation Details</h2>

        <table class="info-table">

            <tr>
                <td>Preferred Date</td>

                <td>

                    <?php echo htmlspecialchars($formatted_date); ?>

                </td>
            </tr>

            <tr>
                <td>Preferred Time</td>

                <td>

                    <?php echo htmlspecialchars($formatted_time); ?>

                </td>
            </tr>

        </table>

    </div>

    <div class="btn-group">

        <a href="view_status.php"
           class="btn-primary">

            View Status

        </a>

        <a href="index.php"
           class="btn-secondary">

            Back to Home

        </a>

    </div>

</div>

<footer class="footer text-center"
        style="margin-top:60px;
               padding:20px 0;
               color:#aaa;">

    <p>
        &copy; 2026 AutoDeal.
        All rights reserved.
    </p>

</footer>

</body>
</html>