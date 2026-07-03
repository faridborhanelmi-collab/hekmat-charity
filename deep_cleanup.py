import sqlite3
import re

def normalize_name(s):
    if not s: return ''
    # Strip all possible months and years and foundation keywords
    s = re.sub(r'حکمت|بنیاد|هزینه|پرداختی|بهمن|اسفند|فروردین|اردیبهشت|خرداد|تیر|مرداد|شهریور|مهر|آبان|آذر|دی|۱۴۰۱|1401|۱۴۰۲|1402|۱۴۰۳|1403|۱۴۰۴|1404|بورس', '', s)
    # Standardize Persian/Arabic characters
    s = s.replace('ی', 'ی').replace('ي', 'ی').replace('ئ', 'ی').replace('إ', 'ا').replace('أ', 'ا').replace('آ', 'ا')
    s = s.replace('ک', 'ک').replace('ك', 'ک')
    s = s.replace('ة', 'ه')
    s = re.sub(r'[^\w]', '', s)
    return s

def deep_cleanup():
    conn = sqlite3.connect('hekmat.db')
    cursor = conn.cursor()

    # 1. Load all students
    cursor.execute("SELECT id, name, surname, code FROM students")
    all_students = cursor.fetchall()
    
    # 2. Map normalized names to a candidate profile
    # Preference: Profile with lowest ID or manual mention
    student_pool = {} # norm_name -> list of IDs
    for sid, name, surname, code in all_students:
        full_name = f"{name}{surname}"
        norm = normalize_name(full_name)
        if not norm: continue
        if norm not in student_pool: student_pool[norm] = []
        student_pool[norm].append(sid)

    # 3. Handle Special Case: Maede Vafaei (Force to 158)
    maede_norm = normalize_name("مائده وفای")
    # Identify Maede's IDs
    cursor.execute("SELECT id FROM students WHERE name LIKE '%مایده%' OR name LIKE '%مائده%' OR surname LIKE '%وفای%'")
    maede_ids = [row[0] for row in cursor.fetchall()]
    
    # If Maede exists, pick one as master, update its code, merge others
    if maede_ids:
        master_maede = min(maede_ids)
        cursor.execute("UPDATE students SET name='مائده', surname='وفایی', code='158' WHERE id=?", (master_maede,))
        for sid in maede_ids:
            if sid != master_maede:
                cursor.execute("UPDATE expenses SET student_id = ? WHERE student_id = ?", (master_maede, sid))
                cursor.execute("DELETE FROM students WHERE id = ?", (sid,))
        print(f"Force-merged Maede Vafaei (IDs {maede_ids}) into profile ID {master_maede} with code 158.")

    # 4. Global Merge for others
    merged_count = 0
    for norm, ids in student_pool.items():
        if len(ids) > 1:
             # Skip if already handled (multi-match for Maede)
             ids = [i for i in ids if cursor.execute("SELECT id FROM students WHERE id=?", (i,)).fetchone()]
             if len(ids) <= 1: continue
             
             master_id = min(ids)
             for sid in ids:
                 if sid != master_id:
                     cursor.execute("UPDATE expenses SET student_id = ? WHERE student_id = ?", (master_id, sid))
                     cursor.execute("DELETE FROM students WHERE id = ?", (sid,))
                     merged_count += 1

    conn.commit()
    print(f"Deep Audit Complete:")
    print(f"- Merged {merged_count} remaining duplicate profiles.")
    
    cursor.execute("SELECT COUNT(*) FROM students")
    final_count = cursor.fetchone()[0]
    print(f"- Final total unique students: {final_count}")
    conn.close()

if __name__ == "__main__":
    deep_cleanup()
