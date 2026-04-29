<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        r.reservation_id, r.reservation_date, r.reservation_created_at, r.reservation_status,
        c.car_brand, c.car_model, c.car_year, c.car_origin,
        ci.car_image_url,
        p.payment_id, p.payment_amount, p.payment_method, p.payment_status, p.payment_date
    FROM reservations r
    LEFT JOIN cars c       ON r.car_id = c.car_id
    LEFT JOIN car_image ci ON c.car_id = ci.car_id
    LEFT JOIN payments p   ON r.reservation_id = p.reservation_id
    WHERE r.user_id = ?
";
$params = [$user_id];
if ($filter === 'downpayment') $sql .= " AND r.reservation_status = 'Down Payment'";
elseif ($filter === 'reservation') $sql .= " AND r.reservation_status != 'Down Payment'";
$sql .= " GROUP BY r.reservation_id ORDER BY r.reservation_created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$search_result = null;
if (!empty($search)) {
    foreach ($transactions as $t) {
        $ref = 'TXN' . strtoupper(substr(md5($t['payment_id'].''), 0, 8));
        if (strtoupper($search) === $ref) { $search_result = $t; $search_result['ref'] = $ref; break; }
    }
}

function statusClass($s) {
    if (in_array($s, ['Paid','Confirmed'])) return 'status-success';
    if ($s === 'Pending') return 'status-pending';
    if ($s === 'Cancelled') return 'status-danger';
    return '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>View Status</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="styles.css"/>
    <style>
        .page-hero { background:linear-gradient(135deg,var(--primary-color),#0056b3); color:white; padding:40px 0 30px; margin-bottom:30px; }
        .page-hero h1 { font-size:32px; font-weight:700; margin-bottom:6px; }
        .page-hero p  { opacity:0.85; font-size:15px; }

        .section-card { background:var(--card-bg); border-radius:8px; box-shadow:var(--shadow); padding:28px; margin-bottom:24px; }
        .section-card h2 { font-size:18px; margin-bottom:18px; color:var(--primary-color); border-bottom:2px solid #f0f0f0; padding-bottom:10px; }

        .search-row { display:flex; gap:10px; }
        .search-row input { flex:1; }
        .search-row button { white-space:nowrap; }

        .filter-tabs { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
        .filter-tab { padding:8px 20px; border-radius:20px; text-decoration:none; font-size:13px; font-weight:500; border:2px solid #e0e0e0; color:var(--text-light); transition:var(--transition); }
        .filter-tab.active, .filter-tab:hover { border-color:var(--primary-color); color:var(--primary-color); background:#f0f7ff; }

        .status-table { width:100%; border-collapse:collapse; font-size:14px; }
        .status-table th { padding:12px 14px; text-align:left; background:#f8f9fa; color:var(--text-light); font-weight:600; border-bottom:2px solid #e0e0e0; font-size:13px; }
        .status-table td { padding:14px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
        .status-table tr:hover td { background:#fafafa; }

        .status-badge { display:inline-block; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600; letter-spacing:0.5px; }
        .status-success { background:#d4edda; color:#155724; }
        .status-pending { background:#fff3cd; color:#856404; }
        .status-danger  { background:#f8d7da; color:#721c24; }

        .car-cell { display:flex; align-items:center; gap:10px; }
        .car-cell img { width:60px; height:44px; object-fit:cover; border-radius:4px; flex-shrink:0; }
        .car-cell-info { font-size:13px; }
        .car-cell-info strong { display:block; }
        .car-cell-info span { color:var(--text-light); font-size:12px; }

        .empty-state { text-align:center; padding:48px 20px; color:var(--text-light); }
        .empty-state .empty-icon { font-size:48px; margin-bottom:12px; }

        .search-result-card { background:#f0f7ff; border:1px solid #c8e0ff; border-radius:8px; padding:20px; margin-top:16px; }
        .search-result-card table { width:100%; border-collapse:collapse; }
        .search-result-card td { padding:8px 12px; font-size:14px; border-bottom:1px solid #d8ecff; }
        .search-result-card td:first-child { color:var(--text-light); font-weight:500; width:180px; }

        .stats-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px; }
        .stat-card { background:var(--card-bg); border-radius:8px; box-shadow:var(--shadow); padding:20px; text-align:center; }
        .stat-num { font-size:28px; font-weight:700; color:var(--primary-color); }
        .stat-label { font-size:13px; color:var(--text-light); margin-top:4px; }

        @media (max-width:768px) { .status-table { display:block; overflow-x:auto; } .stats-grid { grid-template-columns:1fr; } }
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
        <h1>My Transaction Status</h1>
        <p>Track your down payment applications and car reservations.</p>
    </div>
</div>

<div class="container">

    <!-- Stats -->
    <?php
        $total_count = count($transactions);
        $res_count   = count(array_filter($transactions, fn($t) => $t['reservation_status'] !== 'Down Payment'));
        $dp_count    = count(array_filter($transactions, fn($t) => $t['reservation_status'] === 'Down Payment'));
    ?>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-num"><?php echo $total_count; ?></div>
            <div class="stat-label">Total Transactions</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?php echo $res_count; ?></div>
            <div class="stat-label">Reservations</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?php echo $dp_count; ?></div>
            <div class="stat-label">Down Payments</div>
        </div>
    </div>

    <!-- Search -->
    <div class="section-card">
        <h2>Search by Reference Number</h2>
        <form method="GET" action="view_status.php">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>"/>
            <div class="search-row">
                <input type="text" name="search" class="form-control"
                       placeholder="e.g. TXN1A2B3C4D"
                       value="<?php echo htmlspecialchars($search); ?>"/>
                <button type="submit" class="btn-primary">Search</button>
            </div>
        </form>

        <?php if (!empty($search)): ?>
        <?php if ($search_result): ?>
        <div class="search-result-card">
            <table>
                <tr><td>Reference</td><td><strong><?php echo htmlspecialchars($search_result['ref']); ?></strong></td></tr>
                <tr><td>Car</td><td><?php echo htmlspecialchars($search_result['car_brand'].' '.$search_result['car_model']); ?></td></tr>
                <tr><td>Date</td><td><?php echo htmlspecialchars($search_result['payment_date'] ?? $search_result['reservation_created_at']); ?></td></tr>
                <tr><td>Type</td><td><?php echo $search_result['reservation_status']==='Down Payment'?'Down Payment':'Reservation'; ?></td></tr>
                <tr><td>Amount</td><td>RM <?php echo number_format((float)($search_result['payment_amount']??0),2); ?></td></tr>
                <tr><td>Method</td><td><?php echo htmlspecialchars($search_result['payment_method']??'-'); ?></td></tr>
                <tr><td>Status</td>
                    <td><span class="status-badge <?php echo statusClass($search_result['payment_status']??''); ?>">
                        <?php echo strtoupper($search_result['payment_status']??'N/A'); ?>
                    </span></td>
                </tr>
            </table>
        </div>
        <?php else: ?>
        <p style="margin-top:12px; color:var(--text-light); font-size:14px;">
            No transaction found with reference <strong><?php echo htmlspecialchars($search); ?></strong>.
        </p>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- All Transactions -->
    <div class="section-card">
        <h2>My Transactions</h2>

        <div class="filter-tabs">
            <a href="?filter=all"         class="filter-tab <?php echo $filter==='all'        ?'active':''; ?>">All (<?php echo $total_count; ?>)</a>
            <a href="?filter=reservation" class="filter-tab <?php echo $filter==='reservation'?'active':''; ?>">Reservations (<?php echo $res_count; ?>)</a>
            <a href="?filter=downpayment" class="filter-tab <?php echo $filter==='downpayment'?'active':''; ?>">Down Payments (<?php echo $dp_count; ?>)</a>
        </div>

        <?php if (empty($transactions)): ?>
        <div class="empty-state">
            <div class="empty-icon">&#128663;</div>
            <p>No transactions found.</p>
            <a href="index.php" class="btn-primary" style="display:inline-block; margin-top:16px; text-decoration:none;">Browse Cars</a>
        </div>
        <?php else: ?>
        <table class="status-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Car</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Visit Date</th>
                    <th>Payment</th>
                    <th>Reservation</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $t):
                    $ref  = 'TXN'.strtoupper(substr(md5($t['payment_id'].''),0,8));
                    $type = $t['reservation_status']==='Down Payment' ? 'Down Payment' : 'Reservation';
                ?>
                <tr>
                    <td><strong style="font-family:monospace;"><?php echo htmlspecialchars($ref); ?></strong></td>
                    <td>
                        <div class="car-cell">
                            <?php if ($t['car_image_url']): ?>
                            <img src="<?php echo htmlspecialchars($t['car_image_url']); ?>" alt="Car"/>
                            <?php endif; ?>
                            <div class="car-cell-info">
                                <strong><?php echo htmlspecialchars($t['car_brand'].' '.$t['car_model']); ?></strong>
                                <span><?php echo htmlspecialchars($t['car_year'].' · '.$t['car_origin']); ?></span>
                            </div>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($type); ?></td>
                    <td><strong>RM <?php echo number_format((float)($t['payment_amount']??0),2); ?></strong></td>
                    <td><?php echo htmlspecialchars($t['payment_method']??'-'); ?></td>
                    <td><?php echo htmlspecialchars($t['reservation_date']??'-'); ?></td>
                    <td><span class="status-badge <?php echo statusClass($t['payment_status']??''); ?>"><?php echo strtoupper($t['payment_status']??'N/A'); ?></span></td>
                    <td><span class="status-badge <?php echo statusClass($t['reservation_status']??''); ?>"><?php echo strtoupper($t['reservation_status']??'N/A'); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<footer class="footer text-center">
    <p>&copy; 2025 AutoDeal. All rights reserved.</p>
</footer>

</body>
</html>
