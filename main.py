"""
Точка входа приложения
Система учёта реестров документов
"""

import sys
from PySide6.QtWidgets import QApplication
from PySide6.QtCore import Qt, QTranslator, QLocale
from PySide6.QtGui import QFont

from config import APP_NAME, DB_CONFIG
from utils.database import get_database, close_database
from ui.login_dialog import LoginDialog
from ui.main_window import MainWindow


def setup_application():
    """Настройка приложения"""
    # Включение поддержки High DPI
    QApplication.setHighDpiScaleFactorRoundingPolicy(
        Qt.HighDpiScaleFactorRoundingPolicy.PassThrough
    )
    
    app = QApplication(sys.argv)
    app.setApplicationName(APP_NAME)
    app.setOrganizationName("Финансово-экономический отдел")
    
    # Настройка шрифта
    font = QFont("Segoe UI", 10)
    app.setFont(font)
    
    # Применение стилей
    apply_styles(app)
    
    return app


def apply_styles(app: QApplication):
    """Применение стилей приложения"""
    stylesheet = """
    /* Основные цвета */
    QMainWindow, QDialog {
        background-color: #f5f5f5;
    }
    
    /* Кнопки */
    QPushButton {
        background-color: #0056b3;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 8px 16px;
        font-weight: bold;
    }
    
    QPushButton:hover {
        background-color: #004494;
    }
    
    QPushButton:pressed {
        background-color: #003d7a;
    }
    
    QPushButton:disabled {
        background-color: #cccccc;
    }
    
    QPushButton[role="secondary"] {
        background-color: transparent;
        border: 2px solid #0056b3;
        color: #0056b3;
    }
    
    QPushButton[role="secondary"]:hover {
        background-color: #e6f0ff;
    }
    
    /* Поля ввода */
    QLineEdit, QTextEdit, QPlainTextEdit, QComboBox, QDateEdit {
        border: 1px solid #cccccc;
        border-radius: 6px;
        padding: 8px;
        background-color: white;
        selection-background-color: #00a0e4;
    }
    
    QLineEdit:focus, QTextEdit:focus, QPlainTextEdit:focus, 
    QComboBox:focus, QDateEdit:focus {
        border: 2px solid #0056b3;
    }
    
    /* Таблицы */
    QTableView, QTableWidget {
        background-color: white;
        alternate-background-color: #f9f9f9;
        border: 1px solid #dddddd;
        border-radius: 8px;
        gridline-color: #eeeeee;
    }
    
    QTableView::item, QTableWidget::item {
        padding: 8px;
    }
    
    QTableView::item:selected, QTableWidget::item:selected {
        background-color: #00a0e4;
        color: white;
    }
    
    QHeaderView::section {
        background-color: #0056b3;
        color: white;
        padding: 8px;
        border: none;
        font-weight: bold;
    }
    
    /* Группы и фреймы */
    QGroupBox {
        font-weight: bold;
        border: 2px solid #0056b3;
        border-radius: 8px;
        margin-top: 12px;
        padding-top: 12px;
    }
    
    QGroupBox::title {
        subcontrol-origin: margin;
        left: 12px;
        padding: 0 8px;
        color: #0056b3;
    }
    
    /* Вкладки */
    QTabWidget::pane {
        border: 1px solid #dddddd;
        border-radius: 8px;
        background-color: white;
    }
    
    QTabBar::tab {
        background-color: #f0f0f0;
        padding: 8px 16px;
        margin-right: 2px;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
    }
    
    QTabBar::tab:selected {
        background-color: white;
        color: #0056b3;
        font-weight: bold;
    }
    
    /* Скроллбары */
    QScrollBar:vertical {
        background-color: #f5f5f5;
        width: 12px;
        border-radius: 6px;
    }
    
    QScrollBar::handle:vertical {
        background-color: #cccccc;
        border-radius: 6px;
        min-height: 20px;
    }
    
    QScrollBar::handle:vertical:hover {
        background-color: #bbbbbb;
    }
    
    QScrollBar::add-line:vertical, QScrollBar::sub-line:vertical {
        height: 0px;
    }
    
    /* Сообщения */
    QMessageBox {
        background-color: white;
    }
    
    /* Прогресс бар */
    QProgressBar {
        border: 1px solid #cccccc;
        border-radius: 6px;
        text-align: center;
        background-color: #f5f5f5;
    }
    
    QProgressBar::chunk {
        background-color: #0056b3;
        border-radius: 5px;
    }
    
    /* Чекбоксы и радио кнопки */
    QCheckBox, QRadioButton {
        spacing: 8px;
    }
    
    QCheckBox::indicator, QRadioButton::indicator {
        width: 18px;
        height: 18px;
    }
    
    /* Список */
    QListWidget {
        border: 1px solid #cccccc;
        border-radius: 6px;
        background-color: white;
    }
    
    QListWidget::item {
        padding: 8px;
    }
    
    QListWidget::item:selected {
        background-color: #00a0e4;
        color: white;
    }
    """
    
    app.setStyleSheet(stylesheet)


def main():
    """Основная функция"""
    # Создание приложения
    app = setup_application()
    
    try:
        # Подключение к базе данных
        db = get_database(DB_CONFIG)
        
        if not db.is_connected():
            from PySide6.QtWidgets import QMessageBox
            QMessageBox.critical(
                None,
                "Ошибка",
                "Не удалось подключиться к базе данных.\n"
                "Проверьте настройки подключения."
            )
            sys.exit(1)
        
        # Показ диалога входа
        login_dialog = LoginDialog()
        
        if login_dialog.exec() == 1:  # QDialog.Accepted
            user_data = login_dialog.get_user_data()
            
            if user_data:
                # Показ главного окна
                main_window = MainWindow(user_data)
                main_window.show()
                
                # Запуск цикла приложения
                exit_code = app.exec()
                
                # Закрытие соединения с БД
                close_database()
                
                return exit_code
        
        close_database()
        return 0
        
    except Exception as e:
        from PySide6.QtWidgets import QMessageBox
        QMessageBox.critical(
            None,
            "Критическая ошибка",
            f"Произошла ошибка при запуске приложения:\n{str(e)}"
        )
        close_database()
        return 1


if __name__ == "__main__":
    sys.exit(main())
