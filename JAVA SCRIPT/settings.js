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
                const userNameInput = document.getElementById('user_name');
                const userEmailInput = document.getElementById('user_email');

                if (userNameInput) userNameInput.value = data.user.user_name || '';
                if (userEmailInput) userEmailInput.value = data.user.user_email || '';
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
        console.error("Load Failed:", error);
    }
}

async function saveSettings(formId) {
    const form = document.getElementById(formId);
    const settingsData = {};
    let hasError = false;

    form.querySelectorAll('input, select, textarea').forEach(input => {
        if (input.name) {
            input.style.borderColor = '';

            if (input.type === 'number') {
                const val = parseFloat(input.value);
                if (val < 0 || isNaN(val)) {
                    hasError = true;
                    input.style.borderColor = 'red';
                }
            }

            if (input.type === 'checkbox') {
                settingsData[input.name] = input.checked ? '1' : '0';
            } else {
                settingsData[input.name] = input.value;
            }
        }
    });

    if (hasError) {
        Swal.fire('Error', 'Invalid or negative value detected.', 'error');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'update_settings');
    fd.append('settings_data', JSON.stringify(settingsData));

    await executeSave(fd, 'System Logic Updated!');
}

async function saveProfile(formId) {
    const form = document.getElementById(formId);
    const fd = new FormData(form);
    fd.append('action', 'update_profile');

    await executeSave(fd, 'Profile Updated!');

    const newPasswordInput = form.querySelector('input[name="new_password"]');
    if (newPasswordInput) {
        newPasswordInput.value = '';
    }
    await loadSettings();
}

async function executeSave(formData, successMsg) {
    try {
        const response = await fetch('settings.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: successMsg,
                showConfirmButton: false,
                timer: 1500
            });
        } else {
            Swal.fire('Error', result.message, 'error');
        }
    } catch (error) {
        Swal.fire('Error', 'Save Failed', 'error');
        console.error(error);
    }
}

function previewAvatar(event) {
    const reader = new FileReader();
    reader.onload = function() {
        const img = document.getElementById('avatar_preview_img');
        const icon = document.getElementById('avatar_icon');
        img.src = reader.result;
        img.style.display = 'block';
        if (icon) icon.style.display = 'none';
    }
    if (event.target.files[0]) {
        reader.readAsDataURL(event.target.files[0]);
    }
}