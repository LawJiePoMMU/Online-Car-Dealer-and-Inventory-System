<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-grid">
            <div class="footer-col footer-about">
                <img src="/Online-Car-Dealer-and-Inventory-System/PHP%20and%20HTML/Users/Includes/Logo.png" alt="LCWcar"
                    class="footer-logo">
                <h3 class="footer-title">LCWcar Sdn Bhd</h3>
                <p class="footer-desc">
                    Your trusted destination for quality cars. We specialize in
                    new and used vehicles, easy booking, and after-sales service
                    you can rely on.
                </p>
                <p class="footer-tagline"><strong>Drive your dream car today.</strong></p>
            </div>

            <div class="footer-col">
                <h3 class="footer-title">Shop Categories</h3>
                <ul class="footer-links">
                    <li><a href="/Online-Car-Dealer-and-Inventory-System/PHP%20AND%20HTML/Users/index.php">Home</a></li>
                    <li><a href="/Online-Car-Dealer-and-Inventory-System/PHP%20AND%20HTML/Users/cars.php">Cars</a></li>
                    <li><a
                            href="/Online-Car-Dealer-and-Inventory-System/PHP%20AND%20HTML/Users/view_status.php">Status</a>
                    </li>
                    <li><a href="/Online-Car-Dealer-and-Inventory-System/PHP%20AND%20HTML/Users/chat.php">Chat</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h3 class="footer-title">Contact Us</h3>
                <ul class="footer-contact">
                    <li>
                        <i class="fas fa-map-marker-alt"></i>
                        <span>168, Jalan Bukit Bintang, Bukit Bintang, 55100 Kuala Lumpur, Wilayah Persekutuan Kuala
                            Lumpur</span>
                    </li>
                    <li>
                        <i class="fas fa-phone"></i>
                        <span>+60 12-3456789</span>
                    </li>
                    <li>
                        <i class="fas fa-envelope"></i>
                        <span>lcwcar.support@gmail.com</span>
                    </li>
                </ul>

                <div class="footer-registration">
                    <p>SSM: 202401123456</p>
                    <p>SST: M10-2405-12345678</p>
                </div>
            </div>

        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> LCWcar Sdn Bhd. All Rights Reserved.</p>
        </div>
    </div>
</footer>

<style>
    .site-footer {
        background-color: #040720;
        color: #ffffff;
        padding: 60px 20px 20px;
        font-family: 'Segoe UI', Tahoke, sans-serif;
        margin-top: 60px;
    }

    .footer-container {
        max-width: 1200px;
        margin: 0 auto;
    }

    .footer-grid {
        display: grid;
        grid-template-columns: 1.5fr 1fr 1.5fr;
        gap: 40px;
        margin-bottom: 40px;
    }

    .footer-title {
        color: #ffffff !important;
        opacity: 1 !important;
        filter: none !important;
        visibility: visible !important;
        text-shadow: none !important;
    }

    .footer-logo {
        max-width: 160px;
        margin-bottom: 12px;
    }

    .footer-desc {
        color: #d6d6d6;
        font-size: 14px;
        line-height: 1.7;
        margin-bottom: 10px;
    }

    .footer-tagline {
        color: #ffffff;
        font-size: 14px;
        margin-bottom: 18px;
    }

    .footer-social {
        display: flex;
        gap: 14px;
    }

    .footer-social a {
        color: #ffffff;
        font-size: 18px;
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background 0.25s;
        text-decoration: none;
    }

    .footer-social a:hover {
        background: #1ea7e1;
    }

    .footer-links {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .footer-links li {
        margin-bottom: 10px;
    }

    .footer-links a {
        color: #ffffff;
        text-decoration: none;
        font-size: 15px;
        transition: color 0.2s;
    }

    .footer-links a:hover {
        color: #1ea7e1;
    }

    .footer-contact {
        list-style: none;
        padding: 0;
        margin: 0 0 18px 0;
    }

    .footer-contact li {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        color: #d6d6d6;
        font-size: 14px;
        margin-bottom: 12px;
        line-height: 1.6;
    }

    .footer-contact li i {
        color: #1ea7e1;
        margin-top: 4px;
        min-width: 14px;
    }

    .footer-registration {
        background: rgba(255, 255, 255, 0.05);
        padding: 10px 14px;
        border-radius: 6px;
        margin-bottom: 18px;
    }

    .footer-registration p {
        color: #b5b5b5;
        font-size: 12px;
        margin: 2px 0;
    }

    .footer-payment-title {
        color: #b5b5b5;
        font-size: 12px;
        letter-spacing: 1px;
        margin-bottom: 8px;
    }

    .payment-icons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .pay-badge {
        background: #ffffff;
        color: #333;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
    }

    .footer-bottom {
        border-top: 1px solid #2a2a2a;
        padding-top: 20px;
        text-align: center;
        color: #b5b5b5;
        font-size: 13px;
    }

    .footer-bottom p {
        margin: 4px 0;
    }

    .footer-bottom-links a {
        color: #b5b5b5;
        text-decoration: none;
        margin: 0 4px;
    }

    .footer-bottom-links a:hover {
        color: #1ea7e1;
    }

    @media (max-width: 900px) {
        .footer-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 600px) {
        .footer-grid {
            grid-template-columns: 1fr;
            gap: 30px;
        }

        .site-footer {
            padding: 40px 16px 16px;
        }
    }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">