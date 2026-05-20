document.addEventListener("DOMContentLoaded", function () {

    const logoutBtn = document.querySelector('.logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function (e) {
            if (!confirm("Are you sure you want to log out from the admin panel?")) {
                e.preventDefault();
            }
        });
    }

    const calendarEl = document.getElementById('calendar');

    if (calendarEl) {
        if (typeof FullCalendar === 'undefined') {
            console.error('FullCalendar is not loaded. Check your CDN script tag.');
            calendarEl.innerHTML = '<p style="color:#ef4444; padding:20px;">Calendar failed to load.</p>';
            return;
        }
        const events = window.calendarEvents || [];
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            height: 650,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },

            events: events
        });

        calendar.render();
    }
});