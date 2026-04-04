document.addEventListener("DOMContentLoaded", function () {
    const logoutBtn = document.querySelector('.logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function (e) {
            if (!confirm("Are you sure you want to log out from the admin panel?")) {
                e.preventDefault();
            }
        });
    }
});