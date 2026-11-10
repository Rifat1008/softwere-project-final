<?php
require_once __DIR__ . '/../includes/admin.php';
admin_boot();
const ORDER_STATUSES = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0); $s = $_POST['status'] ?? '';
    if ($id && in_array($s, ORDER_STATUSES, true)) {
        $st = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $st->bind_param('si', $s, $id); $st->execute();
        flash_set('ok', "Order #$id is now " . $s . '.');
    }
    redirect('orders.php' . (!empty($_POST['back']) ? '?status=' . urlencode($_POST['back']) : ''));
}

$status = in_array($_GET['status'] ?? '', ORDER_STATUSES, true) ? $_GET['status'] : 'all';
$page = max(1, (int)($_GET['page'] ?? 1)); $per = 20;
$w = $status === 'all' ? '1=1' : "o.status = '$status'";
$total = (int)$conn->query("SELECT COUNT(*) FROM orders o WHERE $w")->fetch_row()[0];
$pages = max(1, (int)ceil($total / $per)); $page = min($page, $pages);
$rows = $conn->query("SELECT o.*, u.full_name, u.email FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE $w ORDER BY o.id DESC LIMIT $per OFFSET " . (($page - 1) * $per))->fetch_all(MYSQLI_ASSOC);
$items = [];
if ($rows) {
    $in = implode(',', array_map('intval', array_column($rows, 'id')));
    foreach ($conn->query("SELECT order_id, name, qty, price FROM order_items WHERE order_id IN ($in)") as $it) $items[(int)$it['order_id']][] = $it;
}
admin_header('Orders', 'orders');
?>
<div class="tabs">
    <a href="?status=all" class="<?= $status === 'all' ? 'on' : '' ?>">All</a>
    <?php foreach (ORDER_STATUSES as $s): ?><a href="?status=<?= $s ?>" class="<?= $status === $s ? 'on' : '' ?>"><?= ucfirst($s) ?></a><?php endforeach; ?>
</div>
<div class="card tablewrap">
<table>
    <tr><th>#</th><th>Customer</th><th>Items</th><th class="num">Total</th><th>Date</th><th>Status</th></tr>
    <?php foreach ($rows as $o): ?>
        <tr>
            <td><b><?= (int)$o['id'] ?></b></td>
            <td><?= e($o['full_name'] ?? 'Deleted user') ?><br><small class="muted"><?= e($o['email'] ?? '') ?></small></td>
            <td class="small"><?php foreach ($items[(int)$o['id']] ?? [] as $it): ?><?= e($it['name']) ?> &times; <?= (int)$it['qty'] ?><br><?php endforeach; ?></td>
            <td class="num">$<?= number_format((float)$o['total'], 2) ?></td>
            <td class="nowrap"><?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?></td>
            <td>
                <form method="POST" class="actions"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="back" value="<?= e($status === 'all' ? '' : $status) ?>">
                    <select name="status" onchange="this.form.submit()" style="padding:6px 8px;border:1px solid #e5d9a8;border-radius:10px;font:inherit">
                        <?php foreach (ORDER_STATUSES as $s): ?><option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
                    </select><noscript><button class="btn-line sm">Save</button></noscript></form>
            </td>
        </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="muted">No orders here.</td></tr><?php endif; ?>
</table>
</div>
<?php pager($page, $pages, $status === 'all' ? [] : ['status' => $status]); admin_footer();
