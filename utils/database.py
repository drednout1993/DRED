"""
Утилиты для работы с базой данных
Система учёта реестров документов
"""

import pymysql
from typing import Optional, Dict, Any
from contextlib import contextmanager


class Database:
    """Класс для работы с базой данных"""
    
    def __init__(self, config: Dict[str, Any]):
        self.config = config
        self.connection: Optional[pymysql.Connection] = None
    
    def connect(self) -> bool:
        """Подключение к базе данных"""
        try:
            self.connection = pymysql.connect(**self.config)
            return True
        except pymysql.Error as e:
            print(f"Ошибка подключения к базе данных: {e}")
            return False
    
    def disconnect(self):
        """Отключение от базы данных"""
        if self.connection:
            self.connection.close()
            self.connection = None
    
    def is_connected(self) -> bool:
        """Проверка подключения"""
        return self.connection is not None and self.connection.open
    
    def reconnect(self) -> bool:
        """Переподключение к базе данных"""
        self.disconnect()
        return self.connect()
    
    @contextmanager
    def cursor(self, dictionary: bool = True):
        """Контекстный менеджер для курсора"""
        if not self.is_connected():
            self.connect()
        
        cursor_type = pymysql.cursors.DictCursor if dictionary else pymysql.cursors.Cursor
        cursor = self.connection.cursor(cursor_type)
        
        try:
            yield cursor
        finally:
            cursor.close()
    
    def commit(self):
        """Фиксация транзакции"""
        if self.connection:
            self.connection.commit()
    
    def rollback(self):
        """Откат транзакции"""
        if self.connection:
            self.connection.rollback()
    
    def execute(self, query: str, params: tuple = ()) -> int:
        """Выполнение запроса (INSERT, UPDATE, DELETE)"""
        with self.cursor(dictionary=False) as cursor:
            cursor.execute(query, params)
            self.commit()
            return cursor.lastrowid
    
    def fetch_one(self, query: str, params: tuple = ()) -> Optional[Dict[str, Any]]:
        """Получение одной записи"""
        with self.cursor() as cursor:
            cursor.execute(query, params)
            return cursor.fetchone()
    
    def fetch_all(self, query: str, params: tuple = ()) -> list:
        """Получение всех записей"""
        with self.cursor() as cursor:
            cursor.execute(query, params)
            return cursor.fetchall()
    
    def table_exists(self, table_name: str) -> bool:
        """Проверка существования таблицы"""
        query = """
            SELECT COUNT(*) as count 
            FROM information_schema.tables 
            WHERE table_schema = %s AND table_name = %s
        """
        result = self.fetch_one(query, (self.config['database'], table_name))
        return result['count'] > 0 if result else False
    
    def get_tables(self) -> list:
        """Получение списка таблиц"""
        query = """
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = %s
        """
        results = self.fetch_all(query, (self.config['database'],))
        return [r['table_name'] for r in results]


def create_database(db_config: Dict[str, Any], sql_file_path: str) -> bool:
    """Создание базы данных из SQL файла"""
    try:
        # Подключаемся без указания базы данных
        config_without_db = db_config.copy()
        if 'database' in config_without_db:
            del config_without_db['database']
        
        conn = pymysql.connect(**config_without_db)
        cursor = conn.cursor()
        
        # Читаем SQL файл
        with open(sql_file_path, 'r', encoding='utf-8') as f:
            sql_script = f.read()
        
        # Выполняем скрипт
        for statement in sql_script.split(';'):
            statement = statement.strip()
            if statement:
                cursor.execute(statement)
        
        conn.commit()
        cursor.close()
        conn.close()
        
        return True
    
    except Exception as e:
        print(f"Ошибка создания базы данных: {e}")
        return False


# Глобальный экземпляр базы данных
db_instance: Optional[Database] = None


def get_database(config: Dict[str, Any]) -> Database:
    """Получение экземпляра базы данных (singleton)"""
    global db_instance
    
    if db_instance is None:
        db_instance = Database(config)
        db_instance.connect()
    
    return db_instance


def close_database():
    """Закрытие соединения с базой данных"""
    global db_instance
    
    if db_instance:
        db_instance.disconnect()
        db_instance = None
