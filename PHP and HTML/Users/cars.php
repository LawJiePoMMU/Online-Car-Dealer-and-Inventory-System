<?php
session_start();
include 'Includes/header.php'; 
?>

<div class="inventory-page">
    <div class="inventory-wrapper">
        
        <div class="inventory-header">
            <div class="category-tabs" id="category-tabs">
                <!-- 🔥 更新了 data-filter，与数据库 car_types 表中的名称完全一致 -->
                <div class="category-tab active" data-filter="All">All</div>
                <div class="category-tab" data-filter="Standard">Standard</div>
                <div class="category-tab" data-filter="SUV">SUV</div>
                <div class="category-tab" data-filter="Hatchback">Hatchback</div>
                <div class="category-tab" data-filter="EV">EV</div>
                <div class="category-tab" data-filter="Exora (MPV)">Exora (MPV)</div>
            </div>

            <div class="filter-btn">
                <span>Filter</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="4" y1="21" x2="4" y2="14"></line>
                    <line x1="4" y1="10" x2="4" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12" y2="3"></line>
                    <line x1="20" y1="21" x2="20" y2="16"></line>
                    <line x1="20" y1="12" x2="20" y2="3"></line>
                    <line x1="1" y1="14" x2="7" y2="14"></line>
                    <line x1="9" y1="8" x2="15" y2="8"></line>
                    <line x1="17" y1="16" x2="23" y2="16"></line>
                </svg>
            </div>
        </div>

        <div class="inventory-body">
            
            <aside class="inventory-sidebar">
                <ul class="brand-list" id="brand-list">
                    <li class="brand-item active" data-filter="AllBrands">All Brands</li>
                    <li class="brand-item" data-filter="Proton">Proton</li>
                    <li class="brand-item" data-filter="Perodua">Perodua</li>
                    <li class="brand-item" data-filter="Toyota">Toyota</li>
                    <li class="brand-item" data-filter="Honda">Honda</li>
                    <li class="brand-item" data-filter="Mazda">Mazda</li>
                    <li class="brand-item" data-filter="Nissan">Nissan</li>
                </ul>
            </aside>

            <div class="inventory-grid" id="cars-container">
                <div style="grid-column: 1/-1; text-align:center; color:#64748b;">Loading vehicles...</div>
            </div> 

        </div> 
    </div> 
</div>

<script>
    // 默认选中的条件
    let currentCategory = 'All';
    let currentBrand = 'AllBrands';

    // 1. 去后台抓取车子的函数
    function fetchFilteredCars() {
        const container = document.getElementById('cars-container');
        container.innerHTML = '<div style="grid-column: 1/-1; text-align:center; color:#64748b;">Loading...</div>';

        // 使用 Fetch API 偷偷向 fetch_cars.php 发送数据
        fetch('fetch_cars.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `category=${encodeURIComponent(currentCategory)}&brand=${encodeURIComponent(currentBrand)}`
        })
        .then(response => response.text())
        .then(html => {
            // 把后台传回来的车子 HTML 塞进网格里！
            container.innerHTML = html;
        })
        .catch(error => {
            container.innerHTML = '<div style="grid-column: 1/-1; text-align:center; color:red;">Error loading cars.</div>';
            console.error('Error:', error);
        });
    }

    // 2. 监听顶部 Categories 的点击
    const categoryTabs = document.querySelectorAll('.category-tab');
    categoryTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // 移除所有人的 active，只给自己加 active
            categoryTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // 更新当前条件，并重新抓取
            currentCategory = this.getAttribute('data-filter');
            fetchFilteredCars();
        });
    });

    // 3. 监听左侧 Brands 的点击
    const brandItems = document.querySelectorAll('.brand-item');
    brandItems.forEach(item => {
        item.addEventListener('click', function() {
            brandItems.forEach(i => i.classList.remove('active'));
            this.classList.add('active');
            
            currentBrand = this.getAttribute('data-filter');
            fetchFilteredCars();
        });
    });

    // 4. 网页一打开，先自动执行一次加载全部车子
    window.onload = () => {
        fetchFilteredCars();
    };
</script>

<?php include 'Includes/footer.php'; ?>