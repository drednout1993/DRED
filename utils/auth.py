"""
Утилиты аутентификации и авторизации
Система учёта реестров документов
"""

import bcrypt
from typing import Optional, Dict, Any
from datetime import datetime, timedelta


class AuthService:
    """Сервис аутентификации и авторизации"""
    
    def __init__(self, user_model):
        self.user_model = user_model
        self.sessions: Dict[str, Dict[str, Any]] = {}
        self.session_duration_hours = 8
    
    def authenticate(self, username: str, password: str) -> Optional[Dict[str, Any]]:
        """
        Аутентификация пользователя по логину и паролю
        
        Returns:
            Dict с данными пользователя или None при неудаче
        """
        user = self.user_model.authenticate(username, password)
        
        if user:
            # Проверка активности пользователя
            if not user.get('is_active', True):
                return None
            
            # Создание сессии
            session_token = self._create_session(user)
            user['session_token'] = session_token
            
            return user
        
        return None
    
    def _create_session(self, user: Dict[str, Any]) -> str:
        """Создание сессии для пользователя"""
        import secrets
        
        session_token = secrets.token_urlsafe(32)
        
        self.sessions[session_token] = {
            'user_id': user['id'],
            'username': user['username'],
            'role': user['role'],
            'created_at': datetime.now(),
            'expires_at': datetime.now() + timedelta(hours=self.session_duration_hours)
        }
        
        return session_token
    
    def validate_session(self, session_token: str) -> Optional[Dict[str, Any]]:
        """Проверка валидности сессии"""
        session = self.sessions.get(session_token)
        
        if not session:
            return None
        
        # Проверка истечения сессии
        if datetime.now() > session['expires_at']:
            self.logout(session_token)
            return None
        
        # Получение актуальных данных пользователя
        user = self.user_model.get_by_id(session['user_id'])
        
        if not user or not user.get('is_active', True):
            self.logout(session_token)
            return None
        
        return user
    
    def logout(self, session_token: str):
        """Завершение сессии"""
        if session_token in self.sessions:
            del self.sessions[session_token]
    
    def has_permission(self, user: Dict[str, Any], permission: str) -> bool:
        """
        Проверка прав доступа пользователя
        
        Args:
            user: Данные пользователя
            permission: Название права доступа
        
        Returns:
            True если право доступно, False иначе
        """
        role = user.get('role', 'user')
        
        # Матрица прав доступа
        permissions_matrix = {
            'view_all_registers': ['admin', 'financier', 'head'],
            'view_own_registers': ['admin', 'financier', 'head', 'user'],
            'create_register': ['admin', 'financier', 'head', 'user'],
            'edit_draft': ['admin', 'user'],
            'edit_returned': ['admin', 'user'],
            'submit_for_review': ['admin', 'user'],
            'change_status': ['admin', 'financier', 'head'],
            'add_comment': ['admin', 'financier', 'head'],
            'delete_draft': ['admin', 'financier', 'head', 'user'],
            'delete_before_accept': ['admin', 'financier', 'head'],
            'delete_accepted': ['admin'],
            'print_register': ['admin', 'financier', 'head', 'user'],
            'print_journal': ['admin', 'financier', 'head'],
            'manage_users': ['admin'],
            'system_settings': ['admin'],
        }
        
        allowed_roles = permissions_matrix.get(permission, [])
        return role in allowed_roles
    
    def is_admin(self, user: Dict[str, Any]) -> bool:
        """Проверка является ли пользователь администратором"""
        return user.get('role') == 'admin'
    
    def is_financier(self, user: Dict[str, Any]) -> bool:
        """Проверка является ли пользователь финансистом"""
        return user.get('role') in ['financier', 'head']
    
    def can_edit_register(self, user: Dict[str, Any], register: Dict[str, Any]) -> bool:
        """Проверка возможности редактирования реестра"""
        role = user.get('role', 'user')
        status = register.get('status', 'draft')
        author_id = register.get('author_id')
        user_id = user.get('id')
        
        # Администратор может редактировать всё
        if role == 'admin':
            return True
        
        # Пользователь может редактировать только свои черновики и возвращённые
        if role == 'user' and author_id == user_id:
            return status in ['draft', 'returned']
        
        return False
    
    def can_delete_register(self, user: Dict[str, Any], register: Dict[str, Any]) -> bool:
        """Проверка возможности удаления реестра"""
        role = user.get('role', 'user')
        status = register.get('status', 'draft')
        author_id = register.get('author_id')
        user_id = user.get('id')
        
        # Администратор может удалять всё
        if role == 'admin':
            return True
        
        # Финансист и руководитель могут удалять до статуса "принят"
        if role in ['financier', 'head'] and status != 'accepted':
            return True
        
        # Пользователь может удалять только свои черновики
        if role == 'user' and status == 'draft' and author_id == user_id:
            return True
        
        return False
    
    def can_change_status(self, user: Dict[str, Any]) -> bool:
        """Проверка возможности изменения статуса реестра"""
        return user.get('role') in ['admin', 'financier', 'head']


def hash_password(password: str, rounds: int = 12) -> str:
    """Хэширование пароля"""
    salt = bcrypt.gensalt(rounds=rounds)
    hashed = bcrypt.hashpw(password.encode('utf-8'), salt)
    return hashed.decode('utf-8')


def verify_password(password: str, password_hash: str) -> bool:
    """Проверка пароля"""
    try:
        return bcrypt.checkpw(password.encode('utf-8'), password_hash.encode('utf-8'))
    except Exception:
        return False
