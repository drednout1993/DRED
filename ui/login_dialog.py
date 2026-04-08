"""
Диалог входа в систему
Система учёта реестров документов
"""

from PySide6.QtWidgets import (
    QDialog, QVBoxLayout, QHBoxLayout, QLabel, 
    QLineEdit, QPushButton, QMessageBox, QWidget
)
from PySide6.QtCore import Qt
from PySide6.QtGui import QFont

from models.user import User
from utils.database import get_database


class LoginDialog(QDialog):
    """Диалоговое окно входа в систему"""
    
    def __init__(self, parent=None):
        super().__init__(parent)
        
        self.user_data = None
        
        self.setWindowTitle("Вход в систему")
        self.setFixedSize(400, 300)
        self.setModal(True)
        
        self.setup_ui()
    
    def setup_ui(self):
        """Настройка интерфейса"""
        layout = QVBoxLayout()
        layout.setSpacing(20)
        layout.setContentsMargins(40, 40, 40, 40)
        
        # Заголовок
        title_label = QLabel("Система учёта реестров")
        title_label.setFont(QFont("Segoe UI", 16, QFont.Bold))
        title_label.setAlignment(Qt.AlignCenter)
        title_label.setStyleSheet("color: #0056b3; margin-bottom: 10px;")
        layout.addWidget(title_label)
        
        subtitle_label = QLabel("Финансово-экономический отдел")
        subtitle_label.setFont(QFont("Segoe UI", 10))
        subtitle_label.setAlignment(Qt.AlignCenter)
        subtitle_label.setStyleSheet("color: #666666; margin-bottom: 30px;")
        layout.addWidget(subtitle_label)
        
        # Поле логина
        login_layout = QVBoxLayout()
        login_label = QLabel("Логин:")
        login_label.setStyleSheet("font-weight: bold;")
        self.login_input = QLineEdit()
        self.login_input.setPlaceholderText("Введите логин")
        self.login_input.setMinimumHeight(40)
        login_layout.addWidget(login_label)
        login_layout.addWidget(self.login_input)
        layout.addLayout(login_layout)
        
        # Поле пароля
        password_layout = QVBoxLayout()
        password_label = QLabel("Пароль:")
        password_label.setStyleSheet("font-weight: bold;")
        self.password_input = QLineEdit()
        self.password_input.setPlaceholderText("Введите пароль")
        self.password_input.setEchoMode(QLineEdit.Password)
        self.password_input.setMinimumHeight(40)
        self.password_input.returnPressed.connect(self.login)
        password_layout.addWidget(password_label)
        password_layout.addWidget(self.password_input)
        layout.addLayout(password_layout)
        
        # Кнопки
        button_layout = QHBoxLayout()
        button_layout.setSpacing(10)
        
        self.cancel_button = QPushButton("Отмена")
        self.cancel_button.setProperty("role", "secondary")
        self.cancel_button.setMinimumHeight(40)
        self.cancel_button.clicked.connect(self.reject)
        button_layout.addWidget(self.cancel_button)
        
        self.login_button = QPushButton("Войти")
        self.login_button.setMinimumHeight(40)
        self.login_button.clicked.connect(self.login)
        button_layout.addWidget(self.login_button)
        
        layout.addLayout(button_layout)
        
        self.setLayout(layout)
    
    def login(self):
        """Обработчик входа"""
        username = self.login_input.text().strip()
        password = self.password_input.text()
        
        if not username:
            QMessageBox.warning(self, "Ошибка", "Введите логин")
            self.login_input.setFocus()
            return
        
        if not password:
            QMessageBox.warning(self, "Ошибка", "Введите пароль")
            self.password_input.setFocus()
            return
        
        # Блокировка кнопок на время проверки
        self.login_button.setEnabled(False)
        self.cancel_button.setEnabled(False)
        
        try:
            db = get_database(None)  # Используем глобальный экземпляр
            user_model = User(db)
            
            user = user_model.authenticate(username, password)
            
            if user:
                self.user_data = dict(user)
                # Удаляем хэш пароля из данных сессии
                if 'password_hash' in self.user_data:
                    del self.user_data['password_hash']
                
                self.accept()
            else:
                QMessageBox.critical(
                    self, 
                    "Ошибка входа", 
                    "Неверный логин или пароль"
                )
                self.password_input.clear()
                self.password_input.setFocus()
        
        except Exception as e:
            QMessageBox.critical(
                self, 
                "Ошибка", 
                f"Произошла ошибка при входе:\n{str(e)}"
            )
        
        finally:
            # Разблокировка кнопок
            self.login_button.setEnabled(True)
            self.cancel_button.setEnabled(True)
    
    def get_user_data(self) -> dict:
        """Получение данных пользователя после успешного входа"""
        return self.user_data
