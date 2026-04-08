"""
Модель вложения
"""

import os
import uuid
from datetime import datetime
from typing import Optional, Dict, Any, List
from pathlib import Path


class Attachment:
    """Модель вложения (файла)"""
    
    def __init__(self, db_connection, upload_folder: str = 'uploads'):
        self.db = db_connection
        self.upload_folder = Path(upload_folder)
        self.allowed_extensions = {'.pdf', '.docx', '.xlsx', '.jpg', '.png', '.jpeg'}
        self.max_file_size_mb = 16
    
    def create(
        self,
        register_id: int,
        file_path: str,
        file_name: str,
        file_size: int,
        file_type: str,
        uploaded_by: Optional[int] = None,
        storage: str = 'local'
    ) -> int:
        """Создание записи о вложении"""
        cursor = self.db.cursor()
        query = """
            INSERT INTO attachments 
            (register_id, file_name, file_path, file_size, file_type, 
             uploaded_by, storage)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
        """
        cursor.execute(query, (
            register_id, file_name, file_path, file_size, 
            file_type, uploaded_by, storage
        ))
        self.db.commit()
        attachment_id = cursor.lastrowid
        cursor.close()
        return attachment_id
    
    def get_by_register(self, register_id: int) -> List[Dict[str, Any]]:
        """Получение всех вложений реестра"""
        cursor = self.db.cursor()
        query = """
            SELECT a.*, u.fio as uploader_fio
            FROM attachments a
            LEFT JOIN users u ON a.uploaded_by = u.id
            WHERE a.register_id = %s
            ORDER BY uploaded_at ASC
        """
        cursor.execute(query, (register_id,))
        attachments = cursor.fetchall()
        cursor.close()
        return attachments
    
    def get_by_id(self, attachment_id: int) -> Optional[Dict[str, Any]]:
        """Получение вложения по ID"""
        cursor = self.db.cursor()
        query = "SELECT * FROM attachments WHERE id = %s"
        cursor.execute(query, (attachment_id,))
        attachment = cursor.fetchone()
        cursor.close()
        return attachment
    
    def get_count_by_register(self, register_id: int) -> int:
        """Получение количества вложений реестра"""
        cursor = self.db.cursor()
        query = "SELECT COUNT(*) as count FROM attachments WHERE register_id = %s"
        cursor.execute(query, (register_id,))
        result = cursor.fetchone()
        cursor.close()
        return result['count'] if result else 0
    
    def delete(self, attachment_id: int) -> bool:
        """Удаление вложения (записи и файла)"""
        attachment = self.get_by_id(attachment_id)
        
        if attachment:
            # Удаляем файл с диска
            file_path = Path(attachment['file_path'])
            if file_path.exists():
                try:
                    file_path.unlink()
                except Exception:
                    pass
            
            # Удаляем запись из БД
            cursor = self.db.cursor()
            query = "DELETE FROM attachments WHERE id = %s"
            cursor.execute(query, (attachment_id,))
            self.db.commit()
            cursor.close()
            return True
        
        return False
    
    def delete_by_register(self, register_id: int) -> bool:
        """Удаление всех вложений реестра"""
        attachments = self.get_by_register(register_id)
        
        for attachment in attachments:
            self.delete(attachment['id'])
        
        return True
    
    def validate_file(self, file_path: str) -> tuple:
        """Валидация файла (расширение и размер)"""
        path = Path(file_path)
        extension = path.suffix.lower()
        
        # Проверка расширения
        if extension not in self.allowed_extensions:
            return False, f"Недопустимый формат файла. Разрешены: {', '.join(self.allowed_extensions)}"
        
        # Проверка размера
        file_size = os.path.getsize(file_path)
        max_size_bytes = self.max_file_size_mb * 1024 * 1024
        
        if file_size > max_size_bytes:
            return False, f"Файл слишком большой. Максимальный размер: {self.max_file_size_mb} МБ"
        
        return True, extension
    
    def save_file(
        self, 
        source_path: str, 
        register_id: int, 
        user_id: Optional[int] = None
    ) -> Optional[Dict[str, Any]]:
        """Сохранение файла и создание записи в БД"""
        # Валидация
        is_valid, result = self.validate_file(source_path)
        
        if not is_valid:
            raise ValueError(result)
        
        file_extension = result
        
        # Генерация уникального имени файла
        original_name = Path(source_path).name
        unique_name = f"{uuid.uuid4().hex}_{original_name}"
        
        # Создание папки для реестра
        register_folder = self.upload_folder / f"register_{register_id}"
        register_folder.mkdir(parents=True, exist_ok=True)
        
        # Копирование файла
        dest_path = register_folder / unique_name
        
        try:
            import shutil
            shutil.copy2(source_path, dest_path)
            
            # Получение размера файла
            file_size = os.path.getsize(dest_path)
            
            # Создание записи в БД
            attachment_id = self.create(
                register_id=register_id,
                file_path=str(dest_path),
                file_name=original_name,
                file_size=file_size,
                file_type=file_extension,
                uploaded_by=user_id,
                storage='local'
            )
            
            return self.get_by_id(attachment_id)
        
        except Exception as e:
            raise Exception(f"Ошибка сохранения файла: {str(e)}")
    
    def can_add_more(self, register_id: int, max_attachments: int = 5) -> bool:
        """Проверка возможности добавления ещё одного файла"""
        current_count = self.get_count_by_register(register_id)
        return current_count < max_attachments
