document.addEventListener('DOMContentLoaded', function () {

    // ── Inner Tabs ──
    const tabButtons = document.querySelectorAll('.inner-tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    tabButtons.forEach(button => {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            tabButtons.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            const target = document.getElementById(this.getAttribute('data-target'));
            if (target) target.classList.add('active');
        });
    });

    // ── Score Average ──
    const scoreInputs = document.querySelectorAll('.score-input');
    const scoreAvgEl = document.getElementById('score_avg');
    if (scoreInputs.length && scoreAvgEl) {
        scoreInputs.forEach(input => {
            input.addEventListener('input', function () {
                const clamp = id => Math.max(0, Math.min(5, parseFloat(document.getElementById(id).value) || 0));
                const ext = clamp('score_ext');
                const int_ = clamp('score_int');
                const mech = clamp('score_mech');
                const tyre = clamp('score_tyre');
                document.getElementById('score_ext').value = ext;
                document.getElementById('score_int').value = int_;
                document.getElementById('score_mech').value = mech;
                document.getElementById('score_tyre').value = tyre;
                scoreAvgEl.value = ((ext + int_ + mech + tyre) / 4).toFixed(1);
            });
        });
    }

    // ── Monthly Installment Calculator ──
    const priceInput = document.querySelector('input[name="price"]');
    const tenureSelect = document.getElementById('loan_tenure_select');
    const installmentInput = document.getElementById('monthly_installment_input');
    function calculateInstallment() {
        if (!priceInput || !tenureSelect || !installmentInput) return;
        const price = parseFloat(priceInput.value) || 0;
        const years = parseInt(tenureSelect.value) || 9;
        if (price > 0) {
            const loan = price * 0.9;
            const total = loan + loan * 0.03 * years;
            installmentInput.value = (total / (years * 12)).toFixed(2);
        } else {
            installmentInput.value = '';
        }
    }
    if (priceInput) priceInput.addEventListener('input', calculateInstallment);
    if (tenureSelect) tenureSelect.addEventListener('change', calculateInstallment);
    setTimeout(calculateInstallment, 100);

    // ── State / City Dropdowns ──
    const locations = {
        "Johor": ["Johor Bahru", "Batu Pahat", "Kluang", "Kulai", "Muar", "Segamat", "Pontian", "Kota Tinggi", "Mersing", "Tangkak"],
        "Kedah": ["Alor Setar", "Sungai Petani", "Kulim", "Baling", "Langkawi", "Kubang Pasu", "Yan", "Padang Terap", "Sik"],
        "Kelantan": ["Kota Bharu", "Pasir Mas", "Tumpat", "Bachok", "Tanah Merah", "Machang", "Kuala Krai", "Gua Musang"],
        "Kuala Lumpur": ["Kuala Lumpur"],
        "Labuan": ["Labuan"],
        "Melaka": ["Melaka Tengah", "Alor Gajah", "Jasin"],
        "Negeri Sembilan": ["Seremban", "Port Dickson", "Jempol", "Tampin", "Kuala Pilah", "Rembau", "Jelebu"],
        "Pahang": ["Kuantan", "Temerloh", "Bentong", "Pekan", "Rompin", "Raub", "Jerantut", "Lipis", "Cameron Highlands"],
        "Penang": ["George Town", "Butterworth", "Bukit Mertajam", "Seberang Perai"],
        "Perak": ["Ipoh", "Taiping", "Teluk Intan", "Manjung", "Kuala Kangsar", "Batu Gajah", "Kampar", "Tapah"],
        "Perlis": ["Kangar", "Arau", "Padang Besar"],
        "Putrajaya": ["Putrajaya"],
        "Sabah": ["Kota Kinabalu", "Sandakan", "Tawau", "Lahad Datu", "Keningau", "Kinabatangan", "Semporna"],
        "Sarawak": ["Kuching", "Miri", "Sibu", "Bintulu", "Limbang", "Samarahan", "Sri Aman"],
        "Selangor": ["Petaling Jaya", "Shah Alam", "Subang Jaya", "Klang", "Kajang", "Sepang", "Selayang", "Ampang", "Gombak", "Hulu Langat"],
        "Terengganu": ["Kuala Terengganu", "Kemaman", "Dungun", "Besut", "Marang", "Setiu"]
    };
    const stateSelect = document.getElementById('state_select');
    const citySelect = document.getElementById('city_select');
    if (stateSelect && citySelect) {
        const savedState = (document.getElementById('saved_state') || {}).value || '';
        const savedCity = (document.getElementById('saved_city') || {}).value || '';
        for (const state in locations) {
            const opt = document.createElement('option');
            opt.value = opt.textContent = state;
            if (state === savedState) opt.selected = true;
            stateSelect.appendChild(opt);
        }
        function populateCities(selectedState, preselect = '') {
            citySelect.innerHTML = '<option value="">Select City...</option>';
            (locations[selectedState] || []).forEach(city => {
                const opt = document.createElement('option');
                opt.value = opt.textContent = city;
                if (city === preselect) opt.selected = true;
                citySelect.appendChild(opt);
            });
        }
        if (savedState) populateCities(savedState, savedCity);
        stateSelect.addEventListener('change', function () { populateCities(this.value); });
    }

    // ── AJAX Image Upload ──
    // KEY FIX: use window.EDIT_CAR_ID (set by PHP) instead of reading from a hidden input.
    // The hidden input approach broke because car_id_post was always 0 for new cars on first load.
    const carId = (typeof window.EDIT_CAR_ID !== 'undefined') ? window.EDIT_CAR_ID : 0;
    const ajaxInput = document.getElementById('ajaxImageInput');
    const galleryGrid = document.getElementById('imageGalleryGrid');
    if (ajaxInput && galleryGrid) {
        ajaxInput.addEventListener('change', function () {
            if (!this.files.length) return;
            const fd = new FormData();
            fd.append('car_id_ajax', carId);
            for (let i = 0; i < this.files.length; i++) fd.append('ajax_car_images[]', this.files[i]);
            const span = document.querySelector('.custom-file-upload span');
            const origText = span ? span.innerText : '';
            if (span) { span.innerText = 'Uploading... Please wait.'; span.style.color = '#6366f1'; }
            fetch('edit_car_details.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        data.images.forEach(img => {
                            const deleteFn = (carId == 0) ? `deleteTempImage('${img.id}')` : `deleteExistingImage(${img.id})`;
                            galleryGrid.insertAdjacentHTML('beforeend', `
                                <div class="gallery-item" id="img_container_${img.id}">
                                    <img src="${img.url}">
                                    <button type="button" class="delete-img-btn" onclick="${deleteFn}" title="Delete image">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>`);
                        });
                    } else {
                        alert('Upload failed: ' + (data.message || 'Unknown error'));
                    }
                    if (span) { span.innerText = origText; span.style.color = ''; }
                    ajaxInput.value = '';
                })
                .catch(err => {
                    console.error(err);
                    alert('An error occurred during upload.');
                    if (span) { span.innerText = origText; span.style.color = ''; }
                });
        });
    }

    // ── Inventory container: ensure at least one row ──
    const container = document.getElementById('inventory-container');
    if (container && container.children.length === 0) addInventoryRow();

    // ── New/Used Car UI toggle ──
    const originSelect = document.getElementById('car_origin_select');
    const histTabBtn = document.getElementById('tab-history-btn');
    const basicTabBtn = document.querySelector('button[data-target="tab-basic"]');

    function applyOriginUI() {
        if (!originSelect) return;
        const invSection = document.getElementById('variant_inventory_section');
        const stockGroup = document.getElementById('stock_group');
        const extColorGrp = document.getElementById('ext_color_group');
        const isNew = (originSelect.value === 'New Car');

        if (histTabBtn) histTabBtn.style.display = isNew ? 'none' : 'inline-block';
        if (invSection) invSection.style.display = isNew ? 'block' : 'none';
        if (stockGroup) stockGroup.style.display = isNew ? 'none' : 'block';
        if (extColorGrp) extColorGrp.style.display = isNew ? 'none' : 'block';

        if (isNew && histTabBtn && histTabBtn.classList.contains('active') && basicTabBtn) {
            basicTabBtn.click();
        }
        if (!isNew) {
            const stockInput = document.querySelector('input[name="stock"]');
            if (stockInput) { stockInput.value = 1; stockInput.max = 1; }
        }
        originSelect.className = 'badge ' + (isNew ? 'badge-new' : 'badge-used');
    }
    applyOriginUI();
    if (originSelect) originSelect.addEventListener('change', applyOriginUI);

    // ── Ext colour circle sync on load ──
    const extColorInput = document.getElementById('ext_color_input');
    const extColorCircle = document.getElementById('ext_color_circle');
    const extColorMap = {
        'Solid White': '#ffffff', 'Pearl White': '#f8fafc', 'Silver': '#d1d5db',
        'Meteor Grey': '#6b7280', 'Black': '#111827', 'Matte Black': '#1f2937',
        'Ruby Red': '#ef4444', 'Maroon': '#991b1b', 'Orange': '#f97316',
        'Yellow': '#eab308', 'Champagne Gold': '#d97706', 'Bronze': '#b45309',
        'Ocean Blue': '#3b82f6', 'Cyan': '#0ea5e9', 'Navy Blue': '#1e3a8a',
        'Green': '#22c55e', 'Dark Green': '#064e3b', 'Purple': '#8b5cf6'
    };
    if (extColorInput && extColorCircle && extColorMap[extColorInput.value]) {
        extColorCircle.style.background = extColorMap[extColorInput.value];
    }

    // ── Save button: only validate color names; let the form submit naturally ──
    // KEY FIX: The form has novalidate so browser HTML5 validation won't silently block submit.
    // We do minimal JS validation only for color names, then allow the form to POST.
    const saveBtn = document.querySelector('button[name="save_all_details"]');
    const mainForm = document.getElementById('mainCarForm');
    if (saveBtn && mainForm) {
        saveBtn.addEventListener('click', function (e) {
            // Only validate color name inputs for New Cars
            if (originSelect && originSelect.value === 'New Car') {
                const colorInputs = document.querySelectorAll('.color-name-input');
                let hasEmpty = false;
                colorInputs.forEach(input => {
                    if (!input.value.trim()) {
                        hasEmpty = true;
                        input.style.border = '2px solid #ef4444';
                    } else {
                        input.style.border = '';
                    }
                });
                if (hasEmpty) {
                    e.preventDefault();
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'Please fill in all color names', showConfirmButton: false, timer: 3000 });
                    }
                    return;
                }
            }
            // Allow the form to submit naturally — no e.preventDefault() here.
        });
    }
});

