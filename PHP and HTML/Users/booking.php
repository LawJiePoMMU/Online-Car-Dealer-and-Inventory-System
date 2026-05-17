<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ===============================
// USER DATA
// ===============================
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ===============================
// SELECTED CAR
// ===============================
$car_id = $_SESSION['selected_car_id'] ?? null;

if (!$car_id) {
    header("Location: index.php");
    exit();
}

$car = null;
$car_images = [];
$used_car = null;

// Fetch Car
$stmt = $pdo->prepare("SELECT * FROM cars WHERE car_id = ?");
$stmt->execute([$car_id]);
$car = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$car) {
    die("Selected car does not exist.");
}

// Fetch Image
$stmt2 = $pdo->prepare("SELECT car_image_url FROM car_image WHERE car_id = ? LIMIT 1");
$stmt2->execute([$car_id]);
$car_images = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Used Car Details
if ($car['car_origin'] === 'Used Car') {
    $used_stmt = $pdo->prepare("SELECT * FROM used_car_details WHERE car_id = ?");
    $used_stmt->execute([$car_id]);
    $used_car = $used_stmt->fetch(PDO::FETCH_ASSOC);
}

// ===============================
// FINANCIAL CALCULATION
// ===============================
$car_price = floatval($car['car_price'] ?? 0);
$insurance_amount = 3000;
$total_price = $car_price + $insurance_amount;

$display_years = isset($_POST['loan_years']) ? intval($_POST['loan_years']) : 5;
$estimated_monthly = $total_price / ($display_years * 12);

// ===============================
// FORM STATE
// ===============================
$errors = [];
$booking_date = $_POST['booking_date'] ?? '';
$res_name     = $_POST['res_name']  ?? ($user['user_name'] ?? '');
$res_ic       = $_POST['res_ic']    ?? '';
$res_phone    = $_POST['res_phone'] ?? ($user['user_phone'] ?? '');
$res_email    = $_POST['res_email'] ?? ($user['user_email'] ?? '');
$car_color    = $_POST['car_color'] ?? '';
$car_variant  = $_POST['car_variant'] ?? '';

