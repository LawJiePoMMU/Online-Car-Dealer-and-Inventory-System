<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

// NOTE: this file lives in PHP AND HTML/Users/, so database is at ../Config/
require '../Config/database.php';

// ======================================================
// SESSION CHECK (align with Auth/login.php)
// ======================================================

if (
    !isset($_SESSION['loggedin']) ||
    $_SESSION['loggedin'] !== true ||
    !isset($_SESSION['id']) ||
    strcasecmp($_SESSION['role'] ?? '', 'Customer') !== 0
) {
    header("Location: Auth/login.php");
    exit();

}

$reservation_id = 0;
if (isset($_GET['id']) && intval($_GET['id']) > 0) {
    $reservation_id = intval($_GET['id']);
} elseif (!empty($_SESSION['reservation_id'])) {
    $reservation_id = intval($_SESSION['reservation_id']);
}

$user_id = (int) $_SESSION['id'];

// Render styled error if no valid reservation ID
if ($reservation_id <= 0) {
    include 'Includes/header.php';
    echo '
    <div style="min-height:60vh;display:flex;align-items:center;justify-content:center;padding:40px 20px;">
        <div style="max-width:480px;background:#fff;border:1px solid #f1f5f9;border-radius:18px;padding:40px;text-align:center;box-shadow:0 8px 24px rgba(0,0,0,0.05);">
            <div style="width:70px;height:70px;background:#fef3c7;color:#d97706;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 20px;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2 style="color:#1e293b;font-size:22px;margin-bottom:10px;">Reservation Not Found</h2>
            <p style="color:#64748b;font-size:14px;line-height:1.6;margin-bottom:24px;">
                Your reservation session has expired, or this page was opened without a valid reservation reference.
            </p>
            <a href="cars.php" style="display:inline-block;background:#1e293b;color:white;padding:12px 28px;border-radius:10px;text-decoration:none;font-weight:600;">Browse Vehicles</a>
        </div>
    </div>';
    include 'Includes/footer.php';
    exit;
}

// ======================================================
// FETCH RESERVATION (including licence URL)
// ======================================================

$reservation_sql = "
SELECT
    reservation_id,
    reservation_status,
    reservation_created_at,
    preferred_test_drive_at,
    driving_licence_url,
    snapshot_data
FROM reservations
WHERE reservation_id = ?
AND user_id = ?
LIMIT 1
";

$reservation_stmt = mysqli_prepare($conn, $reservation_sql);
mysqli_stmt_bind_param($reservation_stmt, "ii", $reservation_id, $user_id);
mysqli_stmt_execute($reservation_stmt);
$reservation_result = mysqli_stmt_get_result($reservation_stmt);
$reservation = mysqli_fetch_assoc($reservation_result);
mysqli_stmt_close($reservation_stmt);

if (!$reservation) {
    die("Reservation record not found.");
}

// ======================================================
// DECODE SNAPSHOT
// ======================================================

$snapshot = json_decode($reservation['snapshot_data'], true);
if (!is_array($snapshot)) $snapshot = [];

$car_brand   = $snapshot['car_brand']   ?? 'Unknown Brand';
$car_model   = $snapshot['car_model']   ?? 'Unknown Model';
$car_year    = $snapshot['car_year']    ?? 'N/A';
$car_origin  = $snapshot['car_origin']  ?? 'N/A';
$car_price   = floatval($snapshot['car_price']   ?? 0);
$car_variant = $snapshot['car_variant'] ?? '-';
$car_color   = $snapshot['car_color']   ?? '-';
$car_plate   = $snapshot['car_plate']   ?? '';

$user_name   = $snapshot['user_name']   ?? 'Customer';
$user_email  = $snapshot['user_email']  ?? 'N/A';
$user_phone  = $snapshot['user_phone']  ?? 'N/A';
$user_ic     = $snapshot['user_ic']     ?? 'N/A';

$car_image = !empty($snapshot['car_image'])
    ? $snapshot['car_image']
    : 'https://via.placeholder.com/600x400.png?text=Vehicle+Image';

