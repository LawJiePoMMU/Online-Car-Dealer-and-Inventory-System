<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'Includes/header.php';
?>

<style>

/* ==================== SIDEBAR ==================== */

.side-drawer {
    position: fixed;
    top: 0;
    right: -100%;
    width: 100%;
    max-width: 520px;
    height: 100vh;
    background: #ffffff;
    box-shadow: -10px 0 40px rgba(15, 23, 42, 0.12);
    transition: right 0.4s cubic-bezier(0.22, 1, 0.36, 1);
    z-index: 9999;
    overflow: visible;
    border-left: 1px solid #e2e8f0;
}

.side-drawer.open {
    right: 0;
}

/* ==================== CONTENT ==================== */

.drawer-content {
    padding: 28px;
    position: relative;
    height: 100vh;
    overflow-y: auto;
    box-sizing: border-box;
    background: linear-gradient(to bottom, #ffffff, #f8fafc);
}

/* ==================== OVERLAY ==================== */

.drawer-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.35);
    backdrop-filter: blur(5px);
    z-index: 9998;
    opacity: 0;
    visibility: hidden;
    transition: 0.3s ease;
}

.drawer-overlay.show {
    opacity: 1;
    visibility: visible;
}

/* ==================== TOGGLE BTN ==================== */

.toggle-drawer-edge-btn {
    position: absolute;
    top: 20px;
    left: 20px;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 10005;
}

.toggle-drawer-edge-btn:hover {
    transform: scale(1.08);
    color: #0f172a;
    background: #f8fafc;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
}

/* ==================== GALLERY ==================== */

.gallery-main img {
    width: 100%;
    height: 300px;
    object-fit: cover;
    border-radius: 24px;
    border: 1px solid #e2e8f0;
    background: #f1f5f9;
}

.gallery-main {
    margin-top: 50px !important;
}

.gallery-thumbs {
    display: flex;
    gap: 10px;
    margin-top: 14px;
    overflow-x: auto;
    padding-bottom: 6px;
}

.gallery-thumbs img {
    width: 85px;
    height: 65px;
    object-fit: cover;
    border-radius: 14px;
    cursor: pointer;
    border: 2px solid transparent;
    transition: 0.25s ease;
}

.gallery-thumbs img:hover {
    border-color: #0f172a;
    transform: translateY(-2px);
}

/* ==================== HEADINGS ==================== */

h2 {
    font-size: 32px !important;
    font-weight: 900 !important;
    line-height: 1.1;
    color: #0f172a !important;
}

h3 {
    color: #0f172a !important;
    font-size: 18px !important;
    font-weight: 800 !important;
    margin-bottom: 18px !important;
}

/* ==================== GRID ==================== */

.car-details-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
    margin-top: 18px;
}

.grid-item {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 16px;
    transition: 0.25s ease;
    box-shadow: 0 2px 8px rgba(15,23,42,0.04);
}

.grid-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(15,23,42,0.08);
}

.grid-item span {
    color: #64748b;
    display: block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.grid-item strong {
    color: #0f172a;
    font-size: 15px;
    font-weight: 700;
}

/* ==================== SECTION SPACING ==================== */

hr {
    border: 0 !important;
    border-top: 1px solid #e2e8f0 !important;
    margin: 30px 0 !important;
}

/* ==================== BUTTONS ==================== */

button {
    transition: 0.25s ease;
}

button:hover {
    transform: translateY(-2px);
}

/* ==================== SCROLLBAR ==================== */

.drawer-content::-webkit-scrollbar {
    width: 7px;
}

.drawer-content::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 20px;
}

/* ================= MODEL TABS (更新优化版) ================= */

.category-tabs {
    display: flex;
    flex-wrap: wrap; 
    gap: 8px;       
    margin-top: 15px;
    margin-bottom: 25px;
    align-items: center;
}

.category-tab {
    padding: 8px 18px;
    background-color: transparent; /* 去掉默认多余底色 */
    border: 1px solid transparent;   /* 去掉默认的框框 */
    border-radius: 8px; 
    font-size: 14px;
    font-weight: 600;
    color: #64748b; /* 未选中时使用温和的灰蓝色 */
    cursor: pointer;
    transition: all 0.25s ease;
    user-select: none;
}

.category-tab:hover {
    background-color: #f1f5f9; /* 悬停时淡淡的灰色反馈 */
    color: #0f172a;
    transform: translateY(-2px); 
}

.category-tab.active {
    background-color: #0f172a; 
    color: #ffffff;
    border-color: #0f172a;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15); 
}

/* ================= SIDEBAR BODY TYPE FILTER (更新优化版) ================= */

.checkbox-list {
    display: grid;
    grid-template-columns: 1fr 1fr; 
    gap: 10px;
    padding: 0;
    margin: 0;
}

