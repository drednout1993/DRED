"""
Модель позиции реестра
"""

from datetime import date
from typing import Optional, Dict, Any, List


class RegisterItem:
    """Модель позиции реестра (документа в реестре)"""
    
    def __init__(self, db_connection):
        self.db = db_connection
    
    def create(
        self,
        register_id: int,
        item_index: int,
        document_name: str,
        document_number: str,
        document_date: date,
        contract_info: str,
        note: Optional[str] = None
    ) -> int:
        """Создание позиции реестра"""
        cursor = self.db.cursor()
        query = """
            INSERT INTO register_items 
            (register_id, item_index, document_name, document_number, 
             document_date, contract_info, note)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
        """
        cursor.execute(query, (
            register_id, item_index, document_name, document_number,
            document_date, contract_info, note
        ))
        self.db.commit()
        item_id = cursor.lastrowid
        cursor.close()
        return item_id
    
    def get_by_register(self, register_id: int) -> List[Dict[str, Any]]:
        """Получение всех позиций реестра"""
        cursor = self.db.cursor()
        query = """
            SELECT * FROM register_items 
            WHERE register_id = %s 
            ORDER BY item_index ASC
        """
        cursor.execute(query, (register_id,))
        items = cursor.fetchall()
        cursor.close()
        return items
    
    def get_by_id(self, item_id: int) -> Optional[Dict[str, Any]]:
        """Получение позиции по ID"""
        cursor = self.db.cursor()
        query = "SELECT * FROM register_items WHERE id = %s"
        cursor.execute(query, (item_id,))
        item = cursor.fetchone()
        cursor.close()
        return item
    
    def update(self, item_id: int, **kwargs) -> bool:
        """Обновление позиции"""
        allowed_fields = [
            'item_index', 'document_name', 'document_number',
            'document_date', 'contract_info', 'note'
        ]
        
        updates = []
        values = []
        
        for field, value in kwargs.items():
            if field in allowed_fields:
                updates.append(f"{field} = %s")
                values.append(value)
        
        if not updates:
            return False
        
        values.append(item_id)
        cursor = self.db.cursor()
        query = f"UPDATE register_items SET {', '.join(updates)} WHERE id = %s"
        cursor.execute(query, values)
        self.db.commit()
        cursor.close()
        return True
    
    def delete(self, item_id: int) -> bool:
        """Удаление позиции"""
        cursor = self.db.cursor()
        query = "DELETE FROM register_items WHERE id = %s"
        cursor.execute(query, (item_id,))
        self.db.commit()
        cursor.close()
        return True
    
    def delete_by_register(self, register_id: int) -> bool:
        """Удаление всех позиций реестра"""
        cursor = self.db.cursor()
        query = "DELETE FROM register_items WHERE register_id = %s"
        cursor.execute(query, (register_id,))
        self.db.commit()
        cursor.close()
        return True
    
    def bulk_create(self, register_id: int, items: List[Dict[str, Any]]) -> bool:
        """Массовое создание позиций реестра"""
        cursor = self.db.cursor()
        query = """
            INSERT INTO register_items 
            (register_id, item_index, document_name, document_number, 
             document_date, contract_info, note)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
        """
        
        try:
            values = []
            for item in items:
                values.append((
                    register_id,
                    item.get('item_index', 0),
                    item.get('document_name', ''),
                    item.get('document_number', ''),
                    item.get('document_date'),
                    item.get('contract_info', ''),
                    item.get('note')
                ))
            
            cursor.executemany(query, values)
            self.db.commit()
            cursor.close()
            return True
        except Exception as e:
            self.db.rollback()
            cursor.close()
            raise e
    
    def get_max_index(self, register_id: int) -> int:
        """Получение максимального индекса позиции в реестре"""
        cursor = self.db.cursor()
        query = """
            SELECT COALESCE(MAX(item_index), 0) as max_index 
            FROM register_items 
            WHERE register_id = %s
        """
        cursor.execute(query, (register_id,))
        result = cursor.fetchone()
        cursor.close()
        return result['max_index'] if result else 0
