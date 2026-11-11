<?php
require __DIR__ . '/../koneksi.php';
require __DIR__ . '/../auth.php';
wajib_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pesanan.php');
    exit;
}
csrf_cek();

$id     = (int) ($_POST['id'] ?? 0);
$jumlah = (int) ($_POST['jumlah'] ?? 0);
$jenis  = $_POST['jenis'] ?? '';

if (!in_array($jenis, ['tf', 'qris', 'cash'], true)) {
    exit('Jenis pembayaran tidak valid.');
}
// aturan Bu Rina: transfer dan QRIS hanya owner
if ($jenis !== 'cash') {
    wajib_owner();
}

try {
    $pdo->beginTransaction();

    // kunci baris pesanan supaya dua klik bersamaan tidak menggandakan pembayaran
    $stmt = $pdo->prepare("SELECT total, status_pesanan FROM pesanan WHERE id_pesanan = ? FOR UPDATE");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if (!$p) {
        $pdo->rollBack();
        http_response_code(404);
        exit('Pesanan tidak ditemukan.');
    }
    if ($p['status_pesanan'] === 'dibatalkan') {
        $pdo->rollBack();
        exit('Pesanan sudah dibatalkan.');
    }

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) FROM catatan_transaksi WHERE id_pesanan = ?");
    $stmt->execute([$id]);
    $terbayar = (int) $stmt->fetchColumn();
    $sisa = (int) $p['total'] - $terbayar;

    if ($jumlah < 1 || $jumlah > $sisa) {
        $pdo->rollBack();
        exit('Jumlah harus antara 1 dan sisa tagihan (Rp' . number_format($sisa, 0, ',', '.') . ').');
    }

    $pdo->prepare("INSERT INTO catatan_transaksi (id_pesanan, jumlah, jenis, dikonfirmasi_oleh)
                   VALUES (?, ?, ?, ?)")
        ->execute([$id, $jumlah, $jenis, $_SESSION['id_pengguna']]);

    $terbayar += $jumlah;
    $statusBayar = $terbayar >= (int) $p['total'] ? 'lunas' : 'dp_masuk';
    $pdo->prepare("UPDATE pesanan SET status_bayar = ? WHERE id_pesanan = ?")
        ->execute([$statusBayar, $id]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    exit('Gagal mencatat pembayaran.');
}

header('Location: detail.php?id=' . $id);
exit;