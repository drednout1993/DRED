<?php
/**
 * Регистрация нового пользователя
 */

define('ACCESS_GRANTED', true);
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';

// Если уже авторизован - на дашборд
if (isUserLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $position = trim($_POST['position'] ?? '');
    
    // Валидация
    if (strlen($login) < 3) {
        $errors[] = 'Логин должен быть не менее 3 символов';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Пароль должен быть не менее 6 символов';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Пароли не совпадают';
    }
    if (empty($fullName)) {
        $errors[] = 'Укажите ФИО';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный email';
    }
    
    if (empty($errors)) {
        $result = registerUser($login, $password, $fullName, $email, $position);
        
        if ($result['success']) {
            $success = 'Регистрация успешна! Теперь вы можете войти.';
        } else {
            $errors[] = $result['message'];
        }
    }
}

$pageTitle = 'Регистрация';
ob_start();
?>

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100 py-5">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="bi bi-person-plus display-4 text-primary"></i>
                        <h3 class="mt-2">Регистрация</h3>
                        <p class="text-muted">Создание учётной записи</p>
                    </div>

                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="bi bi-check-circle"></i> <?= e($success) ?>
                        </div>
                        <a href="login.php" class="btn btn-primary w-100">Перейти ко входу</a>
                    <?php else: ?>
                        <?php if ($errors): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= e($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="login" class="form-label">Логин *</label>
                                <input type="text" class="form-control" id="login" name="login" 
                                       value="<?= e($_POST['login'] ?? '') ?>" required autofocus>
                            </div>
                            
                            <div class="mb-3">
                                <label for="full_name" class="form-label">ФИО *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?= e($_POST['full_name'] ?? '') ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= e($_POST['email'] ?? '') ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="position" class="form-label">Должность</label>
                                <input type="text" class="form-control" id="position" name="position" 
                                       value="<?= e($_POST['position'] ?? '') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Пароль *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">Подтверждение пароля *</label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-person-plus"></i> Зарегистрироваться
                                </button>
                                <a href="login.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-box-arrow-in-right"></i> Уже есть аккаунт? Войти
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/footer.php';
