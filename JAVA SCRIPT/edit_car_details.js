document.addEventListener('DOMContentLoaded', function () {

    const Toast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false, timer: 2500, timerProgressBar: true
    });

    const tabButtons = document.querySelectorAll('.inner-tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            tabButtons.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            const target = document.getElementById(this.getAttribute('data-target'));
            if (target) target.classList.add('active');
        });
    });

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

    const stateWrapper = document.getElementById('state_wrapper');
    const cityWrapper = document.getElementById('city_wrapper');

    if (stateWrapper && cityWrapper) {
        const stateDisplay = document.getElementById('state_display');
        const stateList = document.getElementById('state_list');
        const stateInput = document.getElementById('state_input');
        const cityDisplay = document.getElementById('city_display');
        const cityList = document.getElementById('city_list');
        const cityInput = document.getElementById('city_input');

        function createDropdownItem(text, value, onClickCallback) {
            const div = document.createElement('div');
            div.className = 'custom-dropdown-item';
            div.textContent = text;
            div.dataset.value = value;
            div.addEventListener('click', onClickCallback);
            return div;
        }

        function populateCustomCities(stateName, preselect = '') {
            cityList.innerHTML = '';
            if (locations[stateName]) {
                locations[stateName].forEach(city => {
                    const item = createDropdownItem(city, city, function (e) {
                        e.stopPropagation();
                        cityDisplay.textContent = city;
                        cityInput.value = city;
                        cityList.classList.remove('active');
                    });
                    cityList.appendChild(item);
                });
            }

            if (preselect && locations[stateName] && locations[stateName].includes(preselect)) {
                cityDisplay.textContent = preselect;
                cityInput.value = preselect;
            } else {
                cityDisplay.textContent = 'Select City';
                cityInput.value = '';
            }
        }

        for (const st in locations) {
            const item = createDropdownItem(st, st, function (e) {
                e.stopPropagation();
                stateDisplay.textContent = st;
                stateInput.value = st;
                stateList.classList.remove('active');
                populateCustomCities(st);
            });
            stateList.appendChild(item);
        }

        const savedState = stateInput.value;
        const savedCity = cityInput.value;
        if (savedState && locations[savedState]) {
            stateDisplay.textContent = savedState;
            populateCustomCities(savedState, savedCity);
        }

        stateDisplay.addEventListener('click', function (e) {
            e.stopPropagation();
            cityList.classList.remove('active');
            stateList.classList.toggle('active');
        });

        cityDisplay.addEventListener('click', function (e) {
            e.stopPropagation();
            stateList.classList.remove('active');
            cityList.classList.toggle('active');
        });

        document.addEventListener('click', function () {
            stateList.classList.remove('active');
            cityList.classList.remove('active');
        });
    }

    if (window.__PRELOAD_INVENTORY && Array.isArray(window.__PRELOAD_INVENTORY) && window.__PRELOAD_INVENTORY.length > 0) {
        window.__PRELOAD_INVENTORY.forEach(row => {
            const parsedQty = parseInt(row.quantity);
            const safeQty = isNaN(parsedQty) ? 1 : Math.max(0, parsedQty);
            addInventoryRow(row.color_name || '', row.color_hex || '#ffffff', safeQty);
        });
    } else {
        addInventoryRow('', '#ffffff', 1);
    }
    updateTotalStock();
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
            if (span) { span.innerText = 'Uploading...'; span.style.color = '#6366f1'; }

            fetch('edit_car_details.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        data.images.forEach(img => {
                            const deleteFn = (carId == 0) ? `deleteTempImage('${img.id}')` : `deleteExistingImage(${img.id})`;
                            galleryGrid.insertAdjacentHTML('beforeend', `
                                <div class="gallery-item" id="img_container_${img.id}">
                                    <img src="${img.url}">
                                    <button type="button" class="delete-img-btn" onclick="${deleteFn}">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>`);
                        });
                        Toast.fire({ icon: 'success', title: data.images.length + ' image(s) uploaded' });
                    } else {
                        Toast.fire({ icon: 'error', title: 'Upload failed' });
                    }
                    if (span) { span.innerText = origText; span.style.color = ''; }
                    ajaxInput.value = '';
                })
                .catch(err => {
                    console.error(err);
                    Toast.fire({ icon: 'error', title: 'Upload error' });
                    if (span) { span.innerText = origText; span.style.color = ''; }
                });
        });
    }

    const originSelect = document.getElementById('car_origin_select');
    const histTabBtn = document.getElementById('tab-history-btn');
    const firstTabBtn = document.querySelector('button[data-target="tab-basic"]');

    function applyOriginUI() {
        if (!originSelect) return;
        const isUsed = (originSelect.value === 'Used Car');
        if (histTabBtn) histTabBtn.style.display = isUsed ? 'inline-flex' : 'none';
        if (!isUsed && histTabBtn && histTabBtn.classList.contains('active') && firstTabBtn) {
            firstTabBtn.click();
        }

        originSelect.className = 'origin-select ' + (isUsed ? 'used' : 'new');

        const btnAddColor = document.getElementById('btnAddColor');

        if (isUsed) {
            if (btnAddColor) btnAddColor.style.display = 'none';
            const invRows = document.querySelectorAll('.inv-row');
            for (let i = 1; i < invRows.length; i++) invRows[i].remove();
            const firstQty = document.querySelector('input[name="inv_qty[]"]');
            if (firstQty) {
                firstQty.value = 1;
                firstQty.readOnly = true;
            }
        } else {
            if (btnAddColor) btnAddColor.style.display = 'inline-flex';
            document.querySelectorAll('input[name="inv_qty[]"]').forEach(input => {
                input.readOnly = false;
            });
        }
        updateTotalStock();
    }
    applyOriginUI();
    if (originSelect) originSelect.addEventListener('change', applyOriginUI);

    const engineSelect = document.getElementById('engine_type_select');
    const fuelSelect = document.getElementById('fuel_type_select');
    const evSection = document.getElementById('ev_section');
    function applyEvUI() {
        if (!evSection) return;
        const isEv = (engineSelect && engineSelect.value === 'EV') || (fuelSelect && fuelSelect.value === 'Electric');
        evSection.style.display = isEv ? 'block' : 'none';
    }
    applyEvUI();
    if (engineSelect) engineSelect.addEventListener('change', applyEvUI);
    if (fuelSelect) fuelSelect.addEventListener('change', applyEvUI);

    const saveBtn = document.querySelector('button[name="save_all_details"]');
    if (saveBtn) {
        saveBtn.addEventListener('click', function (e) {
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
                Swal.fire({ icon: 'warning', title: 'Missing Color Names', text: 'Each color row needs a name.' });
            }
        });
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.custom-color-picker')) {
            document.querySelectorAll('.color-palette-popup').forEach(p => p.classList.remove('active'));
        }
    });

    const pdfInput = document.querySelector('input[name="inspection_pdf"]');
    if (pdfInput) {
        pdfInput.addEventListener('change', function () {
            const existingPreview = document.getElementById('temp-pdf-preview');
            if (existingPreview) existingPreview.remove();
            if (this.files && this.files.length > 0) {
                const file = this.files[0];
                if (file.type === 'application/pdf') {
                    const fileURL = URL.createObjectURL(file);
                    const previewHtml = `
                        <div id="temp-pdf-preview" style="display:flex;align-items:center;gap:12px;margin-top:12px;padding:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px; animation: fadeIn 0.3s ease;">
                            <i class="fas fa-file-pdf" style="color:#3b82f6;font-size:20px;"></i>
                            <span style="flex:1; color:#1e3a8a; font-weight:600; font-size: 13px;">
                                Ready to upload: ${file.name} 
                                <span style="color:#60a5fa; font-weight:400;">(${(file.size / 1024 / 1024).toFixed(2)} MB)</span>
                            </span>
                            <a href="${fileURL}" target="_blank" style="padding:6px 14px; background:#3b82f6; color:white; border-radius:8px; text-decoration:none; font-size:13px; font-weight:600; transition:0.2s; box-shadow:0 2px 4px rgba(59,130,246,0.3);">
                                <i class="fas fa-eye"></i> View 
                            </a>
                        </div>
                    `;

                    this.insertAdjacentHTML('afterend', previewHtml);
                } else {
                    Swal.fire({ icon: 'error', title: 'Invalid File', text: 'Please select a PDF document.' });
                    this.value = '';
                }
            }
        });
    }
});

