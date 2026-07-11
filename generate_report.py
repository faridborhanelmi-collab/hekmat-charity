import json
import os

with open('report.json', 'r', encoding='utf-8') as f:
    report = json.load(f)

new_donations = report.get('new_donations', 0)
new_expenses = report.get('new_expenses', 0)
updated_cards = report.get('updated_cards', 0)
unmatched_deposits = report.get('unmatched_deposits', [])
unmatched_withdrawals = report.get('unmatched_withdrawals', [])

md_content = f"""# گزارش نهایی حسابداری و بررسی تراکنش‌های فایل اکسل

این گزارش به تفصیل نتایج پردازش و ادغام فایل اکسل حساب بانکی (`Offline_Statement_Report-14012432610-2026-06-30.xlsx`) با دیتابیس فعلی وب‌سایت حکمت (`hekmat.db`) را شرح می‌دهد.

## خلاصه پردازش و ثبت اطلاعات

- **تعداد واریزی‌های (کمک‌های مردمی) جدید ثبت شده:** {new_donations} رکورد
- **تعداد برداشت‌های (هزینه‌ها/بورسیه/حقوق) جدید ثبت شده:** {new_expenses} رکورد
- **شماره کارت‌های استخراج شده و بروزرسانی شده در پروفایل خیرین:** {updated_cards} مورد

تمامی این رکوردها پس از بررسی دقیق تاریخ، مبلغ و شرح سند برای جلوگیری از ایجاد **رکوردهای تکراری** به سیستم افزوده شدند.

## تراکنش‌های نیازمند بررسی دستی (تناقضات)

با وجود الگوریتم‌های تطبیق هوشمند (بر اساس شماره کارت و نام موجود در شرح تراکنش)، برخی از رکوردها به دلیل عدم وجود اطلاعات کافی (مثلاً واریز پایا بدون نام مشخص یا سود سپرده) به هیچ شخص خاصی در سیستم متصل نشدند. این موارد در سیستم به عنوان رکوردهای ناشناس (واریزی) یا هزینه‌های عمومی (برداشتی) ثبت شده‌اند تا حسابداری کل بالانس بماند، اما بهتر است به صورت دستی بازبینی شوند.

### الف) مهم‌ترین واریزی‌های نامشخص (۵۰ مورد اول)
"""

for dep in unmatched_deposits[:50]:
    amount = f"{dep[0]:,}"
    date = dep[1]
    desc = dep[2]
    md_content += f"- **مبلغ:** {amount} ریال | **تاریخ:** {date}\n  - **شرح:** {desc}\n"

if len(unmatched_deposits) > 50:
    md_content += f"\n*... و {len(unmatched_deposits) - 50} مورد دیگر.*\n"

md_content += "\n### ب) مهم‌ترین برداشت‌های نامشخص (مرتبط شده با حساب عمومی) (۵۰ مورد اول)\n\n"

for w in unmatched_withdrawals[:50]:
    amount = f"{w[0]:,}"
    date = w[1]
    desc = w[2]
    md_content += f"- **مبلغ:** {amount} ریال | **تاریخ:** {date}\n  - **شرح:** {desc}\n"

if len(unmatched_withdrawals) > 50:
    md_content += f"\n*... و {len(unmatched_withdrawals) - 50} مورد دیگر.*\n"

md_content += """
> [!TIP]
> برای تکمیل ۱۰۰ درصدی حسابداری، لطفاً وارد پنل مدیریت وب‌سایت شده و در بخش **لیست واریزی‌ها** و **هزینه‌کردها**، رکوردهای ناشناس یا مرتبط با سیستم عمومی (General) را بررسی کرده و در صورت اطلاع از شخص مرتبط، آن را به پروفایل وی انتقال دهید.
"""

artifact_dir = "/Users/faridborhanelmi/.gemini/antigravity/brain/29c77f72-ec86-4283-9bcf-dce9af2ef647"
os.makedirs(artifact_dir, exist_ok=True)
with open(os.path.join(artifact_dir, 'accounting_final_report.md'), 'w', encoding='utf-8') as f:
    f.write(md_content)
