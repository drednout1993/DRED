"""
Виджет панели управления (Dashboard)
Система учёта реестров документов
"""

from PySide6.QtWidgets import (
    QWidget, QVBoxLayout, QHBoxLayout, QLabel, 
    QFrame, QGridLayout, QPushButton, QScrollArea, QTableWidget, QTableWidgetItem
)
from PySide6.QtCore import Qt
from PySide6.QtGui import QFont

from config import COLORS, STATUS_CHOICES
from utils.database import get_database
from models.register import Register


class DashboardWidget(QWidget):
    """Виджет панели управления"""
    
    def __init__(self, user_data: dict, parent=None):
        super().__init__(parent)
        
        self.user_data = user_data
        self.db = get_database(None)
        self.register_model = Register(self.db)
        
        self.setup_ui()
    
    def setup_ui(self):
        """Настройка интерфейса"""
        scroll = QScrollArea()
        scroll.setWidgetResizable(True)
        scroll.setStyleSheet("border: none; background-color: transparent;")
        
        container = QWidget()
        layout = QVBoxLayout(container)
        layout.setSpacing(20)
        layout.setContentsMargins(30, 30, 30, 30)
        
        # Статистика
        stats_layout = self.create_stats_section()
        layout.addLayout(stats_layout)
        
        # Быстрые действия
        actions_section = self.create_actions_section()
        layout.addWidget(actions_section)
        
        # Последние реестры
        registers_section = self.create_registers_section()
        layout.addWidget(registers_section)
        
        layout.addStretch()
        
        scroll.setWidget(container)
        
        main_layout = QVBoxLayout()
        main_layout.setContentsMargins(0, 0, 0, 0)
        main_layout.addWidget(scroll)
        
        self.setLayout(main_layout)
    
    def create_stats_section(self) -> QGridLayout:
        """Создание секции статистики"""
        grid = QGridLayout()
        grid.setSpacing(15)
        
        # Получение статистики
        stats = self.register_model.get_statistics()
        
        # Карточки статистики
        cards = [
            ("📊 Всего", str(stats.get('total', 0)), COLORS['primary']),
            ("✅ Принято", str(stats.get('accepted', 0)), COLORS['success']),
            ("🔍 На проверке", str(stats.get('in_review', 0)), COLORS['warning']),
            ("⚠️ На доработке", str(stats.get('returned', 0)), COLORS['danger']),
        ]
        
        self.stat_cards = {}
        
        for i, (title, value, color) in enumerate(cards):
            card = self.create_stat_card(title, value, color)
            row = i // 2
            col = i % 2
            grid.addWidget(card, row, col)
            self.stat_cards[title] = card
        
        return grid
    
    def create_stat_card(self, title: str, value: str, color: str) -> QFrame:
        """Создание карточки статистики"""
        card = QFrame()
        card.setStyleSheet(f"""
            QFrame {{
                background-color: white;
                border-radius: 12px;
                padding: 20px;
                border-left: 4px solid {color};
            }}
        """)
        
        layout = QVBoxLayout()
        
        title_label = QLabel(title)
        title_label.setFont(QFont("Segoe UI", 12))
        title_label.setStyleSheet("color: #666666;")
        layout.addWidget(title_label)
        
        value_label = QLabel(value)
        value_label.setFont(QFont("Segoe UI", 28, QFont.Bold))
        value_label.setStyleSheet(f"color: {color};")
        layout.addWidget(value_label)
        
        card.setLayout(layout)
        return card
    
    def create_actions_section(self) -> QFrame:
        """Создание секции быстрых действий"""
        section = QFrame()
        section.setStyleSheet("""
            QFrame {
                background-color: white;
                border-radius: 12px;
                padding: 20px;
            }
        """)
        
        layout = QVBoxLayout()
        
        title = QLabel("⚡ Быстрые действия")
        title.setFont(QFont("Segoe UI", 14, QFont.Bold))
        title.setStyleSheet(f"color: {COLORS['primary']}; margin-bottom: 15px;")
        layout.addWidget(title)
        
        buttons_layout = QHBoxLayout()
        buttons_layout.setSpacing(15)
        
        # Кнопки действий
        actions = [
            ("➕ Новый реестр", self.open_create_form),
            ("📊 Журнал", self.open_journal),
            ("📄 Печать бланка", self.print_blank),
            ("📋 Журнал печати", self.print_journal),
        ]
        
        for text, handler in actions:
            btn = QPushButton(text)
            btn.setMinimumHeight(50)
            btn.setCursor(Qt.PointingHandCursor)
            btn.clicked.connect(handler)
            
            # Скрытие недоступных кнопок для обычных пользователей
            role = self.user_data.get('role', 'user')
            if text == "📋 Журнал печати" and role == 'user':
                btn.setVisible(False)
            
            buttons_layout.addWidget(btn)
        
        layout.addLayout(buttons_layout)
        section.setLayout(layout)
        return section
    
    def create_registers_section(self) -> QFrame:
        """Создание секции последних реестров"""
        section = QFrame()
        section.setStyleSheet("""
            QFrame {
                background-color: white;
                border-radius: 12px;
                padding: 20px;
            }
        """)
        
        layout = QVBoxLayout()
        
        header_layout = QHBoxLayout()
        
        title = QLabel("📋 Последние реестры")
        title.setFont(QFont("Segoe UI", 14, QFont.Bold))
        title.setStyleSheet(f"color: {COLORS['primary']};")
        header_layout.addWidget(title)
        
        header_layout.addStretch()
        
        btn_all = QPushButton("Все реестры →")
        btn_all.setProperty("role", "secondary")
        btn_all.clicked.connect(self.open_journal)
        header_layout.addWidget(btn_all)
        
        layout.addLayout(header_layout)
        
        # Таблица
        self.registers_table = QTableWidget()
        self.registers_table.setColumnCount(4)
        self.registers_table.setHorizontalHeaderLabels([
            "Код", "Дата", "Автор", "Статус"
        ])
        self.registers_table.horizontalHeader().setStretchLastSection(True)
        self.registers_table.setAlternatingRowColors(True)
        self.registers_table.setSelectionBehavior(QTableWidget.SelectRows)
        self.registers_table.setMaximumHeight(300)
        
        layout.addWidget(self.registers_table)
        
        section.setLayout(layout)
        return section
    
    def refresh_data(self):
        """Обновление данных"""
        # Обновление статистики
        stats = self.register_model.get_statistics()
        
        stat_values = {
            "📊 Всего": str(stats.get('total', 0)),
            "✅ Принято": str(stats.get('accepted', 0)),
            "🔍 На проверке": str(stats.get('in_review', 0)),
            "⚠️ На доработке": str(stats.get('returned', 0)),
        }
        
        for title, card in self.stat_cards.items():
            value_label = card.layout().itemAt(1).widget()
            value_label.setText(stat_values.get(title, "0"))
        
        # Обновление таблицы
        self.load_recent_registers()
    
    def load_recent_registers(self):
        """Загрузка последних реестров"""
        registers = self.register_model.get_all()[:5]  # Только первые 5
        
        self.registers_table.setRowCount(len(registers))
        
        for i, reg in enumerate(registers):
            code = reg.get('reg_code') or 'Черновик'
            date = reg.get('register_date', '')
            author = reg.get('author_fio') or reg.get('sender_fio', '')
            status = STATUS_CHOICES.get(reg.get('status', 'draft'), '')
            
            self.registers_table.setItem(i, 0, QTableWidgetItem(code))
            self.registers_table.setItem(i, 1, QTableWidgetItem(str(date)))
            self.registers_table.setItem(i, 2, QTableWidgetItem(author))
            
            status_item = QTableWidgetItem(status)
            status_item.setBackground(self.get_status_color(reg.get('status')))
            self.registers_table.setItem(i, 3, status_item)
    
    def get_status_color(self, status: str):
        """Получение цвета статуса"""
        colors = {
            'draft': '#cccccc',
            'submitted': '#ffc107',
            'in_review': '#17a2b8',
            'returned': '#dc3545',
            'accepted': '#28a745',
        }
        from PySide6.QtGui import QColor
        return QColor(colors.get(status, '#cccccc'))
    
    def open_create_form(self):
        """Открытие формы создания реестра"""
        self.parent().switch_page(2)
    
    def open_journal(self):
        """Открытие журнала реестров"""
        self.parent().switch_page(1)
    
    def print_blank(self):
        """Печать пустого бланка"""
        from PySide6.QtWidgets import QMessageBox
        QMessageBox.information(self, "Печать", "Функция печати бланка будет доступна в ближайшее время")
    
    def print_journal(self):
        """Печать журнала"""
        from PySide6.QtWidgets import QMessageBox
        QMessageBox.information(self, "Печать", "Функция печати журнала будет доступна в ближайшее время")
