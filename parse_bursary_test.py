import pypdf
import re

pdf_path = "/Users/faridborhanelmi/Downloads/لیست بورسیه خرداد ماه  1405.pdf"

def parse_digits(digits_str):
    # E.g. "200000002000000" or "18500007500001100000"
    if len(digits_str) < 7:
        return 0, 0, 0
    
    bursary = int(digits_str[:7])
    rest = digits_str[7:]
    
    if not rest:
        return bursary, 0, bursary
    
    if rest.startswith('0'):
        deduction = 0
        final = int(rest[1:]) if rest[1:] else bursary
    else:
        # Check length of rest
        if len(rest) == 13: # e.g. 750000 + 1100000
            deduction = int(rest[:6])
            final = int(rest[6:])
        elif len(rest) == 14: # e.g. 1000000 + 1000000
            deduction = int(rest[:7])
            final = int(rest[7:])
        else:
            # Fallback
            deduction = 0
            final = int(rest) if rest else bursary
            
    return bursary, deduction, final

def test_parse():
    reader = pypdf.PdfReader(pdf_path)
    total_records = 0
    
    for page_idx, page in enumerate(reader.pages):
        print(f"\n--- Page {page_idx + 1} ---")
        text = page.extract_text()
        lines = text.split('\n')
        
        for line in lines:
            line = line.strip()
            if not line:
                continue
            
            # Identify line starting with a number and containing a role/text
            # Let's match the number at the start
            match = re.match(r'^(\d+)(.*?)(دانش\s*آموز|دانشجو|مورد|پشت\s*کنکوری|دیپلم|ابتدایی|راهنمایی|پیش\s*دانشگاهی)(.*)$', line)
            if match:
                idx = match.group(1)
                name = match.group(2).strip()
                role = match.group(3).strip()
                rest = match.group(4).strip()
                
                # Extract only digits from rest
                digits = re.sub(r'[^\d]', '', rest)
                bursary, deduction, final = parse_digits(digits)
                
                print(f"[{idx}] Name: {name:20} | Role: {role:12} | Bursary: {bursary:9,} | Deduct: {deduction:9,} | Final: {final:9,}")
                total_records += 1
            else:
                # Let's check if it starts with digit but has a different role or text
                # (e.g. some students might have other descriptions)
                match_fallback = re.match(r'^(\d+)(.*?)([\d,]{7,}.*)$', line)
                if match_fallback:
                    idx = match_fallback.group(1)
                    name = match_fallback.group(2).strip()
                    rest = match_fallback.group(3).strip()
                    digits = re.sub(r'[^\d]', '', rest)
                    bursary, deduction, final = parse_digits(digits)
                    # Skip if name is empty or just header
                    if name and not any(h in name for h in ["نام و نام خانوادگی", "واریزی"]):
                        print(f"[*] Name: {name:20} | Role: Unknown      | Bursary: {bursary:9,} | Deduct: {deduction:9,} | Final: {final:9,}")
                        total_records += 1

    print(f"\nTotal records parsed: {total_records}")

if __name__ == "__main__":
    test_parse()