$licence_url = $reservation['driving_licence_url'] ?? '';
$is_used_car = (strcasecmp($car_origin, 'Used Car') === 0);

// ======================================================
// REFERENCE NUMBER
// ======================================================
$reservation_ref = 'RES' . str_pad($reservation_id, 3, '0', STR_PAD_LEFT);

$reservation_status = $reservation['reservation_status'] ?? 'Pending Viewing';
$status_lower       = strtolower($reservation_status);

// ======================================================
// FORMAT DATES
// ======================================================

$created_at = 'N/A';
if (!empty($reservation['reservation_created_at'])) {
    $ts = strtotime($reservation['reservation_created_at']);
    if ($ts) $created_at = date('d M Y, h:i A', $ts);
}

$test_drive_at = 'Not Scheduled';
if (!empty($reservation['preferred_test_drive_at'])) {
    $ts = strtotime($reservation['preferred_test_drive_at']);
    if ($ts) $test_drive_at = date('d M Y, h:i A', $ts);
}

include 'Includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

<style>
.success-page{
    background:#f8fafc;
    min-height:calc(100vh - 80px);
    padding:40px 20px 60px;
}
.success-container{
    max-width:920px;
    margin:0 auto;
    background:#ffffff;
    border-radius:18px;
    overflow:hidden;
    border:1px solid #f1f5f9;
    box-shadow:0 1px 2px rgba(0,0,0,0.03), 0 8px 24px rgba(0,0,0,0.05);
}

.success-header{
    background:linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    color:white;
    padding:40px;
    text-align:center;
}
.success-icon{
    width:80px;
    height:80px;
    background:#ffffff;
    color:#1e293b;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:36px;
    margin:0 auto 18px;
    box-shadow:0 8px 24px rgba(0,0,0,0.15);
}
.success-header h1{
    font-size:30px;
    font-weight:700;
    margin-bottom:10px;
    letter-spacing:-0.5px;
}
.success-header p{
    opacity:0.85;
    font-size:14px;
    max-width:580px;
    margin:0 auto;
    line-height:1.6;
}

.content-wrapper{
    padding:32px;
}

