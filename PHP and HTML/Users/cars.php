<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'Includes/header.php';
?>

<style>
/* ==================== 1. 全局与布局 ==================== */
.inventory-page {
    background-color: #f4f6f8; 
    min-height: 100vh;
    padding: 40px 0;
    font-family: 'Segoe UI', Roboto, Helvetica, sans-serif;
}

.inventory-wrapper {
    max-width: 98%; 
    width: 100%;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    gap: 30px;
    align-items: flex-start;
}

/* ==================== 2. 左侧专业固定侧边栏 (Sticky Sidebar) ==================== */
.inventory-sidebar {
    width: 260px;
    flex-shrink: 0;
    background: #fff;
    border-radius: 12px;
    padding: 25px 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    
    /* 🔥 核心：固定在屏幕左侧，并在内部独立滚动 */
    position: sticky;
    top: 90px; 
    height: calc(100vh - 120px); 
    overflow-y: auto; 
}
.inventory-sidebar::-webkit-scrollbar { width: 4px; }
.inventory-sidebar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

/* ==================== 3. 恢复你原本好看的方形 Checkbox (Custom Tick) ==================== */
.filter-group { margin-bottom: 30px; }
.sidebar-title { font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px; }
.filter-options { display: flex; flex-direction: column; gap: 14px; }

