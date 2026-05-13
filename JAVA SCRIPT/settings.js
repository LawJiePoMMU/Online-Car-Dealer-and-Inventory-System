document.addEventListener('DOMContentLoaded', () => {
    setupTabs();
    loadSettings();
});

function setupTabs() {
    const tabs = document.querySelectorAll('.settings-tab');
    const cards = document.querySelectorAll('.settings-card');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            cards.forEach(c => c.classList.remove('active'));
            tab.classList.add('active');
            const targetId = tab.getAttribute('data-target');
            document.getElementById(targetId).classList.add('active');
        });
    });
}

async function loadSettings() {
    try {
        const response = await fetch('settings.php?ajax=1', { cache: 'no-store' });
        const data = await response.json();

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

            if (data.user) {
                const n = document.getElementById('user_name');
                const e = document.getElementById('user_email');
                if (n) n.value = data.user.user_name || '';
                if (e) e.value = data.user.user_email || '';

                if (data.user.user_avatar) {
                    const img = document.getElementById('avatar_preview_img');
                    const icon = document.getElementById('avatar_icon');
                    if (img && icon) {
                        img.src = data.user.user_avatar;
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