import pymysql
from db_config import DB

conn = pymysql.connect(**DB, charset="utf8mb4")
try:
    with conn.cursor() as cur:
        cur.execute("""
            SELECT COUNT(*) FROM pesanan
            WHERE tanggal_ambil >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
              AND tanggal_ambil <  DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
              AND status_pesanan <> 'dibatalkan'
        """)
        print("Pesanan minggu ini:", cur.fetchone()[0])
finally:
    conn.close()