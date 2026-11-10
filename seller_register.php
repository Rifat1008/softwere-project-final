<?php
require_once __DIR__ . '/includes/functions.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (seller_logged_in()) redirect('seller_dashboard.php');

$plans  = ['free', 'silver', 'gold'];
$plan   = $_POST['plan'] ?? $_GET['plan'] ?? 'free';
if (!in_array($plan, $plans, true)) $plan = 'free';

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => '', 'shop_name' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? null)) $errors[] = 'Session expired. Please try again.';

    foreach ($old as $k => $_) $old[$k] = trim($_POST[$k] ?? '');
    $password = $_POST['password'] ?? '';

    if ($old['full_name'] === '' || mb_strlen($old['full_name']) > 100) $errors[] = 'Enter your full name (max 100 characters).';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!preg_match('/^\+?[0-9\s-]{8,20}$/', $old['phone'])) $errors[] = 'Enter a valid phone number.';
    if ($old['shop_name'] === '' || mb_strlen($old['shop_name']) > 120) $errors[] = 'Enter your shop name (max 120 characters).';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

    if (!$errors) {
        $stmt = $conn->prepare('SELECT id FROM sellers WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $old['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows) $errors[] = 'A seller account with this email already exists.';
        $stmt->close();
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        // Everyone starts on the Free plan; the chosen plan is stored as requested_plan.
        $stmt = $conn->prepare("INSERT INTO sellers (full_name, email, phone, shop_name, password, requested_plan, status) VALUES (?,?,?,?,?,?, 'pending')");
        $stmt->bind_param('ssssss', $old['full_name'], $old['email'], $old['phone'], $old['shop_name'], $hash, $plan);
        $stmt->execute();
        $newId = (int)$stmt->insert_id;
        $stmt->close();

        // Paid plan -> go to payment page (bKash / Nagad)
        if ($plan !== 'free' && $newId) {
            $_SESSION['pay_seller_id'] = $newId;
            redirect('seller_payment.php');
        }
        redirect('seller_login.php?registered=1');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Registration - UPOHAR</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/seller.css">
</head>
<body>
<div class="center">
    <div class="box">
        <div class="brand"><h1>UPOHAR</h1><p>Become a Trusted Seller</p></div>
        <h2>Seller Registration</h2>

        <?php if ($errors): ?>
            <div class="alert"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
        <?php endif; ?>

        <form method="POST" action="seller_register.php">
            <?= csrf_field() ?>
            <input type="hidden" name="plan" value="<?= e($plan) ?>">
            <p class="alt" style="margin:0 0 16px">Selected plan: <span class="badge"><?= e(ucfirst($plan)) ?></span></p>
            <?php if ($plan !== 'free'): ?><p class="alt" style="margin:-6px 0 16px;color:#6b6540">After registering, you will be taken to the payment page.</p><?php endif; ?>

            <div class="input-group"><label>Full Name</label>
                <input type="text" name="full_name" value="<?= e($old['full_name']) ?>" maxlength="100" required></div>
            <div class="input-group"><label>Email Address</label>
                <input type="email" name="email" value="<?= e($old['email']) ?>" required></div>
            <div class="input-group"><label>Phone Number</label>
                <input type="text" name="phone" value="<?= e($old['phone']) ?>" required></div>
            <div class="input-group"><label>Shop Name</label>
                <input type="text" name="shop_name" value="<?= e($old['shop_name']) ?>" maxlength="120" required></div>
            <div class="input-group"><label>Password (8+ characters)</label>
                <input type="password" name="password" minlength="8" required></div>

            <button type="submit" class="btn"><?= $plan === 'free' ? 'Register Seller' : 'Register &amp; Continue to Payment' ?></button>
        </form>

        <p class="alt">Already have an account? <a href="seller_login.php">Login</a></p>
        <p class="alt"><a href="seller.php">&larr; Back to plans</a></p>
    </div>
</div>
</body>
</html>
