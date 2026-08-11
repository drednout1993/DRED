<?php
/**
 * Выход из системы
 */

define('ACCESS_GRANTED', true);
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';

logout();
header('Location: login.php');
exit;
