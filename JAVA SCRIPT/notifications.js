document.addEventListener('DOMContentLoaded', () => {

    const clearBtn = document.getElementById('clearAllBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Clear All Notifications?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, clear all',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (!result.isConfirmed) return;

                fetch('notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'clear_all' })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.querySelector('.notification-list-container').innerHTML = `
                            <div class="empty-state">
                                <i class="fa-regular fa-bell-slash"></i>
                                <p>No new notifications right now.</p>
                            </div>
                        `;
                            clearBtn.disabled = true;
                            const badge = document.getElementById('sidebar-badge');
                            if (badge) badge.remove();
                            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'All notifications cleared.', showConfirmButton: false, timer: 2000 });
                        } else {
                            Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Failed to clear.', showConfirmButton: false, timer: 3000 });
                        }
                    })
                    .catch(() => {
                        Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Server error.', showConfirmButton: false, timer: 3000 });
                    });
            });
        });
    }

    const unreadCards = document.querySelectorAll('.notification-card.unread');
    unreadCards.forEach(card => {
        card.addEventListener('click', function () {
            const notifId = this.dataset.id;

            fetch('notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_read', id: notifId })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.classList.remove('unread');
                        const dot = this.querySelector('.unread-dot');
                        if (dot) dot.remove();
                        const badge = document.getElementById('sidebar-badge');
                        const remaining = document.querySelectorAll('.notification-card.unread').length;
                        if (badge && remaining === 0) badge.remove();
                    }
                });
        });
    });

});