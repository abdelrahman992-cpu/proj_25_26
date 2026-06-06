from fastapi import FastAPI, Depends, HTTPException, status, BackgroundTasks
from sqlalchemy.orm import Session
import models, schemas, database, auth  # <--- يجب إضافة auth هنا
import random
from auth import get_current_user
from models import UserRole 
import bcrypt 
from typing import Optional
from fastapi.security import OAuth2PasswordBearer, OAuth2PasswordRequestForm # هذا السطر هو الذي كان ينقصك
from auth import create_access_token
import secrets
import string
from database import get_db, SessionLocal, engine
import datetime as dt
import smtplib
from email.mime.text import MIMEText
import os
from dotenv import load_dotenv
app = FastAPI()
load_dotenv()
EMAIL_USER = os.getenv("EMAIL_USER")
EMAIL_PASS = os.getenv("EMAIL_PASS")

def send_email(to_email, code):
    if not EMAIL_USER or not EMAIL_PASS:
        print("Error: Email credentials not found in .env")
        return False
        
    msg = MIMEText(f"كود التحقق الخاص بك هو: {code}")
    msg['Subject'] = "كود استعادة كلمة المرور"
    msg['From'] = EMAIL_USER
    msg['To'] = to_email
    
    try:
        # استخدام المتغيرات التي قرأناها من .env
        server = smtplib.SMTP_SSL('smtp.gmail.com', 465)
        server.login(EMAIL_USER, EMAIL_PASS)
        server.sendmail(EMAIL_USER, to_email, msg.as_string())
        server.quit()
        return True
    except Exception as e:
        print(f"Error sending email: {e}")
        return False

def generate_otp_code(length=6):
    # توليد سلسلة أرقام عشوائية
    return ''.join(secrets.choice(string.digits) for _ in range(length))
new_code = generate_otp_code(6)

# تخزين الكود في قاعدة البيانات

def verify_password(plain_password, hashed_password):
    # نستخدم [:72] لتجنب خطأ الـ 72 بايت الذي ظهر لك
    return bcrypt.checkpw(plain_password[:72].encode('utf-8'), hashed_password.encode('utf-8'))

def get_password_hash(password):
    return bcrypt.hashpw(password[:72].encode('utf-8'), bcrypt.gensalt()).decode('utf-8')
@app.post("/login/")
def login(form_data: OAuth2PasswordRequestForm = Depends(), db: Session = Depends(database.get_db)):
    # 1. التحقق من المستخدم
    user = db.query(models.User).filter(models.User.username == form_data.username).first()
    if not user or not verify_password(form_data.password, user.passwor):
        raise HTTPException(status_code=400, detail="اسم المستخدم أو كلمة المرور غير صحيحة")

    # 2. إنشاء التوكن (هنا نستخدم الدالة الجديدة)
    access_token = create_access_token(data={"sub": user.username})
    
    # 3. إرجاع التوكن للمتصفح
    return {"access_token": access_token, "token_type": "bearer"}
# --- دوال تشفير ومقارنة كلمات المرور ---

@app.put("/terms/{term_id}")
def update_term(term_id: int, term_data: schemas.TermSchema, db: Session = Depends(database.get_db)):
    term = db.query(models.Term).filter(models.Term.id == term_id).first()
    if not term: raise HTTPException(status_code=404, detail="غير موجود")
    term.term, term.trans, term.defe = term_data.term, term_data.trans, term_data.defe
    db.commit()
    return {"message": "تم التعديل"}

@app.delete("/terms/{term_id}")
def delete_term(term_id: int, db: Session = Depends(database.get_db)):
    term = db.query(models.Term).filter(models.Term.id == term_id).first()
    if not term: raise HTTPException(status_code=404, detail="غير موجود")
    db.delete(term)
    db.commit()
    return {"message": "تم الحذف"}
# في main.py

@app.post("/terms/")
def add_term(term: schemas.TermSchema, 
             db: Session = Depends(database.get_db), 
             current_user: models.User = Depends(auth.get_current_user)):
    
    new_term = models.Term(
        term=term.term,
        trans=term.trans,
        defe=term.defe,
        status='pending',  # هنا نثبت أنها دائماً تحت المراجعة
        user_id=current_user.id
    )
    db.add(new_term)
    db.commit()
    return {"message": "تمت إضافة المصطلح بنجاح، بانتظار موافقة المسؤول."}
@app.put("/terms/approve/{term_id}")
def approve_term(term_id: int, 
                 db: Session = Depends(database.get_db), 
                 current_user: models.User = Depends(auth.get_current_user)):
    
    # التحقق من صلاحية المسؤول
    if current_user.role != 'admin':
        raise HTTPException(status_code=403, detail="لا تملك صلاحية الموافقة على المصطلحات")
    
    term = db.query(models.Term).filter(models.Term.id == term_id).first()
    if not term:
        raise HTTPException(status_code=404, detail="المصطلح غير موجود")
    
    term.status = 'approved' # تغيير الحالة للموافقة
    db.commit()
    return {"message": "تمت الموافقة على المصطلح وعرضه للجمهور بنجاح"}

@app.post("/users/")
def create_user(user: schemas.UserCreate, db: Session = Depends(database.get_db)):
    # 1. تشفير كلمة المرور باستخدام الدالة التي عرفتها في الأعلى
    hashed_password = get_password_hash(user.passwor)
    
    # 2. إنشاء المستخدم الجديد مباشرة بالبيانات الصحيحة
    new_user = models.User(
        username=user.username, 
        passwor=hashed_password,  # تأكد أن اسم العمود في موديل User هو passwor
        role=models.UserRole.user
    )
    
    # 3. حفظ في قاعدة البيانات
    db.add(new_user)
    db.commit()
    db.refresh(new_user)
    
    return {"message": "تم إنشاء الحساب بنجاح"}
