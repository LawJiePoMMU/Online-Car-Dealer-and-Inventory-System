document.addEventListener("DOMContentLoaded", function () {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
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

    const highlightId = urlParams.get('highlight');
    if (highlightId) {
        cleanUrlParams(['highlight']);
        setTimeout(() => {
            let targetRow = null;
            document.querySelectorAll('tbody .data-row').forEach(row => {
                const carIdCell = row.querySelector('td:nth-child(2)');
                if (carIdCell) {
                    const rowCarId = carIdCell.textContent.trim().replace('CAR', '').replace(/^0+/, '');
                    if (rowCarId === highlightId) {
                        targetRow = row;
                    }
                }
            });
            if (targetRow) {
                targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                let count = 0;
                const originalBg = targetRow.style.backgroundColor;
                const flash = setInterval(() => {
                    if (count % 2 === 0) {
                        targetRow.style.backgroundColor = '#fef3c7';
                        targetRow.style.transition = 'background-color 0.3s ease';
                    } else {
                        targetRow.style.backgroundColor = originalBg;
                    }
                    count++;
                    if (count >= 6) {
                        clearInterval(flash);
                        targetRow.style.backgroundColor = originalBg;
                    }
                }, 400);
            }
        }, 500);
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

    let carViewModal = document.getElementById('carViewModal');
    if (carViewModal) {
        carViewModal.addEventListener('click', function (e) {
            if (e.target === carViewModal) {
                closeCarModal();
            }
        });
    }
});

function toggleStatus(id, currentStatus, element) {
    let newStatus = currentStatus === 'Active' ? 'Inactive' : 'Active';
    let row = element.closest('tr');
    fetch(window.location.pathname + '?ajax=1&toggle_id=' + id + '&current_status=' + currentStatus)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            let urlParamsStatus = new URLSearchParams(window.location.search).get('status');
            if ((urlParamsStatus === 'Active' && newStatus === 'Inactive') ||
                (urlParamsStatus === 'Inactive' && newStatus === 'Active')) {
                row.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                row.style.opacity = '0';
                row.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    row.remove();
                    if (document.querySelectorAll('tbody .data-row').length === 0) {
                        location.reload();
                    }
                }, 400);
            } else {
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
        })
        .catch(error => {
            console.error('Error toggling status:', error);
            Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Failed to update status.', showConfirmButton: false, timer: 3000 });
        });
}

let isCopyMode = false;

function toggleCopyMode() {
    const copyBtn = document.getElementById('copyBtn');
    const checkboxes = document.querySelectorAll('.row-checkbox');
    const selectAllBox = document.querySelector('.selectAllColumn');

    if (!isCopyMode) {
        isCopyMode = true;
        if (selectAllBox) selectAllBox.style.display = 'none';

        checkboxes.forEach(cb => {
            cb.style.display = 'inline-block';
            cb.checked = false;

            // 強制單選邏輯：點擊時把其他的都取消勾選
            cb.onclick = function () {
                if (this.checked) {
                    checkboxes.forEach(other => {
                        if (other !== this) other.checked = false;
                    });
                }
            };
        });

        copyBtn.innerHTML = '<i class="fas fa-paste"></i> Paste (Duplicate)';
        copyBtn.style.backgroundColor = '#f59e0b';
        copyBtn.style.color = '#fff';
        copyBtn.style.borderColor = '#f59e0b';
        document.getElementById('cancelCopyBtn').style.display = 'inline-block';

        Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Select ONE car and click Paste', showConfirmButton: false, timer: 3000 });
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

    Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Duplicating car data...', showConfirmButton: false, timer: 2000 });
    fetch(window.location.pathname + '?ajax=1&action=copy_cars', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ car_ids: [selectedId] })
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Duplicated!',
                    text: 'Car has been successfully copied as a Draft.',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = 'edit_car_details.php?id=' + data.new_id;
                });
            } else {
                Swal.fire('Error', data.message || 'Failed to copy car.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Server connection failed.', 'error');
        });
}

