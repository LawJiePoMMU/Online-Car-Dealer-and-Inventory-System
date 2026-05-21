<?php
// 确保 session 已经启动 (这样才能判断用户有没有登录)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Dealer</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="/Online-Car-Dealer-and-Inventory-System/CSS/cus.css?v=<?php echo time(); ?>">

<body>

    <header class="navbar">
        <div class="nav-container">

            <a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/index.php" class="nav-logo">🚗
                CarDealer</a>

<<<<<<< HEAD
            <ul class="nav-links">
                <li><a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/index.php">Home</a></li>
                <li><a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/cars.php">Cars</a></li>
                <li><a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/booking.php">Booking</a>
                </li>
                <li><a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/chat.php">Chat</a></li>
            </ul>

            <div class="nav-actions">
                <?php if (
                    isset($_SESSION["loggedin"])
                    && $_SESSION["loggedin"] === true
                    && isset($_SESSION["user_role"])
                    && $_SESSION["user_role"] === "Customer"
                ): ?>

                    <div class="user-menu">
                        <a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/wishlist.php"
                            class="icon-link" title="My Wishlist">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path
                                    d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z">
                                </path>
                            </svg>
                        </a>

                        <a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/profile.php"
                            class="icon-link" title="My Profile">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </a>
                    </div>

                <?php else: ?>

                    <a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/Auth/login.php"
                        class="btn-primary">Login / Register</a>

                <?php endif; ?>
            </div>
=======
        <div class="nav-actions">
           <?php if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && isset($_SESSION["role"]) && strcasecmp($_SESSION["role"], "Customer") === 0): ?>
                
                <div class="user-menu">
                    <a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/wishlist.php" class="icon-link" title="My Wishlist">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                    </a>
                    
                    <a href="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/profile.php" class="icon-link" title="My Profile">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </a>
                </div>
>>>>>>> 8ea3e9dfe44d679e3eb2cc6df8bd3605e082b0aa

        </div>
    </header>