<?php
require __DIR__ . '/koneksi.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/fungsi.php';
catat_sumber();

$menu = $pdo->query("SELECT id_menu, nama_kue, harga, kategori, foto
                     FROM menu WHERE aktif = 1
                     ORDER BY nama_kue")->fetchAll();

$kelompok = ['kering' => [], 'ultah' => []];
foreach ($menu as $m) {
    $kelompok[$m['kategori']][] = $m;
}
$judul = ['kering' => 'Kue Kering', 'ultah' => 'Kue Ulang Tahun'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Menu Kue</title>
  <link rel="stylesheet" href="assets/gaya.css">
</head>
<body>
  <header>
    <h1>Toko Kue Bu Rina</h1>
    <p>Keranjang: <?= (int) jumlah_keranjang() ?> item</p>
  </header>

  <?php foreach ($kelompok as $kat => $daftar): ?>
    <?php if (!$daftar) continue; ?>
    <h2><?= h($judul[$kat]) ?></h2>
    <div class="grid">
      <?php foreach ($daftar as $m): ?>
        <?php $foto = url_foto($m['foto']); ?>
        <a class="kartu" href="kue.php?id=<?= (int) $m['id_menu'] ?>">
          <?php if ($foto): ?>
            <img src="<?= h($foto) ?>" alt="<?= h($m['nama_kue']) ?>" loading="lazy">
          <?php else: ?>
            <div class="tanpa-foto">Foto belum ada</div>
          <?php endif; ?>
          <strong><?= h($m['nama_kue']) ?></strong>
          <span><?= rp($m['harga']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <?php if (!$menu): ?>
    <p>Menu belum tersedia.</p>
  <?php endif; ?>
</body>
</html>