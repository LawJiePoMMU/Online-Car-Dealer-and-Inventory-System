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
$car_price        = floatval($car['car_price'] ?? 0);
$insurance_amount = 3000.00;
$booking_fee       = 500.00; 
$total_price      = $car_price + $insurance_amount + $booking_fee;

// Detect loan term selection
$display_years = isset($_POST['loan_years']) ? intval($_POST['loan_years']) : 5;
$estimated_monthly = $total_price / ($display_years * 12);

// ===============================
// FORM STATE
// ===============================
$errors = [];
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {

    // New Car Validation
    if ($car['car_origin'] === 'New Car') {
        if (empty($car_color)) {
            $errors[] = "Please select a car color.";
        }
        if (empty($car_variant)) {
            $errors[] = "Please select a car variant.";
        }
    }

    // File Upload Directory Management
    $upload_dir = 'uploads/documents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Handle IC
    $ic_path = uploadFile('ic_document', $upload_dir);
    if ($ic_path === false) {
        $errors[] = "IC Document must be a valid JPG, PNG, or PDF file.";
    } elseif ($ic_path) {
        $_SESSION['tmp_ic_document'] = $ic_path;
    }

    // Handle License
    $license_path = uploadFile('license_document', $upload_dir);
    if ($license_path === false) {
        $errors[] = "Driving License must be a valid JPG, PNG, or PDF file.";
    } elseif ($license_path) {
        $_SESSION['tmp_license_document'] = $license_path;
    }

    // Handle Payslip
    $payslip_path = uploadFile('payslip_document', $upload_dir);
    if ($payslip_path === false) {
        $errors[] = "Recent Payslip must be a valid JPG, PNG, or PDF file.";
    } elseif ($payslip_path) {
        $_SESSION['tmp_payslip_document'] = $payslip_path;
    }

    // Handle Bank Statement
    $bank_path = uploadFile('bank_statement_document', $upload_dir);
    if ($bank_path === false) {
        $errors[] = "Bank Statement must be a valid JPG, PNG, or PDF file.";
    } elseif ($bank_path) {
        $_SESSION['tmp_bank_statement_document'] = $bank_path;
    }

    // STRICT CHECKING CRITERIA: All document variables are absolutely required
    if (empty($_SESSION['tmp_ic_document'])) {
        $errors[] = "IC Document upload is required.";
    }
    if (empty($_SESSION['tmp_license_document'])) {
        $errors[] = "Driving License upload is required.";
    }
    if (empty($_SESSION['tmp_payslip_document'])) {
        $errors[] = "Recent Payslip document upload is required.";
    }
    if (empty($_SESSION['tmp_bank_statement_document'])) {
        $errors[] = "3-Month Bank Statement document upload is required.";
    }

    // ===============================
    // FINAL DATA PROCESSING
    // ===============================
    if (empty($errors)) {
        $loan_years = intval($_POST['loan_years'] ?? 5);
        $monthly_payment = $total_price / ($loan_years * 12);

        $_SESSION['res_name']         = $res_name;
        $_SESSION['res_ic']           = $res_ic;
        $_SESSION['res_phone']        = $res_phone;
        $_SESSION['res_email']        = $res_email;
        $_SESSION['car_color']        = ($car['car_origin'] === 'Used Car') ? ($used_car['car_color'] ?? 'Default') : $car_color;
        $_SESSION['car_variant']      = ($car['car_origin'] === 'Used Car') ? 'Used Vehicle' : $car_variant;
        $_SESSION['loan_years']       = $loan_years;
        $_SESSION['insurance_amount']  = $insurance_amount;
        $_SESSION['monthly_payment']  = round($monthly_payment, 2);
        
        $_SESSION['total_price']      = round($total_price, 2); 
        $_SESSION['pay_amount']       = number_format($booking_fee, 2, '.', ''); 

        $_SESSION['res_brand']        = $car['car_brand'] ?? '';
        $_SESSION['res_model']        = $car['car_model'] ?? '';
        $_SESSION['res_year']         = $car['car_year'] ?? '';
        $_SESSION['res_origin']       = $car['car_origin'] ?? '';
        $_SESSION['res_image']        = !empty($car_images) ? $car_images[0]['car_image_url'] : null;
        $_SESSION['car_plate']        = $used_car['car_plate'] ?? '';

        // Assign Permanent Documents Track Locations
        $_SESSION['ic_document']             = $_SESSION['tmp_ic_document'];
        $_SESSION['license_document']        = $_SESSION['tmp_license_document'];
        $_SESSION['payslip_document']        = $_SESSION['tmp_payslip_document'];
        $_SESSION['bank_statement_document'] = $_SESSION['tmp_bank_statement_document'];

        // Free memory lifecycle allocations
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
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
    body { background: #f5f7fb; color: #333; padding-bottom: 60px; }
    .navbar { background: white; padding: 18px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 12px rgba(0,0,0,0.05); margin-bottom: 30px; }
    .nav-logo { font-size: 24px; font-weight: 700; color: #2b6cb0; text-decoration: none; }
    .container { max-width: 760px; margin: 0 auto; padding: 30px; background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
    
    input[type="text"], input[type="email"], select { width: 100%; padding: 12px; margin-bottom: 16px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
    input:focus, select:focus { outline: none; border-color: #2b6cb0; }
    label { display: block; font-weight: 600; margin-bottom: 6px; color: #475569; font-size: 14px; }
    
    .btn-submit { width: 100%; padding: 15px; background: #2b6cb0; color: white; border: 0; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s; margin-top: 15px; }
    .btn-submit:hover { background: #1f4f85; }
    
    .section-title { margin-top: 30px; margin-bottom: 16px; color: #2b6cb0; font-size: 18px; font-weight: 700; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px; }
    .summary-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; margin: 25px 0; }
    .summary-item { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; color: #475569; }
    .error-box { background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; padding: 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
    .readonly-box { background: #f1f5f9; color: #64748b; font-weight: 500; cursor: not-allowed; }
    .upload-status { font-size: 12px; color: #16a34a; margin-top: -12px; margin-bottom: 16px; display: block; font-weight: 600; }
</style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="nav-logo">AutoDeal</a>
</nav>

<div class="container">

<?php if (!empty($errors)): ?>
<div class="error-box">
    <?php foreach ($errors as $error): ?>
        <p style="margin: 4px 0;">• <?php echo htmlspecialchars($error); ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form id="bookingForm" method="POST" action="booking.php" enctype="multipart/form-data">

    <h2>Secure Vehicle Booking Checkout</h2>
    <p style="color:#64748b; margin-top:4px;">Vehicle Selected: <strong style="color:#1e293b;"><?php echo htmlspecialchars($car['car_brand'] . ' ' . $car['car_model']); ?></strong></p>

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
        <input type="text" class="readonly-box" value="Color: <?php echo htmlspecialchars($used_car['car_color'] ?? 'Unknown'); ?>" readonly>
        <input type="text" class="readonly-box" value="Plate Ref: <?php echo htmlspecialchars($used_car['car_plate'] ?? 'N/A'); ?>" readonly>
        
        <input type="hidden" name="car_color" value="<?php echo htmlspecialchars($used_car['car_color'] ?? ''); ?>">
        <input type="hidden" name="car_variant" value="Used Vehicle">
    <?php endif; ?>

    <h3 class="section-title">3. Financing Preferences</h3>
    <label>Financing Loan Tenure Preference</label>
    <select name="loan_years" id="loan_years" required>
        <option value="5" <?php echo ($display_years === 5) ? 'selected' : ''; ?>>5 Years</option>
        <option value="7" <?php echo ($display_years === 7) ? 'selected' : ''; ?>>7 Years</option>
        <option value="9" <?php echo ($display_years === 9) ? 'selected' : ''; ?>>9 Years</option>
    </select>

    <h3 class="section-title">4. Mandatory Financing Documents</h3>

    <label>IC Document Copy (PDF/Image)</label>
    <input type="file" name="ic_document" <?php echo empty($_SESSION['tmp_ic_document']) ? 'required' : ''; ?>>
    <?php if (!empty($_SESSION['tmp_ic_document'])): ?>
        <span class="upload-status">✓ IC Document cached from previous upload</span>
    <?php endif; ?>

    <label>Valid Driving License</label>
    <input type="file" name="license_document" <?php echo empty($_SESSION['tmp_license_document']) ? 'required' : ''; ?>>
    <?php if (!empty($_SESSION['tmp_license_document'])): ?>
        <span class="upload-status">✓ Driving License cached from previous upload</span>
    <?php endif; ?>

    <label>Recent Payslip (PDF/Image)</label>
    <input type="file" name="payslip_document" <?php echo empty($_SESSION['tmp_payslip_document']) ? 'required' : ''; ?>>
    <?php if (!empty($_SESSION['tmp_payslip_document'])): ?>
        <span class="upload-status">✓ Recent Payslip cached from previous upload</span>
    <?php endif; ?>

    <label>3-Month Bank Statement (PDF/Image)</label>
    <input type="file" name="bank_statement_document" <?php echo empty($_SESSION['tmp_bank_statement_document']) ? 'required' : ''; ?>>
    <?php if (!empty($_SESSION['tmp_bank_statement_document'])): ?>
        <span class="upload-status">✓ Bank Statement cached from previous upload</span>
    <?php endif; ?>

    <div class="summary-box">
        <h3 style="margin-bottom:12px; font-size:16px; color:#1e293b;">Estimated Billing Summary</h3>
        <div class="summary-item"><span>Vehicle Asset Price</span><strong>RM <?php echo number_format($car_price, 2); ?></strong></div>
        <div class="summary-item"><span>Comprehensive Insurance Coverage</span><strong>RM <?php echo number_format($insurance_amount, 2); ?></strong></div>
        <div class="summary-item"><span>Processing Booking Fee (Due Now)</span><strong style="color:#16a34a;">RM <?php echo number_format($booking_fee, 2); ?></strong></div>
        <hr style="margin:12px 0; border: 0; border-top: 1px solid #e2e8f0;">
        <p style="font-size:16px; font-weight:700; color:#2b6cb0; margin:0; display:flex; justify-content:space-between;">
            <span>Est. Monthly Installment:</span>
            <span id="monthly_payment">RM <?php echo number_format($estimated_monthly, 2); ?> / mo</span>
        </p>
    </div>

    <button type="submit" name="submit_booking" value="1" class="btn-submit">Confirm & Proceed to Payment &rarr;</button>

</form>
</div>

<script>
const loanSelect = document.getElementById('loan_years');
const monthlyText = document.getElementById('monthly_payment');
const totalPrice = <?php echo $total_price; ?>;

function updateLoanCalculation() {
    const years = parseInt(loanSelect.value);
    const monthly = totalPrice / (years * 12);
    monthlyText.innerHTML = 'RM ' + monthly.toFixed(2) + ' / mo';
}

loanSelect.addEventListener('change', updateLoanCalculation);
</script>

</body>
</html>