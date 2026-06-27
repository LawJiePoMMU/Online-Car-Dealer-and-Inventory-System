<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

require '../Config/database.php';
require '../Config/functions.php';

if (
    !isset($_SESSION['loggedin']) ||
    $_SESSION['loggedin'] !== true ||
    !isset($_SESSION['id']) ||
    strcasecmp($_SESSION['role'] ?? '', 'Customer') !== 0
) {
    header("Location: Auth/login.php");
    exit();

}

$user_id = (int) $_SESSION['id'];


if (!isset($_GET['car_id']) || empty($_GET['car_id'])) {
    die("System Error: No car was specified.");
}

$car_id = intval($_GET['car_id']);

if ($car_id <= 0) {
    die("Invalid car reference.");
}

$car_sql = "
SELECT
    c.*,
    cs.car_status_price AS car_price,
    cs.car_status_status,
    ucd.car_plate,
    (SELECT car_image_url FROM car_image WHERE car_id = c.car_id LIMIT 1) AS car_image
FROM cars c
LEFT JOIN car_status cs ON cs.car_id = c.car_id
LEFT JOIN used_car_details ucd ON ucd.car_id = c.car_id
WHERE c.car_id = ?
LIMIT 1
";

$car_stmt = mysqli_prepare($conn, $car_sql);
mysqli_stmt_bind_param($car_stmt, "i", $car_id);
mysqli_stmt_execute($car_stmt);
$car_result = mysqli_stmt_get_result($car_stmt);
$car = mysqli_fetch_assoc($car_result);
mysqli_stmt_close($car_stmt);

if (!$car) {
    die("The selected vehicle does not exist.");
}

$is_used_car = (strcasecmp($car['car_origin'] ?? '', 'Used Car') === 0);
$car_price = isset($car['car_price']) ? floatval($car['car_price']) : 0;


$variants = [];
$colors = [];

$inv_q = mysqli_query(
    $conn,
    "SELECT variant, color_name
     FROM car_inventory
     WHERE car_id = $car_id
     ORDER BY inventory_id ASC"
);

if ($inv_q) {
    while ($r = mysqli_fetch_assoc($inv_q)) {
        if (!empty($r['variant'])) {
            $variants[$r['variant']] = $r['variant'];
        }
        if (!empty($r['color_name'])) {
            $colors[$r['color_name']] = $r['color_name'];
        }
    }
}

$variants = array_values($variants);
$colors = array_values($colors);

$default_variant = $variants[0] ?? '';
$default_color = $colors[0] ?? '';

$user_sql = "
SELECT user_name, user_email, user_phone, user_ic
FROM users
WHERE user_id = ?
LIMIT 1
";

$user_stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($user_stmt);


$errors = [];

$res_name = $_POST['user_name'] ?? ($user['user_name'] ?? '');
$res_email = $_POST['user_email'] ?? ($user['user_email'] ?? '');
$res_phone = $_POST['user_phone'] ?? ($user['user_phone'] ?? '');
$res_ic = $_POST['user_ic'] ?? ($user['user_ic'] ?? '');

$preferred_test_drive_at = $_POST['preferred_test_drive_at'] ?? '';

$car_variant = $is_used_car
    ? $default_variant
    : ($_POST['car_variant'] ?? $default_variant);

$car_color = $is_used_car
    ? $default_color
    : ($_POST['car_color'] ?? $default_color);

