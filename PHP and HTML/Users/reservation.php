<?php
session_start();
require 'db.php'; 

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// IMPROVEMENT 1: CAPTURE AND VALIDATE GET PARAMETER IMMEDIATELY
if (!isset($_GET['car_id']) || empty($_GET['car_id'])) {
    die("System Error: No vehicle was specified for booking.");
}
$car_id = intval($_GET['car_id']);

// IMPROVEMENT 1 (CRITICAL): Query database to confirm the vehicle structurally exists
$car_check_stmt = $pdo->prepare("
    SELECT car_brand, car_model, car_origin 
    FROM cars 
    WHERE car_id = ?
");
$car_check_stmt->execute([$car_id]);
$car_exists = $car_check_stmt->fetch(PDO::FETCH_ASSOC);

if (!$car_exists) {
    die("Security Exception: The requested vehicle resource does not exist in our inventory.");
}

// FETCH USER DATA AFTER LOGIN FOR AUTO-FILL
$user_stmt = $pdo->prepare("
    SELECT full_name, email, phone 
    FROM users 
    WHERE user_id = ?
");
$user_stmt->execute([$user_id]);
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Fallbacks to avoid PHP undefined index notices if a user profile is missing data
$fetched_name  = $user_data['full_name'] ?? '';
$fetched_email = $user_data['email'] ?? '';
$fetched_phone = $user_data['phone'] ?? '';

// Format a clean display title for the page context
$car_display_name = htmlspecialchars($car_exists['car_brand'] . ' ' . $car_exists['car_model']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Reservation - <?php echo $car_display_name; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: #f4f7fb; color: #333; padding: 40px 20px; }
        .form-container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 20px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,0.02); }
        .form-header { margin-bottom: 30px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; }
        .form-header h2 { font-size: 24px; color: #1e293b; font-weight: 700; }
        .form-header p { font-size: 14px; color: #64748b; margin-top: 5px; }
        .form-group { margin-bottom: 20px; display: flex; flex-direction: column; }
        .form-group label { font-size: 14px; font-weight: 600; color: #475569; margin-bottom: 8px; }
        .form-input { padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 10px; width: 100%; font-size: 14px; transition: border-color 0.2s; }
        .form-input:focus { outline: none; border-color: #2563eb; }
        .btn-submit { background: #2563eb; color: white; border: none; padding: 14px; width: 100%; font-size: 15px; font-weight: 600; border-radius: 10px; cursor: pointer; transition: background 0.2s; margin-top: 10px; }
        .btn-submit:hover { background: #1d4ed8; }
    </style>
</head>
<body>

<div class="form-container">
    <div class="form-header">
        <h2>Secure Vehicle Reservation</h2>
        <p>Booking Target: <strong><?php echo $car_display_name; ?></strong> (<?php echo htmlspecialchars($car_exists['car_origin']); ?>)</p>
    </div>

    <form action="process_reservation.php" method="POST">
        
        <input type="hidden" name="car_id" value="<?php echo htmlspecialchars($car_id); ?>">

        <div class="form-group">
            <label>Full Name</label>
            <input type="text" 
                   name="user_name" 
                   class="form-input" 
                   value="<?php echo htmlspecialchars($fetched_name); ?>" 
                   required>
        </div>

        <div class="form-group">
            <label>Email Address</label>
            <input type="email" 
                   name="user_email" 
                   class="form-input" 
                   value="<?php echo htmlspecialchars($fetched_email); ?>" 
                   required>
        </div>

        <div class="form-group">
            <label>Phone Number</label>
            <input type="text" 
                   name="user_phone" 
                   class="form-input" 
                   value="<?php echo htmlspecialchars($fetched_phone); ?>" 
                   required>
        </div>

        <div class="form-group">
            <label>IC / Passport Number</label>
            <input type="text" 
                   name="user_ic" 
                   class="form-input" 
                   placeholder="e.g. 021024045321" 
                   required>
        </div>
        
        <div class="form-group">
            <label>Preferred Variant</label>
            <select name="car_variant" class="form-input" required>
                <option value="">-- Select Variant --</option>
                <option value="Standard Base">Standard Base</option>
                <option value="Executive Spec">Executive Spec</option>
                <option value="Premium Sport RS">Premium Sport RS</option>
            </select>
        </div>

        <div class="form-group">
            <label>Preferred Exterior Color</label>
            <select name="car_color" class="form-input" required>
                <option value="">-- Select Color --</option>
                <option value="Platinum White Pearl">Platinum White Pearl</option>
                <option value="Meteoroid Gray Metallic">Meteoroid Gray Metallic</option>
                <option value="Crystal Black Pearl">Crystal Black Pearl</option>
                <option value="Ignite Red Metallic">Ignite Red Metallic</option>
            </select>
        </div>

        <div class="form-group">
            <label>Preferred Viewing Date</label>
            <input type="date" name="reserve_date" class="form-input" required>
        </div>

        <div class="form-group">
            <label>Preferred Viewing Time</label>
            <input type="time" name="reserve_time" class="form-input" required>
        </div>

        <button type="submit" class="btn-submit">Confirm & Submit Request</button>
    </form>
</div>

</body>
</html>