.checkbox-item {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px 5px;
    background-color: #ffffff; /* 干净纯白底 */
    border: 1px solid #cbd5e1; /* 柔和的边框线 */
    border-radius: 30px; 
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all 0.25s ease;
    user-select: none;
    text-align: center;
}

/* 完美排版平衡：让第一个 "All" 按钮横跨两个格子 */
.checkbox-item:first-child {
    grid-column: span 2;
}

.checkbox-item input[type="radio"] {
    display: none;
}

.checkbox-item:hover {
    background-color: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
    transform: translateY(-2px);
}

.checkbox-item:has(input[type="radio"]:checked) {
    background-color: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
}

.checkbox-item input[type="radio"]:checked + span {
    color: white;
}

/* ==================== FULLSCREEN MODE ==================== */

#car-details-sidebar[style*="max-width: 100%"] {
    max-width: 100% !important;
}

#car-details-sidebar[style*="max-width: 100%"] .drawer-content {
    max-width: 1600px;
    margin: auto;
}

#car-details-sidebar[style*="max-width: 100%"] .car-details-grid {
    grid-template-columns: repeat(4, 1fr);
}

/* ==================== MOBILE ==================== */

@media (max-width: 768px) {

    .side-drawer {
        max-width: 100%;
    }

    .car-details-grid {
        grid-template-columns: 1fr;
    }

    h2 {
        font-size: 26px !important;
    }

}

</style>

