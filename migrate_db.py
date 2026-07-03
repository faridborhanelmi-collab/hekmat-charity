import sqlite3

def migrate():
    db_path = 'hekmat.db'
    print(f"Connecting to database: {db_path}")
    conn = sqlite3.connect(db_path)
    cur = conn.cursor()
    
    # 1. Add new columns to students table if they don't exist
    cur.execute("PRAGMA table_info(students)")
    columns = [col[1] for col in cur.fetchall()]
    
    new_cols = {
        'avatar_url': 'TEXT',
        'alias_name': 'TEXT',
        'talents': 'TEXT',
        'dreams': 'TEXT'
    }
    
    for col_name, col_type in new_cols.items():
        if col_name not in columns:
            print(f"Adding column '{col_name}' to 'students' table...")
            cur.execute(f"ALTER TABLE students ADD COLUMN {col_name} {col_type}")
        else:
            print(f"Column '{col_name}' already exists in 'students' table.")
            
    # 2. Create sponsorships table
    print("Creating 'sponsorships' table...")
    cur.execute("""
    CREATE TABLE IF NOT EXISTS sponsorships (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        donor_id INTEGER NOT NULL,
        student_id INTEGER NOT NULL,
        shares_count INTEGER DEFAULT 1,
        start_date TEXT,
        status TEXT DEFAULT 'active',
        FOREIGN KEY (donor_id) REFERENCES donors(id),
        FOREIGN KEY (student_id) REFERENCES students(id)
    )
    """)
    
    # 3. Create sponsorship_messages table
    print("Creating 'sponsorship_messages' table...")
    cur.execute("""
    CREATE TABLE IF NOT EXISTS sponsorship_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sponsorship_id INTEGER NOT NULL,
        sender_type TEXT, -- 'donor' or 'student'
        message_text TEXT,
        attachment_url TEXT,
        status TEXT DEFAULT 'pending', -- pending / approved / rejected
        created_at TEXT,
        FOREIGN KEY (sponsorship_id) REFERENCES sponsorships(id)
    )
    """)
    
    conn.commit()
    conn.close()
    print("Database migration completed successfully!")

if __name__ == "__main__":
    migrate()
