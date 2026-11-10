<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/products.php';
require_once __DIR__ . '/includes/product_lib.php';

$opts = filter_options($conn);          // gender / age / occasion / hobby / profession / relation (managed in Admin > Filters)
$cats = categories_all($conn);

/* ---- read the filters from the URL; anything that is not a real option is ignored ---- */
$sel = [];
foreach (FILTER_DIMS as $type => $label) {
    $v = (string)($_GET[$type] ?? '');
    $sel[$type] = isset($opts[$type][$v]) ? $v : '';
}
$budget = isset(BUDGETS[$_GET['budget'] ?? '']) ? $_GET['budget'] : '';
$cat    = isset($cats[$_GET['cat'] ?? '']) ? $_GET['cat'] : '';
$q      = trim((string)($_GET['q'] ?? ''));
$sort   = in_array($_GET['sort'] ?? '', ['price_asc', 'price_desc', 'new'], true) ? $_GET['sort'] : '';

/* ---- build the query: a gift matches when it carries EVERY chosen tag ---- */
$where = [visible_sql('p')]; $types = ''; $vals = [];
foreach ($sel as $type => $slug) {
    if ($slug === '') continue;
    $where[] = 'EXISTS (SELECT 1 FROM product_tags t WHERE t.product_id = p.id AND t.type = ? AND t.slug = ?)';
    $types .= 'ss'; array_push($vals, $type, $slug);
}
if ($budget !== '') {
    [, $min, $max] = BUDGETS[$budget];
    $where[] = 'p.price >= ?'; $types .= 'd'; $vals[] = $min;
    if ($max !== null) { $where[] = 'p.price < ?'; $types .= 'd'; $vals[] = $max; }
}
if ($cat !== '') { $where[] = 'p.category = ?'; $types .= 's'; $vals[] = $cat; }
if ($q !== '')   { $where[] = '(p.name LIKE ? OR p.description LIKE ?)'; $types .= 'ss'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($vals, $like, $like); }
$whereSql = implode(' AND ', $where);

$run = function (string $sql, string $t, array $v) use ($conn) {
    $st = $conn->prepare($sql);
    if ($v) $st->bind_param($t, ...$v);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
};

