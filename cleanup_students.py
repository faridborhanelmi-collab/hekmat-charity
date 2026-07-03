import sqlite3
import re

def normalize_name(s):
    if not s: return ''
    # Strip common foundation-level prefixes and dates
    # Match strings like "حکمت" or "بهمن 1403" or "حکمت بهمن"
    s = re.sub(r'حکمت|بهمن|۱۴۰۳|1403|اسفند|مهر|آبان|دی|آذر|شهریور|اردیبهشت', '', s)
    # Standardize Persian/Arabic characters
    s = s.replace('ی', 'ی').replace('ي', 'ی').replace('ئ', 'ی').replace('إ', 'ا').replace('أ', 'ا').replace('آ', 'ا')
    s = s.replace('ک', 'ک').replace('ك', 'ک')
    s = s.replace('ة', 'ه')
    # Remove non-alpha chars and spaces
    s = re.sub(r'[^\w]', '', s)
    return s

def cleanup():
    conn = sqlite3.connect('hekmat.db')
    cursor = conn.cursor()

    # 1. Load all students
    cursor.execute("SELECT id, name, surname, code FROM students")
    all_students = cursor.fetchall()
    
    # Identify "original" vs "newly created" students
    # Heuristic: IDs < 900 are likely original, or check if name contains 'حکمت'
    original_students = []
    prefixed_students = []

    for sid, name, surname, code in all_students:
        full_raw = f"{name} {surname}"
        if "حکمت" in full_raw or "بهمن" in full_raw or "1403" in full_raw:
             prefixed_students.append((sid, name, surname, code))
        else:
             original_students.append((sid, name, surname, code))

    # Map for original students for deduplication
    original_map = {}
    for sid, name, surname, code in original_students:
        norm = normalize_name(f"{name}{surname}")
        if norm:
            original_map[norm] = sid

    merged_count = 0
    cleaned_count = 0

    for sid, name, surname, code in prefixed_students:
        # 1. Clean the name
        clean_name = re.sub(r'حکمت|بهمن|۱۴۰۳|1403', '', name).strip()
        clean_surname = re.sub(r'حکمت|بهمن|۱۴۰۳|1403', '', surname).strip()
        
        norm_clean = normalize_name(f"{clean_name}{clean_surname}")
        
        # 2. Check for duplicate in originals
        if norm_clean in original_map:
            target_sid = original_map[norm_clean]
            # Move all expenses from this side to original
            cursor.execute("UPDATE expenses SET student_id = ? WHERE student_id = ?", (target_sid, sid))
            # Delete the duplicate student
            cursor.execute("DELETE FROM students WHERE id = ?", (sid,))
            merged_count += 1
        else:
            # No original match, just clean the current record's name
            cursor.execute("UPDATE students SET name = ?, surname = ? WHERE id = ?", (clean_name, clean_surname, sid))
            cleaned_count += 1
            # Add this to original map now so other duplicates can match it
            original_map[norm_clean] = sid

    conn.commit()
    print(f"Cleanup Complete:")
    print(f"- Merged and deleted {merged_count} duplicates.")
    print(f"- Cleaned names for {cleaned_count} students.")
    conn.close()

if __name__ == "__main__":
    cleanup()
