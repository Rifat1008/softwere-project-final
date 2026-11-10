<?php
require_once __DIR__ . '/../includes/admin.php';
admin_boot();

$s = $conn->query("SELECT
    (SELECT COUNT(*) FROM users) AS users,
    (SELECT COUNT(*) FROM sellers WHERE status = 'approved') AS vendors,
    (SELECT COUNT(*) FROM products WHERE status = 'approved' AND is_active = 1) AS products,
    (SELECT COUNT(*) FROM orders) AS orders,
    (SELECT COALESCE(SUM(total),0) FROM orders WHERE status <> 'cancelled') AS revenue,
    (SELECT COUNT(*) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS orders7")->fetch_assoc();
$pend = admin_pending_counts($conn);

$pv = $conn->query("SELECT id, shop_name, full_name, requested_plan, created_at FROM sellers WHERE status = 'pending' ORDER BY id DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$pp = $conn->query("SELECT p.id, p.name, s.shop_name FROM products p LEFT JOIN sellers s ON s.id = p.seller_id WHERE p.status = 'pending' ORDER BY p.id DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$ro = $conn->query("SELECT o.id, o.total, o.status, o.created_at, u.full_name FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.id DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

admin_header('Dashboard', 'index');
?>
<div class="tiles">
    <a class="tile <?= $pend['vendors'] ? 'alert' : '' ?>" href="vendors.php?status=pending"><b><?= $pend['vendors'] ?></b><span>Vendors waiting</span></a>
    <a class="tile <?= $pend['products'] ? 'alert' : '' ?>" href="products.php?status=pending"><b><?= $pend['products'] ?></b><span>Products waiting</span></a>
    <a class="tile" href="orders.php"><b><?= (int)$s['orders'] ?></b><span>Orders (<?= (int)$s['orders7'] ?> this week)</span></a>
    <a class="tile" href="orders.php"><b>$<?= number_format((float)$s['revenue'], 0) ?></b><span>Revenue</span></a>
    <a class="tile" href="products.php"><b><?= (int)$s['products'] ?></b><span>Live products</span></a>
    <a class="tile" href="vendors.php"><b><?= (int)$s['vendors'] ?></b><span>Approved vendors</span></a>
    <a class="tile" href="users.php"><b><?= (int)$s['users'] ?></b><span>Customers</span></a>
</div>

<div class="grid2">
    <div class="card">
        <h2>Vendors waiting for approval</h2>
        <?php if (!$pv): ?><p class="muted">Nobody is waiting.</p><?php else: foreach ($pv as $v): ?>
            <div class="inline" style="justify-content:space-between;padding:8px 0;border-bottom:1px dashed var(--line)">
                <span><b><?= e($v['shop_name']) ?></b><br><small class="muted"><?= e($v['full_name']) ?> &middot; wants <?= e(ucfirst($v['requested_plan'])) ?></small></span>
                <form method="POST" action="vendors.php" style="flex:0 0 auto"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><button class="btn sm">Approve</button></form>
            </div>
        <?php endforeach; endif; ?>
    </div>
    <div class="card">
        <h2>Products waiting for approval</h2>
        <?php if (!$pp): ?><p class="muted">Nothing to review.</p><?php else: foreach ($pp as $p): ?>
            <div class="inline" style="justify-content:space-between;padding:8px 0;border-bottom:1px dashed var(--line)">
                <span><b><?= e($p['name']) ?></b><br><small class="muted"><?= e($p['shop_name'] ?? 'Upohar') ?></small></span>
                <span class="actions" style="flex:0 0 auto">
                    <a class="btn-line sm" href="product_form.php?id=<?= (int)$p['id'] ?>">Review</a>
                    <form method="POST" action="products.php"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn sm">Approve</button></form>
                </span>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<div class="card">
    <h2>Latest orders</h2>
    <div class="tablewrap"><table>
        <tr><th>#</th><th>Customer</th><th>Date</th><th>Status</th><th class="num">Total</th></tr>
        <?php foreach ($ro as $o): ?>
            <tr><td><a href="orders.php"><?= (int)$o['id'] ?></a></td><td><?= e($o['full_name'] ?? 'Deleted user') ?></td><td><?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?></td><td><?= status_pill($o['status']) ?></td><td class="num">$<?= number_format((float)$o['total'], 2) ?></td></tr>
        <?php endforeach; if (!$ro): ?><tr><td colspan="5" class="muted">No orders yet.</td></tr><?php endif; ?>
    </table></div>
</div>
<?php admin_footer();