$n       = (int)$run("SELECT COUNT(*) c FROM products p WHERE $whereSql", $types, $vals)[0]['c'];
$perPage = 24;
$pages   = max(1, (int)ceil($n / $perPage));
$page    = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$order   = ['price_asc' => 'p.price ASC', 'price_desc' => 'p.price DESC', 'new' => 'p.id DESC'][$sort] ?? 'p.id ASC';
$gifts   = $run("SELECT p.id, p.name, p.image, p.price, p.old_price, p.category_label, p.emoji FROM products p WHERE $whereSql ORDER BY $order LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $types, $vals);

$total = (int)$conn->query('SELECT COUNT(*) FROM products p WHERE ' . visible_sql('p'))->fetch_row()[0];
$links = [['Home', 'index.php'], ['Gift Shop', 'gifts.php'], ['AI Suggestion', 'ai.php'], ['Schedule Gift', 'schedule.php']];

function filter_url(array $over = []): string {
    $p = array_merge($_GET, $over);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return 'filter.php' . ($p ? '?' . http_build_query($p) : '');
}
$anyFilter = array_filter($sel) || $budget !== '' || $cat !== '' || $q !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf" content="<?= e(csrf_token()) ?>">
    <title>Smart Gift Finder - Upohar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/filter.css?v=<?= @filemtime(__DIR__ . '/assets/css/filter.css') ?>">
</head>
<body>

    <?php require __DIR__ . '/includes/nav_top.php'; ?>

    <header class="hero">
        <div class="blob b1"></div><div class="blob b2"></div>
        <div class="hero-inner">
            <h1>Find the perfect Upohar</h1>
            <p>Tell us who the gift is for, and we'll narrow down <?= $total ?> gifts to the ones that suit them.</p>
        </div>
    </header>

    <main class="filter-container">

        <form class="filter-panel" id="filterForm" method="GET" action="filter.php" aria-label="Gift filters">
            <div class="filter-group grow">
                <label for="f_q">Search</label>
                <input type="search" id="f_q" name="q" value="<?= e($q) ?>" placeholder="Name or keyword" class="<?= $q !== '' ? 'active' : '' ?>">
            </div>

            <?php foreach (FILTER_DIMS as $type => $label): if (!$opts[$type]) continue; ?>
                <div class="filter-group">
                    <label for="f_<?= e($type) ?>"><?= e($label) ?></label>
                    <select id="f_<?= e($type) ?>" name="<?= e($type) ?>" class="<?= $sel[$type] !== '' ? 'active' : '' ?>">
                        <option value="">Any</option>
                        <?php foreach ($opts[$type] as $slug => $text): ?>
                            <option value="<?= e($slug) ?>" <?= $sel[$type] === $slug ? 'selected' : '' ?>><?= e($text) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>

            <div class="filter-group">
                <label for="f_budget">Budget</label>
                <select id="f_budget" name="budget" class="<?= $budget !== '' ? 'active' : '' ?>">
                    <option value="">Any budget</option>
                    <?php foreach (BUDGETS as $k => $b): ?><option value="<?= e($k) ?>" <?= $budget === $k ? 'selected' : '' ?>><?= e($b[0]) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="f_cat">Category</label>
                <select id="f_cat" name="cat" class="<?= $cat !== '' ? 'active' : '' ?>">
                    <option value="">All categories</option>
                    <?php foreach ($cats as $slug => $c): ?><option value="<?= e($slug) ?>" <?= $cat === $slug ? 'selected' : '' ?>><?= e($c['label']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="f_sort">Sort by</label>
                <select id="f_sort" name="sort">
                    <option value="">Featured</option>
                    <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Newest first</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
                </select>
            </div>
            <a href="filter.php" class="reset-btn" style="text-align:center;text-decoration:none;display:inline-block">Reset</a>
        </form>

        <p class="result-count" aria-live="polite"><?= $n ? 'Showing <strong>' . $n . '</strong> ' . ($n === 1 ? 'gift' : 'gifts') . ($anyFilter ? ' that match' : '') : '' ?></p>

        <div class="gifts-grid" id="giftsGrid">
            <?php if (!$n): ?>
                <div class="empty">
                    <h3>No matching gifts</h3>
                    <p>Try removing one of the filters.</p>
                    <a href="filter.php" class="reset-btn" style="text-decoration:none;display:inline-block">Reset filters</a>
                </div>
            <?php else: foreach ($gifts as $i => $g): $hasImg = $g['image'] !== ''; ?>
                <div class="gift-card" style="--i:<?= min($i, 14) ?>">
                    <div class="img-wrap <?= $hasImg ? '' : 'noimg' ?>" <?= $hasImg ? '' : 'data-emoji="' . e($g['emoji']) . '"' ?>>
                        <?php if ($hasImg): ?><img loading="lazy" src="<?= e(img_url($g['image'])) ?>" alt="<?= e($g['name']) ?>"><?php endif; ?>
                    </div>
                    <small class="gift-cat"><?= e($g['category_label']) ?></small>
                    <h3><?= e($g['name']) ?></h3>
                    <p class="gift-price">$<?= number_format((float)$g['price'], 2) ?><?php if ($g['old_price']): ?> <s>$<?= number_format((float)$g['old_price'], 2) ?></s><?php endif; ?></p>
                    <button class="view-btn" type="button" data-add="<?= (int)$g['id'] ?>">Add to cart</button>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="pager" aria-label="Pages">
                <?php if ($page > 1): ?><a href="<?= e(filter_url(['page' => $page - 1])) ?>">&larr; Prev</a><?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                    <a href="<?= e(filter_url(['page' => $i])) ?>" class="<?= $i === $page ? 'on' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $pages): ?><a href="<?= e(filter_url(['page' => $page + 1])) ?>">Next &rarr;</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </main>

    <div class="toast-lite" id="toastLite" role="status" aria-live="polite"></div>
    <?php require __DIR__ . '/includes/footer_page.php'; ?>
    <script src="assets/js/filter.js?v=<?= @filemtime(__DIR__ . '/assets/js/filter.js') ?>"></script>
</body>
</html>
