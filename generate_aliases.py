import sqlite3

db_path = "hekmat.db"
conn = sqlite3.connect(db_path)
cursor = conn.cursor()

# Get all students without alias
cursor.execute("SELECT id FROM students WHERE alias_name IS NULL OR alias_name = '' ORDER BY id ASC")
students = cursor.fetchall()

counter = 1
for (std_id,) in students:
    alias = f"حکمت‌جو {counter}"
    cursor.execute("UPDATE students SET alias_name = ? WHERE id = ?", (alias, std_id))
    counter += 1

conn.commit()
conn.close()

print(f"Generated {counter-1} aliases.")
