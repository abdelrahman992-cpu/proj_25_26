from fastapi import FastAPI, Depends, HTTPException, status, BackgroundTasks
from sqlalchemy.orm import Session
import models, schemas, database, auth
import random
from auth import get_current_user
from models import UserRole 
import bcrypt 
from typing import Optional
from fastapi.security import OAuth2PasswordBearer, OAuth2PasswordRequestForm
from auth import create_access_token
import secrets
import string
from database import get_db, SessionLocal, engine
import datetime as dt
import smtplib
from email.mime.text import MIMEText
import os
import platform
from dotenv import load_dotenv
from fastapi.middleware.cors import CORSMiddleware
from auth import get_password_hash
from pydantic import BaseModel


def smart_load_env():
    current_os = platform.system().lower()
    # اختيار الملف بناءً على النظام
    target_env = "2.env" if current_os == "windows" else ".env"
    
    if os.path.exists(target_env):
        load_dotenv(target_env)
        print(f"--- النظام يعمل بـ: {target_env} ---")
    else:
        print(f"--- تحذير: ملف {target_env} غير موجود! ---")

# --- 2. استدعاء الدالة أولاً ---
smart_load_env()

# --- 3. الآن تحميل المتغيرات بعد التأكد من وجودها ---
EMAIL_USER = os.getenv("EMAIL_USER")
EMAIL_PASS = os.getenv("EMAIL_PASS")

app = FastAPI()

# ... (بقية الكود الخاص بك يبدأ من هنا
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# --- 1. تعريف دالة التحميل الذكي ---



oauth2_scheme = OAuth2PasswordBearer(tokenUrl="token")

def get_current_user_id(token: str = Depends(oauth2_scheme)):
    # هنا يجب أن تضع منطق فك التوكن الخاص بك
    # وإرجاع الـ user_id
    payload = decode_jwt(token) # دالة فك التوكن الخاصة بك
    user_id = payload.get("sub")
    if user_id is None:
        raise HTTPException(status_code=401, detail="توكن غير صالح")
    return int(user_id)
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

@app.put("/terms/update") # حذفنا /{term_id} من الرابط
def update_term(data: dict, db: Session = Depends(get_db)):
    # استخراج الـ ID من البيانات المرسلة
    term_id = data.get('id')
    
    if not term_id:
        raise HTTPException(status_code=400, detail="ID مفقود")

    term = db.query(models.Term).filter(models.Term.id == term_id).first()
    if not term:
        raise HTTPException(status_code=404, detail="المصطلح غير موجود")
    
    # تحديث البيانات
    term.term = data.get('term', term.term)
    term.trans = data.get('trans', term.trans)
    term.defe = data.get('defe', term.defe)
    term.smiles_code = data.get('smiles_code', term.smiles_code)
    
    if 'status' in data:
        term.status = data['status']
        
    db.commit()
    db.refresh(term) # تحديث الكائن
    return {"status": "success"}

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


# في أعلى ملف main.py (خارج الدوال)
# هذا القاموس يحفظ الإيميل مقابل الكود لمدة 10 دقائق فقط
otp_memory = {}

@app.post("/otp/send-code/")
def send_otp_code(data: dict, background_tasks: BackgroundTasks, db: Session = Depends(database.get_db)):
    email = data.get("email")
    code = str(random.randint(100000, 999999))
    
    # حفظ الإيميل في الذاكرة مؤقتاً مربوطاً بالكود
    otp_memory[code] = email 
    
    # حفظ الكود في قاعدة البيانات (كما تفعل سابقاً)
    expire_time = dt.datetime.now(dt.timezone.utc) + dt.timedelta(minutes=10)
    new_otp = models.OTP(code=code, expires_at=expire_time)
    db.add(new_otp)
    db.commit()

    background_tasks.add_task(send_email, email, code)
    return {"message": "تم إرسال الكود"}
@app.post("/users/finalize-signup/")
def finalize_signup(data: dict, db: Session = Depends(database.get_db)):
    code = data.get("code") # الكود الذي أدخله المستخدم
    
    # 1. البحث في قاعدة البيانات عن الكود
    otp_record = db.query(models.OTP).filter(models.OTP.code == code).first()

    # 2. التحقق من وجود الكود في الذاكرة (otp_memory)
    email = otp_memory.get(code)

    if not otp_record or not email:
        # إذا لم يوجد في القاعدة أو الذاكرة
        raise HTTPException(status_code=400, detail="❌ الكود غير صحيح أو انتهت صلاحيته.")

    # 3. إنشاء المستخدم
    new_user = models.User(
        username=data.get("username"),
        passwor=get_password_hash(data.get("password")),
        email=email,
        phone=data.get("phone")
    )
    db.add(new_user)
    
    # 4. تنظيف
    db.delete(otp_record)
    if code in otp_memory:
        del otp_memory[code]
    db.commit()
    
    return {"status": "success", "message": "تم إنشاء الحساب بنجاح"}
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

