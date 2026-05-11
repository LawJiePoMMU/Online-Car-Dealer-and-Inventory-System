<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$car_id = $_SESSION['selected_car_id'] ?? null;
$car = null;
$car_images = [];

if ($car_id) {
    $stmt = $pdo->prepare("SELECT * FROM cars WHERE car_id = ?");
    $stmt->execute([$car_id]);
    $car = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt2 = $pdo->prepare("SELECT car_image_url FROM car_image WHERE car_id = ?");
    $stmt2->execute([$car_id]);
    $car_images = $stmt2->fetchAll(PDO::FETCH_ASSOC);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservation_date = $_POST['reservation_date'] ?? '';
    if (empty($reservation_date)) $errors[] = "Please select a preferred visit date.";
    if (!$car_id) $errors[] = "No car selected. Please go back and select a car.";

    if (empty($errors)) {
        $_SESSION['res_car_id'] = $car_id;
        $_SESSION['res_date']   = $reservation_date;
        $_SESSION['res_name']  = $_POST['res_name'];
$_SESSION['res_ic']    = $_POST['res_ic'];
$_SESSION['res_phone'] = $_POST['res_phone'];
$_SESSION['res_email'] = $_POST['res_email'];
        $_SESSION['res_brand']  = $car['car_brand']  ?? '';
        $_SESSION['res_model']  = $car['car_model']  ?? '';
        $_SESSION['res_year']   = $car['car_year']   ?? '';
        $_SESSION['res_origin'] = $car['car_origin'] ?? '';
        $_SESSION['res_image']  = !empty($car_images) ? $car_images[0]['car_image_url'] : null;
        header("Location: reservation_confirm.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Car Reservation</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="styles.css"/>
    <style>
        .page-hero { background: linear-gradient(135deg, var(--primary-color), #0056b3); color:white; padding:40px 0 30px; margin-bottom:30px; }
        .page-hero h1 { font-size:32px; font-weight:700; margin-bottom:6px; }
        .page-hero p  { opacity:0.85; font-size:15px; }

        .section-card { background:var(--card-bg); border-radius:8px; box-shadow:var(--shadow); padding:28px; margin-bottom:24px; }
        .section-card h2 { font-size:18px; margin-bottom:18px; color:var(--primary-color); border-bottom:2px solid #f0f0f0; padding-bottom:10px; }

        .car-gallery { display:grid; grid-template-columns:1fr 1fr; gap:24px; align-items:start; }
        .main-img { width:100%; border-radius:8px; object-fit:cover; height:220px; cursor:pointer; }
        .thumb-row { display:flex; gap:8px; margin-top:8px; flex-wrap:wrap; }
        .thumb-small { width:70px; height:50px; object-fit:cover; border-radius:4px; cursor:pointer; border:2px solid transparent; transition:border-color 0.2s; }
        .thumb-small.active, .thumb-small:hover { border-color:var(--primary-color); }
        .no-image { width:100%; height:220px; border-radius:8px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; color:#999; font-size:14px; }

        .info-table { width:100%; border-collapse:collapse; }
        .info-table td { padding:9px 12px; font-size:14px; border-bottom:1px solid #f0f0f0; }
        .info-table td:first-child { color:var(--text-light); font-weight:500; width:140px; }

        .summary-table { width:100%; border-collapse:collapse; }
        .summary-table td { padding:10px 14px; font-size:14px; border-bottom:1px solid #f0f0f0; }
        .summary-table td:first-child { color:var(--text-light); width:200px; }
        .summary-table td:last-child { font-weight:600; }

        .user-info-box { background:#f8f9fa; border-radius:8px; padding:16px 20px; margin-bottom:20px; }
        .user-info-box p { font-size:14px; color:var(--text-light); margin-top:8px; }
        .user-info-box a { color:var(--primary-color); }

        .alert-error { background:#f8d7da; border:1px solid #f5c6cb; border-radius:6px; padding:14px 18px; margin-bottom:20px; color:#721c24; font-size:14px; }
        .alert { background:#fff3cd; border:1px solid #ffc107; border-radius:6px; padding:14px 18px; margin-bottom:20px; font-size:14px; }
        .alert a { color:var(--primary-color); }

        .checkbox-label { display:flex; align-items:flex-start; gap:10px; font-size:14px; color:var(--text-light); cursor:pointer; }
        .checkbox-label input { margin-top:3px; accent-color:var(--primary-color); }

        .fee-badge { display:inline-block; background:#e8f4ff; color:var(--primary-color); font-weight:700; font-size:20px; padding:8px 18px; border-radius:6px; margin-bottom:4px; }

        @media (max-width:768px) { .car-gallery { grid-template-columns:1fr; } }
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
        <div class="nav-actions">
            <span style="font-size:14px; color:var(--text-light);">
                Welcome, <strong><?php echo htmlspecialchars($user['user_name']); ?></strong>
            </span>
        </div>
    </div>
</nav>

<!-- HERO -->
<div class="page-hero">
    <div class="container">
        <h1>Car Reservation</h1>
        <p>Reserve your desired car with a one-time reservation fee of RM500.00.</p>
    </div>
</div>

<div class="container">

    <?php if (!empty($errors)): ?>
    <div class="alert-error">
        <?php foreach ($errors as $e): ?><p><?php echo htmlspecialchars($e); ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($car): ?>

    <!-- Car Info -->
    <div class="section-card">
        <h2>Car You Are Reserving</h2>
        <div class="car-gallery">
            <div>
                <?php if (!empty($car_images)): ?>
                    <img src="<?php echo htmlspecialchars($car_images[0]['car_image_url']); ?>"
                         alt="Car" class="main-img" id="main-img"/>
                    <?php if (count($car_images) > 1): ?>
                    <div class="thumb-row">
                        <?php foreach ($car_images as $i => $img): ?>
                        <img src="<?php echo htmlspecialchars($img['car_image_url']); ?>"
                             class="thumb-small <?php echo $i===0?'active':''; ?>"
                             onclick="document.getElementById('main-img').src=this.src; document.querySelectorAll('.thumb-small').forEach(t=>t.classList.remove('active')); this.classList.add('active');"/>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-image">No Image Available</div>
                <?php endif; ?>
            </div>
            <table class="info-table">
                <tr><td>Brand</td><td><?php echo htmlspecialchars($car['car_brand']); ?></td></tr>
                <tr><td>Model</td><td><?php echo htmlspecialchars($car['car_model']); ?></td></tr>
                <tr><td>Year</td><td><?php echo htmlspecialchars($car['car_year']); ?></td></tr>
                <tr><td>Type</td><td><?php echo htmlspecialchars($car['car_origin']); ?></td></tr>
                <tr><td>Engine</td><td><?php echo htmlspecialchars($car['engine_type']); ?></td></tr>
                <tr><td>Fuel Type</td><td><?php echo htmlspecialchars($car['fuel_type']); ?></td></tr>
                <tr><td>Transmission</td><td><?php echo htmlspecialchars($car['transmission']); ?></td></tr>
                <tr><td>Drive Type</td><td><?php echo htmlspecialchars($car['drive_type']); ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Reservation Form -->
    <form method="POST" action="reservation.php">

        <!-- Your Info (auto-filled) -->
        <div class="section-card">
            <h2>Your Information</h2>
            <div class="user-info-box">
                <div class="form-group">
    <label class="auth-label">Full Name</label>
    <input type="text" 
           name="res_name"
           class="form-control"
           value="<?php echo htmlspecialchars($user['user_name']); ?>"
           required>
</div>

<div class="form-group">
    <label class="auth-label">IC Number</label>
    <input type="text"
           name="res_ic"
           class="form-control"
           value="<?php echo htmlspecialchars($user['user_ic']); ?>"
           required>
</div>

<div class="form-group">
    <label class="auth-label">Phone Number</label>
    <input type="text"
           name="res_phone"
           class="form-control"
           value="<?php echo htmlspecialchars($user['user_phone']); ?>"
           required>
</div>

<div class="form-group">
    <label class="auth-label">Email Address</label>
    <input type="email"
           name="res_email"
           class="form-control"
           value="<?php echo htmlspecialchars($user['user_email']); ?>"
           required>
</div>
                <p>*Details pulled from your account. <a href="profile.php">Update profile</a> if needed.</p>
            </div>
        </div>

        <!-- Visit Date -->
        <div class="section-card">
            <h2>Reservation Details</h2>
            <div class="form-group">
                <label class="auth-label">Preferred Visit Date</label>
                <input type="date" name="reservation_date" class="form-control" required
                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"/>
            </div>
        </div>

        <!-- Payment Summary -->
        <div class="section-card">
            <h2>Payment Summary</h2>
            <div style="text-align:center; margin-bottom:16px;">
                <div class="fee-badge">RM 500.00</div>
                <p style="font-size:13px; color:var(--text-light);">One-time reservation fee</p>
            </div>
            <table class="summary-table">
                <tr><td>Reservation Fee</td><td>RM 500.00</td></tr>
                <tr><td>Deductible From Purchase</td><td>Yes</td></tr>
                <tr><td>Reservation Valid For</td><td>7 days from payment</td></tr>
                <tr><td>Cancellation Policy</td><td>Non-refundable after 48 hours</td></tr>
            </table>
        </div>

        <div class="form-group" style="margin-bottom:24px;">
            <label class="checkbox-label">
                <input type="checkbox" name="agree" required/>
                I agree that the RM500 reservation fee is non-refundable if I cancel after 48 hours.
            </label>
        </div>

        <button type="submit" class="btn-primary" style="width:100%; padding:14px; font-size:16px;">
            Proceed to Confirmation &rarr;
        </button>
    </form>

    <?php else: ?>
    <div class="alert">No car selected. <a href="index.php">Go back to Home</a> to select a car first.</div>
    <?php endif; ?>

</div>

<footer class="footer text-center">
    <p>&copy; 2025 AutoDeal. All rights reserved.</p>
</footer>

</body>
</html>
