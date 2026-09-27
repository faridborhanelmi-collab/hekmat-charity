#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import sqlite3
import pandas as pd

DB_PATH = 'hekmat.db'

def run():
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()
    
    # 1. Get Donor IDs
    c.execute("SELECT id FROM donors WHERE name = 'رضا' AND surname = 'پوررضایی'")
    reza_id = c.fetchone()[0]
    
    c.execute("SELECT id FROM donors WHERE (name LIKE '%محمد%' AND surname = 'پوررضایی') OR (name = 'محمدرضا' AND surname = 'پوررضایی')")
    m_reza_id = c.fetchone()[0]
    
    print(f"Reza Pourrezaei ID: {reza_id}")
    print(f"Mohammad Reza Pourrezaei ID: {m_reza_id}")
    
    # 2. Find all donations from Pariz Shargh (containing 'پاریز' or donor_id in [reza_id, m_reza_id, 1813] from 1403/06 onwards)
    c.execute("""
        SELECT id, date, amount, description 
        FROM donations 
        WHERE description LIKE '%پاریز%' OR donor_id = 1813 OR (donor_id IN (?, ?) AND date >= '1403/06/01')
        ORDER BY date ASC, id ASC
    """, (reza_id, m_reza_id))
    
    all_pariz_dns = c.fetchall()
    print(f"Total Pariz Shargh related donations in DB: {len(all_pariz_dns)}")
    
    # Group by date
    from collections import defaultdict
    date_map = defaultdict(list)
    for row in all_pariz_dns:
        dt = row[1][:10]
        date_map[dt].append(row)
        
    print(f"Found {len(date_map)} unique dates.")
    
    reza_assigned = 0
    m_reza_assigned = 0
    
    for dt, rows in sorted(date_map.items()):
        # Sort rows by id
        rows.sort(key=lambda x: x[0])
        if len(rows) == 2:
            # 1st to Reza, 2nd to Mohammad Reza
            r_row = rows[0]
            mr_row = rows[1]
            c.execute("UPDATE donations SET donor_id = ?, month = ?, year = ? WHERE id = ?", (reza_id, r_row[1][5:7], r_row[1][:4], r_row[0]))
            c.execute("UPDATE donations SET donor_id = ?, month = ?, year = ? WHERE id = ?", (m_reza_id, mr_row[1][5:7], mr_row[1][:4], mr_row[0]))
            reza_assigned += 1
            m_reza_assigned += 1
            print(f"Date {dt} (Pair): Reza -> {r_row[2]:,} | Mohammad Reza -> {mr_row[2]:,}")
        elif len(rows) == 1:
            # Single donation - check if we can match
            single_row = rows[0]
            c.execute("UPDATE donations SET donor_id = ? WHERE id = ?", (reza_id, single_row[0]))
            reza_assigned += 1
            print(f"Date {dt} (Single): Assigned to Reza -> {single_row[2]:,}")
        else:
            print(f"Date {dt} has {len(rows)} donations!")
            # alternate
            for idx, r in enumerate(rows):
                target = reza_id if idx % 2 == 0 else m_reza_id
                c.execute("UPDATE donations SET donor_id = ? WHERE id = ?", (target, r[0]))
                if target == reza_id: reza_assigned += 1
                else: m_reza_assigned += 1

    # 3. Clean up temporary Pariz donor (1813) if 0 donations left
    c.execute("SELECT COUNT(*) FROM donations WHERE donor_id = 1813")
    rem_1813 = c.fetchone()[0]
    if rem_1813 == 0:
        c.execute("DELETE FROM donors WHERE id = 1813")
        print("Cleaned up temporary Pariz Shargh profile (ID 1813).")
        
    # 4. Update descriptions for Reza and Mohammad Reza
    c.execute("UPDATE donors SET description = 'هم‌بنیان‌گذار شرکت پاریز شرق (واریزی ماهانه از طریق شرکت پاریز شرق به همراه برادرشان محمدرضا پوررضایی)' WHERE id = ?", (reza_id,))
    c.execute("UPDATE donors SET description = 'هم‌بنیان‌گذار شرکت پاریز شرق (واریزی ماهانه از طریق شرکت پاریز شرق به همراه برادرشان رضا پوررضایی)' WHERE id = ?", (m_reza_id,))
    
    # 5. Recalculate total_donated
    c.execute("""
        UPDATE donors 
        SET total_donated = COALESCE((
            SELECT SUM(amount) 
            FROM donations 
            WHERE donations.donor_id = donors.id
        ), 0)
    """)
    
    conn.commit()
    
    # Verify stats
    c.execute("SELECT id, name, surname, total_donated FROM donors WHERE id IN (?, ?)", (reza_id, m_reza_id))
    for r in c.fetchall():
        c.execute("SELECT COUNT(*), MIN(date), MAX(date) FROM donations WHERE donor_id = ?", (r[0],))
        cnt, dmin, dmax = c.fetchone()
        print(f"Donor {r[1]} {r[2]} (ID {r[0]}): Total {r[3]:,} Rials | {cnt} donations ({dmin} to {dmax})")
        
    conn.close()

if __name__ == '__main__':
    run()
