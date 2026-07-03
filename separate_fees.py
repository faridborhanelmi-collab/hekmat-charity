import sqlite3
import datetime

db_path = "hekmat.db"
conn = sqlite3.connect(db_path)
cursor = conn.cursor()

# Check for "کارمزد پرداختی بانک" category, create if not exists
cursor.execute("SELECT id FROM expense_categories WHERE name LIKE '%کارمزد%'")
cat = cursor.fetchone()
if not cat:
    cursor.execute("INSERT INTO expense_categories (name) VALUES ('کارمزد پرداختی بانک')")
    cat_id = cursor.lastrowid
else:
    cat_id = cat[0]

# Find all expenses where it's likely a bank fee is included.
# Bursary amounts are usually multiples of 100,000 or 50,000. 
# So amount % 10000 > 0 is a very safe bet for a fee (like 360, 450, 500, 1200).
cursor.execute("SELECT id, amount, description, expense_date FROM expenses WHERE category_id = 3 AND amount % 10000 != 0")
rows = cursor.fetchall()

total_fees = 0
updates = 0
fee_records_to_insert = []

for row in rows:
    exp_id, amount, desc, exp_date = row
    
    fee = amount % 10000
    base_amount = amount - fee
    
    # Update the student's expense to base_amount
    cursor.execute("UPDATE expenses SET amount = ? WHERE id = ?", (base_amount, exp_id))
    
    total_fees += fee
    updates += 1
    
    # Add fee record (we'll aggregate them or insert individually. Individually is better for date tracking)
    fee_records_to_insert.append((cat_id, fee, f"کارمزد تراکنش بانکی (مربوط به {desc})", exp_date))

# Insert fee records
for record in fee_records_to_insert:
    cursor.execute("""
        INSERT INTO expenses (category_id, amount, description, expense_date, student_id)
        VALUES (?, ?, ?, ?, NULL)
    """, record)

conn.commit()
conn.close()

print(f"Updated {updates} bursary payments.")
print(f"Total fees separated: {total_fees}")
