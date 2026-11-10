/* Account page: cart (tick what to buy), wishlist buttons. Everything is saved through the PHP APIs. */
const CSRF = document.querySelector('meta[name="csrf"]').content;
const toastEl = document.getElementById('toast');
let toastTimer;
function toast(msg) {
    toastEl.textContent = msg;
    toastEl.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove('show'), 2600);
}

async function post(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify(body)
    });
    let data = {};
    try { data = await res.json(); } catch (e) { /* ignore */ }
    if (!res.ok) throw new Error(data.error || 'Something went wrong.');
    return data;
}

/* refresh every number: dropdown menu + tab badges + overview tiles */
function setCounts(c) {
    if (window.umSetCounts) window.umSetCounts(c);
    Object.keys(c).forEach(k => document.querySelectorAll('[data-tabcount="' + k + '"]').forEach(el => { el.textContent = c[k]; }));
}
const counter = (k) => parseInt((document.querySelector('[data-tabcount="' + k + '"]') || {}).textContent) || 0;

/* ================= CART TAB ================= */
const cartRoot = document.getElementById('cartRoot');
if (cartRoot) {
    let cart = [];
    const selected = new Set();
    const seen = new Set();
    let flash = '';

    function sync() {
        const ids = new Set(cart.map(i => i.id));
        [...selected].forEach(id => { if (!ids.has(id)) selected.delete(id); });
        [...seen].forEach(id => { if (!ids.has(id)) seen.delete(id); });
        ids.forEach(id => { if (!seen.has(id)) { seen.add(id); selected.add(id); } });
    }

    function draw() {
        if (!cart.length) {
            cartRoot.innerHTML = flash + '<p class="muted">Your cart is empty. <a href="gifts.php">Visit the gift shop</a></p>';
            return;
        }
        const chosen = cart.filter(i => selected.has(i.id));
        const total = chosen.reduce((s, i) => s + i.price * i.qty, 0);

        cartRoot.innerHTML = flash + '<h2>My cart</h2>' + cart.map(i => `
            <div class="cart-row">
                <input type="checkbox" data-pick="${i.id}" ${selected.has(i.id) ? 'checked' : ''} aria-label="Buy ${i.name}">
                <div class="ph"><img src="${i.image}" alt="" onerror="this.style.display='none'"></div>
                <div>
                    <small>${i.categoryLabel}</small>
                    <h3>${i.name}</h3>
                    <div class="qty"><button data-dec="${i.id}" aria-label="Less">&minus;</button><span>${i.qty}</span><button data-inc="${i.id}" aria-label="More">+</button></div>
                </div>
                <div class="row-price">$${(i.price * i.qty).toFixed(2)}<button data-del="${i.id}">Remove</button></div>
            </div>`).join('') + `
            <div class="cart-bar">
                <label><input type="checkbox" id="pickAll" ${chosen.length === cart.length ? 'checked' : ''}> Select all</label>
                <div class="sum">${chosen.length} selected &middot; <b>$${total.toFixed(2)}</b></div>
                <button class="btn-gold" id="buyBtn" ${chosen.length ? '' : 'disabled'}>${chosen.length ? 'Buy selected (' + chosen.length + ')' : 'Select items to buy'}</button>
            </div>
            <p class="keep-note">Items you don't tick stay saved in your cart.</p>`;
    }

    async function call(action, data) {
        try {
            const res = await post('api/cart.php', Object.assign({ action }, data || {}));
            cart = res.cart; sync();
            setCounts({ cart: cart.length });
            return res;
        } catch (err) { toast(err.message); return null; }
    }

    cartRoot.addEventListener('click', async (e) => {
        const t = e.target;
        if (t.dataset.inc)  { await call('change', { id: +t.dataset.inc,  delta: 1 });  draw(); }
        if (t.dataset.dec)  { await call('change', { id: +t.dataset.dec,  delta: -1 }); draw(); }
        if (t.dataset.del)  { await call('remove', { id: +t.dataset.del }); flash = ''; draw(); }
        if (t.id === 'buyBtn') {
            const res = await call('checkout', { ids: [...selected] });
            if (res && res.ok) {
                setCounts({ orders: counter('orders') + 1, cart: cart.length });
                flash = `<div class="success">Order #${res.order_id} placed (${res.bought} item${res.bought > 1 ? 's' : ''}). `
                      + (cart.length ? `${cart.length} other item${cart.length > 1 ? 's are' : ' is'} still in your cart. ` : '')
                      + '<a href="account.php?tab=orders">View orders</a></div>';
                draw();
            }
        }
    });
    cartRoot.addEventListener('change', (e) => {
        const t = e.target;
        if (t.dataset.pick) { t.checked ? selected.add(+t.dataset.pick) : selected.delete(+t.dataset.pick); draw(); }
        if (t.id === 'pickAll') { cart.forEach(i => t.checked ? selected.add(i.id) : selected.delete(i.id)); draw(); }
    });

    fetch('api/cart.php').then(r => r.json()).then(d => { cart = d.cart || []; sync(); draw(); })
        .catch(() => { cartRoot.innerHTML = '<p class="muted">Could not load your cart. Refresh the page.</p>'; });
}

/* ================= WISHLIST TAB ================= */
const wishGrid = document.getElementById('wishGrid');
if (wishGrid) {
    wishGrid.addEventListener('click', async (e) => {
        const card = e.target.closest('.wish');
        if (!card) return;
        const id = +card.dataset.id;
        try {
            if (e.target.classList.contains('js-wish-add')) {
                const res = await post('api/cart.php', { action: 'add', id });
                setCounts({ cart: res.cart.length });
                toast('Added to your cart.');
            }
            if (e.target.classList.contains('js-wish-remove')) {
                const res = await post('api/wishlist.php', { id });
                card.remove();
                setCounts({ wishlist: res.count });
                toast('Removed from your wishlist.');
                if (!wishGrid.children.length) location.reload();
            }
        } catch (err) { toast(err.message); }
    });
}
