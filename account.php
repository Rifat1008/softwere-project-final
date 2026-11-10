<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/products.php';
require_login();

$uid  = (int)$_SESSION['user_id'];
$tabs = ['overview' => 'Overview', 'cart' => 'My cart', 'wishlist' => 'Wishlist', 'orders' => 'Orders', 'schedule' => 'Scheduled gifts', 'profile' => 'Profile'];
$tab  = $_GET['tab'] ?? 'overview';
if (!isset($tabs[$tab])) $tab = 'overview';

/* ---------- profile / password forms ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg = ['err', 'Something went wrong.'];
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        $msg = ['err', 'Session expired. Please try again.'];
    } elseif (($_POST['action'] ?? '') === 'name') {
        $name = trim($_POST['full_name'] ?? '');
        if ($name === '' || mb_strlen($name) > 100) {
            $msg = ['err', 'Enter your name (max 100 characters).'];
        } else {
            $st = $conn->prepare('UPDATE users SET full_name = ? WHERE id = ?');
            $st->bind_param('si', $name, $uid); $st->execute(); $st->close();
            $_SESSION['full_name'] = $name;
            $msg = ['ok', 'Your name was updated.'];
        }
    } elseif (($_POST['action'] ?? '') === 'password') {
        $cur = $_POST['current'] ?? ''; $new = $_POST['new'] ?? ''; $new2 = $_POST['new2'] ?? '';
        $st = $conn->prepare('SELECT password FROM users WHERE id = ?');
        $st->bind_param('i', $uid); $st->execute();
        $row = $st->get_result()->fetch_assoc(); $st->close();
        if (!$row || !password_verify($cur, $row['password'])) {
            $msg = ['err', 'Your current password is wrong.'];
        } elseif (strlen($new) < 8) {
            $msg = ['err', 'New password must be at least 8 characters.'];
        } elseif ($new !== $new2) {
            $msg = ['err', 'The two new passwords do not match.'];
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $st = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            $st->bind_param('si', $hash, $uid); $st->execute(); $st->close();
            session_regenerate_id(true);
            $msg = ['ok', 'Password changed.'];
        }
    }
    $_SESSION['flash'] = $msg;
    redirect('account.php?tab=profile');
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/* ---------- data ---------- */
$st = $conn->prepare('SELECT full_name, email, created_at FROM users WHERE id = ?');
$st->bind_param('i', $uid); $st->execute();
$me = $st->get_result()->fetch_assoc(); $st->close();
if (!$me) redirect('logout.php');

$counts = user_counts($conn, $uid);

function fetch_all_for(mysqli $conn, string $sql, int $uid): array {
    $st = $conn->prepare($sql);
    $st->bind_param('i', $uid); $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}

$orders = $wish = $sched = [];
$itemsByOrder = [];
if (in_array($tab, ['overview', 'orders'], true)) {
    $orders = fetch_all_for($conn, 'SELECT id, total, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC' . ($tab === 'overview' ? ' LIMIT 3' : ''), $uid);
    if ($orders) {
        $in  = implode(',', array_map('intval', array_column($orders, 'id')));
        $res = $conn->query("SELECT order_id, name, price, qty FROM order_items WHERE order_id IN ($in) ORDER BY id");
        foreach ($res as $r) $itemsByOrder[(int)$r['order_id']][] = $r;
    }
}
if ($tab === 'wishlist') {
    $wish = fetch_all_for($conn, 'SELECT p.* FROM wishlist w JOIN products p ON p.id = w.product_id WHERE w.user_id = ? AND ' . visible_sql('p') . ' ORDER BY w.id DESC', $uid);
}
if (in_array($tab, ['overview', 'schedule'], true)) {
    $sched = fetch_all_for($conn, 'SELECT * FROM schedules WHERE user_id = ? ' . ($tab === 'overview' ? 'AND send_at >= NOW() ORDER BY send_at ASC LIMIT 3' : 'ORDER BY send_at DESC'), $uid);
}

