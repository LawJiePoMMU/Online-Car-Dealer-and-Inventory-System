const malaysiaData = {
    "Johor": ["Johor Bahru", "Batu Pahat", "Kluang", "Muar", "Kulai", "Segamat", "Pontian", "Kota Tinggi", "Mersing"],
    "Kedah": ["Alor Setar", "Sungai Petani", "Kulim", "Langkawi", "Baling", "Jitra"],
    "Kelantan": ["Kota Bharu", "Pasir Mas", "Tumpat", "Tanah Merah", "Gua Musang"],
    "Kuala Lumpur": ["Kuala Lumpur", "Cheras", "Kepong", "Setapak", "Wangsa Maju", "Bukit Jalil"],
    "Melaka": ["Melaka City", "Alor Gajah", "Ayer Keroh", "Jasin", "Masjid Tanah"],
    "Negeri Sembilan": ["Seremban", "Port Dickson", "Nilai", "Bahau", "Tampin"],
    "Pahang": ["Kuantan", "Temerloh", "Bentong", "Cameron Highlands", "Mentakab", "Pekan"],
    "Penang": ["George Town", "Butterworth", "Bukit Mertajam", "Bayan Lepas", "Seberang Perai"],
    "Perak": ["Ipoh", "Taiping", "Teluk Intan", "Sitiawan", "Manjung", "Kampar", "Lumut"],
    "Perlis": ["Kangar", "Arau", "Padang Besar"],
    "Putrajaya": ["Putrajaya"],
    "Sabah": ["Kota Kinabalu", "Sandakan", "Tawau", "Lahad Datu", "Keningau"],
    "Sarawak": ["Kuching", "Miri", "Sibu", "Bintulu", "Samarahan"],
    "Selangor": ["Petaling Jaya", "Shah Alam", "Subang Jaya", "Klang", "Puchong", "Kajang", "Sepang", "Rawang", "Cyberjaya", "Ampang"],
    "Terengganu": ["Kuala Terengganu", "Kemaman", "Dungun", "Besut"]
};

document.addEventListener("DOMContentLoaded", function () {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === '1') {
        Toast.fire({ icon: 'success', title: 'Car saved successfully!' });
        const cleanUrl = new URL(window.location);
        cleanUrl.searchParams.delete('success');
        window.history.replaceState(null, null, cleanUrl);
    }
    if (urlParams.get('error') === 'duplicate_plate') {
        Toast.fire({ icon: 'error', title: 'License Plate already exists!' });
        const cleanUrl = new URL(window.location);
        cleanUrl.searchParams.delete('error');
        window.history.replaceState(null, null, cleanUrl);
    }

    const stateSelect = document.getElementById('form_location_state');
    if (stateSelect) {
        for (const state in malaysiaData) {
            let option = document.createElement("option");
            option.value = state;
            option.text = state;
            stateSelect.appendChild(option);
        }
    }

    let selectAllColumns = document.querySelectorAll('.selectAllColumn');
    selectAllColumns.forEach(box => {
        box.addEventListener('change', function () {
            let table = this.closest('table');
            let checkboxes = table.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => {
                let row = cb.closest('tr');
                if (row.style.display !== 'none') {
                    cb.checked = this.checked;
                    if (cb.checked) row.classList.remove('no-print-row');
                    else row.classList.add('no-print-row');
                }
            });
        });
    });

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('row-checkbox')) {
            togglePrintRow(e.target);
            let table = e.target.closest('table');
            let selectAllBox = table.querySelector('.selectAllColumn');
            let allCheckboxes = table.querySelectorAll('.row-checkbox');
            let allChecked = true;
            allCheckboxes.forEach(cb => { if (!cb.checked) allChecked = false; });
            if (selectAllBox) selectAllBox.checked = allChecked;
        }
    });

    const plateInput = document.getElementById('form_car_plate');
    if (plateInput) {
        plateInput.addEventListener('input', function (e) {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9\s]/g, '');
        });
    }

    const carForm = document.getElementById('carForm');
    if (carForm) {
        carForm.addEventListener('submit', function (e) {
            const id = document.getElementById('form_car_id').value;
            const origin = document.getElementById('form_car_origin').value;
            const plate = document.getElementById('form_car_plate').value.trim();
            const state = document.getElementById('form_location_state').value;
            const city = document.getElementById('form_location_city').value;

            const imageInput = document.getElementById('form_car_image').files.length;
            if (id === "" && imageInput === 0) {
                e.preventDefault();
                Toast.fire({ icon: 'error', title: 'Please upload at least one image.' });
                return false;
            }

            if (origin === 'Used Car') {
                if (state === "" || city === "") {
                    e.preventDefault();
                    Toast.fire({ icon: 'error', title: 'Please select a State and City.' });
                    return false;
                }
                if (plate === "") {
                    e.preventDefault();
                    Toast.fire({ icon: 'error', title: 'Used cars must have a License Plate.' });
                    return false;
                }

                let isDup = false;
                if (typeof existingPlates !== 'undefined') {
                    for (let i = 0; i < existingPlates.length; i++) {
                        if (existingPlates[i].car_plate === plate && String(existingPlates[i].car_id) !== id) {
                            isDup = true; break;
                        }
                    }
                }
                if (isDup) {
                    e.preventDefault();
                    Toast.fire({ icon: 'error', title: 'License Plate already exists!' });
                    return false;
                }
            }
        });
    }
});

