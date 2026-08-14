<!-- includes/footer.php - Global Application Footer -->
    </div> <!-- End .content-wrapper -->
</main> <!-- End .main-content -->
</div> <!-- End .app-container -->

<!-- Reusable Toast Container -->
<div id="toast-container"></div>

<!-- Custom Scripts via BASE_URL -->
<script src="<?= BASE_URL ?>assets/js/toast.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>assets/js/modal.js?v=<?= APP_VERSION ?>"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Lucide Icons globally
        if (window.lucide) {
            window.lucide.createIcons();
        }

        // Sidebar Toggle Handler (Matching Expense App behavior)
        const toggleBtn = document.getElementById('toggle-sidebar-btn');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                const body = document.body;
                body.classList.toggle('sidebar-mini');
                const isMini = body.classList.contains('sidebar-mini');
                
                // Save to localStorage & Cookie for persistent state across navigation
                localStorage.setItem('sidebar_mini', isMini ? '1' : '0');
                document.cookie = "sidebar_mini=" + (isMini ? "1" : "0") + "; path=/; max-age=31536000";

                // Trigger window resize event after CSS transition so Chart.js resizes cleanly
                setTimeout(function() {
                    window.dispatchEvent(new Event('resize'));
                }, 260);
            });
        }
    });
</script>
</body>
</html>
