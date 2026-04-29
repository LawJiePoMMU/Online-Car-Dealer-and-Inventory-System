<?php
session_start();
// 引入你刚刚做好的 Header
include 'Includes/header.php'; 
?>

<section class="hero-section">
    <div class="hero-content">
        <h1>Find Your Dream Second-Hand Car</h1>
        <p>Quality inspected vehicles, trusted dealers, and the best prices in Malaysia.</p>
        <a href="inventory.php" class="btn-primary hero-btn">Browse Inventory</a>
    </div>
</section>

<div class="container search-bar-container">
    <form action="inventory.php" method="GET" class="quick-search-form">
        <input type="text" name="brand" placeholder="Search brand (e.g. Honda, Toyota)..." class="form-control">
        <select name="price_range" class="form-control">
            <option value="">Any Price</option>
            <option value="under_50k">Under RM 50,000</option>
            <option value="50k_to_100k">RM 50,000 - RM 100,000</option>
            <option value="above_100k">Above RM 100,000</option>
        </select>
        <button type="submit" class="btn-primary">Search</button>
    </form>
</div>

<div class="container" style="margin-top: 60px;">
    <h2 class="section-title text-center">Featured Vehicles</h2>
    
    <div class="grid grid-3">
        
        <div class="card">
            <img src="https://images.unsplash.com/photo-1552519507-da3b142c6e3d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Honda Civic">
            <div class="card-body">
                <h3 class="card-title">2020 Honda Civic 1.5 TC-P</h3>
                <div class="card-price">RM 105,000</div>
                <p style="color: var(--text-light); margin-bottom: 15px; font-size: 14px;">Mileage: 45,000 km | Auto | Petrol</p>
                <a href="details.php?id=1" class="btn-primary" style="width: 100%; text-align: center;">View Details</a>
            </div>
        </div>

        <div class="card">
            <img src="https://images.unsplash.com/photo-1590362891991-f776e747a588?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Toyota Vios">
            <div class="card-body">
                <h3 class="card-title">2018 Toyota Vios 1.5 G</h3>
                <div class="card-price">RM 62,000</div>
                <p style="color: var(--text-light); margin-bottom: 15px; font-size: 14px;">Mileage: 68,000 km | Auto | Petrol</p>
                <a href="details.php?id=2" class="btn-primary" style="width: 100%; text-align: center;">View Details</a>
            </div>
        </div>

        <div class="card">
            <img src="https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="BMW 3 Series">
            <div class="card-body">
                <h3 class="card-title">2017 BMW 330e M Sport</h3>
                <div class="card-price">RM 128,000</div>
                <p style="color: var(--text-light); margin-bottom: 15px; font-size: 14px;">Mileage: 52,000 km | Auto | Hybrid</p>
                <a href="details.php?id=3" class="btn-primary" style="width: 100%; text-align: center;">View Details</a>
            </div>
        </div>

    </div>
</div>

<div class="features-section">
    <div class="container grid grid-3">
        <div class="feature-box">
            <div class="feature-icon">🛡️</div>
            <h3>175-Point Inspection</h3>
            <p style="color: var(--text-light); font-size: 14px;">Every car passes a strict quality check before hitting our lot.</p>
        </div>
        <div class="feature-box">
            <div class="feature-icon">💰</div>
            <h3>Fixed Transparent Pricing</h3>
            <p style="color: var(--text-light); font-size: 14px;">No hidden fees. The price you see is the price you pay.</p>
        </div>
        <div class="feature-box">
            <div class="feature-icon">🚗</div>
            <h3>5-Day Money Back</h3>
            <p style="color: var(--text-light); font-size: 14px;">Change your mind? Return it within 5 days for a full refund.</p>
        </div>
    </div>
</div>

<footer class="footer text-center">
    <p>&copy; <?php echo date("Y"); ?> CarDealer. All rights reserved.</p>
</footer>

</body>
</html>