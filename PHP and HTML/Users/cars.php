<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'Includes/header.php'; 
?>

<div class="inventory-page">
    <div class="inventory-wrapper">
        
        <aside class="inventory-sidebar">
            <div class="filter-group">
                <div class="sidebar-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>
                    FILTER
                </div>
            </div>

            <div class="filter-group">
                <div class="sidebar-title">🏢 BRAND</div>
                <ul class="checkbox-list" id="brand-list">
                    <label class="checkbox-item"><input type="radio" name="brandFilter" value="AllBrands" checked> All Brands</label>
                    <label class="checkbox-item"><input type="radio" name="brandFilter" value="Proton"> Proton</label>
                    <label class="checkbox-item"><input type="radio" name="brandFilter" value="Perodua"> Perodua</label>
                    <label class="checkbox-item"><input type="radio" name="brandFilter" value="Toyota"> Toyota</label>
                    <label class="checkbox-item"><input type="radio" name="brandFilter" value="Honda"> Honda</label>
                    <label class="checkbox-item"><input type="radio" name="brandFilter" value="Mazda"> Mazda</label>
                    <label class="checkbox-item"><input type="radio" name="brandFilter" value="Nissan"> Nissan</label>
                </ul>
            </div>
            
            <button onclick="resetFilters()" style="width: 100%; padding: 12px; background: #0f172a; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; margin-top: 20px;">RESET FILTER</button>
        </aside>

        <main class="inventory-main">
            <div class="top-action-bar">
                <div class="search-sort-row">
                    <div class="search-bar">
                        <input type="text" id="searchInput" placeholder="Search brand, model or keyword...">
                        <button onclick="fetchFilteredCars()">Search</button>
                    </div>
                    <div class="sort-dropdown">
                        <span>Sort by:</span>
                        <select>
                            <option>Latest</option>
                            <option>Price: Low to High</option>
                            <option>Price: High to Low</option>
                        </select>
                    </div>
                </div>

                <div class="category-tabs" id="category-tabs">
                    <div class="category-tab active" data-filter="All">ALL</div>
                    <div class="category-tab" data-filter="Standard">STANDARD</div>
                    <div class="category-tab" data-filter="SUV">SUV</div>
                    <div class="category-tab" data-filter="MPV">MPV</div>
                    <div class="category-tab" data-filter="Hatchback">HATCHBACK</div>
                    
                </div>
            </div>

            <div class="inventory-grid" id="cars-container">
                <div style="text-align:center; color:#64748b; width: 100%; padding: 50px;">Loading vehicles...</div>
            </div> 

        </main>
    </div> 
</div>

<script>
    let currentCategory = 'All';

    function fetchFilteredCars() {
        const container = document.getElementById('cars-container');
        container.innerHTML = '<div style="text-align:center; color:#64748b; padding: 50px;">Loading...</div>';

        let selectedBrand = document.querySelector('input[name="brandFilter"]:checked').value;
        let searchQuery = document.getElementById('searchInput').value;

        fetch('fetch_cars.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `category=${encodeURIComponent(currentCategory)}&brand=${encodeURIComponent(selectedBrand)}&search=${encodeURIComponent(searchQuery)}`
        })
        .then(response => response.text())
        .then(html => container.innerHTML = html )
        .catch(error => console.error('Error:', error));
    }

    const categoryTabs = document.querySelectorAll('.category-tab');
    categoryTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            categoryTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentCategory = this.getAttribute('data-filter');
            fetchFilteredCars();
        });
    });

    const brandRadios = document.querySelectorAll('input[name="brandFilter"]');
    brandRadios.forEach(radio => {
        radio.addEventListener('change', fetchFilteredCars);
    });
    
    document.getElementById('searchInput').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') fetchFilteredCars();
    });

    function resetFilters() {
        currentCategory = 'All';
        categoryTabs.forEach(t => t.classList.remove('active'));
        categoryTabs[0].classList.add('active');
        
        document.querySelector('input[name="brandFilter"][value="AllBrands"]').checked = true;
        document.getElementById('searchInput').value = '';
        fetchFilteredCars();
    }

    // 🔥 真实后台存入功能 + 没登录自动跳转
    function addToWishlist(event, btnElement, carId) {
        event.preventDefault(); 
        
        fetch('toggle_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `car_id=${carId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'added') {
                btnElement.classList.add('liked'); // 变红心
            } 
            else if (data.status === 'removed') {
                btnElement.classList.remove('liked'); // 取消红心
            } 
            else if (data.status === 'not_logged_in') {
                // 收不到登录暗号，直接跳去 login.php
                window.location.href = 'Auth/login.php';
            }
            else if (data.status === 'error') {
                alert(data.message); 
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // 🔥 就是这里！网页骨架一加载完，立刻强制去抓取车子！
    document.addEventListener('DOMContentLoaded', function() {
        fetchFilteredCars();
    });
</script>

<?php include 'Includes/footer.php'; ?>