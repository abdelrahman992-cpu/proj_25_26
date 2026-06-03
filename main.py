from fastapi import FastAPI, Depends, HTTPException, status
from sqlalchemy.orm import Session
import models, schemas, database, auth  # <--- يجب إضافة auth هنا
import random
from auth import get_current_user
# في ملف main.py - تأكد من تعديل دالة الـ login لتصبح كالتال
from fastapi.security import OAuth2PasswordRequestForm # أضف هذا الاستيراد
from passlib.context import CryptContext

# تعريف سياق التشفير (يجب أن يطابق ما استخدمته عند حفظ كلمة المرور)
pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")
app = FastAPI()
@app.post("/login/")
def login(form_data: OAuth2PasswordRequestForm = Depends(), db: Session = Depends(database.get_db)):
    user = db.query(models.User).filter(models.User.username == form_data.username).first()
    
    if not user:
        raise HTTPException(status_code=400, detail="المستخدم غير موجود")
    
    # تأكد من أن كلمة المرور المخزنة ليست فارغة
    if not user.passwor:
        raise HTTPException(status_code=500, detail="خطأ في بيانات المستخدم")

    try:
        # التحقق مباشرة
        # ملاحظة: pwd_context.verify تتعامل داخلياً مع الهاش، لا تقم بقص الهاش المخزن
        if not pwd_context.verify(form_data.password, user.passwor):
            raise HTTPException(status_code=400, detail="كلمة المرور غير صحيحة")
    except ValueError as e:
        # إذا استمر الخطأ، فالمشكلة في الهاش المخزن في القاعدة
        print(f"DEBUG ERROR: {e}")
        raise HTTPException(status_code=400, detail="كلمة المرور غير صالحة للتحقق")
        
    access_token = auth.create_access_token(data={"sub": str(user.id)})
    return {"access_token": access_token, "token_type": "bearer"}

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
@app.post("/terms/")
def add_term(term: schemas.TermSchema, db: Session = Depends(database.get_db)):
    new_term = models.Term(
        term=term.term,
        trans=term.trans,
        defe=term.defe,
        status=term.status,
        user_id=term.user_id,
        picture="pic/yyy.jpg" # إضافة قيمة افتراضية هنا كاحتياط
    )
    db.add(new_term)
    db.commit()
    db.refresh(new_term)
    return {"message": "تمت الإضافة بنجاح", "term": new_term}

@app.post("/users/")
def create_user(user: schemas.UserCreate, db: Session = Depends(database.get_db)):
    hashed_password = pwd_context.hash(user.passwor)
    new_user = models.User(username=user.username, passwor=hashed_password)
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
