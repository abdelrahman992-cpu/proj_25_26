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
from fastapi import HTTPException, APIRouter
import requests
from Bio import Align
import difflib
import os
import pymysql
import xml.etree.ElementTree as ET
from chembl_webresource_client.new_client import new_client
from models import Term  


def get_db_connection():
    # جلب كلمة المرور من متغيرات البيئة، وإذا كانت فارغة أو غير موجودة تكون "" (بدون باسوورد)
    db_password = os.getenv("DB_PASS", "") 
    
    return pymysql.connect(
        host='localhost',
        user=os.getenv("DB_USER", "root"),
        password=db_password,
        database=os.getenv("DB_NAME", "dbdictionary"),
        charset='utf8mb4',
        cursorclass=pymysql.cursors.DictCursor
    )

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
router = APIRouter()
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
class AccessionRequest(BaseModel):
    accession_id: str
#@app.post("/api/import-ncbi/")
#def import_ncbi_via_api(req: AccessionRequest, db: Session = Depends(get_db)):
@app.post("/api/import-ncbi/")
def import_ncbi_via_api(db: Session = Depends(get_db)):
    try:
        # 1. البحث عن الجينات
        search_url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=nuccore&term=Hemophilia&retmax=10&retmode=json"
        search_res = requests.get(search_url, timeout=10).json()
        
        id_list = search_res.get('esearchresult', {}).get('idlist', [])
        if not id_list:
            return {"status": "error", "message": "لم يتم العثور على نتائج."}
        
        selected_id = random.choice(id_list)
        
        # 2. جلب البيانات (Fasta)
        fasta_url = f"https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=nuccore&id={selected_id}&rettype=fasta&retmode=text"
        fasta_res = requests.get(fasta_url, timeout=10).text
        
        # استخراج العنوان والتسلسل
        lines = fasta_res.splitlines()
        header = lines[0] if lines else "Unknown Gene"
        # تنظيف العنوان (إزالة علامة >)
        clean_header = header.replace(">", "").strip()
        
        clean_sequence = "".join([line.strip() for line in lines if not line.startswith(">")])
        
        # 3. الحفظ في قاعدة البيانات
        new_term = models.Term(
            term=clean_header[:255],  # اسم الجين الحقيقي
            trans=clean_header[:255], # يمكنك وضع العنوان هنا مبدئياً
            defe=f"NCBI Accession ID: {selected_id}",
            fasta_seq=clean_sequence,  # العمود الذي تستخدمه
            sequence=clean_sequence,   # أضفنا هذا أيضاً احتياطياً
            disease_class="Genetic Research",
            status="approved",
            user_id=46
        )
        
        db.add(new_term)
        db.commit()
        new_term.fasta_seq = clean_sequence # تحديث القيمة للقيمة الحقيقية
        db.commit()
        db.refresh(new_term)
        
        return {"status": "success", "imported_id": selected_id, "term": clean_header}

    except Exception as e:
        db.rollback()
        return {"status": "error", "message": str(e)}


