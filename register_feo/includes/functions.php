<?php
/**
 * Вспомогательные функции системы "Реестры ФЭО"
 */

// Защита от прямого доступа
if (!defined('ACCESS_GRANTED')) {
    http_response_code(403);
    die('Доступ запрещён');
}

/**
 * Безопасный вывод данных
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Показ страницы ошибки
 */
function showErrorPage($code = 500, $message = '') {
    http_response_code($code);
    
    $titles = [
        403 => 'Доступ запрещён',
        404 => 'Страница не найдена',
        500 => 'Внутренняя ошибка сервера'
    ];
    
    $title = $titles[$code] ?? 'Ошибка';
    
    if (!empty($message)) {
        logError("Ошибка $code: $message");
    }
    
    include __DIR__ . '/../pages/error.php';
    exit;
}

/**
 * Перенаправление с сообщением
 */
function redirectWithMessage($url, $message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: ' . $url);
    exit;
}

/**
 * Получение и очистка сообщения из сессии
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * Генерация номера реестра
 */
function generateRegisterNumber($pdo) {
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(register_number, 3) AS UNSIGNED)) as max_num FROM registers WHERE register_number IS NOT NULL");
    $row = $stmt->fetch();
    $nextNum = ($row['max_num'] ?? 0) + 1;
    return 'R-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

/**
 * Отправка email уведомления
 */
