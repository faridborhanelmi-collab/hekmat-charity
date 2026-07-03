#!/usr/bin/env python3
import sys
import os
import re
import json
import sqlite3
import unicodedata
from pypdf import PdfReader

def clean_farsi_text(text):
    if not text:
        return ""
    text = unicodedata.normalize('NFKC', text)
    replacements = {
        'ي': 'ی',
        'ك': 'ک',
        'ة': 'ه',
        '٠': '۰', '١': '۱', '٢': '۲', '٣': '۳', '٤': '۴', '٥': '۵', '٦': '۶', '٧': '۷', '٨': '۸', '٩': '۹'
    }
    for k, v in replacements.items():
        text = text.replace(k, v)
    text = text.replace('\u200c', ' ')
    return text.strip()

def parse_pdf(pdf_path, db_path):
    if not os.path.exists(pdf_path):
        return {"error": f"PDF file not found: {pdf_path}"}
    if not os.path.exists(db_path):
        return {"error": f"Database file not found: {db_path}"}

    # Connect to database and fetch donors
    conn = sqlite3.connect(db_path)
    cursor = conn.cursor()
    cursor.execute("SELECT id, name, surname, card_number FROM donors")
    donors = []
    for row in cursor.fetchall():
        donors.append({
            "id": row[0],
            "name": clean_farsi_text(row[1]),
            "surname": clean_farsi_text(row[2]),
            "card_number": row[3].strip() if row[3] else None
        })
    conn.close()

    # Read PDF
    try:
        reader = PdfReader(pdf_path)
    except Exception as e:
        return {"error": f"Failed to read PDF: {str(e)}"}

    raw_transactions = []
    initial_balance = None
    
    # Process page by page
    for page_idx, page in enumerate(reader.pages):
        text = page.extract_text()
        if not text:
            continue
            
        cleaned_text = clean_farsi_text(text)
        
        # Try to find initial balance on page 1
        if page_idx == 0 and initial_balance is None:
            init_match = re.search(r'([\d,]+)\s+موجودی قابل برداشت ابتدای دوره', cleaned_text)
            if init_match:
                initial_balance = int(init_match.group(1).replace(',', ''))

        lines = cleaned_text.split('\n')
        current_tx = None
        
        for line in lines:
            line = line.strip()
            if not line:
                continue
                
            # Regex for transaction line:
            # [DocNo] [Channel/Desc...] [Balance] [Amount] [Time] [Date]
            tx_match = re.search(r'^(\d+)\s+(.*?)\s+([\d,]+)\s+([\d,]+)\s+(\d{2}:\d{2}:\d{2})\s+(\d{4}/\d{2}/\d{2})$', line)
            
            if tx_match:
                # If we have an active transaction, save it
                if current_tx:
                    raw_transactions.append(current_tx)
                
                doc_no = tx_match.group(1)
                channel = tx_match.group(2)
                balance = int(tx_match.group(3).replace(',', ''))
                amount = int(tx_match.group(4).replace(',', ''))
                time_str = tx_match.group(5)
                date_str = tx_match.group(6)
                
                current_tx = {
                    "receipt_no": doc_no,
                    "channel": channel,
                    "balance": balance,
                    "amount": amount,
                    "time": time_str,
                    "date": date_str,
                    "description_lines": []
                }
            else:
                # Append description to the active transaction
                if current_tx:
                    # Ignore page numbers and footer totals
                    if not any(k in line for k in ["مجموع کل واریز", "مجموع کل برداشت", "صفحه نزولی مرتب سازی"]):
                        current_tx["description_lines"].append(line)
                        
        if current_tx:
            raw_transactions.append(current_tx)
            current_tx = None

    if not raw_transactions:
        return {"success": True, "transactions": []}

    # Reorder chronologically (oldest first)
    # The statement lists them newest-first, so we reverse it.
    raw_transactions.reverse()
    
    # Compute signs based on balance differences
    transactions = []
    prev_balance = initial_balance
    
    for i, tx in enumerate(raw_transactions):
        balance = tx["balance"]
        amount = tx["amount"]
        
        # Determine sign
        is_deposit = True
        desc_full = " ".join(tx["description_lines"])
        
        # If we have a previous balance, use the difference
        if prev_balance is not None:
            diff = balance - prev_balance
            if diff < 0:
                is_deposit = False
        else:
            # Fallback for the first transaction if B_init was not found
            # Check keywords in the description
            withdrawal_keywords = ['برداشت', 'کارمزد', 'خرید شارژ', 'خروجی پایا', 'انتقال به']
            if any(kw in desc_full or kw in tx["channel"] for kw in withdrawal_keywords):
                is_deposit = False
                
        prev_balance = balance
        
        # We only care about deposits (donations)
        if not is_deposit:
            continue
            
        # Try to match a donor
        matched_donor = None
        match_method = None
        
        # 1. Match by card number if available
        # Find any 16-digit card or 4-digit card number in description
        card_match = ""
        c16_match = re.search(r'(\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b)', desc_full)
        if c16_match:
            card_match = c16_match.group(1).replace('-', '').replace(' ', '')
        else:
            c4_match = re.search(r'(?:\*|\b)(\d{4})\b', desc_full)
            if c4_match:
                card_match = c4_match.group(1)
                
        if card_match:
            for donor in donors:
                if donor["card_number"] and len(donor["card_number"]) >= 4:
                    if donor["card_number"] == card_match or donor["card_number"][-4:] == card_match:
                        matched_donor = donor
                        match_method = "card"
                        break
                        
        # 2. Match by name and surname
        if not matched_donor:
            for donor in donors:
                if donor["name"] and donor["surname"] and len(donor["name"]) > 1 and len(donor["surname"]) > 1:
                    if donor["name"] in desc_full and donor["surname"] in desc_full:
                        matched_donor = donor
                        match_method = "name"
                        break
                        
        transactions.append({
            "date": tx["date"],
            "amount": amount,
            "description": f"{tx['channel']} | {desc_full}".strip(" | "),
            "receipt_no": tx["receipt_no"],
            "donor_id": matched_donor["id"] if matched_donor else None,
            "donor_name": f"{matched_donor['name']} {matched_donor['surname']}" if matched_donor else None,
            "match_method": match_method
        })
        
    return {"success": True, "transactions": transactions}

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(json.dumps({"error": "Usage: parse_statement.py <pdf_path> <db_path>"}))
        sys.exit(1)
        
    pdf_path = sys.argv[1]
    db_path = sys.argv[2]
    
    result = parse_pdf(pdf_path, db_path)
    print(json.dumps(result, ensure_ascii=False))
