<?php
/**
 * Smart College Management Portal - Footer Component
 * Provides global closing tags, Bootstrap 5 JS Bundle, Chart.js CDN, and UI scripts.
 */
?>
    </div> <!-- Close .portal-wrapper -->

    <!-- Global Footer -->
    <footer class="portal-footer">
        <div class="container-fluid px-4">
            <div class="row align-items-center gy-2">
                <div class="col-md-6 text-center text-md-start">
                    <span class="fw-semibold text-dark">Smart College Management Portal</span>
                    <span class="mx-2 text-muted">&bull;</span>
                    <span>Designed for Windows XAMPP Environment</span>
                </div>
                <div class="col-md-6 text-center text-md-end text-muted small">
                    <span>PHP <?= PHP_VERSION ?></span>
                    <span class="mx-1">&bull;</span>
                    <span>MariaDB / MySQL</span>
                    <span class="mx-1">&bull;</span>
                    <span>Bootstrap 5.3 & Chart.js</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5.3 JS Bundle (Popper included) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <!-- Chart.js CDN (Ready for Attendance Visualizations) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

    <!-- Mobile Sidebar & UI Helpers -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const portalSidebar = document.getElementById('portalSidebar');

            if (sidebarToggle && portalSidebar) {
                sidebarToggle.addEventListener('click', function () {
                    portalSidebar.classList.toggle('show');
                });

                // Close sidebar when clicking outside on mobile devices
                document.addEventListener('click', function (e) {
                    if (window.innerWidth < 992) {
                        if (!portalSidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                            portalSidebar.classList.remove('show');
                        }
                    }
                });
            }

            // Initialize Bootstrap Tooltips if any exist
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>
