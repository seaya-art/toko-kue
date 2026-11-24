<?php
require __DIR__ . '/koneksi.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/fungsi.php';
catat_sumber();

$_SESSION['keranjang'] = $_SESSION['keranjang'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $aksi = $_POST['aksi'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);
    $jml  = (int) ($_POST['jumlah'] ?? 0);

    if ($aksi === 'tambah') {
        $stmt = $pdo->prepare("SELECT 1 FROM menu WHERE id_menu = ? AND aktif = 1");
        $stmt->execute([$id]);
        $ada      = (bool) $stmt->fetchColumn();
        $barisBaru = !isset($_SESSION['keranjang'][$id]);
        if ($ada && $jml >= 1 && $jml <= 50
            && !($barisBaru && count($_SESSION['keranjang']) >= 20)) {
            $_SESSION['keranjang'][$id] = min(50, ($_SESSION['keranjang'][$id] ?? 0) + $jml);
        }
    } elseif ($aksi === 'ubah' && isset($_SESSION['keranjang'][$id])) {
        if ($jml >= 1) {
            $_SESSION['keranjang'][$id] = min(50, $jml);
        } else {
            unset($_SESSION['keranjang'][$id]);
        }
    } elseif ($aksi === 'hapus') {
        unset($_SESSION['keranjang'][$id]);
    }
    header('Location: keranjang.php');
    exit;
}

// ambil data kue dari database (harga asli), buang yang sudah nonaktif
$baris = [];
$total = 0;
if ($_SESSION['keranjang']) {
    $ids = array_map('intval', array_keys($_SESSION['keranjang']));
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id_menu, nama_kue, harga FROM menu
                           WHERE aktif = 1 AND id_menu IN ($in)");
    $stmt->execute($ids);
    $data = array_column($stmt->fetchAll(), null, 'id_menu');

    foreach ($_SESSION['keranjang'] as $id => $jml) {
        if (!isset($data[$id])) {
            unset($_SESSION['keranjang'][$id]);
            continue;
        }
        $sub = (int) $data[$id]['harga'] * $jml;
        $total += $sub;
        $baris[] = ['id' => $id, 'nama' => $data[$id]['nama_kue'],
                    'harga' => $data[$id]['harga'], 'jumlah' => $jml, 'sub' => $sub];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Keranjang</title>
  <link rel="stylesheet" href="assets/gaya.css">
</head>
<body>
  <p><a href="index.php">&larr; Lanjut belanja</a></p>
  <h1>Keranjang</h1>

  <?php if (!$baris): ?>
    <p>Keranjang masih kosong.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
    <table border="1" cellpadding="6">
      <tr><th>Kue</th><th>Harga</th><th>Jumlah</th><th>Subtotal</th><th></th></tr>
      <?php foreach ($baris as $b): ?>
      <tr>
        <td><?= h($b['nama']) ?></td>
        <td><?= rp($b['harga']) ?></td>
        <td>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="aksi" value="ubah">
            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
            <input type="number" name="jumlah" min="0" max="50" value="<?= (int) $b['jumlah'] ?>" style="width:60px">
            <button type="submit">Ubah</button>
          </form>
        </td>
        <td><?= rp($b['sub']) ?></td>
        <td>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
            <button type="submit">Hapus</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <p><strong>Total kue: <?= rp($total) ?></strong> (belum termasuk ongkir)</p>
    <p>Pesanan akan dikonfirmasi oleh pemilik toko setelah dikirim.</p>
    <p><a href="checkout.php">Lanjut ke pemesanan</a></p>
  <?php endif; ?>
</body>
</html>