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
        Toast.fire({ icon: 'success', title: 'Action completed successfully!' });
        cleanUrlParams(['success']);
    }
    if (urlParams.get('error') === 'duplicate_plate') {
        Toast.fire({ icon: 'error', title: 'License Plate already exists!' });
        cleanUrlParams(['error']);
    }
    if (urlParams.get('error') === 'system') {
        Toast.fire({ icon: 'error', title: 'System error occurred.' });
        cleanUrlParams(['error']);
    }

    function cleanUrlParams(paramsToRemove) {
        const cleanUrl = new URL(window.location);
        paramsToRemove.forEach(param => cleanUrl.searchParams.delete(param));
        window.history.replaceState(null, null, cleanUrl);
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
            let checkbox = e.target;
            let row = checkbox.closest('tr');

            if (checkbox.checked) row.classList.remove('no-print-row');
            else row.classList.add('no-print-row');

            let table = checkbox.closest('table');
            let selectAllBox = table.querySelector('.selectAllColumn');
            let allCheckboxes = table.querySelectorAll('.row-checkbox');
            let allChecked = Array.from(allCheckboxes).every(cb => cb.checked);

            if (selectAllBox) selectAllBox.checked = allChecked;
        }
    });
});
function toggleStatus(id, currentStatus, element) {
    fetch(window.location.pathname + '?ajax=1&toggle_id=' + id + '&current_status=' + currentStatus)
        .then(() => {
            let newStatus = currentStatus === 'Active' ? 'Inactive' : 'Active';
            const urlParams = new URLSearchParams(window.location.search);
            const filter = urlParams.get('status');
            if (filter && filter !== 'all' && filter !== newStatus) {
                let row = element.closest('tr');
                row.style.transition = 'opacity 0.4s ease';
                row.style.opacity = '0';

                setTimeout(() => {
                    row.remove();
                    if (document.querySelectorAll('tbody .data-row').length === 0) {
                        location.reload();
                    }
                }, 400);
            } else {
                let row = element.closest('tr');
                let statusText = row.querySelector('.status-cell span:last-child');
                let dot = row.querySelector('.status-cell .dot');
                let icon = element.querySelector('i');
                element.setAttribute('onclick', `toggleStatus(${id}, '${newStatus}', this)`);

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
        })
        .catch(error => {
            console.error('Error updating status:', error);
            Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Failed to update status.', showConfirmButton: false, timer: 3000 });
        });
}

let isCopyMode = false;

function toggleCopyMode() {
    const copyBtn = document.getElementById('copyBtn');
    const checkboxes = document.querySelectorAll('.row-checkbox');

    if (!isCopyMode) {
        isCopyMode = true;
        document.querySelectorAll('.row-checkbox').forEach(cb => {
            cb.style.display = 'inline-block';
            cb.checked = false;
        });

        copyBtn.innerHTML = '<i class="fas fa-paste"></i> Paste (Duplicate)';
        copyBtn.style.backgroundColor = '#f59e0b';
        copyBtn.style.color = '#fff';
        copyBtn.style.borderColor = '#f59e0b';
        document.getElementById('cancelCopyBtn').style.display = 'inline-block';

        Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Select ONE car and click Paste', showConfirmButton: false, timer: 3000 });
        checkboxes.forEach(cb => {
            cb.addEventListener('change', function () {
                if (this.checked) {
                    checkboxes.forEach(other => { if (other !== this) other.checked = false; });
                }
            });
        });
    } else {
        executePaste();
    }
}

function cancelCopyMode() {
    isCopyMode = false;
    const copyBtn = document.getElementById('copyBtn');
    document.querySelectorAll('.row-checkbox').forEach(cb => { 
        cb.style.display = 'none'; 
        cb.checked = false; 
    });
    copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy Selected';
    copyBtn.style.backgroundColor = '';
    copyBtn.style.color = '';
    copyBtn.style.borderColor = '';
    document.getElementById('cancelCopyBtn').style.display = 'none';
}

function executePaste() {
    let selectedId = null;
    document.querySelectorAll('tbody .data-row').forEach(row => {
        let cb = row.querySelector('.row-checkbox');
        if (cb && cb.checked && row.style.display !== 'none') {
            let carIdText = row.querySelector('td:nth-child(2)').textContent;
            selectedId = parseInt(carIdText.replace('CAR', ''), 10);
        }
    });

    if (!selectedId) {
        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'Please select a car first.', showConfirmButton: false, timer: 3000 });
        return;
    }

    fetch(window.location.pathname + '?ajax=1&action=copy_cars', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ car_ids: [selectedId] })
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Car duplicated! Redirecting...', showConfirmButton: false, timer: 2000 });
                setTimeout(() => {
                    window.location.href = 'edit_car_details.php?id=' + data.new_id;
                }, 1000);
            } else {
                Swal.fire('Error', data.message || 'System error occurred.', 'error');
            }
        })
        .catch(error => console.error('Error:', error));
}

