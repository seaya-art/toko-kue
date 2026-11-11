import sys
from datetime import date, datetime, timedelta

import pymysql
from openpyxl import Workbook
from openpyxl.styles import Alignment, Font, PatternFill
from openpyxl.utils import get_column_letter

from db_config import DB

RUPIAH = '"Rp"#,##0'

# subquery total pembayaran per pesanan
TERBAYAR = """(SELECT id_pesanan, SUM(jumlah) AS terbayar
               FROM catatan_transaksi GROUP BY id_pesanan) t"""


def hitung_periode(jenis, acuan):
    if jenis == "minggu":
        awal = acuan - timedelta(days=acuan.weekday())  # Senin
        akhir = awal + timedelta(days=6)                # Minggu
    else:
        awal = acuan.replace(day=1)
        bulan_depan = (awal.replace(day=28) + timedelta(days=4)).replace(day=1)
        akhir = bulan_depan - timedelta(days=1)
    return awal, akhir


def ambil(cur, sql, params=()):
    cur.execute(sql, params)
    return cur.fetchall()


def tulis_sheet(ws, kolom, baris, kolom_rupiah=()):
    ws.append(kolom)
    for c in ws[1]:
        c.font = Font(bold=True, color="FFFFFF")
        c.fill = PatternFill("solid", fgColor="4F81BD")
        c.alignment = Alignment(horizontal="center")
    for b in baris:
        ws.append(list(b))
    for idx in kolom_rupiah:  # nomor kolom, mulai dari 1
        for row in ws.iter_rows(min_row=2, min_col=idx, max_col=idx):
            for cell in row:
                cell.number_format = RUPIAH
    for i, kol in enumerate(ws.columns, 1):
        lebar = max(len(str(c.value)) if c.value is not None else 0 for c in kol)
        ws.column_dimensions[get_column_letter(i)].width = min(lebar + 3, 45)
    ws.freeze_panes = "A2"


