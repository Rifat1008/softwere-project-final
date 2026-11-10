<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$uid = (int)$_SESSION['user_id'];

$stmt = $conn->prepare('SELECT id, recipient, occasion, send_at FROM schedules WHERE user_id = ? AND send_at >= NOW() ORDER BY send_at ASC LIMIT 5');
$stmt->bind_param('i', $uid);
$stmt->execute();
$upcoming = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare('SELECT id, total, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 5');
$stmt->bind_param('i', $uid);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Upohar</title>
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;600;700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <div class="auth-logo"><img src="<?= e(site_logo()) ?>" alt="Upohar Logo"></div>
        <h2>Welcome, <?= e($_SESSION['full_name']) ?>! 🎉</h2>
        <p>You are successfully logged in to Upohar.</p>

        <div class="panel">
            <h3>Upcoming scheduled gifts</h3>
            <?php if (!$upcoming): ?>
                <p class="none">Nothing scheduled yet. <a href="schedule.php">Schedule a gift</a></p>
            <?php else: foreach ($upcoming as $s): ?>
                <div class="row">
                    <span>For <?= e($s['recipient']) ?><?= $s['occasion'] ? ' · ' . e($s['occasion']) : '' ?></span>
                    <b><?= e(date('d M Y, h:i A', strtotime($s['send_at']))) ?></b>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="panel">
            <h3>Recent orders</h3>
            <?php if (!$orders): ?>
                <p class="none">No orders yet. <a href="gifts.php">Visit the gift shop</a></p>
            <?php else: foreach ($orders as $o): ?>
                <div class="row">
                    <span>Order #<?= (int)$o['id'] ?> · <?= e(ucfirst($o['status'])) ?></span>
                    <b>$<?= number_format((float)$o['total'], 2) ?></b>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="btn-row">
            <a href="index.php" class="home-btn">Go to Home</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</body>
</html>
