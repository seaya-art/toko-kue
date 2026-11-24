<?php
require __DIR__ . '/koneksi.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/fungsi.php';
require __DIR__ . '/kuota.php';
catat_sumber();

if (empty($_SESSION['keranjang'])) {
    header('Location: keranjang.php');
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
foreach ($_SESSION['keranjang'] as $id => $jml) {
    if (!isset($menuById[$id])) {
        unset($_SESSION['keranjang'][$id]);
        continue;
    }
    $items[] = ['id' => (int) $id, 'jumlah' => (int) $jml, 'harga' => (int) $menuById[$id]['harga']];
}
if (!$items) {
    header('Location: keranjang.php');
    exit;
}

$zona = $pdo->query("SELECT id_zona, nama_zona, tarif FROM zona ORDER BY id_zona")->fetchAll();
$zonaById = array_column($zona, null, 'id_zona');

$min = date('Y-m-d', strtotime('+1 day'));
$max = date('Y-m-d', strtotime('+90 days'));
$diminta = [];
foreach ($items as $it) {
    $kat = $menuById[$it['id']]['kategori'];
    $diminta[$kat] = ($diminta[$kat] ?? 0) + $it['jumlah'];
}
$penuh = tanggal_penuh($pdo, $min, $max, $diminta);
$hari  = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
$errors = [];
$kuotaPenuh = false;
$old = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();

    $nama    = trim($_POST['nama'] ?? '');
    $wa      = normalisasi_wa($_POST['no_wa'] ?? '');
    $tgl     = $_POST['tanggal_ambil'] ?? '';
    $jam     = $_POST['jam_ambil'] ?? '';
    $cara    = $_POST['cara_pengiriman'] ?? '';
    $idZona  = (int) ($_POST['id_zona'] ?? 0);
    $catatan = mb_substr(trim($_POST['catatan'] ?? ''), 0, 1000);

    if ($nama === '' || mb_strlen($nama) > 100) {
        $errors[] = 'Nama wajib diisi (maksimal 100 karakter).';
    }
    if (!preg_match('/^62\d{8,13}$/', $wa)) {
        $errors[] = 'Nomor WhatsApp tidak valid. Contoh: 081234567890.';
    }

    $d = DateTime::createFromFormat('Y-m-d', $tgl);
    $tglOk = $d && $d->format('Y-m-d') === $tgl && $tgl >= $min && $tgl <= $max;
    if (!$tglOk) {
        $errors[] = 'Tanggal harus antara besok dan 90 hari ke depan.';
    }
    if ($jam !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jam)) {
        $errors[] = 'Jam tidak valid.';
    }
    if (!in_array($cara, ['antar', 'ambil'], true)) {
        $errors[] = 'Pilih cara pengiriman.';
    }

    // catatan per kue, kunci harus id kue yang ada di keranjang
    $catatanItem = [];
    foreach ($items as $it) {
        $c = mb_substr(trim($_POST['catatan_item'][$it['id']] ?? ''), 0, 255);
        $catatanItem[$it['id']] = $c;
    }

    $alamat = [];
    $zonaDipakai = null;
    if ($cara === 'antar') {
        $zonaDipakai = isset($zonaById[$idZona]) ? $idZona : null;   // null = di luar zona
        foreach (['nama_jalan', 'rt', 'rw', 'no_rumah', 'kelurahan', 'kecamatan', 'kota_kabupaten', 'patokan'] as $f) {
            $alamat[$f] = trim($_POST[$f] ?? '');
            if ($f !== 'patokan' && $alamat[$f] === '') {
                $errors[] = 'Alamat belum lengkap.';
                break;
            }
            if (mb_strlen($alamat[$f]) > 80) {
                $errors[] = 'Salah satu kolom alamat terlalu panjang (maksimal 80 karakter).';
                break;
            }
        }
    }

    if (!$errors && cek_kuota($pdo, $tgl, $items, $menuById)) {
        $kuotaPenuh = true;
        $errors[] = 'Maaf, kuota pada tanggal itu sudah penuh. Pilih tanggal lain, atau hubungi admin.';
    }

    if (!$errors) {
        $_SESSION['checkout'] = [
            'nama' => $nama, 'wa' => $wa, 'tanggal' => $tgl, 'jam' => $jam,
            'cara' => $cara, 'id_zona' => $zonaDipakai, 'alamat' => $alamat,
            'catatan' => $catatan, 'catatan_item' => $catatanItem,
        ];
        header('Location: konfirmasi.php');
        exit;
    }
}

