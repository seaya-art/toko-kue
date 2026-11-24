<?php
require __DIR__ . '/koneksi.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/fungsi.php';
catat_sumber();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT id_menu, nama_kue, harga, kategori, foto, bahan, deskripsi
                       FROM menu WHERE id_menu = ? AND aktif = 1");
$stmt->execute([$id]);
$m = $stmt->fetch();
if (!$m) {
    http_response_code(404);
    exit('Kue tidak ditemukan.');
}
$foto = url_foto($m['foto']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($m['nama_kue']) ?></title>
  <link rel="stylesheet" href="assets/gaya.css">
</head>
<body>
  <p><a href="index.php">&larr; Menu</a> | <a href="keranjang.php">Keranjang (<?= (int) jumlah_keranjang() ?>)</a></p>

  <h1><?= h($m['nama_kue']) ?></h1>
  <?php if ($foto): ?>
    <img src="<?= h($foto) ?>" alt="<?= h($m['nama_kue']) ?>" style="max-width:100%;border-radius:8px">
  <?php endif; ?>
  <p><strong><?= rp($m['harga']) ?></strong></p>

  <?php if ($m['bahan']): ?>
    <h3>Bahan</h3>
    <p><?= nl2br(h($m['bahan'])) ?></p>
  <?php endif; ?>
  <?php if ($m['deskripsi']): ?>
    <h3>Deskripsi</h3>
    <p><?= nl2br(h($m['deskripsi'])) ?></p>
  <?php endif; ?>

  <form method="post" action="keranjang.php">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="aksi" value="tambah">
    <input type="hidden" name="id" value="<?= (int) $m['id_menu'] ?>">
    <p>Jumlah: <input type="number" name="jumlah" min="1" max="50" value="1" required></p>
    <p><button type="submit">Masukkan ke keranjang</button></p>
  </form>
</body>
</html>