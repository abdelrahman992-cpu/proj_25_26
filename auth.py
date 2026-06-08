from jose import jwt
from datetime import datetime, timedelta
from fastapi import Depends, HTTPException, status
from fastapi.security import OAuth2PasswordBearer
from fastapi import HTTPException, status
import database  # يجب إضافة هذا السطر هنا ليتم التعرف على كلمة 'database'
from sqlalchemy.orm import Session
from jose import jwt, JWTError # تأكد أنك مثبت مكتبة python-jose
import models
from passlib.context import CryptContext
SECRET_KEY = "YOUR_SECRET_KEY" # نفس الكلمة التي استخدمتها في main.py
ALGORITHM = "HS256"
SECRET_KEY = "CHANGE_AZABOLA_ELKAHTEER" # غير هذه الكلمة لأي نص سري
ALGORITHM = "HS256"
ACCESS_TOKEN_EXPIRE_MINUTES = 30

pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")

def get_password_hash(password: str):
    return pwd_context.hash(password)

def verify_password(plain_password: str, hashed_password: str) -> bool:
    return pwd_context.verify(plain_password, hashed_password)
def create_access_token(data: dict):
    to_encode = data.copy()
    expire = datetime.utcnow() + timedelta(minutes=ACCESS_TOKEN_EXPIRE_MINUTES)
    to_encode.update({"exp": expire})
    encoded_jwt = jwt.encode(to_encode, SECRET_KEY, algorithm=ALGORITHM)
    return encoded_jwt

def verify_token(token: str):
    try:
        payload = jwt.decode(token, SECRET_KEY, algorithms=[ALGORITHM])
        username: str = payload.get("sub")
        if username is None:
            raise HTTPException(status_code=401, detail="التوكن غير صالح")
        return username
    except JWTError:
        raise HTTPException(status_code=401, detail="فشل التحقق من التوكن")
def verify_user_ownership(current_user_id: int, target_user_id: int):
    if current_user_id != target_user_id:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="لا تملك الصلاحية للقيام بهذه العملية على هذا الحساب"
        )

SECRET_KEY = "your_secret_key" # غيرها لكلمة سر قوية
ALGORITHM = "HS256"
oauth2_scheme = OAuth2PasswordBearer(tokenUrl="login")

def create_access_token(data: dict):
    to_encode = data.copy()
    expire = datetime.utcnow() + timedelta(minutes=60)
    to_encode.update({"exp": expire})
    return jwt.encode(to_encode, SECRET_KEY, algorithm=ALGORITHM)

def get_current_user(token: str = Depends(oauth2_scheme), db: Session = Depends(database.get_db)):
    # 1. فك تشفير التوكن للحصول على اسم المستخدم (username)
    username = verify_token(token) # دالة فك التوكن الخاصة بك
    
    # 2. جلب المستخدم كاملاً من القاعدة باستخدام الـ username
    user = db.query(models.User).filter(models.User.username == username).first()
    
    if user is None:
        raise HTTPException(status_code=401, detail="المستخدم غير موجود")
        
    # 3. إرجاع كائن المستخدم (Object) وليس الاسم فقط
    return user
