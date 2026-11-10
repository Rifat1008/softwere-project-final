// Upohar gift shop. Products (PRODUCTS) are printed by gifts.php from MySQL; the cart lives on the server.

let currentPage    = 1;
const ITEMS_PER_PAGE = 10;
let filteredProducts = [...PRODUCTS];
let cart = [];                      // filled from api/cart.php (saved in MySQL when logged in)
const selected = new Set();         // items ticked to buy now
const seen = new Set();             // items we already auto-ticked once
const WISH = new Set(typeof WISHLIST_IDS !== 'undefined' ? WISHLIST_IDS : []);

// New items start ticked; items that left the cart are forgotten.
function syncSelection() {
  const ids = new Set(cart.map(i => i.id));
  [...selected].forEach(id => { if (!ids.has(id)) selected.delete(id); });
  [...seen].forEach(id => { if (!ids.has(id)) seen.delete(id); });
  ids.forEach(id => { if (!seen.has(id)) { seen.add(id); selected.add(id); } });
}
function togglePick(id, on) { on ? selected.add(id) : selected.delete(id); renderCartItems(); }
function selectAll(on) { cart.forEach(i => on ? selected.add(i.id) : selected.delete(i.id)); renderCartItems(); }
const CSRF = document.querySelector('meta[name="csrf"]').content;

async function cartCall(action, data = {}) {
  try {
    const res = await fetch('api/cart.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ action, ...data })
    });
    const json = await res.json();
    if (json.cart) { cart = json.cart; syncSelection(); }
    updateCartUI();
    renderCartItems();
    if (!res.ok) { showToast(json.error || 'Something went wrong.'); if (json.login) setTimeout(() => location.href = 'login.php?next=gifts.php', 1200); return null; }
    return json;
  } catch (err) {
    showToast('Network error. Try again.');
    return null;
  }
}

async function loadCart() {
  try {
    const res = await fetch('api/cart.php');
    const json = await res.json();
    cart = json.cart || [];
  } catch { cart = []; }
  syncSelection();
  updateCartUI();
  renderCartItems();
}

function getCartTotal() {          // total of the ticked items only
  return cart.filter(i => selected.has(i.id)).reduce((sum, item) => sum + item.price * item.qty, 0);
}
function getCartCount() {
  return cart.reduce((sum, item) => sum + item.qty, 0);
}

// ================================================================
// ADD / REMOVE / CLEAR / CHECKOUT  (all saved on the server)
// ================================================================
async function addToCart(productId, btnEl) {
  const product = PRODUCTS.find(p => p.id === productId);
  if (!product) return;

  const ok = await cartCall('add', { id: productId });
  if (!ok) return;
  showToast(`"${product.name}" added to cart!`);

  if (btnEl) {
    btnEl.classList.add("added");
    btnEl.innerHTML = '<i class="fas fa-check"></i> Added!';
    setTimeout(() => {
      btnEl.classList.remove("added");
      btnEl.innerHTML = '<i class="fas fa-shopping-bag"></i> Add to Cart';
    }, 1800);
  }
}

function removeFromCart(productId) { cartCall('remove', { id: productId }); }
function changeQty(productId, delta) { cartCall('change', { id: productId, delta }); }
function clearCart() { cartCall('clear'); }

async function checkout() {
  const ids = [...selected];
  if (!ids.length) { showToast("Tick at least one item to buy."); return; }
  const res = await cartCall('checkout', { ids });
  if (res && res.ok) {
    const left = res.cart.length;
    showToast(`Order #${res.order_id} placed (${res.bought} item${res.bought > 1 ? 's' : ''})!` + (left ? ` ${left} still in your cart.` : ''));
    if (window.umSetCounts) window.umSetCounts({ orders: (parseInt((document.querySelector('[data-um="orders"]') || {}).textContent) || 0) + 1 });
    if (!left) toggleCart();
  }
}

// ================================================================
// CART UI
// ================================================================
function updateCartUI() {
  const count = getCartCount();
  document.getElementById("cartCount").textContent = count;
  if (window.umSetCounts) window.umSetCounts({ cart: cart.length });   // number on the user icon
}