function addInventoryRow(colorName, colorHex, qty) {
    const container = document.getElementById('inventory-container');
    if (!container) return;
    if (colorName === undefined || colorName === null) colorName = '';
    if (!colorHex) colorHex = '#ffffff';
    const originSelect = document.getElementById('car_origin_select');
    const isUsed = originSelect && originSelect.value === 'Used Car';

    let displayQty;
    if (isUsed) {
        displayQty = 1;
    } else {
        const parsed = parseInt(qty);
        if (isNaN(parsed) || parsed < 0) {
            displayQty = (qty === 0) ? 0 : 1;
        } else {
            displayQty = parsed;
        }
    }
    const qtyReadonly = isUsed ? 'readonly' : '';

    const palette = [
        ['#ffffff', 'Solid White'], ['#f8fafc', 'Pearl White'], ['#d1d5db', 'Silver'],
        ['#6b7280', 'Meteor Grey'], ['#111827', 'Black'], ['#1f2937', 'Matte Black'],
        ['#ef4444', 'Ruby Red'], ['#991b1b', 'Maroon'], ['#f97316', 'Orange'],
        ['#eab308', 'Yellow'], ['#d97706', 'Champagne Gold'], ['#b45309', 'Bronze'],
        ['#3b82f6', 'Ocean Blue'], ['#0ea5e9', 'Cyan'], ['#1e3a8a', 'Navy Blue'],
        ['#22c55e', 'Green'], ['#064e3b', 'Dark Green'], ['#8b5cf6', 'Purple']
    ];
    const swatchHtml = palette.map(([hex, name]) =>
        `<div class="palette-swatch" style="background:${hex};" onclick="selectPresetColor(this,'${hex}','${name}')" title="${name}"></div>`
    ).join('');

    const row = document.createElement('div');
    row.className = 'inv-row';
    row.innerHTML = `
        <input type="text" name="inv_color[]" class="form-control color-name-input"
               placeholder="Color Name (e.g., Pearl White)" value="${escapeHtml(colorName)}">
        <div class="custom-color-picker">
            <input type="hidden" name="inv_color_hex[]" value="${colorHex}">
            <div class="selected-color-circle" style="background:${colorHex};" onclick="toggleColorPalette(this)" title="Pick color"></div>
            <div class="color-palette-popup">${swatchHtml}</div>
        </div>
        <input type="number" min="0" max="100" step="1"
               onkeydown="
                   if(['-','e','.'].includes(event.key)) event.preventDefault();
                   if(event.key === '0' && this.value === '0') event.preventDefault();
               "
               oninput="
                   this.value = this.value.replace(/^0+(?=\d)/, '');
                   if (this.value !== '' && +this.value > 100) this.value = 100;
                   updateTotalStock();
               "
               onblur="
                   this.value = (this.value === '' || isNaN(this.value) || +this.value < 0) ? 0 : parseInt(this.value, 10);
                   updateTotalStock();
               "
               name="inv_qty[]" class="form-control inv-qty-input"
               placeholder="Qty" value="${displayQty}" ${qtyReadonly}>
        <button type="button" class="icon-btn" onclick="removeInventoryRow(this)" title="Remove">
            <i class="fas fa-trash-alt"></i>
        </button>
    `;
    container.appendChild(row);
    updateTotalStock();
}

