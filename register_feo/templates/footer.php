    <!-- Основной контент -->
    <div class="main-content">
        <?php if ($flash = getFlashMessage()): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?= $content ?? '' ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <!-- Main JS -->
    <script src="assets/js/main.js"></script>
    
    <?php if (isset($additionalJs)): ?>
        <?php foreach ($additionalJs as $js): ?>
            <script src="<?= e($js) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Toast уведомления из сессии -->
    <?php if (isset($_SESSION['toast_messages'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($_SESSION['toast_messages'] as $toast): ?>
                showToast(<?= json_encode($toast['message']) ?>, '<?= e($toast['type']) ?>');
            <?php endforeach; ?>
        });
        </script>
        <?php unset($_SESSION['toast_messages']); ?>
    <?php endif; ?>
</body>
</html>