/* Reference number */
.reference-box{
    background:#f8fafc;
    border:1.5px dashed #cbd5e1;
    padding:18px 22px;
    border-radius:12px;
    margin-bottom:26px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.reference-box .label{
    color:#64748b;
    font-size:12px;
    text-transform:uppercase;
    font-weight:700;
    letter-spacing:0.6px;
    margin-bottom:4px;
}
.reference-box .number{
    font-size:22px;
    font-weight:800;
    color:#1e293b;
    letter-spacing:1px;
    font-family:'Courier New',monospace;
}
.status-badge{
    padding:6px 14px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:0.5px;
}
.status-pending-viewing,
.status-pending{ background:#fef3c7; color:#92400e; }
.status-approved{ background:#dcfce7; color:#166534; }
.status-rejected{ background:#fee2e2; color:#991b1b; }

/* Sections */
.detail-section{
    margin-bottom:26px;
}
.detail-section h3{
    font-size:13px;
    font-weight:700;
    color:#1e293b;
    text-transform:uppercase;
    letter-spacing:0.8px;
    margin-bottom:14px;
    padding-bottom:10px;
    border-bottom:2px solid #f1f5f9;
    display:flex;
    align-items:center;
    gap:8px;
}
.detail-section h3 i{ color:#1e293b; font-size:14px; }

/* Vehicle preview */
.vehicle-section{
    display:grid;
    grid-template-columns:300px 1fr;
    gap:22px;
    align-items:flex-start;
}
@media(max-width:700px){
    .vehicle-section{ grid-template-columns:1fr; }
}
.vehicle-section img{
    width:100%;
    height:200px;
    object-fit:cover;
    border-radius:12px;
    border:1px solid #e2e8f0;
}
.vehicle-section h2{
    font-size:24px;
    color:#0f172a;
    margin-bottom:6px;
    text-transform:uppercase;
    letter-spacing:0.3px;
}
.vehicle-section .origin-tag{
    display:inline-block;
    background:#f1f5f9;
    color:#64748b;
    padding:3px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:600;
    margin-bottom:10px;
}
.vehicle-section .price{
    font-size:24px;
    font-weight:800;
    color:#dc2626;
    margin-top:8px;
}

/* Info grid */
.info-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(180px,1fr));
    gap:12px;
}
.info-card{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    padding:14px 16px;
    border-radius:10px;
}
.info-card label{
    display:block;
    font-size:11px;
    color:#94a3b8;
    text-transform:uppercase;
    letter-spacing:0.5px;
    font-weight:700;
    margin-bottom:5px;
}
.info-card p{
    font-size:14px;
    color:#1e293b;
    font-weight:600;
}
.info-card.plate p{ color:#dc2626; }

/* Driving licence area */
.licence-box{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:10px;
    padding:16px;
    display:flex;
    flex-direction:column;
    gap:10px;
}
.licence-actions{
    display:flex;
    align-items:center;
    gap:10px;
}
.btn-view-doc{
    background:#1e293b;
    color:white;
    border:none;
    padding:9px 16px;
    border-radius:8px;
    cursor:pointer;
    font-size:13px;
    font-weight:600;
    display:inline-flex;
    align-items:center;
    gap:6px;
    transition:0.2s;
    font-family:'Poppins',sans-serif;
}
.btn-view-doc:hover{
    background:#0f172a;
    transform:translateY(-1px);
}
.licence-missing{
    color:#dc2626;
    font-size:13px;
    font-weight:600;
}
#licenceFrame{
    width:100%;
    height:480px;
    border:1px solid #cbd5e1;
    border-radius:8px;
    display:none;
}

/* Note */
.note-box{
    background:#f1f5f9;
    border-left:4px solid #1e293b;
    padding:16px 20px;
    border-radius:8px;
    margin:24px 0;
}
.note-box p{
    font-size:13px;
    color:#475569;
    line-height:1.7;
    margin:0;
}

/* Buttons */
.button-group{
    display:flex;
    gap:12px;
    margin-top:18px;
    flex-wrap:wrap;
}
.action-btn{
    flex:1;
    min-width:200px;
    text-align:center;
    padding:14px 20px;
    border-radius:10px;
    text-decoration:none;
    font-weight:600;
    font-size:14px;
    transition:0.2s;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
}
.primary-btn{
    background:#1e293b;
    color:white;
    box-shadow:0 4px 6px -1px rgba(30,41,59,0.15);
}
.primary-btn:hover{
    background:#0f172a;
    transform:translateY(-2px);
    box-shadow:0 10px 15px -3px rgba(30,41,59,0.2);
}
.secondary-btn{
    background:#ffffff;
    color:#1e293b;
    border:1.5px solid #cbd5e1;
}
.secondary-btn:hover{
    background:#f8fafc;
    border-color:#1e293b;
}
</style>

<div class="success-page">
    <div class="success-container">

        <!-- Header -->
        <div class="success-header">
            <div class="success-icon"><i class="fas fa-check"></i></div>
            <h1>Reservation Submitted Successfully</h1>
            <p>
                Thank you, <?= htmlspecialchars($user_name) ?>.
                Your test drive reservation request has been received and is awaiting confirmation by our sales team.
            </p>
        </div>

        <div class="content-wrapper">

            <!-- Reference -->
            <div class="reference-box">
                <div>
                    <div class="label">Reservation Reference</div>
                    <div class="number"><?= htmlspecialchars($reservation_ref) ?></div>
                </div>
                <span class="status-badge status-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $reservation_status))) ?>">
                    <?= htmlspecialchars($reservation_status) ?>
                </span>
            </div>

            <!-- Vehicle -->
            <div class="detail-section">
                <h3><i class="fas fa-car"></i> Vehicle Information</h3>
                <div class="vehicle-section">
                    <img src="<?= htmlspecialchars($car_image) ?>" alt="Vehicle">
                    <div>
                        <h2><?= htmlspecialchars($car_brand . ' ' . $car_model) ?></h2>
                        <span class="origin-tag"><?= htmlspecialchars($car_origin) ?></span>
                        <div class="price">RM <?= number_format($car_price, 2) ?></div>

                        <div class="info-grid" style="margin-top:18px;">
                            <div class="info-card">
                                <label>Year</label>
                                <p><?= htmlspecialchars($car_year) ?></p>
                            </div>
                            <div class="info-card">
                                <label>Variant</label>
                                <p><?= htmlspecialchars($car_variant) ?></p>
                            </div>
                            <div class="info-card">
                                <label>Color</label>
                                <p><?= htmlspecialchars($car_color) ?></p>
                            </div>
                            <?php if ($is_used_car && !empty($car_plate)): ?>
                            <div class="info-card plate">
                                <label>Plate Number</label>
                                <p><?= htmlspecialchars($car_plate) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedule -->
            <div class="detail-section">
                <h3><i class="fas fa-calendar-alt"></i> Schedule</h3>
                <div class="info-grid">
                    <div class="info-card">
                        <label>Reservation Date</label>
                        <p><?= htmlspecialchars($created_at) ?></p>
                    </div>
                    <div class="info-card">
                        <label>Preferred Test Drive</label>
                        <p><?= htmlspecialchars($test_drive_at) ?></p>
                    </div>
                </div>
            </div>

            <!-- Customer -->
            <div class="detail-section">
                <h3><i class="fas fa-user-circle"></i> Customer Information</h3>
                <div class="info-grid">
                    <div class="info-card">
                        <label>Full Name</label>
                        <p><?= htmlspecialchars($user_name) ?></p>
                    </div>
                    <div class="info-card">
                        <label>Email</label>
                        <p><?= htmlspecialchars($user_email) ?></p>
                    </div>
                    <div class="info-card">
                        <label>Phone</label>
                        <p><?= htmlspecialchars($user_phone) ?></p>
                    </div>
                    <div class="info-card">
                        <label>IC / Passport</label>
                        <p><?= htmlspecialchars($user_ic) ?></p>
                    </div>
                </div>
            </div>

            <!-- Driving Licence -->
            <div class="detail-section">
                <h3><i class="fas fa-id-card"></i> Driving Licence</h3>
                <div class="licence-box">
                    <?php if (!empty($licence_url)): ?>
                        <div class="licence-actions">
                            <button type="button" class="btn-view-doc" onclick="toggleLicence()">
                                <i class="fas fa-eye"></i> View Document
                            </button>
                            <span style="color:#64748b;font-size:13px;">
                                <i class="fas fa-check-circle" style="color:#16a34a;"></i>
                                Document uploaded successfully
                            </span>
                        </div>
                        <iframe id="licenceFrame" src="<?= htmlspecialchars($licence_url) ?>"></iframe>
                    <?php else: ?>
                        <span class="licence-missing">
                            <i class="fas fa-times-circle"></i> No driving licence on file.
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Note -->
            <div class="note-box">
                <p>
                    <strong>What's next?</strong> Our sales advisor will review your request and contact you shortly
                    to confirm the test drive appointment. Please keep your reservation reference number
                    (<strong><?= htmlspecialchars($reservation_ref) ?></strong>) for future tracking and verification.
                </p>
            </div>

            <!-- Buttons -->
            <div class="button-group">
                <a href="cars.php" class="action-btn primary-btn">
                    <i class="fas fa-car-side"></i> Browse More Vehicles
                </a>
                <a href="index.php" class="action-btn secondary-btn">
                    <i class="fas fa-home"></i> Return Home
                </a>
            </div>

        </div>
    </div>
</div>

<script>
function toggleLicence(){
    const f = document.getElementById('licenceFrame');
    if (!f) return;
    f.style.display = (f.style.display === 'block') ? 'none' : 'block';
}
</script>

<?php
include 'Includes/footer.php';
?>