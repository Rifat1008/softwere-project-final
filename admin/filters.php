<?php
require_once __DIR__ . '/../includes/admin.php';
admin_boot();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? ''; $type = $_POST['type'] ?? '';
    if (isset(FILTER_DIMS[$type])) {
        if ($act === 'add') {
            $label = mb_substr(trim($_POST['label'] ?? ''), 0, 80);
            $slug = slugify($label) ?: 'opt' . substr(md5($label . microtime()), 0, 6);
            if ($label === '') flash_set('err', 'Enter a name for the new option.');
            else {
                $o = (int)$conn->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM filter_options WHERE type = '$type'")->fetch_row()[0];
                $st = $conn->prepare('INSERT IGNORE INTO filter_options (type, slug, label, sort_order) VALUES (?,?,?,?)');
                $st->bind_param('sssi', $type, $slug, $label, $o); $st->execute();
                flash_set($st->affected_rows ? 'ok' : 'err', $st->affected_rows ? "\"$label\" added. Tick it on products to make them appear for it." : 'That option already exists.');
            }
        } elseif ($act === 'save') {
            $id = (int)($_POST['id'] ?? 0); $label = mb_substr(trim($_POST['label'] ?? ''), 0, 80); $o = (int)($_POST['sort_order'] ?? 0);
            if ($id && $label !== '') { $st = $conn->prepare('UPDATE filter_options SET label = ?, sort_order = ? WHERE id = ? AND type = ?'); $st->bind_param('siis', $label, $o, $id, $type); $st->execute(); flash_set('ok', 'Saved.'); }
        } elseif ($act === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $st = $conn->prepare('SELECT slug FROM filter_options WHERE id = ? AND type = ?'); $st->bind_param('is', $id, $type); $st->execute();
            if ($row = $st->get_result()->fetch_assoc()) {
                $d = $conn->prepare('DELETE FROM product_tags WHERE type = ? AND slug = ?'); $d->bind_param('ss', $type, $row['slug']); $d->execute();
                $conn->query('DELETE FROM filter_options WHERE id = ' . $id);
                flash_set('ok', 'Option deleted and removed from all products.');
            }
        }
    }
    redirect('filters.php#' . $type);
}

$opts = []; 
foreach ($conn->query('SELECT o.*, (SELECT COUNT(*) FROM product_tags t WHERE t.type = o.type AND t.slug = o.slug) AS used FROM filter_options o ORDER BY o.sort_order, o.id') as $r) $opts[$r['type']][] = $r;
admin_header('Finder filters', 'filters');
?>
<p class="muted" style="margin-bottom:16px">These are the choices in the <b>Find a Gift</b> filter boxes. Add a new hobby, profession, occasion... here, then tick it on products.</p>
<div class="grid2">
<?php foreach (FILTER_DIMS as $type => $label): ?>
    <div class="card" id="<?= e($type) ?>">
        <h2><?= e($label) ?></h2>
        <?php foreach ($opts[$type] ?? [] as $o): ?>
            <form method="POST" class="optrow"><?= csrf_field() ?><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                <input type="text" name="label" value="<?= e($o['label']) ?>" maxlength="80" aria-label="Name">
                <input type="number" name="sort_order" value="<?= (int)$o['sort_order'] ?>" aria-label="Order" title="Order">
                <button class="btn-line sm" name="action" value="save">Save</button>
                <button class="btn-del sm" name="action" value="delete" onclick="return confirm('Delete this option? It will be removed from <?= (int)$o['used'] ?> product(s).')" title="<?= (int)$o['used'] ?> products use it">&times;</button>
            </form>
        <?php endforeach; ?>
        <form method="POST" class="inline" style="margin-top:14px"><?= csrf_field() ?><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="action" value="add">
            <input type="text" name="label" placeholder="New <?= e(strtolower($label)) ?> option" maxlength="80" required><button class="btn sm">Add</button></form>
    </div>
<?php endforeach; ?>
</div>
<?php admin_footer();