def main():
    jenis = sys.argv[1] if len(sys.argv) > 1 else "minggu"
    if jenis not in ("minggu", "bulan"):
        sys.exit("Pakai: python buat_laporan.py [minggu|bulan] [YYYY-MM-DD]")
    acuan = date.today()
    if len(sys.argv) > 2:
        try:
            acuan = datetime.strptime(sys.argv[2], "%Y-%m-%d").date()
        except ValueError:
            sys.exit("Format tanggal harus YYYY-MM-DD.")
    awal, akhir = hitung_periode(jenis, acuan)
    p = (awal, akhir)

    conn = pymysql.connect(**DB, charset="utf8mb4")
    try:
        with conn.cursor() as cur:
            jumlah, nilai = ambil(cur, """
                SELECT COUNT(*), CAST(COALESCE(SUM(total), 0) AS SIGNED)
                FROM pesanan
                WHERE tanggal_ambil BETWEEN %s AND %s
                  AND status_pesanan <> 'dibatalkan'""", p)[0]

            uang_masuk = ambil(cur, """
                SELECT CAST(COALESCE(SUM(jumlah), 0) AS SIGNED)
                FROM catatan_transaksi
                WHERE DATE(tanggal_bayar) BETWEEN %s AND %s""", p)[0][0]

            sisa_periode = ambil(cur, f"""
                SELECT CAST(COALESCE(SUM(p.total - COALESCE(t.terbayar, 0)), 0) AS SIGNED)
                FROM pesanan p LEFT JOIN {TERBAYAR} ON t.id_pesanan = p.id_pesanan
                WHERE p.tanggal_ambil BETWEEN %s AND %s
                  AND p.status_pesanan <> 'dibatalkan'""", p)[0][0]

            pesanan = ambil(cur, f"""
                SELECT p.id_pesanan, b.nama, p.tanggal_ambil, p.sumber,
                       p.cara_pengiriman, p.status_pesanan, p.status_bayar, p.total,
                       CAST(COALESCE(t.terbayar, 0) AS SIGNED),
                       CAST(p.total - COALESCE(t.terbayar, 0) AS SIGNED)
                FROM pesanan p
                JOIN pembeli b ON b.id_pembeli = p.id_pembeli
                LEFT JOIN {TERBAYAR} ON t.id_pesanan = p.id_pesanan
                WHERE p.tanggal_ambil BETWEEN %s AND %s
                  AND p.status_pesanan <> 'dibatalkan'
                ORDER BY p.tanggal_ambil, p.id_pesanan""", p)

            terlaris = ambil(cur, """
                SELECT m.nama_kue, m.kategori,
                       CAST(SUM(i.jumlah) AS SIGNED),
                       CAST(SUM(i.jumlah * i.harga_satuan) AS SIGNED)
                FROM item_pesanan i
                JOIN pesanan p ON p.id_pesanan = i.id_pesanan
                JOIN menu m ON m.id_menu = i.id_menu
                WHERE p.tanggal_ambil BETWEEN %s AND %s
                  AND p.status_pesanan <> 'dibatalkan'
                GROUP BY m.id_menu, m.nama_kue, m.kategori
                ORDER BY SUM(i.jumlah) DESC""", p)

            per_sumber = ambil(cur, """
                SELECT sumber, COUNT(*), CAST(SUM(total) AS SIGNED)
                FROM pesanan
                WHERE tanggal_ambil BETWEEN %s AND %s
                  AND status_pesanan <> 'dibatalkan'
                GROUP BY sumber ORDER BY COUNT(*) DESC""", p)

            # belum lunas: semua periode, bukan hanya periode laporan
            belum_lunas = ambil(cur, f"""
                SELECT p.id_pesanan, b.nama, b.no_wa, p.tanggal_ambil, p.total,
                       CAST(COALESCE(t.terbayar, 0) AS SIGNED),
                       CAST(p.total - COALESCE(t.terbayar, 0) AS SIGNED)
                FROM pesanan p
                JOIN pembeli b ON b.id_pembeli = p.id_pembeli
                LEFT JOIN {TERBAYAR} ON t.id_pesanan = p.id_pesanan
                WHERE p.status_pesanan <> 'dibatalkan' AND p.status_bayar <> 'lunas'
                ORDER BY p.tanggal_ambil, p.id_pesanan""")
    finally:
        conn.close()

    wb = Workbook()
    ws = wb.active
    ws.title = "Ringkasan"
    ws.append(["Laporan", f"{jenis.capitalize()}an"])
    ws.append(["Periode (tanggal ambil/antar)", f"{awal} s/d {akhir}"])
    ws.append(["Dibuat", datetime.now().strftime("%Y-%m-%d %H:%M")])
    ws.append([])
    ws.append(["Jumlah pesanan", jumlah])
    ws.append(["Total nilai pesanan", nilai])
    ws.append(["Uang diterima (berdasarkan tanggal bayar)", uang_masuk])
    ws.append(["Sisa tagihan pesanan periode ini", sisa_periode])
    for baris in (6, 7, 8):
        ws.cell(row=baris, column=2).number_format = RUPIAH
    for r in ws.iter_rows(min_col=1, max_col=1):
        r[0].font = Font(bold=True)
    ws.column_dimensions["A"].width = 42
    ws.column_dimensions["B"].width = 28

    tulis_sheet(wb.create_sheet("Pesanan"),
                ["#", "Pelanggan", "Tanggal ambil", "Sumber", "Pengiriman",
                 "Status", "Bayar", "Total", "Terbayar", "Sisa"],
                pesanan, kolom_rupiah=(8, 9, 10))
    tulis_sheet(wb.create_sheet("Kue Terlaris"),
                ["Kue", "Kategori", "Jumlah terjual", "Omzet"],
                terlaris, kolom_rupiah=(4,))
    tulis_sheet(wb.create_sheet("Per Sumber"),
                ["Sumber", "Jumlah pesanan", "Total nilai"],
                per_sumber, kolom_rupiah=(3,))
    tulis_sheet(wb.create_sheet("Belum Lunas"),
                ["#", "Pelanggan", "No. WA", "Tanggal ambil", "Total", "Terbayar", "Sisa"],
                belum_lunas, kolom_rupiah=(5, 6, 7))

    nama_file = f"laporan_{jenis}_{awal}_{akhir}.xlsx"
    wb.save(nama_file)
    print("Laporan tersimpan:", nama_file)


if __name__ == "__main__":
    main()