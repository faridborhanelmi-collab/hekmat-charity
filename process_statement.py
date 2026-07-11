import pandas as pd
import sqlite3
import re

DB_PATH = 'hekmat.db'
EXCEL_PATH = 'Offline_Statement_Report-14012432610-2026-06-30.xlsx'

def extract_card_number(desc):
    # e.g., "انتقال از کارت 6063731244413051 به کارت ..."
    # or "انتقال با کارت 6221061239336342 ..."
    match = re.search(r'کارت\s+(\d{16})', desc)
    if match:
        return match.group(1)
    return None

def process_statement():
    df = pd.read_excel(EXCEL_PATH, header=4)
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()

    # Pre-load Donors
    cursor.execute("SELECT id, name, surname, card_number FROM donors")
    donors = cursor.fetchall()
    
    # Pre-load Students
    cursor.execute("SELECT id, name, surname FROM students")
    students = cursor.fetchall()
    
    # Create maps
    donor_by_card = {str(d[3]): d[0] for d in donors if d[3]}
    donor_by_name = {f"{d[1]} {d[2]}".strip().replace('  ', ' '): d[0] for d in donors}
    student_by_name = {f"{s[1]} {s[2]}".strip().replace('  ', ' '): s[0] for s in students}

    # Fetch existing donations to avoid duplicates
    cursor.execute("SELECT amount, date, description FROM donations")
    existing_donations = {(int(r[0]), str(r[1]).strip(), str(r[2]).strip()) for r in cursor.fetchall()}
    
    # Fetch existing expenses
    cursor.execute("SELECT amount, expense_date, description FROM expenses")
    existing_expenses = {(int(r[0]), str(r[1]).strip(), str(r[2]).strip()) for r in cursor.fetchall()}

    # Ensure a "General" record exists
    cursor.execute("SELECT id FROM students WHERE code = 'GENERAL'")
    res = cursor.fetchone()
    if res:
        general_id = res[0]
    else:
        cursor.execute("INSERT INTO students (code, name, surname, status) VALUES ('GENERAL', 'بنیاد حکمت', '(هزینه‌های عمومی)', 'active')")
        general_id = cursor.lastrowid

    new_donations = 0
    new_expenses = 0
    updated_cards = 0
    unmatched_deposits = []
    unmatched_withdrawals = []

    for idx, row in df.iterrows():
        desc = str(row.get('شرح سند', ''))
        if desc == 'nan' or not desc.strip():
            continue
            
        desc = desc.strip()
        date = str(row.get('تاریخ', '')).strip()
        
        deposit = row.get('واریز', 0)
        withdrawal = row.get('برداشت', 0)
        
        try:
            deposit = int(float(deposit)) if pd.notnull(deposit) else 0
        except:
            deposit = 0
            
        try:
            withdrawal = int(float(withdrawal)) if pd.notnull(withdrawal) else 0
        except:
            withdrawal = 0
            
        if deposit > 0:
            card_num = extract_card_number(desc)
            donor_id = None
            
            # 1. Try card number
            if card_num and card_num in donor_by_card:
                donor_id = donor_by_card[card_num]
            else:
                # 2. Try name matching in description
                desc_clean = desc.replace('\u200c', ' ')
                for d_name, d_id in sorted(donor_by_name.items(), key=lambda x: len(x[0]), reverse=True):
                    if len(d_name) > 3 and d_name in desc_clean:
                        donor_id = d_id
                        if card_num: # update card number
                            cursor.execute("UPDATE donors SET card_number = ? WHERE id = ?", (card_num, donor_id))
                            donor_by_card[card_num] = donor_id
                            updated_cards += 1
                        break
            
            if donor_id:
                # Check duplicate
                if (deposit, date, desc) not in existing_donations:
                    cursor.execute("INSERT INTO donations (donor_id, amount, date, description) VALUES (?, ?, ?, ?)",
                                   (donor_id, deposit, date, desc))
                    existing_donations.add((deposit, date, desc))
                    new_donations += 1
            else:
                if card_num:
                    # New donor found by card!
                    cursor.execute("INSERT INTO donors (name, surname, join_date, card_number) VALUES (?, ?, ?, ?)",
                                   (f"ناشناس", f"(کارت {card_num})", date, card_num))
                    donor_id = cursor.lastrowid
                    donor_by_card[card_num] = donor_id
                    
                    cursor.execute("INSERT INTO donations (donor_id, amount, date, description) VALUES (?, ?, ?, ?)",
                                   (donor_id, deposit, date, desc))
                    existing_donations.add((deposit, date, desc))
                    new_donations += 1
                else:
                    unmatched_deposits.append((deposit, date, desc))
                    # Still insert it under a generic "Unknown Deposit" donor?
                    # Better to report it so they can manually fix.
                    # Or insert as a special donor:
                    pass
                    
        elif withdrawal > 0:
            student_id = None
            desc_clean = desc.replace('\u200c', ' ')
            for s_name, s_id in sorted(student_by_name.items(), key=lambda x: len(x[0]), reverse=True):
                if len(s_name) > 3 and s_name in desc_clean:
                    student_id = s_id
                    break
            
            if not student_id:
                student_id = general_id
                unmatched_withdrawals.append((withdrawal, date, desc))
                
            if (withdrawal, date, desc) not in existing_expenses:
                cursor.execute("INSERT INTO expenses (student_id, amount, description, expense_date) VALUES (?, ?, ?, ?)",
                               (student_id, withdrawal, desc, date))
                existing_expenses.add((withdrawal, date, desc))
                new_expenses += 1

    conn.commit()
    conn.close()

    # Generate JSON report for the LLM to read and summarize
    import json
    report = {
        "new_donations": new_donations,
        "new_expenses": new_expenses,
        "updated_cards": updated_cards,
        "unmatched_deposits": unmatched_deposits,
        "unmatched_withdrawals": unmatched_withdrawals
    }
    with open("report.json", "w", encoding="utf-8") as f:
        json.dump(report, f, ensure_ascii=False, indent=2)

if __name__ == "__main__":
    process_statement()