function renderCartItems() {
  const container = document.getElementById("cartItems");
  const footer    = document.getElementById("cartFooter");

  if (cart.length === 0) {
    container.innerHTML = `
      <div class="empty-cart">
        <i class="fas fa-gift"></i>
        <p>Your cart is empty</p>
      </div>`;
    footer.style.display = "none";
    return;
  }

  footer.style.display = "block";
  container.innerHTML = cart.map(item => `
    <div class="cart-item">
      <label class="cart-pick" title="Tick to buy this item now">
        <input type="checkbox" ${selected.has(item.id) ? "checked" : ""} onchange="togglePick(${item.id}, this.checked)">
      </label>
      <div class="cart-item-img">
        <img src="${item.image}" alt="${item.name}" onerror="this.style.display='none'"/>
      </div>
      <div class="cart-item-info">
        <div class="cart-item-cat">${item.categoryLabel}</div>
        <div class="cart-item-name">${item.name}</div>
        <div class="cart-item-price">$${(item.price * item.qty).toFixed(2)}</div>
        <div class="cart-item-controls">
          <button class="qty-btn" onclick="changeQty(${item.id}, -1)"><i class="fas fa-minus"></i></button>
          <span class="qty-display">${item.qty}</span>
          <button class="qty-btn" onclick="changeQty(${item.id}, +1)"><i class="fas fa-plus"></i></button>
          <button class="remove-item" onclick="removeFromCart(${item.id})" title="Remove">
            <i class="fas fa-trash"></i>
          </button>
        </div>
      </div>
    </div>
  `).join("");

  document.getElementById("cartTotal").textContent = `$${getCartTotal().toFixed(2)}`;

  const n = cart.filter(i => selected.has(i.id)).length;
  const btn = document.getElementById("checkoutBtn");
  btn.textContent = n ? `Buy selected (${n})` : "Select items to buy";
  btn.disabled = n === 0;
  document.getElementById("selAll").checked = n === cart.length;
}

function toggleCart() {
  const sidebar = document.getElementById("cartSidebar");
  const overlay = document.getElementById("cartOverlay");
  sidebar.classList.toggle("open");
  overlay.classList.toggle("open");
  if (sidebar.classList.contains("open")) renderCartItems();
}

// ================================================================
// TOAST
// ================================================================
let toastTimer;
function showToast(msg) {
  const toast = document.getElementById("toast");
  document.getElementById("toastMsg").innerHTML = msg; // msg is already HTML-escaped
  toast.classList.add("show");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove("show"), 3000);
}

// ================================================================
// FILTER
// ================================================================
let activeFilter = "all";

function setFilter(filter) {
  activeFilter = filter;
  currentPage  = 1;

  document.querySelectorAll(".filter-btn").forEach(btn => {
    btn.classList.toggle("active", btn.dataset.filter === filter);
  });

  filteredProducts = filter === "all"
    ? [...PRODUCTS]
    : PRODUCTS.filter(p => p.category === filter);

  sortProducts();  // sort + render
}

// ================================================================
// SORT
// ================================================================
function sortProducts() {
  const val = document.getElementById("sortSelect").value;
  const arr  = [...filteredProducts];

  if (val === "price-asc")  arr.sort((a, b) => a.price - b.price);
  if (val === "price-desc") arr.sort((a, b) => b.price - a.price);
  if (val === "name")       arr.sort((a, b) => a.name.localeCompare(b.name));

  filteredProducts = arr;
  renderPage();
}

