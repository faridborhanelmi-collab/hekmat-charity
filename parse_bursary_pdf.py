import pypdf
import sqlite3
import re

pdf_path = "/Users/faridborhanelmi/Downloads/لیست بورسیه خرداد ماه  1405.pdf"
db_path = "hekmat.db"

def clean_num(s):
    # Remove commas, spaces and non-numeric chars
    val = re.sub(r'[^\d]', '', s)
    return int(val) if val else 0

def normalize_persian(text):
    if not text:
        return ""
    # Normalize characters
    text = text.replace('ي', 'ی').replace('ك', 'ک')
    # Remove zero-width non-joiners or double spaces
    text = re.sub(r'\s+', ' ', text).strip()
    return text

def parse_bursary():
    reader = pypdf.PdfReader(pdf_path)
    conn = sqlite3.connect(db_path)
    cur = conn.cursor()
    
    all_students_db = {}
    cur.execute("SELECT id, name, surname FROM students")
    for r in cur.fetchall():
        full_name = normalize_persian(f"{r[1]} {r[2]}")
        all_students_db[full_name] = r[0]
        
    print(f"Total students in DB: {len(all_students_db)}")
    
    extracted_rows = []
    
    # Let's extract lines from all pages
    for page_idx, page in enumerate(reader.pages):
        text = page.extract_text()
        lines = text.split('\n')
        
        for line in lines:
            line = line.strip()
            if not line:
                continue
            
            # Match rows like "1ابوالفضل نیستانیدانش آموز2,000,00002,000,000"
            # We want to match:
            # - Rank number (digits) at the start
            # - Name (Farsi characters, spaces)
            # - Role (دانش آموز or دانشجو or similar)
            # - Payout amounts
            
            # Regex pattern to identify numeric amounts and name
            match = re.search(r'^(\d+)(.*?)(دانش\s*آموز|دانشجو|مورد)(.*)$', line)
            if match:
                idx = match.group(1)
                name = normalize_persian(match.group(2))
                role = match.group(3)
                rest = match.group(4).strip()
                
                # Extract numbers from the rest
                # E.g., "2,000,00002,000,000"
                # Let's find all numeric strings (including commas)
                nums = re.findall(r'[\d,]+', rest)
                
                if nums:
                    # Let's reconstruct or extract amounts
                    # Often the numbers are joined or split
                    raw_numbers = "".join(nums).replace(",", "")
                    # We expect: [bursary_amount, laptop_deduction, final_payout]
                    # Let's parse them by splitting or analyzing
                    # E.g., "2,000,000" and "0" and "2,000,000"
                    # If raw_numbers is "200000002000000" (bursary=2000000, deduction=0, final=2000000)
                    # Let's write a parser based on the original line parts
                    pass
                
                # Let's parse numbers from raw line more safely:
                # E.g., splitting by space or by checking commas
                # Let's find numbers like 2,000,000
                numbers = re.findall(r'[\d,]+', line)
                if len(numbers) >= 3:
                    # First number is usually row index if it's separate, or index is part of the name
                    # Let's print the match for debugging
                    extracted_rows.append((line, name, numbers))
            else:
                # If it doesn't match the regex but has numbers
                numbers = re.findall(r'[\d,]+', line)
                if len(numbers) >= 2 and any(char.isalpha() for char in line):
                    # Potential student line
                    extracted_rows.append((line, "Unknown", numbers))

    print(f"Extracted {len(extracted_rows)} potential rows.")
    
    # Print first 15 parsed rows for verification
    for i, (line, name, numbers) in enumerate(extracted_rows[:15]):
        print(f"Row {i+1}:")
        print(f"  Line: {line}")
        print(f"  Name: {name}")
        print(f"  Nums: {numbers}")
        
    conn.close()

if __name__ == "__main__":
    parse_bursary()