@app.post("/analyze-gene/")
def analyze_gene(payload: dict):
    gene_name = payload.get("gene_name", "").strip()
    if not gene_name:
        raise HTTPException(status_code=400, detail="يجب إدخال اسم الجين (مثل: BRCA1).")

    # 1. البحث المباشر في قاعدة بيانات التسلسلات (nuccore) باسم الجين
    search_url = f"https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=nuccore&term={gene_name}+[Gene+Name]&retmax=1&retmode=json"
    search_res = requests.get(search_url).json()
    id_list = search_res.get("esearchresult", {}).get("idlist", [])
    
    # إذا لم يجد بتصنيف Gene Name، نجرب البحث المباشر بالنص
    if not id_list:
        alt_search_url = f"https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=nuccore&term={gene_name}&retmax=1&retmode=json"
        alt_search_res = requests.get(alt_search_url).json()
        id_list = alt_search_res.get("esearchresult", {}).get("idlist", [])

    if not id_list:
        raise HTTPException(status_code=404, detail="عذراً، لم يتم العثور على تسلسل جيني مطابق لهذا الاسم في NCBI.")
        
    accession = id_list[0]  # الحصول على الـ Accession/ID الخاص بالتسلسل مباشرة

    # 2. جلب التسلسل الفعلي (FASTA) باستخدام الـ Accession
    fasta_url = f"https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=nuccore&id={accession}&rettype=fasta&retmode=text"
    fasta_response = requests.get(fasta_url)
    
    if fasta_response.status_code != 200 or not fasta_response.text:
        raise HTTPException(status_code=500, detail="تعذر جلب التسلسل من قاعدة بيانات NCBI.")

    fasta_text = fasta_response.text
    lines = fasta_text.splitlines()
    raw_sequence = "".join([line.strip() for line in lines if not line.startswith(">")])

    # 3. التحقق من نظافة التسلسل (اعتماد الفلترة المتسامحة التي قمنا بها)
    is_valid, clean_seq = is_valid_dna(raw_sequence)
    
    if not is_valid:
        raise HTTPException(status_code=400, detail="التسلسل المسترجع يحتوي على رموز غير صالحة.")
        
    return {
        "status": "success",
        "gene_name": gene_name,
        "accession": accession,
        "clean_sequence": clean_seq, # معاينة أول 200 قاعدة
        "disease_classification": "Type I (نتيجة تحليل مبدئية)"
    }
class HemophiliaRequest(BaseModel):
    query: str = "Hemophilia Gene Therapy"

# دالة مساعدة للترجمة (يمكنك ربطها بأي API ترجمة مثل Google Translate أو DeepL)
def translate_to_arabic(text: str) -> str:
    # ضع هنا كود الترجمة الخاص بك في بايثون
    return text + " (مترجم)"

@app.post("/api/import-hemophilia/")
def import_hemophilia_api(db: Session = Depends(get_db)):
    search_url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&term=Hemophilia+Gene+Therapy&retmax=3&retmode=json"
    
    try:
        search_res = requests.get(search_url).json()
        id_list = search_res.get("esearchresult", {}).get("idlist", [])
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"خطأ في الاتصال بـ NCBI: {str(e)}")
    
    count = 0
    for pmid in id_list:
        # التحقق من وجود المصطلح قبل محاولة الجلب (لتجنب الطلبات غير الضرورية)
        term_name = f"PMID: {pmid}"
        if db.query(Term).filter(Term.term == term_name).first():
            continue
            
        fetch_url = f"https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pubmed&id={pmid}&retmode=xml"
        try:
            xml_response = requests.get(fetch_url)
            if xml_response.status_code != 200: continue
            
            root = ET.fromstring(xml_response.text)
            title_node = root.find(".//ArticleTitle")
            t_en = title_node.text if title_node is not None else "No Title"
            
            t_ar = translate_to_arabic(t_en)
            abstract = f"Research ID: {pmid}"
            
            # إنشاء كائن Term مع الحقول كاملة
            new_term = Term(
                term=term_name,
                trans=t_ar,
                defe=abstract,
                picture="pic/ncbi_logo.png",
                status="approved",
                user_id=46,
                smiles_code="N/A",      # الحقول الإضافية
                fasta_seq="N/A",
                disease_class="Genetic Research"
            )
            db.add(new_term)
            count += 1
        except Exception:
            continue
            
    # Commit واحد فقط بعد انتهاء الحلقة
    db.commit()
    
    return {
        "status": "success",
        "imported_count": count,
        "message": f"🩸 تم استيراد {count} أبحاث بنجاح!"
    }
    
