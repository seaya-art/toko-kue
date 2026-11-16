<?php
require __DIR__ . '/../koneksi.php';
require __DIR__ . '/../auth.php';
wajib_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pesanan.php');
    exit;
}
csrf_cek();

$idBayar = (int) ($_POST['id_pembayaran'] ?? 0);
$alasan  = trim(mb_substr($_POST['alasan'] ?? '', 0, 255));
if ($alasan === '') {
    exit('Alasan koreksi wajib diisi.');
}

$stmt = $pdo->prepare("SELECT id_pesanan FROM catatan_transaksi WHERE id_pembayaran = ?");
$stmt->execute([$idBayar]);
$idPesanan = (int) $stmt->fetchColumn();
if (!$idPesanan) {
    http_response_code(404);
    exit('Pembayaran tidak ditemukan.');
}

try {
    $pdo->beginTransaction();

    // kunci pesanan dulu (urutan sama dengan catat_bayar.php, mencegah saling tunggu)
    $stmt = $pdo->prepare("SELECT total, status_pesanan FROM pesanan WHERE id_pesanan = ? FOR UPDATE");
    $stmt->execute([$idPesanan]);
    $p = $stmt->fetch();
    if ($p['status_pesanan'] === 'dibatalkan') {
        $pdo->rollBack();
        exit('Pesanan sudah dibatalkan, pembayarannya tidak bisa dikoreksi.');
    }

    $stmt = $pdo->prepare("SELECT dibatalkan FROM catatan_transaksi WHERE id_pembayaran = ?");
    $stmt->execute([$idBayar]);
    if ((int) $stmt->fetchColumn() === 1) {   // klik ganda: sudah dibatalkan
        $pdo->rollBack();
        header('Location: detail.php?id=' . $idPesanan);
        exit;
    }

    $pdo->prepare("UPDATE catatan_transaksi
                   SET dibatalkan = 1, alasan_koreksi = ?, dibatalkan_oleh = ?, waktu_dibatalkan = NOW()
                   WHERE id_pembayaran = ?")
        ->execute([$alasan, $_SESSION['id_pengguna'], $idBayar]);

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) FROM catatan_transaksi
                           WHERE id_pesanan = ? AND dibatalkan = 0");
    $stmt->execute([$idPesanan]);
    $terbayar = (int) $stmt->fetchColumn();

    if ($terbayar <= 0) {
        $status = 'belum_bayar';
    } elseif ($terbayar >= (int) $p['total']) {
        $status = 'lunas';
    } else {
        $status = 'dp_masuk';
    }
    $pdo->prepare("UPDATE pesanan SET status_bayar = ? WHERE id_pesanan = ?")
        ->execute([$status, $idPesanan]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log($e->getMessage());
    exit('Gagal membatalkan pembayaran.');
}

header('Location: detail.php?id=' . $idPesanan);
exit;