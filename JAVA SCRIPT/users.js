document.addEventListener("DOMContentLoaded", function () {

    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === '1') {
        Toast.fire({ icon: 'success', title: 'Saved successfully!' });
        window.history.replaceState(null, null, window.location.pathname);
    }
    if (urlParams.get('error') === 'duplicate') {
        Toast.fire({ icon: 'error', title: 'Data Duplicate! The IC Number, Name, Email, or Phone already belongs to another user.' });
        window.history.replaceState(null, null, window.location.pathname);
    }

    let selectAllBox = document.getElementById('selectAll');
    let rowCheckboxes = document.querySelectorAll('.row-checkbox');

    if (selectAllBox) {
        selectAllBox.addEventListener('change', function () {
            rowCheckboxes.forEach(cb => cb.checked = selectAllBox.checked);
        });
    }

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('row-checkbox')) {
            let allChecked = true;
            rowCheckboxes.forEach(cb => { if (!cb.checked) allChecked = false; });
            if (selectAllBox) selectAllBox.checked = allChecked;
        }
    });

    let btnCancel = document.getElementById('btnCancel');
    if (btnCancel) btnCancel.addEventListener('click', closeModal);

    let eyeIcon = document.getElementById('togglePasswordIcon');
    if (eyeIcon) eyeIcon.addEventListener('click', togglePasswordVisibility);

    const icInput = document.getElementById('form_user_ic');
    if (icInput) {
        icInput.addEventListener('input', function (e) {
            let val = this.value.replace(/\D/g, '');
            if (val.length > 12) val = val.substring(0, 12);

            let formatted = val;
            if (val.length > 6 && val.length <= 8) {
                formatted = val.substring(0, 6) + '-' + val.substring(6);
            } else if (val.length > 8) {
                formatted = val.substring(0, 6) + '-' + val.substring(6, 8) + '-' + val.substring(8);
            }
            this.value = formatted;
        });
    }

    const phoneInput = document.getElementById('form_user_phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function (e) {
            let val = this.value.replace(/\D/g, '');
            if (val.length > 11) val = val.substring(0, 11);

            let formatted = val;
            if (val.length > 3) {
                formatted = val.substring(0, 3) + '-' + val.substring(3);
            }
            this.value = formatted;
        });
    }

    const userForm = document.getElementById('userForm');
    if (userForm) {
        userForm.addEventListener('submit', function (e) {
            const id = document.getElementById('form_user_id').value;
            const name = document.getElementById('form_user_name').value.trim();
            const ic = document.getElementById('form_user_ic').value.trim();
            const email = document.getElementById('form_user_email').value.trim();
            const phone = document.getElementById('form_user_phone').value.trim();
            const password = document.getElementById('form_user_password').value;

            const phoneDigits = phone.replace(/\D/g, '');

            if (name === "") {
                e.preventDefault();
                Toast.fire({ icon: 'error', title: 'Please fill in the Full Name.' });
                return false;
            }

            if (ic.length !== 14) {
                e.preventDefault();
                Toast.fire({ icon: 'error', title: 'IC Number must be exactly 12 digits.' });
                return false;
            }

            if (!email.match(/^[a-zA-Z0-9._%+-]+@gmail\.com$/)) {
                e.preventDefault();
                Toast.fire({ icon: 'error', title: 'Must be a valid @gmail.com address.' });
                return false;
            }

            if (phoneDigits.length < 9 || phoneDigits.length > 11) {
                e.preventDefault();
                Toast.fire({ icon: 'error', title: 'Phone Number must be between 9 and 11 digits.' });
                return false;
            }

            if (id == "") {
                if (password === "") {
                    e.preventDefault();
                    Toast.fire({ icon: 'error', title: 'Password is required for new users.' });
                    return false;
                }
                if (!password.match(/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}$/)) {
                    e.preventDefault();
                    Toast.fire({ icon: 'error', title: 'Password must contain at least 8 chars (uppercase, lowercase, number, symbol).' });
                    return false;
                }
            } else {
                if (password !== "" && password !== "********") {
                    if (!password.match(/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}$/)) {
                        e.preventDefault();
                        Toast.fire({ icon: 'error', title: 'Password must contain at least 8 chars (uppercase, lowercase, number, symbol).' });
                        return false;
                    }
                }
            }

            let isDuplicate = false;
            for (let i = 0; i < existingUsers.length; i++) {
                let u = existingUsers[i];
                if (u.user_id != id) {
                    if (u.user_name === name || u.user_ic === ic || u.user_email === email || u.user_phone === phone) {
                        isDuplicate = true;
                        break;
                    }
                }
            }

            if (isDuplicate) {
                e.preventDefault();
                Toast.fire({ icon: 'error', title: 'Data Duplicate! IC, Name, Email, or Phone already exists.' });
                return false;
            }
        });
    }
});

