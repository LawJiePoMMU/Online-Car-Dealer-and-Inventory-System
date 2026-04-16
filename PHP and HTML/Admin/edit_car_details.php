<?php
session_start();
include '../database.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_GET['car_id'])) {
    header("Location: manage cars.php");
    exit();
}

$car_id = (int) $_GET['car_id'];

$query = "SELECT c.*, stat.car_status_price, stat.car_status_status, stat.car_status_stock_quantity, ct.car_type_name 
          FROM cars c 
          LEFT JOIN car_status stat ON c.car_id = stat.car_id 
          LEFT JOIN car_types ct ON c.car_type_id = ct.car_type_id
          WHERE c.car_id = $car_id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    header("Location: manage cars.php");
    exit();
}

$car = mysqli_fetch_assoc($result);

$is_used_car = ($car['car_origin'] === 'Used Car');

if (isset($_POST['save_all_details'])) {
    header("Location: manage cars.php?success=1");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Details - CAR<?= str_pad($car_id, 3, '0', STR_PAD_LEFT) ?></title>
    <link rel="stylesheet" href="../../CSS/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        .form-section {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .section-header {
            font-size: 16px;
            font-weight: 700;
            color: #111827;
            border-bottom: 2px solid #f3f4f6;
            padding-bottom: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-header i {
            color: var(--primary-color);
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
        }

        .grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            color: #111827;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-control[readonly] {
            background-color: #f3f4f6;
            color: #6b7280;
            cursor: not-allowed;
            font-weight: 600;
        }

        .radio-group {
            display: flex;
            gap: 16px;
            align-items: center;
            height: 40px;
        }

        .checkbox-group {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            margin-top: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #374151;
            cursor: pointer;
        }

        .inner-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 12px;
            overflow-x: auto;
        }

        .inner-tab-btn {
            padding: 10px 20px;
            background: none;
            border: none;
            font-size: 14px;
            font-weight: 600;
            color: #6b7280;
            cursor: pointer;
            border-radius: 6px;
            transition: 0.2s;
            white-space: nowrap;
        }

        .inner-tab-btn:hover {
            background: #f3f4f6;
            color: #111827;
        }

        .inner-tab-btn.active {
            background: #e0e7ff;
            color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .origin-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .origin-new {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .origin-used {
            background: #ffedd5;
            color: #9a3412;
            border: 1px solid #fed7aa;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <form method="POST" enctype="multipart/form-data">

            <header class="topbar"
                style="margin-bottom: 24px; position: sticky; top: 0; background: var(--bg-color); z-index: 100; padding-top: 16px; padding-bottom: 16px; border-bottom: 1px solid #e5e7eb;">
                <div class="page-title" style="display: flex; align-items: center; gap: 16px;">
                    <a href="manage-cars.php" style="color: #6b7280; font-size: 18px;"><i
                            class="fas fa-arrow-left"></i></a>
                    <h1 style="font-size: 24px; font-weight: 700; color: #111827;">Edit Car Details</h1>
                    <span class="origin-badge <?= $is_used_car ? 'origin-used' : 'origin-new' ?>">
                        <?= htmlspecialchars($car['car_origin']) ?>
                    </span>
                </div>
                <div style="display: flex; gap: 12px;">
                    <a href="manage-cars.php" class="btn-export" style="text-decoration: none;">Cancel</a>
                    <button type="submit" name="save_all_details" class="btn-add-blue" style="border:none;"><i
                            class="fas fa-save"></i> Save All</button>
                </div>
            </header>

            <div class="inner-tabs">
                <button type="button" class="inner-tab-btn active" onclick="openTab('tab-basic')"><i
                        class="fas fa-info-circle"></i> Basic & Pricing</button>
                <button type="button" class="inner-tab-btn" onclick="openTab('tab-specs')"><i class="fas fa-cogs"></i>
                    Specs & Dimensions</button>
                <button type="button" class="inner-tab-btn" onclick="openTab('tab-media')"><i
                        class="fas fa-photo-video"></i> Features & Media</button>

                <?php if ($is_used_car): ?>
                    <button type="button" class="inner-tab-btn" onclick="openTab('tab-history')"
                        style="color: #ea580c; background: #fff7ed; border: 1px solid #fed7aa;"><i
                            class="fas fa-history"></i> Vehicle History & Scoring</button>
                <?php endif; ?>
            </div>

            <div id="tab-basic" class="tab-content active">
                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-car"></i> Basic Info</h3>
                    <div class="grid-3">
                        <div class="form-group"><label>Car ID</label><input type="text" class="form-control"
                                value="CAR<?= str_pad($car['car_id'], 3, '0', STR_PAD_LEFT) ?>" readonly></div>
                        <div class="form-group"><label>Model</label>
                            <select name="model" class="form-control">
                                <option <?= $car['car_model'] == 'Saga' ? 'selected' : '' ?>>Saga</option>
                                <option <?= $car['car_model'] == 'X50' ? 'selected' : '' ?>>X50</option>
                                <option <?= $car['car_model'] == 'X70' ? 'selected' : '' ?>>X70</option>
                                <option <?= $car['car_model'] == 'X90' ? 'selected' : '' ?>>X90</option>
                                <option <?= $car['car_model'] == 'Persona' ? 'selected' : '' ?>>Persona</option>
                                <option <?= $car['car_model'] == 'Iriz' ? 'selected' : '' ?>>Iriz</option>
                                <option <?= $car['car_model'] == 'Exora' ? 'selected' : '' ?>>Exora</option>
                                <option <?= $car['car_model'] == 'S70' ? 'selected' : '' ?>>S70</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Variant/Trim</label><input type="text" name="variant"
                                class="form-control" placeholder="e.g., 1.5 TGDi Flagship"></div>

                        <div class="form-group"><label>Body Type</label>
                            <select name="body_type" class="form-control">
                                <option <?= $car['car_type_name'] == 'Sedan' ? 'selected' : '' ?>>Sedan</option>
                                <option <?= $car['car_type_name'] == 'SUV' ? 'selected' : '' ?>>SUV</option>
                                <option <?= $car['car_type_name'] == 'Hatchback' ? 'selected' : '' ?>>Hatchback</option>
                                <option <?= $car['car_type_name'] == 'MPV' ? 'selected' : '' ?>>MPV</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Year</label><input type="number" name="year" class="form-control"
                                value="<?= $car['car_year'] ?>" min="1990" max="2026"></div>
                        <div class="form-group"><label>Status</label>
                            <select name="status" class="form-control">
                                <option <?= $car['car_status_status'] == 'Active' ? 'selected' : '' ?>>Active</option>
                                <option <?= $car['car_status_status'] == 'Draft' ? 'selected' : '' ?>>Draft</option>
                                <option <?= $car['car_status_status'] == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-tag"></i> Pricing</h3>
                    <div class="grid-4">
                        <div class="form-group"><label>OTR Price (RM)</label><input type="number" step="0.01"
                                name="price" class="form-control" value="<?= $car['car_status_price'] ?>"></div>
                        <div class="form-group"><label>Negotiable</label>
                            <div class="radio-group">
                                <label><input type="radio" name="negotiable" value="Yes" checked> Yes</label>
                                <label><input type="radio" name="negotiable" value="No"> No</label>
                            </div>
                        </div>
                        <div class="form-group"><label>Monthly Installment (RM)</label><input type="number" step="0.01"
                                name="installment" class="form-control"></div>
                        <div class="form-group"><label>Promotion/Rebate</label><input type="text" name="promotion"
                                class="form-control" placeholder="e.g., RM 2000 Cash Rebate"></div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-boxes"></i> Stock & Delivery</h3>
                    <div class="grid-3">
                        <div class="form-group"><label>Stock Available (units)</label><input type="number" name="stock"
                                class="form-control" value="<?= $car['car_status_stock_quantity'] ?>"></div>
                        <div class="form-group"><label>Estimated Delivery</label><input type="text" name="delivery"
                                class="form-control" placeholder="e.g., 4-6 weeks"></div>
                        <div class="form-group"><label>Available Colours</label><input type="text" name="avail_colors"
                                class="form-control" placeholder="e.g., Red, White, Silver"></div>
                        <div class="form-group"><label>Promotion Valid Until</label><input type="date"
                                name="promo_valid" class="form-control"></div>
                        <div class="form-group"><label>Warranty</label><input type="text" name="warranty"
                                class="form-control" placeholder="e.g., 5 years / 150,000 km"></div>
                        <div class="form-group"><label>Free Service Package</label><input type="text"
                                name="free_service" class="form-control" placeholder="e.g., 3 years / 60,000 km"></div>
                    </div>
                </div>
            </div>

            <div id="tab-specs" class="tab-content">
                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-tachometer-alt"></i> Performance & Powertrain</h3>
                    <div class="grid-4">
                        <div class="form-group"><label>Engine Type</label><select name="engine_type"
                                class="form-control">
                                <option>Petrol</option>
                                <option>Hybrid</option>
                                <option>EV</option>
                            </select></div>
                        <div class="form-group"><label>Displacement (cc)</label><input type="number" name="displacement"
                                class="form-control"></div>
                        <div class="form-group"><label>Horsepower (hp)</label><input type="number" name="hp"
                                class="form-control"></div>
                        <div class="form-group"><label>Torque (Nm)</label><input type="number" name="torque"
                                class="form-control"></div>
                        <div class="form-group"><label>Transmission</label><select name="transmission"
                                class="form-control">
                                <option>Auto</option>
                                <option>Manual</option>
                                <option>CVT</option>
                                <option>DCT</option>
                            </select></div>
                        <div class="form-group"><label>Drive Type</label><select name="drive_type" class="form-control">
                                <option>FWD</option>
                                <option>RWD</option>
                                <option>AWD</option>
                            </select></div>
                        <div class="form-group"><label>Fuel Type</label><select name="fuel_type" class="form-control">
                                <option>RON95</option>
                                <option>RON97</option>
                                <option>Diesel</option>
                                <option>Electric</option>
                            </select></div>
                        <div class="form-group"><label>Fuel Consumption (L/100km)</label><input type="number" step="0.1"
                                name="fuel_consumption" class="form-control"></div>
                        <div class="form-group"><label>Battery Range (km, EV only)</label><input type="number"
                                name="battery_range" class="form-control"></div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-ruler-combined"></i> Dimensions & Capacity</h3>
                    <div class="grid-3">
                        <div class="form-group"><label>L × W × H (mm)</label><input type="text" name="dimensions"
                                class="form-control" placeholder="e.g., 4330 x 1800 x 1609"></div>
                        <div class="form-group"><label>Wheelbase (mm)</label><input type="number" name="wheelbase"
                                class="form-control"></div>
                        <div class="form-group"><label>Boot Capacity (L)</label><input type="number" name="boot_cap"
                                class="form-control"></div>
                        <div class="form-group"><label>Fuel Tank (L)</label><input type="number" name="fuel_tank"
                                class="form-control"></div>
                        <div class="form-group"><label>Kerb Weight (kg)</label><input type="number" name="weight"
                                class="form-control"></div>
                        <div class="form-group"><label>Seating Capacity</label><input type="number" name="seats"
                                class="form-control"></div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-fill-drip"></i> Exterior & Interior</h3>
                    <div class="grid-3">
                        <div class="form-group"><label>Exterior Colour</label><input type="text" name="ext_color"
                                class="form-control" placeholder="e.g., Snow White"></div>
                        <div class="form-group"><label>Interior Colour</label><input type="text" name="int_color"
                                class="form-control" placeholder="e.g., Black/Red"></div>
                        <div class="form-group"><label>Seat Material</label><select name="seat_mat"
                                class="form-control">
                                <option>Fabric</option>
                                <option>Leather</option>
                                <option>Half-leather</option>
                            </select></div>
                        <div class="form-group"><label>Wheel Size & Type</label><input type="text" name="wheel_size"
                                class="form-control" placeholder="e.g., 18-inch Alloy"></div>
                        <div class="form-group"><label>Headlights</label><select name="headlights" class="form-control">
                                <option>Halogen</option>
                                <option>LED</option>
                                <option>Matrix LED</option>
                            </select></div>
                        <div class="form-group"><label>Infotainment Screen (inch)</label><input type="text"
                                name="screen" class="form-control" placeholder="e.g., 10.25"></div>
                    </div>
                </div>
            </div>

            <div id="tab-media" class="tab-content">
                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-check-square"></i> Features</h3>

                    <label style="font-weight: 700; color: #111827; margin-top: 10px; display:block;">Safety</label>
                    <div class="checkbox-group">
                        <label class="checkbox-item"><input type="checkbox" name="feat_safety[]" value="ABS">
                            ABS</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_safety[]" value="ESC">
                            ESC</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_safety[]" value="AEB"> AEB
                            (Autonomous Brake)</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_safety[]" value="LKA"> Lane Keep
                            Assist</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_safety[]" value="BSM"> Blind Spot
                            Monitor</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_safety[]" value="360 Cam"> 360°
                            Camera</label>
                        <div class="form-group" style="grid-column: span 3; margin-top: 8px;"><input type="number"
                                name="airbags" class="form-control" placeholder="Airbags Count (e.g., 6)"
                                style="width: 200px;"></div>
                    </div>

                    <label style="font-weight: 700; color: #111827; margin-top: 24px; display:block;">Technology</label>
                    <div class="checkbox-group">
                        <label class="checkbox-item"><input type="checkbox" name="feat_tech[]" value="Apple CarPlay">
                            Apple CarPlay</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_tech[]" value="Android Auto">
                            Android Auto</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_tech[]"
                                value="Wireless Charging"> Wireless Charging</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_tech[]" value="HUD"> HUD</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_tech[]" value="Keyless"> Keyless
                            Entry</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_tech[]" value="Push Start"> Push
                            Start</label>
                    </div>

                    <label style="font-weight: 700; color: #111827; margin-top: 24px; display:block;">Comfort</label>
                    <div class="checkbox-group">
                        <label class="checkbox-item"><input type="checkbox" name="feat_comf[]" value="Sunroof">
                            Sunroof</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_comf[]" value="Heated Seats">
                            Heated Seats</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_comf[]"
                                value="Electric Tailgate"> Electric Tailgate</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_comf[]" value="Memory Seat">
                            Memory Seat</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_comf[]" value="Auto AC"> Auto
                            AC</label>
                        <label class="checkbox-item"><input type="checkbox" name="feat_comf[]" value="Ambient Lighting">
                            Ambient Lighting</label>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-header"><i class="fas fa-images"></i> Media & Description</h3>
                    <div class="grid-2" style="margin-bottom: 20px;">
                        <div class="form-group">
                            <label>Photos (Min 8)</label>
                            <input type="file" name="photos[]" class="form-control" multiple accept="image/*"
                                style="padding: 7px;">
                            <small style="color: #6b7280; display:block; margin-top:4px;">Front, rear, sides, interior,
                                dashboard, engine, boot, 45° view.</small>
                        </div>
                        <div class="form-group">
                            <label>Video (Max 2 min)</label>
                            <input type="file" name="video" class="form-control" accept="video/*" style="padding: 7px;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Selling Description</label>
                        <textarea name="description" class="form-control" rows="6"
                            placeholder="Write an engaging description for the customer..."></textarea>
                    </div>
                </div>
            </div>

            <?php if ($is_used_car): ?>
                <div id="tab-history" class="tab-content">
                    <div class="form-section" style="border-color: #fdba74; background: #fffbeb;">
                        <h3 class="section-header" style="color: #c2410c; border-bottom-color: #fed7aa;"><i
                                class="fas fa-file-contract"></i> Vehicle History</h3>
                        <div class="grid-3">
                            <div class="form-group"><label>Plate Number</label><input type="text" name="plate_no"
                                    class="form-control" value="<?= htmlspecialchars($car['car_plate']) ?>"></div>
                            <div class="form-group"><label>Location (City, State)</label><input type="text" name="location"
                                    class="form-control" placeholder="e.g., Kepong, KL"></div>
                            <div class="form-group"><label>Mileage (km)</label><input type="number" name="used_mileage"
                                    class="form-control"></div>
                            <div class="form-group"><label>Previous Owners</label><input type="number" name="owners"
                                    class="form-control"></div>
                            <div class="form-group"><label>Accident History</label>
                                <div class="radio-group"><label><input type="radio" name="accident" value="None" checked>
                                        None</label><label><input type="radio" name="accident" value="Minor">
                                        Minor</label><label><input type="radio" name="accident" value="Major"> Major</label>
                                </div>
                            </div>
                            <div class="form-group"><label>Flood/Fire History</label>
                                <div class="radio-group"><label><input type="radio" name="flood" value="Yes">
                                        Yes</label><label><input type="radio" name="flood" value="No" checked> No</label>
                                </div>
                            </div>
                            <div class="form-group"><label>Service History</label>
                                <div class="radio-group"><label><input type="radio" name="service_hist" value="Full">
                                        Full</label><label><input type="radio" name="service_hist" value="Partial">
                                        Partial</label><label><input type="radio" name="service_hist" value="None">
                                        None</label></div>
                            </div>
                            <div class="form-group"><label>Last Service Date</label><input type="date" name="last_service"
                                    class="form-control"></div>
                            <div class="form-group"><label>Next Service (km)</label><input type="number" name="next_service"
                                    class="form-control"></div>
                            <div class="form-group"><label>Road Tax Expiry</label><input type="date" name="roadtax"
                                    class="form-control"></div>
                            <div class="form-group"><label>Puspakom Date</label><input type="date" name="puspakom"
                                    class="form-control"></div>
                            <div class="form-group"><label>Remaining Warranty</label>
                                <div class="radio-group"><label><input type="radio" name="rem_warranty" value="Yes">
                                        Yes</label><label><input type="radio" name="rem_warranty" value="No" checked>
                                        No</label></div>
                            </div>
                        </div>
                        <div class="grid-2" style="margin-top: 16px;">
                            <div class="form-group">
                                <label>Inspection Report (PDF)</label>
                                <input type="file" name="inspection_pdf" class="form-control" accept=".pdf"
                                    style="padding: 7px; background: white;">
                            </div>
                            <div class="form-group">
                                <label>Known Issues/Defects</label>
                                <textarea name="defects" class="form-control" rows="2"
                                    placeholder="List any known scratches, dents, or mechanical faults..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-header"><i class="fas fa-clipboard-list"></i> Admin Condition Scoring (Internal)
                        </h3>
                        <div class="grid-4" style="align-items: end;">
                            <div class="form-group"><label>Exterior (1-5)</label><input type="number" id="score_ext"
                                    class="form-control score-input" min="1" max="5" value="5"></div>
                            <div class="form-group"><label>Interior (1-5)</label><input type="number" id="score_int"
                                    class="form-control score-input" min="1" max="5" value="5"></div>
                            <div class="form-group"><label>Mechanical (1-5)</label><input type="number" id="score_mech"
                                    class="form-control score-input" min="1" max="5" value="5"></div>
                            <div class="form-group"><label>Tyre (1-5)</label><input type="number" id="score_tyre"
                                    class="form-control score-input" min="1" max="5" value="5"></div>
                        </div>
                        <div class="grid-2" style="margin-top: 24px;">
                            <div class="form-group">
                                <label style="color: var(--primary-color); font-size: 16px;">Overall Score Average</label>
                                <input type="text" id="score_avg" class="form-control"
                                    style="font-size: 28px; font-weight: 700; color: var(--primary-color); border-color: var(--primary-color); background: #e0e7ff; height: 60px;"
                                    value="5.0" readonly>
                            </div>
                            <div class="form-group">
                                <label>Inspector Notes</label>
                                <textarea name="inspector_notes" class="form-control" rows="3"
                                    placeholder="Private notes for admin reference only..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </form>
    </main>

    <script>
        function openTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.inner-tab-btn').forEach(el => el.classList.remove('active'));

            document.getElementById(tabId).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        <?php if ($is_used_car): ?>
            document.querySelectorAll('.score-input').forEach(input => {
                input.addEventListener('input', function () {
                    let ext = parseFloat(document.getElementById('score_ext').value) || 0;
                    let int = parseFloat(document.getElementById('score_int').value) || 0;
                    let mech = parseFloat(document.getElementById('score_mech').value) || 0;
                    let tyre = parseFloat(document.getElementById('score_tyre').value) || 0;

                    let avg = (ext + int + mech + tyre) / 4;
                    document.getElementById('score_avg').value = avg.toFixed(1);
                });
            });
        <?php endif; ?>
    </script>
</body>

</html>