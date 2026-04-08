"""
Модель настроек системы
"""

from typing import Optional, Dict, Any, List


class SystemSetting:
    """Модель настроек системы"""
    
    def __init__(self, db_connection):
        self.db = db_connection
    
    def create(
        self,
        setting_key: str,
        setting_value: str,
        setting_type: str = 'string',
        description: Optional[str] = None,
        updated_by: Optional[int] = None
    ) -> int:
        """Создание настройки"""
        cursor = self.db.cursor()
        query = """
            INSERT INTO system_settings 
            (setting_key, setting_value, setting_type, description, updated_by)
            VALUES (%s, %s, %s, %s, %s)
        """
        cursor.execute(query, (
            setting_key, setting_value, setting_type, description, updated_by
        ))
        self.db.commit()
        setting_id = cursor.lastrowid
        cursor.close()
        return setting_id
    
    def get_by_key(self, setting_key: str) -> Optional[Dict[str, Any]]:
        """Получение настройки по ключу"""
        cursor = self.db.cursor()
        query = "SELECT * FROM system_settings WHERE setting_key = %s"
        cursor.execute(query, (setting_key,))
        setting = cursor.fetchone()
        cursor.close()
        return setting
    
    def get_value(self, setting_key: str, default: Any = None) -> Any:
        """Получение значения настройки"""
        setting = self.get_by_key(setting_key)
        
        if not setting:
            return default
        
        value = setting['setting_value']
        setting_type = setting['setting_type']
        
        # Преобразование типа
        if setting_type == 'integer':
            return int(value) if value else default
        elif setting_type == 'boolean':
            return value == '1' or value.lower() == 'true'
        elif setting_type == 'float':
            return float(value) if value else default
        else:
            return value
    
    def set_value(
        self,
        setting_key: str,
        setting_value: str,
        updated_by: Optional[int] = None
    ) -> bool:
        """Установка значения настройки"""
        cursor = self.db.cursor()
        
        # Проверка существования настройки
        existing = self.get_by_key(setting_key)
        
        if existing:
            # Обновление
            query = """
                UPDATE system_settings 
                SET setting_value = %s, updated_by = %s
                WHERE setting_key = %s
            """
            cursor.execute(query, (setting_value, updated_by, setting_key))
        else:
            # Создание
            query = """
                INSERT INTO system_settings 
                (setting_key, setting_value, updated_by)
                VALUES (%s, %s, %s)
            """
            cursor.execute(query, (setting_key, setting_value, updated_by))
        
        self.db.commit()
        cursor.close()
        return True
    
    def get_all(self) -> List[Dict[str, Any]]:
        """Получение всех настроек"""
        cursor = self.db.cursor()
        query = "SELECT * FROM system_settings ORDER BY setting_key"
        cursor.execute(query)
        settings = cursor.fetchall()
        cursor.close()
        return settings
    
    def get_as_dict(self) -> Dict[str, Any]:
        """Получение всех настроек в виде словаря {key: value}"""
        settings = self.get_all()
        result = {}
        
        for setting in settings:
            key = setting['setting_key']
            value = self.get_value(key, setting['setting_value'])
            result[key] = value
        
        return result
    
    def delete(self, setting_key: str) -> bool:
        """Удаление настройки"""
        cursor = self.db.cursor()
        query = "DELETE FROM system_settings WHERE setting_key = %s"
        cursor.execute(query, (setting_key,))
        self.db.commit()
        cursor.close()
        return True
    
    # Методы для конкретных настроек
    
    def get_next_reg_number(self) -> int:
        """Получение следующего номера для генерации кода реестра"""
        return self.get_value('next_reg_number', 1000)
    
    def increment_reg_number(self) -> int:
        """Увеличение счётчика номеров реестров"""
        current = self.get_next_reg_number()
        self.set_value('next_reg_number', str(current + 1))
        return current
    
    def get_max_attachments(self) -> int:
        """Получение максимального количества вложений"""
        return self.get_value('max_attachments', 5)
    
    def is_email_notifications_enabled(self) -> bool:
        """Проверка включены ли email уведомления"""
        return self.get_value('email_notifications', True)
    
    def get_default_pdf_copies(self) -> int:
        """Получение количества копий PDF по умолчанию"""
        return self.get_value('default_pdf_copies', 1)
