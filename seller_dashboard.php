<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/products.php';
require_once __DIR__ . '/includes/product_lib.php';
require_seller();

$sid = (int)$_SESSION['seller_id'];
$stmt = $conn->prepare('SELECT shop_name, plan, requested_plan, status FROM sellers WHERE id = ?');
$stmt->bind_param('i', $sid); $stmt->execute();
$seller = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$seller || $seller['status'] === 'suspended') redirect('seller_logout.php');

$flash = $_SESSION['sflash'] ?? ''; unset($_SESSION['sflash']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        $errors[] = 'Session expired. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $st = $conn->prepare('SELECT image FROM products WHERE id = ? AND seller_id = ?');
        $st->bind_param('ii', $pid, $sid); $st->execute();
        if ($row = $st->get_result()->fetch_assoc()) {
            foreach (['product_tags', 'cart_items', 'wishlist'] as $t) $conn->query("DELETE FROM $t WHERE product_id = " . $pid);
            $conn->query('DELETE FROM products WHERE id = ' . $pid);
            delete_upload($row['image']);
            $_SESSION['sflash'] = 'Product removed.';
            redirect('seller_dashboard.php');
        }
    }
}

$stmt = $conn->prepare('SELECT id, name, category_label, price, image, emoji, status, reject_reason, is_active FROM products WHERE seller_id = ? ORDER BY id DESC');
$stmt->bind_param('i', $sid); $stmt->execute();
$mine = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

$limit = PLAN_LIMITS[$seller['plan']];
$used  = count(array_filter($mine, fn($p) => $p['status'] !== 'rejected'));
$st = $conn->prepare("SELECT COALESCE(SUM(oi.qty),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN products p ON p.id = oi.product_id
                      WHERE p.seller_id = ? AND o.status <> 'cancelled' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$st->bind_param('i', $sid); $st->execute();
$sold7 = (int)$st->get_result()->fetch_row()[0]; $st->close();
$canAdd = $seller['status'] === 'approved' && ($limit === null || $used < $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - UPOHAR</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/seller.css">
</head>
<body>
<div class="center">
    <div class="box wide">
        <div class="dash-head">
            <div>
                <h2>Welcome, <?= e($_SESSION['seller_name']) ?> 🎉</h2>
                <p class="alt" style="text-align:left;margin:6px 0 0">
                    <?= e($seller['shop_name']) ?> &middot; <span class="badge"><?= e(ucfirst($seller['plan'])) ?> plan</span>
                    <?php if ($seller['requested_plan'] !== $seller['plan']): ?><span class="badge"><?= e(ucfirst($seller['requested_plan'])) ?> requested - pending payment</span><?php endif; ?>
                </p>
            </div>
            <div class="top-actions">
                <a href="gifts.php?seller=<?= $sid ?>" class="ghost-link">View my shop</a>
                <a href="seller_logout.php" class="seller-login-btn">Logout</a>
            </div>
        </div>

        <?php if ($seller['status'] === 'pending'): ?>
            <div class="alert">Your shop is waiting for approval from the Upohar team. You will be able to add products as soon as it is approved.</div>
        <?php endif; ?>
        <?php if ($flash): ?><div class="alert ok"><?= e($flash) ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div><?php endif; ?>

        <div class="stats">
            <div><b><?= count(array_filter($mine, fn($p) => $p['status'] === 'approved')) ?></b><span>Live</span></div>
            <div><b><?= count(array_filter($mine, fn($p) => $p['status'] === 'pending')) ?></b><span>Waiting for review</span></div>
            <div><b><?= count(array_filter($mine, fn($p) => $p['status'] === 'rejected')) ?></b><span>Rejected</span></div>
            <div><b><?= $sold7 ?></b><span>Sold this week</span></div>
        </div>

        <div class="dash-head" style="margin-top:22px">
            <h3>My products (<?= $used ?><?= $limit !== null ? ' / ' . $limit : '' ?>)</h3>
            <?php if ($canAdd): ?><a href="seller_product.php" class="seller-login-btn">+ Add product</a>
            <?php elseif ($seller['status'] === 'approved'): ?><span class="muted-note">Plan limit reached. Delete a product or ask for an upgrade.</span><?php endif; ?>
        </div>

        <?php if (!$mine): ?>
            <p class="alt" style="text-align:left">No products yet.</p>
        <?php else: foreach ($mine as $p): ?>
            <div class="prod">
                <?php if ($p['image'] !== ''): ?><img src="<?= e(img_url($p['image'])) ?>" alt=""><?php else: ?><span class="ph"><?= e($p['emoji']) ?></span><?php endif; ?>
                <div>
                    <b><?= e($p['name']) ?></b> <span class="st <?= e($p['status']) ?>"><?= e($p['status'] === 'pending' ? 'Waiting for review' : ucfirst($p['status'])) ?></span><br>
                    <small><?= e($p['category_label']) ?> &middot; $<?= number_format((float)$p['price'], 2) ?></small>
                    <?php if ($p['status'] === 'rejected' && $p['reject_reason']): ?><br><small style="color:#a8231b">Reason: <?= e($p['reject_reason']) ?></small><?php endif; ?>
                </div>
                <a class="ghost-link" style="padding:6px 14px;font-size:.8rem" href="seller_product.php?id=<?= (int)$p['id'] ?>">Edit</a>
                <form method="POST" action="seller_dashboard.php" onsubmit="return confirm('Remove this product?')">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                    <button class="del" type="submit">Delete</button>
                </form>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>
</body>
</html>