# في ملف main.py

# --- تعديل المستخدم (مُحمية) ---
@app.put("/users/{user_id}")
def update_user(user_id: int, data: schemas.UserUpdate, db: Session = Depends(database.get_db), current_user_id: int = Depends(auth.get_current_user)):
    if user_id != current_user_id: raise HTTPException(status_code=403, detail="غير مسموح لك")
    user = db.query(models.User).filter(models.User.id == user_id).first()
    # ... تحديث
    db.commit()
    return {"message": "تم التعديل"}

@app.post("/user/delete/")
def delete_user(email: str, code: str, db: Session = Depends(database.get_db)):
    user = db.query(models.User).filter(models.User.email == email).first()
    if not user:
        raise HTTPException(status_code=404, detail="مستخدم غير موجود")
        
    otp_record = db.query(models.OTP).filter(
        models.OTP.user_id == user.id,
        models.OTP.delete_otp == code
    ).first()

    if not otp_record:
        raise HTTPException(status_code=400, detail="الكود غير صحيح")

    # --- الحل هنا ---
    # نقوم بتحويل وقت قاعدة البيانات إلى UTC قبل المقارنة
    # إذا كان الحقل في قاعدة البيانات مخزناً بدون منطقة زمنية (naive)
    db_time = otp_record.delete_expire
    if db_time.tzinfo is None:
        db_time = db_time.replace(tzinfo=dt.timezone.utc)
    
    if db_time < dt.datetime.now(dt.timezone.utc):
        raise HTTPException(status_code=400, detail="الكود منتهي الصلاحية")
    # ----------------

    db.delete(user)
    db.delete(otp_record)
    db.commit()
    return {"status": "deleted"}

@app.post("/otp/send/")
def send_otp(email: str, background_tasks: BackgroundTasks, db: Session = Depends(get_db)):
    user = db.query(models.User).filter(models.User.email == email).first()
    if not user:
        raise HTTPException(status_code=404, detail="مستخدم غير موجود")

    # 1. توليد الكود وتخزينه في متغير اسمه otp_code
    otp_code = generate_otp_code(6)
    
    # 2. تمرير نفس المتغير otp_code إلى دالة الإرسال
    background_tasks.add_task(send_email, email, otp_code)
    
    # 3. استخدام نفس المتغير otp_code في قاعدة البيانات
    new_otp = models.OTP(
        user_id=user.id,
        reset_code=otp_code,
        expires_at = dt.datetime.now(dt.timezone.utc) + dt.timedelta(minutes=15)
    )
    
    db.add(new_otp)
    db.commit()
    
    return {"message": "تم إرسال الكود، قد يستغرق وصوله ثوانٍ معدودة."}

# دالة التحقق من OTP
@app.post("/otp/verify/")
def verify_otp(email: str, code: str, db: Session = Depends(database.get_db)):
    # 1. البحث باستخدام 'reset_code' وليس 'code'
    otp_record = db.query(models.OTP).join(models.User).filter(
        models.User.email == email,
        models.OTP.reset_code == code, # تأكد أن هذا هو اسم العمود في models.py
        models.OTP.expires_at > dt.datetime.now(dt.timezone.utc)
    ).first()

    if not otp_record:
        # للإصلاح: يمكنك إضافة print هنا لرؤية ما يحدث في التيرمينال
        print(f"DEBUG: No OTP found for {email} with code {code}")
        raise HTTPException(status_code=400, detail="الكود غير صحيح أو منتهي الصلاحية")

    # 2. حذف الكود بعد استخدامه
    db.delete(otp_record)
    db.commit()
    
    return {"status": "success", "message": "تم التحقق بنجاح"}
@app.get("/terms/")
def get_terms(term_id: Optional[int] = None, db: Session = Depends(database.get_db)):
    if term_id is not None:
        # جلب مصطلح محدد
        term = db.query(models.Term).filter(models.Term.id == term_id).first()
        if not term:
            raise HTTPException(status_code=404, detail="المصطلح غير موجود")
        return term
    
    # جلب الكل إذا لم يتم تمرير ID
    return db.query(models.Term).all()

# دالة المصطلحات العامة (الموافق عليها فقط)
@app.get("/terms/public/")
def get_public_terms(db: Session = Depends(database.get_db)):
    return db.query(models.Term).filter(models.Term.status == 'approved').all()
if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="127.0.0.1", port=8000)
@app.post("/otp/send-delete/")
def send_delete_otp(email: str, password: str, background_tasks: BackgroundTasks, db: Session = Depends(get_db)):
    user = db.query(models.User).filter(models.User.email == email).first()
    if not user or not verify_password(password, user.passwor):
        raise HTTPException(status_code=400, detail="كلمة المرور غير صحيحة")
    
    otp_code = generate_otp_code(6)
    expire_time = dt.datetime.now(dt.timezone.utc) + dt.timedelta(minutes=5)
    
    new_otp = models.OTP(
        user_id=user.id,
        delete_otp=otp_code,
        delete_expire=expire_time
    )
    db.add(new_otp)
    db.commit()
    
    background_tasks.add_task(send_email, email, otp_code)
    return {"message": "تم إرسال الكود", "expires_at": expire_time.isoformat()}
    
