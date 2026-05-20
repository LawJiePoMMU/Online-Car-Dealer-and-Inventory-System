<?php
session_start();
require 'db.php';

// 1. USER SECURITY: Ensure user is logged in to access data securely
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Extract context target tracking ID passed from view_status.php
$reservation_id = $_GET['reservation_id'] ?? ($_GET['id'] ?? 0);

if (!$reservation_id) {
    header("Location: view_status.php?error=missing_reference");
    exit();
}

try {
    // ENFORCE STATUS CHECK: Only allow 'Approved' status to proceed
    $stmt = $pdo->prepare("
        SELECT *
        FROM reservations
        WHERE reservation_id = ? 
          AND user_id = ? 
          AND reservation_status = 'Approved'
        LIMIT 1
    ");
    $stmt->execute([$reservation_id, $_SESSION['user_id']]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reservation) {
        header("Location: view_status.php?error=unauthorized_or_not_approved");
        exit();
    }

    // SAFE EMPTY JSON OBJECT STRING FALLBACK
    $snapshot = json_decode($reservation['snapshot_data'] ?? '{}', true) ?? [];

} catch (Exception $e) {
    die("Database Connection Error: " . $e->getMessage());
}

// CLEAN STRING TRIM & FALLBACK ASSIGNMENT FOR VEHICLE NAME
$car_name = trim(($snapshot['car_brand'] ?? '') . ' ' . ($snapshot['car_model'] ?? ''));
if (empty($car_name)) {
    $car_name = 'Selected Vehicle';
}

// Map Dynamic Database Variables into Local Display Strings
$car_price           = (float)($snapshot['total_price'] ?? 0);
$plate_number        = $snapshot['car_plate'] ?? 'Pending Registration';
$loan_duration       = ($snapshot['loan_years'] ?? 0) . ' Years';
$monthly_installment = (float)($snapshot['monthly_payment'] ?? 0);

// =========================================================================
// UPDATED FINANCING LOGIC (REMOVED BOOKING DEDUCTION)
// =========================================================================
$insurance_fee       = 3000.00; // Flat price for every car

// 10% down payment strictly on the vehicle price
$base_downpayment    = round($car_price * 0.10, 2);

// Final amount due now: Full 10% Downpayment + Insurance (No deduction)
$final_amount_due    = $base_downpayment + $insurance_fee;
// =========================================================================

// SAVE INSURANCE FILE BACKEND LOGIC HANDLER
$upload_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['insurance_file'])) {
    $file = $_FILES['insurance_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        
        // FILE SIZE VALIDATION (MAX 5MB)
        if ($file['size'] > 5 * 1024 * 1024) { 
            $upload_error = "File size exceeds the 5MB maximum allowed limit.";
        } else {
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
            
            if (in_array($file_ext, $allowed_extensions)) {
                if (!is_dir('uploads/insurance')) {
                    mkdir('uploads/insurance', 0755, true);
                }
                
                $new_file_name = 'INS_RES_' . $reservation_id . '_' . time() . '.' . $file_ext;
                $destination = 'uploads/insurance/' . $new_file_name;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    
                    $snapshot['signed_insurance_path'] = $destination;
                    $updated_json = json_encode($snapshot, JSON_UNESCAPED_SLASHES);
                    
                    $update_stmt = $pdo->prepare("
                        UPDATE reservations 
                        SET snapshot_data = ?
                        WHERE reservation_id = ? AND user_id = ?
                    ");
                    $update_stmt->execute([$updated_json, $reservation_id, $_SESSION['user_id']]);
                    
                    // Establish fallback tracking metrics inside session memory structures
                    $_SESSION['pay_source']         = 'downpayment';
                    $_SESSION['pay_car_id']         = $reservation['car_id'];
                    $_SESSION['pay_amount']         = $final_amount_due;
                    $_SESSION['pay_label']          = 'Down Payment Checkout - ' . $car_name;
                    $_SESSION['pay_reservation_id'] = $reservation_id;
                    
                    $_SESSION['pay_detail_price']   = 'RM ' . number_format($car_price, 2);
                    $_SESSION['pay_detail_loan']    = 'RM ' . number_format($car_price - $base_downpayment, 2);
                    $_SESSION['pay_detail_monthly'] = 'RM ' . number_format($monthly_installment, 2) . ' / month';
                    $_SESSION['pay_detail_tenure']  = $loan_duration;

                    echo "<form id='gate_redir' method='POST' action='payment.php'>
                            <input type='hidden' name='source' value='downpayment'>
                            <input type='hidden' name='reservation_id' value='".htmlspecialchars($reservation_id)."'>
                            <input type='hidden' name='payment_amount' value='".htmlspecialchars($final_amount_due)."'>
                            <input type='hidden' name='payment_label' value='Down Payment Checkout - ".htmlspecialchars($car_name)."'>
                          </form>
                          <script>document.getElementById('gate_redir').submit();</script>";
                    exit();
                } else {
                    $upload_error = "Server storage system write failure. Please check directory permissions.";
                }
            } else {
                $upload_error = "Invalid file type extension. Only PDF, JPG, and PNG are accepted.";
            }
        }
    } else {
        $upload_error = "Failed to transmit file data package safely. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Insurance & Down Payment Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    *{ margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
    body{ background:#f4f7fb; color:#1e293b; }
    .navbar{ background:white; padding:18px 50px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 12px rgba(0,0,0,0.05); }
    .logo{ font-size:26px; font-weight:700; color:#2563eb; }
    .nav-links{ display:flex; gap:25px; }
    .nav-links a{ text-decoration:none; color:#334155; font-weight:500; }
    .hero{ background:linear-gradient(135deg,#2563eb,#1d4ed8); color:white; padding:60px 20px; text-align:center; }
    .hero h1{ font-size:42px; margin-bottom:12px; }
    .hero p{ opacity:0.9; }
    .container{ max-width:1300px; margin:auto; padding:40px 20px; }
    .portal-grid{ display:grid; grid-template-columns:1fr 1.1fr; gap:30px; }
    .card{ background:white; border-radius:24px; padding:30px; box-shadow:0 10px 30px rgba(0,0,0,0.06); }
    .car-image{ width:100%; height:260px; object-fit:cover; border-radius:18px; margin-bottom:20px; }
    .section-title{ font-size:24px; font-weight:700; margin-bottom:25px; }
    .car-title{ font-size:30px; font-weight:700; margin-bottom:8px; }
    .car-price{ font-size:32px; font-weight:700; color:#2563eb; margin-bottom:20px; }
    .info-row{ display:flex; justify-content:space-between; padding:14px 0; border-bottom:1px solid #edf2f7; }
    .info-row:last-child{ border-bottom:none; }
    .info-label{ color:#64748b; }
    .info-value{ font-weight:600; }
    .badge{ display:inline-block; padding:8px 16px; border-radius:50px; font-size:12px; font-weight:700; text-transform:uppercase; }
    .approved{ background:#dcfce7; color:#166534; }
    .summary-box{ background:#eff6ff; border-radius:18px; padding:22px; margin-top:25px; }
    .summary-title{ font-size:20px; font-weight:700; margin-bottom:18px; }
    .summary-row{ display:flex; justify-content:space-between; padding:10px 0; }
    .summary-label{ color:#64748b; }
    .summary-value{ font-weight:600; }
    .highlight{ color:#2563eb; font-size:24px; font-weight:700; }
    .doc-box{ background:#f8fafc; border:2px dashed #cbd5e1; border-radius:18px; padding:25px; text-align:center; transition:0.2s; margin-bottom:22px; }
    .doc-box:hover{ border-color:#2563eb; background:#eff6ff; }
    .doc-title{ font-size:18px; font-weight:700; margin-bottom:10px; }
    .doc-text{ font-size:14px; color:#64748b; margin-bottom:18px; line-height:1.6; }
    .btn{ display:inline-block; padding:14px 24px; border-radius:12px; text-decoration:none; font-weight:600; transition:0.2s; cursor:pointer; }
    .btn-download{ background:#2563eb; color:white; }
    .btn-download:hover{ background:#1d4ed8; }
    .btn-pay{ width:100%; text-align:center; background:#22c55e; color:white; border:none; padding:16px; border-radius:14px; font-size:16px; font-weight:700; cursor:pointer; transition:0.2s; margin-top:25px; }
    .btn-pay:hover{ background:#16a34a; }
    .file-input{ margin-top:15px; }
    .alert-danger { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; text-align: left; }
    .security-box{ background:#f8fafc; border:1px solid #e2e8f0; border-radius:16px; padding:20px; margin-top:25px; }
    .security-title{ font-weight:700; margin-bottom:10px; }
    .security-text{ font-size:14px; color:#64748b; line-height:1.6; }
    @media(max-width:1000px){ .portal-grid{ grid-template-columns:1fr; } }
    @media(max-width:768px){ .hero h1{ font-size:32px; } .navbar{ flex-direction:column; gap:15px; } }
</style>
</head>
<body>

<nav class="navbar">
    <div class="logo">AutoDeal</div>
    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="view_status.php">View Status</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="hero">
    <h1>Insurance & Down Payment Portal</h1>
    <p>Complete your insurance documentation and proceed with down payment securely.</p>
</div>

<div class="container">
    <div class="portal-grid">

        <div class="card">
            <img src="<?php echo htmlspecialchars($snapshot['car_image'] ?? 'images/default_car.jpg'); ?>" class="car-image" alt="Vehicle Asset Image">
            <div class="car-title"><?php echo htmlspecialchars($car_name); ?></div>
            <div class="car-price">RM <?php echo number_format($car_price, 2); ?></div>

            <div class="info-row">
                <div class="info-label">Application Status</div>
                <div class="info-value">
                    <span class="badge approved">
                        <?php echo htmlspecialchars($reservation['reservation_status']); ?>
                    </span>
                </div>
            </div>

            <div class="info-row">
                <div class="info-label">Plate Number</div>
                <div class="info-value"><?php echo htmlspecialchars($plate_number); ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">Loan Duration</div>
                <div class="info-value"><?php echo htmlspecialchars($loan_duration); ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">Monthly Installment</div>
                <div class="info-value">RM <?php echo number_format($monthly_installment, 2); ?> / month</div>
            </div>

            <div class="summary-box">
                <div class="summary-title">Payment Summary</div>
                
                <div class="summary-row">
                    <div class="summary-label">Vehicle Down Payment (10%)</div>
                    <div class="summary-value">RM <?php echo number_format($base_downpayment, 2); ?></div>
                </div>

                <div class="summary-row">
                    <div class="summary-label">Flat Insurance Fee</div>
                    <div class="summary-value">RM <?php echo number_format($insurance_fee, 2); ?></div>
                </div>

                <hr style="margin:15px 0; border:0; border-top:1px solid #cbd5e1;">

                <div class="summary-row">
                    <div class="summary-label">Total Outstanding Balance Due</div>
                    <div class="summary-value highlight">RM <?php echo number_format($final_amount_due, 2); ?></div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2 class="section-title">Insurance Documentation</h2>

            <?php if ($upload_error): ?>
                <div class="alert-danger">⚠️ <?php echo htmlspecialchars($upload_error); ?></div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?reservation_id=' . htmlspecialchars($reservation_id); ?>" method="POST" enctype="multipart/form-data">

                <div class="doc-box">
                    <div class="doc-title">📄 Download Insurance Agreement</div>
                    <div class="doc-text">Download the insurance agreement form, complete the required information, sign the document, and upload the modified version below.</div>
                    <a href="documents/insurance_form.pdf" class="btn btn-download" download>Download Insurance Form</a>
                </div>

                <div class="doc-box">
                    <div class="doc-title">☁️ Upload Signed Insurance File</div>
                    <div class="doc-text">Upload the completed and signed insurance agreement in PDF, JPG, or PNG format. (Max size: 5MB)</div>
                    <input type="file" name="insurance_file" class="file-input" accept=".pdf,.png,.jpg,.jpeg" required>
                </div>

                <div class="security-box">
                    <div class="security-title">🔒 Secure Verification Process</div>
                    <div class="security-text">All uploaded insurance and financing documents are securely stored and verified by authorized dealership personnel.</div>
                </div>

                <input type="hidden" name="source" value="downpayment">
                <input type="hidden" name="reservation_id" value="<?php echo htmlspecialchars($reservation_id); ?>">
                <input type="hidden" name="payment_amount" value="<?php echo htmlspecialchars($final_amount_due); ?>">

                <button type="submit" class="btn-pay">Upload & Proceed To Down Payment →</button>
            </form>
        </div>

    </div>
</div>

</body>
</html>