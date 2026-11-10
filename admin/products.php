<?php
require_once __DIR__ . '/../includes/admin.php';
admin_boot();

/* ---------- actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';
    if ($id) {
        if ($act === 'approve') {
            $st = $conn->prepare("UPDATE products SET status = 'approved', reject_reason = NULL WHERE id = ?");
            $st->bind_param('i', $id); $st->execute();
            flash_set('ok', 'Product approved. It is now live in the shop and the gift finder.');
        } elseif ($act === 'reject') {
            $reason = mb_substr(trim($_POST['reason'] ?? ''), 0, 255);
            $st = $conn->prepare("UPDATE products SET status = 'rejected', reject_reason = ? WHERE id = ?");
            $st->bind_param('si', $reason, $id); $st->execute();
            flash_set('ok', 'Product rejected.');
        } elseif ($act === 'toggle') {
            $conn->query('UPDATE products SET is_active = 1 - is_active WHERE id = ' . $id);
            flash_set('ok', 'Visibility changed.');
        } elseif ($act === 'delete') {
            $img = $conn->query('SELECT image FROM products WHERE id = ' . $id)->fetch_row()[0] ?? null;
            foreach (['product_tags', 'cart_items', 'wishlist'] as $t) $conn->query("DELETE FROM $t WHERE product_id = $id");
            $conn->query('DELETE FROM products WHERE id = ' . $id);
            delete_upload($img);
            flash_set('ok', 'Product deleted.');
        }
    }
    redirect('products.php' . (!empty($_POST['back']) ? '?' . $_POST['back'] : ''));
}

/* ---------- list ---------- */
$status = $_GET['status'] ?? 'all';
$q      = trim($_GET['q'] ?? '');
$seller = (int)($_GET['seller'] ?? 0);
$where = ['1=1']; $types = ''; $vals = [];
if ($status === 'hidden') $where[] = 'p.is_active = 0';
elseif (in_array($status, ['pending', 'approved', 'rejected'], true)) { $where[] = 'p.status = ?'; $types .= 's'; $vals[] = $status; }
else $status = 'all';
if ($q !== '')  { $where[] = 'p.name LIKE ?'; $types .= 's'; $vals[] = '%' . addcslashes($q, '%_\\') . '%'; }
if ($seller)    { $where[] = 'p.seller_id = ?'; $types .= 'i'; $vals[] = $seller; }
$w = implode(' AND ', $where);

$cnt = $conn->prepare("SELECT COUNT(*) FROM products p WHERE $w");
if ($vals) $cnt->bind_param($types, ...$vals);
$cnt->execute(); $total = (int)$cnt->get_result()->fetch_row()[0]; $cnt->close();
$per = 25; $pages = max(1, (int)ceil($total / $per)); $page = min($pages, max(1, (int)($_GET['page'] ?? 1)));

$st = $conn->prepare("SELECT p.*, s.shop_name, (SELECT COUNT(*) FROM product_tags t WHERE t.product_id = p.id) AS tagc
                      FROM products p LEFT JOIN sellers s ON s.id = p.seller_id WHERE $w ORDER BY (p.status = 'pending') DESC, p.id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
if ($vals) $st->bind_param($types, ...$vals);
$st->execute(); $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

$c = $conn->query("SELECT COUNT(*) a, SUM(status='pending') p, SUM(status='approved') ok, SUM(status='rejected') r, SUM(is_active=0) h FROM products")->fetch_assoc();
$params = array_filter(['status' => $status === 'all' ? '' : $status, 'q' => $q, 'seller' => $seller ?: '']);
admin_header('Products', 'products');
?>
<div class="bar">
    <div class="tabs" style="margin:0">
        <?php foreach (['all' => ['All', $c['a']], 'pending' => ['Waiting', $c['p']], 'approved' => ['Approved', $c['ok']], 'rejected' => ['Rejected', $c['r']], 'hidden' => ['Hidden', $c['h']]] as $k => [$l, $n]): ?>
            <a href="?status=<?= $k ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= $l ?><em><?= (int)$n ?></em></a>
        <?php endforeach; ?>
    </div>
    <div class="actions">
        <form method="GET"><input type="hidden" name="status" value="<?= e($status) ?>"><input type="text" name="q" value="<?= e($q) ?>" placeholder="Search products"><button class="btn-line sm">Search</button></form>
        <a class="btn" href="product_form.php">+ Add product</a>
    </div>
</div>
<?php if ($seller): ?><p class="muted" style="margin-bottom:12px">Showing one vendor's products. <a href="products.php" style="color:var(--gold-deep);font-weight:700">Show all</a></p><?php endif; ?>

<div class="card tablewrap">
<table>
    <tr><th></th><th>Product</th><th>Sold by</th><th>Category</th><th class="num">Price</th><th>Status</th><th>Actions</th></tr>
    <?php foreach ($rows as $p): $back = http_build_query(array_merge($params, ['page' => $page])); ?>
        <tr>
            <td><?php if ($p['image'] !== ''): ?><img class="thumb" src="<?= e(img_url($p['image'], '../')) ?>" alt="" loading="lazy"><?php else: ?><span class="thumb"><?= e($p['emoji']) ?></span><?php endif; ?></td>
            <td><b><?= e($p['name']) ?></b><br><small class="muted">#<?= (int)$p['id'] ?> &middot; <?= (int)$p['tagc'] ?> tags<?= $p['image'] === '' ? ' &middot; <span style="color:var(--red)">no photo</span>' : '' ?></small>
                <?php if ($p['status'] === 'rejected' && $p['reject_reason']): ?><br><small style="color:var(--red)">Reason: <?= e($p['reject_reason']) ?></small><?php endif; ?></td>
            <td><?= e($p['shop_name'] ?? 'Upohar') ?></td>
            <td><?= e($p['category_label']) ?></td>
            <td class="num">$<?= number_format((float)$p['price'], 2) ?></td>
            <td><?= status_pill($p['status']) ?><?= $p['is_active'] ? '' : ' <span class="pill hidden">Hidden</span>' ?></td>
            <td><div class="actions">
                <a class="btn-line sm" href="product_form.php?id=<?= (int)$p['id'] ?>">Edit</a>
                <?php if ($p['status'] !== 'approved'): ?>
                    <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>"><button class="btn sm">Approve</button></form>
                <?php endif; ?>
                <?php if ($p['status'] !== 'rejected'): ?>
                    <form method="POST" data-reject><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="reason" value=""><input type="hidden" name="back" value="<?= e($back) ?>"><button class="btn-line sm">Reject</button></form>
                <?php endif; ?>
                <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>"><button class="btn-line sm"><?= $p['is_active'] ? 'Hide' : 'Show' ?></button></form>
                <form method="POST" data-confirm="Delete this product for good?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>"><button class="btn-del sm">Delete</button></form>
            </div></td>
        </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="muted">No products here.</td></tr><?php endif; ?>
</table>
</div>
<?php pager($page, $pages, $params); admin_footer();
