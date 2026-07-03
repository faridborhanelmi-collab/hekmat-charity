import pandas as pd
import sqlite3
import os
import re

DB_PATH = 'hekmat.db'

def clean(val):
    if pd.isnull(val): return ''
    s = str(val).strip().replace('\n', ' ')
    if s.endswith('.0'):
        return s[:-2]
    return s

def migrate():
    file_path = 'finalmali2.xlsx'
    if not os.path.exists(file_path):
        print(f"File {file_path} not found.")
        return

    # ---- Load sheets ----
    try:
        df_students = pd.read_excel(file_path, sheet_name=' اطلاعات ورودد مددجو', header=None)
        df_donors   = pd.read_excel(file_path, sheet_name='لیست واریزی کل خیرین', header=None)
        df_donations = pd.read_excel(file_path, sheet_name='لیست واریزی ماهیانه', header=0)
        df_expenses = pd.read_excel(file_path, sheet_name='ریزهزینه کرد مددجوها', header=0)
    except Exception as e:
        print(f"Error loading sheets: {e}")
        return

    conn   = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()

    cursor.execute("DELETE FROM students")
    cursor.execute("DELETE FROM donors")
    cursor.execute("DELETE FROM donations")
    cursor.execute("DELETE FROM expenses")
    cursor.execute("DELETE FROM users")

    # ---- STUDENT SHEET – column-oriented ----
    # Row mapping (based on inspection):
    ROW = {
        'code':           0,
        'full_name':      1,
        'birthday':       2,
        'birth_place':    3,
        'father':         5,
        'mother':         6,
        'gender':         7,
        'national_id':    8,
        'school':         9,
        'start_date':    10,
        'address':       11,
        'postal':        12,
        'counselor':     13,
        'email':         14,
        'phone':         15,   # may vary per sheet
        'grade':         16,
        'field':         17,
    }

    imported = 0
    for col in range(6, df_students.shape[1]):
        full_name = clean(df_students.iloc[ROW['full_name'], col])
        if not full_name or full_name == 'nan':
            continue

        # Split name/surname (last word is surname)
        parts = full_name.split()
        if len(parts) >= 2:
            name    = ' '.join(parts[:-1])
            surname = parts[-1]
        else:
            name    = full_name
            surname = ''

        code      = clean(df_students.iloc[ROW['code'], col])
        birthday  = clean(df_students.iloc[ROW['birthday'], col])
        bplace    = clean(df_students.iloc[ROW['birth_place'], col])
        father    = clean(df_students.iloc[ROW['father'], col])
        mother    = clean(df_students.iloc[ROW['mother'], col])
        nat_id    = clean(df_students.iloc[ROW['national_id'], col])
        school    = clean(df_students.iloc[ROW['school'], col])
        address   = clean(df_students.iloc[ROW['address'], col])
        counselor = clean(df_students.iloc[ROW['counselor'], col])
        grade     = clean(df_students.iloc[ROW['grade'], col])
        field     = clean(df_students.iloc[ROW['field'], col])

        # Phone may span multiple rows — collect from rows 15..25
        phone_parts = []
        for r in range(15, min(25, df_students.shape[0])):
            v = clean(df_students.iloc[r, col])
            if v and re.search(r'09\d{9}', v):
                phone_parts.append(v)
        phone = ' / '.join(phone_parts) if phone_parts else ''

        if not code:
            code = f"AUTO_{col}"

        try:
            cursor.execute("""
                INSERT INTO students (
                    code, name, surname, national_id, phone, father_name, mother_name,
                    birthday, birth_place, address, school, grade, field_of_study,
                    counselor, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            """, (code, name, surname, nat_id, phone, father, mother,
                  birthday, bplace, address, school, grade, field, counselor))
            imported += 1
        except sqlite3.IntegrityError:
            code += f"_{col}"
            cursor.execute("""
                INSERT INTO students (
                    code, name, surname, national_id, phone, father_name, mother_name,
                    birthday, birth_place, address, school, grade, field_of_study,
                    counselor, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            """, (code, name, surname, nat_id, phone, father, mother,
                  birthday, bplace, address, school, grade, field, counselor))
            imported += 1

    # ---- DONORS – row-oriented ----
    d_imported = 0
    donor_map = {}
    for idx, row in df_donors.iterrows():
        if idx < 2: continue
        name    = clean(row[3])
        surname = clean(row[4])
        total   = row[9] if pd.notnull(row[9]) else 0
        phone   = clean(row[2]) if pd.notnull(row[2]) else ''
        bday    = '' 

        if not name or name in ['جمع', 'جمع کل', 'نام', 'nan']:
            continue
        try:
            total_int = int(float(str(total).replace(',', '')))
        except:
            total_int = 0

        cursor.execute("""
            INSERT INTO donors (name, surname, phone, total_donated, join_date, birthday)
            VALUES (?, ?, ?, ?, ?, ?)
        """, (name, surname, phone, total_int, '1401/01/01', bday))
        donor_id = cursor.lastrowid
        donor_map[(name, surname)] = donor_id
        d_imported += 1

    # ---- DONATIONS – row-oriented ----
    dn_imported = 0
    for idx, row in df_donations.iterrows():
        name    = clean(row.iloc[2]) 
        surname = clean(row.iloc[3]) 
        if not name or name == 'nan' or name == 'جمع کل' or name == 'جمع':
            continue
        
        date    = clean(row.iloc[4]) 
        month   = clean(row.iloc[6]) 
        
        year_raw = row.iloc[8]
        if pd.notnull(year_raw):
            try:
                year = str(int(float(year_raw)))
            except:
                year = clean(year_raw)
        else:
            year = ''
            
        receipt = clean(row.iloc[9]) 
        amount_raw = row.iloc[10] 
        try:
            amount = int(float(str(amount_raw).replace(',', '')))
        except:
            amount = 0
            
        desc    = clean(row.iloc[11]) 

        donor_id = donor_map.get((name, surname))
        
        if donor_id and amount > 0:
            cursor.execute("""
                INSERT INTO donations (donor_id, amount, date, month, year, receipt_no, description)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            """, (donor_id, amount, date, month, year, receipt, desc))
            dn_imported += 1

    # ---- EXPENSES – row-oriented ----
    # Pre-fetch all student codes and names to IDs
    cursor.execute("SELECT id, code, name, surname FROM students")
    students_db = cursor.fetchall()
    student_map_code = {str(row[1]): row[0] for row in students_db}
    # Name map: "name surname" -> id
    student_map_name = {f"{row[2]} {row[3]}".strip(): row[0] for row in students_db}
    
    # Manual aliases for poor naming in Excel (e.g. Sana/Lena)
    aliases = {
        'لنا پور دوران': 'ثنا پور دوران',
        'لنا پوردوران': 'ثنا پور دوران',
        'ثنا پوردوران': 'ثنا پور دوران',
        'الهام صمدی': 'الهام صمدی',
    }
    for alias, canonical in aliases.items():
        if canonical in student_map_name:
            student_map_name[alias] = student_map_name[canonical]

    # Sort names by length descending to match longest matches first
    sorted_names = sorted(student_map_name.items(), key=lambda x: len(x[0]), reverse=True)

    # Ensure a "General" record exists with improved naming
    cursor.execute("INSERT OR IGNORE INTO students (code, name, surname, status) VALUES ('GENERAL', 'بنیاد حکمت', '(هزینه‌های عمومی)', 'active')")
    cursor.execute("SELECT id FROM students WHERE code = 'GENERAL'")
    general_student_id = cursor.fetchone()[0]

    ex_imported = 0
    ex_skipped = 0
    for idx, row in df_expenses.iterrows():
        # df sheet header: ok, ردیف, kkkk, Column1, کد, نام ونام خانوادگی, شرح هزینه, تاریخ  هزینه, شماره فیش, مبلغ هزینه کرد, توضیحات
        code        = clean(row.iloc[4]) 
        name_excel  = clean(row.iloc[5]) 
        desc        = clean(row.iloc[6]) 
        ex_date     = clean(row.iloc[7]) 
        receipt     = clean(row.iloc[8]) 
        amount_raw  = row.iloc[9]        
        notes       = clean(row.iloc[10])

        if not ex_date and not amount_raw and not desc:
            continue
            
        try:
            # Handle commas and non-numeric values
            amt_str = str(amount_raw).replace(',', '').strip()
            if not amt_str or amt_str == 'nan':
                amount = 0
            else:
                amount = int(float(amt_str))
        except:
            amount = 0
            
        if amount == 0 and not desc:
            continue

        # Link to student
        student_id = None
        # 1. By direct code
        if code and code != 'nan' and code in student_map_code:
            student_id = student_map_code[code]
        # 2. By name column
        elif name_excel and name_excel != 'nan':
            clean_name = name_excel.replace('  ', ' ').strip()
            if clean_name in student_map_name:
                student_id = student_map_name[clean_name]
            else:
                for sname, sid in sorted_names:
                    if clean_name in sname or sname in clean_name:
                        student_id = sid
                        break
        
        # 3. By searching in description
        if not student_id:
            for sname, sid in sorted_names:
                # Use spaces or context to avoid partial matches on common names?
                # Actually for this data, full names are best
                if sname in desc:
                    student_id = sid
                    break
        
        # 4. Special manual keyword fallback for "صمدی" and "پور دوران"
        if not student_id:
            if 'صمدی' in desc:
                student_id = student_map_name.get('الهام صمدی')
            elif 'پور دوران' in desc or 'پوردوران' in desc:
                student_id = student_map_name.get('ثنا پور دوران')

        # Default to general if not found but has amount/desc
        if not student_id:
            student_id = general_student_id
            
        # Skip grand total row usually found at the end
        if 'جمع کل' in str(row.iloc[1]) or (not ex_date and amount == 0):
            continue

        cursor.execute("""
            INSERT INTO expenses (student_id, amount, description, expense_date, receipt_no, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        """, (student_id, amount, desc, ex_date, receipt, notes))
        ex_imported += 1

    # Admin user
    cursor.execute("INSERT OR IGNORE INTO users (username, password, role) VALUES ('admin', 'admin', 'admin')")
    conn.commit()

    cursor.execute("SELECT count(*) FROM students")
    s = cursor.fetchone()[0]
    cursor.execute("SELECT count(*) FROM donors")
    d = cursor.fetchone()[0]
    cursor.execute("SELECT count(*) FROM donations")
    dn = cursor.fetchone()[0]
    cursor.execute("SELECT count(*) FROM expenses")
    ex = cursor.fetchone()[0]

    print(f"✅ Migration complete: {s} students, {d} donors, {dn} donations, {ex} expenses imported.")

    conn.close()

if __name__ == "__main__":
    migrate()
