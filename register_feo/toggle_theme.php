<?php
/**
 * Переключение темы
 */

define('ACCESS_GRANTED', true);
require_once __DIR__ . '/includes/init.php';

// Переключение темы
if (isset($_SESSION['theme']) && $_SESSION['theme'] === 'dark') {
    $_SESSION['theme'] = 'light';
} else {
    $_SESSION['theme'] = 'dark';
}

// Возврат на предыдущую страницу
$referer = $_SERVER['HTTP_REFERER'] ?? 'pages/dashboard.php';
header('Location: ' . $referer);
exit;
