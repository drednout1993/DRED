"""
Модель истории изменений реестра
"""

from datetime import datetime
from typing import Optional, Dict, Any, List


class RegisterHistory:
    """Модель истории изменений реестра"""
    
    def __init__(self, db_connection):
        self.db = db_connection
    
    def create(
        self,
        register_id: int,
        action: str,
        user_id: Optional[int] = None,
        old_status: Optional[str] = None,
        new_status: Optional[str] = None,
        comment: Optional[str] = None,
        ip_address: Optional[str] = None
    ) -> int:
        """Создание записи в истории"""
        cursor = self.db.cursor()
        query = """
            INSERT INTO register_history 
            (register_id, user_id, action, old_status, new_status, 
             comment, ip_address)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
        """
        cursor.execute(query, (
            register_id, user_id, action, old_status, 
            new_status, comment, ip_address
        ))
        self.db.commit()
        history_id = cursor.lastrowid
        cursor.close()
        return history_id
    
    def get_by_register(self, register_id: int) -> List[Dict[str, Any]]:
        """Получение истории изменений реестра"""
        cursor = self.db.cursor()
        query = """
            SELECT h.*, u.fio as user_fio, u.role as user_role
            FROM register_history h
            LEFT JOIN users u ON h.user_id = u.id
            WHERE h.register_id = %s
            ORDER BY h.created_at ASC
        """
        cursor.execute(query, (register_id,))
        history = cursor.fetchall()
        cursor.close()
        return history
    
    def get_all(
        self,
        register_id: Optional[int] = None,
        user_id: Optional[int] = None,
        date_from: Optional[datetime] = None,
        date_to: Optional[datetime] = None,
        action_filter: Optional[str] = None
    ) -> List[Dict[str, Any]]:
        """Получение всей истории с фильтрацией"""
        cursor = self.db.cursor()
        
        conditions = []
        params = []
        
        if register_id:
            conditions.append("h.register_id = %s")
            params.append(register_id)
        
        if user_id:
            conditions.append("h.user_id = %s")
            params.append(user_id)
        
        if date_from:
            conditions.append("h.created_at >= %s")
            params.append(date_from)
        
        if date_to:
            conditions.append("h.created_at <= %s")
            params.append(date_to)
        
        if action_filter:
            conditions.append("h.action LIKE %s")
            params.append(f"%{action_filter}%")
        
        where_clause = ""
        if conditions:
            where_clause = "WHERE " + " AND ".join(conditions)
        
        query = f"""
            SELECT h.*, u.fio as user_fio, u.role as user_role
            FROM register_history h
            LEFT JOIN users u ON h.user_id = u.id
            {where_clause}
            ORDER BY h.created_at DESC
        """
        
        cursor.execute(query, params)
        history = cursor.fetchall()
        cursor.close()
        return history
    
    def log_status_change(
        self,
        register_id: int,
        old_status: str,
        new_status: str,
        user_id: Optional[int] = None,
        comment: Optional[str] = None,
        ip_address: Optional[str] = None
    ) -> int:
        """Логирование изменения статуса"""
        action_map = {
            ('draft', 'submitted'): 'Отправлен на проверку',
            ('submitted', 'in_review'): 'Открыт на проверку',
            ('in_review', 'accepted'): 'Принят',
            ('in_review', 'returned'): 'Возвращён на доработку',
            ('returned', 'draft'): 'Редактируется после возврата',
            ('returned', 'submitted'): 'Отправлен повторно',
        }
        
        action_key = (old_status, new_status)
        action = action_map.get(action_key, f'Изменён статус: {old_status} → {new_status}')
        
        return self.create(
            register_id=register_id,
            action=action,
            user_id=user_id,
            old_status=old_status,
            new_status=new_status,
            comment=comment,
            ip_address=ip_address
        )
    
    def log_creation(
        self,
        register_id: int,
        user_id: Optional[int] = None,
        ip_address: Optional[str] = None
    ) -> int:
        """Логирование создания реестра"""
        return self.create(
            register_id=register_id,
            action='Создан черновик',
            user_id=user_id,
            new_status='draft',
            ip_address=ip_address
        )
    
    def log_deletion(
        self,
        register_id: int,
        user_id: Optional[int] = None,
        old_status: Optional[str] = None,
        comment: Optional[str] = None,
        ip_address: Optional[str] = None
    ) -> int:
        """Логирование удаления реестра"""
        return self.create(
            register_id=register_id,
            action='Удалён',
            user_id=user_id,
            old_status=old_status,
            comment=comment or 'Реестр удалён',
            ip_address=ip_address
        )
    
    def log_attachment_action(
        self,
        register_id: int,
        action_type: str,  # 'added' or 'removed'
        file_name: str,
        user_id: Optional[int] = None,
        ip_address: Optional[str] = None
    ) -> int:
        """Логирование действий с вложениями"""
        action = f"Файл {file_name} {'добавлен' if action_type == 'added' else 'удалён'}"
        
        return self.create(
            register_id=register_id,
            action=action,
            user_id=user_id,
            ip_address=ip_address
        )
    
    def log_print(
        self,
        register_id: int,
        document_type: str,  # 'register', 'blank', 'journal'
        user_id: Optional[int] = None,
        ip_address: Optional[str] = None
    ) -> int:
        """Логирование печати документа"""
        type_names = {
            'register': 'реестр',
            'blank': 'пустой бланк',
            'journal': 'журнал'
        }
        
        action = f"Печать: {type_names.get(document_type, document_type)}"
        
        return self.create(
            register_id=register_id,
            action=action,
            user_id=user_id,
            ip_address=ip_address
        )
