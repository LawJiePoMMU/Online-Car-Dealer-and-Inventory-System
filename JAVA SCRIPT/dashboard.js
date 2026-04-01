document.addEventListener("DOMContentLoaded", function () {
    let currentLocation = window.location.pathname.split('/').pop();
    let menuItems = document.querySelectorAll('.sidebar-menu a');

    menuItems.forEach(item => {
        if (item.getAttribute('href').includes(currentLocation)) {
            menuItems.forEach(i => i.classList.remove('active'));
            item.classList.add('active');
        }
    });
});

const logoutBtn = document.querySelector('.logout-btn');
if (logoutBtn) {
    logoutBtn.addEventListener('click', function (e) {
        if (!confirm("Are you sure you want to log out from the admin panel?")) {
            e.preventDefault();
        }
    });
}