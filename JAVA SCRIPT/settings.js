document.addEventListener('DOMContentLoaded', () => {
    setupTabs();
    loadSettings();
});

function setupTabs() {
    const tabs = document.querySelectorAll('.settings-tab');
    const cards = document.querySelectorAll('.settings-card');

    const savedTab = localStorage.getItem('activeSettingsTab') || 'tab-profile';

    tabs.forEach(t => t.classList.remove('active'));
    cards.forEach(c => c.classList.remove('active'));

    const activeTabBtn = document.querySelector(`.settings-tab[data-target="${savedTab}"]`);
    const activeCard = document.getElementById(savedTab);

    if (activeTabBtn && activeCard) {
        activeTabBtn.classList.add('active');
        activeCard.classList.add('active');
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            cards.forEach(c => c.classList.remove('active'));

            tab.classList.add('active');
            const targetId = tab.getAttribute('data-target');
            document.getElementById(targetId).classList.add('active');

            localStorage.setItem('activeSettingsTab', targetId);
        });
    });
}

async function loadSettings() {
    try {
        const response = await fetch('settings.php?ajax=1', { cache: 'no-store' });
        const data = await response.json();
        const fixPath = (p) => {
            if (!p) return '';
            if (p.startsWith('../../')) {
                return p.replace('../../', '/Online-Car-Dealer-and-Inventory-System/');
            }
            return p;
        };

        if (data.success) {
            for (const key in data.data) {
                const input = document.getElementById(key);
                if (input) {
                    if (input.type === 'checkbox') {
                        input.checked = (data.data[key] === '1');
                    } else {
                        input.value = data.data[key];
                    }
                }
            }

            if (data.data.company_logo) {
                const logoImg = document.getElementById('logoPreview');
                const placeholder = document.getElementById('logoPlaceholder');
                if (logoImg && placeholder) {
                    logoImg.src = fixPath(data.data.company_logo);
                    logoImg.style.display = 'block';
                    placeholder.style.display = 'none';
                }
            }

            if (data.user) {
                const n = document.getElementById('user_name');
                const e = document.getElementById('user_email');
                if (n) n.value = data.user.user_name || '';
                if (e) e.value = data.user.user_email || '';

                if (data.user.user_avatar) {
                    const img = document.getElementById('avatar_preview_img');
                    const icon = document.getElementById('avatar_icon');
                    if (img && icon) {
                        img.src = fixPath(data.user.user_avatar);
                        img.style.display = 'block';
                        icon.style.display = 'none';
                    }
                }
            }
        }
    } catch (error) {
        console.error('Load Failed:', error);
    }
}

async function saveSettings(formId) {
    const form = document.getElementById(formId);
    const settingsData = {};
    let hasError = false;

    form.querySelectorAll('input, select, textarea').forEach(input => {
        if (!input.name) return;
        input.style.borderColor = '';

        if (input.type === 'number') {
            const val = parseFloat(input.value);
            if (isNaN(val) || val < 0) {
                hasError = true;
                input.style.borderColor = 'red';
            }
        }

        settingsData[input.name] = (input.type === 'checkbox') ? (input.checked ? '1' : '0') : input.value;
    });

    if (hasError) {
        Swal.fire('Error', 'Invalid or negative value detected.', 'error');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'update_settings');
    fd.append('settings_data', JSON.stringify(settingsData));
    await executeSave(fd, 'Settings Updated!');
}

async function saveProfile(formId) {
    const form = document.getElementById(formId);
    const fd = new FormData(form);
    fd.append('action', 'update_profile');
    await executeSave(fd, 'Profile Updated!');

    const pwd = form.querySelector('input[name="new_password"]');
    if (pwd) pwd.value = '';
    await loadSettings();
}

async function executeSave(formData, successMsg) {
    try {
        const response = await fetch('settings.php', { method: 'POST', body: formData });
        const result = await response.json();

        if (result.success) {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: successMsg, showConfirmButton: false, timer: 1500 });
        } else {
            Swal.fire('Error', result.message, 'error');
        }
    } catch (error) {
        Swal.fire('Error', 'Save Failed', 'error');
        console.error(error);
    }
}

