"""
Модели данных системы учёта реестров документов
"""

from .user import User
from .register import Register
from .register_item import RegisterItem
from .attachment import Attachment
from .register_history import RegisterHistory
from .system_setting import SystemSetting

__all__ = [
    "User",
    "Register",
    "RegisterItem",
    "Attachment",
    "RegisterHistory",
    "SystemSetting",
]

