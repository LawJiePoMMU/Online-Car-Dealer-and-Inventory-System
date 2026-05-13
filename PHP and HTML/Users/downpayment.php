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

/* FILE UPLOAD */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $upload_dir = "uploads/";

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (isset($_FILES['ic_file']) && $_FILES['ic_file']['error'] === 0) {

        $ic_path = $upload_dir . basename($_FILES['ic_file']['name']);

        move_uploaded_file(
            $_FILES['ic_file']['tmp_name'],
            $ic_path
        );
    }

    if (isset($_FILES['license_file']) && $_FILES['license_file']['error'] === 0) {

        $license_path = $upload_dir . basename($_FILES['license_file']['name']);

        move_uploaded_file(
            $_FILES['license_file']['tmp_name'],
            $license_path
        );
    }

    if (isset($_FILES['payslip_file']) && $_FILES['payslip_file']['error'] === 0) {

        $payslip_path = $upload_dir . basename($_FILES['payslip_file']['name']);

        move_uploaded_file(
            $_FILES['payslip_file']['tmp_name'],
            $payslip_path
        );
    }

    if (isset($_FILES['bank_statement']) && $_FILES['bank_statement']['error'] === 0) {

        $bank_path = $upload_dir . basename($_FILES['bank_statement']['name']);

        move_uploaded_file(
            $_FILES['bank_statement']['tmp_name'],
            $bank_path
        );
    }
}

/* SELECTED CAR */

$car_id = $_SESSION['selected_car_id'] ?? null;

$car = null;
$car_image = null;

