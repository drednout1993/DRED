<?php
/**
 * Страница входа
 */

define('ACCESS_GRANTED', true);
require_once __DIR__ . '/includes/init.php';

// Если уже авторизован - на дашборд
if (isLoggedIn()) {
    header('Location: pages/dashboard.php');
    exit;
}

$error = '';
$login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF токена
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ошибка безопасности. Попробуйте ещё раз.';
    } else {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($login) || empty($password)) {
            $error = 'Введите логин и пароль';
        } else {
            $result = login($login, $password);
            
            if ($result['success']) {
                // Перенаправление после успешного входа
                $redirect = $_SESSION['redirect_after_login'] ?? 'pages/dashboard.php';
                unset($_SESSION['redirect_after_login']);
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}

$pageTitle = 'Вход в систему';
ob_start();
?>

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="bi bi-folder2-open display-4 text-primary"></i>
                        <h3 class="mt-2">Реестры ФЭО</h3>
                        <p class="text-muted">Вход в систему</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-circle"></i> <?= e($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <?= generateCsrfInput() ?>
                        <div class="mb-3">
                            <label for="login" class="form-label">Логин</label>
                            <input type="text" class="form-control" id="login" name="login" 
                                   value="<?= e($login) ?>" required autofocus>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Пароль</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right"></i> Войти
                            </button>
                        </div>
                    </form>

                    <hr class="my-4">

                    <div class="text-center">
                        <a href="pages/forgot_password.php" class="text-decoration-none">
                            <i class="bi bi-question-circle"></i> Забыли пароль?
                        </a>
                    </div>

                    <div class="text-center mt-3">
                        <small class="text-muted">
                            Нет учётной записи? 
                            <a href="pages/register.php" class="text-decoration-none">Зарегистрироваться</a>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/footer.php';
