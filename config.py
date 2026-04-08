"""
Конфигурация приложения
Система учёта реестров документов
"""

import os
from pathlib import Path

# Базовый путь к директории проекта
BASE_DIR = Path(__file__).resolve().parent

# База данных
DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'port': int(os.getenv('DB_PORT', 3306)),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'database': os.getenv('DB_NAME', 'reestr_system'),
    'charset': 'utf8mb4',
    'cursorclass': 'pymysql.cursors.DictCursor'
}

# Почтовый сервер
EMAIL_CONFIG = {
    'smtp_server': os.getenv('SMTP_SERVER', 'localhost'),
    'smtp_port': int(os.getenv('SMTP_PORT', 25)),
    'sender_email': os.getenv('SENDER_EMAIL', 'reestr-system@company.ru'),
    'use_auth': os.getenv('SMTP_USE_AUTH', 'false').lower() == 'true',
    'smtp_user': os.getenv('SMTP_USER', ''),
    'smtp_password': os.getenv('SMTP_PASSWORD', '')
}

# Безопасность
SECRET_KEY = os.getenv('SECRET_KEY', 'change-this-secret-key-in-production')
SESSION_DURATION_HOURS = 8
BCRYPT_ROUNDS = 12

# Файлы
MAX_FILE_SIZE_MB = 16
MAX_ATTACHMENTS = 5
ALLOWED_EXTENSIONS = {'.pdf', '.docx', '.xlsx', '.jpg', '.png', '.jpeg'}

UPLOAD_FOLDER = BASE_DIR / 'uploads'
GENERATED_PDFS_FOLDER = BASE_DIR / 'generated_pdfs'
TEMPLATES_FOLDER = BASE_DIR / 'templates_docx'
LOGS_FOLDER = BASE_DIR / 'logs'

# Пути к шаблонам
TEMPLATE_D1 = TEMPLATES_FOLDER / 'D1.docx'  # Заполненный реестр
TEMPLATE_D2 = TEMPLATES_FOLDER / 'D2.docx'  # Пустой бланк
TEMPLATE_J = TEMPLATES_FOLDER / 'J.docx'    # Журнал

# Интерфейс
APP_NAME = "Реестры документов"
APP_VERSION = "1.0.0"
ORGANIZATION_NAME = "Финансово-экономический отдел"

# Цветовая схема (корпоративный стиль)
COLORS = {
    'primary': '#0056b3',
    'secondary': '#00a0e4',
    'accent': '#00c8d7',
    'dark': '#003d7a',
    'success': '#28a745',
    'warning': '#ffc107',
    'danger': '#dc3545',
    'info': '#17a2b8'
}

# Статусы реестров
STATUS_CHOICES = {
    'draft': 'Черновик',
    'submitted': 'Отправлен',
    'in_review': 'На проверке',
    'returned': 'На доработке',
    'accepted': 'Принят'
}

# Роли пользователей
ROLE_CHOICES = {
    'admin': 'Администратор',
    'financier': 'Финансист',
    'head': 'Руководитель ФЭО',
    'user': 'Пользователь'
}

# Логирование
LOG_LEVEL = os.getenv('LOG_LEVEL', 'INFO')
LOG_FORMAT = '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
