<?php
/**
 * Установщик системы "Реестры ФЭО"
 * Многошаговый мастер установки
 */

session_start();

// Если уже установлен - перенаправляем
if (file_exists(__DIR__ . '/includes/config.php')) {
    die('Система уже установлена. Удалите файл includes/config.php для повторной установки.');
}

$step = $_GET['step'] ?? 1;
$errors = [];
$success = '';

// Обработка шагов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step'])) {
        $step = (int)$_POST['step'];
        
        switch ($step) {
            case 1:
                // Шаг 1: Настройки БД
                $dbDriver = $_POST['db_driver'] ?? 'mysql';
                $dbHost = $_POST['db_host'] ?? 'localhost';
                $dbPort = $_POST['db_port'] ?? ($dbDriver === 'pgsql' ? '5432' : '3306');
                $dbName = $_POST['db_name'] ?? '';
                $dbUser = $_POST['db_user'] ?? '';
                $dbPass = $_POST['db_pass'] ?? '';
                
                // Валидация имени БД
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
                    $errors[] = 'Имя базы данных содержит недопустимые символы';
                }
                
                if (empty($errors)) {
                    // Попытка подключения
                    try {
                        $dsn = "$dbDriver:host=$dbHost;port=$dbPort;charset=utf8mb4";
                        $pdo = new PDO($dsn, $dbUser, $dbPass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                        ]);
                        
                        // Создание БД
                        $quotedDbName = $dbDriver === 'mysql' ? "`$dbName`" : "\"$dbName\"";
                        
                        if ($dbDriver === 'mysql') {
                            $pdo->exec("CREATE DATABASE IF NOT EXISTS $quotedDbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        } else {
                            $pdo->exec("CREATE DATABASE $quotedDbName");
                        }
                        
                        // Сохранение в сессию
                        $_SESSION['install'] = [
                            'db_driver' => $dbDriver,
                            'db_host' => $dbHost,
                            'db_port' => $dbPort,
                            'db_name' => $dbName,
                            'db_user' => $dbUser,
                            'db_pass' => $dbPass
                        ];
                        
                        $step = 2;
                    } catch (PDOException $e) {
                        $errors[] = 'Ошибка подключения к БД: ' . $e->getMessage();
                    }
                }
                break;
                
            case 2:
                // Шаг 2: Администратор и SMTP
                $adminLogin = $_POST['admin_login'] ?? '';
                $adminPassword = $_POST['admin_password'] ?? '';
                $adminFullName = $_POST['admin_full_name'] ?? '';
                $adminEmail = $_POST['admin_email'] ?? '';
                
                $smtpHost = $_POST['smtp_host'] ?? '';
                $smtpPort = $_POST['smtp_port'] ?? '587';
                $smtpSecurity = $_POST['smtp_security'] ?? 'tls';
                $smtpUser = $_POST['smtp_user'] ?? '';
                $smtpPass = $_POST['smtp_pass'] ?? '';
                $smtpFrom = $_POST['smtp_from'] ?? '';
                
                $itHelpUrl = $_POST['it_help_url'] ?? '';
                $orderDetails = $_POST['order_details'] ?? '';
                $journalOrderDetails = $_POST['journal_order_details'] ?? '';
                
                // Валидация
                if (strlen($adminLogin) < 3) {
                    $errors[] = 'Логин администратора должен быть не менее 3 символов';
                }
                if (strlen($adminPassword) < 6) {
                    $errors[] = 'Пароль администратора должен быть не менее 6 символов';
                }
                if (empty($adminFullName)) {
                    $errors[] = 'Укажите ФИО администратора';
                }
                if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Некорректный email администратора';
                }
                
                if (empty($errors)) {
                    $_SESSION['install']['admin'] = [
                        'login' => $adminLogin,
                        'password' => $adminPassword,
                        'full_name' => $adminFullName,
                        'email' => $adminEmail
                    ];
                    
                    $_SESSION['install']['smtp'] = [
                        'host' => $smtpHost,
                        'port' => $smtpPort,
                        'security' => $smtpSecurity,
                        'user' => $smtpUser,
                        'pass' => $smtpPass,
                        'from' => $smtpFrom
                    ];
                    
                    $_SESSION['install']['settings'] = [
                        'it_help_url' => $itHelpUrl,
                        'order_details' => $orderDetails,
                        'journal_order_details' => $journalOrderDetails
                    ];
                    
                    $step = 3;
                }
                break;
                
            case 3:
                // Шаг 3: Демо данные и финальная установка
                $createDemo = isset($_POST['create_demo']);
                
                if (performInstallation($createDemo)) {
                    $step = 4;
                } else {
                    $errors[] = 'Ошибка при установке. Проверьте логи.';
                }
                break;
        }
    }
}

