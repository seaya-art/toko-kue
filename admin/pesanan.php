<?php
require __DIR__ . '/../koneksi.php';
require __DIR__ . '/../auth.php';
wajib_login();

$sql = "SELECT p.id_pesanan, b.nama, p.tanggal_ambil, p.sumber,
               p.status_pesanan, p.status_bayar, p.total
        FROM pesanan p
        JOIN pembeli b ON b.id_pembeli = p.id_pembeli
        ORDER BY p.tanggal_ambil ASC, p.id_pesanan ASC";
$pesanan = $pdo->query($sql)->fetchAll();

$sqlBelum = "SELECT p.id_pesanan, b.nama, p.tanggal_ambil, p.status_bayar, p.total,
                    COALESCE((SELECT SUM(t.jumlah) FROM catatan_transaksi t
                              WHERE t.id_pesanan = p.id_pesanan), 0) AS terbayar
             FROM pesanan p
             JOIN pembeli b ON b.id_pembeli = p.id_pembeli
             WHERE p.status_pesanan <> 'dibatalkan' AND p.status_bayar <> 'lunas'
             ORDER BY p.tanggal_ambil ASC, p.id_pesanan ASC";
$belumLunas = $pdo->query($sqlBelum)->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daftar Pesanan</title>
</head>
<body>
  <p><a href="logout.php">Keluar</a></p>
  <p><a href="pesanan_baru.php">+ Pesanan baru</a></p>
  <h1>Daftar Pesanan</h1>
  <div style="overflow-x:auto">
  <table border="1" cellpadding="6">
    <tr>
      <th>#</th><th>Pelanggan</th><th>Tanggal ambil</th>
      <th>Sumber</th><th>Status</th><th>Bayar</th><th>Total</th>
    </tr>
    <?php foreach ($pesanan as $p): ?>
    <tr>
      <td><?= $p['id_pesanan'] ?></td>
      <td><?= htmlspecialchars($p['nama']) ?></td>
      <td><?= htmlspecialchars($p['tanggal_ambil']) ?></td>
      <td><?= htmlspecialchars($p['sumber']) ?></td>
      <td><?= htmlspecialchars($p['status_pesanan']) ?></td>
      <td><?= htmlspecialchars($p['status_bayar']) ?></td>
      <td>Rp<?= number_format($p['total'], 0, ',', '.') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>

 <h1>Daftar Pesanan Belum Lunas</h1>
<?php if (!$belumLunas): ?>
  <p>Semua pesanan sudah lunas.</p>
<?php else: ?>
<div style="overflow-x:auto">
<table border="1" cellpadding="6">
  <tr>
    <th>#</th><th>Pelanggan</th><th>Tanggal ambil</th>
    <th>Total</th><th>Terbayar</th><th>Sisa</th>
  </tr>
  <?php foreach ($belumLunas as $b): ?>
  <tr>
    <td><a href="detail.php?id=<?= (int) $b['id_pesanan'] ?>">#<?= (int) $b['id_pesanan'] ?></a></td>
    <td><?= htmlspecialchars($b['nama']) ?></td>
    <td><?= htmlspecialchars($b['tanggal_ambil']) ?></td>
    <td>Rp<?= number_format($b['total'], 0, ',', '.') ?></td>
    <td>Rp<?= number_format($b['terbayar'], 0, ',', '.') ?></td>
    <td><strong>Rp<?= number_format($b['total'] - $b['terbayar'], 0, ',', '.') ?></strong></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php endif; ?>

</body>
</html>