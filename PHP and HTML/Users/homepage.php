<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Dealer - Homepage</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../CSS/cus.css">
</head>
<body>

    <nav class="navbar">
        <div class="nav-container">
            <a href="homepage.html" class="nav-logo">🚗 CarDealer</a>
            
            <ul class="nav-links">
                <li><a href="homepage.html">Home</a></li>
                <li><a href="car-listing.html">Cars</a></li>
                <li><a href="category.html">Categories</a></li>
                <li><a href="contact.html">Contact</a></li>
            </ul>

            <div class="nav-action">
                <a href="login.html" class="btn-primary">Login / Register</a>
            </div>
        </div>
    </nav>
    <section class="hero-banner" style="background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?auto=format&fit=crop&w=1920&q=80') center/cover; padding: 100px 20px; text-align: center; color: white;">
        <h1 style="font-size: 48px; margin-bottom: 20px;">Find Your Dream Car</h1>
        <p style="font-size: 18px; margin-bottom: 30px;">The best platform to buy and sell premium cars.</p>
        <a href="car-listing.html" class="btn-primary" style="font-size: 18px; padding: 15px 30px;">Browse Cars</a>
    </section>

    <div class="container">
        <section class="search-section" style="background: var(--card-bg); padding: 20px; border-radius: 8px; box-shadow: var(--shadow); margin-top: -30px; position: relative;">
            <form action="search.html" style="display: flex; gap: 15px; flex-wrap: wrap;">
                <input type="text" placeholder="Search keyword (e.g. Civic)..." style="flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                <select style="flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                    <option value="">All Brands</option>
                    <option value="toyota">Toyota</option>
                    <option value="honda">Honda</option>
                    <option value="bmw">BMW</option>
                </select>
                <button type="button" class="btn-primary" onclick="window.location.href='search.html'">Search 🔍</button>
            </form>
        </section>

        <h2 class="section-title text-center">Featured Cars</h2>
        <div class="grid grid-3">
            <div class="card">
                <img src="https://images.unsplash.com/photo-1583121274602-3e2820c69888?auto=format&fit=crop&w=800&q=80" alt="Car">
                <div class="card-body">
                    <h3 class="card-title">2023 Honda Civic Sedan</h3>
                    <div class="card-price">$25,000</div>
                    <a href="car-details.html" class="btn-primary" style="display: block; text-align: center;">View Details</a>
                </div>
            </div>
            <div class="card">
                <img src="https://images.unsplash.com/photo-1555215695-3004980ad54e?auto=format&fit=crop&w=800&q=80" alt="Car">
                <div class="card-body">
                    <h3 class="card-title">2022 BMW M3</h3>
                    <div class="card-price">$72,000</div>
                    <a href="car-details.html" class="btn-primary" style="display: block; text-align: center;">View Details</a>
                </div>
            </div>
            <div class="card">
                <img src="https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?auto=format&fit=crop&w=800&q=80" alt="Car">
                <div class="card-body">
                    <h3 class="card-title">2024 Toyota RAV4 SUV</h3>
                    <div class="card-price">$32,500</div>
                    <a href="car-details.html" class="btn-primary" style="display: block; text-align: center;">View Details</a>
                </div>
            </div>
        </div>

        <h2 class="section-title text-center">Browse by Category</h2>
        <div class="grid grid-4 text-center">
            <a href="category.html" class="card" style="padding: 30px 10px; text-decoration: none; color: var(--text-dark);">
                <div style="font-size: 40px; margin-bottom: 10px;">🚘</div>
                <h3>Sedan</h3>
            </a>
            <a href="category.html" class="card" style="padding: 30px 10px; text-decoration: none; color: var(--text-dark);">
                <div style="font-size: 40px; margin-bottom: 10px;">🚙</div>
                <h3>SUV</h3>
            </a>
            <a href="category.html" class="card" style="padding: 30px 10px; text-decoration: none; color: var(--text-dark);">
                <div style="font-size: 40px; margin-bottom: 10px;">🚗</div>
                <h3>Hatchback</h3>
            </a>
            <a href="category.html" class="card" style="padding: 30px 10px; text-decoration: none; color: var(--text-dark);">
                <div style="font-size: 40px; margin-bottom: 10px;">🏎️</div>
                <h3>Luxury</h3>
            </a>
        </div>
    </div>

    <footer class="footer">
        <div class="container text-center">
            <p>&copy; 2024 Car Dealer. All Rights Reserved.</p>
        </div>
    </footer>

</body>
</html>