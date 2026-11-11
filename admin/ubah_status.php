<?php
require __DIR__ . '/../koneksi.php';
require __DIR__ . '/../auth.php';
wajib_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pesanan.php');
    exit;
}
csrf_cek();

$alur   = require __DIR__ . '/../alur.php';
$id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$baru   = isset($_POST['status']) ? $_POST['status'] : '';
$alasan = isset($_POST['alasan']) ? trim($_POST['alasan']) : '';

$stmt = $pdo->prepare("SELECT status_pesanan FROM pesanan WHERE id_pesanan = ?");
$stmt->execute([$id]);
$pesanan = $stmt->fetch();
if (!$pesanan) {
    http_response_code(404);
    exit('Pesanan tidak ditemukan.');
}

$boleh = isset($alur[$pesanan['status_pesanan']]) ? $alur[$pesanan['status_pesanan']] : [];
if (!in_array($baru, $boleh, true)) {
    http_response_code(400);
    exit('Perubahan status tidak diizinkan.');
}

if ($baru === 'dibatalkan') {
    wajib_owner();
    if ($alasan === '') {
        exit('Alasan pembatalan wajib diisi.');
    }
    $stmt = $pdo->prepare("UPDATE pesanan SET status_pesanan = ?, alasan_batal = ? WHERE id_pesanan = ?");
    $stmt->execute([$baru, $alasan, $id]);
} else {
    $stmt = $pdo->prepare("UPDATE pesanan SET status_pesanan = ? WHERE id_pesanan = ?");
    $stmt->execute([$baru, $id]);
}

header('Location: detail.php?id=' . $id);
exit;