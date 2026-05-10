document.addEventListener('DOMContentLoaded', () => {
    const clearBtn = document.getElementById('clearAllBtn');

    if(clearBtn) {
        clearBtn.addEventListener('click', function() {
            if(!confirm('Are you sure you want to clear all notifications?')) return;

            fetch('api_clear_notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action: 'clear_all' })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    const container = document.querySelector('.notification-list-container');
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fa-regular fa-bell-slash"></i>
                            <p>No new notifications right now.</p>
                        </div>
                    `;
                    this.disabled = true;
                    const badge = document.getElementById('sidebar-badge');
                    if(badge) badge.remove();
                } else {
                    alert('Failed to clear notifications.');
                }
            })
            .catch(error => console.error('Error:', error));
        });
    }

    const unreadItems = document.querySelectorAll('.notification-item.unread');
    unreadItems.forEach(item => {
        item.addEventListener('click', function() {
            const notifId = this.dataset.id;
            
            fetch('api_mark_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: notifId })
            }).then(res => res.json()).then(data => {
                if(data.success) {
                    this.classList.remove('unread');
                    this.classList.add('read');
                    const indicator = this.querySelector('.unread-indicator');
                    if(indicator) indicator.remove();
                }
            });
        });
    });
});