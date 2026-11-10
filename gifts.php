<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/products.php';

require_once __DIR__ . '/includes/product_lib.php';

// ?seller=ID shows only one vendor's shop (links come from "Top sellers of the week")
$sellerId = (int)($_GET['seller'] ?? 0);
$shop = null;
if ($sellerId) {
    $st = $conn->prepare("SELECT id, shop_name FROM sellers WHERE id = ? AND status = 'approved'");
    $st->bind_param('i', $sellerId); $st->execute();
    $shop = $st->get_result()->fetch_assoc(); $st->close();
}
$sql = 'SELECT p.* FROM products p WHERE ' . visible_sql('p') . ($shop ? ' AND p.seller_id = ' . (int)$shop['id'] : '') . ' ORDER BY p.id ASC';
$products = [];
foreach ($conn->query($sql) as $r) $products[] = product_for_js($r);

// category buttons = categories that really have gifts (managed in Admin > Categories)
$present = array_unique(array_column($products, 'category'));
$catButtons = [];
foreach (categories_all($conn) as $slug => $c) if (in_array($slug, $present, true)) $catButtons[$slug] = $c['label'];

// hearts that this user already saved (table: wishlist)
$wishIds = [];
if (is_logged_in()) {
    $uid = (int)$_SESSION['user_id'];
    $st = $conn->prepare('SELECT product_id FROM wishlist WHERE user_id = ?');
    $st->bind_param('i', $uid); $st->execute();
    foreach ($st->get_result() as $w) $wishIds[] = (int)$w['product_id'];
    $st->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="csrf" content="<?= e(csrf_token()) ?>">
  <title>Viral Gift Collections - Upohar</title>
  <link rel="stylesheet" href="assets/css/gifts.css" />
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body>

  <!-- ===== CART SIDEBAR ===== -->
  <div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>
  <aside class="cart-sidebar" id="cartSidebar">
    <div class="cart-header">
      <h2>Your Gift Cart</h2>
      <button class="close-cart" onclick="toggleCart()"><i class="fas fa-times"></i></button>
    </div>
    <div class="cart-items" id="cartItems">
      <div class="empty-cart">
        <i class="fas fa-gift"></i>
        <p>Your cart is empty</p>
      </div>
    </div>
    <div class="cart-footer" id="cartFooter" style="display:none;">
      <label class="cart-selall"><input type="checkbox" id="selAll" onchange="selectAll(this.checked)"> Select all</label>
      <div class="cart-total">
        <span>Selected total</span>
        <span id="cartTotal">$0.00</span>
      </div>
      <p class="cart-hint">Items you don't tick stay in your cart.</p>
      <button class="checkout-btn" id="checkoutBtn" onclick="checkout()">Buy selected</button>
      <button class="clear-cart-btn" onclick="clearCart()">Clear Cart</button>
    </div>
  </aside>

  <!-- ===== HEADER ===== -->
  <header class="page-header">
    <div class="header-inner">
      <div class="header-left">
        <a href="index.php" class="tag-badge" style="text-decoration:none;margin-right:6px"><i class="fas fa-arrow-left"></i> Home</a>
        <span class="tag-badge"><i class="fas fa-fire"></i> Trending Gifts</span>
        <h1 class="page-title">Luxury Gift Collection</h1>
        <p class="page-subtitle"><?php if ($shop): ?>Shop: <b><?= e($shop['shop_name']) ?></b> &middot; <a href="gifts.php" style="text-decoration:underline">show all gifts</a><?php else: ?>Curated premium gifts for every occasion — perfumes, gadgets, sneakers &amp; chocolates<?php endif; ?></p>
      </div>
      <div class="header-actions">
        <?php require __DIR__ . '/includes/usermenu.php'; ?>
        <?php if (!is_logged_in()): ?><a class="um-login" href="login.php?next=gifts.php">Login</a><?php endif; ?>
        <button class="cart-btn" onclick="toggleCart()" aria-label="Open cart">
          <i class="fas fa-shopping-bag"></i>
          <span class="cart-count" id="cartCount">0</span>
        </button>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <button class="filter-btn active" data-filter="all">All Gifts</button>
      <?php foreach ($catButtons as $slug => $label): ?>
        <button class="filter-btn" data-filter="<?= e($slug) ?>"><?= e($label) ?></button>
      <?php endforeach; ?>
    </div>
  </header>

  <!-- ===== PRODUCTS GRID ===== -->
  <main class="main-content">
    <div class="results-bar">
      <p id="resultsText">Showing products</p>
      <select id="sortSelect" onchange="sortProducts()">
        <option value="default">Sort: Featured</option>
        <option value="price-asc">Price: Low to High</option>
        <option value="price-desc">Price: High to Low</option>
        <option value="name">Name: A–Z</option>
      </select>
    </div>

    <div class="products-grid" id="productsGrid"></div>

    <!-- ===== PAGINATION ===== -->
    <div class="pagination" id="pagination">
      <button class="page-btn prev-btn" id="prevBtn" onclick="changePage(-1)">
        <i class="fas fa-chevron-left"></i> Prev
      </button>
      <div class="page-numbers" id="pageNumbers"></div>
      <button class="page-btn next-btn" id="nextBtn" onclick="changePage(1)">
        Next <i class="fas fa-chevron-right"></i>
      </button>
    </div>
  </main>

  <!-- ===== TOAST ===== -->
  <div class="toast" id="toast">
    <i class="fas fa-check-circle"></i>
    <span id="toastMsg">Added to cart!</span>
  </div>

  <!-- Products come from the MySQL "products" table -->
  <script>
    const PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const LOGGED_IN = <?= is_logged_in() ? 'true' : 'false' ?>;
    const WISHLIST_IDS = <?= json_encode($wishIds) ?>;
  </script>
  <script src="assets/js/gifts.js?v=<?= @filemtime(__DIR__ . '/assets/js/gifts.js') ?>"></script>
</body>
</html>
