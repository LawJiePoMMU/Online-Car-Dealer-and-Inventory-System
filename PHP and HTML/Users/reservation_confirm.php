<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if (!isset($_SESSION['res_car_id'])) { header("Location: reservation.php"); exit(); }

$car_id = $_SESSION['res_car_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Confirm Reservation</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="styles.css"/>
    <style>
        .page-hero { background:linear-gradient(135deg,var(--primary-color),#0056b3); color:white; padding:40px 0 30px; margin-bottom:30px; }
        .page-hero h1 { font-size:32px; font-weight:700; margin-bottom:6px; }
        .page-hero p  { opacity:0.85; font-size:15px; }

        .section-card { background:var(--card-bg); border-radius:8px; box-shadow:var(--shadow); padding:28px; margin-bottom:24px; }
        .section-card h2 { font-size:18px; margin-bottom:18px; color:var(--primary-color); border-bottom:2px solid #f0f0f0; padding-bottom:10px; }

        .confirm-grid { display:grid; grid-template-columns:200px 1fr; gap:24px; align-items:start; }
        .confirm-grid img { width:100%; border-radius:8px; object-fit:cover; height:140px; }
        .no-image { width:100%; height:140px; border-radius:8px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; color:#999; font-size:14px; }

        .info-table { width:100%; border-collapse:collapse; }
        .info-table td { padding:9px 12px; font-size:14px; border-bottom:1px solid #f0f0f0; }
        .info-table td:first-child { color:var(--text-light); font-weight:500; width:160px; }

        .fee-badge { display:inline-block; background:#e8f4ff; color:var(--primary-color); font-weight:700; font-size:22px; padding:10px 20px; border-radius:6px; }
        .confirm-note { font-size:13px; color:var(--text-light); margin-top:16px; font-style:italic; }

        .btn-group { display:flex; gap:12px; margin-top:8px; }
        .btn-secondary { background:#e2e2e2; color:#111; border:1px solid #d0d0d0; padding:12px 24px; border-radius:5px; font-family:'Poppins',sans-serif; font-weight:500; cursor:pointer; transition:var(--transition); }
        .btn-secondary:hover { background:#d0d0d0; }

        @media (max-width:600px) { .confirm-grid { grid-template-columns:1fr; } .btn-group { flex-direction:column; } }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="nav-logo">AutoDeal</a>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="downpayment.php">Down Payment</a></li>
            <li><a href="reservation.php">Reservation</a></li>
            <li><a href="view_status.php">View Status</a></li>
        </ul>
        <div class="nav-actions"></div>
    </div>
</nav>

<!-- HERO -->
<div class="page-hero">
    <div class="container">
        <h1>Confirm Your Reservation</h1>
        <p>Please review your details carefully before proceeding to payment.</p>
    </div>
</div>

<div class="container">

    <!-- Car Details -->
    <div class="section-card">
        <h2>Car Details</h2>
        <div class="confirm-grid">
            <?php if (!empty($_SESSION['res_image'])): ?>
                <img src="<?php echo htmlspecialchars($_SESSION['res_image']); ?>" alt="Car"/>
            <?php else: ?>
                <div class="no-image">No Image</div>
            <?php endif; ?>
            <table class="info-table">
                <tr><td>Brand</td><td><?php echo htmlspecialchars($_SESSION['res_brand']); ?></td></tr>
                <tr><td>Model</td><td><?php echo htmlspecialchars($_SESSION['res_model']); ?></td></tr>
                <tr><td>Year</td><td><?php echo htmlspecialchars($_SESSION['res_year']); ?></td></tr>
                <tr><td>Type</td><td><?php echo htmlspecialchars($_SESSION['res_origin']); ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Personal Info -->
    <div class="section-card">
        <h2>Personal Information</h2>
        <table class="info-table">
            <tr><td>Full Name</td><td><?php echo htmlspecialchars($_SESSION['res_name']); ?></td></tr>
            <tr><td>IC Number</td><td><?php echo htmlspecialchars($_SESSION['res_ic']); ?></td></tr>
            <tr><td>Phone Number</td><td><?php echo htmlspecialchars($_SESSION['res_phone']); ?></td></tr>
            <tr><td>Email Address</td><td><?php echo htmlspecialchars($_SESSION['res_email']); ?></td></tr>
            <tr><td>Preferred Visit Date</td><td><?php echo htmlspecialchars($_SESSION['res_date']); ?></td></tr>
        </table>
    </div>

    <!-- Payment Summary -->
    <div class="section-card">
        <h2>Payment Summary</h2>
        <div style="text-align:center; margin-bottom:20px;">
            <div class="fee-badge">RM 500.00</div>
            <p style="font-size:13px; color:var(--text-light); margin-top:6px;">One-time reservation fee</p>
        </div>
        <table class="info-table">
            <tr><td>Reservation Fee</td><td><strong>RM 500.00</strong></td></tr>
            <tr><td>Deductible From Purchase</td><td>Yes</td></tr>
            <tr><td>Valid For</td><td>7 days from payment date</td></tr>
            <tr><td>Cancellation Policy</td><td>Non-refundable after 48 hours</td></tr>
        </table>
        <p class="confirm-note">By proceeding, you confirm all the above details are correct.</p>
    </div>

    <!-- Action Buttons -->
    <form method="POST" action="payment.php">
        <input type="hidden" name="source"         value="reservation"/>
        <input type="hidden" name="car_id"         value="<?php echo $car_id; ?>"/>
        <input type="hidden" name="payment_amount" value="500.00"/>
        <input type="hidden" name="payment_label"  value="Reservation Fee — <?php echo htmlspecialchars($_SESSION['res_brand'].' '.$_SESSION['res_model']); ?>"/>
        <div class="btn-group">
            <button type="button" class="btn-secondary" onclick="window.history.back()">
                &larr; Edit Details
            </button>
            <button type="submit" class="btn-primary" style="flex:1; padding:14px; font-size:15px;">
                Confirm &amp; Pay RM500 &rarr;
            </button>
        </div>
    </form>

</div>

<footer class="footer text-center">
    <p>&copy; 2025 AutoDeal. All rights reserved.</p>
</footer>

</body>
</html>