.custom-tick { display: inline-flex; align-items: center; cursor: pointer; font-size: 14px; font-weight: 600; color: #475569; user-select: none; transition: 0.2s ease; }
.custom-tick:hover { color: #0f172a; }
.custom-tick input[type="radio"] { display: none; }

/* 🔥 这里恢复了方形 (border-radius: 6px) 和打钩的动画 */
.custom-tick .box { width: 20px; height: 20px; border: 2px solid #cbd5e1; border-radius: 6px; margin-right: 12px; transition: all 0.2s ease; background-color: #ffffff; }
.custom-tick input[type="radio"]:checked + .box {
    background-color: #0f172a; border-color: #0f172a;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'%3E%3C/polyline%3E%3C/svg%3E");
    background-size: 14px; background-position: center; background-repeat: no-repeat; box-shadow: 0 2px 6px rgba(15, 23, 42, 0.2);
}
.custom-tick input[type="radio"]:checked ~ span { color: #0f172a; font-weight: 700; }

/* ==================== 4. 右侧主内容与搜寻栏 ==================== */
.inventory-main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    min-height: 100vh; /* 防跳动 */
}

.top-action-bar {
    background: #fff; border-radius: 12px; padding: 20px 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03);
}

.search-sort-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 20px; }
.search-bar { flex: 1; display: flex; gap: 10px; }
.search-bar input { width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; }

.sort-dropdown { display: flex; align-items: center; gap: 10px; font-size: 14px; color: #475569; }
.sort-dropdown select { padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; cursor: pointer; }

/* 动态 Model Tabs */
.category-tabs { display: flex; flex-wrap: wrap; gap: 8px; border-top: 1px solid #f1f5f9; padding-top: 15px; align-items: center; }
.category-tab { padding: 8px 18px; background-color: transparent; border: 1px solid transparent; border-radius: 8px; font-size: 14px; font-weight: 600; color: #64748b; cursor: pointer; transition: all 0.25s ease; user-select: none; }
.category-tab:hover { background-color: #f1f5f9; color: #0f172a; transform: translateY(-2px); }
.category-tab.active { background-color: #0f172a; color: #ffffff; border-color: #0f172a; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15); }

/* ==================== 5. 车辆卡片 Grid ==================== */
#cars-container {
    display: flex;
    flex-direction: column;
    flex-grow: 1; /* 推到底部 */
}

.inventory-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px;
}

.pro-car-card {
    background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; transition: transform 0.2s, box-shadow 0.2s; text-decoration: none !important; display: flex; flex-direction: column; height: 100%;
}
.pro-car-card:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }

.card-img-container { position: relative; width: 100%; height: 160px; }
.card-img-container img { width: 100%; height: 100%; object-fit: cover; background: #f8fafc; display: block; }
.badge-condition { position: absolute; bottom: 10px; left: 10px; background: rgba(255, 255, 255, 0.95); color: #0f172a; font-size: 11px; font-weight: 800; padding: 4px 8px; border-radius: 4px; text-transform: uppercase; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.btn-wishlist { position: absolute; bottom: 10px; right: 10px; background: transparent; border: none; color: #ffffff; cursor: pointer; transition: all 0.2s; padding: 0; opacity: 0; display: flex; align-items: center; justify-content: center; filter: drop-shadow(0 2px 5px rgba(0,0,0,0.5)); }
.pro-car-card:hover .btn-wishlist { opacity: 1; }
.btn-wishlist:hover { transform: scale(1.15); }
.btn-wishlist.liked { opacity: 1; color: #ef4444; }
.btn-wishlist.liked svg { fill: none !important; stroke-width: 2.5; }

.card-content { padding: 12px 15px; display: flex; flex-direction: column; flex: 1; }
.card-title { font-size: 15px; font-weight: 800; color: #0f172a !important; margin: 0 0 4px 0; text-transform: uppercase; line-height: 1.3; }
.card-price { font-size: 17px; font-weight: 800; color: #dc2626 !important; margin: 0 0 10px 0; }
.card-specs { font-size: 13px; color: #334155 !important; font-weight: 600; display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
.spec-row { display: flex; align-items: center; gap: 6px; }
.spec-dot { color: #94a3b8; font-size: 12px; }
.card-footer { margin-top: auto; border-top: none; padding-top: 0; font-size: 13px; color: #334155 !important; display: flex; align-items: center; text-transform: capitalize; font-weight: 600; }

/* ==================== 6. 分页按钮 (Pagination UI) ==================== */
.pagination-container {
    display: flex; justify-content: center; gap: 8px; margin-top: auto; padding-top: 30px; padding-bottom: 10px;
}
.page-btn {
    width: 38px; height: 38px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #475569; font-weight: 700; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; font-size: 14px;
}
.page-btn:hover { background: #f1f5f9; color: #0f172a; border-color: #94a3b8; }
.page-btn.active { background: #0f172a; color: #fff; border-color: #0f172a; box-shadow: 0 4px 10px rgba(15,23,42,0.15); }

/* ==================== 7. Drawer & Mobile ==================== */
.side-drawer { position: fixed; top: 0; right: -100%; width: 100%; max-width: 520px; height: 100vh; background: #ffffff; box-shadow: -10px 0 40px rgba(15, 23, 42, 0.12); transition: right 0.4s cubic-bezier(0.22, 1, 0.36, 1); z-index: 9999; overflow: visible; border-left: 1px solid #e2e8f0; }
.side-drawer.open { right: 0; }
.drawer-content { padding: 28px; position: relative; height: 100vh; overflow-y: auto; box-sizing: border-box; background: linear-gradient(to bottom, #ffffff, #f8fafc); }
.drawer-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.35); backdrop-filter: blur(5px); z-index: 9998; opacity: 0; visibility: hidden; transition: 0.3s ease; }
.drawer-overlay.show { opacity: 1; visibility: visible; }
.toggle-drawer-edge-btn { position: absolute; top: 20px; left: 20px; width: 40px; height: 40px; border-radius: 50%; border: 1px solid #e2e8f0; background: #ffffff; color: #475569; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08); transition: all 0.25s; z-index: 10005; }
.toggle-drawer-edge-btn:hover { transform: scale(1.08); color: #0f172a; background: #f8fafc; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12); }
#car-details-sidebar[style*="max-width: 100%"] { max-width: 100% !important; }
#car-details-sidebar[style*="max-width: 100%"] .drawer-content { max-width: 1600px; margin: auto; }
@media (max-width: 1024px) {
    .inventory-wrapper { flex-direction: column; }
    .inventory-sidebar { width: 100%; position: static; margin-bottom: 20px; height: auto; }
}
</style>

<div class="inventory-page">
    <div class="inventory-wrapper">

        <aside class="inventory-sidebar">
            <div class="filter-group">
                <div class="sidebar-title" style="font-size: 16px; color: #0f172a; font-weight: 900;">🚗 PROTON FILTER</div>
            </div>

            <div class="filter-group">
                <div class="sidebar-title">BODY TYPE</div>
                <div class="filter-options">
                    <label class="custom-tick"><input type="radio" name="bodyTypeFilter" value="All" checked><div class="box"></div><span>All</span></label>
                    <label class="custom-tick"><input type="radio" name="bodyTypeFilter" value="Sedan"><div class="box"></div><span>Sedan</span></label>
                    <label class="custom-tick"><input type="radio" name="bodyTypeFilter" value="SUV"><div class="box"></div><span>SUV</span></label>
                    <label class="custom-tick"><input type="radio" name="bodyTypeFilter" value="Hatchback"><div class="box"></div><span>Hatchback</span></label>
                    <label class="custom-tick"><input type="radio" name="bodyTypeFilter" value="EV"><div class="box"></div><span>EV</span></label>
                </div>
            </div>

            <div class="filter-group">
                <div class="sidebar-title">CONDITION</div>
                <div class="filter-options">
                    <label class="custom-tick"><input type="radio" name="conditionFilter" value="All" checked><div class="box"></div><span>All</span></label>
                    <label class="custom-tick"><input type="radio" name="conditionFilter" value="New Car"><div class="box"></div><span>New Car</span></label>
                    <label class="custom-tick"><input type="radio" name="conditionFilter" value="Used Car"><div class="box"></div><span>Used Car</span></label>
                </div>
            </div>

            <div class="filter-group">
                <div class="sidebar-title">TRANSMISSION</div>
                <div class="filter-options">
                    <label class="custom-tick"><input type="radio" name="transmissionFilter" value="All" checked><div class="box"></div><span>All</span></label>
                    <label class="custom-tick"><input type="radio" name="transmissionFilter" value="Auto"><div class="box"></div><span>Auto</span></label>
                    <label class="custom-tick"><input type="radio" name="transmissionFilter" value="Manual"><div class="box"></div><span>Manual</span></label>
                    <label class="custom-tick"><input type="radio" name="transmissionFilter" value="CVT"><div class="box"></div><span>CVT</span></label>
                    <label class="custom-tick"><input type="radio" name="transmissionFilter" value="DCT"><div class="box"></div><span>DCT</span></label>
                </div>
            </div>

            <div class="filter-group">
                <div class="sidebar-title">YEAR</div>
                <div class="filter-options">
                    <label class="custom-tick"><input type="radio" name="yearFilter" value="All" checked><div class="box"></div><span>All</span></label>
                    <label class="custom-tick"><input type="radio" name="yearFilter" value="2023-2024"><div class="box"></div><span>2023 - 2024</span></label>
                    <label class="custom-tick"><input type="radio" name="yearFilter" value="2020-2022"><div class="box"></div><span>2020 - 2022</span></label>
                    <label class="custom-tick"><input type="radio" name="yearFilter" value="Before 2020"><div class="box"></div><span>Before 2020</span></label>
                </div>
            </div>

            <div class="filter-group">
                <div class="sidebar-title">PRICE (RM)</div>
                <div class="filter-options">
                    <label class="custom-tick"><input type="radio" name="priceFilter" value="All" checked><div class="box"></div><span>All</span></label>
                    <label class="custom-tick"><input type="radio" name="priceFilter" value="Under 50k"><div class="box"></div><span>Under RM 50,000</span></label>
                    <label class="custom-tick"><input type="radio" name="priceFilter" value="50k-100k"><div class="box"></div><span>RM 50,000 - RM 100,000</span></label>
                    <label class="custom-tick"><input type="radio" name="priceFilter" value="Above 100k"><div class="box"></div><span>Above RM 100,000</span></label>
                </div>
            </div>

            <button onclick="resetFilters()" style="width: 100%; padding: 12px; background: #f8fafc; color: #0f172a; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 800; cursor: pointer; transition: 0.2s;">
                RESET FILTER
            </button>
        </aside>

        <main class="inventory-main">
            <div class="top-action-bar">
                <div class="search-sort-row">
                    <div class="search-bar">
                        <input type="text" id="searchInput" placeholder="Search by Model or Year...">
                    </div>
                    <div class="sort-dropdown">
                        <span>Sort by:</span>
                        <select id="sortSelect">
                            <option value="Latest">Latest</option>
                            <option value="Price: Low to High">Price: Low to High</option>
                            <option value="Price: High to Low">Price: High to Low</option>
                        </select>
                    </div>
                </div>

                <div class="category-tabs" id="model-tabs">
                    <div class="category-tab active" data-filter="AllModels">ALL</div>
                </div>
            </div>

            <div id="cars-container">
                <div style="text-align:center; color:#64748b; padding: 100px 20px;">
                    Loading vehicles... 🚗💨
                </div>
            </div>
        </main>

    </div>
</div>

<div id="drawerOverlay" class="drawer-overlay" onclick="closeCarDetails()"></div>
<div id="car-details-sidebar" class="side-drawer">
    <button class="toggle-drawer-edge-btn" onclick="toggleMainSidebarSize()" title="Toggle Fullscreen">
        <svg id="expand-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>
        </svg>
    </button>
    <div class="drawer-content" id="drawer-content"></div>
</div>

<script>
    let currentModel = 'AllModels';
    let currentPage = 1; 
    let searchTimeout = null; 

    function fetchModelTabs() {
        let bodyType = document.querySelector('input[name="bodyTypeFilter"]:checked').value;
        
        fetch('fetch_models.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `bodyType=${encodeURIComponent(bodyType)}`
        })
        .then(response => response.json())
        .then(data => {
            const tabsContainer = document.getElementById('model-tabs');
            let availableModels = data.map(item => item.car_model);
            
            if (currentModel !== 'AllModels' && !availableModels.includes(currentModel)) {
                currentModel = 'AllModels';
            }

            let html = `<div class="category-tab ${currentModel === 'AllModels' ? 'active' : ''}" data-filter="AllModels">ALL</div>`;
            
            data.forEach(item => {
                let val = item.car_model;
                let displayTxt = val;
                if (item.car_brand && item.car_brand.toLowerCase().includes('e.mas')) {
                    displayTxt = 'e.MAS ' + val; 
                }
                let isActive = (currentModel === val) ? 'active' : '';
                html += `<div class="category-tab ${isActive}" data-filter="${val}">${displayTxt.toUpperCase()}</div>`;
            });

            tabsContainer.innerHTML = html;

            document.querySelectorAll('.category-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    currentModel = this.getAttribute('data-filter');
                    currentPage = 1; 
                    fetchFilteredCars();
                });
            });

            fetchFilteredCars();
        })
        .catch(error => { console.error('Error fetching models:', error); fetchFilteredCars(); });
    }

    function fetchFilteredCars() {
        const container = document.getElementById('cars-container');
        container.innerHTML = `<div style="text-align:center; color:#64748b; padding: 100px 20px;">Loading vehicles... 🚗💨</div>`;

        let bodyType = document.querySelector('input[name="bodyTypeFilter"]:checked').value;
        let condition = document.querySelector('input[name="conditionFilter"]:checked').value;
        let transmission = document.querySelector('input[name="transmissionFilter"]:checked').value;
        let year = document.querySelector('input[name="yearFilter"]:checked').value;
        let price = document.querySelector('input[name="priceFilter"]:checked').value;
        let sortValue = document.getElementById('sortSelect').value;
        let keyword = document.getElementById('searchInput').value.trim();

        let postData = `model=${encodeURIComponent(currentModel)}&bodyType=${encodeURIComponent(bodyType)}&condition=${encodeURIComponent(condition)}&transmission=${encodeURIComponent(transmission)}&year=${encodeURIComponent(year)}&price=${encodeURIComponent(price)}&sort=${encodeURIComponent(sortValue)}&keyword=${encodeURIComponent(keyword)}&page=${currentPage}`;

        fetch('fetch_cars.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: postData
        })
        .then(response => response.text())
        .then(html => { 
            // 如果后端返回了包含卡片的网格，把它包在 inventory-grid 里
            if (html.includes('pro-car-card')) {
                // 利用正则把后端吐出来的 pagination 剥离出来放到底部
                let gridHtml = '<div class="inventory-grid">' + html.split('<div class="pagination-container">')[0] + '</div>';
                let pageHtml = html.includes('<div class="pagination-container">') ? '<div class="pagination-container">' + html.split('<div class="pagination-container">')[1] : '';
                container.innerHTML = gridHtml + pageHtml;
            } else {
                // 显示 No vehicles found
                container.innerHTML = html;
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function changePage(page) {
        currentPage = page;
        fetchFilteredCars();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    document.addEventListener('DOMContentLoaded', function() {
        fetchModelTabs();

        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                currentPage = 1; 
                if (this.name === 'bodyTypeFilter') {
                    fetchModelTabs(); 
                } else {
                    fetchFilteredCars();
                }
            });
        });

        document.getElementById('sortSelect').addEventListener('change', () => {
            currentPage = 1;
            fetchFilteredCars();
        });
        
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentPage = 1;
                fetchFilteredCars();
            }, 300);
        });
    });

    function resetFilters() {
        currentModel = 'AllModels';
        currentPage = 1;
        document.querySelectorAll('input[type="radio"]').forEach(r => r.checked = false);
        document.querySelectorAll('input[value="All"]').forEach(r => r.checked = true);
        document.getElementById('searchInput').value = '';
        document.getElementById('sortSelect').value = 'Latest';
        fetchModelTabs(); 
    }

    function addToWishlist(event, btnElement, carId) {
        event.preventDefault(); event.stopPropagation();
        fetch('toggle_wishlist.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `car_id=${carId}` })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'added') btnElement.classList.add('liked');
            else if (data.status === 'removed') btnElement.classList.remove('liked');
            else if (data.status === 'not_logged_in') window.location.href = 'Auth/login.php';
            else alert(data.message);
        }).catch(err => console.error(err));
    }
    function openCarDetails(carId) {
        const drawer = document.getElementById('car-details-sidebar');
        const content = document.getElementById('drawer-content');
        const overlay = document.getElementById('drawerOverlay');
        drawer.style.maxWidth = '520px'; drawer.classList.add('open'); overlay.classList.add('show');
        content.innerHTML = `<div style="text-align: center; padding: 100px; color:#64748b;">Loading details... 🚗💨</div>`;
        fetch('fetch_car_details_sidebar.php?id=' + carId).then(response => response.text()).then(html => content.innerHTML = html).catch(err => console.error(err));
    }
    function closeCarDetails() { document.getElementById('car-details-sidebar').classList.remove('open'); document.getElementById('drawerOverlay').classList.remove('show'); }
    function toggleMainSidebarSize() {
        const sidebar = document.getElementById('car-details-sidebar'); const iconContainer = document.getElementById('expand-icon');
        if (sidebar.style.maxWidth === '100%') { sidebar.style.maxWidth = '520px'; if(iconContainer) iconContainer.innerHTML = '<path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>'; } 
        else { sidebar.style.maxWidth = '100%'; if(iconContainer) iconContainer.innerHTML = '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>'; }
    }
</script>

<?php include 'Includes/footer.php'; ?>