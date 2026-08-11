<?php
/**
 * Личный кабинет пользователя
 */

define('ACCESS_GRANTED', true);
require_once __DIR__ . '/../includes/init.php';
requireAuth();

$user = getCurrentUser();
$pdo = getDbConnection();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ошибка безопасности. Попробуйте снова.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_profile') {
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $position = trim($_POST['position'] ?? '');
            $notifyEmail = isset($_POST['notify_email']);
            
            if (empty($fullName)) {
                $errors[] = 'Укажите ФИО';
            }
            if (!isValidEmail($email)) {
                $errors[] = 'Некорректный email';
            }
            
            if (empty($errors)) {
                $result = updateProfile($user['id'], $fullName, $email, $position, $notifyEmail);
                if ($result['success']) {
                    $success = $result['message'];
                    // Обновляем данные в сессии
                    getCurrentUser();
                } else {
                    $errors[] = $result['message'];
                }
            }
        } elseif ($action === 'change_password') {
            $oldPassword = $_POST['old_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (empty($oldPassword)) {
                $errors[] = 'Введите текущий пароль';
            }
            if (strlen($newPassword) < 6) {
                $errors[] = 'Новый пароль должен быть не менее 6 символов';
            }
            if ($newPassword !== $confirmPassword) {
                $errors[] = 'Пароли не совпадают';
            }
            
            if (empty($errors)) {
                $result = changePassword($user['id'], $oldPassword, $newPassword);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $errors[] = $result['message'];
                }
            }
        }
    }
}

$pageTitle = 'Личный кабинет';
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-person-circle"></i> Личный кабинет</h2>
    <a href="dashboard.php" class="btn btn-outline-secondary">Назад</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle"></i> <?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-person"></i> Редактирование профиля
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= generateCsrfInput() ?>
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="mb-3">
                        <label class="form-label">ФИО</label>
                        <input type="text" class="form-control" name="full_name" 
                               value="<?= e($user['full_name']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" 
                               value="<?= e($user['email']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Должность</label>
                        <input type="text" class="form-control" name="position" 
                               value="<?= e($user['position'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="notify_email" 
                               id="notifyEmail" <?= $user['notify_email'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notifyEmail">
                            Получать email-уведомления
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Сохранить
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-key"></i> Смена пароля
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= generateCsrfInput() ?>
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label class="form-label">Текущий пароль</label>
                        <input type="password" class="form-control" name="old_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Новый пароль</label>
                        <input type="password" class="form-control" name="new_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Подтверждение пароля</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-key-fill"></i> Изменить пароль
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Информация
            </div>
            <div class="card-body">
                <p><strong>Логин:</strong> <?= e($user['login']) ?></p>
                <p><strong>Роль:</strong> <?= e(getStatusText($user['role'])) ?></p>
                <p><strong>Дата регистрации:</strong> <?= formatDate($user['created_at']) ?></p>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/nav.php';
include __DIR__ . '/../templates/footer.php';
