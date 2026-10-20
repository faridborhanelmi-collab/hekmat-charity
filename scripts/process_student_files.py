#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Hekmat Charity - Automated Student File Processor
Handles processing, optimization, and database registration of:
1. Portrait photos (uploads/photos/)
2. School Report Cards / Karnameh (uploads/docs/)
3. National ID Cards / Shenasnameh (uploads/docs/)
"""

import os
import sys
import time
import secrets
import sqlite3
from PIL import Image, ImageOps

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(BASE_DIR, 'hekmat.db')
PHOTOS_DIR = os.path.join(BASE_DIR, 'uploads', 'photos')
DOCS_DIR = os.path.join(BASE_DIR, 'uploads', 'docs')

DOWNLOADS_DIR = os.path.expanduser('~/Downloads')
DIR_PHOTOS = os.path.join(DOWNLOADS_DIR, 'عکس')
DIR_KARNAME = os.path.join(DOWNLOADS_DIR, 'کارنامه')
DIR_SHENASNAME = os.path.join(DOWNLOADS_DIR, 'شناسنامه')

os.makedirs(PHOTOS_DIR, exist_ok=True)
os.makedirs(DOCS_DIR, exist_ok=True)

def optimize_image(src_path, dest_path, max_dim=1200, quality=85):
    """
    Optimizes image by fixing EXIF orientation, resizing if necessary,
    and saving as JPEG with quality 85.
    """
    try:
        with Image.open(src_path) as img:
            img = ImageOps.exif_transpose(img)
            if img.mode in ('RGBA', 'P', 'LA'):
                img = img.convert('RGB')
            w, h = img.size
            if w > max_dim or h > max_dim:
                if w > h:
                    new_w = max_dim
                    new_h = int(round((h * max_dim) / w))
                else:
                    new_h = max_dim
                    new_w = int(round((w * max_dim) / h))
                img = img.resize((new_w, new_h), Image.Resampling.LANCZOS)
            img.save(dest_path, 'JPEG', quality=quality, optimize=True)
            return True
    except Exception as e:
        print(f"Error optimizing {src_path}: {e}")
        return False

def generate_safe_filename():
    return f"{int(time.time())}_{secrets.token_hex(8)}.jpg"

def main():
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()

    now_str = time.strftime('%Y-%m-%d %H:%M:%S')

    # =========================================================================
    # 1. PROCESS PHOTOS (uploads/photos/)
    # =========================================================================
    print("\n--- 1. Processing Student Portrait Photos ---")
    photos_map = [
        ('الهام اکبرزاده.jpg', 713, 'الهام اکبرزاده'),
        ('الهام نقیب.jpg', 714, 'الهام نقیب'),
        ('حانیه دشتبانی.jpg', 688, 'حانیه دشتبانی'),
        ('سارینا داوری.jpg', 706, 'سارینا داوری'),
        ('سحر خادم.jpg', 705, 'سحر خادم حقیقی'),
        ('عکس آیدا سلیمانی.jpg', 716, 'آیدا سلیمانی'),
        ('عکس حسنا عظیمی .jpg', 718, 'حسنا عظیمی خراسانی'),
        ('عکس زهرا رضایی.jpg', 721, 'زهرا رضائی'),
        ('عکس زهرا ضماند.jpg', 979, 'زهرا ضماند'),
        ('عکس مریم قرئی.jpg', 732, 'مریم قرئی'),
        ('عکس مطهره مبارز.jpg', 733, 'مطهره مبارز'),
        ('عکس نازنین زهرا اقایی.jpg', 736, 'نازنین زهرا آقایی'),
        ('عکس نازنین زهرا گوهری .jpg', 737, 'نازنین زهرا گوهری'),
        ('عکس نازنین زینب محمد پور.jpg', 738, 'نازنین زینب محمدپور'),
        ('عکس نیایش بلوریان.jpg', 739, 'نیایش بلوریان'),
        ('عکس کوثر آدینی .jpg', 728, 'کوثر آدینی'),
        ('فاطمه حیدزاده.jpg', 689, 'فاطمه حیدرزاده'),
        ('فرزانه عبدی.jpg', 985, 'فرزانه عبدی'),
        ('محمد امین صفایی.jpg', 769, 'محمد امین صفایی'),
        ('مهناز سادات حسینی.jpg', 766, 'مهناز سادات حسینی'),
        ('نازنین زینب اصلاح.jpg', 748, 'نازنین زینب اصلاح'),
        ('نرگس حسنی.jpg', 763, 'نرگس حسنی'),
    ]

    for fname, st_id, st_name in photos_map:
        fpath = os.path.join(DIR_PHOTOS, fname)
        if not os.path.exists(fpath):
            print(f"⚠️ File not found: {fpath}")
            continue

        cursor.execute("SELECT photo_path FROM students WHERE id = ?;", (st_id,))
        row = cursor.fetchone()
        existing_photo = row[0] if row else None

        # Check if existing photo file actually exists
        needs_upload = True
        if existing_photo:
            full_existing = os.path.join(BASE_DIR, existing_photo)
            if os.path.exists(full_existing):
                # Photo already exists and is present on disk
                needs_upload = False
                print(f"  ✓ Photo already exists for ID {st_id} ({st_name}): {existing_photo}")

        if needs_upload:
            safe_name = generate_safe_filename()
            rel_path = f"uploads/photos/{safe_name}"
            dest_path = os.path.join(PHOTOS_DIR, safe_name)
            if optimize_image(fpath, dest_path):
                cursor.execute("UPDATE students SET photo_path = ? WHERE id = ?;", (rel_path, st_id))
                print(f"  + Uploaded & Saved Photo for ID {st_id} ({st_name}) -> {rel_path}")
            time.sleep(0.05)

    # =========================================================================
    # 2. PROCESS REPORT CARDS / KARNAMEH (uploads/docs/)
    # =========================================================================
    print("\n--- 2. Processing School Report Cards (کارنامه‌ها) ---")
    
    # Special fix: Rename camera raw names for report cards if existing
    cursor.execute("""
        UPDATE documents 
        SET file_name = 'کارنامه تحصیلی - مریم قرئی.jpg' 
        WHERE owner_type = 'student' AND owner_id = 732 AND (file_name LIKE '%94625%' OR file_name LIKE '%IMG%');
    """)
    cursor.execute("""
        UPDATE documents 
        SET file_name = 'کارنامه تحصیلی - پارسا پرهیزکار.jpg' 
        WHERE owner_type = 'student' AND owner_id = 717 AND (file_name LIKE '%94648%' OR file_name LIKE '%IMG%');
    """)

    # Karnameh mapping
    karnameh_map = [
        ('کارنامه حانیه دشتبانی.jpg', 688, 'حانیه دشتبانی', 'کارنامه تحصیلی - حانیه دشتبانی.jpg'),
        ('کارنامه ستایش محمودی زاده.jpg', 772, 'سیده ستایش محمودی‌زاده', 'کارنامه ستایش محمودی زاده.jpg'),
        ('فرزانه عبدی کارنامه.jpg', 985, 'فرزانه عبدی', 'فرزانه عبدی کارنامه.jpg'),
        ('IMG_20261014_094625_939.jpg', 732, 'مریم قرئی', 'کارنامه تحصیلی - مریم قرئی.jpg'),
        ('IMG_20261014_094648_818.jpg', 717, 'پارسا پرهیزکار', 'کارنامه تحصیلی - پارسا پرهیزکار.jpg'),
    ]

    for fname, st_id, st_name, display_name in karnameh_map:
        fpath = os.path.join(DIR_KARNAME, fname)
        if not os.path.exists(fpath):
            print(f"⚠️ File not found: {fpath}")
            continue

        # Check if already in documents table
        cursor.execute("""
            SELECT id, file_path, file_name FROM documents 
            WHERE owner_type = 'student' AND owner_id = ? AND description = 'کارنامه تحصیلی';
        """, (st_id,))
        existing_doc = cursor.fetchone()

        if existing_doc:
            doc_file = os.path.join(BASE_DIR, existing_doc[1])
            if os.path.exists(doc_file):
                print(f"  ✓ Karnameh already exists for ID {st_id} ({st_name}): {existing_doc[1]} ({existing_doc[2]})")
                continue

        safe_name = generate_safe_filename()
        rel_path = f"uploads/docs/{safe_name}"
        dest_path = os.path.join(DOCS_DIR, safe_name)
        if optimize_image(fpath, dest_path):
            cursor.execute("""
                INSERT INTO documents (owner_type, owner_id, file_path, file_name, upload_date, description)
                VALUES ('student', ?, ?, ?, ?, 'کارنامه تحصیلی');
            """, (st_id, rel_path, display_name, now_str))
            print(f"  + Added Karnameh for ID {st_id} ({st_name}): {display_name} -> {rel_path}")
        time.sleep(0.05)

    # =========================================================================
    # 3. PROCESS NATIONAL ID / SHENASNAMEH (uploads/docs/)
    # =========================================================================
    print("\n--- 3. Processing National ID Cards (شناسنامه‌ها) ---")
    
    # Pre-clean/rename any raw camera names in existing documents
    raw_name_fixes = [
        (999, 'شناسنامه - زهرا محمودی.jpg', '%095354%'),
        (748, 'شناسنامه - نازنین زینب اصلاح.jpg', '%095501%'),
        (763, 'شناسنامه - نرگس حسنی.jpg', '%095621%'),
        (770, 'شناسنامه - بهناز عباسی.jpg', '%095719%'),
        (754, 'شناسنامه - الینا ذاکری.jpg', '%095815%'),
        (771, 'شناسنامه - عطیه سالاری.jpg', '%095908%'),
    ]
    for st_id, fixed_name, pattern in raw_name_fixes:
        cursor.execute("""
            UPDATE documents 
            SET file_name = ?, description = 'شناسنامه'
            WHERE owner_type = 'student' AND owner_id = ? AND file_name LIKE ?;
        """, (fixed_name, st_id, pattern))

    shenasnameh_map = [
        ('ش الهام اکبرزاده.jpg', 713, 'الهام اکبرزاده', 'شناسنامه - الهام اکبرزاده.jpg'),
        ('ش حانیه دشتبانی.jpg', 688, 'حانیه دشتبانی', 'شناسنامه - حانیه دشتبانی.jpg'),
        ('ش سارینا داوری.jpg', 706, 'سارینا داوری', 'شناسنامه - سارینا داوری.jpg'),
        ('ش فاطمه حیدزاده.jpg', 689, 'فاطمه حیدرزاده', 'شناسنامه - فاطمه حیدرزاده.jpg'),
        ('ش لیلا محمدی.jpg', 729, 'لیلا محمدی', 'شناسنامه - لیلا محمدی.jpg'),
        ('ش معصومه صفدریان.jpg', 734, 'معصومه صفدریان', 'شناسنامه - معصومه صفدریان.jpg'),
        ('شناسنامه  زهرا محمودی.jpg', 999, 'زهرا محمودی', 'شناسنامه - زهرا محمودی.jpg'),
        ('شناسنامه الهام نقیب.jpg', 714, 'الهام نقیب', 'شناسنامه - الهام نقیب.jpg'),
        ('شناسنامه الینا ذاکری.jpg', 754, 'الینا ذاکری', 'شناسنامه - الینا ذاکری.jpg'),
        ('شناسنامه بهناز عباسی.jpg', 770, 'بهناز عباسی', 'شناسنامه - بهناز عباسی.jpg'),
        ('شناسنامه حسنا عظیمی.jpg', 718, 'حسنا عظیمی خراسانی', 'شناسنامه - حسنا عظیمی خراسانی.jpg'),
        ('شناسنامه ریحانه حقانی .jpg', 720, 'ریحانه حقانی', 'شناسنامه - ریحانه حقانی.jpg'),
        ('شناسنامه زهرا رضایی.jpg', 721, 'زهرا رضائی', 'شناسنامه - زهرا رضائی.jpg'),
        ('شناسنامه زهرا ضماند.jpg', 979, 'زهرا ضماند', 'شناسنامه - زهرا ضماند.jpg'),
        ('شناسنامه سحر خادم.jpg', 705, 'سحر خادم حقیقی', 'شناسنامه - سحر خادم حقیقی.jpg'),
        ('شناسنامه سیده فاطمه جواهری فرد.jpg', 741, 'فاطمه جواهری', 'شناسنامه - سیده فاطمه جواهری فرد.jpg'),
        ('شناسنامه سیده فرزانه جواهری فرد.jpg', 740, 'فرزانه جواهری', 'شناسنامه - سیده فرزانه جواهری فرد.jpg'),
        ('شناسنامه عطیه سالاری.jpg', 771, 'عطیه سالاری', 'شناسنامه - عطیه سالاری.jpg'),
        ('شناسنامه فاطمه سادات جوادی.jpg', 724, 'فاطمه سادات جوادی', 'شناسنامه - فاطمه سادات جوادی.jpg'),
        ('شناسنامه فرزانه عبدی.jpg', 985, 'فرزانه عبدی', 'شناسنامه - فرزانه عبدی.jpg'),
        ('شناسنامه محمد امین صفایی.jpg', 769, 'محمد امین صفایی', 'شناسنامه - محمد امین صفایی.jpg'),
        ('شناسنامه مریم قرئی.jpg', 732, 'مریم قرئی', 'شناسنامه - مریم قرئی.jpg'),
        ('شناسنامه مطهره مبارز.jpg', 733, 'مطهره مبارز', 'شناسنامه - مطهره مبارز.jpg'),
        ('شناسنامه مهدی پسته شکر.jpg', 751, 'سید محمد مهدی پسته شکر', 'شناسنامه - سید محمد مهدی پسته شکر.jpg'),
        ('شناسنامه نازنین زهرا آقایی.jpg', 736, 'نازنین زهرا آقایی', 'شناسنامه - نازنین زهرا آقایی.jpg'),
        ('شناسنامه نازنین زهرا گوهری.jpg', 737, 'نازنین زهرا گوهری', 'شناسنامه - نازنین زهرا گوهری.jpg'),
        ('شناسنامه نازنین زینب اصلاح.jpg', 748, 'نازنین زینب اصلاح', 'شناسنامه - نازنین زینب اصلاح.jpg'),
        ('شناسنامه نازنین زینب محمد پور.jpg', 738, 'نازنین زینب محمدپور', 'شناسنامه - نازنین زینب محمدپور.jpg'),
        ('شناسنامه نرگس حسنی.jpg', 763, 'نرگس حسنی', 'شناسنامه - نرگس حسنی.jpg'),
        ('شناسنامه نیایش بلوریان.jpg', 739, 'نیایش بلوریان', 'شناسنامه - نیایش بلوریان.jpg'),
        ('شناسنامه هستی اسکناطی.jpg', 761, 'هستی اسکناطی', 'شناسنامه - هستی اسکناطی.jpg'),
        ('شناسنامه کوثر آدینی .jpg', 728, 'کوثر آدینی', 'شناسنامه - کوثر آدینی.jpg'),
        ('مهناز سادات حسینی شناسنامه.jpg', 766, 'مهناز سادات حسینی', 'شناسنامه - مهناز سادات حسینی.jpg'),
        ('نیایش حیدری شناسنامه.jpg', 768, 'نیایش حیدری', 'شناسنامه - نیایش حیدری.jpg'),
    ]

    for fname, st_id, st_name, display_name in shenasnameh_map:
        fpath = os.path.join(DIR_SHENASNAME, fname)
        if not os.path.exists(fpath):
            print(f"⚠️ File not found: {fpath}")
            continue

        # Check if already in documents table
        cursor.execute("""
            SELECT id, file_path, file_name FROM documents 
            WHERE owner_type = 'student' AND owner_id = ? AND description = 'شناسنامه';
        """, (st_id,))
        existing_doc = cursor.fetchone()

        if existing_doc:
            doc_file = os.path.join(BASE_DIR, existing_doc[1])
            if os.path.exists(doc_file):
                print(f"  ✓ Shenasnameh already exists for ID {st_id} ({st_name}): {existing_doc[1]} ({existing_doc[2]})")
                continue

        safe_name = generate_safe_filename()
        rel_path = f"uploads/docs/{safe_name}"
        dest_path = os.path.join(DOCS_DIR, safe_name)
        if optimize_image(fpath, dest_path):
            cursor.execute("""
                INSERT INTO documents (owner_type, owner_id, file_path, file_name, upload_date, description)
                VALUES ('student', ?, ?, ?, ?, 'شناسنامه');
            """, (st_id, rel_path, display_name, now_str))
            print(f"  + Added Shenasnameh for ID {st_id} ({st_name}): {display_name} -> {rel_path}")
        time.sleep(0.05)

    conn.commit()
    conn.close()
    print("\n✅ Local Database & Uploads Processing Completed Successfully!")

if __name__ == '__main__':
    main()