function quickView(carId) {
    fetch(window.location.pathname + '?ajax=1&action=quick_view&car_id=' + carId)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const car = data.data;
                const price = car.car_status_price ? 'RM ' + parseFloat(car.car_status_price).toLocaleString() : 'TBA';
                const installment = car.monthly_installment ? 'RM ' + parseFloat(car.monthly_installment).toLocaleString() + ' / month' : '-';
                const desc = car.description ? car.description : '<span style="color:#94a3b8; font-style:italic;">No description provided.</span>';

                let colorHtml = '<span style="color:#94a3b8; font-style:italic;">No color inventory</span>';
                if (car.color_data) {
                    let cHtml = '';
                    const colorItems = car.color_data.split('||');
                    colorItems.forEach(item => {
                        const parts = item.split('::');
                        if (parts.length === 3) {
                            cHtml += `
                            <div style="display:inline-flex; align-items:center; background:#f8fafc; padding:4px 12px; border-radius:20px; margin-right:8px; margin-bottom:8px; font-size:12px; border:1px solid #e2e8f0; color:#334155; font-weight:500; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                                <div style="width:14px; height:14px; border-radius:50%; background-color:${parts[1]}; margin-right:8px; border:1px solid rgba(0,0,0,0.1);"></div>
                                ${parts[0]} <span style="margin-left:8px; color:#0f172a; font-weight:700;">${parts[2]}</span>
                            </div>`;
                        }
                    });
                    colorHtml = cHtml;
                }

                let historyHtml = '';
                if (car.car_origin === 'Used Car') {
                    historyHtml = `
                        <div class="qv-section" style="border-left: 4px solid #f59e0b;">
                            <h4 class="qv-title" style="color: #b45309;"><i class="fas fa-history"></i> Used Car History & Condition</h4>
                            <div class="qv-grid">
                                <div class="qv-item"><span class="qv-label">Mileage</span><span class="qv-val">${car.used_mileage || '0'} km</span></div>
                                <div class="qv-item"><span class="qv-label">Owners</span><span class="qv-val">${car.owners || '0'}</span></div>
                                <div class="qv-item"><span class="qv-label">Accident Record</span><span class="qv-val">${car.accident || 'None'}</span></div>
                                <div class="qv-item"><span class="qv-label">Flood/Fire</span><span class="qv-val">${car.flood || 'None'}</span></div>
                                <div class="qv-item"><span class="qv-label">Last Service</span><span class="qv-val">${car.last_service || '-'}</span></div>
                                <div class="qv-item"><span class="qv-label">Next Service</span><span class="qv-val">${car.next_service || '-'}</span></div>
                                <div class="qv-item"><span class="qv-label">Roadtax Expiry</span><span class="qv-val">${car.roadtax || '-'}</span></div>
                                <div class="qv-item"><span class="qv-label">Puspakom</span><span class="qv-val">${car.puspakom || '-'}</span></div>
                            </div>
                        </div>
                    `;
                }

                const htmlContent = `
                    <style>
                        .quick-view-html-container { margin: 0 !important; overflow: hidden !important; }
                        
                        .qv-container { font-family: 'Inter', 'Poppins', sans-serif; text-align: left; max-height: 72vh; overflow-y: auto; padding: 10px 20px 20px 10px; background: #f8fafc; border-radius: 8px; }
                        .qv-container::-webkit-scrollbar { width: 6px; }
                        .qv-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
                        
                        .qv-section { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
                        .qv-title { font-size: 15px; font-weight: 700; color: #1e3a8a; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-top: 0; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; letter-spacing: -0.3px; }
                        
                        .qv-grid { display: grid; grid-template-columns: 1fr 1fr; row-gap: 16px; column-gap: 24px; }
                        .qv-item { display: flex; flex-direction: column; gap: 4px; }
                        
                        .qv-label { font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px; }
                        .qv-val { font-size: 14px; color: #0f172a; font-weight: 500; }
                        .qv-val-price { color: #059669; font-weight: 800; font-size: 16px; }
                        .qv-badge-stock { display: inline-block; background: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; border: 1px solid #bbf7d0; }
                    </style>

                    <div class="qv-container">
                        
                        <div class="qv-section">
                            <h4 class="qv-title"><i class="fas fa-tags"></i> Basic Info & Pricing</h4>
                            <div class="qv-grid">
                                <div class="qv-item"><span class="qv-label">Type & Origin</span>
                                    <span class="qv-val">
                                        <span style="background:#1e3a8a; color:white; padding:2px 8px; border-radius:4px; font-size:11px; margin-right:6px; font-weight:600;">${car.car_origin}</span>${car.car_type_name || '-'}
                                    </span>
                                </div>
                                <div class="qv-item"><span class="qv-label">Variant</span><span class="qv-val">${car.variant || '-'}</span></div>
                                <div class="qv-item"><span class="qv-label">Year</span><span class="qv-val">${car.car_year || '-'}</span></div>
                                <div class="qv-item"><span class="qv-label">Plate No / Location</span><span class="qv-val">${car.car_plate || '-'} <span style="color:#cbd5e1; margin:0 4px;">|</span> ${car.location_city ? car.location_city + ', ' + car.location_state : '-'}</span></div>
                                <div class="qv-item"><span class="qv-label">Price</span><span class="qv-val qv-val-price">${price}</span></div>
                                <div class="qv-item"><span class="qv-label">Installment</span><span class="qv-val">${installment}</span></div>
                                <div class="qv-item"><span class="qv-label">Negotiable</span><span class="qv-val">${car.negotiable === 'Yes' ? '<span style="color:#059669; font-weight:700;">Yes</span>' : 'No'}</span></div>
                                <div class="qv-item"><span class="qv-label">Promotion/Rebate</span><span class="qv-val" style="color:#ea580c; font-weight:700;">${car.promotion_rebate || '-'}</span></div>
                            </div>
                        </div>

                        <div class="qv-section" style="border-left: 4px solid #10b981;">
                            <h4 class="qv-title"><i class="fas fa-boxes"></i> Inventory & Colors</h4>
                            <div style="margin-bottom: 16px;">
                                <span class="qv-label" style="display:block; margin-bottom:6px;">Total Stock</span>
                                <span class="qv-badge-stock">${car.car_status_stock_quantity || '0'} Units</span>
                            </div>
                            <div>
                                <span class="qv-label" style="display:block; margin-bottom:8px;">Color Variants Distribution</span>
                                <div style="display:flex; flex-wrap:wrap;">${colorHtml}</div>
                            </div>
                        </div>

                        <div class="qv-section">
                            <h4 class="qv-title"><i class="fas fa-cogs"></i> Powertrain & Performance</h4>
                            <div class="qv-grid">
                                <div class="qv-item"><span class="qv-label">Engine</span><span class="qv-val">${car.engine_type || '-'} <span style="color:#94a3b8; font-size:12px; margin-left:4px;">(${car.displacement ? car.displacement + ' cc' : '-'})</span></span></div>
                                <div class="qv-item"><span class="qv-label">Transmission</span><span class="qv-val">${car.transmission || '-'}</span></div>
                                <div class="qv-item"><span class="qv-label">Power Output</span><span class="qv-val">${car.hp ? car.hp + ' hp' : '-'} <span style="color:#cbd5e1; margin:0 4px;">|</span> ${car.torque ? car.torque + ' Nm' : '-'}</span></div>
                                <div class="qv-item"><span class="qv-label">Drive Type</span><span class="qv-val">${car.drive_type || '-'}</span></div>
                                <div class="qv-item"><span class="qv-label">Performance</span><span class="qv-val">0-100: ${car.acceleration ? car.acceleration + ' s' : '-'} <span style="color:#cbd5e1; margin:0 4px;">|</span> Max: ${car.top_speed ? car.top_speed + ' km/h' : '-'}</span></div>
                                <div class="qv-item"><span class="qv-label">Fuel Details</span><span class="qv-val">${car.fuel_type || '-'} <span style="color:#cbd5e1; margin:0 4px;">|</span> ${car.fuel_consumption ? car.fuel_consumption + ' L/100km' : '-'}</span></div>
                            </div>
                        </div>

                        <div class="qv-section">
                            <h4 class="qv-title"><i class="fas fa-ruler-combined"></i> Dimensions & Chassis</h4>
                            <div class="qv-grid">
                                <div class="qv-item"><span class="qv-label">Dimensions (L×W×H)</span><span class="qv-val">${car.dimensions || '-'}</span></div>
                                <div class="qv-item"><span class="qv-label">Wheelbase</span><span class="qv-val">${car.wheelbase ? car.wheelbase + ' mm' : '-'}</span></div>
                                <div class="qv-item"><span class="qv-label">Kerb Weight</span><span class="qv-val">${car.weight ? car.weight + ' kg' : '-'}</span></div>
                                <div class="qv-item"><span class="qv-label">Capacities</span><span class="qv-val">Boot: ${car.boot_cap ? car.boot_cap + ' L' : '-'} <span style="color:#cbd5e1; margin:0 4px;">|</span> Tank: ${car.fuel_tank ? car.fuel_tank + ' L' : '-'}</span></div>
                            </div>
                        </div>

                        <div class="qv-section">
                            <h4 class="qv-title"><i class="fas fa-shield-alt"></i> Features & Equipment</h4>
                            <div class="qv-grid" style="grid-template-columns: 1fr;">
                                <div class="qv-item"><span class="qv-label">Exterior & Interior</span><span class="qv-val">${car.ext_color || '-'} (Ext) / ${car.int_color || '-'} (Int) <span style="color:#cbd5e1; margin:0 4px;">|</span> ${car.seat_mat || '-'} Seats</span></div>
                                <div class="qv-item"><span class="qv-label">Safety</span><span class="qv-val">${car.airbags_count ? car.airbags_count + ' Airbags' : '-'}, ${car.feat_safety || '-'}</span></div>
                                <div class="qv-item"><span class="qv-label">Technology & Display</span><span class="qv-val">${car.screen || '-'}, ${car.feat_tech || '-'}</span></div>
                            </div>
                        </div>

                        ${historyHtml}

                        <div class="qv-section" style="margin-bottom: 0;">
                            <h4 class="qv-title"><i class="fas fa-align-left"></i> Description</h4>
                            <p style="white-space: pre-wrap; font-size: 13.5px; color: #475569; margin: 0; line-height: 1.7;">${desc}</p>
                        </div>
                    </div>
                `;

                Swal.fire({
                    title: `<div style="font-family: 'Inter', 'Poppins', sans-serif; font-size: 26px; font-weight: 800; color: #1e3a8a; text-align: left; padding: 15px 10px 5px 10px; letter-spacing: -0.5px;">${car.car_brand} ${car.car_model}</div>`,
                    html: htmlContent,
                    width: '850px',
                    padding: '1rem',
                    showCloseButton: true,
                    confirmButtonText: '<i class="fas fa-check" style="margin-right:6px;"></i> Done',
                    confirmButtonColor: '#1e3a8a',
                    customClass: {
                        popup: 'quick-view-popup',
                        htmlContainer: 'quick-view-html-container'
                    }
                });
            } else {
                Swal.fire('Error', 'Could not load car details.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'Server connection failed.', 'error');
        });
} 