$waAdmin = preg_replace('/\D/', '', $cfg['wa_admin'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Checkout</title>
  <link rel="stylesheet" href="assets/gaya.css">
</head>
<body>
  <p><a href="keranjang.php">&larr; Keranjang</a></p>
  <h1>Data Pemesanan</h1>

  <?php foreach ($errors as $e): ?>
    <p style="color:red"><?= h($e) ?></p>
  <?php endforeach; ?>
  <?php if ($kuotaPenuh && $waAdmin !== ''): ?>
    <p><a href="https://wa.me/<?= h($waAdmin) ?>?text=<?= rawurlencode('Halo, saya ingin tanya ketersediaan tanggal ' . $tgl) ?>">Hubungi admin lewat WhatsApp</a></p>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

    <h2>Identitas</h2>
    <p><input name="nama" placeholder="Nama" maxlength="100" value="<?= h($old['nama'] ?? '') ?>" required></p>
    <p><input name="no_wa" placeholder="Nomor WhatsApp" inputmode="tel" value="<?= h($old['no_wa'] ?? '') ?>" required></p>

    <h2>Kapan dan bagaimana</h2>
    <p>Tanggal ambil/antar:
      <select name="tanggal_ambil" required>
      <option value="">- pilih tanggal -</option>
        <?php
        $adaTersedia = false;
        $akhirTgl = new DateTime($max);
        for ($d = new DateTime($min); $d <= $akhirTgl; $d->modify('+1 day')):
            $t = $d->format('Y-m-d');
            $penuhIni = isset($penuh[$t]);
            if (!$penuhIni) {
                $adaTersedia = true;
            }
            $label = $hari[(int) $d->format('N')] . ', ' . $d->format('d-m-Y') . ($penuhIni ? ' (penuh)' : '');
        ?>
          <option value="<?= h($t) ?>"
                  <?= $penuhIni ? 'disabled' : '' ?>
                  <?= ($old['tanggal_ambil'] ?? '') === $t ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endfor; ?>
      </select>
    </p>
    <?php if (!$adaTersedia): ?>
      <p style="color:red">Jumlah kue di keranjang melebihi kuota harian. Kurangi jumlahnya, atau hubungi admin.</p>
    <?php endif; ?>
    <?php if ($waAdmin !== ''): ?>
      <p><small>Tanggal penuh? <a href="https://wa.me/<?= h($waAdmin) ?>?text=<?= rawurlencode('Halo, saya ingin tanya ketersediaan tanggal untuk pesanan kue.') ?>">Hubungi admin lewat WhatsApp</a></small></p>
    <?php endif; ?>
    <p>Jam (opsional): <input type="time" name="jam_ambil" value="<?= h($old['jam_ambil'] ?? '') ?>"></p>
    <p>
      <select name="cara_pengiriman" id="cara">
        <option value="ambil" <?= ($old['cara_pengiriman'] ?? '') === 'ambil' ? 'selected' : '' ?>>Ambil di toko</option>
        <option value="antar" <?= ($old['cara_pengiriman'] ?? '') === 'antar' ? 'selected' : '' ?>>Diantar</option>
      </select>
    </p>

    <div id="alamat" style="display:none">
      <p>
        <select name="id_zona">
          <?php foreach ($zona as $z): ?>
            <option value="<?= (int) $z['id_zona'] ?>" <?= (int) ($old['id_zona'] ?? 0) === (int) $z['id_zona'] ? 'selected' : '' ?>>
              <?= h($z['nama_zona']) ?> (ongkir <?= rp($z['tarif']) ?>)
            </option>
          <?php endforeach; ?>
          <option value="0" <?= isset($old['id_zona']) && (int) $old['id_zona'] === 0 ? 'selected' : '' ?>>Di luar zona (ongkir dikonfirmasi admin)</option>
        </select>
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

    <h2>Catatan</h2>
    <?php foreach ($items as $it): ?>
      <p><?= h($menuById[$it['id']]['nama_kue']) ?> &times; <?= (int) $it['jumlah'] ?><br>
         <input name="catatan_item[<?= (int) $it['id'] ?>]" maxlength="255" style="width:100%"
                placeholder="Catatan (mis. tulisan di atas kue)"
                value="<?= h($old['catatan_item'][$it['id']] ?? '') ?>"></p>
    <?php endforeach; ?>
    <p><textarea name="catatan" rows="2" maxlength="1000" placeholder="Catatan untuk pesanan (opsional)" style="width:100%"><?= h($old['catatan'] ?? '') ?></textarea></p>

    <p><button type="submit">Lanjut</button></p>
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