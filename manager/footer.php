    </main> <!-- End Main Content -->

    <!-- Bootstrap JS (for any components that need it) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function markNotificationsRead(e) {
        if(e) e.preventDefault();
        fetch('mark_notifications_read.php', { method: 'POST' })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                // Hide the unread count badge
                const countBadge = document.getElementById('notificationCount');
                if(countBadge) countBadge.style.display = 'none';
                
                // Remove the highlighted background from dropdown items
                document.querySelectorAll('.dropdown-item').forEach(item => {
                    item.style.backgroundColor = 'transparent';
                });

                // Optionally, hide the "Mark all as read" button itself
                if(e && e.target) {
                    e.target.closest('li').style.display = 'none';
                }
            }
        })
        .catch(err => console.error('Error marking notifications as read:', err));
    }
    </script>
</body>
</html>
