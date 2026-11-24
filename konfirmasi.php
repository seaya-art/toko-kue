<?php
require __DIR__ . '/koneksi.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/fungsi.php';
require __DIR__ . '/kuota.php';

$co = $_SESSION['checkout'] ?? null;
if (!$co || empty($_SESSION['keranjang'])) {
    header('Location: index.php');
    exit;
}

// data kue di keranjang, selalu dari database
$ids = array_map('intval', array_keys($_SESSION['keranjang']));
$in  = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT id_menu, nama_kue, harga, kategori FROM menu
                       WHERE aktif = 1 AND id_menu IN ($in)");
$stmt->execute($ids);
$menuById = array_column($stmt->fetchAll(), null, 'id_menu');

$items = [];
$baris = [];
$total = 0;
foreach ($_SESSION['keranjang'] as $id => $jml) {
    if (!isset($menuById[$id])) {
        continue;
    }
    $harga = (int) $menuById[$id]['harga'];
    $items[] = ['id' => (int) $id, 'jumlah' => (int) $jml, 'harga' => $harga];
    $baris[] = [$menuById[$id]['nama_kue'], (int) $jml, $harga * (int) $jml, $co['catatan_item'][$id] ?? ''];
    $total += $harga * (int) $jml;
}
if (!$items) {
    header('Location: keranjang.php');
    exit;
}

