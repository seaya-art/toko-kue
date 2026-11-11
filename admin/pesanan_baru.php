<?php
require __DIR__ . '/../koneksi.php';
require __DIR__ . '/../auth.php';
wajib_login();

function h($s) { return htmlspecialchars((string) $s); }

$menu = $pdo->query("SELECT id_menu, nama_kue, harga, kategori FROM menu
                     WHERE aktif = 1 ORDER BY kategori, nama_kue")->fetchAll();
$zona = $pdo->query("SELECT id_zona, nama_zona, tarif FROM zona ORDER BY id_zona")->fetchAll();
$menuById = array_column($menu, null, 'id_menu');
$zonaById = array_column($zona, null, 'id_zona');

$sumberOk = ['telepon', 'titipan', 'toko', 'wa', 'ig'];
$errors = [];
$kuotaPenuh = false;
$old = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();

    // nomor WA: buang selain angka, awalan 0 jadi 62
    $wa = preg_replace('/\D/', '', $_POST['no_wa'] ?? '');
    if (str_starts_with($wa, '0')) {
        $wa = '62' . substr($wa, 1);
    }
    $nama    = trim($_POST['nama'] ?? '');
    $tgl     = $_POST['tanggal_ambil'] ?? '';
    $jam     = $_POST['jam_ambil'] ?? '';
    $sumber  = $_POST['sumber'] ?? '';
    $cara    = $_POST['cara_pengiriman'] ?? '';
    $idZona  = (int) ($_POST['id_zona'] ?? 0);
    $catatan = mb_substr(trim($_POST['catatan'] ?? ''), 0, 1000);

    if (strlen($wa) < 9 || strlen($wa) > 15) {
        $errors[] = 'Nomor WA tidak valid.';
    }

    // cari pelanggan lama lewat nomor WA
    $idPembeli = null;
    if (!$errors) {
        $stmt = $pdo->prepare("SELECT id_pembeli FROM pembeli WHERE no_wa = ?");
        $stmt->execute([$wa]);
        $idPembeli = $stmt->fetchColumn() ?: null;
        if (!$idPembeli && $nama === '') {
            $errors[] = 'Pelanggan baru: nama wajib diisi.';
        }
    }

    $d = DateTime::createFromFormat('Y-m-d', $tgl);
    if (!$d || $d->format('Y-m-d') !== $tgl || $tgl < date('Y-m-d')) {
        $errors[] = 'Tanggal ambil/antar tidak valid (minimal hari ini).';
    }
    if ($jam !== '' && !preg_match('/^\d{2}:\d{2}$/', $jam)) {
        $errors[] = 'Jam tidak valid.';
    }
    if (!in_array($sumber, $sumberOk, true)) {
        $errors[] = 'Sumber pesanan tidak valid.';
    }
    if (!in_array($cara, ['antar', 'ambil'], true)) {
        $errors[] = 'Cara pengiriman tidak valid.';
    }

    // item pesanan (harga selalu diambil dari database, bukan dari form)
    $items = [];
    foreach (($_POST['menu'] ?? []) as $i => $idMenu) {
        $idMenu = (int) $idMenu;
        if ($idMenu === 0) continue;
        $jml = (int) ($_POST['jumlah'][$i] ?? 0);
        if (!isset($menuById[$idMenu]) || $jml < 1) {
            $errors[] = 'Baris item ' . ($i + 1) . ' tidak valid.';
            continue;
        }
        $cat = mb_substr(trim($_POST['catatan_item'][$i] ?? ''), 0, 255);
        $items[] = [
            'id'      => $idMenu,
            'jumlah'  => $jml,
            'harga'   => (int) $menuById[$idMenu]['harga'],
            'catatan' => $cat !== '' ? $cat : null,
        ];
    }
    if (!$items) {
        $errors[] = 'Pilih minimal satu kue.';
    }

    // ongkir dan alamat
    $ongkir = 0;
    $zonaDipakai = null;
    $alamat = [];
    if ($cara === 'antar') {
        if (isset($zonaById[$idZona])) {
            $zonaDipakai = $idZona;
            $ongkir = (int) $zonaById[$idZona]['tarif'];
        } else {
            $ongkir = max(0, (int) ($_POST['ongkir_manual'] ?? 0));
        }
        foreach (['nama_jalan', 'rt', 'rw', 'no_rumah', 'kelurahan', 'kecamatan', 'kota_kabupaten', 'patokan'] as $f) {
            $alamat[$f] = trim($_POST[$f] ?? '');
            if ($f !== 'patokan' && $alamat[$f] === '') {
                $errors[] = 'Alamat belum lengkap (' . $f . ').';
            }
        }
    }
    
        if (!$errors) {
        require_once __DIR__ . '/../kuota.php';
        $peringatan = cek_kuota($pdo, $tgl, $items, $menuById);
        if ($peringatan) {
            $boleh = $_SESSION['peran'] === 'owner' && !empty($_POST['lewati_kuota']);
            if (!$boleh) {
                $kuotaPenuh = true;
                foreach ($peringatan as $p) {
                    $errors[] = $p;
                }
            }
        }
    }

    if (!$errors) {
        $total = $ongkir;
        foreach ($items as $it) {
            $total += $it['harga'] * $it['jumlah'];
        }

        try {
            $pdo->beginTransaction();

            if (!$idPembeli) {
                $pdo->prepare("INSERT INTO pembeli (nama, no_wa) VALUES (?, ?)")->execute([$nama, $wa]);
                $idPembeli = (int) $pdo->lastInsertId();
            }

            $idAlamat = null;
            if ($cara === 'antar') {
                $pdo->prepare("INSERT INTO alamat_pembeli
                (id_pembeli, nama_jalan, rt_rw, no_rumah, kelurahan, kecamatan, kota_kabupaten, patokan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$idPembeli, $alamat['nama_jalan'], $alamat['rt'] . '/' . $alamat['rw'],
                           $alamat['no_rumah'], $alamat['kelurahan'], $alamat['kecamatan'],
                           $alamat['kota_kabupaten'], $alamat['patokan'] !== '' ? $alamat['patokan'] : null]);
                $idAlamat = (int) $pdo->lastInsertId();
            }

            $pdo->prepare("INSERT INTO pesanan
                (id_pembeli, tanggal_ambil, jam_ambil, sumber, cara_pengiriman, id_alamat, id_zona,
                 ongkir, total, tipe_bayar, status_bayar, status_pesanan, nominal_dp, dibuat_oleh, catatan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'belum_bayar', 'belum_bayar', 'dikonfirmasi', 0, ?, ?)")
                ->execute([$idPembeli, $tgl, $jam !== '' ? $jam : null, $sumber, $cara, $idAlamat,
                           $zonaDipakai, $ongkir, $total, $_SESSION['id_pengguna'],
                           $catatan !== '' ? $catatan : null]);
            $idPesanan = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO item_pesanan (id_pesanan, id_menu, jumlah, harga_satuan, catatan)
                                   VALUES (?, ?, ?, ?, ?)");
            foreach ($items as $it) {
                $stmt->execute([$idPesanan, $it['id'], $it['jumlah'], $it['harga'], $it['catatan']]);
            }

            $pdo->commit();
            header('Location: detail.php?id=' . $idPesanan);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
             $errors[] = 'Gagal menyimpan pesanan: ';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pesanan Baru</title>
</head>
<body>
  <p><a href="pesanan.php">&larr; Kembali</a></p>
  <h1>Pesanan Baru</h1>

  <?php foreach ($errors as $e): ?>
    <p style="color:red"><?= h($e) ?></p>
  <?php endforeach; ?>

  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

    <h2>Pelanggan</h2>
    <p><input name="no_wa" placeholder="Nomor WA" value="<?= h($old['no_wa'] ?? '') ?>" required></p>
    <p><input name="nama" placeholder="Nama (kosongkan kalau sudah langganan)" value="<?= h($old['nama'] ?? '') ?>"></p>

    <h2>Pesanan</h2>
    <p>Tanggal ambil/antar: <input type="date" name="tanggal_ambil" value="<?= h($old['tanggal_ambil'] ?? '') ?>" required>
       Jam: <input type="time" name="jam_ambil" value="<?= h($old['jam_ambil'] ?? '') ?>"></p>
    <p>Sumber:
      <select name="sumber">
        <?php foreach ($sumberOk as $s): ?>
          <option value="<?= $s ?>" <?= ($old['sumber'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </p>

    <?php for ($i = 0; $i < 5; $i++): ?>
    <p>
      <select name="menu[<?= $i ?>]">
        <option value="0">- pilih kue -</option>
        <?php foreach ($menu as $m): ?>
          <option value="<?= (int) $m['id_menu'] ?>" <?= (int) ($old['menu'][$i] ?? 0) === (int) $m['id_menu'] ? 'selected' : '' ?>>
            <?= h($m['nama_kue']) ?> (<?= h($m['kategori']) ?>) - Rp<?= number_format($m['harga'], 0, ',', '.') ?>
          </option>
        <?php endforeach; ?>
      </select>
      <input type="number" name="jumlah[<?= $i ?>]" min="1" placeholder="Jml" style="width:70px"
             value="<?= h($old['jumlah'][$i] ?? '') ?>">
      <input name="catatan_item[<?= $i ?>]" placeholder="Catatan kue (mis. tulisan di atas kue)"
             value="<?= h($old['catatan_item'][$i] ?? '') ?>">
    </p>
    <?php endfor; ?>

    <h2>Pengiriman</h2>
    <p>
      <select name="cara_pengiriman" id="cara">
        <option value="ambil" <?= ($old['cara_pengiriman'] ?? '') === 'ambil' ? 'selected' : '' ?>>Ambil di toko</option>
        <option value="antar" <?= ($old['cara_pengiriman'] ?? '') === 'antar' ? 'selected' : '' ?>>Diantar</option>
      </select>
    </p>

    <div id="alamat" style="display:none">
      <p>
        <select name="id_zona">
          <option value="0">Di luar zona (ojol), isi ongkir manual</option>
          <?php foreach ($zona as $z): ?>
            <option value="<?= (int) $z['id_zona'] ?>" <?= (int) ($old['id_zona'] ?? 0) === (int) $z['id_zona'] ? 'selected' : '' ?>>
              <?= h($z['nama_zona']) ?> - Rp<?= number_format($z['tarif'], 0, ',', '.') ?>
            </option>
          <?php endforeach; ?>
        </select>
        <input type="number" name="ongkir_manual" min="0" placeholder="Ongkir manual" value="<?= h($old['ongkir_manual'] ?? '') ?>">
      </p>
      <p><input name="nama_jalan" placeholder="Nama jalan" value="<?= h($old['nama_jalan'] ?? '') ?>"></p>
      <p><input name="rt" placeholder="RT" size="3" value="<?= h($old['rt'] ?? '') ?>">
         <input name="rw" placeholder="RW" size="3" value="<?= h($old['rw'] ?? '') ?>">
         <input name="no_rumah" placeholder="No. rumah" size="8" value="<?= h($old['no_rumah'] ?? '') ?>"></p>
      <p><input name="kelurahan" placeholder="Kelurahan" value="<?= h($old['kelurahan'] ?? '') ?>">
         <input name="kecamatan" placeholder="Kecamatan" value="<?= h($old['kecamatan'] ?? '') ?>"></p>
      <p><input name="kota_kabupaten" placeholder="Kota/Kabupaten" value="<?= h($old['kota_kabupaten'] ?? '') ?>">
         <input name="patokan" placeholder="Patokan (opsional)" value="<?= h($old['patokan'] ?? '') ?>"></p>
    </div>

    <p><textarea name="catatan" placeholder="Catatan untuk pesanan (opsional)" rows="2" cols="40"><?= h($old['catatan'] ?? '') ?></textarea></p>
     <?php if ($kuotaPenuh && $_SESSION['peran'] === 'owner'): ?>
      <p><label><input type="checkbox" name="lewati_kuota" value="1"> Tetap lanjutkan walau melewati kuota</label></p>
    <?php elseif ($kuotaPenuh): ?>
      <p>Kuota penuh. Hubungi Bu Rina untuk persetujuan.</p>
    <?php endif; ?>
    <p><button type="submit">Simpan pesanan</button></p>
  </form>

  <script>
    var cara = document.getElementById('cara');
    var alamat = document.getElementById('alamat');
    function atur() { alamat.style.display = cara.value === 'antar' ? 'block' : 'none'; }
    cara.addEventListener('change', atur);
    atur();
  </script>
</body>
</html>