/**
 * Выполнение установки
 */
function performInstallation($createDemo = false) {
    $install = $_SESSION['install'] ?? [];
    
    if (empty($install)) {
        return false;
    }
    
    try {
        $dbDriver = $install['db_driver'];
        $dsn = "$dbDriver:host={$install['db_host']};port={$install['db_port']};dbname={$install['db_name']};charset=utf8mb4";
        
        $pdo = new PDO($dsn, $install['db_user'], $install['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // Создание таблиц
        createTables($pdo, $dbDriver);
        
        // Создание администратора
        createAdmin($pdo, $install['admin']);
        
        // Демо данные
        if ($createDemo) {
            createDemoData($pdo);
        }
        
        // Создание config.php
        createConfigFile($install);
        
        // Сохраняем данные администратора для отображения на финальном экране
        $_SESSION['install_complete'] = [
            'admin_login' => $install['admin']['login'],
            'admin_email' => $install['admin']['email']
        ];
        
        // Очищаем сессию установки
        unset($_SESSION['install']);
        
        return true;
    } catch (Exception $e) {
        error_log('Install error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Создание таблиц
 */
function createTables($pdo, $driver) {
    if ($driver === 'mysql') {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    notify_email TINYINT DEFAULT 1,
    position VARCHAR(255),
    priority INT DEFAULT 0,
    role ENUM('user', 'economist', 'admin') NOT NULL DEFAULT 'user',
    is_blocked TINYINT DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX idx_login (login),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('draft', 'new', 'in_review', 'revision', 'accepted', 'deleted') NOT NULL DEFAULT 'draft',
    register_number VARCHAR(10),
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    sent_at DATETIME,
    reviewed_at DATETIME,
    accepted_at DATETIME,
    transmitted_name VARCHAR(255),
    transmitted_position VARCHAR(255),
    accepted_name VARCHAR(255),
    accepted_position VARCHAR(255),
    receive_date_feo DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_user (user_id),
    INDEX idx_number (register_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS register_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    register_id INT NOT NULL,
    item_order INT NOT NULL,
    doc_name VARCHAR(500) NOT NULL,
    doc_number VARCHAR(100) NOT NULL,
    doc_date DATE NOT NULL,
    contract_details VARCHAR(500),
    notes TEXT,
    FOREIGN KEY (register_id) REFERENCES registers(id) ON DELETE CASCADE,
    INDEX idx_register (register_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS register_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    register_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (register_id) REFERENCES registers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_register (register_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS register_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    register_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_at DATETIME NOT NULL,
    FOREIGN KEY (register_id) REFERENCES registers(id) ON DELETE CASCADE,
    INDEX idx_register (register_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL,
    INDEX idx_login_attempt (login, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    } else {
        // PostgreSQL
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    login VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    notify_email SMALLINT DEFAULT 1,
    position VARCHAR(255),
    priority INTEGER DEFAULT 0,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    is_blocked SMALLINT DEFAULT 0,
    created_at TIMESTAMP NOT NULL
);

CREATE TABLE IF NOT EXISTS registers (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    register_number VARCHAR(10),
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP,
    sent_at TIMESTAMP,
    reviewed_at TIMESTAMP,
    accepted_at TIMESTAMP,
    transmitted_name VARCHAR(255),
    transmitted_position VARCHAR(255),
    accepted_name VARCHAR(255),
    accepted_position VARCHAR(255),
    receive_date_feo DATE
);

CREATE TABLE IF NOT EXISTS register_items (
    id SERIAL PRIMARY KEY,
    register_id INTEGER NOT NULL REFERENCES registers(id) ON DELETE CASCADE,
    item_order INTEGER NOT NULL,
    doc_name VARCHAR(500) NOT NULL,
    doc_number VARCHAR(100) NOT NULL,
    doc_date DATE NOT NULL,
    contract_details VARCHAR(500),
    notes TEXT
);

CREATE TABLE IF NOT EXISTS register_comments (
    id SERIAL PRIMARY KEY,
    register_id INTEGER NOT NULL REFERENCES registers(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    comment TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL
);

CREATE TABLE IF NOT EXISTS register_files (
    id SERIAL PRIMARY KEY,
    register_id INTEGER NOT NULL REFERENCES registers(id) ON DELETE CASCADE,
    file_path VARCHAR(500) NOT NULL,
    uploaded_at TIMESTAMP NOT NULL
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id SERIAL PRIMARY KEY,
    login VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_users_login ON users(login);
CREATE INDEX IF NOT EXISTS idx_registers_status ON registers(status);
CREATE INDEX IF NOT EXISTS idx_login_attempts ON login_attempts(login, attempted_at);
SQL;
    }
    
    $pdo->exec($sql);
}

/**
 * Создание администратора
 */
function createAdmin($pdo, $admin) {
    $passwordHash = password_hash($admin['password'], PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (login, password, full_name, email, role, created_at)
        VALUES (?, ?, ?, ?, 'admin', NOW())
    ");
    $stmt->execute([
        $admin['login'],
        $passwordHash,
        $admin['full_name'],
        $admin['email']
    ]);
}

/**
 * Создание демо данных
 */
function createDemoData($pdo) {
    // Пользователь
    $stmt = $pdo->prepare("
        INSERT INTO users (login, password, full_name, email, position, role, created_at)
        VALUES (?, ?, ?, ?, ?, 'user', NOW())
    ");
    $stmt->execute([
        'user1',
        password_hash('password', PASSWORD_BCRYPT),
        'Иванов Иван Иванович',
        'user1@example.local',
        'Менеджер'
    ]);
    
    // Экономист
    $stmt = $pdo->prepare("
        INSERT INTO users (login, password, full_name, email, position, role, created_at)
        VALUES (?, ?, ?, ?, ?, 'economist', NOW())
    ");
    $stmt->execute([
        'econom1',
        password_hash('password', PASSWORD_BCRYPT),
        'Петрова Анна Сергеевна',
        'econom1@example.local',
        'Экономист'
    ]);
    
    // Получаем ID созданного пользователя для реестров
    $userId = $pdo->query("SELECT id FROM users WHERE login = 'user1'")->fetchColumn();
    
    // Демо реестр 1 (принятый)
    $stmt = $pdo->prepare("
        INSERT INTO registers (user_id, status, register_number, created_at, sent_at, reviewed_at, accepted_at, 
                               transmitted_name, transmitted_position, accepted_name, accepted_position, receive_date_feo)
        VALUES (?, 'accepted', 'R-0001', NOW(), NOW(), NOW(), NOW(), ?, ?, ?, ?, CURDATE())
    ");
    $stmt->execute([
        $userId,
        'Иванов И.И.',
        'Менеджер',
        'Петрова А.С.',
        'Экономист'
    ]);
    
    $registerId = $pdo->lastInsertId();
    
    // Позиции реестра 1
    $stmt = $pdo->prepare("
        INSERT INTO register_items (register_id, item_order, doc_name, doc_number, doc_date, contract_details, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$registerId, 1, 'Акт выполненных работ №1', 'АВР-001', '2024-01-15', 'Договор №123 от 10.01.2024', '']);
    $stmt->execute([$registerId, 2, 'Счет на оплату №456', 'СЧ-456', '2024-01-16', 'Договор №123 от 10.01.2024', '']);
    
    // Демо реестр 2 (на проверке)
    $stmt = $pdo->prepare("
        INSERT INTO registers (user_id, status, created_at, sent_at, transmitted_name, transmitted_position)
        VALUES (?, 'new', NOW(), NOW(), ?, ?)
    ");
    $stmt->execute([
        $userId,
        'Иванов И.И.',
        'Менеджер'
    ]);
    
    $registerId2 = $pdo->lastInsertId();
    $stmt->execute([$registerId2, 1, 'Накладная №789', 'НАК-789', '2024-02-01', 'Договор №456 от 25.01.2024', 'Срочно']);
}

/**
 * Создание файла конфигурации
 */
function createConfigFile($install) {
    $config = <<<PHP
<?php
/**
 * Конфигурация системы "Реестры ФЭО"
 * Сгенерировано установщиком
 */

// Защита от прямого доступа
if (!defined('ACCESS_GRANTED')) {
    http_response_code(403);
    die('Доступ запрещён');
}

// База данных
define('DB_DRIVER', '{$install['db_driver']}');
define('DB_HOST', '{$install['db_host']}');
define('DB_PORT', '{$install['db_port']}');
define('DB_NAME', '{$install['db_name']}');
define('DB_USER', '{$install['db_user']}');
define('DB_PASS', '{$install['db_pass']}');

// SMTP
define('SMTP_HOST', '{$install['smtp']['host']}');
define('SMTP_PORT', '{$install['smtp']['port']}');
define('SMTP_SECURITY', '{$install['smtp']['security']}');
define('SMTP_USER', '{$install['smtp']['user']}');
define('SMTP_PASS', '{$install['smtp']['pass']}');
define('SMTP_FROM', '{$install['smtp']['from']}');

// Настройки
define('IT_HELP_URL', '{$install['settings']['it_help_url']}');
define('ORDER_DETAILS', '{$install['settings']['order_details']}');
define('JOURNAL_ORDER_DETAILS', '{$install['settings']['journal_order_details']}');
PHP;
    
    file_put_contents(__DIR__ . '/includes/config.php', $config);
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Установка - Реестры ФЭО</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-gear"></i> Установка системы "Реестры ФЭО"</h4>
                    </div>
                    <div class="card-body p-4">
                        <!-- Индикатор шагов -->
                        <div class="progress mb-4" style="height: 10px;">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: <?= $step * 33 ?>%" 
                                 aria-valuenow="<?= $step * 33 ?>" aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                        
                        <ul class="nav nav-pills mb-4">
                            <li class="nav-item">
                                <span class="nav-link <?= $step >= 1 ? 'active' : '' ?>">1. База данных</span>
                            </li>
                            <li class="nav-item">
                                <span class="nav-link <?= $step >= 2 ? 'active' : '' ?>">2. Администратор</span>
                            </li>
                            <li class="nav-item">
                                <span class="nav-link <?= $step >= 3 ? 'active' : '' ?>">3. Завершение</span>
                            </li>
                        </ul>

                        <?php if ($errors): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= e($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($step === 1): ?>
                            <!-- Шаг 1: База данных -->
                            <form method="POST">
                                <input type="hidden" name="step" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label">Тип СУБД</label>
                                    <select class="form-select" name="db_driver" required>
                                        <option value="mysql">MySQL / MariaDB</option>
                                        <option value="pgsql">PostgreSQL</option>
                                    </select>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">Хост</label>
                                        <input type="text" class="form-control" name="db_host" value="localhost" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Порт</label>
                                        <input type="text" class="form-control" name="db_port" value="3306">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Имя базы данных</label>
                                    <input type="text" class="form-control" name="db_name" value="registers_feo" required>
                                    <small class="text-muted">База данных будет создана автоматически</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Пользователь БД</label>
                                    <input type="text" class="form-control" name="db_user" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Пароль БД</label>
                                    <input type="password" class="form-control" name="db_pass">
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-arrow-right"></i> Далее
                                </button>
                            </form>
                            
                        <?php elseif ($step === 2): ?>
                            <!-- Шаг 2: Администратор и SMTP -->
                            <form method="POST">
                                <input type="hidden" name="step" value="2">
                                
                                <h5 class="mb-3">Учётная запись администратора</h5>
                                
                                <div class="mb-3">
                                    <label class="form-label">Логин</label>
                                    <input type="text" class="form-control" name="admin_login" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Пароль</label>
                                    <input type="password" class="form-control" name="admin_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">ФИО</label>
                                    <input type="text" class="form-control" name="admin_full_name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="admin_email" required>
                                </div>
                                
                                <hr class="my-4">
                                
                                <h5 class="mb-3">Настройки SMTP (опционально)</h5>
                                
                                <div class="mb-3">
                                    <label class="form-label">SMTP сервер</label>
                                    <input type="text" class="form-control" name="smtp_host" placeholder="smtp.example.local">
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Порт</label>
                                        <input type="text" class="form-control" name="smtp_port" value="587">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Безопасность</label>
                                        <select class="form-select" name="smtp_security">
                                            <option value="">Нет</option>
                                            <option value="tls">TLS</option>
                                            <option value="ssl">SSL</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">SMTP логин</label>
                                    <input type="text" class="form-control" name="smtp_user">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">SMTP пароль</label>
                                    <input type="password" class="form-control" name="smtp_pass">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email отправителя</label>
                                    <input type="email" class="form-control" name="smtp_from" placeholder="noreply@example.local">
                                </div>
                                
                                <hr class="my-4">
                                
                                <h5 class="mb-3">Дополнительные настройки</h5>
                                
                                <div class="mb-3">
                                    <label class="form-label">URL IT-помощи</label>
                                    <input type="url" class="form-control" name="it_help_url" placeholder="https://helpdesk.example.local">
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-arrow-right"></i> Далее
                                </button>
                            </form>
                            
                        <?php elseif ($step === 3): ?>
                            <!-- Шаг 3: Демо данные -->
                            <form method="POST">
                                <input type="hidden" name="step" value="3">
                                
                                <div class="text-center mb-4">
                                    <i class="bi bi-check-circle display-4 text-success"></i>
                                    <h5 class="mt-3">Готово к установке</h5>
                                    <p class="text-muted">Проверьте параметры и нажмите "Установить"</p>
                                </div>
                                
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <h6>Будет создано:</h6>
                                        <ul class="mb-0">
                                            <li>База данных: <strong><?= e($_SESSION['install']['db_name']) ?></strong></li>
                                            <li>Администратор: <strong><?= e($_SESSION['install']['admin']['login']) ?></strong></li>
                                            <li>Все необходимые таблицы</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="create_demo" id="createDemo">
                                    <label class="form-check-label" for="createDemo">
                                        Создать демо-данные (пользователь user1/password, экономист econom1/password)
                                    </label>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-download"></i> Установить
                                </button>
                            </form>
                            
                        <?php elseif ($step === 4): ?>
                            <!-- Шаг 4: Завершение -->
                            <div class="text-center py-4">
                                <i class="bi bi-check-circle-fill display-1 text-success"></i>
                                <h4 class="mt-3">Установка завершена!</h4>
                                <p class="text-muted">Система "Реестры ФЭО" готова к работе.</p>
                                
                                <div class="alert alert-info mt-4">
                                    <strong>Логин администратора:</strong> <?= e($_SESSION['install_complete']['admin_login'] ?? 'admin') ?><br>
                                    <strong>Email:</strong> <?= e($_SESSION['install_complete']['admin_email'] ?? '') ?><br>
                                    <strong>Пароль:</strong> используйте заданный при установке
                                </div>
                                
                                <a href="login.php" class="btn btn-primary mt-3">
                                    <i class="bi bi-box-arrow-in-right"></i> Перейти ко входу
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