function previewLogo(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function () {
        const img = document.getElementById('logoPreview');
        const placeholder = document.getElementById('logoPlaceholder');
        img.src = reader.result;
        img.style.display = 'block';
        if (placeholder) placeholder.style.display = 'none';
    };
    reader.readAsDataURL(file);

    const fd = new FormData();
    fd.append('action', 'upload_logo');
    fd.append('logo_file', file);
    fetch('settings.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Logo uploaded!', showConfirmButton: false, timer: 1500 });
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(e => console.error(e));
}

document.addEventListener('DOMContentLoaded', function () {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');
    const fileNameDisplay = document.getElementById('file-name-display');

    if (dropZone && fileInput) {
        dropZone.addEventListener('click', () => fileInput.click());

        fileInput.addEventListener('change', function () {
            if (this.files && this.files.length > 0) {
                fileNameDisplay.innerHTML = `<i class="fas fa-check-circle" style="color: #10b981; margin-right:5px;"></i> Selected: <strong style="color: #111827;">${this.files[0].name}</strong>`;
                dropZone.style.borderColor = '#10b981';
                dropZone.style.background = '#f0fdf4';
            } else {
                fileNameDisplay.innerHTML = `Drag and drop the banner image here, or click to select <span style="color: #1e3a8a; text-decoration: underline;">Browse</span>`;
                dropZone.style.borderColor = '#cbd5e1';
                dropZone.style.background = '#f8fafc';
            }
        });

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#2563eb';
            dropZone.style.background = '#eff6ff';
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.style.borderColor = '#cbd5e1';
            dropZone.style.background = '#f8fafc';
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                fileNameDisplay.innerHTML = `<i class="fas fa-check-circle" style="color: #10b981; margin-right:5px;"></i> Selected: <strong style="color: #111827;">${e.dataTransfer.files[0].name}</strong>`;
                dropZone.style.borderColor = '#10b981';
                dropZone.style.background = '#f0fdf4';
            }
        });
    }
});

function submitBannerForm() {
    try {
        if (typeof Swal === 'undefined') {
            alert("SweetAlert2 library failed to load.");
            return;
        }

        const fileInput = document.getElementById('file-input');
        const orderEl = document.getElementById('display_order');
        const formElement = document.getElementById('form-banner');

        if (!fileInput || !orderEl || !formElement) {
            alert("Missing required HTML elements.");
            return;
        }

        const orderInput = orderEl.value;

        if (!fileInput.files || !fileInput.files.length) {
            Swal.fire('Warning', 'Please select a banner image first.', 'warning');
            return;
        }
        if (!orderInput) {
            Swal.fire('Warning', 'Please set a display order.', 'warning');
            return;
        }

        const formData = new FormData(formElement);
        formData.append('action', 'upload_banner');

        Swal.fire({
            title: 'Uploading...',
            text: 'Please wait while the banner is being saved.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch('', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        localStorage.setItem('activeSettingsTab', 'tab-banners');
                        location.reload();
                    });
                } else {
                    Swal.fire('Failed', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Backend Error', error.message, 'error');
            });

    } catch (jsError) {
        alert("Execution Error: " + jsError.message);
    }
}

function deleteBanner(bannerId) {
    Swal.fire({
        title: 'Delete this banner?',
        text: "This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete_banner');
            formData.append('banner_id', bannerId);

            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Deleted!', text: data.message, timer: 1500, showConfirmButton: false })
                            .then(() => {
                                localStorage.setItem('activeSettingsTab', 'tab-banners');
                                location.reload();
                            });
                    } else {
                        Swal.fire('Failed', data.message, 'error');
                    }
                });
        }
    });
}

function toggleBannerStatus(bannerId, currentStatus) {
    const formData = new FormData();
    formData.append('action', 'toggle_banner_status');
    formData.append('banner_id', bannerId);
    formData.append('current_status', currentStatus);

    fetch('', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Status Updated',
                    showConfirmButton: false,
                    timer: 1000
                }).then(() => {
                    localStorage.setItem('activeSettingsTab', 'tab-banners');
                    location.reload();
                });
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Failed to change status', 'error');
        });
}