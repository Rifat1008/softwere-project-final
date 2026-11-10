<?php
require_once __DIR__ . '/../includes/admin.php';
admin_boot();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($act === 'add') {
        $errors = [];
        $path = isset($_FILES['image']) ? save_image_upload($_FILES['image'], 'banners', $errors) : null;
        $alt = mb_substr(trim($_POST['alt'] ?? ''), 0, 150);
        $link = trim($_POST['link'] ?? '');
        if ($link !== '' && !preg_match('#^(https?://|[a-z0-9_./?=&-]+\.php)#i', $link)) $errors[] = 'The link must start with http(s):// or be a page like gifts.php.';
        if (!$path && !$errors) $errors[] = 'Choose a banner image.';
        if ($errors) { if ($path) delete_upload($path); flash_set('err', implode(' ', $errors)); }
        else {
            $order = (int)$conn->query('SELECT COALESCE(MAX(sort_order),0) + 1 FROM banners')->fetch_row()[0];
            $st = $conn->prepare('INSERT INTO banners (image, alt, link, sort_order) VALUES (?,?,?,?)');
            $st->bind_param('sssi', $path, $alt, $link, $order); $st->execute();
            flash_set('ok', 'Banner added. It is on the home page now.');
        }
    } elseif ($id && $act === 'toggle') {
        $conn->query('UPDATE banners SET is_active = 1 - is_active WHERE id = ' . $id); flash_set('ok', 'Banner updated.');
    } elseif ($id && $act === 'order') {
        $o = (int)($_POST['sort_order'] ?? 0);
        $st = $conn->prepare('UPDATE banners SET sort_order = ? WHERE id = ?'); $st->bind_param('ii', $o, $id); $st->execute(); flash_set('ok', 'Order saved.');
    } elseif ($id && $act === 'delete') {
        $img = $conn->query('SELECT image FROM banners WHERE id = ' . $id)->fetch_row()[0] ?? null;
        $conn->query('DELETE FROM banners WHERE id = ' . $id); delete_upload($img); flash_set('ok', 'Banner deleted.');
    }
    redirect('banners.php');
}
$rows = $conn->query('SELECT * FROM banners ORDER BY sort_order, id')->fetch_all(MYSQLI_ASSOC);
admin_header('Home banners', 'banners');
?>
<div class="card">
    <h2>Add a banner <small>Best size 1600 x 900 px (16:9). It appears in the slider on the home page.</small></h2>
    <form method="POST" enctype="multipart/form-data" class="inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <label class="f">Image<input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required></label>
        <label class="f">Short description (for screen readers)<input type="text" name="alt" maxlength="150"></label>
        <label class="f">Link when clicked (optional)<input type="text" name="link" placeholder="gifts.php"></label>
        <button class="btn" type="submit">Add banner</button>
    </form>
</div>
<div class="card">
    <h2>Banners <small>The slider shows active banners from the smallest order number.</small></h2>
    <?php foreach ($rows as $b): ?>
        <div class="banner-row">
            <img src="<?= e(img_url($b['image'], '../')) ?>" alt="">
            <div>
                <b><?= e($b['alt'] ?: 'Banner #' . $b['id']) ?></b> <?= $b['is_active'] ? status_pill('approved') : '<span class="pill hidden">Hidden</span>' ?>
                <?php if ($b['link']): ?><br><small class="muted">Links to <?= e($b['link']) ?></small><?php endif; ?>
                <form method="POST" class="actions" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="action" value="order"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                    <label class="small muted">Order <input type="number" name="sort_order" value="<?= (int)$b['sort_order'] ?>" style="width:70px;padding:6px 8px;border:1px solid #e5d9a8;border-radius:10px"></label>
                    <button class="btn-line sm">Save</button></form>
            </div>
            <div class="actions">
                <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn-line sm"><?= $b['is_active'] ? 'Hide' : 'Show' ?></button></form>
                <form method="POST" data-confirm="Delete this banner?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn-del sm">Delete</button></form>
            </div>
        </div>
    <?php endforeach; if (!$rows): ?><p class="muted">No banners yet. The home page shows the default banners until you add one.</p><?php endif; ?>
</div>
<?php admin_footer();
