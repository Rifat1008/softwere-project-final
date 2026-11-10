<?php
require_once __DIR__ . '/../includes/admin.php';
admin_boot();

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1)); $per = 25;
$where = $q !== '' ? 'WHERE u.full_name LIKE ? OR u.email LIKE ?' : '';
$like = '%' . addcslashes($q, '%_\\') . '%';

$cnt = $conn->prepare("SELECT COUNT(*) FROM users u $where");
if ($q !== '') $cnt->bind_param('ss', $like, $like);
$cnt->execute(); $total = (int)$cnt->get_result()->fetch_row()[0];
$pages = max(1, (int)ceil($total / $per)); $page = min($page, $pages);

$st = $conn->prepare("SELECT u.id, u.full_name, u.email, u.created_at, u.last_login,
        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders,
        (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.user_id = u.id AND o.status <> 'cancelled') AS spent
        FROM users u $where ORDER BY u.id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
if ($q !== '') $st->bind_param('ss', $like, $like);
$st->execute(); $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);

admin_header('Customers', 'users');
?>
<div class="bar"><span class="muted"><?= $total ?> customer<?= $total === 1 ? '' : 's' ?></span>
    <form method="GET"><input type="text" name="q" value="<?= e($q) ?>" placeholder="Search name or email"><button class="btn-line sm">Search</button></form></div>
<div class="card tablewrap">
<table>
    <tr><th>Name</th><th>Email</th><th>Joined</th><th>Last login</th><th class="num">Orders</th><th class="num">Spent</th></tr>
    <?php foreach ($rows as $u): ?>
        <tr><td><b><?= e($u['full_name']) ?></b></td><td><?= e($u['email']) ?></td>
            <td class="nowrap"><?= $u['created_at'] ? e(date('d M Y', strtotime($u['created_at']))) : '-' ?></td>
            <td class="nowrap"><?= $u['last_login'] ? e(date('d M Y, h:i A', strtotime($u['last_login']))) : '-' ?></td>
            <td class="num"><?= (int)$u['orders'] ?></td><td class="num">$<?= number_format((float)$u['spent'], 2) ?></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="muted">No customers found.</td></tr><?php endif; ?>
</table>
</div>
<?php pager($page, $pages, $q !== '' ? ['q' => $q] : []); admin_footer();