function updateCities(selectedCity = "") {
    const stateSelect = document.getElementById("form_location_state");
    const citySelect = document.getElementById("form_location_city");
    const selectedState = stateSelect.value;

    citySelect.innerHTML = '<option value="">Select City</option>';

    if (selectedState && malaysiaData[selectedState]) {
        malaysiaData[selectedState].forEach(city => {
            let option = document.createElement("option");
            option.value = city;
            option.text = city;
            citySelect.appendChild(option);
        });
        if (selectedCity !== "") {
            citySelect.value = selectedCity;
        }
    }
}

function switchCarType(carType) {
    const btnNew = document.getElementById('btn_new_car');
    const btnUsed = document.getElementById('btn_used_car');
    const usedCarFields = document.getElementById('used_car_fields');
    const hiddenOrigin = document.getElementById('form_car_origin');
    const plateInput = document.getElementById('form_car_plate');
    const stateInput = document.getElementById('form_location_state');
    const cityInput = document.getElementById('form_location_city');

    hiddenOrigin.value = carType;

    if (carType === 'New Car') {
        btnNew.classList.add('active');
        btnUsed.classList.remove('active');
        usedCarFields.style.display = 'none';
        plateInput.required = false;
        stateInput.required = false;
        cityInput.required = false;
        plateInput.value = "";
    } else {
        btnUsed.classList.add('active');
        btnNew.classList.remove('active');
        usedCarFields.style.display = 'block';
        plateInput.required = true;
        stateInput.required = true;
        cityInput.required = true;
    }
}

function openAddCarModal() {
    const modal = document.getElementById('carModal');
    if (modal) {
        document.getElementById('modalTitle').innerText = "Add New Car";
        document.getElementById('form_car_id').value = "";
        document.getElementById('group_car_id').style.display = "none";

        document.getElementById('form_car_brand').value = "";
        document.getElementById('form_car_model').value = "";
        document.getElementById('form_car_year').value = "";
        document.getElementById('form_car_price').value = "";
        document.getElementById('form_car_plate').value = "";
        document.getElementById('form_car_image').value = "";
        document.getElementById('form_car_image').required = true;
        document.getElementById('image_edit_note').innerText = "";

        document.getElementById('form_location_state').value = "";
        updateCities();
        document.getElementById('form_location_state').size = 1;
        document.getElementById('form_location_city').size = 1;

        switchCarType('New Car');
        modal.classList.add('active');
    }
}

function editCarModal(carData) {
    const modal = document.getElementById('carModal');
    if (modal) {
        document.getElementById('modalTitle').innerText = "Edit Car";

        document.getElementById('form_car_id').value = carData.car_id;
        document.getElementById('display_car_id').value = "CAR" + String(carData.car_id).padStart(3, '0');
        document.getElementById('group_car_id').style.display = "block";

        document.getElementById('form_car_brand').value = carData.car_brand;
        document.getElementById('form_car_model').value = carData.car_model;
        document.getElementById('form_car_year').value = carData.car_year;
        document.getElementById('form_car_price').value = carData.car_status_price;

        document.getElementById('form_car_image').required = false;
        document.getElementById('image_edit_note').innerText = "Leave empty to keep existing images.";

        switchCarType(carData.car_origin);

        if (carData.car_origin === 'Used Car') {
            document.getElementById('form_car_plate').value = carData.car_plate;
            if (carData.location_state) {
                document.getElementById('form_location_state').value = carData.location_state;
                updateCities(carData.location_city);
            }
        }

        if (carData.car_type_id) {
            document.getElementById('form_car_type_id').value = carData.car_type_id;
        }

        modal.classList.add('active');
    }
}

function closeCarModal() {
    document.getElementById('carModal').classList.remove('active');
}

function toggleStatus(id, currentStatus, element) {
    fetch(window.location.pathname + '?ajax=1&toggle_id=' + id + '&current_status=' + currentStatus);

    let newStatus = currentStatus === 'Active' ? 'Inactive' : 'Active';
    let row = element.closest('tr');

    let statusText = row.querySelector('.status-cell span:last-child');
    let dot = row.querySelector('.status-cell .dot');
    let icon = element.querySelector('i');

    element.setAttribute('onclick', 'toggleStatus(' + id + ', "' + newStatus + '", this)');

    if (newStatus === 'Active') {
        statusText.textContent = 'Active';
        statusText.className = 'text-active';
        dot.className = 'dot dot-active print-hide';
        icon.className = 'fas fa-lock';
        element.style.color = '#ef4444';
    } else {
        statusText.textContent = 'Inactive';
        statusText.className = 'text-inactive';
        dot.className = 'dot dot-inactive print-hide';
        icon.className = 'fas fa-unlock';
        element.style.color = '#10b981';
    }
}

function togglePrintRow(checkbox) {
    let row = checkbox.closest('tr');
    if (checkbox.checked) {
        row.classList.remove('no-print-row');
    } else {
        row.classList.add('no-print-row');
    }
}

function printSelected() {
    let hasSelected = false;
    let rows = document.querySelectorAll('tbody .data-row');

    rows.forEach(row => {
        let cb = row.querySelector('.row-checkbox');
        if (cb && cb.checked && row.style.display !== 'none') {
            hasSelected = true;
            row.classList.remove('no-print-row');
        } else {
            row.classList.add('no-print-row');
        }
    });

    if (!hasSelected) {
        const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
        Toast.fire({ icon: 'warning', title: 'Please check at least one box to print.' });
        return;
    }

    window.print();
}