class DeleteData(BaseModel):
    email: str
    password: str

@app.post("/otp/send-delete/")
def send_delete_otp(data: DeleteData, background_tasks: BackgroundTasks, db: Session = Depends(get_db)):
    # استخدم data.email و data.password بدلاً من المتغيرات المنفصلة
    user = db.query(models.User).filter(models.User.email == data.email).first()
    
    if not user or not verify_password(data.password, user.passwor):
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
    
    # هنا الخطأ: استخدم data.email وليس email
    background_tasks.add_task(send_email, data.email, otp_code)
    
    return {"message": "تم إرسال الكود", "expires_at": expire_time.isoformat()}
 # أضف هذا في main.py
@app.get("/users/me/")
def get_me(current_user: models.User = Depends(auth.get_current_user)):
    return {
        "username": current_user.username,
        "email": current_user.email,
        "phone": current_user.phone
    }
# --- تحديث البيانات (طلب الكود) ---
@app.post("/user/request-update/")
def request_user_update(
    data: dict, 
    background_tasks: BackgroundTasks, 
    current_user: models.User = Depends(auth.get_current_user), 
    db: Session = Depends(get_db)
):
    new_email = data.get("new_email")
    new_phone = data.get("new_phone")
    new_name = data.get("new_name")
    
    # تصحيح المتغيرات هنا
    code_val = generate_otp_code(6) 
    expire_time = dt.datetime.now(dt.timezone.utc) + dt.timedelta(minutes=5)
    
    new_otp = models.OTP(
        user_id=current_user.id,
        edit_code=code_val,
        edit_expire=expire_time
    )
    db.add(new_otp)
    
    current_user.pending_email = new_email
    current_user.pending_phone = new_phone
    current_user.pending_name = new_name
    
    db.commit()
    background_tasks.add_task(send_email, new_email, code_val)
    return {"message": "تم إرسال كود التأكيد إلى إيميلك الجديد"}

@app.post("/otp/change/")
def change_otp(data: dict, current_user: models.User = Depends(auth.get_current_user), db: Session = Depends(get_db)):
    input_code = data.get("code")
    
    # البحث عن السجل الذي يحتوي على الكود في عمود edit_code
    otp_record = db.query(models.OTP).filter(
        models.OTP.user_id == current_user.id,
        models.OTP.edit_code == input_code
    ).first()
    
    # 1. إذا لم يوجد سجل بهذا الكود
    if not otp_record:
        raise HTTPException(status_code=400, detail="الكود غير موجود")

    # 2. التحقق من انتهاء الصلاحية باستخدام عمود edit_expire
    # نستخدم dt.datetime.now(dt.timezone.utc) لأن الـ DateTime في SQL غالباً ما يكون بـ UTC
    if otp_record.edit_expire < dt.datetime.now(dt.timezone.utc).replace(tzinfo=None):
        db.delete(otp_record)
        db.commit()
        raise HTTPException(status_code=400, detail="الكود منتهي الصلاحية")
    
    # 3. اعتماد البيانات المؤقتة (Pending)
    if current_user.pending_email: current_user.email = current_user.pending_email
    if current_user.pending_phone: current_user.phone = current_user.pending_phone
    if current_user.pending_name: current_user.username = current_user.pending_name
    
    # تصفير البيانات المؤقتة
    current_user.pending_email = None
    current_user.pending_phone = None
    current_user.pending_name = None
    
    # 4. حذف الكود والحفظ
    db.delete(otp_record)
    db.commit()
    
    return {"status": "success", "message": "تم التحديث بنجاح"}
# 1. دالة طلب الكود (ترسل الإيميل فقط)
@app.post("/password/request-reset/")
def request_password_reset(email: str, background_tasks: BackgroundTasks, db: Session = Depends(get_db)):
    # كودك الحالي ممتاز هنا
    user = db.query(models.User).filter(models.User.email == email).first()
    if not user: raise HTTPException(status_code=404, detail="هذا البريد غير مسجل")
    
    otp_code = generate_otp_code(6)
    expire_time = dt.datetime.now(dt.timezone.utc) + dt.timedelta(minutes=10)
    new_otp = models.OTP(user_id=user.id, reset_code=otp_code, reset_expire=expire_time)
    db.add(new_otp)
    db.commit()
    background_tasks.add_task(send_email, email, otp_code)
    return {"message": "تم إرسال كود إعادة تعيين كلمة المرور"}