// ================================================================
// RENDER PRODUCTS (paginated)
// ================================================================
function renderPage() {
  const grid      = document.getElementById("productsGrid");
  const totalPages = Math.ceil(filteredProducts.length / ITEMS_PER_PAGE);
  const start     = (currentPage - 1) * ITEMS_PER_PAGE;
  const pageItems = filteredProducts.slice(start, start + ITEMS_PER_PAGE);

  // Results text
  const total = filteredProducts.length;
  document.getElementById("resultsText").textContent =
    `Showing ${start + 1}–${Math.min(start + ITEMS_PER_PAGE, total)} of ${total} gifts`;

  // Render cards
  grid.innerHTML = pageItems.map(p => `
    <div class="product-card" data-id="${p.id}">
      ${p.badge ? `<span class="product-badge badge-${p.badge}">${p.badgeLabel}</span>` : ""}
      <button class="wishlist-btn ${WISH.has(p.id) ? 'active' : ''}" title="Add to wishlist" onclick="toggleWish(${p.id}, this)">
        <i class="fas fa-heart"></i>
      </button>

      <div class="product-img-wrap">
        ${p.image ? `<img
          src="${p.image}"
          alt="${p.name}"
          loading="lazy"
          onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
        />` : ""}
        <div class="img-fallback" style="display:${p.image ? 'none' : 'flex'};">
          <span style="font-size:3.5rem">${p.emoji}</span>
          <span>${p.categoryLabel}</span>
        </div>
      </div>

      <div class="product-info">
        <div class="product-category">${p.categoryLabel}</div>
        <div class="product-name">${p.name}</div>
        <div class="product-desc">${p.desc}</div>

        ${p.reviews > 0 ? `<div class="product-stars">
          <span class="stars">${"★".repeat(p.stars)}${"☆".repeat(5 - p.stars)}</span>
          <span class="review-count">(${p.reviews}k)</span>
        </div>` : ""}

        <div class="price-row">
          <span class="product-price">$${p.price.toFixed(2)}</span>
          ${p.oldPrice ? `<span class="product-oldprice">$${p.oldPrice.toFixed(2)}</span>` : ""}
        </div>

        <button class="add-cart-btn" onclick="addToCart(${p.id}, this)">
          <i class="fas fa-shopping-bag"></i> Add to Cart
        </button>
      </div>
    </div>
  `).join("");

  renderPagination(totalPages);
  window.scrollTo({ top: 0, behavior: "smooth" });
}

// ================================================================
// PAGINATION
// ================================================================
function renderPagination(totalPages) {
  const numbersEl = document.getElementById("pageNumbers");
  const prevBtn   = document.getElementById("prevBtn");
  const nextBtn   = document.getElementById("nextBtn");

  prevBtn.disabled = currentPage === 1;
  nextBtn.disabled = currentPage === totalPages;

  // Build page number buttons (show up to 5 around current)
  let pages = [];
  if (totalPages <= 7) {
    pages = Array.from({length: totalPages}, (_, i) => i + 1);
  } else {
    pages = [1];
    if (currentPage > 3) pages.push("...");
    for (let i = Math.max(2, currentPage - 1); i <= Math.min(totalPages - 1, currentPage + 1); i++) {
      pages.push(i);
    }
    if (currentPage < totalPages - 2) pages.push("...");
    pages.push(totalPages);
  }

  numbersEl.innerHTML = pages.map(p =>
    p === "..."
      ? `<span style="display:flex;align-items:center;padding:0 4px;color:var(--text-light)">…</span>`
      : `<button class="page-num ${p === currentPage ? "active" : ""}" onclick="goToPage(${p})">${p}</button>`
  ).join("");
}

function changePage(delta) {
  const totalPages = Math.ceil(filteredProducts.length / ITEMS_PER_PAGE);
  currentPage = Math.max(1, Math.min(currentPage + delta, totalPages));
  renderPage();
}

function goToPage(page) {
  currentPage = page;
  renderPage();
}

// ================================================================
// FILTER BUTTONS — attach events
// ================================================================
document.querySelectorAll(".filter-btn").forEach(btn => {
  btn.addEventListener("click", () => setFilter(btn.dataset.filter));
});

// ================================================================
// INIT
// ================================================================
renderPage();
loadCart();


// ================================================================
// WISHLIST (saved in MySQL through api/wishlist.php)
// ================================================================
async function toggleWish(productId, btn) {
  try {
    const res = await fetch('api/wishlist.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ id: productId })
    });
    const json = await res.json();
    if (!res.ok) {
      showToast(json.error || 'Something went wrong.');
      if (json.login) setTimeout(() => location.href = 'login.php?next=gifts.php', 1200);
      return;
    }
    WISH.clear(); json.ids.forEach(id => WISH.add(id));
    btn.classList.toggle('active', json.active);
    showToast(json.active ? 'Saved to your wishlist.' : 'Removed from your wishlist.');
    if (window.umSetCounts) window.umSetCounts({ wishlist: json.count });
  } catch (err) {
    showToast('Network error. Try again.');
  }
}
