<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Ошибка') ?> - Реестры ФЭО</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 bg-light">
    <div class="text-center">
        <h1 class="display-1 text-muted"><?= e($code ?? 500) ?></h1>
        <h2 class="mb-4"><?= e($title ?? 'Ошибка') ?></h2>
        <p class="lead text-muted mb-4"><?= e($message ?? 'Произошла непредвиденная ошибка') ?></p>
        <a href="index.php" class="btn btn-primary">
            <i class="bi bi-house"></i> На главную
        </a>
        <?php if (isUserLoggedIn()): ?>
            <a href="pages/dashboard.php" class="btn btn-outline-secondary ms-2">
                <i class="bi bi-speedometer2"></i> Дашборд
            </a>
        <?php endif; ?>
    </div>
</body>
</html>
