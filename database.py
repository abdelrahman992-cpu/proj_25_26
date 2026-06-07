from sqlalchemy import create_engine
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker
import os
from dotenv import load_dotenv
# استيراد إعدادات النظام من الملف الذي أنشأناه
from config import settings 

load_dotenv(settings['env_file'])

db_user = os.getenv("DB_USER")
db_pass = os.getenv("DB_PASS", "") # تأكد أنها سلسلة نصية فارغة إذا لم توجد
db_host = os.getenv("DB_HOST", "localhost")
db_name = os.getenv("DB_NAME")

# بدلاً من الكود القديم، استخدم هذا المنطق:import os
print(f"DEBUG: Checking .env file at {os.path.abspath(settings['env_file'])}")
print(f"DEBUG: DB_USER is {os.getenv('DB_USER')}")

# هذا السطر يجلب مسار المجلد الذي يوجد فيه الكود حالياً (أياً كان نظام التشغيل)
BASE_DIR = os.path.dirname(os.path.abspath(__file__))

# الآن، إذا كان ملف الـ database.db في نفس مجلد المشروع:
db_path = os.path.join(BASE_DIR, "dbdictionary.db")

project_root = BASE_DIR

# إضافة ?charset=utf8mb4 لضمان توافق الترميز عبر كل الأنظمة
charset_query = "?charset=utf8mb4"

if db_pass:
    SQLALCHEMY_DATABASE_URL = f"mysql+pymysql://{db_user}:{db_pass}@{db_host}/{db_name}{charset_query}"
else:
    SQLALCHEMY_DATABASE_URL = f"mysql+pymysql://{db_user}@{db_host}/{db_name}{charset_query}"

print(f"Connecting to: {SQLALCHEMY_DATABASE_URL}")

engine = create_engine(
    SQLALCHEMY_DATABASE_URL,
    connect_args={
        "charset": "utf8mb4",
        "init_command": "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    }
)

SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
Base = declarative_base()

# هذه هي الدالة المفقودة
def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()
