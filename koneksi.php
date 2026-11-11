<?php
$cfg = require __DIR__ . '/config.php';

$dsn = "mysql:host={$cfg['host']};dbname={$cfg['db']};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    exit('Koneksi database gagal.');
}