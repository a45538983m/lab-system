<?php
// includes/footer.php
?>
    </main>

    <footer class="footer">
        <div class="container py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <div class="d-flex flex-column">
                <span class="footer-title">Лабораторная система</span>
                <span class="footer-subtitle">
                    &copy; <?php echo date('Y'); ?>. Все права защищены.
                    Электронные анализы и отчёты для больницы.
                </span>
            </div>

            <div class="footer-meta d-flex flex-wrap gap-2 gap-md-3 small justify-content-center">
                <span class="footer-pill footer-pill-1">Безопасное хранение данных</span>
                <span class="footer-pill footer-pill-2">Экспорт анализов в Excel</span>
                <span class="footer-pill footer-pill-3">Отчёты для главврача</span>
            </div>
        </div>
    </footer>
</div>

<!-- Bootstrap JS (локально) -->
<script src="/lab-system/public/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
