<?php
require_once __DIR__ . '/includes/functions.php';

if (seller_logged_in()) redirect('seller_dashboard.php');

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? null)) $errors[] = 'Session expired. Please try again.';

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$errors) {
        $stmt = $conn->prepare('SELECT id, full_name, password, status FROM sellers WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $seller = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$seller || !password_verify($password, $seller['password'])) {
            $errors[] = 'Invalid email or password.';
        } elseif ($seller['status'] === 'suspended') {
            $errors[] = 'This shop account is suspended. Please contact Upohar support.';
        } else {
            session_regenerate_id(true);
            $_SESSION['seller_id']   = (int)$seller['id'];
            $_SESSION['seller_name'] = $seller['full_name'];
            redirect('seller_dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Login - UPOHAR</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/seller.css">
</head>
<body>
<div class="center">
    <div class="box">
        <div class="brand"><h1>UPOHAR</h1><p>Seller Login Portal</p></div>
        <h2>Seller Login</h2>

        <?php if (isset($_GET['registered'])): ?>
            <div class="alert ok">Registration successful! Our team will review your shop. You can log in now and you will be able to add products once it is approved.</div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
        <?php endif; ?>

        <form method="POST" action="seller_login.php">
            <?= csrf_field() ?>
            <div class="input-group"><label>Email</label>
                <input type="email" name="email" value="<?= e($email) ?>" required></div>
            <div class="input-group"><label>Password</label>
                <input type="password" name="password" required></div>
            <button type="submit" class="btn">Login</button>
        </form>

        <p class="alt">New seller? <a href="seller_register.php">Register</a></p>
        <p class="alt"><a href="seller.php">&larr; Back to plans</a></p>
    </div>
</div>
</body>
</html>
