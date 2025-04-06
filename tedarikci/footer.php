    <!-- Sayfa içeriği burada biter -->
</main>

<footer class="mt-auto py-3 bg-light">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <span class="text-muted">© <?= date('Y') ?> Tedarik Portalı</span>
            <span class="text-muted">v1.0</span>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Sidebar Toggle Script -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const main = document.getElementById('main');
        const navbar = document.getElementById('navbar');
        
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                main.classList.toggle('sidebar-hidden');
                navbar.classList.toggle('sidebar-hidden');
            });
        }
        
        // Mobil görünümde sidebar dışına tıklanınca sidebar'ı kapat
        document.addEventListener('click', function(event) {
            const windowWidth = window.innerWidth;
            if (windowWidth < 768 && sidebar.classList.contains('active')) {
                if (!sidebar.contains(event.target) && event.target !== sidebarToggle) {
                    sidebar.classList.remove('active');
                    main.classList.add('sidebar-hidden');
                    navbar.classList.add('sidebar-hidden');
                }
            }
        });
    });
</script>

<?php if (isset($extra_js)): ?>
<script>
    <?= $extra_js ?>
</script>
<?php endif; ?>
</body>
</html> 