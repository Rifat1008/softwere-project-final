<?php
require_once __DIR__ . '/../includes/admin.php';
admin_boot();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    $label = mb_substr(trim($_POST['label'] ?? ''), 0, 60); $emoji = mb_substr(trim($_POST['emoji'] ?? ''), 0, 8) ?: '🎁';
    if ($act === 'add') {
        $slug = slugify($label) ?: 'cat' . substr(md5($label . microtime()), 0, 6);
        if ($label === '') flash_set('err', 'Enter a category name.');
        else {
            $o = (int)$conn->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM categories')->fetch_row()[0];
            $st = $conn->prepare('INSERT IGNORE INTO categories (slug, label, emoji, sort_order) VALUES (?,?,?,?)'); $st->bind_param('sssi', $slug, $label, $emoji, $o); $st->execute();
            flash_set($st->affected_rows ? 'ok' : 'err', $st->affected_rows ? 'Category added.' : 'That category already exists.');
        }
    } elseif ($act === 'save' && $id && $label !== '') {
        $st = $conn->prepare('UPDATE categories SET label = ?, emoji = ? WHERE id = ?'); $st->bind_param('ssi', $label, $emoji, $id); $st->execute();
        $slug = $conn->query('SELECT slug FROM categories WHERE id = ' . $id)->fetch_row()[0];
        $u = $conn->prepare('UPDATE products SET category_label = ? WHERE category = ?'); $u->bind_param('ss', $label, $slug); $u->execute();   // keep products in sync
        flash_set('ok', 'Category saved.');
    } elseif ($act === 'delete' && $id) {
        $slug = $conn->query('SELECT slug FROM categories WHERE id = ' . $id)->fetch_row()[0] ?? '';
        $n = $conn->prepare('SELECT COUNT(*) FROM products WHERE category = ?'); $n->bind_param('s', $slug); $n->execute();
        if ((int)$n->get_result()->fetch_row()[0] > 0) flash_set('err', 'Move or delete the products in this category first.');
        else { $conn->query('DELETE FROM categories WHERE id = ' . $id); flash_set('ok', 'Category deleted.'); }
    }
    redirect('categories.php');
}
$rows = $conn->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category = c.slug) AS n FROM categories c ORDER BY c.sort_order, c.id')->fetch_all(MYSQLI_ASSOC);
admin_header('Categories', 'categories');
?>
<div class="card">
    <h2>Product categories <small>Used by the gift shop buttons and the Find-a-Gift category filter.</small></h2>
    <?php foreach ($rows as $c): ?>
        <form method="POST" class="optrow" style="grid-template-columns:60px 1fr 90px auto auto"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <input type="text" name="emoji" value="<?= e($c['emoji']) ?>" maxlength="8" aria-label="Emoji" style="text-align:center">
            <input type="text" name="label" value="<?= e($c['label']) ?>" maxlength="60" aria-label="Name">
            <span class="small muted"><?= (int)$c['n'] ?> products</span>
            <button class="btn-line sm" name="action" value="save">Save</button>
            <button class="btn-del sm" name="action" value="delete" onclick="return confirm('Delete this category?')">&times;</button>
        </form>
    <?php endforeach; ?>
    <form method="POST" class="inline" style="margin-top:16px"><?= csrf_field() ?><input type="hidden" name="action" value="add">
        <input type="text" name="emoji" placeholder="🎁" maxlength="8" style="max-width:80px"><input type="text" name="label" placeholder="New category name" maxlength="60" required><button class="btn sm">Add category</button></form>
</div>
<?php admin_footer();
