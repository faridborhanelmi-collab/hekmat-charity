#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import pandas as pd
import sqlite3
import re
import os
import unicodedata

DB_PATH = 'hekmat.db'
EXCEL_PATH = '_parsian_invoice.xlsx.xlsx'

MONTH_NAMES = {
    '01': 'فروردین', '02': 'اردیبهشت', '03': 'خرداد',
    '04': 'تیر', '05': 'مرداد', '06': 'شهریور',
    '07': 'مهر', '08': 'آبان', '09': 'آذر',
    '10': 'دی', '11': 'بهمن', '12': 'اسفند'
}

def clean_farsi(text):
    if not text or pd.isnull(text):
        return ''
    text = str(text)
    text = unicodedata.normalize('NFKC', text)
    repl = {
        'ي': 'ی', 'ك': 'ک', 'ة': 'ه',
        '٠': '۰', '١': '۱', '٢': '۲', '٣': '۳', '٤': '۴',
        '٥': '۵', '٦': '۶', '٧': '۷', '٨': '۸', '٩': '۹'
    }
    for k, v in repl.items():
        text = text.replace(k, v)
    return text.replace('\u200c', ' ').strip()

def normalize_name(s):
    s = clean_farsi(s)
    return re.sub(r'[^\w]', '', s)

def get_name_words(s):
    s = clean_farsi(s)
    words = re.findall(r'\w+', s)
    stop_words = {'به', 'نام', 'بانک', 'ملی', 'ملت', 'شبا', 'شماره', 'تراکنش', 'پل', 'ساتنا', 'پایا', 'صاحب', 'سپرده', 'مبدا', 'مقصد', 'حساب'}
    return set(w for w in words if len(w) > 1 and w not in stop_words)

def parse_date(date_str):
    # e.g. "1405/06/06 - 10:54:26" -> ("1405/06/06", "1405", "شهریور")
    cleaned = clean_farsi(date_str)
    m = re.search(r'(\d{4})/(\d{2})/(\d{2})', cleaned)
    if m:
        y, mo, d = m.group(1), m.group(2), m.group(3)
        dt = f"{y}/{mo}/{d}"
        m_name = MONTH_NAMES.get(mo, mo)
        return dt, y, m_name
    return cleaned[:10], '', ''

def extract_card_number(desc):
    match = re.search(r'کارت\s+(\d{16})', desc)
    if match:
        return match.group(1)
    return None

def extract_receipt_no(desc, row_no):
    # Extract tracking or retrieval number
    m = re.search(r'شماره پیگیری\s+(\d+)', desc)
    if m:
        return m.group(1)
    m2 = re.search(r'شماره بازیابی\s+(\d+)', desc)
    if m2:
        return m2.group(1)
    m3 = re.search(r'رهگیری:\s*(\d+)', desc)
    if m3:
        return m3.group(1)
    return str(row_no)

