<?php
/**
 * Восстановление пароля
 */

define('ACCESS_GRANTED', true);
require_once __DIR__ . '/../includes/init.php';

// Если уже авторизован - на дашборд
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ошибка безопасности. Попробуйте снова.';
    } else {
        $loginOrEmail = trim($_POST['login_or_email'] ?? '');
        
        if (empty($loginOrEmail)) {
            $error = 'Введите логин или email';
        } else {
            $result = requestPasswordReset($loginOrEmail);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
}

$pageTitle = 'Восстановление пароля';
ob_start();
?>

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="bi bi-key display-4 text-warning"></i>
                        <h3 class="mt-2">Восстановление пароля</h3>
                        <p class="text-muted">Введите логин или email для восстановления</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="bi bi-check-circle"></i> <?= e($message) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-circle"></i> <?= e($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($message)): ?>
                    <form method="POST">
                        <?= generateCsrfInput() ?>
                        
                        <div class="mb-3">
                            <label for="login_or_email" class="form-label">Логин или Email</label>
                            <input type="text" class="form-control" id="login_or_email" 
                                   name="login_or_email" required autofocus>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-envelope"></i> Отправить инструкции
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>

                    <div class="text-center">
                        <a href="../login.php" class="text-decoration-none">
                            <i class="bi bi-arrow-left"></i> Вернуться ко входу
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/footer.php';
