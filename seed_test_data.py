import sqlite3

def seed():
    conn = sqlite3.connect('hekmat.db')
    cur = conn.cursor()
    
    # Check if test student already exists
    cur.execute("SELECT id FROM students WHERE national_id = '1234567890'")
    student = cur.fetchone()
    if not student:
        print("Inserting test student 'علی احمدی'...")
        cur.execute("""
        INSERT INTO students (code, name, surname, national_id, phone, grade, field_of_study, status)
        VALUES ('ST-999', 'علی', 'احمدی', '1234567890', '09123456789', 'دهم', 'ریاضی', 'active')
        """)
        student_id = cur.lastrowid
    else:
        student_id = student[0]
        print(f"Test student already exists with ID: {student_id}")
        
    # Check if test donor already exists
    cur.execute("SELECT id FROM donors WHERE phone = '09129876543'")
    donor = cur.fetchone()
    if not donor:
        print("Inserting test donor 'رضا رضایی'...")
        cur.execute("""
        INSERT INTO donors (name, surname, phone, join_date, total_donated)
        VALUES ('رضا', 'رضایی', '09129876543', '1404/01/01', 50000000)
        """)
        donor_id = cur.lastrowid
    else:
        donor_id = donor[0]
        print(f"Test donor already exists with ID: {donor_id}")
        
    conn.commit()
    conn.close()
    print("Database seeding completed!")

if __name__ == "__main__":
    seed()