def run_import():
    if not os.path.exists(EXCEL_PATH):
        print(f"Error: {EXCEL_PATH} not found.")
        return

    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()

    # 1. Load Donors
    cursor.execute("SELECT id, name, surname, card_number FROM donors")
    donor_rows = cursor.fetchall()
    donor_by_card = {}
    donor_by_norm = {}
    donor_word_map = []
    
    for d in donor_rows:
        did, fn, ln, card = d
        fn_c = clean_farsi(fn)
        ln_c = clean_farsi(ln)
        full = f"{fn_c} {ln_c}".strip()
        
        if card and card.strip():
            donor_by_card[card.strip()] = did
            
        norm1 = normalize_name(f"{fn_c}{ln_c}")
        norm2 = normalize_name(f"{ln_c}{fn_c}")
        if norm1: donor_by_norm[norm1] = did
        if norm2: donor_by_norm[norm2] = did
        
        wset = get_name_words(full)
        if wset and 'ناشناس' not in fn_c:
            donor_word_map.append((did, full, wset))

    donor_word_map.sort(key=lambda x: len(x[2]), reverse=True)

    # Ensure "سود سپرده بانکی" donor exists for interest deposits
    cursor.execute("SELECT id FROM donors WHERE name = 'سود سپرده' OR name = 'بانک پارسیان (سود سپرده)'")
    res_interest = cursor.fetchone()
    if res_interest:
        interest_donor_id = res_interest[0]
    else:
        cursor.execute("INSERT INTO donors (name, surname, join_date, description) VALUES ('بانک پارسیان', '(سود سپرده)', '1401/01/01', 'درآمدهای ناشی از سود سپرده بانکی')")
        interest_donor_id = cursor.lastrowid

    # 2. Load Students
    cursor.execute("SELECT id, name, surname, code FROM students")
    student_rows = cursor.fetchall()
    student_by_norm = {}
    student_word_map = []
    
    for s in student_rows:
        sid, fn, ln, code = s
        fn_c = clean_farsi(fn)
        ln_c = clean_farsi(ln)
        full = f"{fn_c} {ln_c}".strip()
        
        norm1 = normalize_name(f"{fn_c}{ln_c}")
        norm2 = normalize_name(f"{ln_c}{fn_c}")
        if norm1: student_by_norm[norm1] = sid
        if norm2: student_by_norm[norm2] = sid
        
        wset = get_name_words(full)
        if wset:
            student_word_map.append((sid, full, wset))

    student_word_map.sort(key=lambda x: len(x[2]), reverse=True)

    # Ensure "GENERAL" student exists
    cursor.execute("SELECT id FROM students WHERE code = 'GENERAL'")
    res_gen = cursor.fetchone()
    if res_gen:
        general_student_id = res_gen[0]
    else:
        cursor.execute("INSERT INTO students (code, name, surname, status) VALUES ('GENERAL', 'بنیاد حکمت', '(هزینه‌های عمومی)', 'active')")
        general_student_id = cursor.lastrowid

    # 3. Load Existing Donations & Expenses for duplicate prevention
    cursor.execute("SELECT amount, date, description FROM donations")
    existing_donations = set()
    for r in cursor.fetchall():
        existing_donations.add((int(r[0]), clean_farsi(r[1])[:10], clean_farsi(r[2])))

    cursor.execute("SELECT amount, expense_date, description FROM expenses")
    existing_expenses = set()
    for r in cursor.fetchall():
        existing_expenses.add((int(r[0]), clean_farsi(r[1])[:10], clean_farsi(r[2])))

    # 4. Read Excel File
    df = pd.read_excel(EXCEL_PATH)
    print(f"Processing {len(df)} rows from {EXCEL_PATH}...")

    new_donations = 0
    skipped_donations = 0
    new_expenses = 0
    skipped_expenses = 0
    new_donors_created = 0
    updated_donor_cards = 0

    for idx, row in df.iterrows():
        row_no = row.get('ردیف', idx + 1)
        raw_desc = clean_farsi(row.get('توضیحات', ''))
        if not raw_desc:
            continue

        raw_date = clean_farsi(row.get('تاریخ', ''))
        date_formatted, year, month_name = parse_date(raw_date)

        tx_type = clean_farsi(row.get('واریز/ برداشت', ''))
        amount_raw = str(row.get('مبلغ (ریال)', 0)).replace('+', '').replace('-', '').replace(',', '').strip()
        try:
            amount = int(float(amount_raw))
        except:
            amount = 0

        if amount <= 0:
            continue

        receipt_no = extract_receipt_no(raw_desc, row_no)

        # -------------------------------------------------------------
        # DEPOSITS (واریز) -> donations & donors
        # -------------------------------------------------------------
        if tx_type == 'واریز':
            # Check duplicate
            if (amount, date_formatted, raw_desc) in existing_donations:
                skipped_donations += 1
                continue

            donor_id = None

            # A. Bank interest
            if 'سود علی الحساب' in raw_desc or 'سود سپرده' in raw_desc:
                donor_id = interest_donor_id
            else:
                card_num = extract_card_number(raw_desc)
                
                # B. Try 16-digit card match
                if card_num and card_num in donor_by_card:
                    donor_id = donor_by_card[card_num]
                else:
                    # C. Try extracted name match
                    extracted_name = None
                    m_mob = re.search(r'صاحب سپرده مبدا:\s*([^،\n]+)', raw_desc)
                    if m_mob:
                        extracted_name = m_mob.group(1).strip()
                    else:
                        m_pol = re.search(r'به نامِ?\s+([^،\n]+?)\s+و شماره شبا', raw_desc)
                        if m_pol:
                            extracted_name = m_pol.group(1).strip()
                        else:
                            m_sat = re.search(r'به نام\s+([^،\n]+?)\s+بابت', raw_desc)
                            if m_sat:
                                extracted_name = m_sat.group(1).strip()
                            else:
                                m_nam = re.search(r'نام:\s*([^،\n]+?)\s+شبا', raw_desc)
                                if m_nam:
                                    extracted_name = m_nam.group(1).strip()

                    if extracted_name:
                        norm_ex = normalize_name(extracted_name)
                        if norm_ex in donor_by_norm:
                            donor_id = donor_by_norm[norm_ex]
                        else:
                            # Try word overlap
                            ex_words = get_name_words(extracted_name)
                            best_match_id = None
                            best_overlap = 0
                            for did, full_dname, wset in donor_word_map:
                                overlap = len(ex_words.intersection(wset))
                                if overlap >= 2 or (len(wset) == 1 and overlap == 1):
                                    if overlap > best_overlap:
                                        best_overlap = overlap
                                        best_match_id = did
                            if best_match_id:
                                donor_id = best_match_id

                    # D. If still not matched, search whole description against donor word map
                    if not donor_id:
                        desc_words = get_name_words(raw_desc)
                        best_match_id = None
                        best_overlap = 0
                        for did, full_dname, wset in donor_word_map:
                            overlap = len(desc_words.intersection(wset))
                            if (len(wset) >= 2 and overlap >= 2) or (len(wset) == 1 and overlap == 1 and len(full_dname) > 4):
                                if overlap > best_overlap:
                                    best_overlap = overlap
                                    best_match_id = did
                        if best_match_id:
                            donor_id = best_match_id

                # If donor found and has a new card number, update donor's card
                if donor_id and card_num and card_num not in donor_by_card:
                    cursor.execute("UPDATE donors SET card_number = ? WHERE id = ? AND (card_number IS NULL OR card_number = '')", (card_num, donor_id))
                    donor_by_card[card_num] = donor_id
                    updated_donor_cards += 1

                # E. If NOT found, create new donor
                if not donor_id:
                    if extracted_name:
                        parts = extracted_name.split()
                        if len(parts) >= 2:
                            fname = parts[0]
                            lname = " ".join(parts[1:])
                        else:
                            fname = extracted_name
                            lname = ""
                        cursor.execute("INSERT INTO donors (name, surname, join_date, card_number) VALUES (?, ?, ?, ?)",
                                       (fname, lname, date_formatted, card_num))
                        donor_id = cursor.lastrowid
                        new_donors_created += 1
                        
                        norm1 = normalize_name(f"{fname}{lname}")
                        norm2 = normalize_name(f"{lname}{fname}")
                        if norm1: donor_by_norm[norm1] = donor_id
                        if norm2: donor_by_norm[norm2] = donor_id
                        if card_num: donor_by_card[card_num] = donor_id
                        wset = get_name_words(f"{fname} {lname}")
                        if wset: donor_word_map.append((donor_id, f"{fname} {lname}", wset))
                    elif card_num:
                        cursor.execute("INSERT INTO donors (name, surname, join_date, card_number) VALUES (?, ?, ?, ?)",
                                       ("ناشناس", f"(کارت {card_num})", date_formatted, card_num))
                        donor_id = cursor.lastrowid
                        new_donors_created += 1
                        donor_by_card[card_num] = donor_id
                    else:
                        cursor.execute("INSERT INTO donors (name, surname, join_date) VALUES (?, ?, ?)",
                                       ("واریز ناشناس", f"({date_formatted})", date_formatted))
                        donor_id = cursor.lastrowid
                        new_donors_created += 1

            # Insert into donations
            cursor.execute("""
                INSERT INTO donations (donor_id, amount, date, month, year, receipt_no, description)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            """, (donor_id, amount, date_formatted, month_name, year, receipt_no, raw_desc))
            existing_donations.add((amount, date_formatted, raw_desc))
            new_donations += 1

        # -------------------------------------------------------------
        # WITHDRAWALS (برداشت) -> expenses & students
        # -------------------------------------------------------------
        elif tx_type == 'برداشت':
            # Check duplicate
            if (amount, date_formatted, raw_desc) in existing_expenses:
                skipped_expenses += 1
                continue

            category_id = 1
            student_id = None

            # Category 9: Bank Fee
            if raw_desc.startswith('کارمزد') or 'کارمزد پایا' in raw_desc or 'کارمزد انتقال وجه' in raw_desc:
                category_id = 9
                student_id = None
            else:
                # Determine Category
                if 'بورسیه' in raw_desc or 'بورس' in raw_desc:
                    category_id = 3
                elif any(k in raw_desc for k in ['کتاب', 'مدرسه', 'آموزش', 'کلاس', 'ثبتنام', 'دانشگاه']):
                    category_id = 6
                elif any(k in raw_desc for k in ['درمان', 'پزشک', 'بیمارستان', 'ویزیت', 'دارو', 'عینک']):
                    category_id = 7
                elif any(k in raw_desc for k in ['تغذیه', 'معیشت', 'ارزاق', 'بسته']):
                    category_id = 8
                elif any(k in raw_desc for k in ['حقوق', 'دستمزد', 'پرسنل']):
                    category_id = 2
                else:
                    category_id = 1

                # Match Student
                extracted_sname = None
                m_dest = re.search(r'صاحب سپرده مقصد:\s*([^،\n]+)', raw_desc)
                if m_dest:
                    extracted_sname = m_dest.group(1).strip()
                else:
                    m_burs = re.search(r'بورسیه\s+(?:.*?)\s+(?:14\d\d)\s+([^\n,-]+)', raw_desc)
                    if m_burs:
                        extracted_sname = m_burs.group(1).strip()
                    else:
                        m_bat = re.search(r'بابت\s*_@@(?:کمک هزینه|هزینه|بورسیه)?\s*([^\n,]+)', raw_desc)
                        if m_bat:
                            extracted_sname = m_bat.group(1).strip()

                if extracted_sname:
                    norm_s = normalize_name(extracted_sname)
                    if norm_s in student_by_norm:
                        student_id = student_by_norm[norm_s]
                    else:
                        swords = get_name_words(extracted_sname)
                        best_match_id = None
                        best_overlap = 0
                        for sid, full_sname, wset in student_word_map:
                            overlap = len(swords.intersection(wset))
                            if overlap >= 2 or (len(wset) == 1 and overlap == 1):
                                if overlap > best_overlap:
                                    best_overlap = overlap
                                    best_match_id = sid
                        if best_match_id:
                            student_id = best_match_id

                if not student_id:
                    desc_words = get_name_words(raw_desc)
                    best_match_id = None
                    best_overlap = 0
                    for sid, full_sname, wset in student_word_map:
                        overlap = len(desc_words.intersection(wset))
                        if (len(wset) >= 2 and overlap >= 2) or (len(wset) == 1 and overlap == 1 and len(full_sname) > 4):
                            if overlap > best_overlap:
                                best_overlap = overlap
                                best_match_id = sid
                    if best_match_id:
                        student_id = best_match_id

                if not student_id:
                    student_id = general_student_id

            cursor.execute("""
                INSERT INTO expenses (student_id, amount, description, expense_date, receipt_no, category_id)
                VALUES (?, ?, ?, ?, ?, ?)
            """, (student_id, amount, raw_desc, date_formatted, receipt_no, category_id))
            existing_expenses.add((amount, date_formatted, raw_desc))
            new_expenses += 1

    # 5. Recalculate and update total_donated for ALL donors
    print("Recalculating total_donated for all donors...")
    cursor.execute("""
        UPDATE donors 
        SET total_donated = COALESCE((
            SELECT SUM(amount) 
            FROM donations 
            WHERE donations.donor_id = donors.id
        ), 0)
    """)

    conn.commit()

    # 6. Fetch stats
    cursor.execute("SELECT COUNT(*) FROM donations")
    total_donations_count = cursor.fetchone()[0]
    cursor.execute("SELECT SUM(amount) FROM donations")
    total_donations_sum = cursor.fetchone()[0] or 0

    cursor.execute("SELECT COUNT(*) FROM expenses")
    total_expenses_count = cursor.fetchone()[0]
    cursor.execute("SELECT SUM(amount) FROM expenses")
    total_expenses_sum = cursor.fetchone()[0] or 0

    cursor.execute("SELECT COUNT(*) FROM donors")
    total_donors_count = cursor.fetchone()[0]

    conn.close()

    print("\n================ IMPORT REPORT ================")
    print(f"New Donations Inserted: {new_donations}")
    print(f"Skipped Duplicate Donations: {skipped_donations}")
    print(f"New Expenses Inserted: {new_expenses}")
    print(f"Skipped Duplicate Expenses: {skipped_expenses}")
    print(f"New Donors Created: {new_donors_created}")
    print(f"Updated Donor Cards: {updated_donor_cards}")
    print("-----------------------------------------------")
    print(f"Total Donors in DB: {total_donors_count}")
    print(f"Total Donations in DB: {total_donations_count} (Sum: {total_donations_sum:,} Rials)")
    print(f"Total Expenses in DB: {total_expenses_count} (Sum: {total_expenses_sum:,} Rials)")
    print(f"Current Net Balance: {total_donations_sum - total_expenses_sum:,} Rials")
    print("===============================================")

if __name__ == '__main__':
    run_import()