$min_datetime = date('Y-m-d', strtotime('+2 days')) . 'T09:00';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty(trim($res_name)))
        $errors[] = "Full Name is required.";
    if (empty(trim($res_email)))
        $errors[] = "Email Address is required.";
    if (empty(trim($res_phone)))
        $errors[] = "Phone Number is required.";
    if (empty(trim($res_ic)))
        $errors[] = "IC / Passport Number is required.";
    if (empty($car_variant))
        $errors[] = "Vehicle variant is required.";
    if (empty($car_color))
        $errors[] = "Vehicle color is required.";
    if (empty($preferred_test_drive_at))
        $errors[] = "Please select your preferred test drive date.";

    if (
        !empty($preferred_test_drive_at) &&
        strtotime($preferred_test_drive_at) < time()
    ) {
        $errors[] = "Test drive date/time cannot be in the past.";
    }
    if (!empty($preferred_test_drive_at)) {
        $earliest_day = strtotime('+2 days', strtotime(date('Y-m-d')));
        if (strtotime($preferred_test_drive_at) < $earliest_day) {
            $errors[] = "Test drive must be booked at least 2 days in advance (earliest date: " . date('d M Y', $earliest_day) . ").";
        }
    }
    if (!empty($preferred_test_drive_at)) {
        $td_ts = strtotime($preferred_test_drive_at);
        if ($td_ts !== false) {
            $minutes_of_day = (int) date('G', $td_ts) * 60 + (int) date('i', $td_ts);
            if ($minutes_of_day < 540 || $minutes_of_day > 1020) { // 540 = 09:00, 1020 = 17:00
                $errors[] = "Test drive time must be between 9:00 AM and 5:00 PM.";
            }
        }
    }
    if ($is_used_car && empty($_POST['ack_used_car'])) {
        $errors[] = "Please tick the box to acknowledge the used car cancellation policy.";
    }

    $licence_ok = false;
    if (!isset($_FILES['driving_licence']) || $_FILES['driving_licence']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "Please upload your driving licence (PDF).";
    } elseif ($_FILES['driving_licence']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Driving licence upload failed. Please try again.";
    } else {
        $ext = strtolower(pathinfo($_FILES['driving_licence']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $errors[] = "Driving licence must be a PDF file.";
        } elseif ($_FILES['driving_licence']['size'] > 5 * 1024 * 1024) {
            $errors[] = "Driving licence file size cannot exceed 5MB.";
        } else {
            $licence_ok = true;
        }
    }

    $dup_stmt = mysqli_prepare(
        $conn,
        "SELECT reservation_id
         FROM reservations
         WHERE user_id = ? AND car_id = ?
         AND reservation_status IN ('Pending Viewing', 'Approved')
         LIMIT 1"
    );
    mysqli_stmt_bind_param($dup_stmt, "ii", $user_id, $car_id);
    mysqli_stmt_execute($dup_stmt);
    $dup_res = mysqli_stmt_get_result($dup_stmt);
    if ($dup_res && mysqli_num_rows($dup_res) > 0) {
        $errors[] = "You already have an active reservation for this vehicle.";
    }
    mysqli_stmt_close($dup_stmt);

    if (empty($errors) && $licence_ok) {
        $target_dir = __DIR__ . '/../../uploads/documents/';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $licence_filename =
            'licence_user' . $user_id .
            '_' . time() .
            '_' . bin2hex(random_bytes(4)) . '.pdf';

        $licence_full_path = $target_dir . $licence_filename;

        if (
            !move_uploaded_file(
                $_FILES['driving_licence']['tmp_name'],
                $licence_full_path
            )
        ) {
            die("Failed to save driving licence file.");
        }

        $licence_url = '../../uploads/documents/' . $licence_filename;

        $snapshot_data = [
            'user_name' => $res_name,
            'user_email' => $res_email,
            'user_phone' => $res_phone,
            'user_ic' => $res_ic,
            'car_brand' => $car['car_brand'],
            'car_model' => $car['car_model'],
            'car_year' => $car['car_year'],
            'car_origin' => $car['car_origin'],
            'car_price' => $car_price,
            'car_variant' => $car_variant,
            'car_color' => $car_color,
            'car_plate' => $car['car_plate'] ?? '',
            'car_image' => $car['car_image'] ?? ''
        ];

        $snapshot_json = json_encode($snapshot_data, JSON_UNESCAPED_UNICODE);

        if ($snapshot_json === false) {
            die("Snapshot encoding failed.");
        }

        $insert_sql = "
        INSERT INTO reservations (
            user_id,
            car_id,
            reservation_status,
            reservation_created_at,
            driving_licence_url,
            preferred_test_drive_at,
            snapshot_data
        ) VALUES (?, ?, ?, NOW(), ?, ?, ?)
        ";

        $insert_stmt = mysqli_prepare($conn, $insert_sql);

        if (!$insert_stmt) {
            die("Reservation Prepare Failed: " . mysqli_error($conn));
        }

        $status_value = 'Pending Viewing';

        mysqli_stmt_bind_param(
            $insert_stmt,
            "iissss",
            $user_id,
            $car_id,
            $status_value,
            $licence_url,
            $preferred_test_drive_at,
            $snapshot_json
        );

        if (!mysqli_stmt_execute($insert_stmt)) {
            die("Reservation Insert Failed: " . mysqli_stmt_error($insert_stmt));
        }

        $reservation_id = mysqli_insert_id($conn);
        mysqli_stmt_close($insert_stmt);
        trigger_new_reservation_alert($conn, $reservation_id, $res_name);

        $_SESSION['reservation_id'] = $reservation_id;
        $_SESSION['ref_number'] = 'RES' . str_pad($reservation_id, 3, '0', STR_PAD_LEFT);

        header("Location: reservation_success.php");
        exit();
    }
}