function sendEmailNotification($to, $subject, $body) {
    if (!defined('SMTP_HOST') || empty(SMTP_HOST)) {
        return false;
    }
    
    // Простая реализация через mail() с заголовками
    // Для полноценной SMTP поддержки требуется PHPMailer или аналог
    $headers = [
        'From: ' . SMTP_FROM,
        'Reply-To: ' . SMTP_FROM,
        'X-Mailer: PHP/' . phpversion(),
        'Content-Type: text/html; charset=UTF-8'
    ];
    
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

/**
 * Уведомление экономистов о новом реестре
 */
function notifyEconomistsAboutNewRegister($registerId) {
    try {
        $pdo = getDbConnection();
        
        // Получаем реестр
        $stmt = $pdo->prepare("SELECT r.*, u.full_name as author_name FROM registers r JOIN users u ON r.user_id = u.id WHERE r.id = ?");
        $stmt->execute([$registerId]);
        $register = $stmt->fetch();
        
        if (!$register) {
            return false;
        }
        
        // Получаем экономистов с включёнными уведомлениями
        $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'economist' AND notify_email = 1");
        $stmt->execute();
        $economists = $stmt->fetchAll();
        
        foreach ($economists as $economist) {
            $subject = 'Новый реестр на проверку от ' . e($register['author_name']);
            $body = "
                <h2>Новый реестр на проверку</h2>
                <p><strong>Автор:</strong> " . e($register['author_name']) . "</p>
                <p><strong>Передающий:</strong> " . e($register['transmitted_name']) . "</p>
                <p><strong>Дата создания:</strong> " . formatDate($register['created_at']) . "</p>
                <p><a href='" . getBaseUrl() . "/pages/register_view.php?id=" . $registerId . "'>Перейти к реестру</a></p>
            ";
            
            sendEmailNotification($economist['email'], $subject, $body);
        }
        
        return true;
    } catch (Exception $e) {
        logError('Ошибка отправки уведомлений: ' . $e->getMessage());
        return false;
    }
}

/**
 * Форматирование даты
 */
function formatDate($dateString) {
    if (empty($dateString)) {
        return '';
    }
    $date = new DateTime($dateString);
    return $date->format('d.m.Y');
}

/**
 * Форматирование даты и времени
 */
function formatDateTime($dateString) {
    if (empty($dateString)) {
        return '';
    }
    $date = new DateTime($dateString);
    return $date->format('d.m.Y H:i');
}

/**
 * Статус реестра в текст
 */
function getStatusText($status) {
    $statuses = [
        'draft' => 'Черновик',
        'new' => 'Новый',
        'in_review' => 'На проверке',
        'revision' => 'На доработке',
        'accepted' => 'Принят',
        'deleted' => 'Удалён'
    ];
    return $statuses[$status] ?? $status;
}

/**
 * Класс статуса для Bootstrap
 */
function getStatusClass($status) {
    $classes = [
        'draft' => 'secondary',
        'new' => 'info',
        'in_review' => 'warning',
        'revision' => 'orange',
        'accepted' => 'success',
        'deleted' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

/**
 * Получение базового URL
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || 
                 (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $basePath = dirname($_SERVER['SCRIPT_NAME']);
    
    // Убираем /includes из пути
    $basePath = str_replace('/includes', '', $basePath);
    
    return $protocol . '://' . $host . $basePath;
}

/**
 * Проверка файла на допустимый тип
 */
function isValidFileType($fileType) {
    $allowedTypes = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/png'
    ];
    return in_array($fileType, $allowedTypes);
}

/**
 * Генерация безопасного имени файла
 */
function generateSafeFileName($originalName) {
    $info = pathinfo($originalName);
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $info['filename']);
    $extension = isset($info['extension']) ? '.' . $info['extension'] : '';
    return uniqid() . '_' . $filename . $extension;
}

/**
 * Пагинация
 */
function getPagination($currentPage, $totalItems, $itemsPerPage = 20) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    return [
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'total_items' => $totalItems,
        'items_per_page' => $itemsPerPage,
        'start_page' => $startPage,
        'end_page' => $endPage,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * Экспорт в CSV
 */
function exportToCsv($data, $filename = 'export.csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // BOM для корректного отображения кириллицы в Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    foreach ($data as $row) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit;
}

/**
 * Проверка блокировки пользователя после неудачных попыток входа
 */
function isLoginBlocked($login) {
    try {
        $pdo = getDbConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Проверяем количество неудачных попыток за последние 15 минут
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE login = ? 
            AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$login]);
        $result = $stmt->fetch();
        
        return $result['attempts'] >= 5;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Запись попытки входа
 */
function recordLoginAttempt($login, $success) {
    try {
        $pdo = getDbConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        if (!$success) {
            $stmt = $pdo->prepare("
                INSERT INTO login_attempts (login, ip_address, attempted_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$login, $ip]);
        } else {
            // Очищаем записи об неудачных попытках при успешном входе
            $stmt = $pdo->prepare("
                DELETE FROM login_attempts 
                WHERE login = ?
            ");
            $stmt->execute([$login]);
        }
    } catch (Exception $e) {
        logError('Ошибка записи попытки входа: ' . $e->getMessage());
    }
}

/**
 * Очистка старых попыток входа
 */
function cleanupLoginAttempts() {
    try {
        $pdo = getDbConnection();
        $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    } catch (Exception $e) {
        // Игнорируем ошибки очистки
    }
}

/**
 * Получение соединения с БД
 */
function getDbConnection() {
    global $pdo;
    if (!isset($pdo)) {
        throw new Exception('Database connection not initialized');
    }
    return $pdo;
}

/**
 * Логирование ошибок
 */
function logError($message) {
    $logFile = __DIR__ . '/../logs/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    error_log($logMessage, 3, $logFile);
}

/**
 * Проверка CSRF токена
 */
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Генерация CSRF токена для формы
 */
function generateCsrfInput() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
}

/**
 * Проверка прав доступа
 */
function checkRole($allowedRoles) {
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    if (!isLoggedIn()) {
        redirectWithMessage('/login.php', 'Требуется авторизация', 'warning');
    }
    
    $userRole = $_SESSION['user_role'] ?? '';
    if (!in_array($userRole, $allowedRoles)) {
        showErrorPage(403, 'Недостаточно прав для выполнения операции');
    }
}

/**
 * Проверка авторства реестра
 */
function isRegisterAuthor($registerId) {
    if (!isLoggedIn()) {
        return false;
    }
    
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT user_id FROM registers WHERE id = ?");
        $stmt->execute([$registerId]);
        $register = $stmt->fetch();
        
        return $register && $register['user_id'] == $_SESSION['user_id'];
    } catch (Exception $e) {
        logError('Ошибка проверки авторства: ' . $e->getMessage());
        return false;
    }
}

/**
 * Безопасное получение данных из POST
 */
function post($key, $default = '') {
    return $_POST[$key] ?? $default;
}

/**
 * Безопасное получение данных из GET
 */
function get($key, $default = '') {
    return $_GET[$key] ?? $default;
}

/**
 * Валидация email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Обрезка строки до определённой длины
 */
function truncateString($string, $length = 50, $suffix = '...') {
    if (mb_strlen($string) <= $length) {
        return $string;
    }
    return mb_substr($string, 0, $length) . $suffix;
}
