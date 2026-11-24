<?php
function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function rp($n) {
    return 'Rp' . number_format((int) $n, 0, ',', '.');
}

// nama file foto harus aman dan benar-benar ada di uploads/menu
function url_foto($nama) {
    if (!is_string($nama) || !preg_match('/^[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)$/i', $nama)) {
        return null;
    }
    return is_file(__DIR__ . '/uploads/menu/' . $nama) ? 'uploads/menu/' . $nama : null;
}

// sumber pesanan otomatis dari link: ?dari=ig atau ?dari=wa
function catat_sumber() {
    $dari = $_GET['dari'] ?? '';
    if (in_array($dari, ['ig', 'wa'], true)) {
        $_SESSION['sumber'] = $dari;
    } elseif (empty($_SESSION['sumber'])) {
        $_SESSION['sumber'] = 'website';
    }
}

function jumlah_keranjang() {
    return array_sum($_SESSION['keranjang'] ?? []);
}

function normalisasi_wa($s) {
    $wa = preg_replace('/\D/', '', (string) $s);
    if (str_starts_with($wa, '0')) {
        $wa = '62' . substr($wa, 1);
    }
    return $wa;
}