@app.get("/suggest-genes/")
def suggest_genes(term: str):
    if len(term) < 2:
        return []
    
    # البحث باستخدام علامة * لجلب اقتراحات تبدأ بالحروف المدخلة (غير حساس لحالة الأحرف تلقائياً في NCBI)
    search_url = f"https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=gene&term={term}*&retmax=5&retmode=json"
    search_res = requests.get(search_url).json()
    id_list = search_res.get("esearchresult", {}).get("idlist", [])
    
    if not id_list:
        return []

    # جلب أسماء الجينات المقترحة لعرضها للمستخدم
    summary_url = f"https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=gene&id={','.join(id_list)}&retmode=json"
    summary_res = requests.get(summary_url).json()
    
    suggestions = []
    for gid in id_list:
        gene_info = summary_res.get("result", {}).get(str(gid))
        if gene_info:
            suggestions.append({
                "id": gid,
                "symbol": gene_info.get("name"),
                "description": gene_info.get("description")
            })
            
    return suggestions    
@app.post("/compare-two-sequences/")
def compare_two_sequences(payload: dict):
    # استقبال السلسلتين وتنظيفهما
    seq1 = payload.get("seq1", "").replace("\n", "").replace(" ", "").upper()
    seq2 = payload.get("seq2", "").replace("\n", "").replace(" ", "").upper()
    
    if not seq1 or not seq2:
        raise HTTPException(status_code=400, detail="الرجاء إدخال التسلسلين للمقارنة.")

    # التحقق من أن السلاسل تحتوي فقط على أحرف DNA صالحة (اختياري، يمكنك استخدام دالة الفحص السابقة)
    
    # إجراء المحاذاة والمقارنة
    aligner = Align.PairwiseAligner()
    alignments = aligner.align(seq1, seq2)
    
    if not alignments:
        return {
            "status": "success",
            "alignment_score": 0,
            "sequence_1_aligned": seq1,
            "sequence_2_aligned": seq2,
            "message": "لا يوجد تطابق عالي."
        }
        
    best_alignment = alignments[0]
    score = best_alignment.score 
    
    # استخراج شكل المحاذاة النصي للعرض
    alignment_str = str(best_alignment).split('\n')
    aligned_1 = alignment_str[0]
    aligned_2 = alignment_str[2]

    return {
        "status": "success",
        "alignment_score": score,
        "sequence_1_aligned": aligned_1,
        "sequence_2_aligned": aligned_2
    }
def is_valid_dna(sequence):
    # إزالة المسافات والأسطر الجديدة
    clean_seq = sequence.replace("\n", "").replace(" ", "").upper()
    
    # السماح فقط بـ A, C, G, T, N
    allowed_chars = set("ACGTN")
    
    if set(clean_seq).issubset(allowed_chars):
        return True, clean_seq
    else:
        return False, "يحتوي التسلسل على أحرف غير صالحة (يجب أن يكون A, C, G, T فقط)."

