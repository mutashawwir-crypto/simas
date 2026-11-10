<?php
require_once __DIR__ . '/config/koneksi.php';

if (is_login()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!csrf_valid()) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $user = db_one('SELECT id, nama, username, password, role FROM users WHERE username = ? LIMIT 1', 's', [$username]);
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'       => (int)$user['id'],
                'nama'     => $user['nama'],
                'username' => $user['username'],
                'role'     => $user['role'],
            ];
            header('Location: dashboard.php');
            exit;
        }
        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk | SIMAS <?= e(NAMA_MASJID) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-head">
            <div class="brand-mark">S</div>
            <h1>SIMAS</h1>
            <p>Sistem Informasi Manajemen Masjid<br><?= e(NAMA_MASJID) ?></p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="index.php" autocomplete="off">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= e($username) ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-block">Masuk</button>
        </form>

        <div class="demo-box">
            <b>Akun contoh</b><br>
            Admin: <b>admin</b> / admin123<br>
            Bendahara: <b>bendahara</b> / bendahara123<br>
            Jamaah: <b>jamaah</b> / jamaah123
        </div>
    </div>
</body>
</html>