function removeInventoryRow(btn) {
    const container = document.getElementById('inventory-container');
    const rows = container.querySelectorAll('.inv-row');
    if (rows.length > 1) {
        btn.closest('.inv-row').remove();
        updateTotalStock();
    } else {
        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'Keep at least one color', showConfirmButton: false, timer: 2500 });
    }
}

function updateTotalStock() {
    const originSelect = document.getElementById('car_origin_select');
    const display = document.getElementById('display_stock');
    if (!display) return;

    if (originSelect && originSelect.value === 'Used Car') {
        display.value = 1;
    } else {
        const qtyInputs = document.querySelectorAll('input[name="inv_qty[]"]');
        let total = 0;
        qtyInputs.forEach(input => {
            const v = parseInt(input.value);
            if (!isNaN(v) && v > 0) total += v;
        });
        display.value = total;
    }
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
    const row = popup.closest('.inv-row');
    const nameInput = row.querySelector('.color-name-input');
    let isDup = false;
    document.querySelectorAll('#inventory-container .inv-row').forEach(r => {
        if (r === row) return;
        const c = (r.querySelector('.color-name-input') || {}).value || '';
        if (c.trim() === presetName) isDup = true;
    });
    if (isDup) {
        popup.classList.remove('active');
        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: `${presetName} already exists`, showConfirmButton: false, timer: 2500 });
        return;
    }

    circle.style.background = hexCode;
    hiddenInput.value = hexCode;
    if (nameInput && !nameInput.value.trim()) nameInput.value = presetName;
    popup.classList.remove('active');
}

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
            }
        });
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
            }
        });
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/[&<>"']/g, ch => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[ch]);
}

document.querySelectorAll('input[type="number"]').forEach(input => {
    input.addEventListener('keydown', (e) => {
        if (['-', 'e'].includes(e.key)) e.preventDefault();
    });

    input.addEventListener('input', (e) => {
        if (e.target.value.length > 1 && e.target.value.startsWith('0')) {
            e.target.value = e.target.value.replace(/^0+/, '');
        }
    });
});