<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

function wajib_login() {
    if (empty($_SESSION['id_pengguna'])) {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $_SESSION['tujuan'] = $_SERVER['REQUEST_URI'];
        }
        header('Location: login.php');
        exit;
    }
}

function wajib_owner() {
    wajib_login();
    if ($_SESSION['peran'] !== 'owner') {
        http_response_code(403);
        exit('Akses ditolak.');
    }
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_cek() {
    $kirim = isset($_POST['csrf']) ? $_POST['csrf'] : '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $kirim)) {
        http_response_code(403);
        exit('Permintaan tidak valid.');
    }
}