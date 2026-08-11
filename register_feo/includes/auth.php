<?php
/**
 * Аутентификация и авторизация
 */

// Защита от прямого доступа
if (!defined('ACCESS_GRANTED')) {
    http_response_code(403);
    die('Доступ запрещён');
}

/**
 * Вход пользователя
 */
function login($login, $password) {
    // Проверка блокировки
    if (isLoginBlocked($login)) {
        return ['success' => false, 'message' => 'Аккаунт временно заблокирован после множественных неудачных попыток входа. Попробуйте через 15 минут.'];
    }
    
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ? AND is_blocked = 0");
        $stmt->execute([$login]);
        $user = $stmt->fetch();
        
        if (!$user) {
            recordLoginAttempt($login, false);
            return ['success' => false, 'message' => 'Неверный логин или пароль'];
        }
        
        if (!password_verify($password, $user['password'])) {
            recordLoginAttempt($login, false);
            return ['success' => false, 'message' => 'Неверный логин или пароль'];
        }
        
        // Успешный вход
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_login'] = $user['login'];
        recordLoginAttempt($login, true);
        cleanupLoginAttempts();
        
        return ['success' => true];
    } catch (Exception $e) {
        logError('Ошибка входа: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Ошибка сервера при входе'];
    }
}

/**
 * Выход пользователя
 */
function logout() {
    session_unset();
    session_destroy();
    session_start();
}

/**
 * Регистрация нового пользователя
 */
function registerUser($login, $password, $fullName, $email, $position = '') {
    try {
        $pdo = getDbConnection();
        
        // Проверка существования логина
        $stmt = $pdo->prepare("SELECT id FROM users WHERE login = ?");
        $stmt->execute([$login]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Пользователь с таким логином уже существует'];
        }
        
        // Хэширование пароля
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        
        // Создание пользователя
        $stmt = $pdo->prepare("
            INSERT INTO users (login, password, full_name, email, position, role, notify_email, created_at)
            VALUES (?, ?, ?, ?, ?, 'user', 1, NOW())
        ");
        $stmt->execute([$login, $passwordHash, $fullName, $email, $position]);
        
        return ['success' => true, 'user_id' => $pdo->lastInsertId()];
    } catch (Exception $e) {
        logError('Ошибка регистрации: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Ошибка сервера при регистрации'];
    }
}

/**
 * Восстановление пароля
 */
function requestPasswordReset($loginOrEmail) {
    try {
        $pdo = getDbConnection();
        
        // Поиск пользователя по логину или email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ? OR email = ?");
        $stmt->execute([$loginOrEmail, $loginOrEmail]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // Не показываем, существует ли пользователь
            return ['success' => true, 'message' => 'Если пользователь найден, инструкции отправлены на email'];
        }
        
        // Генерация временного пароля
        $newPassword = bin2hex(random_bytes(8));
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        
        // Обновление пароля
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$passwordHash, $user['id']]);
        
        // Отправка email
        $subject = 'Восстановление пароля - Реестры ФЭО';
        $body = "
            <h2>Восстановление пароля</h2>
            <p>Здравствуйте, " . e($user['full_name']) . "!</p>
            <p>Ваш новый пароль: <strong>" . e($newPassword) . "</strong></p>
            <p>Рекомендуется изменить пароль после входа в систему.</p>
            <p><a href='" . getBaseUrl() . "/login.php'>Перейти ко входу</a></p>
        ";
        
        if (sendEmailNotification($user['email'], $subject, $body)) {
            return ['success' => true, 'message' => 'Инструкции отправлены на email'];
        } else {
            return ['success' => false, 'message' => 'Ошибка отправки email. SMTP не настроен.'];
        }
    } catch (Exception $e) {
        logError('Ошибка восстановления пароля: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Ошибка сервера'];
    }
}

/**
 * Изменение пароля пользователя
 */
function changePassword($userId, $oldPassword, $newPassword) {
    try {
        $pdo = getDbConnection();
        
        // Получение текущего пароля
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($oldPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Неверный текущий пароль'];
        }
        
        // Обновление пароля
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$passwordHash, $userId]);
        
        return ['success' => true, 'message' => 'Пароль успешно изменён'];
    } catch (Exception $e) {
        logError('Ошибка смены пароля: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Ошибка сервера'];
    }
}

/**
 * Обновление профиля пользователя
 */
function updateProfile($userId, $fullName, $email, $position, $notifyEmail) {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->prepare("
            UPDATE users 
            SET full_name = ?, email = ?, position = ?, notify_email = ?
            WHERE id = ?
        ");
        $stmt->execute([$fullName, $email, $position, $notifyEmail ? 1 : 0, $userId]);
        
        return ['success' => true, 'message' => 'Профиль успешно обновлён'];
    } catch (Exception $e) {
        logError('Ошибка обновления профиля: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Ошибка сервера'];
    }
}
