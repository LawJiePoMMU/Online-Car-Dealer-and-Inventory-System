document.addEventListener('DOMContentLoaded', function () {
    const tabButtons = document.querySelectorAll('.inner-tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            this.classList.add('active');
            const targetId = this.getAttribute('data-target');
            const targetContent = document.getElementById(targetId);
            if (targetContent) {
                targetContent.classList.add('active');
            }
        });
    });

    const scoreInputs = document.querySelectorAll('.score-input');
    const scoreAvgInput = document.getElementById('score_avg');
    if (scoreInputs.length > 0 && scoreAvgInput) {
        scoreInputs.forEach(input => {
            input.addEventListener('input', function () {
                let ext = parseFloat(document.getElementById('score_ext').value) || 0;
                let int = parseFloat(document.getElementById('score_int').value) || 0;
                let mech = parseFloat(document.getElementById('score_mech').value) || 0;
                let tyre = parseFloat(document.getElementById('score_tyre').value) || 0;

                ext = Math.max(0, Math.min(5, ext));
                int = Math.max(0, Math.min(5, int));
                mech = Math.max(0, Math.min(5, mech));
                tyre = Math.max(0, Math.min(5, tyre));

                document.getElementById('score_ext').value = ext;
                document.getElementById('score_int').value = int;
                document.getElementById('score_mech').value = mech;
                document.getElementById('score_tyre').value = tyre;

                let avg = (ext + int + mech + tyre) / 4;
                scoreAvgInput.value = avg.toFixed(1);
            });
        });
    }
    const priceInput = document.querySelector('input[name="price"]');
    const tenureSelect = document.getElementById('loan_tenure_select');
    const installmentInput = document.querySelector('input[name="monthly_installment"]');

    function calculateInstallment() {
        if (!priceInput || !tenureSelect || !installmentInput) {
            console.warn("Error: Missing price input, tenure select, or installment input!");
            return;
        }

        const price = parseFloat(priceInput.value) || 0;
        const years = parseInt(tenureSelect.value) || 9;
        const interestRate = 0.03;

        if (price > 0) {
            const loanAmount = price * 0.9;
            const totalInterest = loanAmount * interestRate * years;
            const totalRepayment = loanAmount + totalInterest;
            const monthly = totalRepayment / (years * 12);
            installmentInput.value = monthly.toFixed(2);
        } else {
            installmentInput.value = '';
        }
    }

    if (priceInput && tenureSelect && installmentInput) {
        priceInput.addEventListener('input', calculateInstallment);
        tenureSelect.addEventListener('change', calculateInstallment);
        setTimeout(calculateInstallment, 100);
    }

    const locations = {
        "Johor": ["Johor Bahru", "Batu Pahat", "Kluang", "Kulai", "Muar", "Segamat", "Pontian", "Kota Tinggi", "Mersing", "Tangkak"],
        "Kedah": ["Alor Setar", "Sungai Petani", "Kulim", "Baling", "Langkawi", "Kubang Pasu", "Yan", "Padang Terap", "Sik"],
        "Kelantan": ["Kota Bharu", "Pasir Mas", "Tumpat", "Bachok", "Tanah Merah", "Machang", "Kuala Krai", "Gua Musang"],
        "Kuala Lumpur": ["Kuala Lumpur"],
        "Labuan": ["Labuan"],
        "Melaka": ["George Town", "Butterworth", "Bukit Mertajam", "Nibong Tebal", "Kepala Batas"],
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
        const savedState = document.getElementById('saved_state') ? document.getElementById('saved_state').value : '';
        const savedCity = document.getElementById('saved_city') ? document.getElementById('saved_city').value : '';

        for (const state in locations) {
            let option = document.createElement('option');
            option.value = state;
            option.textContent = state;
            if (state === savedState) option.selected = true;
            stateSelect.appendChild(option);
        }

        function populateCities(selectedState, preselectCity = '') {
            citySelect.innerHTML = '<option value="">Select City...</option>';
            if (selectedState && locations[selectedState]) {
                const cities = locations[selectedState];
                cities.forEach(city => {
                    let option = document.createElement('option');
                    option.value = city;
                    option.textContent = city;
                    if (city === preselectCity) option.selected = true;
                    citySelect.appendChild(option);
                });
            }
        }

        if (savedState) populateCities(savedState, savedCity);
        stateSelect.addEventListener('change', function () { populateCities(this.value); });
    }

    const ajaxInput = document.getElementById('ajaxImageInput');
    const galleryGrid = document.getElementById('imageGalleryGrid');
    const carIdElement = document.getElementById('car_id_post');
    const carId = carIdElement ? carIdElement.value : '0';

    if (ajaxInput && galleryGrid) {
        ajaxInput.addEventListener('change', function () {
            if (this.files.length === 0) return;
            const formData = new FormData();
            formData.append('car_id_ajax', carId);
            for (let i = 0; i < this.files.length; i++) {
                formData.append('ajax_car_images[]', this.files[i]);
            }
            const uploadBtnSpan = document.querySelector('.custom-file-upload span');
            let originalText = '';
            if (uploadBtnSpan) {
                originalText = uploadBtnSpan.innerText;
                uploadBtnSpan.innerText = "Uploading... Please wait.";
                uploadBtnSpan.style.color = "#6366f1";
            }
            fetch('edit_car_details.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        data.images.forEach(img => {
                            const html = `
                        <div class="gallery-item" id="img_container_${img.id}">
                            <img src="${img.url}">
                            <button type="button" class="delete-img-btn" onclick="${carId == 0 ? `deleteTempImage('${img.id}')` : `deleteExistingImage(${img.id})`}" title="Delete image">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>`;
                            galleryGrid.insertAdjacentHTML('beforeend', html);
                        });
                        if (uploadBtnSpan) {
                            uploadBtnSpan.innerText = originalText;
                            uploadBtnSpan.style.color = "";
                        }
                    } else {
                        alert('Upload failed: ' + (data.message || 'Unknown error'));
                    }
                    ajaxInput.value = '';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred during upload.');
                    if (uploadBtnSpan) { uploadBtnSpan.innerText = originalText; uploadBtnSpan.style.color = ""; }
                });
        });
    }

    const container = document.getElementById('inventory-container');
    if (container && container.children.length === 0) addInventoryRow();
    const originSelect = document.getElementById('car_origin_select');
    const historyTabBtn = document.getElementById('tab-history-btn');
    const basicTabBtn = document.querySelector('button[data-target="tab-basic"]');

    function toggleHistoryTab() {
        if (!originSelect || !historyTabBtn) return;
        const inventorySection = document.getElementById('variant_inventory_section');
        const stockGroup = document.getElementById('stock_group');
        const extColorGroup = document.getElementById('ext_color_group');

        if (originSelect.value === 'New Car') {
            historyTabBtn.style.display = 'none';
            if (inventorySection) inventorySection.style.display = 'block';
            if (stockGroup) stockGroup.style.display = 'none';
            if (extColorGroup) extColorGroup.style.display = 'none';

            if (historyTabBtn.classList.contains('active') && basicTabBtn) {
                basicTabBtn.click();
            }
            originSelect.className = 'badge badge-new';
        } else {
            historyTabBtn.style.display = 'inline-block';
            if (inventorySection) inventorySection.style.display = 'none';
            if (stockGroup) stockGroup.style.display = 'block';
            if (extColorGroup) extColorGroup.style.display = 'block';

            originSelect.className = 'badge badge-used';
        }
    }

    toggleHistoryTab();
    if (originSelect) originSelect.addEventListener('change', toggleHistoryTab);

    const saveBtn = document.querySelector('button[name="save_all_details"]');
    const mainForm = document.getElementById('mainCarForm');

    if (saveBtn && mainForm) {
        saveBtn.addEventListener('click', function () {
            if (!mainForm.checkValidity()) {
                const firstInvalidInput = mainForm.querySelector(':invalid');
                if (firstInvalidInput) {
                    const parentTab = firstInvalidInput.closest('.tab-content');
                    if (parentTab && !parentTab.classList.contains('active')) {
                        const targetId = parentTab.id;
                        const tabBtn = document.querySelector(`.inner-tab-btn[data-target="${targetId}"]`);
                        if (tabBtn) {
                            tabBtn.click();
                        }
                    }
                    setTimeout(() => {
                        firstInvalidInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 100);
                }
            }
        });
    }
});