function quickView(carId) {
    fetch(window.location.pathname + '?ajax=1&action=quick_view&car_id=' + carId)
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') {
                Swal.fire('Error', 'Could not load car details.', 'error');
                return;
            }

            const car = data.data;
            const setVal = (id, val) => {
                const el = document.getElementById(id);
                if (!el) return;
                el.value = (val !== null && val !== undefined && val !== '') ? val : '-';
            };

            document.getElementById('carModalTitle').innerText =
                'View ' + (car.car_brand || '') + ' ' + (car.car_model || '');
            const originBadge = document.getElementById('cv_origin_badge');
            const isUsed = car.car_origin === 'Used Car';
            if (originBadge) {
                originBadge.textContent = isUsed ? 'USED CAR' : 'NEW CAR';
                originBadge.className = 'origin-select ' + (isUsed ? 'used' : 'new');
            }

            setVal('cv_brand', car.car_brand);
            setVal('cv_model', car.car_model);
            setVal('cv_variant', car.all_variants);
            setVal('cv_body_type', car.body_type || car.car_type_name);
            setVal('cv_year', car.car_year);
            setVal('cv_seats', car.seats);
            setVal('cv_mileage_basic', car.car_mileage ? parseInt(car.car_mileage).toLocaleString() : '0');
            setVal('cv_fuel_type', car.fuel_type);
            setVal('cv_transmission', car.transmission);
            const descEl = document.getElementById('cv_description');
            if (descEl) descEl.value = car.description || 'No description provided.';

            setVal('cv_price', car.car_status_price ? 'RM ' + parseFloat(car.car_status_price).toLocaleString() : 'TBA');
            setVal('cv_status', car.car_status_status);
            setVal('cv_stock', (car.total_stock || '0') + ' Units');

            const colorContainer = document.getElementById('cv_colors_container');
            if (car.color_data) {
                let cHtml = '';
                car.color_data.split('||').forEach(item => {
                    const parts = item.split('::');
                    if (parts.length === 3) {
                        cHtml += `<div style="display:inline-flex;align-items:center;background:#f9fafb;padding:8px 14px;border-radius:20px;margin-right:8px;margin-bottom:8px;font-size:13px;border:1px solid #e2e8f0;color:#334155;font-weight:500;">
                            <div style="width:16px;height:16px;border-radius:50%;background-color:${parts[1]};margin-right:10px;border:1px solid rgba(0,0,0,0.1);"></div>
                            ${parts[0]} <span style="margin-left:10px;color:#0f172a;font-weight:700;">${parts[2]}</span>
                        </div>`;
                    }
                });
                colorContainer.innerHTML = cHtml;
            } else {
                colorContainer.innerHTML = '<span style="color:#94a3b8;font-style:italic;font-size:13px;">No color inventory</span>';
            }

            setVal('cv_engine_type', car.engine_type);
            setVal('cv_engine_cc', car.engine_cc ? car.engine_cc + ' cc' : '-');
            setVal('cv_compression', car.compression_ratio);
            setVal('cv_peak_power', car.peak_power_kw ? car.peak_power_kw + ' kW' : '-');
            setVal('cv_peak_torque', car.peak_torque_nm ? car.peak_torque_nm + ' Nm' : '-');
            setVal('cv_length', car.length ? car.length + ' mm' : '-');
            setVal('cv_width', car.width ? car.width + ' mm' : '-');
            setVal('cv_height', car.height ? car.height + ' mm' : '-');
            setVal('cv_wheelbase', car.wheelbase ? car.wheelbase + ' mm' : '-');
            setVal('cv_fuel_tank', car.fuel_tank ? car.fuel_tank + ' L' : '-');
            setVal('cv_weight', car.weight ? car.weight + ' kg' : '-');
            setVal('cv_int_color', car.int_color);
            setVal('cv_seat_mat', car.seat_mat);
            setVal('cv_wheel_size', car.wheel_size);
            setVal('cv_headlights', car.headlights);
            setVal('cv_screen', car.screen);
            setVal('cv_airbags', car.airbags_count);
            const featConfEl = document.getElementById('cv_feat_conf');
            if (featConfEl) featConfEl.value = car.feat_conf || '-';

            setVal('cv_front_brakes', car.front_brakes);
            setVal('cv_rear_brakes', car.rear_brakes);
            setVal('cv_front_susp', car.front_suspension);
            setVal('cv_rear_susp', car.rear_suspension);
            setVal('cv_steering', car.steering_type);
            setVal('cv_front_tyres', car.front_tyres);
            setVal('cv_rear_tyres', car.rear_tyres);
            setVal('cv_front_rim', car.front_rim_inches ? car.front_rim_inches + '"' : '-');
            setVal('cv_rear_rim', car.rear_rim_inches ? car.rear_rim_inches + '"' : '-');

            const evSection = document.getElementById('cv_ev_section');
            if (car.battery_range && car.battery_range !== '0') {
                evSection.style.display = 'block';
                setVal('cv_battery_range', car.battery_range + ' km');
            } else {
                evSection.style.display = 'none';
            }

            const gallery = document.getElementById('cv_image_gallery');
            if (gallery) {
                if (car.image_urls) {
                    let imgHtml = '';
                    car.image_urls.split('||').forEach(url => {
                        if (url) {
                            imgHtml += `<div style="position:relative;border-radius:12px;overflow:hidden;height:130px;box-shadow:0 2px 6px rgba(0,0,0,0.06);">
                                <img src="${url}" style="width:100%;height:100%;object-fit:cover;">
                            </div>`;
                        }
                    });
                    gallery.innerHTML = imgHtml;
                } else {
                    gallery.innerHTML = '<div style="grid-column:1/-1;padding:32px;text-align:center;color:#94a3b8;font-style:italic;">No images available</div>';
                }
            }

            const historyTabBtn = document.getElementById('qv-tab-history-btn');
            if (isUsed) {
                if (historyTabBtn) historyTabBtn.style.display = 'inline-flex';
                setVal('cv_state', car.location_state);
                setVal('cv_city', car.location_city);
                setVal('cv_plate', car.car_plate);
                setVal('cv_owners', car.owners);
                setVal('cv_rem_warranty', car.rem_warranty);
                setVal('cv_accident', car.accident || 'None');
                setVal('cv_flood', car.flood || 'No');
                setVal('cv_service_hist', car.service_hist);
                setVal('cv_last_service', car.last_service);
                setVal('cv_next_service', car.next_service ? parseInt(car.next_service).toLocaleString() : '-');
                setVal('cv_roadtax', car.roadtax);
                setVal('cv_puspakom', car.puspakom);
                const defectsEl = document.getElementById('cv_defects');
                if (defectsEl) defectsEl.value = car.defects || '-';

                const pdfGroup = document.getElementById('cv_inspection_pdf_group');
                const pdfLink = document.getElementById('cv_inspection_pdf_link');
                if (car.inspection_pdf && car.inspection_pdf !== '') {
                    pdfGroup.style.display = 'block';
                    pdfLink.href = car.inspection_pdf;
                } else {
                    pdfGroup.style.display = 'none';
                }
            } else {
                if (historyTabBtn) historyTabBtn.style.display = 'none';
            }

            document.querySelectorAll('.qv-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.qv-tab-content').forEach(c => c.classList.remove('active'));
            document.querySelector('.qv-tab-btn[data-qv-target="qv-tab-basic"]').classList.add('active');
            document.getElementById('qv-tab-basic').classList.add('active');

            const modal = document.getElementById('carViewModal');
            modal.classList.add('active');
            const content = modal.querySelector('.modal-content');
            if (content) content.scrollTop = 0;
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'Server connection failed.', 'error');
        });
}

document.addEventListener('click', function (e) {
    const btn = e.target.closest('.qv-tab-btn');
    if (!btn) return;
    const target = btn.getAttribute('data-qv-target');
    if (!target) return;
    document.querySelectorAll('.qv-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.qv-tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    const content = document.getElementById(target);
    if (content) content.classList.add('active');
});

function closeCarModal() {
    const modal = document.getElementById('carViewModal');
    if (modal) modal.classList.remove('active');
}