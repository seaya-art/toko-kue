<?php
require __DIR__ . '/../koneksi.php';
require __DIR__ . '/../auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $stmt = $pdo->prepare("SELECT id_pengguna, nama, password_hash, peran
                           FROM pengguna WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['id_pengguna'] = $user['id_pengguna'];
        $_SESSION['nama']        = $user['nama'];
        $_SESSION['peran']       = $user['peran'];
                $tujuan = isset($_SESSION['tujuan']) ? $_SESSION['tujuan'] : 'pesanan.php';
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
        exit;;
        exit;
    }
    $error = 'Username atau password salah.';
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
    <p><input name="username" placeholder="Username" required></p>
    <p><input name="password" type="password" placeholder="Password" required></p>
    <p><button type="submit">Masuk</button></p>
  </form>
</body>
</html>