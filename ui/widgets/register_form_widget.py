"""
Виджет формы создания/редактирования реестра
Система учёта реестров документов
"""

from PySide6.QtWidgets import (
    QWidget, QVBoxLayout, QHBoxLayout, QLabel, QPushButton, 
    QFrame, QDateEdit, QLineEdit, QTableWidget, QTableWidgetItem,
    QMessageBox, QHeaderView, QScrollArea, QFileDialog, QListWidget, QListWidgetItem
)
from PySide6.QtCore import Qt, QDate
from PySide6.QtGui import QFont

from config import COLORS
from utils.database import get_database
from models.register import Register
from models.register_item import RegisterItem


class RegisterFormWidget(QWidget):
    """Виджет формы создания/редактирования реестра"""
    
    def __init__(self, user_data: dict, parent=None):
        super().__init__(parent)
        
        self.user_data = user_data
        self.db = get_database(None)
        self.register_model = Register(self.db)
        self.item_model = RegisterItem(self.db)
        
        self.current_register_id = None
        self.editing_mode = False
        
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
        
        # Заголовок
        self.form_title = QLabel("➕ Создание нового реестра")
        self.form_title.setFont(QFont("Segoe UI", 18, QFont.Bold))
        self.form_title.setStyleSheet(f"color: {COLORS['primary']};")
        layout.addWidget(self.form_title)
        
        # Основная информация
        main_info_section = self.create_main_info_section()
        layout.addWidget(main_info_section)
        
        # Позиции реестра
        items_section = self.create_items_section()
        layout.addWidget(items_section)
        
        # Кнопки действий
        actions_section = self.create_actions_section()
        layout.addWidget(actions_section)
        
        layout.addStretch()
        
        scroll.setWidget(container)
        
        main_layout = QVBoxLayout()
        main_layout.setContentsMargins(0, 0, 0, 0)
        main_layout.addWidget(scroll)
        
        self.setLayout(main_layout)
    
    def create_main_info_section(self) -> QFrame:
        """Создание секции основной информации"""
        section = QFrame()
        section.setStyleSheet("""
            QFrame {
                background-color: white;
                border-radius: 12px;
                padding: 20px;
            }
        """)
        
        layout = QVBoxLayout()
        layout.setSpacing(15)
        
        title = QLabel("📝 Основная информация")
        title.setFont(QFont("Segoe UI", 14, QFont.Bold))
        title.setStyleSheet(f"color: {COLORS['primary']};")
        layout.addWidget(title)
        
        # Сетка полей
        grid_layout = QGridLayout()
        grid_layout.setSpacing(15)
        
        # Дата реестра
        grid_layout.addWidget(QLabel("Дата реестра:"), 0, 0)
        self.date_input = QDateEdit()
        self.date_input.setCalendarPopup(True)
        self.date_input.setDate(QDate.currentDate())
        self.date_input.setMinimumHeight(40)
        grid_layout.addWidget(self.date_input, 0, 1)
        
        # ФИО передавшего
        grid_layout.addWidget(QLabel("ФИО передавшего:"), 1, 0)
        self.sender_fio_input = QLineEdit()
        self.sender_fio_input.setPlaceholderText("Иванов Иван Иванович")
        self.sender_fio_input.setMinimumHeight(40)
        grid_layout.addWidget(self.sender_fio_input, 1, 1)
        
        # Должность передавшего
        grid_layout.addWidget(QLabel("Должность передавшего:"), 2, 0)
        self.sender_position_input = QLineEdit()
        self.sender_position_input.setPlaceholderText("Главный специалист")
        self.sender_position_input.setMinimumHeight(40)
        grid_layout.addWidget(self.sender_position_input, 2, 1)
        
        # ФИО принявшего
        grid_layout.addWidget(QLabel("ФИО принявшего:"), 3, 0)
        self.receiver_fio_input = QLineEdit()
        self.receiver_fio_input.setPlaceholderText("Петров Петр Петрович")
        self.receiver_fio_input.setMinimumHeight(40)
        grid_layout.addWidget(self.receiver_fio_input, 3, 1)
        
        # Должность принявшего
        grid_layout.addWidget(QLabel("Должность принявшего:"), 4, 0)
        self.receiver_position_input = QLineEdit()
        self.receiver_position_input.setPlaceholderText("Начальник отдела")
        self.receiver_position_input.setMinimumHeight(40)
        grid_layout.addWidget(self.receiver_position_input, 4, 1)
        
        layout.addLayout(grid_layout)
        section.setLayout(layout)
        return section
    
    def create_items_section(self) -> QFrame:
        """Создание секции позиций реестра"""
        section = QFrame()
        section.setStyleSheet("""
            QFrame {
                background-color: white;
                border-radius: 12px;
                padding: 20px;
            }
        """)
        
        layout = QVBoxLayout()
        layout.setSpacing(15)
        
        header_layout = QHBoxLayout()
        
        title = QLabel("📋 Позиции реестра")
        title.setFont(QFont("Segoe UI", 14, QFont.Bold))
        title.setStyleSheet(f"color: {COLORS['primary']};")
        header_layout.addWidget(title)
        
        header_layout.addStretch()
        
        btn_add = QPushButton("➕ Добавить строку")
        btn_add.clicked.connect(self.add_item_row)
        header_layout.addWidget(btn_add)
        
        layout.addLayout(header_layout)
        
        # Таблица позиций
        self.items_table = QTableWidget()
        self.items_table.setColumnCount(5)
        self.items_table.setHorizontalHeaderLabels([
            "№", "Наименование документа", "Номер", "Дата", "Договор", "Примечание"
        ])
        
        header = self.items_table.horizontalHeader()
        header.setSectionResizeMode(1, QHeaderView.Stretch)
        header.setSectionResizeMode(2, QHeaderView.ResizeToContents)
        header.setSectionResizeMode(3, QHeaderView.ResizeToContents)
        header.setSectionResizeMode(4, QHeaderView.Stretch)
        
        self.items_table.setAlternatingRowColors(True)
        self.items_table.setMinimumHeight(200)
        
        layout.addWidget(self.items_table)
        
        section.setLayout(layout)
        return section
    
    def create_actions_section(self) -> QFrame:
        """Создание секции кнопок действий"""
        section = QFrame()
        section.setStyleSheet("""
            QFrame {
                background-color: white;
                border-radius: 12px;
                padding: 20px;
            }
        """)
        
        layout = QHBoxLayout()
        layout.setSpacing(15)
        
        btn_cancel = QPushButton("❌ Отмена")
        btn_cancel.setProperty("role", "secondary")
        btn_cancel.setMinimumHeight(45)
        btn_cancel.clicked.connect(self.cancel_form)
        layout.addWidget(btn_cancel)
        
        layout.addStretch()
        
        btn_save_draft = QPushButton("💾 Сохранить черновик")
        btn_save_draft.setMinimumHeight(45)
        btn_save_draft.clicked.connect(self.save_as_draft)
        layout.addWidget(btn_save_draft)
        
        btn_submit = QPushButton("📤 Отправить на проверку")
        btn_submit.setMinimumHeight(45)
        btn_submit.setStyleSheet("""
            QPushButton {
                background-color: #28a745;
                color: white;
            }
            QPushButton:hover {
                background-color: #218838;
            }
        """)
        btn_submit.clicked.connect(self.submit_for_review)
        layout.addWidget(btn_submit)
        
        section.setLayout(layout)
        return section
    
    def add_item_row(self, data: dict = None):
        """Добавление строки позиции"""
        row_count = self.items_table.rowCount()
        self.items_table.insertRow(row_count)
        
        # Номер строки
        index_item = QTableWidgetItem(str(row_count + 1))
        index_item.setFlags(Qt.ItemIsEnabled)
        self.items_table.setItem(row_count, 0, index_item)
        
        # Наименование
        name_input = QLineEdit()
        if data:
            name_input.setText(data.get('document_name', ''))
        self.items_table.setCellWidget(row_count, 1, name_input)
        
        # Номер
        number_input = QLineEdit()
        if data:
            number_input.setText(data.get('document_number', ''))
        self.items_table.setCellWidget(row_count, 2, number_input)
        
        # Дата
        from PySide6.QtWidgets import QDateEdit
        date_input = QDateEdit()
        date_input.setCalendarPopup(True)
        if data and data.get('document_date'):
            date_input.setDate(QDate.fromString(str(data['document_date']), 'yyyy-MM-dd'))
        else:
            date_input.setDate(QDate.currentDate())
        self.items_table.setCellWidget(row_count, 3, date_input)
        
        # Договор
        contract_input = QLineEdit()
        if data:
            contract_input.setText(data.get('contract_info', ''))
        self.items_table.setCellWidget(row_count, 4, contract_input)
    
    def validate_form(self) -> bool:
        """Валидация формы"""
        if not self.sender_fio_input.text().strip():
            QMessageBox.warning(self, "Ошибка", "Введите ФИО передавшего")
            self.sender_fio_input.setFocus()
            return False
        
        if not self.sender_position_input.text().strip():
            QMessageBox.warning(self, "Ошибка", "Введите должность передавшего")
            self.sender_position_input.setFocus()
            return False
        
        if not self.receiver_fio_input.text().strip():
            QMessageBox.warning(self, "Ошибка", "Введите ФИО принявшего")
            self.receiver_fio_input.setFocus()
            return False
        
        if not self.receiver_position_input.text().strip():
            QMessageBox.warning(self, "Ошибка", "Введите должность принявшего")
            self.receiver_position_input.setFocus()
            return False
        
        if self.items_table.rowCount() == 0:
            QMessageBox.warning(self, "Ошибка", "Добавьте хотя бы одну позицию в реестр")
            return False
        
        # Валидация позиций
        for row in range(self.items_table.rowCount()):
            name_widget = self.items_table.cellWidget(row, 1)
            number_widget = self.items_table.cellWidget(row, 2)
            
            if not name_widget.text().strip():
                QMessageBox.warning(self, "Ошибка", f"Введите наименование документа в строке {row + 1}")
                return False
            
            if not number_widget.text().strip():
                QMessageBox.warning(self, "Ошибка", f"Введите номер документа в строке {row + 1}")
                return False
        
        return True
    
    def save_as_draft(self):
        """Сохранение как черновик"""
        if not self.validate_form():
            return
        
        try:
            if self.editing_mode and self.current_register_id:
                # Обновление существующего
                self.update_register()
            else:
                # Создание нового
                self.create_register()
            
            QMessageBox.information(self, "Успешно", "Черновик сохранён")
            self.clear_form()
            
        except Exception as e:
            QMessageBox.critical(self, "Ошибка", str(e))
    
    def submit_for_review(self):
        """Отправка на проверку"""
        if not self.validate_form():
            return
        
        reply = QMessageBox.question(
            self,
            "Отправка на проверку",
            "Вы уверены, что хотите отправить реестр на проверку?\nПосле отправки редактирование будет недоступно.",
            QMessageBox.Yes | QMessageBox.No
        )
        
        if reply != QMessageBox.Yes:
            return
        
        try:
            if self.editing_mode and self.current_register_id:
                # Сначала обновляем, затем отправляем
                self.update_register()
                self.register_model.submit_for_review(self.current_register_id)
            else:
                # Создаём и сразу отправляем
                register_id = self.create_register()
                self.register_model.submit_for_review(register_id)
            
            QMessageBox.information(self, "Успешно", "Реестр отправлен на проверку")
            self.clear_form()
            
        except Exception as e:
            QMessageBox.critical(self, "Ошибка", str(e))
    
    def create_register(self) -> int:
        """Создание нового реестра"""
        from datetime import date
        
        register_id = self.register_model.create(
            register_date=self.date_input.date().toPyDate(),
            sender_fio=self.sender_fio_input.text().strip(),
            sender_position=self.sender_position_input.text().strip(),
            receiver_fio=self.receiver_fio_input.text().strip(),
            receiver_position=self.receiver_position_input.text().strip(),
            author_id=self.user_data.get('id'),
            author_type='user',
            status='draft'
        )
        
        # Сохранение позиций
        self.save_items(register_id)
        
        return register_id
    
    def update_register(self):
        """Обновление существующего реестра"""
        from datetime import date
        
        self.register_model.update(
            self.current_register_id,
            register_date=self.date_input.date().toPyDate(),
            sender_fio=self.sender_fio_input.text().strip(),
            sender_position=self.sender_position_input.text().strip(),
            receiver_fio=self.receiver_fio_input.text().strip(),
            receiver_position=self.receiver_position_input.text().strip(),
            status='draft'  # Возврат в черновики при редактировании
        )
        
        # Обновление позиций
        self.item_model.delete_by_register(self.current_register_id)
        self.save_items(self.current_register_id)
    
    def save_items(self, register_id: int):
        """Сохранение позиций реестра"""
        from datetime import date
        
        items = []
        for row in range(self.items_table.rowCount()):
            name_widget = self.items_table.cellWidget(row, 1)
            number_widget = self.items_table.cellWidget(row, 2)
            date_widget = self.items_table.cellWidget(row, 3)
            contract_widget = self.items_table.cellWidget(row, 4)
            
            items.append({
                'item_index': row + 1,
                'document_name': name_widget.text().strip(),
                'document_number': number_widget.text().strip(),
                'document_date': date_widget.date().toPyDate(),
                'contract_info': contract_widget.text().strip(),
                'note': ''
            })
        
        self.item_model.bulk_create(register_id, items)
    
    def clear_form(self):
        """Очистка формы"""
        self.date_input.setDate(QDate.currentDate())
        self.sender_fio_input.clear()
        self.sender_position_input.clear()
        self.receiver_fio_input.clear()
        self.receiver_position_input.clear()
        
        self.items_table.setRowCount(0)
        
        self.current_register_id = None
        self.editing_mode = False
        self.form_title.setText("➕ Создание нового реестра")
    
    def cancel_form(self):
        """Отмена и переход на главную"""
        reply = QMessageBox.question(
            self,
            "Отмена",
            "Все несохранённые данные будут потеряны. Продолжить?",
            QMessageBox.Yes | QMessageBox.No
        )
        
        if reply == QMessageBox.Yes:
            self.clear_form()
            self.parent().switch_page(0)
    
    def load_register_for_edit(self, register_id: int):
        """Загрузка реестра для редактирования"""
        register = self.register_model.get_by_id(register_id)
        
        if not register:
            return
        
        self.current_register_id = register_id
        self.editing_mode = True
        self.form_title.setText(f"✏️ Редактирование реестра {register.get('reg_code', 'Черновик')}")
        
        # Заполнение основных полей
        if register.get('register_date'):
            self.date_input.setDate(QDate.fromString(str(register['register_date']), 'yyyy-MM-dd'))
        
        self.sender_fio_input.setText(register.get('sender_fio', ''))
        self.sender_position_input.setText(register.get('sender_position', ''))
        self.receiver_fio_input.setText(register.get('receiver_fio', ''))
        self.receiver_position_input.setText(register.get('receiver_position', ''))
        
        # Загрузка позиций
        items = self.item_model.get_by_register(register_id)
        for item in items:
            self.add_item_row(item)