function deleteExistingImage(imgId) {
    const formData = new FormData();
    formData.append('action', 'delete_image');
    formData.append('img_id', imgId);

    fetch('edit_car_details.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const el = document.getElementById('img_container_' + imgId);
                if (el) el.remove();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Image deleted', showConfirmButton: false, timer: 2000 });
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Failed to delete image.', showConfirmButton: false, timer: 3000 });
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Server error.', showConfirmButton: false, timer: 3000 });
            }
        });
}

function deleteTempImage(tempId) {
    const formData = new FormData();
    formData.append('action', 'delete_temp_image');
    formData.append('temp_id', tempId);

    fetch('edit_car_details.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const el = document.getElementById('img_container_' + tempId);
                if (el) el.remove();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Image deleted', showConfirmButton: false, timer: 2000 });
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Failed to delete image.', showConfirmButton: false, timer: 3000 });
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Server error.', showConfirmButton: false, timer: 3000 });
            }
        });
}

function addInventoryRow(colorName = '', colorHex = '#ffffff', qty = 1) {
    const container = document.getElementById('inventory-container');
    if (!container) return;

    const row = document.createElement('div');
    row.className = 'color-inv-row';

    row.innerHTML = `
        <div class="custom-color-picker">
            <input type="hidden" name="inv_color_hex[]" value="${colorHex}">
            <div class="selected-color-circle" style="background: ${colorHex};" onclick="toggleColorPalette(this)" title="Click to pick a color"></div>
            <div class="color-palette-popup">
                <div class="palette-swatch" style="background: #ffffff;" onclick="selectPresetColor(this, '#ffffff', 'Solid White')" title="Solid White"></div>
                <div class="palette-swatch" style="background: radial-gradient(circle, #ffffff 0%, #f3f4f6 100%);" onclick="selectPresetColor(this, '#f8fafc', 'Pearl White')" title="Pearl White"></div>
                <div class="palette-swatch" style="background: linear-gradient(135deg, #f3f4f6, #9ca3af);" onclick="selectPresetColor(this, '#d1d5db', 'Silver')" title="Silver"></div>
                <div class="palette-swatch" style="background: #6b7280;" onclick="selectPresetColor(this, '#6b7280', 'Meteor Grey')" title="Meteor Grey"></div>
                <div class="palette-swatch" style="background: #111827;" onclick="selectPresetColor(this, '#111827', 'Black')" title="Black"></div>
                <div class="palette-swatch" style="background: radial-gradient(circle, #374151 0%, #111827 100%);" onclick="selectPresetColor(this, '#1f2937', 'Matte Black')" title="Matte Black"></div>
                <div class="palette-swatch" style="background: #ef4444;" onclick="selectPresetColor(this, '#ef4444', 'Ruby Red')" title="Ruby Red"></div>
                <div class="palette-swatch" style="background: #991b1b;" onclick="selectPresetColor(this, '#991b1b', 'Maroon')" title="Maroon"></div>
                <div class="palette-swatch" style="background: #f97316;" onclick="selectPresetColor(this, '#f97316', 'Orange')" title="Orange"></div>
                <div class="palette-swatch" style="background: #eab308;" onclick="selectPresetColor(this, '#eab308', 'Yellow')" title="Yellow"></div>
                <div class="palette-swatch" style="background: linear-gradient(135deg, #fde047, #d97706);" onclick="selectPresetColor(this, '#d97706', 'Champagne Gold')" title="Champagne Gold"></div>
                <div class="palette-swatch" style="background: #b45309;" onclick="selectPresetColor(this, '#b45309', 'Bronze')" title="Bronze"></div>
                <div class="palette-swatch" style="background: #3b82f6;" onclick="selectPresetColor(this, '#3b82f6', 'Ocean Blue')" title="Ocean Blue"></div>
                <div class="palette-swatch" style="background: #0ea5e9;" onclick="selectPresetColor(this, '#0ea5e9', 'Cyan')" title="Cyan"></div>
                <div class="palette-swatch" style="background: #1e3a8a;" onclick="selectPresetColor(this, '#1e3a8a', 'Navy Blue')" title="Navy Blue"></div>
                <div class="palette-swatch" style="background: #22c55e;" onclick="selectPresetColor(this, '#22c55e', 'Green')" title="Green"></div>
                <div class="palette-swatch" style="background: #064e3b;" onclick="selectPresetColor(this, '#064e3b', 'Dark Green')" title="Dark Green"></div>
                <div class="palette-swatch" style="background: #8b5cf6;" onclick="selectPresetColor(this, '#8b5cf6', 'Purple')" title="Purple"></div>
            </div>
        </div>

        <div>
            <input type="text" name="inv_color[]" class="form-control color-name-input" placeholder="Color Name" value="${colorName}" style="width: 100%; font-weight: 600; margin-bottom: 0;" required>
        </div>

        <div class="qty-control">
            <input type="number" name="inv_qty[]" class="form-control" style="text-align: center; padding: 10px; width:80px; margin-bottom: 0;" value="${qty}" min="0">     
        </div>
 
        <button type="button" class="delete-img-btn" style="position: static; opacity: 1; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;" onclick="removeInventoryRow(this)" title="Delete Color">
            <i class="fas fa-trash-alt"></i>
        </button>
    `;
    container.appendChild(row);
}

function toggleColorPalette(circleElement) {
    document.querySelectorAll('.color-palette-popup').forEach(p => {
        if (p !== circleElement.nextElementSibling) p.classList.remove('active');
    });
    const popup = circleElement.nextElementSibling;
    popup.classList.toggle('active');
}

function selectPresetColor(swatchElement, hexCode, presetName) {
    const popup = swatchElement.parentElement;
    const circle = popup.previousElementSibling;
    const hiddenInput = circle.previousElementSibling;
    const row = popup.closest('.color-inv-row');
    const nameInput = row.querySelector('.color-name-input');
    circle.style.background = swatchElement.style.background;
    hiddenInput.value = hexCode;
    if (nameInput) {
        nameInput.value = presetName;
    }

    popup.classList.remove('active');
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
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'warning',
                title: 'You must keep at least one color variant!',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.onmouseenter = Swal.stopTimer;
                    toast.onmouseleave = Swal.resumeTimer;
                }
            });
        } else {
            alert("Action Denied: You must keep at least one color variant for a New Car.");
        }
    }
}