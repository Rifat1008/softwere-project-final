<?php
require_once __DIR__ . '/../includes/functions.php';
if (admin_logged_in()) redirect('index.php');

$error = ''; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        $error = 'Session expired. Please try again.';
    } else {
        try {
            $st = $conn->prepare('SELECT id, name, password FROM admins WHERE email = ? LIMIT 1');
            $st->bind_param('s', $email); $st->execute();
            $a = $st->get_result()->fetch_assoc(); $st->close();
            if ($a && password_verify($_POST['password'] ?? '', $a['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$a['id'];
                $_SESSION['admin_name'] = $a['name'];
                $conn->query('UPDATE admins SET last_login = NOW() WHERE id = ' . (int)$a['id']);
                redirect('index.php');
            }
            usleep(600000);                      // slow down password guessing
            $error = 'Wrong email or password.';
        } catch (mysqli_sql_exception $ex) {
            $error = 'Admin tables are missing. Import database/upohar_admin.sql in phpMyAdmin first.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex">
    <title>Admin login - Upohar</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body style="background:linear-gradient(#fff,#FFF8DB)">
<div class="loginbox"><div class="card">
    <img src="../<?= e(site_logo()) ?>" alt="">
    <h1>Admin login</h1>
    <p class="muted small" style="margin-bottom:18px">Upohar website management</p>
    <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
    <form method="POST" action="login.php">
        <?= csrf_field() ?>
        <label class="f">Email<input type="email" name="email" value="<?= e($email) ?>" required autofocus></label>
        <label class="f">Password<input type="password" name="password" required></label>
        <button class="btn" type="submit">Login</button>
    </form>
</div></div>
</body>
</html>
