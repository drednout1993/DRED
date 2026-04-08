"""
Главное окно приложения
Система учёта реестров документов
"""

from PySide6.QtWidgets import (
    QMainWindow, QWidget, QVBoxLayout, QHBoxLayout, 
    QStackedWidget, QPushButton, QLabel, QFrame, QScrollArea
)
from PySide6.QtCore import Qt, QSize
from PySide6.QtGui import QFont, QIcon

from config import COLORS, STATUS_CHOICES, ROLE_CHOICES
from ui.widgets.dashboard import DashboardWidget
from ui.widgets.journal_widget import JournalWidget
from ui.widgets.register_form_widget import RegisterFormWidget
from ui.widgets.profile_widget import ProfileWidget


class MainWindow(QMainWindow):
    """Главное окно приложения"""
    
    def __init__(self, user_data: dict):
        super().__init__()
        
        self.user_data = user_data
        self.current_widget = None
        
        self.setWindowTitle("Реестры документов - Финансово-экономический отдел")
        self.setMinimumSize(1200, 800)
        
        self.setup_ui()
    
    def setup_ui(self):
        """Настройка интерфейса"""
        # Центральный виджет
        central_widget = QWidget()
        self.setCentralWidget(central_widget)
        
        # Основной layout
        main_layout = QHBoxLayout()
        main_layout.setSpacing(0)
        main_layout.setContentsMargins(0, 0, 0, 0)
        
        # Левая панель навигации
        sidebar = self.create_sidebar()
        main_layout.addWidget(sidebar)
        
        # Основная область контента
        content_area = QWidget()
        content_layout = QVBoxLayout()
        content_layout.setSpacing(0)
        content_layout.setContentsMargins(0, 0, 0, 0)
        
        # Шапка
        header = self.create_header()
        content_layout.addWidget(header)
        
        # Область страниц
        self.stacked_widget = QStackedWidget()
        self.stacked_widget.setStyleSheet("background-color: #f5f5f5;")
        
        # Добавление страниц
        self.dashboard_page = DashboardWidget(self.user_data, self)
        self.stacked_widget.addWidget(self.dashboard_page)
        
        self.journal_page = JournalWidget(self.user_data, self)
        self.stacked_widget.addWidget(self.journal_page)
        
        self.register_form_page = RegisterFormWidget(self.user_data, self)
        self.stacked_widget.addWidget(self.register_form_page)
        
        self.profile_page = ProfileWidget(self.user_data, self)
        self.stacked_widget.addWidget(self.profile_page)
        
        content_layout.addWidget(self.stacked_widget)
        
        content_area.setLayout(content_layout)
        main_layout.addWidget(content_area)
        
        central_widget.setLayout(main_layout)
    
    def create_sidebar(self) -> QFrame:
        """Создание левой панели навигации"""
        sidebar = QFrame()
        sidebar.setFixedWidth(250)
        sidebar.setStyleSheet(f"""
            QFrame {{
                background-color: {COLORS['primary']};
                color: white;
            }}
        """)
        
        layout = QVBoxLayout()
        layout.setSpacing(0)
        layout.setContentsMargins(0, 0, 0, 0)
        
        # Логотип и название
        logo_section = QFrame()
        logo_section.setStyleSheet("background-color: transparent; padding: 20px;")
        logo_layout = QVBoxLayout()
        logo_layout.setAlignment(Qt.AlignCenter)
        
        logo_label = QLabel("📋")
        logo_label.setFont(QFont("Segoe UI", 36))
        logo_label.setAlignment(Qt.AlignCenter)
        logo_label.setStyleSheet("color: white;")
        logo_layout.addWidget(logo_label)
        
        title_label = QLabel("Реестры\nдокументов")
        title_label.setFont(QFont("Segoe UI", 14, QFont.Bold))
        title_label.setAlignment(Qt.AlignCenter)
        title_label.setStyleSheet("color: white; margin-top: 10px;")
        logo_layout.addWidget(title_label)
        
        logo_section.setLayout(logo_layout)
        layout.addWidget(logo_section)
        
        # Разделитель
        separator = QFrame()
        separator.setFixedHeight(1)
        separator.setStyleSheet(f"background-color: {COLORS['secondary']};")
        layout.addWidget(separator)
        
        # Навигационные кнопки
        nav_buttons_layout = QVBoxLayout()
        nav_buttons_layout.setSpacing(5)
        nav_buttons_layout.setContentsMargins(10, 20, 10, 20)
        
        # Кнопки меню в зависимости от роли
        role = self.user_data.get('role', 'user')
        
        self.nav_buttons = {}
        
        # Главная - для всех
        btn_dashboard = self.create_nav_button("🏠 Главная", 0)
        nav_buttons_layout.addWidget(btn_dashboard)
        self.nav_buttons['dashboard'] = btn_dashboard
        
        # Журнал реестров - для финансистов и админа
        if role in ['admin', 'financier', 'head']:
            btn_journal = self.create_nav_button("📊 Журнал реестров", 1)
            nav_buttons_layout.addWidget(btn_journal)
            self.nav_buttons['journal'] = btn_journal
        
        # Создать реестр - для всех
        btn_create = self.create_nav_button("➕ Создать реестр", 2)
        nav_buttons_layout.addWidget(btn_create)
        self.nav_buttons['create'] = btn_create
        
        # Профиль - для всех
        btn_profile = self.create_nav_button("👤 Мой профиль", 3)
        nav_buttons_layout.addWidget(btn_profile)
        self.nav_buttons['profile'] = btn_profile
        
        # Управление пользователями - только админ
        if role == 'admin':
            btn_users = self.create_nav_button("👥 Пользователи", 4)
            nav_buttons_layout.addWidget(btn_users)
            self.nav_buttons['users'] = btn_users
        
        nav_buttons_layout.addStretch()
        layout.addLayout(nav_buttons_layout)
        
        # Информация о пользователе внизу
        user_section = QFrame()
        user_section.setStyleSheet("background-color: transparent; padding: 15px;")
        user_layout = QVBoxLayout()
        
        user_name = QLabel(self.user_data.get('fio', 'Пользователь'))
        user_name.setFont(QFont("Segoe UI", 10, QFont.Bold))
        user_name.setStyleSheet("color: white;")
        user_layout.addWidget(user_name)
        
        user_role = QLabel(ROLE_CHOICES.get(role, 'Пользователь'))
        user_role.setStyleSheet("color: rgba(255,255,255,0.7); font-size: 12px;")
        user_layout.addWidget(user_role)
        
        user_section.setLayout(user_layout)
        layout.addWidget(user_section)
        
        sidebar.setLayout(layout)
        
        # Выделение первой кнопки
        btn_dashboard.setChecked(True)
        
        return sidebar
    
    def create_nav_button(self, text: str, page_index: int) -> QPushButton:
        """Создание кнопки навигации"""
        button = QPushButton(text)
        button.setCheckable(True)
        button.setMinimumHeight(45)
        button.setCursor(Qt.PointingHandCursor)
        button.setStyleSheet(f"""
            QPushButton {{
                background-color: transparent;
                color: white;
                border: none;
                border-radius: 8px;
                padding: 10px 15px;
                text-align: left;
                font-size: 13px;
            }}
            
            QPushButton:hover {{
                background-color: rgba(255, 255, 255, 0.1);
            }}
            
            QPushButton:checked {{
                background-color: rgba(255, 255, 255, 0.2);
                font-weight: bold;
            }}
        """)
        button.clicked.connect(lambda: self.switch_page(page_index))
        return button
    
    def create_header(self) -> QFrame:
        """Создание шапки"""
        header = QFrame()
        header.setFixedHeight(60)
        header.setStyleSheet(f"""
            QFrame {{
                background-color: white;
                border-bottom: 1px solid #dddddd;
            }}
        """)
        
        layout = QHBoxLayout()
        layout.setContentsMargins(20, 10, 20, 10)
        
        # Заголовок страницы
        self.page_title = QLabel("Панель управления")
        self.page_title.setFont(QFont("Segoe UI", 18, QFont.Bold))
        self.page_title.setStyleSheet(f"color: {COLORS['primary']};")
        layout.addWidget(self.page_title)
        
        layout.addStretch()
        
        # Кнопка выхода
        logout_btn = QPushButton("🚪 Выход")
        logout_btn.setProperty("role", "secondary")
        logout_btn.setMaximumWidth(120)
        logout_btn.clicked.connect(self.logout)
        layout.addWidget(logout_btn)
        
        header.setLayout(layout)
        return header
    
    def switch_page(self, index: int):
        """Переключение страницы"""
        self.stacked_widget.setCurrentIndex(index)
        
        # Обновление заголовка
        titles = {
            0: "Панель управления",
            1: "Журнал реестров",
            2: "Создание реестра",
            3: "Мой профиль",
            4: "Управление пользователями"
        }
        self.page_title.setText(titles.get(index, ""))
        
        # Сброс состояния кнопок
        for btn in self.nav_buttons.values():
            btn.setChecked(False)
        
        # Нахождение и выделение нужной кнопки
        sender = self.sender()
        if sender:
            sender.setChecked(True)
        
        # Обновление данных на странице
        if index == 0:
            self.dashboard_page.refresh_data()
        elif index == 1:
            self.journal_page.refresh_data()
    
    def logout(self):
        """Выход из системы"""
        from PySide6.QtWidgets import QMessageBox
        
        reply = QMessageBox.question(
            self,
            "Выход",
            "Вы действительно хотите выйти из системы?",
            QMessageBox.Yes | QMessageBox.No
        )
        
        if reply == QMessageBox.Yes:
            self.close()
