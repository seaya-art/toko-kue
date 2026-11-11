<?php
require __DIR__ . '/../koneksi.php';
require __DIR__ . '/../auth.php';   // baris baru 1
wajib_login(); 

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT p.*, b.nama, b.no_wa
                       FROM pesanan p
                       JOIN pembeli b ON b.id_pembeli = p.id_pembeli
                       WHERE p.id_pesanan = ?");
$stmt->execute([$id]);
$pesanan = $stmt->fetch();

if (!$pesanan) {
    http_response_code(404);
    exit('Pesanan tidak ditemukan.');
}

$stmt = $pdo->prepare("SELECT m.nama_kue, i.jumlah, i.harga_satuan, i.catatan
                       FROM item_pesanan i
                       JOIN menu m ON m.id_menu = i.id_menu
                       WHERE i.id_pesanan = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT jumlah, jenis, tanggal_bayar
                       FROM catatan_transaksi
                       WHERE id_pesanan = ?
                       ORDER BY tanggal_bayar");
$stmt->execute([$id]);
$bayar = $stmt->fetchAll();

$terbayar = 0;
foreach ($bayar as $b) {
    $terbayar += $b['jumlah'];
}
$sisa = $pesanan['total'] - $terbayar;

$set = $pdo->query("SELECT jenis_dp, nilai_dp FROM pengaturan LIMIT 1")->fetch();
$saranDp = $set['jenis_dp'] === 'persen'
    ? (int) round($pesanan['total'] * $set['nilai_dp'] / 100)
    : (int) $set['nilai_dp'];

function rp($angka) {
    return 'Rp' . number_format($angka, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pesanan #<?= $id ?></title>
</head>
<body>
  <body>
  <p><a href="logout.php">Keluar</a></p>
  <p><a href="pesanan.php">&larr; Kembali</a></p>
  <h1>Pesanan #<?= $id ?></h1>
  <p>
    Pelanggan: <?= htmlspecialchars($pesanan['nama']) ?>
    (<?= htmlspecialchars($pesanan['no_wa']) ?>)<br>
    Tanggal ambil/antar: <?= htmlspecialchars($pesanan['tanggal_ambil']) ?><br>
    Status: <?= htmlspecialchars($pesanan['status_pesanan']) ?>
  </p>

<?php
$alur  = require __DIR__ . '/../alur.php';
$lanjut = isset($alur[$pesanan['status_pesanan']]) ? $alur[$pesanan['status_pesanan']] : [];
?>
<?php if ($lanjut): ?>
<form method="post" action="ubah_status.php">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="id" value="<?= $id ?>">
  <select name="status">
    <?php foreach ($lanjut as $s): ?>
      <?php if ($s === 'dibatalkan' && $_SESSION['peran'] !== 'owner') continue; ?>
      <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
    <?php endforeach; ?>
  </select>
  <input name="alasan" placeholder="Alasan (wajib kalau dibatalkan)">
  <button type="submit">Ubah status</button>
</form>
<?php endif; ?>

  <h2>Isi pesanan</h2>
  <table border="1" cellpadding="6">
    <tr><th>Kue</th><th>Jumlah</th><th>Harga</th><th>Subtotal</th><th>Catatan</th></tr>
    <?php foreach ($items as $i): ?>
    <tr>
      <td><?= htmlspecialchars($i['nama_kue']) ?></td>
      <td><?= (int) $i['jumlah'] ?></td>
      <td><?= rp($i['harga_satuan']) ?></td>
      <td><?= rp($i['jumlah'] * $i['harga_satuan']) ?></td>
      <td><?= htmlspecialchars($i['catatan']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <p>Ongkir: <?= rp($pesanan['ongkir']) ?><br>
     <strong>Total: <?= rp($pesanan['total']) ?></strong></p>

  <h2>Pembayaran</h2>
  <table border="1" cellpadding="6">
    <tr><th>Tanggal</th><th>Jenis</th><th>Jumlah</th></tr>
    <?php foreach ($bayar as $b): ?>
    <tr>
      <td><?= htmlspecialchars($b['tanggal_bayar']) ?></td>
      <td><?= htmlspecialchars($b['jenis']) ?></td>
      <td><?= rp($b['jumlah']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <p>Terbayar: <?= rp($terbayar) ?><br>
     <strong>Sisa: <?= rp($sisa) ?></strong></p>

  <?php if ($pesanan['status_pesanan'] !== 'dibatalkan' && $sisa > 0): ?>
  <h3>Catat pembayaran</h3>
  <p>Saran DP sesuai pengaturan: <?= rp($saranDp) ?></p>
  <form method="post" action="catat_bayar.php">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="number" name="jumlah" min="1" max="<?= (int) $sisa ?>"
           value="<?= $terbayar == 0 ? min($saranDp, $sisa) : $sisa ?>">
    <select name="jenis">
      <option value="cash">cash</option>
      <?php if ($_SESSION['peran'] === 'owner'): ?>
        <option value="tf">tf</option>
        <option value="qris">qris</option>
      <?php endif; ?>
    </select>
    <button type="submit">Catat</button>
  </form>
  <?php endif; ?>


</body>
</html>