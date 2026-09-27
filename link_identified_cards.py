#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import sqlite3
import pandas as pd
import unicodedata
import re

DB_PATH = 'hekmat.db'

def clean_farsi(text):
    if not text or pd.isnull(text): return ''
    text = str(text)
    text = unicodedata.normalize('NFKC', text)
    repl = {'ي': 'ی', 'ك': 'ک', 'ة': 'ه', '٠':'۰','١':'۱','٢':'۲','٣':'۳','٤':'۴','٥':'۵','٦':'۶','٧':'۷','٨':'۸','٩':'۹'}
    for k, v in repl.items():
        text = text.replace(k, v)
    return text.replace('\u200c', ' ').strip()

def normalize_name(s):
    s = clean_farsi(s)
    return re.sub(r'[^\w]', '', s)

# Mapped cards to canonical donor names based on historical verification
CARD_MAP = {
    # Ehsan Khadivi
    '6104337886949674': 'احسان خدیوی',
    '6037991947370221': 'احسان خدیوی',
    
    # Mojtaba Hassanabadi
    '6037691526531223': 'مجتبی حسن آبادی',
    
    # Ehsan Mohajeri
    '6037697561226116': 'احسان مهاجری',
    '6396071211003454': 'احسان مهاجری',
    
    # Somayeh Taherabadi
    '6221061208001216': 'سمیه طاهرآبادی',
    '6063731244413051': 'سمیه طاهرآبادی',
    
    # Farzad Farshid
    '5022291304273111': 'فرزاد فرشید',
    '5022291067156743': 'فرزاد فرشید',
    '6362141810901620': 'فرزاد فرشید',
    
    # Hassan Golestani
    '5022291308274941': 'حسن گلستانی',
    
    # Hossein Zarangian
    '5057851003297345': 'حسین زرنگیان',
    '5859471010724858': 'حسین زرنگیان',
    
    # Mohammad Barjasteh
    '6037697598049572': 'محمد برجسته',
    '5894631134330836': 'محمد برجسته',
    '6037691652873696': 'محمد برجسته',
    
    # Farhang Bohloul
    '6221061079430403': 'فرهنگ بهلول',
    '6219861021865570': 'فرهنگ بهلول',
    
    # Ali Akbar Akbarzadeh
    '5894631830633582': 'علی اکبر اکبرزاده',
    '6037991947268151': 'علی اکبر اکبرزاده',
    
    # Mohammad Rokn
    '6037997136517738': 'محمد رکن',
    '6104337604787133': 'محمد رکن',
    
    # Omid Emadzadeh
    '6221061225972373': 'امید عمادزاده',
    
    # Firoozeh Farshid
    '5894631233399880': 'فیروزه فرشید',
    '5022291055113961': 'فیروزه فرشید',
    '6037697574168529': 'فیروزه فرشید',
    
    # Mohammad Mehdi Akbarzadeh
    '6274121193079712': 'محمدمهدی اکبرزاده',
    '5894631593018294': 'محمدمهدی اکبرزاده',
    
    # Omid Aminian
    '6104337956006173': 'امید امینیان',
    
    # Reza Ehtesham
    '5029381036136646': 'رضا احتشام',
    
    # Hamid Ghorbani
    '6104338707084832': 'حمید قربانی',
    
    # Amir Hossein Noroozi
    '6037998284795894': 'امیرحسین نوروزی',
    
    # Reza Hosseini
    '6063731028949882': 'رضا حسینی',
    
    # Ahmadreza Bakhtiari
    '6104337592048191': 'احمدرضا بختیاری',
    '5029381043451491': 'احمدرضا بختیاری',
    
    # Fatemeh Farshid
    '6037991926096490': 'فاطمه فرشید',
    
    # Davood Bamiri
    '6104337871897409': 'داوود بامیری',
    
    # Hamid Sahebkar
    '6219861942053173': 'حمید صاحبکار',
    
    # Masoud Mehdizadeh
    '6362141808896493': 'مسعود مهدیزاده',
    
    # Ali Asayesh
    '5859831045174744': 'علی آسایش',
    
    # Mahnaz Sadat Robat
    '6037691597612225': 'مهناز سادات رباط',
    
    # Elham Safdari
    '6037701431563847': 'الهام صفدری',
    
    # Tina Yaghoobi
    '6219861925066325': 'تینا یعقوبی',
    
    # Mahsa Ghorbanzadeh
    '5859831100566818': 'مهسا قربانزاده',
    
    # Marzieh Vaezi
    '6037991930237312': 'مرضیه واعظی',
    
    # Faramarz Soleimani
    '6037997277895331': 'فرامرز سلیمانی',
    
    # Payam Shah Rezaei
    '5022291511398297': 'پیام شاه رضائی',
    
    # Mehdi Dadkhah
    '6221061219707819': 'مهدی دادخواه',
    
    # Siamak Ebrahimi
    '5859831102479226': 'سیامک ابراهیمی',
    
    # Bibi Narjes Sadrossadat
    '6037691667470785': 'بی بی نرجس صدرالسادات',
    
    # Vajiheh Mohammad Doust
    '5894631598424927': 'وجیهه محمد دوست',
    
    # Zohreh Narimisa
    '6104338799384611': 'زهره نری میسا',
    
    # Farid Borhan Elmi
    '6104337649628052': 'فرید برهان علمی',
    
    # Masoud Tavakoli
    '6104338946796501': 'مسعود توکلی',
    
    # Reza Daneshmand
    '6219861986887288': 'رضا دانشمند',
    
    # Azadeh Arian
    '6219861908259798': 'آزاده آرین',
    
    # Hamid Gharaei
    '6037991928572944': 'حمید قرایی',
    
    # Behzad Amiri Kashani
    '5859831026431972': 'بهزاد امیری کاشانی',
    '5859831026733211': 'بهزاد امیری کاشانی',
    
    # Zahra Noori Jamshidi
    '6219861944091387': 'زهرا نوری جمشیدی',
    
    # Mehrdad Bagheri
    '5894631232257345': 'مهرداد باقری',
    
    # Majid Farahmand
    '5894631563315035': 'مجید فرحمند',
    
    # Nahal Nafar
    '6104337614201166': 'نهال نفر',
    
    # Taj Khatoon Rag
    '6219861937222502': 'تاج خاتون رگ'
}