$first  = explode(' ', trim($me['full_name']))[0];
$since  = $me['created_at'] ? date('F Y', strtotime($me['created_at'])) : null;
$links  = [['Home', 'index.php'], ['Gift Shop', 'gifts.php'], ['Find a Gift', 'filter.php'], ['AI Suggestion', 'ai.php'], ['Schedule Gift', 'schedule.php']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf" content="<?= e(csrf_token()) ?>">
    <title>My account - Upohar</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/account.css?v=<?= @filemtime(__DIR__ . '/assets/css/account.css') ?>">
</head>
<body>

<?php require __DIR__ . '/includes/nav_top.php'; ?>

<main class="acc">

    <!-- profile header -->
    <section class="acc-hero">
        <span class="acc-avatar"><?= e(mb_strtoupper(mb_substr($me['full_name'], 0, 1))) ?></span>
        <div>
            <h1>Hi, <?= e($first) ?> 👋</h1>
            <p><?= e($me['email']) ?><?= $since ? ' &middot; Member since ' . e($since) : '' ?></p>
        </div>
        <a href="logout.php" class="btn-out">Logout</a>
    </section>

    <div class="acc-layout">

        <!-- tabs -->
        <nav class="acc-tabs" aria-label="Account sections">
            <?php foreach ($tabs as $key => $label): ?>
                <a href="account.php?tab=<?= e($key) ?>" class="<?= $tab === $key ? 'active' : '' ?>">
                    <?= e($label) ?>
                    <?php $map = ['cart' => 'cart', 'wishlist' => 'wishlist', 'orders' => 'orders', 'schedule' => 'schedules']; ?>
                    <?php if (isset($map[$key])): ?><em data-tabcount="<?= $map[$key] ?>"><?= (int)$counts[$map[$key]] ?></em><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <section class="acc-body">
        <?php if ($flash): ?><div class="note <?= $flash[0] === 'ok' ? 'ok' : 'err' ?>"><?= e($flash[1]) ?></div><?php endif; ?>

        <?php if ($tab === 'overview'): ?>
            <div class="tiles">
                <a class="tile" href="account.php?tab=cart"><b data-tabcount="cart"><?= (int)$counts['cart'] ?></b><span>Items in cart</span></a>
                <a class="tile" href="account.php?tab=wishlist"><b data-tabcount="wishlist"><?= (int)$counts['wishlist'] ?></b><span>Wishlist</span></a>
                <a class="tile" href="account.php?tab=orders"><b data-tabcount="orders"><?= (int)$counts['orders'] ?></b><span>Orders</span></a>
                <a class="tile" href="account.php?tab=schedule"><b data-tabcount="schedules"><?= (int)$counts['schedules'] ?></b><span>Upcoming gifts</span></a>
            </div>

            <div class="grid2">
                <div class="panel">
                    <h2>Recent orders</h2>
                    <?php if (!$orders): ?>
                        <p class="muted">No orders yet. <a href="gifts.php">Visit the gift shop</a></p>
                    <?php else: foreach ($orders as $o): ?>
                        <div class="line"><span>Order #<?= (int)$o['id'] ?> <i class="pill <?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></i></span><b>$<?= number_format((float)$o['total'], 2) ?></b></div>
                    <?php endforeach; endif; ?>
                </div>
                <div class="panel">
                    <h2>Next scheduled gifts</h2>
                    <?php if (!$sched): ?>
                        <p class="muted">Nothing scheduled. <a href="schedule.php">Schedule a gift</a></p>
                    <?php else: foreach ($sched as $s): ?>
                        <div class="line"><span>For <?= e($s['recipient']) ?><?= $s['occasion'] ? ' &middot; ' . e($s['occasion']) : '' ?></span><b><?= e(date('d M, h:i A', strtotime($s['send_at']))) ?></b></div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

        <?php elseif ($tab === 'cart'): ?>
            <div class="panel" id="cartRoot"><p class="muted">Loading your cart...</p></div>

        <?php elseif ($tab === 'wishlist'): ?>
            <?php if (!$wish): ?>
                <div class="panel"><p class="muted">Your wishlist is empty. Tap the heart on any gift in the <a href="gifts.php">gift shop</a>.</p></div>
            <?php else: ?>
                <div class="wish-grid" id="wishGrid">
                <?php foreach ($wish as $r): $p = product_for_js($r); ?>
                    <article class="wish" data-id="<?= $p['id'] ?>">
                        <div class="wish-img"><img src="<?= $p['image'] ?>" alt="<?= $p['name'] ?>" onerror="this.style.display='none'"></div>
                        <div class="wish-info">
                            <small><?= $p['categoryLabel'] ?></small>
                            <h3><?= $p['name'] ?></h3>
                            <b>$<?= number_format($p['price'], 2) ?></b>
                            <div class="wish-actions">
                                <button type="button" class="btn-gold js-wish-add">Add to cart</button>
                                <button type="button" class="btn-line js-wish-remove">Remove</button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($tab === 'orders'): ?>
            <?php if (!$orders): ?>
                <div class="panel"><p class="muted">No orders yet. <a href="gifts.php">Visit the gift shop</a></p></div>
            <?php else: foreach ($orders as $o): ?>
                <div class="panel order">
                    <div class="order-top">
                        <div><h2>Order #<?= (int)$o['id'] ?></h2><small><?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?></small></div>
                        <i class="pill <?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></i>
                    </div>
                    <?php foreach ($itemsByOrder[(int)$o['id']] ?? [] as $it): ?>
                        <div class="line"><span><?= e($it['name']) ?> &times; <?= (int)$it['qty'] ?></span><b>$<?= number_format((float)$it['price'] * (int)$it['qty'], 2) ?></b></div>
                    <?php endforeach; ?>
                    <div class="line total"><span>Total</span><b>$<?= number_format((float)$o['total'], 2) ?></b></div>
                </div>
            <?php endforeach; endif; ?>

        <?php elseif ($tab === 'schedule'): ?>
            <div class="tab-head"><h2>Scheduled gifts</h2><a class="btn-gold" href="schedule.php">+ Schedule a gift</a></div>
            <?php if (!$sched): ?>
                <div class="panel"><p class="muted">Nothing scheduled yet.</p></div>
            <?php else: foreach ($sched as $s): $past = strtotime($s['send_at']) < time(); ?>
                <div class="panel sched">
                    <div class="order-top">
                        <div><h2>For <?= e($s['recipient']) ?><?= $s['occasion'] ? ' &middot; ' . e($s['occasion']) : '' ?></h2>
                             <small><?= e(date('l, d M Y, h:i A', strtotime($s['send_at']))) ?></small></div>
                        <i class="pill <?= $past ? 'delivered' : 'pending' ?>"><?= $past ? 'Passed' : 'Upcoming' ?></i>
                    </div>
                    <p class="muted"><?= $s['message'] ? '&ldquo;' . e($s['message']) . '&rdquo; &middot; ' : '' ?>
                        Reminder: <?= $s['remind_days'] ? (int)$s['remind_days'] . ' day(s) before' : 'on the day' ?>
                        (<?= e(implode(', ', array_filter([$s['via_app'] ? 'app' : '', $s['via_email'] ? 'email' : '', $s['via_sms'] ? 'SMS' : '']))) ?>)</p>
                    <a class="link" href="<?= e($s['gift_url']) ?>" target="_blank" rel="noopener noreferrer">Open gift link &rarr;</a>
                </div>
            <?php endforeach; endif; ?>

        <?php else: /* profile */ ?>
            <div class="grid2">
                <form class="panel" method="POST" action="account.php?tab=profile">
                    <h2>Your details</h2>
                    <?= csrf_field() ?><input type="hidden" name="action" value="name">
                    <label>Full name<input type="text" name="full_name" value="<?= e($me['full_name']) ?>" maxlength="100" required></label>
                    <label>Email<input type="email" value="<?= e($me['email']) ?>" disabled></label>
                    <button class="btn-gold" type="submit">Save name</button>
                </form>
                <form class="panel" method="POST" action="account.php?tab=profile">
                    <h2>Change password</h2>
                    <?= csrf_field() ?><input type="hidden" name="action" value="password">
                    <label>Current password<input type="password" name="current" autocomplete="current-password" required></label>
                    <label>New password (8+ characters)<input type="password" name="new" minlength="8" autocomplete="new-password" required></label>
                    <label>Repeat new password<input type="password" name="new2" minlength="8" autocomplete="new-password" required></label>
                    <button class="btn-gold" type="submit">Change password</button>
                </form>
            </div>
        <?php endif; ?>
        </section>
    </div>
</main>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="assets/js/account.js?v=<?= @filemtime(__DIR__ . '/assets/js/account.js') ?>"></script>
</body>
</html>