if ($car_id) {

    $stmt = $pdo->prepare("SELECT * FROM cars WHERE car_id = ?");
    $stmt->execute([$car_id]);

    $car = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt2 = $pdo->prepare("
        SELECT car_image_url
        FROM car_image
        WHERE car_id = ?
        LIMIT 1
    ");

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

</head>

<body>

<!-- NAVBAR -->

<nav class="navbar">

    <div class="nav-container">

        <a href="index.php" class="nav-logo">
            AutoDeal
        </a>

        <ul class="nav-links">

            <li><a href="index.php">Home</a></li>

            <li><a href="downpayment.php">Down Payment</a></li>

            <li><a href="reservation.php">Reservation</a></li>

            <li><a href="view_status.php">View Status</a></li>

        </ul>

        <div class="nav-actions">

            <span style="font-size:14px; color:var(--text-light);">

                Welcome,
                <strong>
                    <?php echo htmlspecialchars($user['user_name']); ?>
                </strong>

            </span>

        </div>

    </div>

</nav>

<!-- HERO -->

<div class="page-hero">

    <div class="container">

        <h1>Down Payment Calculator</h1>

        <p>
            Calculate your estimated down payment
            for new and used cars.
        </p>

    </div>

</div>

<div class="container">

<?php if ($car): ?>

<!-- SELECTED CAR -->

<div class="section-card">

    <h2>Selected Car</h2>

    <div class="car-info-inner">

        <?php if ($car_image): ?>

            <img src="<?php echo htmlspecialchars($car_image); ?>" alt="Car"/>

        <?php else: ?>

            <div class="no-image">
                No Image Available
            </div>

        <?php endif; ?>

        <table>

            <tr>
                <td>Brand</td>
                <td><?php echo htmlspecialchars($car['car_brand']); ?></td>
            </tr>

            <tr>
                <td>Model</td>
                <td><?php echo htmlspecialchars($car['car_model']); ?></td>
            </tr>

            <tr>
                <td>Year</td>
                <td><?php echo htmlspecialchars($car['car_year']); ?></td>
            </tr>

            <tr>
                <td>Type</td>
                <td><?php echo htmlspecialchars($car['car_origin']); ?></td>
            </tr>

            <tr>
                <td>Fuel Type</td>
                <td><?php echo htmlspecialchars($car['fuel_type']); ?></td>
            </tr>

            <tr>
                <td>Transmission</td>
                <td><?php echo htmlspecialchars($car['transmission']); ?></td>
            </tr>

        </table>

    </div>

</div>

<?php else: ?>

<div class="alert">

    No car selected.

    <a href="index.php">
        Go back to Home
    </a>

</div>

<?php endif; ?>

<!-- CALCULATOR -->

<div class="section-card">

    <h2>Calculator</h2>

    <div class="tab-group">

        <button id="btn-new"
                class="tab-btn active"
                onclick="switchType('new')">

            New Car

        </button>

        <button id="btn-used"
                class="tab-btn"
                onclick="switchType('used')">

            Used Car

        </button>

    </div>

    <div class="form-group">

        <label class="auth-label">
            Car Price (RM)
        </label>

        <input type="number"
               id="car-price"
               class="form-control"
               placeholder="e.g. 80000"
               min="1"
               oninput="calculateDP()"/>

    </div>

    <div class="form-group">

        <label class="auth-label">

            Down Payment Rate:

            <span id="dp-rate-val"
                  style="color:var(--primary-color);font-weight:600;">

                10%

            </span>

        </label>

        <input type="range"
               id="dp-rate"
               min="0"
               max="50"
               step="1"
               value="10"

               oninput="
                    document.getElementById('dp-rate-val').textContent=this.value+'%';
                    calculateDP();
               "/>

    </div>

    <div class="form-group">

        <label class="auth-label">
            Loan Tenure
        </label>

        <select id="tenure"
                class="form-control"
                onchange="calculateDP()">

            <option value="3">3 Years</option>
            <option value="5">5 Years</option>
            <option value="7" selected>7 Years</option>
            <option value="9">9 Years</option>

        </select>

    </div>

    <div class="form-group">

        <label class="auth-label">
            Interest Rate (% per year)
        </label>

        <input type="number"
               id="interest-rate"
               class="form-control"
               placeholder="e.g. 3.5"
               step="0.1"
               min="0"
               oninput="calculateDP()"/>

    </div>

    <!-- RESULT -->

    <div id="result-box"
         class="result-box"
         style="display:none;">

        <h2>Estimated Summary</h2>

        <table class="result-table">

            <tr><td>Car Price</td><td id="r-price">-</td></tr>

            <tr>
                <td>
                    Down Payment
                    (<span id="r-rate">-</span>)
                </td>

                <td id="r-dp">-</td>
            </tr>

            <tr><td>Loan Amount</td><td id="r-loan">-</td></tr>

            <tr><td>Total Interest</td><td id="r-interest">-</td></tr>

            <tr><td>Total Payable</td><td id="r-total">-</td></tr>

            <tr><td>Monthly Instalment</td><td id="r-monthly">-</td></tr>

            <tr><td>Tenure</td><td id="r-tenure">-</td></tr>

        </table>

        <p class="result-note">

            *Estimate only.
            Actual figures depend on bank approval and additional fees.

        </p>

        <!-- FORM -->

        <form method="POST"
              action="payment.php"
              id="proceed-form"
              enctype="multipart/form-data"
              style="margin-top:20px;">

            <!-- DOCUMENTS -->

            <div class="section-card">

                <h2>Supporting Documents</h2>

                <div class="form-group">

                    <label class="auth-label">
                        Upload IC / Passport
                    </label>

                    <input type="file"
                           name="ic_file"
                           class="form-control"
                           accept=".jpg,.jpeg,.png,.pdf"
                           required>

                </div>

                <div class="form-group">

                    <label class="auth-label">
                        Upload Driving License
                    </label>

                    <input type="file"
                           name="license_file"
                           class="form-control"
                           accept=".jpg,.jpeg,.png,.pdf">

                </div>

                <div class="form-group">

                    <label class="auth-label">
                        Upload Payslip
                    </label>

                    <input type="file"
                           name="payslip_file"
                           class="form-control"
                           accept=".jpg,.jpeg,.png,.pdf"
                           required>

                </div>

                <div class="form-group">

                    <label class="auth-label">
                        Upload Bank Statement
                    </label>

                    <input type="file"
                           name="bank_statement"
                           class="form-control"
                           accept=".jpg,.jpeg,.png,.pdf"
                           required>

                </div>

            </div>

            <!-- HIDDEN INPUTS -->

            <input type="hidden"
                   name="source"
                   value="downpayment"/>

            <input type="hidden"
                   name="car_id"
                   value="<?php echo $car_id; ?>"/>

            <input type="hidden"
                   name="payment_amount"
                   id="h-dp"/>

            <input type="hidden"
                   name="payment_label"
                   id="h-label"/>

            <input type="hidden"
                   name="detail_price"
                   id="h-price"/>

            <input type="hidden"
                   name="detail_loan"
                   id="h-loan"/>

            <input type="hidden"
                   name="detail_monthly"
                   id="h-monthly"/>

            <input type="hidden"
                   name="detail_tenure"
                   id="h-tenure"/>

            <button type="button"
                    class="btn-primary"
                    onclick="proceedToPayment()">

                Proceed to Down Payment →

            </button>

        </form>

    </div>

</div>

</div>

<script>

let carType = 'new';

function switchType(type) {

    carType = type;

    document.getElementById('btn-new')
        .classList.toggle('active', type==='new');

    document.getElementById('btn-used')
        .classList.toggle('active', type==='used');

    calculateDP();
}

function fmt(n) {

    return 'RM ' + n.toFixed(2)
        .replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function calculateDP() {

    const price =
        parseFloat(document.getElementById('car-price').value);

    const dpRate =
        parseFloat(document.getElementById('dp-rate').value)/100;

    const tenure =
        parseInt(document.getElementById('tenure').value);

    const rateInput =
        parseFloat(document.getElementById('interest-rate').value);

    if (!price || price <= 0) {

        document.getElementById('result-box')
            .style.display='none';

        return;
    }

    const rate =
        (!rateInput || isNaN(rateInput))
        ? (carType==='new' ? 0.03 : 0.04)
        : rateInput/100;

    const dp       = price * dpRate;
    const loan     = price - dp;
    const interest = loan * rate * tenure;
    const total    = loan + interest;
    const monthly  = total / (tenure*12);

    document.getElementById('r-price').textContent    = fmt(price);
    document.getElementById('r-rate').textContent     = (dpRate*100).toFixed(0)+'%';
    document.getElementById('r-dp').textContent       = fmt(dp);
    document.getElementById('r-loan').textContent     = fmt(loan);
    document.getElementById('r-interest').textContent = fmt(interest);
    document.getElementById('r-total').textContent    = fmt(total);
    document.getElementById('r-monthly').textContent  = fmt(monthly);
    document.getElementById('r-tenure').textContent   = tenure+' years ('+tenure*12+' months)';

    document.getElementById('h-dp').value = dp.toFixed(2);

    document.getElementById('h-label').value =
        'Down Payment ('+(dpRate*100).toFixed(0)+'%) — <?php echo addslashes(($car['car_brand']??'').' '.($car['car_model']??'Car')); ?>';

    document.getElementById('h-price').value   = fmt(price);
    document.getElementById('h-loan').value    = fmt(loan);
    document.getElementById('h-monthly').value = fmt(monthly);
    document.getElementById('h-tenure').value  = tenure+' years';

    document.getElementById('result-box')
        .style.display='block';
}

function proceedToPayment() {

    if (!document.getElementById('h-dp').value ||
        parseFloat(document.getElementById('h-dp').value) <= 0) {

        alert('Please calculate first.');

        return;
    }

    document.getElementById('proceed-form').submit();
}

</script>

</body>
</html>