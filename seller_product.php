<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/products.php';
require_once __DIR__ . '/includes/product_lib.php';
require_seller();

$sid = (int)$_SESSION['seller_id'];
$stmt = $conn->prepare('SELECT plan, status FROM sellers WHERE id = ?');
$stmt->bind_param('i', $sid); $stmt->execute();
$seller = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$seller || $seller['status'] !== 'approved') redirect('seller_dashboard.php');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$existing = null;
if ($id) {
    $st = $conn->prepare('SELECT * FROM products WHERE id = ? AND seller_id = ?');   // vendors can only touch their own products
    $st->bind_param('ii', $id, $sid); $st->execute();
    $existing = $st->get_result()->fetch_assoc(); $st->close();
    if (!$existing) redirect('seller_dashboard.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? null)) $errors[] = 'Session expired. Please try again.';
    if (!$existing) {
        $limit = PLAN_LIMITS[$seller['plan']];
        $n = $conn->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ? AND status <> 'rejected'");
        $n->bind_param('i', $sid); $n->execute();
        if ($limit !== null && (int)$n->get_result()->fetch_row()[0] >= $limit) $errors[] = "Your " . ucfirst($seller['plan']) . " plan allows $limit products. Delete one or ask for an upgrade.";
    }
    $d = read_product_form($conn, $_POST, $_FILES, $existing, $errors);
    if (!$errors) {
        $oldImage = $existing['image'] ?? null;
        save_product($conn, $d, $sid, 'pending', $id ?: null);          // every new or edited product waits for admin approval
        if ($oldImage && $d['new_upload'] && $oldImage !== $d['image']) delete_upload($oldImage);
        $_SESSION['sflash'] = $id ? 'Saved. Your changes are waiting for review.' : 'Product submitted! It will go live after the Upohar team approves it.';
        redirect('seller_dashboard.php');
    }
    if ($d['new_upload']) { delete_upload($d['image']); $d['image'] = $existing['image'] ?? ''; }
    $v = $d;
} else {
    $v = $existing ?? ['name' => '', 'category' => '', 'price' => '', 'old_price' => '', 'badge' => '', 'description' => '', 'image' => ''];
    $v['badge'] = $v['badge'] ?? '';
    $v['tags'] = $id ? product_tags($conn, $id) : [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id ? 'Edit' : 'Add' ?> product - UPOHAR</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/seller.css">
    <link rel="stylesheet" href="assets/css/productform.css">
</head>
<body>
<div class="center">
    <div class="box wide" style="margin:20px 0">
        <div class="dash-head"><h2><?= $id ? 'Edit product' : 'Add a product' ?></h2><a href="seller_dashboard.php" class="ghost-link">&larr; Back</a></div>
        <p class="muted-note" style="margin-bottom:14px">After you save, the Upohar team reviews the product. Once approved it appears in the gift shop and in the Find-a-Gift page for the people you tag.</p>
        <?php if ($errors): ?><div class="alert"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div><?php endif; ?>
        <form method="POST" action="seller_product.php<?= $id ? '?id=' . $id : '' ?>" enctype="multipart/form-data">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
            <?php product_form_fields($conn, $v); ?>
            <button class="btn" type="submit" style="margin-top:22px"><?= $id ? 'Save for review' : 'Submit product' ?></button>
        </form>
    </div>
</div>
<script src="assets/js/productform.js"></script>
</body>
</html>
