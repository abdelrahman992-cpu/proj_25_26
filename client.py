import requests
import json
from deep_translator import GoogleTranslator

# 1. كلاس التعامل مع الـ API (البحث والإضافة العامة)
class DictionaryAPI:
    def __init__(self, base_url, api_key):
        self.base_url = base_url
        self.api_key = api_key
        self.headers = {
            "Content-Type": "application/json"
        }

    def search_term(self, query):
        url = f"{self.base_url}?search={query}&api_key={self.api_key}"
        response = requests.get(url)
        if response.status_code == 200:
            return response.json()
        return {"status": "error", "message": "Connection failed"}

    def add_term(self, term, trans, description, smiles="N/A"):
        payload = {
            "api_key": self.api_key,
            "term": term,
            "trans": trans,
            "defe": description,
            "smiles_code": smiles
        }
        # نرسلها كـ JSON أو كـ Data حسب إعداد السيرفر عندك
        response = requests.post(self.site_url, json=payload) # استخدام json= يرسلها بالتنسيق الصحيح
        return response.json()

# 2. كلاس البوت المتخصص في سحب بيانات NCBI وترجمتها
class BioDictionaryBot:
    def __init__(self, site_url, api_key):
        self.site_url = site_url
        self.api_key = api_key
        self.ncbi_base = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/"

    def fetch_and_upload_gene(self, gene_id):
        print(f"🧬 جاري جلب بيانات الجين ID: {gene_id} من NCBI...")
        
        # جلب البيانات من NCBI
        summary_url = f"{self.ncbi_base}esummary.fcgi?db=gene&id={gene_id}&retmode=json"
        res = requests.get(summary_url)
        data = res.json()

        if 'result' in data and str(gene_id) in data['result']:
            gene_info = data['result'][str(gene_id)]
            name = f"Gene: {gene_info['name']}"
            desc = gene_info['description']
            summary = gene_info.get('summary', 'No summary available')
            
            print(f"🌍 جاري ترجمة المصطلح: {name}...")
            try:
                # ترجمة الاسم والوصف للعربية
                translated_name = GoogleTranslator(source='en', target='ar').translate(name)
                translated_desc = GoogleTranslator(source='en', target='ar').translate(desc)
                translated_summary = GoogleTranslator(source='en', target='ar').translate(summary)
            except Exception as e:
                print(f"⚠️ فشلت الترجمة، سيتم استخدام النص الأصلي: {e}")
                translated_name = name
                translated_desc = desc
                translated_summary = summary

            # رفع البيانات لموقعك
            print(f"🚀 جاري رفع المصطلح إلى موقعك...")
            payload = {
                "api_key": self.api_key,
                "term": name,
                "trans": translated_name, # الاسم المترجم
                "defe": translated_summary, # الملخص المترجم
                "smiles_code": "N/A"
            }
            
            # نرسلها بـ POST عادي لضمان التوافق مع PHP
            response = requests.post(self.site_url, data=payload)
            print("--- رد السيرفر ---")
            print(response.text) # هذا السطر سيطبع لنا خطأ MySQL الحقيقي
            return response.text
        
        return "❌ فشل في جلب البيانات من NCBI"

# --- الجزء الخاص بالتشغيل ---

# تأكد من صحة الروابط هنا
API_URL = "http://localhost/dbdictionary/api.php" 
MY_KEY = "my_secret_key_123"

# 1. تجربة البوت (جلب من NCBI وترجمة ورفع)
bot = BioDictionaryBot(API_URL, MY_KEY)
print(bot.fetch_and_upload_gene(7157)) # جين TP53

# 2. تجربة البحث العادي (اختياري)
# client = DictionaryAPI(API_URL, MY_KEY)
# print(client.search_term("DNA"))
