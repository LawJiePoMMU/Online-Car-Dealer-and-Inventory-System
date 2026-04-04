document.addEventListener("DOMContentLoaded", function () {
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
            rowCheckboxes.forEach(cb => {
                if (!cb.checked) allChecked = false;
            });
            if (selectAllBox) selectAllBox.checked = allChecked;
        }
    });

    let btnCancel = document.getElementById('btnCancel');
    if (btnCancel) {
        btnCancel.addEventListener('click', closeModal);
    }

    let eyeIcon = document.getElementById('togglePasswordIcon');
    if (eyeIcon) {
        eyeIcon.addEventListener('click', togglePasswordVisibility);
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
        document.getElementById('form_user_email').value = "";
        document.getElementById('form_user_phone').value = "";
        document.getElementById('form_user_password').value = "";
        document.getElementById('form_user_password').required = true;
        document.getElementById('password_group').style.display = 'block';
        document.getElementById('form_user_role').innerHTML = '<option value="Admin">Admin</option>';
        modal.classList.add('active');
    }
}

function closeModal() {
    const modal = document.getElementById('userModal');
    if (modal) modal.classList.remove('active');
}

function editUser(data) {
    const modal = document.getElementById('userModal');
    if (modal) {
        document.getElementById('modalTitle').innerText = "Edit User";
        document.getElementById('form_user_id').value = data.user_id;
        document.getElementById('form_user_name').value = data.user_name;
        document.getElementById('form_user_email').value = data.user_email;
        document.getElementById('form_user_phone').value = data.user_phone;
        document.getElementById('form_user_password').value = "";
        document.getElementById('form_user_password').required = false;

        let roleSelect = document.getElementById('form_user_role');
        let pwdGroup = document.getElementById('password_group');

        if (data.user_role === 'Customer') {
            roleSelect.innerHTML = '<option value="Customer">Customer</option><option value="Admin">Admin</option>';
            pwdGroup.style.display = 'none';
        } else {
            roleSelect.innerHTML = '<option value="Admin">Admin</option>';
            pwdGroup.style.display = 'block';
        }

        roleSelect.value = data.user_role;
        document.getElementById('form_user_status').value = data.user_status;
        modal.classList.add('active');
    }
}

function printSelected() {
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
        alert("Please check at least one box.");
        rows.forEach(r => r.classList.remove('no-print-row'));
        return;
    }

    window.print();

    setTimeout(() => {
        rows.forEach(r => r.classList.remove('no-print-row'));
    }, 1000);
}