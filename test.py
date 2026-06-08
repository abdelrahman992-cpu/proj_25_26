import requests
from chembl_webresource_client.new_client import new_client

def add_drug_to_my_site(drug_name):
    # 1. البحث في قاعدة بيانات ChEMBL
    print(f"🔍 جاري البحث عن {drug_name} في قاعدة بيانات ChEMBL...")
    molecule = new_client.molecule
    res = molecule.search(drug_name)

    if not res:
        print(f"❌ لم يتم العثور على الدواء: {drug_name}")
        return

    data = res[0]
    name_en = data.get('pref_name', drug_name)
    # حماية في حال عدم وجود SMILES
    structures = data.get('molecule_structures')
    smiles = structures.get('canonical_smiles', 'N/A') if structures else 'N/A'
    
    properties = data.get('molecule_properties')
    mw = properties.get('full_mwt', 'Unknown') if properties else 'Unknown'
    
    description = f"Molecular Weight: {mw} g/mol. Source: ChEMBL Database."

    # 2. إعدادات الإرسال
    url = "http://localhost/dbdictionary/Add_Term.php" 
    
    payload = {
        'txt_term': name_en,
        'TextArea1': description,
        'trans': name_en, 
        'smiles_code': smiles,
        'api_key': 'my_secret_key_123', 
        'Submit1': 'save'
    }

    # 3. تنفيذ الإرسال (يجب أن يكون داخل الدالة - لاحظ المسافة)
    try:
        response = requests.post(url, data=payload)
        print(f"📡 رد السيرفر لـ {drug_name}: {response.text.strip()}") 
        
        if "Success" in response.text or "✅" in response.text:
            print(f"✅ تم إضافة {name_en} بنجاح!")
        else:
            print(f"⚠️ لم يتم تأكيد إضافة {drug_name}.")
    except Exception as e:
        print(f"❌ خطأ اتصال: {e}")

# --- تشغيل السكربت ---
drugs_to_add = ["Aspirin", "Caffeine", "Paracetamol", "Ibuprofen"]

for d in drugs_to_add:
    add_drug_to_my_site(d)
