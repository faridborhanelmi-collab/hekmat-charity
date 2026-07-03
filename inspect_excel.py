import pandas as pd
import json

file_path = 'finalmali2.xlsx'
df = pd.read_excel(file_path, sheet_name='لیست واریزی ماهیانه')
print("Columns of list varizi mahiane:")
for i, col in enumerate(df.columns):
    print(f"{i}: {col}")

print("\nFirst 5 rows:")
print(df.head(5).to_csv(index=False))

df_donors = pd.read_excel(file_path, sheet_name='لیست واریزی کل خیرین')
print("Columns of Kol khayerin:")
for i, col in enumerate(df_donors.columns):
    print(f"{i}: {col}")
