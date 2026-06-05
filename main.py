from fastapi import FastAPI, Depends, HTTPException, status
from sqlalchemy.orm import Session
import models, schemas, database, auth  # <--- يجب إضافة auth هنا
import random
from auth import get_current_user
from models import UserRole 
import bcrypt 
from fastapi.security import OAuth2PasswordBearer, OAuth2PasswordRequestForm # هذا السطر هو الذي كان ينقصك
# في أعلى ملف main.py
from auth import create_access_token


app = FastAPI()

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
    hashed_password = pwd_context.hash(user.passwor)
    # نحدد الدور يدوياً هنا
    
    # بدلاً من pwd_context.hash(password)
    new_user.passwor = get_password_hash(user.passwor)
    new_user = models.User(
        username=user.username, 
        passwor=hashed_password, 
        role=models.UserRole.user # تحديد الدور كـ user
    )
    db.add(new_user)
    db.commit()
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

@app.delete("/users/{user_id}")
def delete_user(user_id: int, db: Session = Depends(database.get_db), current_user_id: int = Depends(auth.get_current_user)):
    if user_id != current_user_id: raise HTTPException(status_code=403, detail="غير مسموح لك")
    user = db.query(models.User).filter(models.User.id == user_id).first()
    db.delete(user)
    db.commit()
    return {"message": "تم حذف حسابك نهائياً"}
# --- 3. عمليات الـ OTP (3 دوال) ---

# إرسال كود (يُستخدم قبل إنشاء/تعديل/حذف)
@app.post("/otp/send/{user_id}")
def send_otp(user_id: int):
    code = random.randint(100000, 999999)
    return {"message": "تم إرسال الكود", "code": code}

# التحقق من الكود
@app.post("/otp/verify/")
def verify_otp(user_id: int, code: int):
    # هنا تضع منطق المقارنة مع القاعدة
    return {"status": "success", "message": "تم التحقق"}

# إعادة إرسال الكود
@app.post("/otp/resend/{user_id}")
def resend_otp(user_id: int):
    code = random.randint(100000, 999999)
    return {"message": "تمت إعادة الإرسال", "code": code}
# أضف هذه الدالة في main.py
@app.get("/terms/{term_id}")
def get_term(term_id: int, db: Session = Depends(database.get_db)):
    term = db.query(models.Term).filter(models.Term.id == term_id).first()
    if not term:
        raise HTTPException(status_code=404, detail="المصطلح غير موجود")
    return term
    
@app.get("/terms/public/")
def get_public_terms(db: Session = Depends(database.get_db)):
    # جلب المصطلحات الموافق عليها فقط
    return db.query(models.Term).filter(models.Term.status == 'approved').all()
