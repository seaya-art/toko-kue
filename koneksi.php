<?php
$cfg = require __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', !empty($cfg['debug']) ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php-error.log');

$dsn = "mysql:host={$cfg['host']};dbname={$cfg['db']};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log('Koneksi database gagal: ' . $e->getMessage());
    exit('Koneksi database gagal.');
}