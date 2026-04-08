"""
Модель реестра документов
"""

import random
import string
from datetime import datetime, date
from typing import Optional, Dict, Any, List


class Register:
    """Модель реестра документов"""
    
    def __init__(self, db_connection):
        self.db = db_connection
    
    def create(
        self,
        register_date: date,
        sender_fio: str,
        sender_position: str,
        receiver_fio: str,
        receiver_position: str,
        author_id: Optional[int] = None,
        author_type: str = 'user',
        status: str = 'draft'
    ) -> int:
        """Создание нового реестра (черновик)"""
        cursor = self.db.cursor()
        query = """
            INSERT INTO registers (register_date, sender_fio, sender_position, 
                                   receiver_fio, receiver_position, author_id, 
                                   author_type, status)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
        """
        cursor.execute(query, (
            register_date, sender_fio, sender_position,
            receiver_fio, receiver_position, author_id, author_type, status
        ))
        self.db.commit()
        register_id = cursor.lastrowid
        cursor.close()
        return register_id
    
    def get_by_id(self, register_id: int) -> Optional[Dict[str, Any]]:
        """Получение реестра по ID"""
        cursor = self.db.cursor()
        query = """
            SELECT r.*, u.fio as author_fio, u.position as author_position
            FROM registers r
            LEFT JOIN users u ON r.author_id = u.id
            WHERE r.id = %s
        """
        cursor.execute(query, (register_id,))
        register = cursor.fetchone()
        cursor.close()
        return register
    
    def get_by_code(self, reg_code: str) -> Optional[Dict[str, Any]]:
        """Получение реестра по коду"""
        cursor = self.db.cursor()
        query = "SELECT * FROM registers WHERE reg_code = %s"
        cursor.execute(query, (reg_code,))
        register = cursor.fetchone()
        cursor.close()
        return register
    
    def get_all(
        self,
        status_filter: Optional[str] = None,
        author_id: Optional[int] = None,
        date_from: Optional[date] = None,
        date_to: Optional[date] = None,
        search_term: Optional[str] = None
    ) -> List[Dict[str, Any]]:
        """Получение списка реестров с фильтрацией"""
        cursor = self.db.cursor()
        
        conditions = []
        params = []
        
        if status_filter:
            conditions.append("r.status = %s")
            params.append(status_filter)
        
        if author_id:
            conditions.append("r.author_id = %s")
            params.append(author_id)
        
        if date_from:
            conditions.append("r.register_date >= %s")
            params.append(date_from)
        
        if date_to:
            conditions.append("r.register_date <= %s")
            params.append(date_to)
        
        if search_term:
            conditions.append("(r.sender_fio LIKE %s OR r.reg_code LIKE %s)")
            params.append(f"%{search_term}%")
            params.append(f"%{search_term}%")
        
        where_clause = ""
        if conditions:
            where_clause = "WHERE " + " AND ".join(conditions)
        
        query = f"""
            SELECT r.*, u.fio as author_fio, u.position as author_position
            FROM registers r
            LEFT JOIN users u ON r.author_id = u.id
            {where_clause}
            ORDER BY r.created_at DESC
        """
        
        cursor.execute(query, params)
        registers = cursor.fetchall()
        cursor.close()
        return registers
    
    def get_drafts_by_author(self, author_id: int) -> List[Dict[str, Any]]:
        """Получение черновиков автора"""
        cursor = self.db.cursor()
        query = """
            SELECT * FROM registers 
            WHERE author_id = %s AND status = 'draft'
            ORDER BY created_at DESC
        """
        cursor.execute(query, (author_id,))
        drafts = cursor.fetchall()
        cursor.close()
        return drafts
    
    def update(self, register_id: int, **kwargs) -> bool:
        """Обновление данных реестра"""
        allowed_fields = [
            'register_date', 'sender_fio', 'sender_position',
            'receiver_fio', 'receiver_position', 'status', 
            'return_comment', 'submitted_at', 'accepted_at'
        ]
        
        updates = []
        values = []
        
        for field, value in kwargs.items():
            if field in allowed_fields:
                updates.append(f"{field} = %s")
                values.append(value)
        
        if not updates:
            return False
        
        values.append(register_id)
        cursor = self.db.cursor()
        query = f"UPDATE registers SET {', '.join(updates)} WHERE id = %s"
        cursor.execute(query, values)
        self.db.commit()
        cursor.close()
        return True
    
    def generate_reg_code(self) -> str:
        """Генерация уникального кода реестра формата R-XXXX"""
        chars = string.ascii_uppercase + string.digits
        
        cursor = self.db.cursor()
        max_attempts = 10
        
        for _ in range(max_attempts):
            code_suffix = ''.join(random.choices(chars, k=4))
            reg_code = f"R-{code_suffix}"
            
            # Проверка на уникальность
            check_query = "SELECT id FROM registers WHERE reg_code = %s"
            cursor.execute(check_query, (reg_code,))
            
            if not cursor.fetchone():
                cursor.close()
                return reg_code
        
        cursor.close()
        raise ValueError("Не удалось сгенерировать уникальный код реестра")
    
    def submit_for_review(self, register_id: int) -> bool:
        """Отправка реестра на проверку"""
        reg_code = self.generate_reg_code()
        return self.update(
            register_id,
            reg_code=reg_code,
            status='submitted',
            submitted_at=datetime.now()
        )
    
    def accept(self, register_id: int) -> bool:
        """Принятие реестра"""
        return self.update(
            register_id,
            status='accepted',
            accepted_at=datetime.now()
        )
    
    def return_for_revision(self, register_id: int, comment: str) -> bool:
        """Возврат реестра на доработку"""
        return self.update(
            register_id,
            status='returned',
            return_comment=comment
        )
    
    def delete(self, register_id: int) -> bool:
        """Удаление реестра"""
        cursor = self.db.cursor()
        query = "DELETE FROM registers WHERE id = %s"
        cursor.execute(query, (register_id,))
        self.db.commit()
        cursor.close()
        return True
    
    def can_delete(self, register: Dict[str, Any], user_role: str, user_id: int) -> bool:
        """Проверка возможности удаления реестра"""
        status = register.get('status')
        author_id = register.get('author_id')
        
        # Администратор может удалять всё
        if user_role == 'admin':
            return True
        
        # Финансист и руководитель могут удалять до статуса "принят"
        if user_role in ['financier', 'head'] and status != 'accepted':
            return True
        
        # Пользователь может удалять только свои черновики
        if user_role == 'user' and status == 'draft' and author_id == user_id:
            return True
        
        return False
    
    def get_statistics(self) -> Dict[str, int]:
        """Получение статистики по реестрам"""
        cursor = self.db.cursor()
        query = """
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                SUM(CASE WHEN status = 'in_review' THEN 1 ELSE 0 END) as in_review,
                SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft
            FROM registers
        """
        cursor.execute(query)
        stats = cursor.fetchone()
        cursor.close()
        return stats or {
            'total': 0, 'accepted': 0, 'in_review': 0, 
            'returned': 0, 'draft': 0
        }
    
    def get_user_statistics(self, user_id: int) -> Dict[str, int]:
        """Получение статистики по реестрам пользователя"""
        cursor = self.db.cursor()
        query = """
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft
            FROM registers
            WHERE author_id = %s
        """
        cursor.execute(query, (user_id,))
        stats = cursor.fetchone()
        cursor.close()
        return stats or {
            'total': 0, 'accepted': 0, 'returned': 0, 'draft': 0
        }