function togglePasswordVisibility() {
    const pwdInput = document.getElementById('form_user_password');
    const eyeIcon = document.getElementById('togglePasswordIcon');
    if (pwdInput.type === "password") {
        pwdInput.type = "text";
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
    } else {
        pwdInput.type = "password";
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
    }
}

function openModal() {
    const modal = document.getElementById('userModal');
    if (modal) {
        document.getElementById('modalTitle').innerText = "Add New Admin";
        document.getElementById('form_user_id').value = "";
        document.getElementById('form_user_name').value = "";
        document.getElementById('form_user_ic').value = "";
        document.getElementById('form_user_email').value = "";
        document.getElementById('form_user_phone').value = "";
        document.getElementById('form_user_password').value = "";
        document.getElementById('form_user_password').required = true;
        document.getElementById('password_group').style.display = 'block';
        document.getElementById('form_user_role').value = 'Admin';
        modal.classList.add('active');
    }
}

function editUser(data) {
    const modal = document.getElementById('userModal');
    if (modal) {
        document.getElementById('modalTitle').innerText = "Edit User";
        document.getElementById('form_user_id').value = data.user_id;
        document.getElementById('form_user_name').value = data.user_name;
        document.getElementById('form_user_ic').value = (data.user_ic && data.user_ic !== 'NULL') ? data.user_ic : "";
        document.getElementById('form_user_email').value = data.user_email;
        document.getElementById('form_user_phone').value = data.user_phone ? data.user_phone : "";
        document.getElementById('form_user_password').value = "";
        document.getElementById('form_user_password').required = false;

        let roleInput = document.getElementById('form_user_role');
        let pwdGroup = document.getElementById('password_group');

        if (data.user_role === 'Customer') {
            roleInput.value = 'Customer';
            pwdGroup.style.display = 'none';
        } else {
            roleInput.value = 'Admin';
            pwdGroup.style.display = 'block';
        }
        document.getElementById('form_user_status').value = data.user_status;
        modal.classList.add('active');
    }
}

function closeModal() {
    const modal = document.getElementById('userModal');
    if (modal) modal.classList.remove('active');
}

function toggleStatus(id, currentStatus, element) {
    if (id == 4) return;
    fetch('users.php?ajax=1&toggle_id=' + id + '&current_status=' + currentStatus);
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

function printSelected() {
    let selectAllBox = document.getElementById('selectAll');

    if (selectAllBox && selectAllBox.checked) {
        document.body.classList.add('print-all-mode');
        window.print();
        setTimeout(() => { document.body.classList.remove('print-all-mode'); }, 1000);
        return;
    }

    let rows = document.querySelectorAll('.data-row');
    let hasSelected = false;

    rows.forEach(row => {
        let cb = row.querySelector('.row-checkbox');
        if (cb && cb.checked) {
            hasSelected = true;
        } else {
            row.classList.add('no-print-row');
        }
    });

    if (!hasSelected) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            icon: 'warning',
            title: 'Please check at least one box to print.'
        });
        rows.forEach(r => r.classList.remove('no-print-row'));
        return;
    }

    window.print();

    setTimeout(() => {
        rows.forEach(r => r.classList.remove('no-print-row'));
    }, 1000);
}