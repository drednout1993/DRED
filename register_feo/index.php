<?php
/**
 * Главный файл инициализации (точка входа)
 */

define('ACCESS_GRANTED', true);

require_once __DIR__ . '/includes/init.php';

// Перенаправление на дашборд если авторизован
if (isUserLoggedIn()) {
    header('Location: pages/dashboard.php');
} else {
    header('Location: login.php');
}
exit;
