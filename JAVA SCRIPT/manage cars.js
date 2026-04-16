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
        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'Please check at least one box to print.', showConfirmButton: false, timer: 3000 });
        return;
    }

    window.print();
}