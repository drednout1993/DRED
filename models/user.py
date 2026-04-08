"""
Модель пользователя
"""

import bcrypt
from datetime import datetime
from typing import Optional, Dict, Any


class User:
    """Модель пользователя системы"""
    
    def __init__(self, db_connection):
        self.db = db_connection
    
    def create(
        self,
        username: str,
        password: str,
        fio: str,
        role: str = 'user',
        email: Optional[str] = None,
        position: Optional[str] = None,
        department: Optional[str] = None,
        is_active: bool = True
    ) -> int:
        """Создание нового пользователя"""
        password_hash = self.hash_password(password)
        
        cursor = self.db.cursor()
        query = """
            INSERT INTO users (username, password_hash, fio, role, email, position, department, is_active)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
        """
        cursor.execute(query, (
            username, password_hash, fio, role, email, position, 
            department or 'Финансово-экономический отдел', is_active
        ))
        self.db.commit()
        user_id = cursor.lastrowid
        cursor.close()
        return user_id
    
    def get_by_id(self, user_id: int) -> Optional[Dict[str, Any]]:
        """Получение пользователя по ID"""
        cursor = self.db.cursor()
        query = "SELECT * FROM users WHERE id = %s"
        cursor.execute(query, (user_id,))
        user = cursor.fetchone()
        cursor.close()
        return user
    
    def get_by_username(self, username: str) -> Optional[Dict[str, Any]]:
        """Получение пользователя по логину"""
        cursor = self.db.cursor()
        query = "SELECT * FROM users WHERE username = %s"
        cursor.execute(query, (username,))
        user = cursor.fetchone()
        cursor.close()
        return user
    
    def get_all(self, active_only: bool = False) -> list:
        """Получение всех пользователей"""
        cursor = self.db.cursor()
        if active_only:
            query = "SELECT * FROM users WHERE is_active = TRUE ORDER BY fio"
        else:
            query = "SELECT * FROM users ORDER BY fio"
        cursor.execute(query)
        users = cursor.fetchall()
        cursor.close()
        return users
    
    def authenticate(self, username: str, password: str) -> Optional[Dict[str, Any]]:
        """Аутентификация пользователя"""
        user = self.get_by_username(username)
        if user and self.verify_password(password, user['password_hash']):
            # Обновляем время последнего входа
            self.update_last_login(user['id'])
            return user
        return None
    
    def verify_password(self, password: str, password_hash: str) -> bool:
        """Проверка пароля"""
        try:
            return bcrypt.checkpw(password.encode('utf-8'), password_hash.encode('utf-8'))
        except Exception:
            return False
    
    def hash_password(self, password: str) -> str:
        """Хэширование пароля"""
        salt = bcrypt.gensalt(rounds=12)
        hashed = bcrypt.hashpw(password.encode('utf-8'), salt)
        return hashed.decode('utf-8')
    
    def update_last_login(self, user_id: int):
        """Обновление времени последнего входа"""
        cursor = self.db.cursor()
        query = "UPDATE users SET last_login = NOW() WHERE id = %s"
        cursor.execute(query, (user_id,))
        self.db.commit()
        cursor.close()
    
    def update(self, user_id: int, **kwargs) -> bool:
        """Обновление данных пользователя"""
        allowed_fields = ['fio', 'email', 'position', 'department', 'role', 'is_active']
        updates = []
        values = []
        
        for field, value in kwargs.items():
            if field in allowed_fields:
                updates.append(f"{field} = %s")
                values.append(value)
        
        if not updates:
            return False
        
        values.append(user_id)
        cursor = self.db.cursor()
        query = f"UPDATE users SET {', '.join(updates)} WHERE id = %s"
        cursor.execute(query, values)
        self.db.commit()
        cursor.close()
        return True
    
    def change_password(self, user_id: int, new_password: str) -> bool:
        """Смена пароля пользователя"""
        password_hash = self.hash_password(new_password)
        return self.update(user_id, password_hash=password_hash)
    
    def delete(self, user_id: int) -> bool:
        """Удаление пользователя (деактивация)"""
        return self.update(user_id, is_active=False)
    
    def get_users_by_role(self, role: str) -> list:
        """Получение пользователей по роли"""
        cursor = self.db.cursor()
        query = "SELECT * FROM users WHERE role = %s AND is_active = TRUE ORDER BY fio"
        cursor.execute(query, (role,))
        users = cursor.fetchall()
        cursor.close()
        return users
