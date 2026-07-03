import pypdf
import os

files = {
    "Statement": "/Users/faridborhanelmi/Downloads/Offline_Statement_Report-14012432610-2026-06-22.pdf",
    "Bursary": "/Users/faridborhanelmi/Downloads/لیست بورسیه خرداد ماه  1405.pdf",
    "Expenses": "/Users/faridborhanelmi/Downloads/هزینه های داخلی دفتر.pdf"
}

for name, path in files.items():
    print(f"\n=================== {name} ({os.path.basename(path)}) ===================")
    if not os.path.exists(path):
        print("File does not exist!")
        continue
    
    reader = pypdf.PdfReader(path)
    print(f"Total pages: {len(reader.pages)}")
    
    # Print first page text
    text = reader.pages[0].extract_text()
    print("--- First 800 chars of page 1 text ---")
    print(text[:800])
    
    if len(reader.pages) > 1:
        print("\n--- Page 2 preview ---")
        print(reader.pages[1].extract_text()[:400])
