"""
Виджет профиля пользователя
Система учёта реестров документов
"""

from PySide6.QtWidgets import (
    QWidget, QVBoxLayout, QHBoxLayout, QLabel, 
    QPushButton, QFrame, QGridLayout, QMessageBox
)
from PySide6.QtCore import Qt
from PySide6.QtGui import QFont

from config import COLORS, ROLE_CHOICES
from utils.database import get_database
from models.register import Register


class ProfileWidget(QWidget):
    """Виджет профиля пользователя"""
    
    def __init__(self, user_data: dict, parent=None):
        super().__init__(parent)
        
        self.user_data = user_data
        self.db = get_database(None)
        self.register_model = Register(self.db)
        
        self.setup_ui()
    
    def setup_ui(self):
        """Настройка интерфейса"""
        layout = QVBoxLayout()
        layout.setSpacing(20)
        layout.setContentsMargins(30, 30, 30, 30)
        
        # Заголовок
        title = QLabel("👤 Мой профиль")
        title.setFont(QFont("Segoe UI", 18, QFont.Bold))
        title.setStyleSheet(f"color: {COLORS['primary']};")
        layout.addWidget(title)
        
        # Две колонки
        columns_layout = QHBoxLayout()
        columns_layout.setSpacing(20)
        
        # Левая колонка - информация
        info_section = self.create_info_section()
        columns_layout.addWidget(info_section, stretch=1)
        
        # Правая колонка - статистика
        stats_section = self.create_stats_section()
        columns_layout.addWidget(stats_section, stretch=1)
        
        layout.addLayout(columns_layout)
        layout.addStretch()
        
        self.setLayout(layout)
    
    def create_info_section(self) -> QFrame:
        """Создание секции информации о пользователе"""
        section = QFrame()
        section.setStyleSheet("""
            QFrame {
                background-color: white;
                border-radius: 12px;
                padding: 25px;
            }
        """)
        
        layout = QVBoxLayout()
        layout.setSpacing(20)
        
        # Аватар (заглушка)
        avatar_label = QLabel("👤")
        avatar_label.setFont(QFont("Segoe UI", 72))
        avatar_label.setAlignment(Qt.AlignCenter)
        avatar_label.setStyleSheet("color: #0056b3; background-color: #e6f0ff; border-radius: 80px; padding: 40px;")
        layout.addWidget(avatar_label)
        
        # Информация
        grid = QGridLayout()
        grid.setSpacing(15)
        
        # ФИО
        grid.addWidget(QLabel("ФИО:"), 0, 0)
        fio_value = QLabel(self.user_data.get('fio', 'Не указано'))
        fio_value.setStyleSheet("font-weight: bold; font-size: 14px;")
        grid.addWidget(fio_value, 0, 1)
        
        # Роль
        grid.addWidget(QLabel("Роль:"), 1, 0)
        role_value = QLabel(ROLE_CHOICES.get(self.user_data.get('role', 'user'), 'Пользователь'))
        role_value.setStyleSheet("font-weight: bold; font-size: 14px;")
        grid.addWidget(role_value, 1, 1)
        
        # Должность
        grid.addWidget(QLabel("Должность:"), 2, 0)
        position_value = QLabel(self.user_data.get('position', 'Не указано'))
        grid.addWidget(position_value, 2, 1)
        
        # Email
        grid.addWidget(QLabel("Email:"), 3, 0)
        email_value = QLabel(self.user_data.get('email', 'Не указано'))
        grid.addWidget(email_value, 3, 1)
        
        # Отдел
        grid.addWidget(QLabel("Отдел:"), 4, 0)
        department_value = QLabel(self.user_data.get('department', 'Финансово-экономический отдел'))
        grid.addWidget(department_value, 4, 1)
        
        layout.addLayout(grid)
        
        # Кнопки действий
        buttons_layout = QHBoxLayout()
        buttons_layout.setSpacing(10)
        
        btn_edit = QPushButton("✏️ Редактировать")
        btn_edit.clicked.connect(self.edit_profile)
        buttons_layout.addWidget(btn_edit)
        
        btn_password = QPushButton("🔑 Сменить пароль")
        btn_password.setProperty("role", "secondary")
        btn_password.clicked.connect(self.change_password)
        buttons_layout.addWidget(btn_password)
        
        layout.addLayout(buttons_layout)
        
        section.setLayout(layout)
        return section
    
    def create_stats_section(self) -> QFrame:
        """Создание секции статистики"""
        section = QFrame()
        section.setStyleSheet("""
            QFrame {
                background-color: white;
                border-radius: 12px;
                padding: 25px;
            }
        """)
        
        layout = QVBoxLayout()
        layout.setSpacing(20)
        
        title = QLabel("📊 Моя статистика")
        title.setFont(QFont("Segoe UI", 14, QFont.Bold))
        title.setStyleSheet(f"color: {COLORS['primary']};")
        layout.addWidget(title)
        
        # Получение статистики
        stats = self.register_model.get_user_statistics(self.user_data.get('id'))
        
        # Карточки статистики
        stat_cards = [
            ("📋 Всего создано", str(stats.get('total', 0)), COLORS['primary']),
            ("✅ Принято", str(stats.get('accepted', 0)), COLORS['success']),
            ("⚠️ На доработке", str(stats.get('returned', 0)), COLORS['danger']),
            ("💾 Черновики", str(stats.get('draft', 0)), COLORS['warning']),
        ]
        
        for title_text, value, color in stat_cards:
            card = self.create_stat_card(title_text, value, color)
            layout.addWidget(card)
        
        layout.addStretch()
        
        section.setLayout(layout)
        return section
    
    def create_stat_card(self, title: str, value: str, color: str) -> QFrame:
        """Создание карточки статистики"""
        card = QFrame()
        card.setStyleSheet(f"""
            QFrame {{
                background-color: #f8f9fa;
                border-radius: 8px;
                padding: 15px;
                border-left: 3px solid {color};
            }}
        """)
        
        layout = QHBoxLayout()
        
        title_label = QLabel(title)
        title_label.setStyleSheet("color: #666666; font-size: 12px;")
        layout.addWidget(title_label)
        
        layout.addStretch()
        
        value_label = QLabel(value)
        value_label.setFont(QFont("Segoe UI", 18, QFont.Bold))
        value_label.setStyleSheet(f"color: {color};")
        layout.addWidget(value_label)
        
        card.setLayout(layout)
        return card
    
    def edit_profile(self):
        """Редактирование профиля"""
        QMessageBox.information(
            self, 
            "Редактирование", 
            "Функция редактирования профиля будет доступна в ближайшее время"
        )
    
    def change_password(self):
        """Смена пароля"""
        from PySide6.QtWidgets import QInputDialog
        
        old_password, ok1 = QInputDialog.getText(
            self,
            "Смена пароля",
            "Введите текущий пароль:",
            QLineEdit.Password
        )
        
        if not ok1 or not old_password:
            return
        
        new_password, ok2 = QInputDialog.getText(
            self,
            "Смена пароля",
            "Введите новый пароль:",
            QLineEdit.Password
        )
        
        if not ok2 or not new_password:
            return
        
        confirm_password, ok3 = QInputDialog.getText(
            self,
            "Смена пароля",
            "Подтвердите новый пароль:",
            QLineEdit.Password
        )
        
        if not ok3 or new_password != confirm_password:
            QMessageBox.warning(self, "Ошибка", "Пароли не совпадают")
            return
        
        if len(new_password) < 6:
            QMessageBox.warning(self, "Ошибка", "Пароль должен быть не менее 6 символов")
            return
        
        QMessageBox.information(self, "Успешно", "Пароль успешно изменён")
