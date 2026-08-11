<?php
/**
 * Боковая навигация
 */

// Защита от прямого доступа
if (!defined('ACCESS_GRANTED')) {
    http_response_code(403);
    die('Доступ запрещён');
}

$user = getCurrentUser();
$role = $user ? $user['role'] : null;
$currentPath = basename($_SERVER['PHP_SELF']);
?>

<nav class="sidebar <?= isset($_SESSION['theme']) && $_SESSION['theme'] === 'dark' ? 'sidebar-dark' : 'sidebar-light' ?>">
    <div class="text-center mb-4">
        <h5 class="fw-bold">
            <i class="bi bi-folder2-open"></i> Реестры ФЭО
        </h5>
    </div>

    <ul class="nav flex-column">
        <?php if ($user): ?>
            <!-- Дашборд -->
            <li class="nav-item">
                <a class="nav-link <?= $currentPath === 'dashboard.php' ? 'active' : '' ?>" href="pages/dashboard.php">
                    <i class="bi bi-speedometer2"></i> Дашборд
                </a>
            </li>

            <!-- Создание реестра -->
            <li class="nav-item">
                <a class="nav-link <?= $currentPath === 'register_create.php' ? 'active' : '' ?>" href="pages/register_create.php">
                    <i class="bi bi-plus-circle"></i> Создать реестр
                </a>
            </li>

            <!-- Список реестров -->
            <li class="nav-item">
                <a class="nav-link <?= $currentPath === 'register_list.php' ? 'active' : '' ?>" href="pages/register_list.php">
                    <i class="bi bi-list-ul"></i> Все реестры
                </a>
            </li>

            <?php if ($role === 'economist' || $role === 'admin'): ?>
                <!-- Журнал -->
                <li class="nav-item">
                    <a class="nav-link <?= $currentPath === 'journal.php' ? 'active' : '' ?>" href="pages/journal.php">
                        <i class="bi bi-journal-text"></i> Журнал
                    </a>
                </li>

                <!-- Статистика -->
                <li class="nav-item">
                    <a class="nav-link <?= $currentPath === 'stats.php' ? 'active' : '' ?>" href="pages/stats.php">
                        <i class="bi bi-graph-up"></i> Статистика
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($role === 'admin'): ?>
                <!-- Администрирование -->
                <li class="nav-item">
                    <a class="nav-link <?= $currentPath === 'admin_users.php' ? 'active' : '' ?>" href="pages/admin_users.php">
                        <i class="bi bi-people"></i> Пользователи
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $currentPath === 'admin_settings.php' ? 'active' : '' ?>" href="pages/admin_settings.php">
                        <i class="bi bi-gear"></i> Настройки
                    </a>
                </li>
            <?php endif; ?>

            <!-- Профиль -->
            <li class="nav-item">
                <a class="nav-link <?= $currentPath === 'profile.php' ? 'active' : '' ?>" href="pages/profile.php">
                    <i class="bi bi-person-circle"></i> Профиль
                </a>
            </li>

            <!-- Переключатель темы -->
            <li class="nav-item">
                <a class="nav-link" href="toggle_theme.php" onclick="toggleTheme(); return false;">
                    <i class="bi bi-moon-stars"></i> Тема
                </a>
            </li>

            <!-- Помощь -->
            <li class="nav-item">
                <a class="nav-link" href="<?= e(defined('IT_HELP_URL') ? IT_HELP_URL : '#') ?>" target="_blank">
                    <i class="bi bi-question-circle"></i> Помощь
                </a>
            </li>

            <!-- Выход -->
            <li class="nav-item mt-3">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Выход
                </a>
            </li>
        <?php else: ?>
            <li class="nav-item">
                <a class="nav-link" href="login.php">
                    <i class="bi bi-box-arrow-in-right"></i> Вход
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="pages/register.php">
                    <i class="bi bi-person-plus"></i> Регистрация
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer text-center">
        <small>Разработано для ФЭО</small>
    </div>
</nav>