// ===============================
// FILE UPLOAD FUNCTION
// ===============================
function uploadFile($field_name, $upload_dir)
{
    if (!empty($_FILES[$field_name]['name']) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES[$field_name]['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];

        if (!in_array($ext, $allowed)) {
            return false;
        }

        $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $upload_dir . $filename;

        if (move_uploaded_file($_FILES[$field_name]['tmp_name'], $target)) {
            return $target;
        }
    }
    return null;
}

// ===============================
// FORM SUBMIT
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($booking_date)) {
        $errors[] = "Please select booking date.";
    }

    // New Car Validation
    if ($car['car_origin'] === 'New Car') {
        if (empty($car_color)) {
            $errors[] = "Please select car color.";
        }
        if (empty($car_variant)) {
            $errors[] = "Please select car variant.";
        }
    }

    // ===============================
    // FILE UPLOADS
    // ===============================
    $upload_dir = 'uploads/documents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // IC
    $ic_path = uploadFile('ic_document', $upload_dir);
    if ($ic_path === false) {
        $errors[] = "IC Document must be JPG, PNG, or PDF.";
    }
    if ($ic_path) {
        $_SESSION['tmp_ic_document'] = $ic_path;
    }

    // License
    $license_path = uploadFile('license_document', $upload_dir);
    if ($license_path === false) {
        $errors[] = "Driving License must be JPG, PNG, or PDF.";
    }
    if ($license_path) {
        $_SESSION['tmp_license_document'] = $license_path;
    }

    // Payslip
    $payslip_path = uploadFile('payslip_document', $upload_dir);
    if ($payslip_path === false) {
        $errors[] = "Payslip must be JPG, PNG, or PDF.";
    }
    if ($payslip_path) {
        $_SESSION['tmp_payslip_document'] = $payslip_path;
    }

    // Bank Statement
    $bank_path = uploadFile('bank_statement_document', $upload_dir);
    if ($bank_path === false) {
        $errors[] = "Bank Statement must be JPG, PNG, or PDF.";
    }
    if ($bank_path) {
        $_SESSION['tmp_bank_statement_document'] = $bank_path;
    }

    // Required Docs Checks
    if (empty($_SESSION['tmp_ic_document'])) {
        $errors[] = "IC Document is required.";
    }
    if (empty($_SESSION['tmp_license_document'])) {
        $errors[] = "Driving License is required.";
    }

    // ===============================
    // FINAL PROCESS
    // ===============================
    if (empty($errors)) {
        $loan_years = intval($_POST['loan_years'] ?? 5);
        $monthly_payment = $total_price / ($loan_years * 12);

        // SESSION STORAGE
        $_SESSION['booking_date']   = $booking_date;
        $_SESSION['res_name']       = $res_name;
        $_SESSION['res_ic']         = $res_ic;
        $_SESSION['res_phone']      = $res_phone;
        $_SESSION['res_email']      = $res_email;
        $_SESSION['car_color']      = ($car['car_origin'] === 'Used Car') ? ($used_car['car_color'] ?? '') : $car_color;
        $_SESSION['car_variant']    = ($car['car_origin'] === 'Used Car') ? 'Used Vehicle' : $car_variant;
        $_SESSION['loan_years']     = $loan_years;
        $_SESSION['insurance_amount'] = $insurance_amount;
        $_SESSION['monthly_payment'] = round($monthly_payment, 2);
        $_SESSION['total_price']    = round($total_price, 2);

        $_SESSION['res_brand']  = $car['car_brand'] ?? '';
        $_SESSION['res_model']  = $car['car_model'] ?? '';
        $_SESSION['res_year']   = $car['car_year'] ?? '';
        $_SESSION['res_origin'] = $car['car_origin'] ?? '';
        $_SESSION['res_image']  = !empty($car_images) ? $car_images[0]['car_image_url'] : null;
        $_SESSION['car_plate']  = $used_car['car_plate'] ?? '';

        // Final Docs Mapping
        $_SESSION['ic_document']             = $_SESSION['tmp_ic_document'];
        $_SESSION['license_document']        = $_SESSION['tmp_license_document'];
        $_SESSION['payslip_document']        = $_SESSION['tmp_payslip_document'] ?? null;
        $_SESSION['bank_statement_document'] = $_SESSION['tmp_bank_statement_document'] ?? null;

        // Cleanup Temp Sessions
        unset(
            $_SESSION['tmp_ic_document'],
            $_SESSION['tmp_license_document'],
            $_SESSION['tmp_payslip_document'],
            $_SESSION['tmp_bank_statement_document']
        );

        header("Location: payment.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Booking Checkout</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
<style>
body { font-family: 'Poppins', sans-serif; background: #f5f7fb; }
.container { max-width: 760px; margin: 40px auto; padding: 25px; background: white; border-radius: 10px; box-shadow: 0 3px 12px rgba(0,0,0,0.06); }
input, select { width: 100%; padding: 12px; margin-bottom: 16px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
label { display: block; font-weight: 500; margin-bottom: 6px; color: #333; }
button { width: 100%; padding: 14px; background: #2b6cb0; color: white; border: 0; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; }
button:hover { background: #1f4f85; }
.section-title { margin-top: 30px; margin-bottom: 16px; color: #2b6cb0; }
.summary-box { background: #f0f4f8; border: 1px solid #d0daf0; padding: 20px; border-radius: 8px; margin: 25px 0; }
.error-box { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
.readonly-box { background: #f9f9f9; }
.upload-status { font-size: 12px; color: #2b6cb0; margin-top: -10px; margin-bottom: 14px; display: block; font-weight: 500; }
</style>
</head>
<body>

<div class="container">

<?php if (!empty($errors)): ?>
<div class="error-box">
    <?php foreach ($errors as $error): ?>
        <p style="margin: 4px 0;">• <?php echo htmlspecialchars($error); ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="POST" action="booking.php" enctype="multipart/form-data">

<h2>Secure Vehicle Booking Checkout</h2>
<p>Vehicle Selected: <strong><?php echo htmlspecialchars($car['car_brand'] . ' ' . $car['car_model']); ?></strong></p>
<hr style="margin:20px 0; border: 0; border-top: 1px solid #eee;">

<h3 class="section-title">1. Customer Details</h3>
<input type="text" name="res_name" placeholder="Full Name" value="<?php echo htmlspecialchars($res_name); ?>" required>
<input type="text" name="res_ic" placeholder="IC Number" value="<?php echo htmlspecialchars($res_ic); ?>" required>
<input type="text" name="res_phone" placeholder="Phone Number" value="<?php echo htmlspecialchars($res_phone); ?>" required>
<input type="email" name="res_email" placeholder="Email Address" value="<?php echo htmlspecialchars($res_email); ?>" required>

<h3 class="section-title">2. Vehicle Configuration</h3>
<?php if ($car['car_origin'] === 'New Car'): ?>
    <select name="car_color" required>
        <option value="">Choose Exterior Color</option>
        <option value="White" <?php echo ($car_color === 'White') ? 'selected' : ''; ?>>White</option>
        <option value="Black" <?php echo ($car_color === 'Black') ? 'selected' : ''; ?>>Black</option>
        <option value="Red"   <?php echo ($car_color === 'Red') ? 'selected' : ''; ?>>Red</option>
        <option value="Blue"  <?php echo ($car_color === 'Blue') ? 'selected' : ''; ?>>Blue</option>
    </select>

    <select name="car_variant" required>
        <option value="">Choose Variant</option>
        <option value="Standard" <?php echo ($car_variant === 'Standard') ? 'selected' : ''; ?>>Standard</option>
        <option value="Premium"  <?php echo ($car_variant === 'Premium') ? 'selected' : ''; ?>>Premium</option>
        <option value="Full Spec"<?php echo ($car_variant === 'Full Spec') ? 'selected' : ''; ?>>Full Spec</option>
    </select>
<?php else: ?>
    <input type="text" class="readonly-box" value="<?php echo htmlspecialchars($used_car['car_color'] ?? 'Unknown'); ?>" readonly>
    <input type="text" class="readonly-box" value="<?php echo htmlspecialchars($used_car['car_plate'] ?? 'N/A'); ?>" readonly>
    <input type="hidden" name="car_color" value="<?php echo htmlspecialchars($used_car['car_color'] ?? ''); ?>">
<?php endif; ?>

<h3 class="section-title">3. Booking & Financing</h3>
<input type="date" name="booking_date" value="<?php echo htmlspecialchars($booking_date); ?>" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>

<select name="loan_years" id="loan_years" required>
    <option value="5" <?php echo ($display_years === 5) ? 'selected' : ''; ?>>5 Years</option>
    <option value="7" <?php echo ($display_years === 7) ? 'selected' : ''; ?>>7 Years</option>
    <option value="9" <?php echo ($display_years === 9) ? 'selected' : ''; ?>>9 Years</option>
</select>

<h3 class="section-title">4. Financing Documents</h3>

<label>IC Document (PDF/Image)</label>
<input type="file" name="ic_document" <?php echo empty($_SESSION['tmp_ic_document']) ? 'required' : ''; ?>>
<?php if (!empty($_SESSION['tmp_ic_document'])): ?>
    <span class="upload-status">✓ IC Document uploaded successfully</span>
<?php endif; ?>

<label>Driving License</label>
<input type="file" name="license_document" <?php echo empty($_SESSION['tmp_license_document']) ? 'required' : ''; ?>>
<?php if (!empty($_SESSION['tmp_license_document'])): ?>
    <span class="upload-status">✓ Driving License uploaded successfully</span>
<?php endif; ?>

<label>Payslip (Optional)</label>
<input type="file" name="payslip_document">

<label>Bank Statement (Optional)</label>
<input type="file" name="bank_statement_document">

<div class="summary-box">
    <h3 style="margin-top:0;">Estimated Billing Summary</h3>
    <p>Booking Fee: <strong>RM 500.00</strong></p>
    <p>Insurance: <strong>RM 3,000.00</strong></p>
    <p>Vehicle Price: <strong>RM <?php echo number_format($car_price, 2); ?></strong></p>
    <hr style="margin:10px 0; border: 0; border-top: 1px solid #d0daf0;">
    <p style="font-size:18px; font-weight:600; color:#2b6cb0; margin:0;">
        Estimated Monthly Installment: <strong id="monthly_payment">RM <?php echo number_format($estimated_monthly, 2); ?> / month</strong>
    </p>
</div>

<button type="submit">Confirm & Proceed to Payment</button>

</form>
</div>

<script>
const loanSelect = document.getElementById('loan_years');
const monthlyText = document.getElementById('monthly_payment');
const totalPrice = <?php echo $total_price; ?>;

function updateLoanCalculation() {
    const years = parseInt(loanSelect.value);
    const monthly = totalPrice / (years * 12);
    monthlyText.innerHTML = 'RM ' + monthly.toFixed(2) + ' / month';
}

loanSelect.addEventListener('change', updateLoanCalculation);
</script>

</body>
</html>