// ongkir dari zona (kalau zona sudah dihapus, anggap di luar zona)
$ongkir = 0;
$ketOngkir = '';
$zonaDipakai = null;
if ($co['cara'] === 'antar') {
    if ($co['id_zona']) {
        $stmt = $pdo->prepare("SELECT tarif FROM zona WHERE id_zona = ?");
        $stmt->execute([$co['id_zona']]);
        $tarif = $stmt->fetchColumn();
        if ($tarif !== false) {
            $ongkir = (int) $tarif;
            $zonaDipakai = (int) $co['id_zona'];
        }
    }
    if ($zonaDipakai === null) {
        $ketOngkir = ' (dikonfirmasi admin)';
    }
}
$sumber = in_array($_SESSION['sumber'] ?? '', ['website', 'ig', 'wa'], true) ? $_SESSION['sumber'] : 'website';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();

    $kunci = $_POST['kunci'] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/', $kunci)) {
        exit('Kunci tidak valid.');
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM percobaan_pesan
                           WHERE ip = ? AND waktu > (NOW() - INTERVAL 1 HOUR)");
    $stmt->execute([$ip]);

    if ((int) $stmt->fetchColumn() >= 10) {
        $errors[] = 'Terlalu banyak percobaan. Coba lagi satu jam lagi, atau hubungi admin.';
    } elseif ($co['tanggal'] < date('Y-m-d', strtotime('+1 day'))) {
        $errors[] = 'Tanggal yang dipilih sudah lewat. Silakan ubah data pemesanan.';
    } else {
        $pdo->prepare("INSERT INTO percobaan_pesan (ip) VALUES (?)")->execute([$ip]);
    if (random_int(1, 50) === 1) {
        $pdo->exec("DELETE FROM percobaan_pesan WHERE waktu < (NOW() - INTERVAL 7 DAY)");
        }

        try {
            $pdo->beginTransaction();

            // pesanan lain menunggu di sini sampai transaksi ini selesai
            $pdo->query("SELECT id_pengaturan FROM pengaturan LIMIT 1 FOR UPDATE");
            if (cek_kuota($pdo, $co['tanggal'], $items, $menuById)) {
                throw new DomainException('kuota');
            }

            // pelanggan lama dicari lewat nomor WA, namanya tidak pernah ditimpa
            $stmt = $pdo->prepare("SELECT id_pembeli FROM pembeli WHERE no_wa = ?");
            $stmt->execute([$co['wa']]);
            $idPembeli = $stmt->fetchColumn();

            if ($idPembeli) {
                $idPembeli = (int) $idPembeli;
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM pesanan
                                       WHERE id_pembeli = ? AND status_pesanan = 'menunggu_konfirmasi'");
                $stmt->execute([$idPembeli]);
                if ((int) $stmt->fetchColumn() >= 3) {
                    throw new DomainException('batas');
                }
            } else {
                $pdo->prepare("INSERT INTO pembeli (nama, no_wa) VALUES (?, ?)")
                    ->execute([$co['nama'], $co['wa']]);
                $idPembeli = (int) $pdo->lastInsertId();
            }

            $idAlamat = null;
            if ($co['cara'] === 'antar') {
                $a = $co['alamat'];
                $rtrw = $a['rt'] . '/' . $a['rw'];
                $stmt = $pdo->prepare("SELECT id_alamat FROM alamat_pembeli
                    WHERE id_pembeli = ? AND nama_jalan = ? AND rt_rw = ? AND no_rumah = ?
                      AND kelurahan = ? AND kecamatan = ? AND kota_kabupaten = ? LIMIT 1");
                $stmt->execute([$idPembeli, $a['nama_jalan'], $rtrw, $a['no_rumah'],
                                $a['kelurahan'], $a['kecamatan'], $a['kota_kabupaten']]);
                $idAlamat = $stmt->fetchColumn();
                if ($idAlamat) {
                    $idAlamat = (int) $idAlamat;
                } else {
                    $pdo->prepare("INSERT INTO alamat_pembeli
                        (id_pembeli, nama_jalan, rt_rw, no_rumah, kelurahan, kecamatan, kota_kabupaten, patokan)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                        ->execute([$idPembeli, $a['nama_jalan'], $rtrw, $a['no_rumah'], $a['kelurahan'],
                                   $a['kecamatan'], $a['kota_kabupaten'],
                                   $a['patokan'] !== '' ? $a['patokan'] : null]);
                    $idAlamat = (int) $pdo->lastInsertId();
                }
            }

            $pdo->prepare("INSERT INTO pesanan
                (id_pembeli, tanggal_ambil, jam_ambil, sumber, cara_pengiriman, id_alamat, id_zona,
                 ongkir, total, tipe_bayar, status_bayar, status_pesanan, nominal_dp, dibuat_oleh,
                 catatan, kunci)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'belum_bayar', 'belum_bayar',
                        'menunggu_konfirmasi', 0, NULL, ?, ?)")
                ->execute([$idPembeli, $co['tanggal'], $co['jam'] !== '' ? $co['jam'] : null, $sumber,
                           $co['cara'], $idAlamat, $zonaDipakai, $ongkir, $total + $ongkir,
                           $co['catatan'] !== '' ? $co['catatan'] : null, $kunci]);
            $idPesanan = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO item_pesanan (id_pesanan, id_menu, jumlah, harga_satuan, catatan)
                                   VALUES (?, ?, ?, ?, ?)");
            foreach ($items as $it) {
                $c = $co['catatan_item'][$it['id']] ?? '';
                $stmt->execute([$idPesanan, $it['id'], $it['jumlah'], $it['harga'], $c !== '' ? $c : null]);
            }

            $pdo->commit();

            unset($_SESSION['keranjang'], $_SESSION['checkout']);
            $_SESSION['pesanan_terakhir'] = $idPesanan;
            header('Location: terima_kasih.php');
            exit;
        } catch (DomainException $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage() === 'kuota'
                ? 'Maaf, kuota pada tanggal itu baru saja penuh. Pilih tanggal lain, atau hubungi admin.'
                : 'Kamu masih punya beberapa pesanan yang menunggu konfirmasi. Tunggu admin menghubungi dulu ya.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->errorInfo[1] == 1062) {   // kunci sama (klik ganda) atau nomor WA baru bentrok
                $stmt = $pdo->prepare("SELECT id_pesanan FROM pesanan WHERE kunci = ?");
                $stmt->execute([$kunci]);
                $idAda = $stmt->fetchColumn();
                if ($idAda) {
                    unset($_SESSION['keranjang'], $_SESSION['checkout']);
                    $_SESSION['pesanan_terakhir'] = (int) $idAda;
                    header('Location: terima_kasih.php');
                    exit;
                }
                $errors[] = 'Terjadi bentrok data, silakan klik kirim sekali lagi.';
            } else {
                error_log($e->getMessage());
                $errors[] = 'Gagal menyimpan pesanan. Silakan coba lagi atau hubungi admin.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log($e->getMessage());
            $errors[] = 'Gagal menyimpan pesanan. Silakan coba lagi atau hubungi admin.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Konfirmasi</title>
  <link rel="stylesheet" href="assets/gaya.css">
</head>
<body>
  <p><a href="checkout.php">&larr; Ubah data</a></p>
  <h1>Ringkasan Pesanan</h1>

  <?php foreach ($errors as $e): ?>
    <p style="color:red"><?= h($e) ?></p>
  <?php endforeach; ?>

  <p>Atas nama: <?= h($co['nama']) ?> (<?= h($co['wa']) ?>)<br>
     Tanggal: <?= h($co['tanggal']) ?> <?= h($co['jam']) ?><br>
     Pengiriman: <?= $co['cara'] === 'antar' ? 'Diantar' : 'Ambil di toko' ?></p>
  <ul>
    <?php foreach ($baris as $b): ?>
      <li><?= h($b[0]) ?> &times; <?= (int) $b[1] ?> = <?= rp($b[2]) ?>
        <?php if ($b[3] !== ''): ?><br><em><?= h($b[3]) ?></em><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
  <p>Ongkir: <?= rp($ongkir) ?><?= h($ketOngkir) ?><br>
     <strong>Total: <?= rp($total + $ongkir) ?></strong></p>
  <p>Pesananmu akan dikonfirmasi oleh pemilik toko lewat WhatsApp, termasuk cara pembayarannya.</p>

  <form method="post" id="formKirim">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="kunci" value="<?= bin2hex(random_bytes(16)) ?>">
    <p><button type="submit">Kirim pesanan</button></p>
  </form>
  <script>
    document.getElementById('formKirim').addEventListener('submit', function () {
      var b = this.querySelector('button[type="submit"]');
      setTimeout(function () { b.disabled = true; }, 0);
    });
  </script>
</body>
</html>