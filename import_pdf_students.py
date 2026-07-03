import sqlite3
import re

try:
    import PyPDF2
except ImportError:
    import os
    os.system("pip3 install PyPDF2")
    import PyPDF2

DB_PATH = 'hekmat.db'
PDF_PATH = 'hekmatmali.pdf'

def extract_names_from_pdf():
    reader = PyPDF2.PdfReader(PDF_PATH)
    text = ""
    for page in reader.pages:
        text += page.extract_text() + "\n"
    
    # We look for lines that start with a number followed by words
    # Example: 41 صادق نجمی 301018909446...
    # We can match: ^\d+\s+([^\d\n]+)
    
    extracted_names = []
    lines = text.split('\n')
    for line in lines:
        line = line.strip()
        # skip lone page numbers
        if line.isdigit():
            continue
            
        match = re.match(r'^(\d+)\s+([^\d]+)', line)
        if match:
            num = match.group(1)
            raw_name = match.group(2).strip()
            # The name might be followed by other things if there are no numbers, but in our sample:
            # "صادق نجمی " -> perfect. 
            if len(raw_name) > 2:
                extracted_names.append(raw_name)
    return extracted_names

def migrate_pdf():
    print("Reading PDF...")
    names = extract_names_from_pdf()
    print(f"Found {len(names)} entries in PDF.")
    
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    # Load existing students to avoid duplicates
    cursor.execute("SELECT id, name, surname FROM students")
    existing = cursor.fetchall()
    
    existing_full_names = []
    for r in existing:
        fname = f"{r[1]} {r[2]}".replace('  ', ' ').strip().replace('ي', 'ی').replace('ك', 'ک')
        existing_full_names.append(fname)
        
    inserted_count = 0
    for raw_name in names:
        # Clean Arabic/Persian chars to match properly
        clean_name = raw_name.replace('ي', 'ی').replace('ك', 'ک').replace('  ', ' ').strip()
        
        # Check standard matching
        already_exists = False
        for ex in existing_full_names:
            if clean_name in ex or ex in clean_name:
                already_exists = True
                break
                
        if not already_exists:
            # Split into name and surname
            parts = clean_name.split(' ')
            if len(parts) >= 2:
                surname = parts[-1]
                name = ' '.join(parts[:-1])
            else:
                name = clean_name
                surname = ''
                
            code = f"NEW_PDF_{inserted_count+1}"
            cursor.execute("""
                INSERT INTO students (code, name, surname, status, notes)
                VALUES (?, ?, ?, 'active', 'وارد شده به صورت خودکار از فایل پرداخت‌ها')
            """, (code, name, surname))
            inserted_count += 1
            print(f" [+] Inserted: {name} {surname}")
            
    conn.commit()
    conn.close()
    
    print(f"✅ Finished! Added {inserted_count} new students.")

if __name__ == "__main__":
    migrate_pdf()
