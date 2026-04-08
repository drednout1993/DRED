-- Создание базы данных
-- Система учёта реестров документов

CREATE DATABASE IF NOT EXISTS reestr_system 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE reestr_system;

-- Таблица пользователей
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    fio VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    position VARCHAR(255) NULL,
    department VARCHAR(255) DEFAULT 'Финансово-экономический отдел',
    role ENUM('admin', 'financier', 'head', 'user') NOT NULL DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица реестров
CREATE TABLE IF NOT EXISTS registers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reg_code VARCHAR(10) UNIQUE NULL,
    register_date DATE NOT NULL,
    author_type ENUM('user', 'anonymous') DEFAULT 'user',
    author_id INT NULL,
    sender_fio VARCHAR(255) NOT NULL,
    sender_position VARCHAR(255) NOT NULL,
    receiver_fio VARCHAR(255) NOT NULL,
    receiver_position VARCHAR(255) NOT NULL,
    status ENUM('draft', 'submitted', 'in_review', 'returned', 'accepted') NOT NULL DEFAULT 'draft',
    return_comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL,
    accepted_at TIMESTAMP NULL,
    
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_status (status),
    INDEX idx_reg_code (reg_code),
    INDEX idx_author_id (author_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица позиций реестра
CREATE TABLE IF NOT EXISTS register_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    register_id INT NOT NULL,
    item_index INT NOT NULL,
    document_name VARCHAR(500) NOT NULL,
    document_number VARCHAR(100) NOT NULL,
    document_date DATE NOT NULL,
    contract_info VARCHAR(255) NOT NULL,
    note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (register_id) REFERENCES registers(id) ON DELETE CASCADE,
    
    INDEX idx_register_id (register_id),
    INDEX idx_item_index (item_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица вложений
CREATE TABLE IF NOT EXISTS attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    register_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(50) NULL,
    storage ENUM('local', 'server') DEFAULT 'local',
    uploaded_by INT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (register_id) REFERENCES registers(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_register_id (register_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица истории изменений
CREATE TABLE IF NOT EXISTS register_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    register_id INT NOT NULL,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    old_status ENUM('draft', 'submitted', 'in_review', 'returned', 'accepted') NULL,
    new_status ENUM('draft', 'submitted', 'in_review', 'returned', 'accepted') NULL,
    comment TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (register_id) REFERENCES registers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_register_id (register_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица настроек системы
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NULL,
    setting_type VARCHAR(50) DEFAULT 'string',
    description VARCHAR(500) NULL,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,
    
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Базовые настройки системы
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('next_reg_number', '1000', 'integer', 'Следующий номер для генерации кода реестра'),
('max_attachments', '5', 'integer', 'Максимальное количество вложений'),
('email_notifications', '1', 'boolean', 'Включить email уведомления'),
('default_pdf_copies', '1', 'integer', 'Количество копий при печати по умолчанию');

-- Создание пользователя администратора (пароль: admin123)
-- Хэш пароля генерируется через bcrypt с rounds=12
INSERT INTO users (username, password_hash, fio, role, is_active) VALUES
('admin', '$2b$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5GyYzS3MebAJu', 'Администратор Системы', 'admin', TRUE);
