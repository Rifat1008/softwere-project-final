<?php
require_once __DIR__ . '/../includes/admin.php';
admin_boot();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$existing = null;
if ($id) {
    $st = $conn->prepare('SELECT * FROM products WHERE id = ?');
    $st->bind_param('i', $id); $st->execute();
    $existing = $st->get_result()->fetch_assoc(); $st->close();
    if (!$existing) { flash_set('err', 'Product not found.'); redirect('products.php'); }
}

$errors = [];
$sellers = $conn->query("SELECT id, shop_name FROM sellers WHERE status = 'approved' ORDER BY shop_name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = read_product_form($conn, $_POST, $_FILES, $existing, $errors);
    $status   = in_array($_POST['status'] ?? '', ['approved', 'pending', 'rejected'], true) ? $_POST['status'] : 'approved';
    $sellerId = (int)($_POST['seller_id'] ?? 0) ?: null;
    $active   = isset($_POST['is_active']) ? 1 : 0;

    if (!$errors) {
        $oldImage = $existing['image'] ?? null;
        $pid = save_product($conn, $d, $sellerId, $status, $id ?: null);
        $st = $conn->prepare('UPDATE products SET seller_id = ?, is_active = ? WHERE id = ?');
        $st->bind_param('iii', $sellerId, $active, $pid); $st->execute(); $st->close();
        if ($status === 'rejected') {
            $r = mb_substr(trim($_POST['reject_reason'] ?? ''), 0, 255);
            $st = $conn->prepare('UPDATE products SET reject_reason = ? WHERE id = ?');
            $st->bind_param('si', $r, $pid); $st->execute(); $st->close();
        }
        if ($oldImage && $d['new_upload'] && $oldImage !== $d['image']) delete_upload($oldImage);
        flash_set('ok', $id ? 'Product updated.' : 'Product added. It is live in the shop and the gift finder.');
        redirect('products.php');
    }
    if ($d['new_upload']) { delete_upload($d['image']); $d['image'] = $existing['image'] ?? ''; }   // do not keep orphan uploads
    $v = $d + ['status' => $status, 'seller_id' => $sellerId, 'is_active' => $active, 'reject_reason' => $_POST['reject_reason'] ?? ''];
} else {
    $v = $existing ?? ['name' => '', 'category' => '', 'price' => '', 'old_price' => '', 'badge' => '', 'description' => '', 'image' => '', 'status' => 'approved', 'seller_id' => null, 'is_active' => 1, 'reject_reason' => ''];
    $v['badge'] = $v['badge'] ?? '';
    $v['tags']  = $id ? product_tags($conn, $id) : [];
}

admin_header($id ? 'Edit product' : 'Add product', 'products');
?>
<?php if ($errors): ?><div class="flash err"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
<form method="POST" action="product_form.php<?= $id ? '?id=' . $id : '' ?>" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
    <?php product_form_fields($conn, $v, '../'); ?>

    <h3 class="pf-title">Publishing</h3>
    <div class="pf-grid">
        <label class="pf-field">Sold by
            <select name="seller_id">
                <option value="0">Upohar (official)</option>
                <?php foreach ($sellers as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)($v['seller_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['shop_name']) ?></option><?php endforeach; ?>
            </select></label>
        <label class="pf-field">Approval
            <select name="status">
                <?php foreach (['approved' => 'Approved (live)', 'pending' => 'Waiting for review', 'rejected' => 'Rejected'] as $k => $l): ?><option value="<?= $k ?>" <?= ($v['status'] ?? 'approved') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
            </select></label>
        <label class="pf-field pf-wide">Rejection reason (shown to the vendor, only if rejected)
            <input type="text" name="reject_reason" maxlength="255" value="<?= e($v['reject_reason'] ?? '') ?>"></label>
        <label class="pf-field"><span><input type="checkbox" name="is_active" value="1" <?= !empty($v['is_active']) ? 'checked' : '' ?>> Visible to customers</span></label>
    </div>

    <div class="actions" style="margin-top:22px">
        <button class="btn" type="submit"><?= $id ? 'Save changes' : 'Add product' ?></button>
        <a class="btn-line" href="products.php">Cancel</a>
    </div>
</form>
<?php admin_footer();
