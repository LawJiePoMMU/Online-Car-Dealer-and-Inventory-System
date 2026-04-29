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
$car_image = null;

if ($car_id) {
    $stmt = $pdo->prepare("SELECT * FROM cars WHERE car_id = ?");
    $stmt->execute([$car_id]);
    $car = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt2 = $pdo->prepare("SELECT car_image_url FROM car_image WHERE car_id = ? LIMIT 1");
    $stmt2->execute([$car_id]);
    $img = $stmt2->fetch(PDO::FETCH_ASSOC);
    $car_image = $img ? $img['car_image_url'] : null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Down Payment Calculator</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="styles.css"/>
    <style>
        .page-hero {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white; padding: 40px 0 30px; margin-bottom: 30px;
        }
        .page-hero h1 { font-size: 32px; font-weight: 700; margin-bottom: 6px; }
        .page-hero p  { opacity: 0.85; font-size: 15px; }

        .tab-group { display: flex; gap: 10px; margin-bottom: 24px; }
        .tab-btn {
            padding: 10px 28px; border: 2px solid var(--primary-color);
            background: white; color: var(--primary-color); border-radius: 5px;
            font-family: 'Poppins', sans-serif; font-weight: 500;
            cursor: pointer; transition: var(--transition);
        }
        .tab-btn.active, .tab-btn:hover { background: var(--primary-color); color: white; }

        .section-card {
            background: var(--card-bg); border-radius: 8px;
            box-shadow: var(--shadow); padding: 28px; margin-bottom: 24px;
        }
        .section-card h2 { font-size: 18px; margin-bottom: 18px; color: var(--primary-color); border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }

        .car-info-inner { display: grid; grid-template-columns: 220px 1fr; gap: 24px; align-items: start; }
        .car-info-inner img { width: 100%; border-radius: 8px; object-fit: cover; height: 150px; }
        .no-image { width:100%; height:150px; border-radius:8px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; color:#999; font-size:14px; }
        .car-info-inner table { width: 100%; border-collapse: collapse; }
        .car-info-inner td { padding: 8px 12px; font-size: 14px; border-bottom: 1px solid #f0f0f0; }
        .car-info-inner td:first-child { color: var(--text-light); font-weight: 500; width: 140px; }

        .result-box { background: #f0f7ff; border: 1px solid #c8e0ff; border-radius: 8px; padding: 24px; margin-top: 20px; }
        .result-box h2 { font-size: 18px; margin-bottom: 16px; color: var(--primary-color); }
        .result-table { width: 100%; border-collapse: collapse; }
        .result-table td { padding: 9px 12px; font-size: 14px; border-bottom: 1px solid #d8ecff; }
        .result-table td:first-child { color: var(--text-light); }
        .result-table td:last-child { font-weight: 600; color: var(--text-dark); text-align: right; }
        .result-note { font-size: 12px; color: #999; margin-top: 12px; }

        .alert { background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 14px 18px; margin-bottom: 20px; font-size: 14px; }
        .alert a { color: var(--primary-color); }

        input[type="range"] { width: 100%; accent-color: var(--primary-color); margin-top: 6px; }

        @media (max-width: 600px) { .car-info-inner { grid-template-columns: 1fr; } }
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
        <h1>Down Payment Calculator</h1>
        <p>Calculate your estimated down payment for new and used cars.</p>
    </div>
</div>

<div class="container">

    <?php if ($car): ?>
    <!-- Selected Car Info -->
    <div class="section-card">
        <h2>Selected Car</h2>
        <div class="car-info-inner">
            <?php if ($car_image): ?>
                <img src="<?php echo htmlspecialchars($car_image); ?>" alt="Car"/>
            <?php else: ?>
                <div class="no-image">No Image Available</div>
            <?php endif; ?>
            <table>
                <tr><td>Brand</td><td><?php echo htmlspecialchars($car['car_brand']); ?></td></tr>
                <tr><td>Model</td><td><?php echo htmlspecialchars($car['car_model']); ?></td></tr>
                <tr><td>Year</td><td><?php echo htmlspecialchars($car['car_year']); ?></td></tr>
                <tr><td>Type</td><td><?php echo htmlspecialchars($car['car_origin']); ?></td></tr>
                <tr><td>Fuel Type</td><td><?php echo htmlspecialchars($car['fuel_type']); ?></td></tr>
                <tr><td>Transmission</td><td><?php echo htmlspecialchars($car['transmission']); ?></td></tr>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="alert">No car selected. <a href="index.php">Go back to Home</a> to select a car first.</div>
    <?php endif; ?>

    <!-- Calculator Form -->
    <div class="section-card">
        <h2>Calculator</h2>

        <div class="tab-group">
            <button id="btn-new" class="tab-btn active" onclick="switchType('new')">New Car</button>
            <button id="btn-used" class="tab-btn" onclick="switchType('used')">Used Car</button>
        </div>

        <div class="form-group">
            <label class="auth-label">Car Price (RM)</label>
            <input type="number" id="car-price" class="form-control" placeholder="e.g. 80000" min="1" oninput="calculateDP()"/>
        </div>

        <div class="form-group">
            <label class="auth-label">Down Payment Rate: <span id="dp-rate-val" style="color:var(--primary-color);font-weight:600;">10%</span></label>
            <input type="range" id="dp-rate" min="0" max="50" step="1" value="10"
                   oninput="document.getElementById('dp-rate-val').textContent=this.value+'%'; calculateDP()"/>
        </div>

        <div class="form-group">
            <label class="auth-label">Loan Tenure</label>
            <select id="tenure" class="form-control" onchange="calculateDP()">
                <option value="3">3 Years</option>
                <option value="5">5 Years</option>
                <option value="7" selected>7 Years</option>
                <option value="9">9 Years</option>
            </select>
        </div>

        <div class="form-group">
            <label class="auth-label">Interest Rate (% per year)</label>
            <input type="number" id="interest-rate" class="form-control" placeholder="e.g. 3.5" step="0.1" min="0" oninput="calculateDP()"/>
        </div>

        <!-- Result -->
        <div id="result-box" class="result-box" style="display:none;">
            <h2>Estimated Summary</h2>
            <table class="result-table">
                <tr><td>Car Price</td><td id="r-price">-</td></tr>
                <tr><td>Down Payment (<span id="r-rate">-</span>)</td><td id="r-dp">-</td></tr>
                <tr><td>Loan Amount</td><td id="r-loan">-</td></tr>
                <tr><td>Total Interest</td><td id="r-interest">-</td></tr>
                <tr><td>Total Payable</td><td id="r-total">-</td></tr>
                <tr><td>Monthly Instalment</td><td id="r-monthly">-</td></tr>
                <tr><td>Tenure</td><td id="r-tenure">-</td></tr>
            </table>
            <p class="result-note">*Estimate only. Actual figures depend on bank approval and additional fees.</p>

            <form method="POST" action="payment.php" id="proceed-form" style="margin-top:20px;">
                <input type="hidden" name="source"         value="downpayment"/>
                <input type="hidden" name="car_id"         value="<?php echo $car_id; ?>"/>
                <input type="hidden" name="payment_amount" id="h-dp"/>
                <input type="hidden" name="payment_label"  id="h-label"/>
                <input type="hidden" name="detail_price"   id="h-price"/>
                <input type="hidden" name="detail_loan"    id="h-loan"/>
                <input type="hidden" name="detail_monthly" id="h-monthly"/>
                <input type="hidden" name="detail_tenure"  id="h-tenure"/>
                <button type="button" class="btn-primary" onclick="proceedToPayment()">
                    Proceed to Down Payment &rarr;
                </button>
            </form>
        </div>
    </div>

</div>

<footer class="footer text-center">
    <p>&copy; 2025 AutoDeal. All rights reserved.</p>
</footer>

<script>
    let carType = 'new';
    function switchType(type) {
        carType = type;
        document.getElementById('btn-new').classList.toggle('active', type==='new');
        document.getElementById('btn-used').classList.toggle('active', type==='used');
        calculateDP();
    }
    function fmt(n) { return 'RM '+n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,','); }
    function calculateDP() {
        const price    = parseFloat(document.getElementById('car-price').value);
        const dpRate   = parseFloat(document.getElementById('dp-rate').value)/100;
        const tenure   = parseInt(document.getElementById('tenure').value);
        const rateInput= parseFloat(document.getElementById('interest-rate').value);
        if (!price||price<=0){document.getElementById('result-box').style.display='none';return;}
        const rate     = (!rateInput||isNaN(rateInput))?(carType==='new'?0.03:0.04):rateInput/100;
        const dp=price*dpRate, loan=price-dp, interest=loan*rate*tenure, total=loan+interest, monthly=total/(tenure*12);
        document.getElementById('r-price').textContent    = fmt(price);
        document.getElementById('r-rate').textContent     = (dpRate*100).toFixed(0)+'%';
        document.getElementById('r-dp').textContent       = fmt(dp);
        document.getElementById('r-loan').textContent     = fmt(loan);
        document.getElementById('r-interest').textContent = fmt(interest);
        document.getElementById('r-total').textContent    = fmt(total);
        document.getElementById('r-monthly').textContent  = fmt(monthly);
        document.getElementById('r-tenure').textContent   = tenure+' years ('+tenure*12+' months)';
        document.getElementById('h-dp').value      = dp.toFixed(2);
        document.getElementById('h-label').value   = 'Down Payment ('+(dpRate*100).toFixed(0)+'%) — <?php echo addslashes(($car['car_brand']??'').' '.($car['car_model']??'Car')); ?>';
        document.getElementById('h-price').value   = fmt(price);
        document.getElementById('h-loan').value    = fmt(loan);
        document.getElementById('h-monthly').value = fmt(monthly);
        document.getElementById('h-tenure').value  = tenure+' years';
        document.getElementById('result-box').style.display='block';
    }
    function proceedToPayment() {
        if (!document.getElementById('h-dp').value||parseFloat(document.getElementById('h-dp').value)<=0){alert('Please calculate first.');return;}
        document.getElementById('proceed-form').submit();
    }
</script>
</body>
</html>
