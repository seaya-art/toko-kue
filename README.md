# Sistem Pesanan Toko Kue

Aplikasi web untuk mencatat dan mengelola pesanan toko kue rumahan: website pemesanan untuk pelanggan, panel admin untuk pemilik dan staf, serta laporan Excel mingguan dan bulanan.

Proyek ini dibuat sebagai latihan menyeluruh: mulai dari wawancara kebutuhan klien, desain database, pemrograman web (PHP dan MySQL), pelaporan (Python), sampai peninjauan keamanan. Semua nama, nomor, dan data di dalamnya fiktif.

## Daftar isi

- [Latar belakang](#latar-belakang)
- [Fitur](#fitur)
- [Alur dan aturan bisnis](#alur-dan-aturan-bisnis)
- [Keamanan](#keamanan)
- [Teknologi](#teknologi)
- [Struktur folder](#struktur-folder)
- [Database](#database)
- [Cara menjalankan](#cara-menjalankan)
- [Laporan Excel](#laporan-excel)
- [Keterbatasan dan rencana lanjutan](#keterbatasan-dan-rencana-lanjutan)
- [Status](#status)

## Latar belakang

Pemilik toko menerima pesanan lewat WhatsApp, DM Instagram, telepon, dan titipan tetangga, lalu mencatatnya di buku, tangkapan layar, atau hanya diingat. Akibatnya:

- jadwal bentrok (misalnya dua kue ulang tahun jatuh di hari yang sama, padahal kapasitas hanya dua per hari),
- status pembayaran lupa dicatat dan ongkir tidak tertagih,
- belanja bahan dilakukan berdasarkan perkiraan.

Sistem ini menyatukan semua pesanan di satu tempat. Pelanggan memesan lewat tautan yang dibagikan di bio Instagram atau balasan WhatsApp, sedangkan pesanan dari telepon, titipan, dan toko diinput langsung oleh pemilik atau staf lewat panel admin.

## Fitur

### Website pelanggan

- Menu dibagi dua kategori (kue kering dan kue ulang tahun), lengkap dengan foto, harga, bahan, dan deskripsi.
- Keranjang belanja (disimpan di sesi server, harga selalu dibaca ulang dari database).
- Form pemesanan: identitas, tanggal ambil atau antar, jam, pilihan ambil di toko atau diantar (pilih zona ongkir atau di luar zona), alamat, dan catatan per kue (misalnya tulisan di atas kue).
- Tanggal yang kuotanya penuh tampil abu-abu dan tidak bisa dipilih, disertai tautan ke WhatsApp admin.
- Sumber pesanan tercatat otomatis dari tautan (`?dari=ig` atau `?dari=wa`).
- Pesanan masuk dengan status "menunggu konfirmasi" dan dikonfirmasi pemilik lewat WhatsApp.

### Panel admin

- Login dengan dua peran: **owner** (pemilik) dan **staf**.
- Daftar pesanan (pesanan yang menunggu konfirmasi tampil paling atas) dan daftar pesanan belum lunas dengan sisa tagihan.
- Input pesanan manual untuk pesanan telepon, titipan, toko, WhatsApp, atau Instagram. Pelanggan lama dikenali lewat nomor WhatsApp.
- Pengecekan kuota harian dengan peringatan. Hanya owner yang boleh melewati kuota.
- Perubahan status pesanan sesuai alur yang diizinkan.
- Pencatatan pembayaran (DP dan pelunasan) dan koreksinya.
- Detail pesanan: item, catatan per kue, ongkir, total, riwayat pembayaran, terbayar, dan sisa.

### Laporan

Skrip Python menghasilkan file Excel mingguan atau bulanan dengan lima sheet: Ringkasan, Pesanan, Kue Terlaris, Per Sumber, dan Belum Lunas.

## Alur dan aturan bisnis

**Status pesanan**

```
menunggu_konfirmasi -> dikonfirmasi -> dibuat -> siap -> selesai
                       (dibatalkan dari tahap mana pun, khusus owner)
```

Pesanan yang sudah `selesai` atau `dibatalkan` tidak bisa diubah lagi.

**Kuota**

- Kue ulang tahun: maksimal 2 per hari (nilai bawaan, dapat diubah di tabel `pengaturan`).
- Kue kering: kuota harian per kategori, dengan satuan yang dapat diatur.
- Kuota khusus per tanggal (misalnya musim Lebaran atau hari libur) disimpan di tabel `kuota_tanggal`.
- Pesanan yang menunggu konfirmasi ikut dihitung, pesanan dibatalkan tidak.
- Pengecekan diulang di dalam transaksi database yang terkunci, supaya dua pesanan bersamaan tidak sama-sama lolos.

**Pembayaran**

- Transfer dan QRIS hanya dapat dicatat oleh owner.
- Tunai dapat dicatat oleh owner dan staf, khusus pesanan yang diambil di toko.
- Pembayaran tidak pernah dihapus atau diubah. Pembayaran yang salah ditandai **dibatalkan** oleh owner dengan alasan, dan semua hitungan (detail pesanan, daftar belum lunas, laporan) hanya menjumlah pembayaran yang belum dibatalkan.
- Harga per item disalin ke pesanan saat dibuat, sehingga perubahan harga menu tidak mengubah pesanan dan laporan lama.

**Hak akses**

| Aksi | Owner | Staf |
|---|---|---|
| Lihat dan input pesanan | Ya | Ya |
| Ubah status (sesuai alur) | Ya | Ya |
| Batalkan pesanan | Ya | Tidak |
| Catat pembayaran transfer atau QRIS | Ya | Tidak |
| Catat pembayaran tunai | Ya | Ya |
| Batalkan (koreksi) pembayaran | Ya | Tidak |
| Melewati kuota | Ya | Tidak |

## Keamanan

Setelah fitur utama selesai, kode ditinjau dari sisi penyerang. Yang diterapkan:

- **SQL injection:** semua query memakai prepared statement (PDO).
- **XSS:** semua data yang ditampilkan melewati `htmlspecialchars`.
- **CSRF:** setiap form yang mengubah data memakai token, diverifikasi di server.
- **Password:** disimpan dengan `password_hash` dan diperiksa dengan `password_verify`.
- **Login:** batas percobaan gagal per username dan alamat IP, pesan galat yang sama untuk username salah maupun password salah, ID sesi diganti setelah login, cookie sesi `HttpOnly` dan `SameSite`, serta pengalihan setelah login hanya ke halaman di situs sendiri.
- **Hak akses:** peran dicek di server pada setiap aksi, bukan hanya disembunyikan di tampilan.
- **Input:** semua nilai divalidasi di server (rentang angka, panjang teks, daftar nilai yang diizinkan, rentang tanggal). Harga dan ongkir tidak pernah diambil dari form.
- **Klik ganda:** form pembayaran dan pemesanan memakai kunci unik sekali pakai, sehingga kiriman ganda tidak menghasilkan data ganda.
- **Pelanggan publik:** pembatasan percobaan kirim per alamat IP per jam, pembatasan pesanan yang menunggu konfirmasi per nomor WhatsApp, dan nama pelanggan lama tidak pernah ditimpa dari form publik.
- **Database:** aplikasi memakai akun khusus dengan hak `SELECT, INSERT, UPDATE, DELETE` pada satu database. Skrip laporan memakai akun lain yang hanya boleh `SELECT`.
- **Berkas rahasia:** `config.php`, kredensial laporan, log, dan file `.xlsx` masuk `.gitignore`. File `.htaccess` memblokir akses langsung ke berkas konfigurasi, log, skema database, dan folder laporan, serta menambahkan header keamanan.
- **Galat:** pesan galat tidak ditampilkan ke pengguna, hanya dicatat ke log.
- **Laporan Excel:** teks dari database yang diawali `=`, `+`, `-`, atau `@` dinetralkan agar tidak dibaca sebagai rumus.

## Teknologi

- PHP 8.x (PDO), tanpa framework
- MySQL atau MariaDB
- Apache (XAMPP)
- Python 3 dengan `pymysql` dan `openpyxl` untuk laporan
- HTML dan CSS sederhana, JavaScript secukupnya

## Struktur folder

```
toko-kue/
├── index.php              Menu (halaman pelanggan)
├── kue.php                Detail kue
├── keranjang.php          Keranjang belanja
├── checkout.php           Data pemesanan dan pengecekan kuota
├── konfirmasi.php         Ringkasan dan penyimpanan pesanan
├── terima_kasih.php       Halaman setelah pesanan terkirim
├── fungsi.php             Fungsi bersama halaman pelanggan
├── kuota.php              Logika pengecekan kuota
├── alur.php               Aturan perpindahan status pesanan
├── auth.php               Sesi, login, peran, dan token CSRF
├── koneksi.php            Koneksi database dan pengaturan galat
├── config.example.php     Contoh konfigurasi (salin menjadi config.php)
├── assets/                CSS
├── uploads/menu/          Foto kue
├── admin/                 Panel admin
│   ├── login.php, logout.php
│   ├── pesanan.php        Daftar pesanan dan belum lunas
│   ├── detail.php         Detail, status, dan pembayaran
│   ├── pesanan_baru.php   Input pesanan manual
│   ├── ubah_status.php
│   ├── catat_bayar.php
│   └── batal_bayar.php    Koreksi pembayaran
├── laporan/               Laporan Excel (Python)
│   ├── buat_laporan.py
│   ├── tes_koneksi.py
│   └── db_config.example.py
└── database/
    └── schema.sql         Struktur tabel
```

## Database

Dua belas tabel:

| Tabel | Isi |
|---|---|
| `pembeli` | Pelanggan (nama, nomor WhatsApp, username Instagram) |
| `alamat_pembeli` | Alamat pengantaran milik pelanggan |
| `menu` | Daftar kue, harga, kategori, dan status aktif |
| `zona` | Zona ongkir dan tarifnya |
| `pengaturan` | Pengaturan DP, kuota bawaan, dan batas hari |
| `kuota_tanggal` | Kuota khusus per tanggal dan kategori |
| `pesanan` | Data umum pesanan, status, ongkir, dan total |
| `item_pesanan` | Kue di dalam pesanan, dengan harga saat dipesan |
| `catatan_transaksi` | Pembayaran, termasuk penanda pembatalan dan alasannya |
| `pengguna` | Akun owner dan staf |
| `login_gagal` | Pencatat percobaan login yang gagal |
| `percobaan_pesan` | Pencatat percobaan kirim pesanan dari website |

![Diagram relasi tabel](docs/relasi.png)

## Cara menjalankan

Kebutuhan: XAMPP (atau Apache, PHP 8.x, dan MySQL/MariaDB), serta Python 3 untuk laporan.

1. **Salin proyek** ke folder web, misalnya `C:\xampp\htdocs\toko-kue`.

2. **Buat database.** Di phpMyAdmin, impor `database/schema.sql`.

3. **Buat akun database** (ganti password dengan milikmu sendiri, dan gunakan password yang berbeda untuk tiap akun):

   ```sql
   CREATE USER 'tokokue_app'@'localhost' IDENTIFIED BY 'PASSWORD_APLIKASI';
   GRANT SELECT, INSERT, UPDATE, DELETE ON toko_kue.* TO 'tokokue_app'@'localhost';

   CREATE USER 'laporan'@'localhost' IDENTIFIED BY 'PASSWORD_LAPORAN';
   GRANT SELECT ON toko_kue.* TO 'laporan'@'localhost';
   FLUSH PRIVILEGES;
   ```

4. **Atur konfigurasi.** Salin `config.example.php` menjadi `config.php`, lalu isi `user`, `pass`, dan `wa_admin` (nomor WhatsApp toko, format `62...`). Biarkan `'debug' => false` kecuali sedang mengembangkan.

5. **Siapkan data awal** (contoh, sesuaikan dengan toko):

   ```sql
   INSERT INTO pengaturan (jenis_dp, nilai_dp, batas_batal_ultah, batas_batal_kering,
                           kuota_default_ultah, kuota_default_kering, satuan_kuota_kering)
   VALUES ('persen', 50, 3, 1, 2, 20, 'toples');

   INSERT INTO zona (nama_zona, tarif, diantar_oleh) VALUES ('Zona 1', 10000, 'Staf');

   INSERT INTO menu (nama_kue, harga, kategori, aktif)
   VALUES ('Contoh Kue Kering', 80000, 'kering', 1),
          ('Contoh Kue Ulang Tahun', 150000, 'ultah', 1);
   ```

   Foto kue ditaruh di `uploads/menu/`, lalu nama berkasnya diisi di kolom `foto` tabel `menu`.

6. **Buat akun admin.** Buat hash password, misalnya lewat terminal:

   ```
   php -r "echo password_hash('PASSWORD_ANDA', PASSWORD_DEFAULT);"
   ```

   Lalu masukkan ke tabel `pengguna`:

   ```sql
   INSERT INTO pengguna (nama, username, password_hash, peran)
   VALUES ('Pemilik', 'owner', 'HASH_DARI_LANGKAH_INI', 'owner');
   ```

7. **Buka aplikasi:**
   - Pelanggan: `http://localhost/toko-kue/`
   - Admin: `http://localhost/toko-kue/admin/login.php`

**Catatan keamanan saat dipasang di server sungguhan:** gunakan HTTPS, nonaktifkan `display_errors` di `php.ini`, dan letakkan file aplikasi (selain folder yang memang perlu diakses publik) di luar folder web. Aturan `.htaccess` di proyek ini hanya berlaku di Apache.

## Laporan Excel

```
cd laporan
pip install pymysql openpyxl
```

Salin `db_config.example.py` menjadi `db_config.py`, lalu isi password akun `laporan`. Jalankan:

```
python buat_laporan.py minggu              # minggu berjalan (Senin sampai Minggu)
python buat_laporan.py bulan               # bulan berjalan
python buat_laporan.py minggu 2026-11-15   # minggu yang memuat tanggal itu
```

Periode dihitung berdasarkan tanggal ambil atau antar, dan pesanan dibatalkan tidak dihitung. "Uang diterima" dihitung berdasarkan tanggal bayar, sehingga angkanya bisa berbeda dari total nilai pesanan pada periode yang sama. Tutup file Excel sebelum menjalankan ulang, karena Excel mengunci berkas yang sedang terbuka.

## Keterbatasan dan rencana lanjutan

Yang belum ada:

- Manajemen stok bahan dan resep per kue.
- Notifikasi otomatis (WhatsApp atau email) saat ada pesanan baru.
- Pengajuan koreksi pembayaran dari staf (sekarang koreksi hanya oleh owner).
- Halaman admin untuk mengelola menu, harga, zona, dan kuota (sekarang lewat phpMyAdmin).
- Pembatalan pesanan belum otomatis menentukan status DP (dikembalikan atau hangus).
- Pembatasan percobaan berbasis alamat IP bisa dilewati dengan berganti IP. Lapisan utamanya adalah konfirmasi manual oleh pemilik sebelum pesanan diproses.
- Kebijakan keamanan konten (CSP) masih mengizinkan `unsafe-inline` karena beberapa halaman memakai skrip dan atribut sebaris.

## Status

- [x] Analisis kebutuhan (wawancara klien, fitur, alur, aturan bisnis)
- [x] Desain dan pembuatan database
- [x] Panel admin
- [x] Website pelanggan
- [x] Laporan Excel (Python)
- [x] Peninjauan keamanan
- [ ] Manajemen stok bahan (fase 2)
- [ ] Notifikasi otomatis