<div class="inventory-page">

    <div class="inventory-wrapper">

        <aside class="inventory-sidebar">

            <div class="filter-group">
                <div class="sidebar-title">
                    🚗 PROTON FILTER
                </div>
            </div>

            <div class="filter-group">

                <div class="sidebar-title">
                    BODY TYPE
                </div>

                <div class="checkbox-list">

                    <label class="checkbox-item">
                        <input type="radio" name="bodyTypeFilter" value="All" checked>
                        <span>All</span>
                    </label>

                    <label class="checkbox-item">
                        <input type="radio" name="bodyTypeFilter" value="Sedan">
                        <span>Sedan</span>
                    </label>

                    <label class="checkbox-item">
                        <input type="radio" name="bodyTypeFilter" value="SUV">
                        <span>SUV</span>
                    </label>

                    <label class="checkbox-item">
                        <input type="radio" name="bodyTypeFilter" value="Hatchback">
                        <span>Hatchback</span>
                    </label>

                    <label class="checkbox-item">
                        <input type="radio" name="bodyTypeFilter" value="EV">
                        <span>EV</span>
                    </label>

                </div>

            </div>

            <button onclick="resetFilters()" style="width: 100%; padding: 12px; background: #0f172a; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 25px;">
                RESET FILTER
            </button>

        </aside>

        <main class="inventory-main">

            <div class="top-action-bar">

                <div class="search-sort-row">

                    <div class="search-bar">

                        <input type="text" id="searchInput" placeholder="Search Proton model...">

                        <button onclick="fetchFilteredCars()">
                            Search
                        </button>

                    </div>

                    <div class="sort-dropdown">

                        <span>Sort by:</span>

                        <select id="sortSelect" onchange="fetchFilteredCars()">

                            <option value="Latest">
                                Latest
                            </option>

                            <option value="Price: Low to High">
                                Price: Low to High
                            </option>

                            <option value="Price: High to Low">
                                Price: High to Low
                            </option>

                        </select>

                    </div>

                </div>

                <div class="category-tabs" id="model-tabs">

                    <div class="category-tab active" data-filter="AllModels">
                        ALL
                    </div>

                    <div class="category-tab" data-filter="Saga">
                        SAGA
                    </div>

                    <div class="category-tab" data-filter="Persona">
                        PERSONA
                    </div>

                    <div class="category-tab" data-filter="Iriz">
                        IRIZ
                    </div>

                    <div class="category-tab" data-filter="S70">
                        S70
                    </div>

                    <div class="category-tab" data-filter="X50">
                        X50
                    </div>

                    <div class="category-tab" data-filter="X70">
                        X70
                    </div>

                    <div class="category-tab" data-filter="X90">
                        X90
                    </div>

                    <div class="category-tab" data-filter="e.MAS 7">
                        e.MAS 7
                    </div>

                </div>

            </div>

            <div class="inventory-grid" id="cars-container">

                <div style="text-align:center; color:#64748b; width: 100%; padding: 50px;">
                    Loading vehicles...
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

    function fetchFilteredCars() {

        const container = document.getElementById('cars-container');

        container.innerHTML = `
            <div style="text-align:center; color:#64748b; padding: 50px; width: 100%;">
                Loading...
            </div>
        `;

        let bodyType =
            document.querySelector('input[name="bodyTypeFilter"]:checked').value;

        let searchQuery =
            document.getElementById('searchInput').value;

        let sortValue =
            document.getElementById('sortSelect').value;

        fetch('fetch_cars.php', {

            method: 'POST',

            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },

            body:
                `model=${encodeURIComponent(currentModel)}
                &bodyType=${encodeURIComponent(bodyType)}
                &search=${encodeURIComponent(searchQuery)}
                &sort=${encodeURIComponent(sortValue)}`

        })

        .then(response => response.text())

        .then(html => {
            container.innerHTML = html;
        })

        .catch(error => console.error('Error:', error));

    }

    /* ================= MODEL TABS ================= */

    const modelTabs =
        document.querySelectorAll('.category-tab');

    modelTabs.forEach(tab => {

        tab.addEventListener('click', function() {

            modelTabs.forEach(t =>
                t.classList.remove('active')
            );

            this.classList.add('active');

            currentModel =
                this.getAttribute('data-filter');

            fetchFilteredCars();

        });

    });

    /* ================= BODY TYPE FILTER ================= */

    const bodyTypeRadios =
        document.querySelectorAll('input[name="bodyTypeFilter"]');

    bodyTypeRadios.forEach(radio => {

        radio.addEventListener('change', fetchFilteredCars);

    });

    /* ================= SEARCH ================= */

    document.getElementById('searchInput')
        .addEventListener('keypress', function(e) {

            if (e.key === 'Enter') {
                fetchFilteredCars();
            }

        });

    /* ================= RESET ================= */

    function resetFilters() {

        currentModel = 'AllModels';

        modelTabs.forEach(t =>
            t.classList.remove('active')
        );

        modelTabs[0].classList.add('active');

        document.querySelector(
            'input[name="bodyTypeFilter"][value="All"]'
        ).checked = true;

        document.getElementById('searchInput').value = '';

        document.getElementById('sortSelect').value = 'Latest';

        fetchFilteredCars();

    }

    /* ================= WISHLIST ================= */

    function addToWishlist(event, btnElement, carId) {

        event.preventDefault();

        event.stopPropagation();

        fetch('toggle_wishlist.php', {

            method: 'POST',

            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },

            body: `car_id=${carId}`

        })

        .then(response => response.json())

        .then(data => {

            if (data.status === 'added') {

                btnElement.classList.add('liked');

            }

            else if (data.status === 'removed') {

                btnElement.classList.remove('liked');

            }

            else if (data.status === 'not_logged_in') {

                window.location.href = 'Auth/login.php';

            }

            else if (data.status === 'error') {

                alert(data.message);

            }

        })

        .catch(error => console.error('Error:', error));

    }

    /* ================= OPEN SIDEBAR ================= */

    function openCarDetails(carId) {

        const drawer =
            document.getElementById('car-details-sidebar');

        const content =
            document.getElementById('drawer-content');

        const overlay =
            document.getElementById('drawerOverlay');

        const iconContainer =
            document.getElementById('expand-icon');

        drawer.style.maxWidth = '520px';

        if (iconContainer) {

            iconContainer.innerHTML =
                '<path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>';

        }

        drawer.classList.add('open');

        overlay.classList.add('show');

        content.innerHTML = `
            <div style="text-align: center; padding: 100px; color:#64748b;">
                Loading details... 🚗💨
            </div>
        `;

        fetch('fetch_car_details_sidebar.php?id=' + carId)

        .then(response => response.text())

        .then(html => {

            content.innerHTML = html;

        })

        .catch(err => console.error(err));

    }

    /* ================= CLOSE SIDEBAR ================= */

    function closeCarDetails() {

        const drawer =
            document.getElementById('car-details-sidebar');

        const overlay =
            document.getElementById('drawerOverlay');

        drawer.classList.remove('open');

        overlay.classList.remove('show');

    }

    /* ================= FULLSCREEN ================= */

    function toggleMainSidebarSize() {

        const sidebar =
            document.getElementById('car-details-sidebar');

        const iconContainer =
            document.getElementById('expand-icon');

        if (sidebar.style.maxWidth === '100%') {

            sidebar.style.maxWidth = '520px';

            if (iconContainer) {

                iconContainer.innerHTML =
                    '<path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>';

            }

        }

        else {

            sidebar.style.maxWidth = '100%';

            if (iconContainer) {

                iconContainer.innerHTML =
                    '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>';

            }

        }

    }

    function changeMainImg(src) {

        document.getElementById('main-gallery-img').src = src;

    }

    /* ================= INITIAL LOAD ================= */

    document.addEventListener('DOMContentLoaded', function() {

        fetchFilteredCars();

    });

</script>

<?php include 'Includes/footer.php'; ?>