"""
Виджет журнала реестров
Система учёта реестров документов
"""

from PySide6.QtWidgets import (
    QWidget, QVBoxLayout, QHBoxLayout, QLabel, 
    QPushButton, QTableWidget, QTableWidgetItem, 
    QComboBox, QLineEdit, QDateEdit, QFrame, QMessageBox, QMenu
)
from PySide6.QtCore import Qt, QDate
from PySide6.QtGui import QFont, QAction

from config import COLORS, STATUS_CHOICES
from utils.database import get_database
from models.register import Register


class JournalWidget(QWidget):
    """Виджет журнала реестров"""
    
    def __init__(self, user_data: dict, parent=None):
        super().__init__(parent)
        
        self.user_data = user_data
        self.db = get_database(None)
        self.register_model = Register(self.db)
        
        self.setup_ui()
    
    def setup_ui(self):
        """Настройка интерфейса"""
        layout = QVBoxLayout()
        layout.setSpacing(15)
        layout.setContentsMargins(30, 30, 30, 30)
        
        # Фильтры
        filters_section = self.create_filters_section()
        layout.addWidget(filters_section)
        
        # Таблица реестров
        table_section = self.create_table_section()
        layout.addWidget(table_section)
        
        self.setLayout(layout)
    
    def create_filters_section(self) -> QFrame:
        """Создание секции фильтров"""
        section = QFrame()
        section.setStyleSheet("""
            QFrame {
                background-color: white;
                border-radius: 12px;
                padding: 20px;
            }
        """)
        
        layout = QVBoxLayout()
        
        # Заголовок
        title = QLabel("🔍 Фильтры")
        title.setFont(QFont("Segoe UI", 14, QFont.Bold))
        title.setStyleSheet(f"color: {COLORS['primary']}; margin-bottom: 15px;")
        layout.addWidget(title)
        
        # Строка фильтров
        filters_layout = QHBoxLayout()
        filters_layout.setSpacing(15)
        
        # Статус
        filters_layout.addWidget(QLabel("Статус:"))
        self.status_filter = QComboBox()
        self.status_filter.addItem("Все статусы", "")
        for code, name in STATUS_CHOICES.items():
            self.status_filter.addItem(name, code)
        self.status_filter.setMinimumWidth(180)
        filters_layout.addWidget(self.status_filter)
        
        # Дата от
        filters_layout.addWidget(QLabel("Дата от:"))
        self.date_from = QDateEdit()
        self.date_from.setCalendarPopup(True)
        self.date_from.setDate(QDate.currentDate().addMonths(-1))
        self.date_from.setMinimumWidth(130)
        filters_layout.addWidget(self.date_from)
        
        # Дата до
        filters_layout.addWidget(QLabel("Дата до:"))
        self.date_to = QDateEdit()
        self.date_to.setCalendarPopup(True)
        self.date_to.setDate(QDate.currentDate())
        self.date_to.setMinimumWidth(130)
        filters_layout.addWidget(self.date_to)
        
        # Поиск
        self.search_input = QLineEdit()
        self.search_input.setPlaceholderText("Поиск по ФИО или коду...")
        self.search_input.setMinimumWidth(250)
        filters_layout.addWidget(self.search_input)
        
        # Кнопки
        btn_apply = QPushButton("Применить")
        btn_apply.clicked.connect(self.apply_filters)
        filters_layout.addWidget(btn_apply)
        
        btn_reset = QPushButton("Сброс")
        btn_reset.setProperty("role", "secondary")
        btn_reset.clicked.connect(self.reset_filters)
        filters_layout.addWidget(btn_reset)
        
        filters_layout.addStretch()
        
        layout.addLayout(filters_layout)
        section.setLayout(layout)
        return section
    
    def create_table_section(self) -> QFrame:
        """Создание секции таблицы"""
        section = QFrame()
        section.setStyleSheet("""
            QFrame {
                background-color: white;
                border-radius: 12px;
                padding: 20px;
            }
        """)
        
        layout = QVBoxLayout()
        
        # Заголовок
        header_layout = QHBoxLayout()
        
        title = QLabel("📋 Реестры")
        title.setFont(QFont("Segoe UI", 14, QFont.Bold))
        title.setStyleSheet(f"color: {COLORS['primary']};")
        header_layout.addWidget(title)
        
        header_layout.addStretch()
        
        # Кнопка создания
        btn_create = QPushButton("➕ Создать реестр")
        btn_create.clicked.connect(self.open_create_form)
        header_layout.addWidget(btn_create)
        
        layout.addLayout(header_layout)
        
        # Таблица
        self.table = QTableWidget()
        self.table.setColumnCount(6)
        self.table.setHorizontalHeaderLabels([
            "№", "Код", "Дата", "Автор", "Статус", "Действия"
        ])
        self.table.horizontalHeader().setStretchLastSection(True)
        self.table.setAlternatingRowColors(True)
        self.table.setSelectionBehavior(QTableWidget.SelectRows)
        self.table.setContextMenuPolicy(Qt.CustomContextMenu)
        self.table.customContextMenuRequested.connect(self.show_context_menu)
        
        layout.addWidget(self.table)
        
        section.setLayout(layout)
        return section
    
    def refresh_data(self):
        """Обновление данных"""
        self.load_registers()
    
    def apply_filters(self):
        """Применение фильтров"""
        self.load_registers()
    
    def reset_filters(self):
        """Сброс фильтров"""
        self.status_filter.setCurrentIndex(0)
        self.date_from.setDate(QDate.currentDate().addMonths(-1))
        self.date_to.setDate(QDate.currentDate())
        self.search_input.clear()
        self.load_registers()
    
    def load_registers(self):
        """Загрузка реестров"""
        role = self.user_data.get('role', 'user')
        user_id = self.user_data.get('id')
        
        # Получение фильтров
        status = self.status_filter.currentData()
        date_from = self.date_from.date().toPyDate()
        date_to = self.date_to.date().toPyDate()
        search = self.search_input.text().strip() or None
        
        # Для обычных пользователей - только свои реестры
        author_id = user_id if role == 'user' else None
        
        registers = self.register_model.get_all(
            status_filter=status,
            author_id=author_id,
            date_from=date_from,
            date_to=date_to,
            search_term=search
        )
        
        self.table.setRowCount(len(registers))
        
        for i, reg in enumerate(registers):
            # Номер строки
            self.table.setItem(i, 0, QTableWidgetItem(str(i + 1)))
            
            # Код
            code = reg.get('reg_code') or 'Черновик'
            self.table.setItem(i, 1, QTableWidgetItem(code))
            
            # Дата
            date = str(reg.get('register_date', ''))
            self.table.setItem(i, 2, QTableWidgetItem(date))
            
            # Автор
            author = reg.get('author_fio') or reg.get('sender_fio', '')
            self.table.setItem(i, 3, QTableWidgetItem(author))
            
            # Статус
            status_text = STATUS_CHOICES.get(reg.get('status', 'draft'), '')
            status_item = QTableWidgetItem(status_text)
            status_item.setBackground(self.get_status_color(reg.get('status')))
            status_item.setForeground(self.get_status_text_color(reg.get('status')))
            self.table.setItem(i, 4, status_item)
            
            # Действия (кнопка с меню)
            actions_widget = QWidget()
            actions_layout = QHBoxLayout(actions_widget)
            actions_layout.setContentsMargins(5, 0, 5, 0)
            
            btn_actions = QPushButton("⋮")
            btn_actions.setMaximumWidth(30)
            btn_actions.clicked.connect(lambda checked, r=reg: self.show_actions_menu(r))
            actions_layout.addWidget(btn_actions)
            
            self.table.setCellWidget(i, 5, actions_widget)
    
    def get_status_color(self, status: str):
        """Получение цвета фона статуса"""
        from PySide6.QtGui import QColor
        
        colors = {
            'draft': '#f0f0f0',
            'submitted': '#fff3cd',
            'in_review': '#d1ecf1',
            'returned': '#f8d7da',
            'accepted': '#d4edda',
        }
        return QColor(colors.get(status, '#f0f0f0'))
    
    def get_status_text_color(self, status: str):
        """Получение цвета текста статуса"""
        from PySide6.QtGui import QColor
        
        colors = {
            'draft': '#666666',
            'submitted': '#856404',
            'in_review': '#0c5460',
            'returned': '#721c24',
            'accepted': '#155724',
        }
        return QColor(colors.get(status, '#666666'))
    
    def show_context_menu(self, pos):
        """Показ контекстного меню"""
        row = self.table.rowAt(pos.y())
        
        if row >= 0:
            # Получение данных реестра из таблицы
            code_item = self.table.item(row, 1)
            # Здесь должна быть логика получения полного объекта реестра
            pass
    
    def show_actions_menu(self, register: dict):
        """Показ меню действий для реестра"""
        menu = QMenu(self)
        
        role = self.user_data.get('role', 'user')
        status = register.get('status', 'draft')
        
        # Просмотр - для всех
        view_action = menu.addAction("👁️ Просмотр")
        view_action.triggered.connect(lambda: self.view_register(register))
        
        # Редактирование - для автора черновика или возвращённого
        if role in ['admin', 'user'] and register.get('author_id') == self.user_data.get('id'):
            if status in ['draft', 'returned']:
                edit_action = menu.addAction("✏️ Редактировать")
                edit_action.triggered.connect(lambda: self.edit_register(register))
        
        # Принятие - для финансистов
        if role in ['admin', 'financier', 'head'] and status == 'in_review':
            accept_action = menu.addAction("✅ Принять")
            accept_action.triggered.connect(lambda: self.accept_register(register))
        
        # Возврат - для финансистов
        if role in ['admin', 'financier', 'head'] and status == 'in_review':
            return_action = menu.addAction("↩️ Вернуть на доработку")
            return_action.triggered.connect(lambda: self.return_register(register))
        
        # Удаление
        if self.can_delete_register(register):
            delete_action = menu.addAction("🗑️ Удалить")
            delete_action.triggered.connect(lambda: self.delete_register(register))
        
        # Печать
        print_action = menu.addAction("🖨️ Печать")
        print_action.triggered.connect(lambda: self.print_register(register))
        
        menu.exec_(self.table.mapToGlobal(self.table.rect().bottomRight()))
    
    def can_delete_register(self, register: dict) -> bool:
        """Проверка возможности удаления"""
        role = self.user_data.get('role', 'user')
        status = register.get('status', 'draft')
        author_id = register.get('author_id')
        user_id = self.user_data.get('id')
        
        if role == 'admin':
            return True
        
        if role in ['financier', 'head'] and status != 'accepted':
            return True
        
        if role == 'user' and status == 'draft' and author_id == user_id:
            return True
        
        return False
    
    def view_register(self, register: dict):
        """Просмотр реестра"""
        from PySide6.QtWidgets import QMessageBox
        QMessageBox.information(
            self, 
            "Просмотр", 
            f"Реестр {register.get('reg_code') or 'Черновик'}\n\nФункция детального просмотра будет доступна в ближайшее время"
        )
    
    def edit_register(self, register: dict):
        """Редактирование реестра"""
        self.parent().switch_page(2)
    
    def accept_register(self, register: dict):
        """Принятие реестра"""
        reply = QMessageBox.question(
            self,
            "Принятие реестра",
            f"Принять реестр {register.get('reg_code')}?",
            QMessageBox.Yes | QMessageBox.No
        )
        
        if reply == QMessageBox.Yes:
            try:
                self.register_model.accept(register['id'])
                QMessageBox.information(self, "Успешно", "Реестр принят")
                self.refresh_data()
            except Exception as e:
                QMessageBox.critical(self, "Ошибка", str(e))
    
    def return_register(self, register: dict):
        """Возврат реестра на доработку"""
        from PySide6.QtWidgets import QInputDialog
        
        comment, ok = QInputDialog.getText(
            self,
            "Возврат на доработку",
            "Введите комментарий:"
        )
        
        if ok and comment:
            try:
                self.register_model.return_for_revision(register['id'], comment)
                QMessageBox.information(self, "Успешно", "Реестр возвращён на доработку")
                self.refresh_data()
            except Exception as e:
                QMessageBox.critical(self, "Ошибка", str(e))
    
    def delete_register(self, register: dict):
        """Удаление реестра"""
        reply = QMessageBox.question(
            self,
            "Удаление реестра",
            f"Вы действительно хотите удалить реестр {register.get('reg_code')}?",
            QMessageBox.Yes | QMessageBox.No
        )
        
        if reply == QMessageBox.Yes:
            try:
                self.register_model.delete(register['id'])
                QMessageBox.information(self, "Успешно", "Реестр удалён")
                self.refresh_data()
            except Exception as e:
                QMessageBox.critical(self, "Ошибка", str(e))
    
    def print_register(self, register: dict):
        """Печать реестра"""
        from PySide6.QtWidgets import QMessageBox
        QMessageBox.information(
            self, 
            "Печать", 
            f"Печать реестра {register.get('reg_code')}\n\nФункция печати будет доступна в ближайшее время"
        )
    
    def open_create_form(self):
        """Открытие формы создания реестра"""
        self.parent().switch_page(2)