@app.post("/terms/")
def add_term(term: schemas.TermSchema, 
             db: Session = Depends(database.get_db), 
             current_user: models.User = Depends(auth.get_current_user)):
    
    # 1. تحويل البيانات القادمة من الـ Schema لقاموس
    term_data = term.dict()
    
    # 2. إضافة البيانات الخاصة بالنظام (التي لا يرسلها المستخدم)
    term_data['user_id'] = current_user.id
    term_data['status'] = 'pending'
    
    # 3. إنشاء الكائن وإرساله للـ DB مباشرة
    new_term = models.Term(**term_data)
    
    db.add(new_term)
    db.commit()
    db.refresh(new_term) # ضروري عشان تجيب الـ ID اللي اتولد في القاعدة
    
    return {"message": "تمت إضافة المصطلح بنجاح، بانتظار موافقة المسؤول.", "term_id": new_term.id}


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
    
    # 1. تحويل البيانات لقاموس
    term_data = term.dict()
    
    # 2. تعيين قيم افتراضية للحقول التي لا يرسلها المستخدم
    term_data.setdefault("smiles_code", "N/A")
    term_data.setdefault("fasta_seq", "N/A")
    term_data.setdefault("disease_class", "Genetic Research")
    
    # 3. بيانات النظام
    term_data['user_id'] = current_user.id
    term_data['status'] = 'pending'
    
    # 4. إنشاء الكائن
    new_term = models.Term(**term_data)
    
    try:
        db.add(new_term)
        db.commit()
        db.refresh(new_term)
    except Exception as e:
        db.rollback()
        # هنا ستعرف لماذا لا يضيف! اطبع الخطأ في التيرمنال
        print("Database Error:", e) 
        raise HTTPException(status_code=500, detail=str(e))
    
    return {"message": "تمت الإضافة بنجاح", "term_id": new_term.id}
    
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
@app.get("/terms/uu/")
def get_terms(term_id: Optional[int] = None, db: Session = Depends(database.get_db)):
    # 1. إذا تم طلب ID محدد
    if term_id is not None:
        term = db.query(models.Term).filter(models.Term.id == term_id).first()
        if not term:
            raise HTTPException(status_code=404, detail="المصطلح غير موجود")
        return term
    
    # 2. إذا تم طلب الكل، مع التصفية والترتيب (بدون دالة مكررة)
    terms = db.query(models.Term).filter(models.Term.status != 'rejected').order_by(models.Term.id.desc()).all()
    return terms

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

    terms = db.query(models.Term).filter(models.Term.status != 'rejected').order_by(models.Term.id.desc()).all()
    return terms
    # من ملف DNAToolKit.py المرفق في مستندك
Nucleotides = ['A', 'G', 'T', 'C'] #

# دالة التحقق من صحة التسلسل (من ملفك المرفق)
def validateSeq(dna_seq):
    tmpseq = dna_seq.upper() 
    for nuc in tmpseq:
        if nuc not in Nucleotides: 
            return False
    return tmpseq 

# دالة حساب تكرار القواعد (من ملفك المرفق)
def countNucFrequency(seq):
    tmpFreqDict = {"A": 0, "C": 0, "G": 0, "T": 0} 
    for nuc in seq:
        if nuc in tmpFreqDict:
            tmpFreqDict[nuc] += 1 
    return tmpFreqDict 

class SequenceCompareRequest(BaseModel):
    seq1: str
    seq2: str
@app.post("/analyze-sequence/")
def analyze_sequence(payload: dict):
    # استقبال السلسلة المدخلة من المستخدم وتفريغها من المسافات
    user_seq = payload.get("sequence", "").strip().upper()
    
    if not user_seq:
        raise HTTPException(status_code=400, detail="لم يتم إدخال أي تسلسل.")
        
    # فحص السلسلة
    valid_seq = validateSeq(user_seq)
    
    if not valid_seq:
        raise HTTPException(status_code=400, detail="التسلسل غير صالح. يجب أن يحتوي فقط على A, C, T, G.")
        
    # حساب التكرارات
    frequencies = countNucFrequency(valid_seq)
    
    return {
        "status": "success",
        "validated_sequence": valid_seq,
        "nuc_frequencies": frequencies,
        "message": "تم فحص التسلسل المتغير بنجاح."
    }

# (اختياري) مسار لتوليد سلسلة عشوائية كما في مستندك
@app.post("/generate-random-seq/")
def generate_random_seq(payload: dict):
    length = payload.get("length", 3000)
    # توليد حروف بصورة عشوائية (الكود من مستندك)
    rndDNAStr = ''.join([random.choice(Nucleotides) for nuc in range(length)]) #
    
    return analyze_sequence({"sequence": rndDNAStr})
@app.get("/generate-random-dna/")
def generate_random_dna():
    import random
    Nucleotides = ['A', 'G', 'T', 'C']
    # توليد سلسلة عشوائية بطول 20
    rnd_seq = ''.join([random.choice(Nucleotides) for _ in range(20)])
    return {"status": "success", "sequence": rnd_seq}
