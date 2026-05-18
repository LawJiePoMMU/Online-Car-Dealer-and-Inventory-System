<?php
session_start();
require 'db.php'; // Ensure your PDO connection points to online_car_dealer_and_inventory_db

// Redirect if the user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Capture user inputs from the reservation form
    $user_id      = $_SESSION['user_id'];
    $car_id       = intval($_POST['car_id'] ?? 0); // Passed via hidden form input
    
    $input_date   = $_POST['reserve_date'] ?? ''; // e.g., "2026-05-21"
    $input_time   = $_POST['reserve_time'] ?? ''; // e.g., "14:30"
    
    $user_name    = $_POST['user_name'] ?? '';
    $user_phone   = $_POST['user_phone'] ?? '';
    $user_email   = $_POST['user_email'] ?? '';
    $user_ic      = $_POST['user_ic'] ?? '';
    
    // NEW: Capture variant and color choices chosen by the user from your form dropdowns
    $chosen_variant = $_POST['car_variant'] ?? ''; 
    $chosen_color   = $_POST['car_color'] ?? ''; 

    // 2. Fetch comprehensive car specs directly from your friend's relational structure
    $car_stmt = $pdo->prepare("SELECT * FROM cars WHERE car_id = ?");
    $car_stmt->execute([$car_id]);
    $car = $car_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$car) {
        die("Error: Selected car does not exist.");
    }

    // EXTRA FETCH A: Pull the license plate if this is a "Used Car"
    $plate_number = "";
    if ($car['car_origin'] === 'Used Car') {
        $plate_stmt = $pdo->prepare("SELECT car_plate FROM used_car_details WHERE car_id = ?");
        $plate_stmt->execute([$car_id]);
        $plate_data = $plate_stmt->fetch(PDO::FETCH_ASSOC);
        $plate_number = $plate_data['car_plate'] ?? "";
    }

    // EXTRA FETCH B: Pull the official main photo asset link for this vehicle
    $img_stmt = $pdo->prepare("SELECT car_image_url FROM car_image WHERE car_id = ? LIMIT 1");
    $img_stmt->execute([$car_id]);
    $img_data = $img_stmt->fetch(PDO::FETCH_ASSOC);
    $car_image_url = $img_data['car_image_url'] ?? "";


    // 3. Combine the separate Date & Time strings into a clean MySQL DATETIME pattern
    $preferred_test_drive_at = null;
    if (!empty($input_date) && !empty($input_time)) {
        $preferred_test_drive_at = $input_date . ' ' . $input_time . ':00';
    }


    // 4. Construct the required JSON dictionary dynamically populated with database properties
    $snapshot_array = [
        "user_name"  => $user_name,
        "user_phone" => $user_phone,
        "user_email" => $user_email,
        "user_ic"    => $user_ic,
        "car_brand"  => $car['car_brand'],
        "car_model"  => $car['car_model'],
        "car_year"   => (string)$car['car_year'],
        "car_origin" => $car['car_origin'],
        "car_plate"  => $plate_number,    // Now dynamically retrieved!
        "car_image"  => $car_image_url,   // Now dynamically retrieved!
        "car_variant"=> $chosen_variant,  // Captured from active post submit selection!
        "car_color"  => $chosen_color     // Captured from active post submit selection!
    ];
    
    // Encode array into a valid clean JSON string for the database
    $snapshot_json = json_encode($snapshot_array, JSON_UNESCAPED_UNICODE);
if (empty($input_date) || empty($input_time)) {
    die("Please select reservation date and time.");
}

if ($car_id <= 0) {
    die("Invalid car selected.");
}
    try {
        // 5. Build your insert query using your friend's exact column names
        $query = "INSERT INTO reservations (
                    user_id, 
                    car_id, 
                    reservation_created_at, 
                    reservation_status, 
                    preferred_test_drive_at, 
                    snapshot_data
                  ) VALUES (?, ?, NOW(), 'Pending Viewing', ?, ?)";
                  
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $user_id,
            $car_id,
            $preferred_test_drive_at,
            $snapshot_json
        ]);
        
        // Grab the auto-increment ID to display as a confirmation reference code
        $new_reservation_id = $pdo->lastInsertId();
        $_SESSION['reservation_id'] = $new_reservation_id;
        
        // Store reference info in the session to render on a success page
        $_SESSION['success_message'] = "Reservation successful!";
        $_SESSION['ref_number'] = 'RSV-' . str_pad($new_reservation_id, 6, '0', STR_PAD_LEFT);
        
        header("Location: reservation_success.php");
        exit();

    } catch (PDOException $e) {
        die("Database error occurred: " . $e->getMessage());
    }
}
?>