include 'Includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

<style>
    .reservation-page {
        background: #f8fafc;
        min-height: calc(100vh - 80px);
        padding: 40px 20px 60px;
    }

    .reservation-wrapper {
        max-width: 1100px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 1fr 1.4fr;
        gap: 24px;
    }

    @media (max-width:900px) {
        .reservation-wrapper {
            grid-template-columns: 1fr;
        }
    }

    .page-heading {
        grid-column: 1 / -1;
        margin-bottom: 6px;
    }

    .page-heading h1 {
        font-size: 28px;
        font-weight: 700;
        color: #1e293b;
        letter-spacing: -0.5px;
        margin-bottom: 6px;
    }

    .page-heading p {
        color: #64748b;
        font-size: 14px;
    }

    .res-card {
        background: #ffffff;
        border: 1px solid #f1f5f9;
        border-radius: 16px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03), 0 8px 24px rgba(0, 0, 0, 0.04);
        padding: 28px;
    }

    .vehicle-preview img {
        width: 100%;
        height: 220px;
        object-fit: cover;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        margin-bottom: 18px;
    }

    .vehicle-preview h2 {
        font-size: 22px;
        color: #0f172a;
        font-weight: 700;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .vehicle-preview .vp-origin {
        color: #64748b;
        font-size: 13px;
        margin-bottom: 14px;
        display: inline-block;
        padding: 3px 10px;
        background: #f1f5f9;
        border-radius: 999px;
        font-weight: 500;
    }

    .vp-price {
        font-size: 24px;
        font-weight: 800;
        color: #dc2626;
        margin: 10px 0 18px;
    }

    .vp-spec {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        padding-top: 16px;
        border-top: 1px solid #f1f5f9;
    }

    .vp-spec-item label {
        font-size: 11px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 700;
        display: block;
        margin-bottom: 3px;
    }

    .vp-spec-item p {
        font-size: 14px;
        color: #1e293b;
        font-weight: 600;
    }

    .vp-spec-item .plate {
        color: #dc2626;
    }

    .form-section {
        margin-bottom: 24px;
    }

    .form-section:last-of-type {
        margin-bottom: 0;
    }

    .form-section h3 {
        font-size: 13px;
        color: #1e293b;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        font-weight: 700;
        margin-bottom: 14px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .form-section h3 i {
        color: #1e293b;
        font-size: 14px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        margin-bottom: 14px;
    }

    .form-row.single {
        grid-template-columns: 1fr;
    }

    @media (max-width:600px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }

    .form-group label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .form-input {
        width: 100%;
        padding: 12px 14px;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        font-family: 'Poppins', sans-serif;
        background: #ffffff;
        color: #1e293b;
        transition: 0.2s;
        outline: none;
    }

    .form-input:focus {
        border-color: #1e293b;
        box-shadow: 0 0 0 3px rgba(30, 41, 59, 0.08);
    }

    .form-input[readonly],
    .form-input:disabled {
        background: #f8fafc;
        color: #475569;
        cursor: not-allowed;
    }

    select.form-input {
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'><path fill='%2364748b' d='M6 8L0 0h12z'/></svg>");
        background-repeat: no-repeat;
        background-position: right 14px center;
        padding-right: 36px;
    }

    .file-upload {
        border: 2px dashed #cbd5e1;
        border-radius: 10px;
        padding: 24px;
        text-align: center;
        cursor: pointer;
        transition: 0.2s;
        background: #f8fafc;
        position: relative;
    }

    .file-upload:hover {
        border-color: #1e293b;
        background: #f1f5f9;
    }

    .file-upload input[type="file"] {
        position: absolute;
        inset: 0;
        opacity: 0;
        cursor: pointer;
    }

    .file-upload .icon {
        font-size: 32px;
        color: #94a3b8;
        margin-bottom: 8px;
    }

    .file-upload p {
        color: #64748b;
        font-size: 13px;
        margin: 0;
    }

    .file-upload .file-name {
        font-weight: 600;
        color: #16a34a;
        margin-top: 6px;
        font-size: 13px;
    }

    .file-upload .helper {
        font-size: 11px;
        color: #94a3b8;
        margin-top: 4px;
    }

    .btn-submit {
        width: 100%;
        background: #1e293b;
        color: white;
        border: none;
        padding: 15px;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
        margin-top: 8px;
        font-family: 'Poppins', sans-serif;
        box-shadow: 0 4px 6px -1px rgba(30, 41, 59, 0.2);
    }

    .btn-submit:hover {
        background: #0f172a;
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(30, 41, 59, 0.25);
    }

    .error-box {
        grid-column: 1 / -1;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-left: 4px solid #ef4444;
        color: #991b1b;
        padding: 14px 18px;
        border-radius: 10px;
        margin-bottom: 6px;
    }

    .error-box p {
        margin: 3px 0;
        font-size: 13px;
        font-weight: 500;
    }

    .error-box p::before {
        content: "\f071";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        margin-right: 8px;
        color: #dc2626;
    }

    .locked-hint {
        font-size: 11px;
        color: #94a3b8;
        margin-top: 4px;
        font-style: italic;
    }
</style>

<div class="reservation-page">
    <div class="reservation-wrapper">

        <div class="page-heading">
            <h1>Vehicle Test Drive Reservation</h1>
            <p>Submit your reservation request and schedule your preferred test drive session.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <?php foreach ($errors as $err): ?>
                    <p><?= htmlspecialchars($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="res-card vehicle-preview">
            <img src="<?= !empty($car['car_image'])
                ? htmlspecialchars($car['car_image'])
                : 'https://via.placeholder.com/600x400?text=Vehicle+Image' ?>" alt="Vehicle">

            <h2><?= htmlspecialchars($car['car_brand'] . ' ' . $car['car_model']) ?></h2>
            <span class="vp-origin"><?= htmlspecialchars($car['car_origin']) ?></span>

            <div class="vp-price">RM <?= number_format($car_price, 2) ?></div>

            <div class="vp-spec">
                <div class="vp-spec-item">
                    <label>Year</label>
                    <p><?= htmlspecialchars($car['car_year']) ?></p>
                </div>
                <div class="vp-spec-item">
                    <label>Default Variant</label>
                    <p><?= htmlspecialchars($default_variant ?: '-') ?></p>
                </div>
                <div class="vp-spec-item">
                    <label>Default Color</label>
                    <p><?= htmlspecialchars($default_color ?: '-') ?></p>
                </div>
                <?php if ($is_used_car && !empty($car['car_plate'])): ?>
                    <div class="vp-spec-item">
                        <label>Plate Number</label>
                        <p class="plate"><?= htmlspecialchars($car['car_plate']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="res-card">
            <form method="POST" enctype="multipart/form-data" autocomplete="off">

                <div class="form-section">
                    <h3><i class="fas fa-user-circle"></i> Customer Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="user_name" class="form-input"
                                value="<?= htmlspecialchars($res_name) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="user_email" class="form-input"
                                value="<?= htmlspecialchars($res_email) ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="user_phone" class="form-input"
                                value="<?= htmlspecialchars($res_phone) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>IC / Passport Number</label>
                            <input type="text" name="user_ic" class="form-input"
                                value="<?= htmlspecialchars($res_ic) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-car"></i> Car Configuration</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Preferred Variant</label>

                            <?php if ($is_used_car || count($variants) <= 1): ?>
                                <input type="text" class="form-input"
                                    value="<?= htmlspecialchars($default_variant ?: '-') ?>" readonly>
                                <input type="hidden" name="car_variant" value="<?= htmlspecialchars($default_variant) ?>">
                                <div class="locked-hint">
                                    <?= $is_used_car ? 'Used car — variant fixed to this unit.' : 'Only one variant available.' ?>
                                </div>
                            <?php else: ?>
                                <select name="car_variant" class="form-input" required>
                                    <?php foreach ($variants as $v): ?>
                                        <option value="<?= htmlspecialchars($v) ?>" <?= ($car_variant === $v) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($v) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label>Preferred Color</label>

                            <?php if ($is_used_car || count($colors) <= 1): ?>
                                <input type="text" class="form-input" value="<?= htmlspecialchars($default_color ?: '-') ?>"
                                    readonly>
                                <input type="hidden" name="car_color" value="<?= htmlspecialchars($default_color) ?>">
                                <div class="locked-hint">
                                    <?= $is_used_car ? 'Used car — color fixed to this unit.' : 'Only one color available.' ?>
                                </div>
                            <?php else: ?>
                                <select name="car_color" class="form-input" required>
                                    <?php foreach ($colors as $cl): ?>
                                        <option value="<?= htmlspecialchars($cl) ?>" <?= ($car_color === $cl) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cl) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-calendar-alt"></i> Test Drive Schedule</h3>

                    <div class="form-row single">
                        <div class="form-group">
                            <label>Preferred Date &amp; Time</label>
                            <input type="text" name="preferred_test_drive_at" id="preferred_test_drive_at"
                                class="form-input" readonly value="<?= htmlspecialchars($preferred_test_drive_at) ?>"
                                min="<?= htmlspecialchars($min_datetime) ?>" required>
                            <div class="locked-hint">Available 9:00 AM – 5:00 PM only, and at least 2 days in advance
                                (earliest: <?= date('d M Y', strtotime('+2 days')) ?>). Our team will confirm
                                availability.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-id-card"></i> Driving Licence</h3>

                    <div class="form-row single">
                        <div class="form-group">
                            <label>Upload your Driving Licence (PDF)</label>
                            <label class="file-upload" id="licenceDrop">
                                <input type="file" name="driving_licence" id="driving_licence" accept="application/pdf"
                                    required>
                                <div class="icon"><i class="fas fa-file-pdf"></i></div>
                                <p><strong>Click to upload</strong> or drag &amp; drop</p>
                                <div class="file-name" id="fileName"></div>
                                <div class="helper">PDF only · Max 5MB</div>
                            </label>
                        </div>
                    </div>
                </div>
                <?php if ($is_used_car): ?>
                    <div class="form-section" style="margin-bottom:18px;">
                        <label
                            style="display:flex;align-items:flex-start;gap:10px;font-size:13px;color:#475569;font-weight:500;cursor:pointer;line-height:1.6;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:14px 16px;">
                            <input type="checkbox" name="ack_used_car" value="1" required
                                style="margin-top:3px;flex-shrink:0;width:16px;height:16px;cursor:pointer;"
                                <?= !empty($_POST['ack_used_car']) ? 'checked' : '' ?>>
                            <span>I understand this is a used car with only one unit available. 
                               <br> If another customer books this car first including while my test drive is being 
                                arranged my reservation may be cancelled automatically.</span>
                        </label>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i>&nbsp; Submit Reservation Request
                </button>
            </form>
        </div>

    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    flatpickr("#preferred_test_drive_at", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",

        minDate: new Date().fp_incr(2),

        minTime: "09:00",
        maxTime: "17:00",

        clickOpens: true,
        allowInput: false,
        time_24hr: true
    });
    document.getElementById('driving_licence').addEventListener('change', function (e) {
        const f = e.target.files[0];
        const out = document.getElementById('fileName');
        if (f) {
            if (f.size > 5 * 1024 * 1024) {
                alert('File exceeds 5MB limit.');
                e.target.value = '';
                out.textContent = '';
                return;
            }
            out.textContent = '✓ ' + f.name + ' (' + (f.size / 1024).toFixed(1) + ' KB)';
        } else {
            out.textContent = '';
        }
    });
    (function () {
        const tdInput = document.getElementById('preferred_test_drive_at');
        if (!tdInput) return;
        function checkBusinessHours() {
            tdInput.setCustomValidity('');
            if (!tdInput.value) return;
            const timePart = tdInput.value.slice(-5);
            const [hh, mm] = timePart.split(':').map(Number);
            if (isNaN(hh)) return;
            const mins = hh * 60 + (mm || 0);
            if (mins < 540 || mins > 1020) {
                tdInput.setCustomValidity('Please choose a time between 9:00 AM and 5:00 PM.');
                tdInput.reportValidity();
            }
        }
        tdInput.addEventListener('change', checkBusinessHours);
        tdInput.addEventListener('input', checkBusinessHours);
    })();
</script>

<?php include 'Includes/footer.php'; ?>