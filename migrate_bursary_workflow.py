import sqlite3
import os

def migrate():
    db_path = 'hekmat.db'
    if not os.path.exists(db_path):
        db_path = '../hekmat.db'
    if not os.path.exists(db_path):
        db_path = 'admin/../hekmat.db'
    if not os.path.exists(db_path):
        # Fallback
        db_path = '/Users/faridborhanelmi/Desktop/Hekmat-Charity/hekmat.db'
        
    print(f"Connecting to database: {db_path}")
    conn = sqlite3.connect(db_path)
    cur = conn.cursor()
    
    # 1. Add new columns to students table if they don't exist
    cur.execute("PRAGMA table_info(students)")
    columns = [col[1] for col in cur.fetchall()]
    
    new_cols = {
        'bursary_eligible': 'INTEGER DEFAULT 1',
        'base_bursary': 'INTEGER DEFAULT 20000000',
        'computer_installment': 'INTEGER DEFAULT 0',
        'loan_installment': 'INTEGER DEFAULT 0',
        'other_deductions': 'INTEGER DEFAULT 0',
        'deductions_desc': 'TEXT DEFAULT \'\''
    }
    
    for col_name, col_type in new_cols.items():
        if col_name not in columns:
            print(f"Adding column '{col_name}' to 'students' table...")
            cur.execute(f"ALTER TABLE students ADD COLUMN {col_name} {col_type}")
        else:
            print(f"Column '{col_name}' already exists in 'students' table.")
            
    # 2. Create monthly_bursary_lists table
    print("Creating 'monthly_bursary_lists' table...")
    cur.execute("""
    CREATE TABLE IF NOT EXISTS monthly_bursary_lists (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        year TEXT NOT NULL,
        month TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'draft',
        created_at TEXT NOT NULL,
        admin_approved_at TEXT,
        signed_bahraman INTEGER DEFAULT 0,
        signed_sanobari INTEGER DEFAULT 0,
        signed_bahraman_at TEXT,
        signed_sanobari_at TEXT
    )
    """)
    
    # 3. Create monthly_bursary_items table
    print("Creating 'monthly_bursary_items' table...")
    cur.execute("""
    CREATE TABLE IF NOT EXISTS monthly_bursary_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        list_id INTEGER NOT NULL,
        student_id INTEGER NOT NULL,
        student_name TEXT NOT NULL,
        account_number TEXT,
        base_amount INTEGER NOT NULL,
        computer_installment INTEGER DEFAULT 0,
        loan_installment INTEGER DEFAULT 0,
        other_deductions INTEGER DEFAULT 0,
        deductions_desc TEXT,
        final_amount INTEGER NOT NULL,
        FOREIGN KEY(list_id) REFERENCES monthly_bursary_lists(id),
        FOREIGN KEY(student_id) REFERENCES students(id)
    )
    """)
    
    conn.commit()
    conn.close()
    print("Database migration completed successfully!")

if __name__ == "__main__":
    migrate()