@app.post("/find-closest/")
def find_closest(payload: dict):
    # السلسلة الأساسية أو المجهولة
    input_seq = payload.get("input_seq", "").strip().upper()
    # السلسلة الأولى للمقارنة
    seq1 = payload.get("seq1", "").strip().upper()
    # السلسلة الثانية للمقارنة
    seq2 = payload.get("seq2", "").strip().upper()
    
    # يمكن إضافة دالة التحقق validateSeq هنا للتأكد من سلامة الحروف
    
    aligner = Align.PairwiseAligner()
    
    # حساب نسبة التطابق مع السلسلة الأولى
    score1 = 0
    alignments1 = aligner.align(input_seq, seq1)
    if alignments1:
        score1 = alignments1[0].score
        
    # حساب نسبة التطابق مع السلسلة الثانية
    score2 = 0
    alignments2 = aligner.align(input_seq, seq2)
    if alignments2:
        score2 = alignments2[0].score
        
    # تحديد الأقرب بناءً على الدرجة الأعلى
    if score1 == 0 and score2 == 0:
        winner = "لا يوجد تطابق مع أي من السلسلتين."
    elif score1 >= score2:
        winner = "السلسلة الأولى (Seq 1) هي الأقرب."
    else:
        winner = "السلسلة الثانية (Seq 2) هي الأقرب."

    return {
        "status": "success",
        "score_seq1": score1,
        "score_seq2": score2,
        "closest": winner
    }
@app.post("/compare-with-ncbi/")
def compare_with_ncbi(payload: dict):
    # استلام التسلسلات
    ncbi_seq = payload.get("ncbi_seq", "").strip().upper()
    seq1 = payload.get("seq1", "").strip().upper()
    seq2 = payload.get("seq2", "").strip().upper()
    
    aligner = Align.PairwiseAligner()
    
    # مقارنة تسلسل NCBI بالسلسلة الأولى
    score1 = 0
    alignments1 = aligner.align(ncbi_seq, seq1)
    if alignments1:
        score1 = alignments1[0].score
        
    # مقارنة تسلسل NCBI بالسلسلة الثانية
    score2 = 0
    alignments2 = aligner.align(ncbi_seq, seq2)
    if alignments2:
        score2 = alignments2[0].score
        
    # تحديد الأقرب
    if score1 == 0 and score2 == 0:
        winner = "لا يوجد تطابق مع تسلسل NCBI."
    elif score1 >= score2:
        winner = "السلسلة الأولى (Seq 1) هي الأقرب لتسلسل NCBI."
    else:
        winner = "السلسلة الثانية (Seq 2) هي الأقرب لتسلسل NCBI."

    return {
        "status": "success",
        "score_seq1": score1,
        "score_seq2": score2,
        "closest": winner
    }

class TermSaveRequest(BaseModel):
    fasta_seq: str
    disease_class: str
    confidence_score: float
import difflib
from fastapi import HTTPException

@app.post("/analyze-sequences/")
def analyze_sequences(req: SequenceCompareRequest):
    s1 = req.seq1.strip().upper()
    s2 = req.seq2.strip().upper()
    
    # دالة مساعدة لحساب نسبة التشابه بين سلسلتين مئوياً
    def calculate_confidence(seq1, seq2):
        if not seq1 or not seq2:
            return 0.0
        matcher = difflib.SequenceMatcher(None, seq1, seq2)
        return round(matcher.ratio() * 100, 2)

    # جلب التسلسلات والأمراض المسجلة في قاعدة البيانات للمقارنة معها
    try:
        connection = get_db_connection() # نستخدم دالة الاتصال
        with connection.cursor() as cursor:
            query = "SELECT fasta_seq, disease_class FROM terms WHERE fasta_seq IS NOT NULL AND fasta_seq != ''"
            cursor.execute(query)
            db_terms = cursor.fetchall()
        connection.close()
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Database error: {str(e)}")

    # إيجاد أقرب تطابق لـ s1
    best_match_s1 = {"disease": "لا يوجد تطابق قريب", "score": 0.0}
    if db_terms:
        for term in db_terms:
            score = calculate_confidence(s1, term['fasta_seq'].upper())
            if score > best_match_s1["score"]:
                best_match_s1["score"] = score
                best_match_s1["disease"] = term['disease_class']

    # إيجاد أقرب تطابق لـ s2 (نستخدم الدالة المساعدة بشكل صحيح)
    best_match_s2 = {"disease": "لا يوجد تطابق قريب", "score": 0.0}
    if db_terms:
        for term in db_terms:
            score = calculate_confidence(s2, term['fasta_seq'].upper())
            if score > best_match_s2["score"]:
                best_match_s2["score"] = score
                best_match_s2["disease"] = term['disease_class']

    return {
        "status": "success",
        "seq1_analysis": {
            "sequence": s1,
            "associated_disease": best_match_s1["disease"],
            "confidence_score": best_match_s1["score"]
        },
        "seq2_analysis": {
            "sequence": s2,
            "associated_disease": best_match_s2["disease"],
            "confidence_score": best_match_s2["score"]
        }
    }