// ── Image delete functions ──
function deleteExistingImage(imgId) {
    const fd = new FormData();
    fd.append('action', 'delete_image');
    fd.append('img_id', imgId);
    fetch('edit_car_details.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                const el = document.getElementById('img_container_' + imgId);
                if (el) el.remove();
                if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Image deleted', showConfirmButton: false, timer: 2000 });
            } else {
                if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Failed to delete image.', showConfirmButton: false, timer: 3000 });
            }
        })
        .catch(() => { if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Server error.', showConfirmButton: false, timer: 3000 }); });
}

function deleteTempImage(tempId) {
    const fd = new FormData();
    fd.append('action', 'delete_temp_image');
    fd.append('temp_id', tempId);
    fetch('edit_car_details.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                const el = document.getElementById('img_container_' + tempId);
                if (el) el.remove();
                if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Image deleted', showConfirmButton: false, timer: 2000 });
            } else {
                if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Failed to delete image.', showConfirmButton: false, timer: 3000 });
            }
        })
        .catch(() => { if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Server error.', showConfirmButton: false, timer: 3000 }); });
}

// ── Inventory row functions ──
function addInventoryRow(colorName = '', colorHex = '#ffffff', qty = 1) {
    const container = document.getElementById('inventory-container');
    if (!container) return;
    const row = document.createElement('div');
    row.className = 'color-inv-row';
    row.innerHTML = `
        <div class="custom-color-picker">
            <input type="hidden" name="inv_color_hex[]" value="${colorHex}">
            <div class="selected-color-circle" style="background:${colorHex};" onclick="toggleColorPalette(this)" title="Click to pick a color"></div>
            <div class="color-palette-popup">
                <div class="palette-swatch" style="background:#ffffff;" onclick="selectPresetColor(this,'#ffffff','Solid White')" title="Solid White"></div>
                <div class="palette-swatch" style="background:radial-gradient(circle,#ffffff 0%,#f3f4f6 100%);" onclick="selectPresetColor(this,'#f8fafc','Pearl White')" title="Pearl White"></div>
                <div class="palette-swatch" style="background:linear-gradient(135deg,#f3f4f6,#9ca3af);" onclick="selectPresetColor(this,'#d1d5db','Silver')" title="Silver"></div>
                <div class="palette-swatch" style="background:#6b7280;" onclick="selectPresetColor(this,'#6b7280','Meteor Grey')" title="Meteor Grey"></div>
                <div class="palette-swatch" style="background:#111827;" onclick="selectPresetColor(this,'#111827','Black')" title="Black"></div>
                <div class="palette-swatch" style="background:radial-gradient(circle,#374151 0%,#111827 100%);" onclick="selectPresetColor(this,'#1f2937','Matte Black')" title="Matte Black"></div>
                <div class="palette-swatch" style="background:#ef4444;" onclick="selectPresetColor(this,'#ef4444','Ruby Red')" title="Ruby Red"></div>
                <div class="palette-swatch" style="background:#991b1b;" onclick="selectPresetColor(this,'#991b1b','Maroon')" title="Maroon"></div>
                <div class="palette-swatch" style="background:#f97316;" onclick="selectPresetColor(this,'#f97316','Orange')" title="Orange"></div>
                <div class="palette-swatch" style="background:#eab308;" onclick="selectPresetColor(this,'#eab308','Yellow')" title="Yellow"></div>
                <div class="palette-swatch" style="background:linear-gradient(135deg,#fde047,#d97706);" onclick="selectPresetColor(this,'#d97706','Champagne Gold')" title="Champagne Gold"></div>
                <div class="palette-swatch" style="background:#b45309;" onclick="selectPresetColor(this,'#b45309','Bronze')" title="Bronze"></div>
                <div class="palette-swatch" style="background:#3b82f6;" onclick="selectPresetColor(this,'#3b82f6','Ocean Blue')" title="Ocean Blue"></div>
                <div class="palette-swatch" style="background:#0ea5e9;" onclick="selectPresetColor(this,'#0ea5e9','Cyan')" title="Cyan"></div>
                <div class="palette-swatch" style="background:#1e3a8a;" onclick="selectPresetColor(this,'#1e3a8a','Navy Blue')" title="Navy Blue"></div>
                <div class="palette-swatch" style="background:#22c55e;" onclick="selectPresetColor(this,'#22c55e','Green')" title="Green"></div>
                <div class="palette-swatch" style="background:#064e3b;" onclick="selectPresetColor(this,'#064e3b','Dark Green')" title="Dark Green"></div>
                <div class="palette-swatch" style="background:#8b5cf6;" onclick="selectPresetColor(this,'#8b5cf6','Purple')" title="Purple"></div>
            </div>
        </div>
        <div>
            <input type="text" name="inv_color[]" class="form-control color-name-input" placeholder="Color Name" value="${colorName}" style="width:100%;font-weight:600;margin-bottom:0;">
        </div>
        <div class="qty-control">
            <input type="number" name="inv_qty[]" class="form-control" style="text-align:center;padding:10px;width:80px;margin-bottom:0;" value="${qty}" min="0">
        </div>
        <button type="button" class="delete-img-btn" style="position:static;opacity:1;width:36px;height:36px;display:flex;align-items:center;justify-content:center;" onclick="removeInventoryRow(this)" title="Delete Color">
            <i class="fas fa-trash-alt"></i>
        </button>`;
    container.appendChild(row);
}

function toggleColorPalette(circleElement) {
    document.querySelectorAll('.color-palette-popup').forEach(p => {
        if (p !== circleElement.nextElementSibling) p.classList.remove('active');
    });
    circleElement.nextElementSibling.classList.toggle('active');
}

function selectPresetColor(swatchElement, hexCode, presetName) {
    const popup = swatchElement.parentElement;
    const circle = popup.previousElementSibling;
    const hiddenInput = circle.previousElementSibling;
    const row = popup.closest('.color-inv-row');
    const nameInput = row.querySelector('.color-name-input');

    // Duplicate check
    let isDuplicate = false;
    document.querySelectorAll('.color-name-input').forEach(input => {
        if (input !== nameInput && input.value.trim() === presetName) isDuplicate = true;
    });
    if (isDuplicate) {
        popup.classList.remove('active');
        if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: `${presetName} is already added.`, showConfirmButton: false, timer: 2500 });
        return;
    }

    circle.style.background = swatchElement.style.background;
    hiddenInput.value = hexCode;
    if (nameInput) nameInput.value = presetName;
    popup.classList.remove('active');
}

function selectExtColor(swatchElement, hexCode, presetName) {
    const circle = document.getElementById('ext_color_circle');
    const input = document.getElementById('ext_color_input');
    const popup = swatchElement.closest('.color-palette-popup');
    if (circle) circle.style.background = swatchElement.style.background;
    if (input) input.value = presetName;
    if (popup) popup.classList.remove('active');
}

document.addEventListener('click', function (e) {
    if (!e.target.closest('.custom-color-picker')) {
        document.querySelectorAll('.color-palette-popup').forEach(p => p.classList.remove('active'));
    }
});

function removeInventoryRow(btn) {
    const container = document.getElementById('inventory-container');
    const currentRows = container.querySelectorAll('.color-inv-row').length;
    if (currentRows > 1) {
        btn.parentElement.remove();
    } else {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'You must keep at least one color variant!', showConfirmButton: false, timer: 3000, timerProgressBar: true });
        } else {
            alert("You must keep at least one color variant for a New Car.");
        }
    }
}