def run_relink():
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    # 1. Load canonical donors
    cursor.execute("SELECT id, name, surname, card_number FROM donors WHERE name NOT LIKE '%ناشناس%'")
    canonical_donors = cursor.fetchall()
    
    donor_norm_map = {}
    for did, fn, ln, card in canonical_donors:
        fn_c = clean_farsi(fn)
        ln_c = clean_farsi(ln)
        norm1 = normalize_name(f"{fn_c}{ln_c}")
        norm2 = normalize_name(f"{ln_c}{fn_c}")
        if norm1: donor_norm_map[norm1] = did
        if norm2: donor_norm_map[norm2] = did

    total_relinked_donations = 0
    cleaned_unknown_donors = 0
    updated_cards = 0

    for card, canonical_name in CARD_MAP.items():
        norm_name = normalize_name(canonical_name)
        canonical_id = donor_norm_map.get(norm_name)
        
        if not canonical_id:
            # Try fuzzy or word match
            parts = canonical_name.split()
            if len(parts) >= 2:
                fn_p = parts[0]
                ln_p = " ".join(parts[1:])
                cursor.execute("SELECT id FROM donors WHERE name LIKE ? AND surname LIKE ?", (f"%{fn_p}%", f"%{ln_p}%"))
                res = cursor.fetchone()
                if res:
                    canonical_id = res[0]
                    donor_norm_map[norm_name] = canonical_id
                    
        if not canonical_id:
            print(f"⚠️ Warning: Canonical donor '{canonical_name}' not found in DB. Creating...")
            parts = canonical_name.split()
            fname = parts[0] if parts else canonical_name
            lname = " ".join(parts[1:]) if len(parts) > 1 else ""
            cursor.execute("INSERT INTO donors (name, surname, join_date, card_number) VALUES (?, ?, '1401/01/01', ?)",
                           (fname, lname, card))
            canonical_id = cursor.lastrowid
            donor_norm_map[norm_name] = canonical_id

        # Update canonical donor's card_number if empty
        cursor.execute("UPDATE donors SET card_number = ? WHERE id = ? AND (card_number IS NULL OR card_number = '')", (card, canonical_id))
        updated_cards += 1

        # Find unknown donors created for this card
        cursor.execute("SELECT id FROM donors WHERE card_number = ? AND id != ?", (card, canonical_id))
        old_donor_ids = [r[0] for r in cursor.fetchall()]

        # Re-link donations
        cursor.execute("UPDATE donations SET donor_id = ? WHERE description LIKE ?", (canonical_id, f"%{card}%"))
        relinked = cursor.rowcount
        total_relinked_donations += relinked

        # Also re-link if any donation was explicitly linked to old_donor_ids
        for old_did in old_donor_ids:
            cursor.execute("UPDATE donations SET donor_id = ? WHERE donor_id = ?", (canonical_id, old_did))
            cursor.execute("DELETE FROM donors WHERE id = ?", (old_did,))
            cleaned_unknown_donors += 1

        print(f"✅ Card {card} -> Linked to '{canonical_name}' (ID {canonical_id}) | {relinked} donations mapped.")

    # Recalculate total_donated for all donors
    print("\nRecalculating total_donated for all donors...")
    cursor.execute("""
        UPDATE donors 
        SET total_donated = COALESCE((
            SELECT SUM(amount) 
            FROM donations 
            WHERE donations.donor_id = donors.id
        ), 0)
    """)

    conn.commit()

    # Check Ehsan Khadivi's updated status
    cursor.execute("SELECT id, name, surname, total_donated, card_number FROM donors WHERE name = 'احسان' AND surname = 'خدیوی'")
    khadivi_stat = cursor.fetchone()
    cursor.execute("SELECT COUNT(*), MIN(date), MAX(date) FROM donations WHERE donor_id = ?", (khadivi_stat[0],))
    k_cnt, k_min, k_max = cursor.fetchone()

    cursor.execute("SELECT COUNT(*) FROM donors WHERE name LIKE '%ناشناس%' OR surname LIKE '%ناشناس%'")
    remaining_unknown = cursor.fetchone()[0]

    conn.close()

    print("\n================ CARD RELINK SUMMARY ================")
    print(f"Total Donations Relinked: {total_relinked_donations}")
    print(f"Redundant Unknown Donor Profiles Cleaned: {cleaned_unknown_donors}")
    print(f"Remaining Unknown Donors in DB: {remaining_unknown}")
    print("-----------------------------------------------------")
    print(f"Ehsan Khadivi Profile:")
    print(f"  Total Donated: {khadivi_stat[3]:,} Rials")
    print(f"  Donations Count: {k_cnt} donations (From {k_min} to {k_max})")
    print("=====================================================")

if __name__ == '__main__':
    run_relink()
