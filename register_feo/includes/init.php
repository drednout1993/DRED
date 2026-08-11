<?php
/**
 * Инициализация системы "Реестры ФЭО"
 * Подключение конфигурации, сессии, базовых функций
 */

// Защита от прямого доступа
if (!defined('ACCESS_GRANTED')) {
    http_response_code(403);
    die('Доступ запрещён');
}

// Настройки безопасности сессии
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
session_name('FEO_SESSION');

// Запуск сессии
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Проверка существования конфигурации
if (!file_exists(__DIR__ . '/config.php')) {
    // Перенаправление на установщик
    if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
        header('Location: install.php');
        exit;
    }
} else {
    require_once __DIR__ . '/config.php';
}

// Подключение функций
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';

// Установка локали
setlocale(LC_ALL, 'ru_RU.UTF-8');
date_default_timezone_set('Europe/Moscow');

// Подключение к базе данных
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        if (!defined('DB_DRIVER')) {
            throw new Exception('База данных не настроена. Пройдите установку.');
        }
        
        try {
            if (DB_DRIVER === 'mysql') {
                $dsn = DB_DRIVER . ':host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            } elseif (DB_DRIVER === 'pgsql') {
                $dsn = DB_DRIVER . ':host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME;
            } else {
                throw new Exception('Неподдерживаемый драйвер БД');
            }
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Ошибка подключения к БД: ' . $e->getMessage());
            throw new Exception('Ошибка подключения к базе данных');
        }
    }
    
    return $pdo;
}

// Проверка авторизации
function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }
    // Обновляем данные пользователя в сессии
    getCurrentUser();
}

// Проверка роли
function requireRole($roles) {
    requireAuth();
    
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    $userRole = getCurrentUserRole();
    if (!$userRole || !in_array($userRole, $roles)) {
        showErrorPage(403, 'Доступ запрещён');
        exit;
    }
}

// Получение текущего пользователя
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    static $user = null;
    
    if ($user === null) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_blocked = 0");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user) {
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_full_name'] = $user['full_name'];
            }
        } catch (Exception $e) {
            return null;
        }
    }
    
    return $user;
}

// Проверка входа
function isLoggedIn() {
    return isset($_SESSION['user_id']) && getCurrentUser() !== null;
}

// Получение роли текущего пользователя
function getCurrentUserRole() {
    $user = getCurrentUser();
    return $user ? $user['role'] : null;
}

// Генерация CSRF токена
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Проверка CSRF токена
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Получение CSRF токена для формы
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

// Логирование ошибок
function logError($message, $context = []) {
    $logFile = dirname(__DIR__) . '/logs/error.log';
    
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    $logEntry = sprintf(
        "[%s] %s - %s - %s\n",
        date('Y-m-d H:i:s'),
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $message,
        json_encode($context)
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Обработка ошибок
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    logError($message, ['file' => $file, 'line' => $line, 'severity' => $severity]);
    
    if (defined('ACCESS_GRANTED')) {
        showErrorPage(500, 'Внутренняя ошибка сервера');
    }
    
    return true;
});

set_exception_handler(function($exception) {
    logError($exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    
    if (defined('ACCESS_GRANTED')) {
        showErrorPage(500, 'Внутренняя ошибка сервера');
    } else {
        echo '<h1>Ошибка</h1><p>' . htmlspecialchars($exception->getMessage()) . '</p>';
    }
});
