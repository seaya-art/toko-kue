<?php
require __DIR__ . '/../koneksi.php';
require __DIR__ . '/../auth.php';

if (!empty($_SESSION['id_pengguna'])) {
    header('Location: pesanan.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();

    $username = mb_substr(trim($_POST['username'] ?? ''), 0, 50);
    $password = $_POST['password'] ?? '';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '';

    // terlalu banyak percobaan gagal dalam 15 menit terakhir?
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(username = ?), 0) AS per_user, COUNT(*) AS per_ip
                           FROM login_gagal
                           WHERE ip = ? AND waktu > (NOW() - INTERVAL 15 MINUTE)");
    $stmt->execute([$username, $ip]);
    $c = $stmt->fetch();

    if ((int) $c['per_user'] >= 5 || (int) $c['per_ip'] >= 20) {
        $error = 'Terlalu banyak percobaan. Coba lagi 15 menit lagi.';
    } else {
        $stmt = $pdo->prepare("SELECT id_pengguna, nama, password_hash, peran
                               FROM pengguna WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // tetap hitung hash walau user tidak ada, supaya waktu responsnya sama
        $hash  = $user ? $user['password_hash'] : password_hash('dummy', PASSWORD_DEFAULT);
        $cocok = password_verify($password, $hash) && $user;

        if ($cocok) {
            $pdo->prepare("DELETE FROM login_gagal WHERE username = ? AND ip = ?")
                ->execute([$username, $ip]);

            session_regenerate_id(true);
            $_SESSION['id_pengguna'] = $user['id_pengguna'];
            $_SESSION['nama']        = $user['nama'];
            $_SESSION['peran']       = $user['peran'];

            $tujuan = $_SESSION['tujuan'] ?? 'pesanan.php';
            unset($_SESSION['tujuan']);

            // hanya boleh alamat di situs ini (cegah open redirect)
            $aman = $tujuan === 'pesanan.php'
                 || (strpos($tujuan, '/') === 0
                     && strpos($tujuan, '//') !== 0
                     && strpos($tujuan, '\\') === false);
            if (!$aman) {
                $tujuan = 'pesanan.php';
            }
            header('Location: ' . $tujuan);
            exit;
        }

        $pdo->prepare("INSERT INTO login_gagal (username, ip) VALUES (?, ?)")
            ->execute([$username, $ip]);
        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login</title>
</head>
<body>
  <h1>Login</h1>
  <?php if ($error): ?><p style="color:red"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <p><input name="username" placeholder="Username" autocomplete="username" required></p>
    <p><input name="password" type="password" placeholder="Password" autocomplete="current-password" required></p>
    <p><button type="submit">Masuk</button></p>
  </form>
</body>
</html>