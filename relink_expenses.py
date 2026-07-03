import sqlite3
import re

def normalize_name(s):
    if not s: return ''
    # Standardize Persian/Arabic characters
    s = s.replace('ی', 'ی').replace('ي', 'ی').replace('ئ', 'ی').replace('إ', 'ا').replace('أ', 'ا').replace('آ', 'ا')
    s = s.replace('ک', 'ک').replace('ك', 'ک')
    s = s.replace('ة', 'ه')
    # Remove non-alpha chars and spaces
    s = re.sub(r'[^\w]', '', s)
    return s

def relink():
    conn = sqlite3.connect('hekmat.db')
    cursor = conn.cursor()

    # 1. Load students
    cursor.execute("SELECT id, name, surname, code FROM students")
    students = cursor.fetchall()
    
    student_map = {}
    last_code = 0
    for sid, name, surname, code in students:
        full_name = normalize_name(f"{name}{surname}")
        if full_name:
            student_map[full_name] = sid
        try:
            if code and code.isdigit():
                last_code = max(last_code, int(code))
        except:
            pass

    # 2. Find suspect expenses in GENERAL (ID 711)
    # We look for descriptions containing "بورسیه"
    cursor.execute("SELECT id, description FROM expenses WHERE student_id = 711 AND (description LIKE '%بورسیه%' OR description LIKE '%بورس%')")
    expenses = cursor.fetchall()

    updates = []
    new_students_to_create = {} # name -> sid (temp)

    for eid, desc in expenses:
        # Improved Regex to extract student name
        # Typical: "هزینه بورسیه شهریور ماه زهرا گوهری"
        match = re.search(r'(?:بورسیه|بورس)\s+(?:.*?)\s+(?:ماه|سال|مدرسه|کتاب|کلاس)\s+(.*)', desc)
        if not match:
             match = re.search(r'(?:بورسیه|بورس)\s+(.*)', desc)
        
        if match:
            extracted_name = match.group(1).strip()
            # Clean common trailing notes
            extracted_name = re.sub(r'\(.*?\)', '', extracted_name).strip()
            extracted_name = extracted_name.split(' - ')[0].split('(')[0].strip()
            
            norm_name = normalize_name(extracted_name)
            if not norm_name or len(norm_name) < 4: continue # skip garbage

            if norm_name in student_map:
                target_sid = student_map[norm_name]
                updates.append((target_sid, 3, eid))
            else:
                # If not found, queue for creation
                if norm_name not in new_students_to_create:
                    # Check if it's already in the process of being created
                    last_code += 1
                    new_code = str(last_code)
                    
                    # Heuristic: split name into parts
                    parts = extracted_name.split()
                    fname = parts[0] if parts else extracted_name
                    lname = " ".join(parts[1:]) if len(parts) > 1 else ""
                    
                    cursor.execute("INSERT INTO students (name, surname, code, status) VALUES (?, ?, ?, 'active')", (fname, lname, new_code, ))
                    new_sid = cursor.lastrowid
                    student_map[norm_name] = new_sid
                    new_students_to_create[norm_name] = new_sid
                
                target_sid = student_map[norm_name]
                updates.append((target_sid, 3, eid))

    # 3. Apply updates
    print(f"Applying {len(updates)} record re-attributions...")
    for sid, catid, eid in updates:
        cursor.execute("UPDATE expenses SET student_id = ?, category_id = ? WHERE id = ?", (sid, catid, eid))
    
    conn.commit()
    print(f"Successfully created {len(new_students_to_create)} new student profiles.")
    conn.close()

if __name__ == "__main__":
    relink()