# 2. دالة التحقق من الكود فقط (تستخدم في صفحة forgot_password.php)
@app.post("/password/verify-code/")
def verify_code(data: dict, db: Session = Depends(get_db)):
    email = data.get("email")
    code = data.get("code")
    user = db.query(models.User).filter(models.User.email == email).first()
    
    otp_record = db.query(models.OTP).filter(
        models.OTP.user_id == user.id,
        models.OTP.reset_code == code,
        models.OTP.reset_expire >= dt.datetime.now(dt.timezone.utc)
    ).first()

    if not otp_record:
        raise HTTPException(status_code=400, detail="الكود غير صحيح أو منتهي الصلاحية")
    
    return {"status": "success", "message": "الكود صحيح"}

# 3. دالة تغيير كلمة المرور (تستخدم في صفحة reset_password.php)
@app.post("/password/reset/")
def reset_password(data: dict, db: Session = Depends(get_db)):
    email = data.get("email")
    new_password = data.get("new_password")
    
    user = db.query(models.User).filter(models.User.email == email).first()
    # تغيير كلمة المرور مباشرة
    user.passwor = get_password_hash(new_password)
    db.commit()
    
    return {"message": "تم تغيير كلمة المرور بنجاح"}
@app.post("/password/modifypa-otp/")
def modifypa_otp(data: dict, db: Session = Depends(get_db)):
    email = data.get("email")
    otp_input = data.get("code")
    
    user = db.query(models.User).filter(models.User.email == email).first()
    if not user:
        raise HTTPException(status_code=404, detail="مستخدم غير موجود")

    otp_record = db.query(models.OTP).filter(
        models.OTP.user_id == user.id,
        models.OTP.reset_code == otp_input
    ).first()

    if not otp_record:
        raise HTTPException(status_code=400, detail="❌ الكود غير صحيح")

    expire_time = otp_record.reset_expire
    
    # معالجة التوقيت لتجنب خطأ offset-naive vs offset-aware
    if expire_time.tzinfo is None:
        expire_time = expire_time.replace(tzinfo=dt.timezone.utc)

    if expire_time < dt.datetime.now(dt.timezone.utc):
        raise HTTPException(status_code=400, detail="❌ الكود منتهي الصلاحية")

    return {"status": "success", "message": "الكود صحيح"}
    # في main.py
@app.get("/users/mee/")
def get_user_profile(current_user: models.User = Depends(get_current_user)):
    # تأكد أن الـ role يتم تحويله إلى نص بسيط (String)
    role_str = str(current_user.role.value) if hasattr(current_user.role, 'value') else str(current_user.role)
    
    response_data = {
        "username": current_user.username,
        "email": current_user.email,
        "phone": current_user.phone,
        "role": role_str
    }
    
    print(f"DEBUG: Data sent to PHP: {response_data}")
    return response_data
    
@app.get("/terms/user/{user_id}")
def get_user_terms(user_id: int, db: Session = Depends(get_db)):
    terms = db.query(models.Term).filter(models.Term.user_id == user_id).order_by(models.Term.id.desc()).all()
    # تحويل البيانات إلى تنسيق مصفوفة يمكن لـ PHP استهلاكه
    return [
        {"term": t.term,  "status": t.status} 
        for t in terms
    ]
@app.post("/terms/add-from-bot/")
def add_term_from_bot(data: dict, db: Session = Depends(get_db)):
    # منطق حفظ المصطلح في قاعدة البيانات باستخدام SQLAlchemy
    new_term = models.Term(
        term=data.get('term'),
        trans=data.get('trans'),
        defe=data.get('defe'),
        smiles_code=data.get('smiles_code'),
        user_id=data.get('user_id'),
        status='pending',
        picture='pic/ncbi_logo.png'
    )
    db.add(new_term)
    db.commit()
    return {"status": "success"}
@app.get("/terms/count/")
def get_terms_count(db: Session = Depends(get_db)):
    count = db.query(models.Term).count()
    return {"total": count}
@app.get("/terms/")
def get_all_terms(db: Session = Depends(get_db)):
    # جلب البيانات التي حالتها ليست مرفوضة
    terms = db.query(models.Term).filter(models.Term.status != 'rejected').order_by(models.Term.id.desc()).all()
    return terms
if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="127.0.0.1", port=8000)