class DnaFullImportRequest(BaseModel):
    accession_id: str
    term_name: Optional[str] = None
    trans: Optional[str] = None
    defe: Optional[str] = None
    disease_class: Optional[str] = "N/A - Genetic Sequence"

class DnaFullImportRequest(BaseModel):
    accession_id: str
    term_name: Optional[str] = None
    trans: Optional[str] = None
    defe: Optional[str] = None
    disease_class: Optional[str] = "N/A - Genetic Sequence"
    smiles_code: Optional[str] = "N/A"

# 2. الدالة (Endpoint)
class DrugRequest(BaseModel):
    drug_name: str

@app.post("/api/import-dna-complete/")
def add_drug_via_api(req: DrugRequest, db: Session = Depends(get_db)):
    drug_name = req.drug_name.strip()
    if not drug_name:
        raise HTTPException(status_code=400, detail="يجب إدخال اسم الدواء.")

    molecule = new_client.molecule
    res = molecule.search(drug_name)

    if not res:
        raise HTTPException(status_code=404, detail=f"لم يتم العثور على الدواء: {drug_name} في ChEMBL.")

    data = res[0]
    name_en = data.get('pref_name', drug_name)
    
    structures = data.get('molecule_structures')
    smiles = structures.get('canonical_smiles', 'N/A') if structures else 'N/A'
    
    properties = data.get('molecule_properties')
    mw = properties.get('full_mwt', 'Unknown') if properties else 'Unknown'
    
    description = f"Molecular Weight: {mw} g/mol. Source: ChEMBL Database."

    try:
        db_term = Term(
            term=name_en,
            trans=name_en,
            defe=description,
            smiles_code=smiles,
            picture="pic/chembl_logo.png",
            status="approved",
            user_id=46,
            fasta_seq=clean_sequence, # <--- هنا يتم الحفظ
            disease_class="Hemophilia Related"
        )
        db.add(db_term)
        db.commit()
        db.refresh(db_term)
    except Exception as e:
        db.rollback()
        raise HTTPException(status_code=500, detail=f"خطأ في حفظ الدواء بقاعدة البيانات: {str(e)}")

    return {
        "status": "success",
        "message": f"تم جلب بيانات {name_en} وحفظها بنجاح.",
        "drug_name": name_en,
        "smiles": smiles
    }

class TermCreate(BaseModel):
    term: str
    trans: str
    defe: str
    user_id: int
    
    # حقول اختيارية بقيم افتراضية لتجنب الأخطاء
    smiles_code: Optional[str] = "N/A"
    picture: Optional[str] = "yyy.jpg"
    status: Optional[str] = "pending"
    sequence: Optional[str] = None
    fasta_seq: Optional[str] = None
    disease_class: Optional[str] = None
    confidence_score: Optional[float] = None


    
if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="127.0.0.1", port=8000)
