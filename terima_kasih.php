<?php
require __DIR__ . '/koneksi.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/fungsi.php';

$id = (int) ($_SESSION['pesanan_terakhir'] ?? 0);
if (!$id) {
    header('Location: index.php');
    exit;
}
$waAdmin = preg_replace('/\D/', '', $cfg['wa_admin'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Terima kasih</title>
  <link rel="stylesheet" href="assets/gaya.css">
</head>
<body>
  <h1>Terima kasih!</h1>
  <p>Pesananmu <strong>#<?= $id ?></strong> sudah kami terima dan sedang menunggu konfirmasi.
     Pemilik toko akan menghubungi lewat WhatsApp.</p>
  <?php if ($waAdmin !== ''): ?>
    <p><a href="https://wa.me/<?= h($waAdmin) ?>?text=<?= rawurlencode('Halo, saya baru memesan, nomor pesanan #' . $id) ?>">Hubungi admin lewat WhatsApp</a></p>
  <?php endif; ?>
  <p><a href="index.php">Kembali ke menu</a></p>
</body>
</html>