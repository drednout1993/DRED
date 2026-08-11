<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Реестры ФЭО') ?></title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="assets/css/bootstrap-icons.min.css">
    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <?php if (isset($additionalCss)): ?>
        <?php foreach ($additionalCss as $css): ?>
            <link rel="stylesheet" href="<?= e($css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="<?= isset($_SESSION['theme']) && $_SESSION['theme'] === 'dark' ? 'dark-mode' : '' ?>">
    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

    <!-- Loading Spinner -->
    <div class="loading-spinner">
        <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">Загрузка...</span>
        </div>
    </div>
