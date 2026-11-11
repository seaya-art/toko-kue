# Sistem Pesanan Toko Kue

Proyek latihan: sistem pencatatan pesanan untuk toko kue rumahan.
Dibuat sebagai studi kasus analisis kebutuhan, desain database, dan pemrograman web (PHP + MySQL).

## Latar belakang
Pemilik toko menerima pesanan lewat WhatsApp, Instagram, telepon, dan titipan, lalu mencatatnya di buku. Akibatnya jadwal bentrok, status bayar lupa dicatat, dan ongkir tidak tertagih. Sistem ini menyatukan semua pesanan di satu tempat.

## Fitur
- Login dengan 2 peran (owner dan staf)
- Input pesanan dari telepon, titipan, dan toko (pelanggan dicari lewat nomor WA)
- Kuota harian per kategori kue, dengan persetujuan owner jika terlewati
- Alur status pesanan dan pembatalan
- Catat pembayaran (DP dan pelunasan), daftar pesanan belum lunas

## Keamanan
- Prepared statement (PDO) untuk semua query
- Password disimpan dengan `password_hash`
- Token CSRF di setiap form yang mengubah data
- Pengecekan peran di sisi server

## Database
10 tabel: pembeli, pengguna, zona, menu, pengaturan, alamat_pembeli,
kuota_tanggal, pesanan, item_pesanan, catatan_transaksi.
Skema ada di `database/schema.sql`.

## Cara menjalankan
1. Jalankan XAMPP (Apache dan MySQL), PHP 8.x
2. Import `database/schema.sql` lewat phpMyAdmin
3. Salin `config.example.php` menjadi `config.php`, lalu sesuaikan isinya
4. Buat akun pengguna (isi tabel `pengguna`, password dibuat dengan `password_hash()`)
5. Buka `http://localhost/toko-kue/admin/login.php`

## Status
- [x] Analisis kebutuhan
- [x] Desain database
- [x] Panel admin
- [ ] Website pembeli
- [ ] Laporan (Python)