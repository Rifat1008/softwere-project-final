/* Find a Gift: filtering happens in PHP/MySQL (tags). This file auto-submits the form,
   shows image loading states and adds gifts to the cart. */
const form = document.getElementById('filterForm');
form.querySelectorAll('select').forEach(s => s.addEventListener('change', () => form.submit()));
let searchTimer;
const searchBox = document.getElementById('f_q');
if (searchBox) searchBox.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => form.submit(), 700); });

document.querySelectorAll('.img-wrap:not(.noimg)').forEach(wrap => {
    const img = wrap.querySelector('img');
    const ok  = () => wrap.classList.add('loaded');
    const bad = () => wrap.classList.add('error');
    if (img.complete) { img.naturalWidth ? ok() : bad(); }
    else { img.addEventListener('load', ok); img.addEventListener('error', bad); }
});

/* Add to cart (saved in MySQL when logged in, in the session otherwise) */
const CSRF = document.querySelector('meta[name="csrf"]').content;
const toastEl = document.getElementById('toastLite');
let toastTimer;
function toast(msg) {
    toastEl.textContent = msg; toastEl.classList.add('show');
    clearTimeout(toastTimer); toastTimer = setTimeout(() => toastEl.classList.remove('show'), 2200);
}
document.getElementById('giftsGrid').addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-add]');
    if (!btn) return;
    btn.disabled = true;
    try {
        const res = await fetch('api/cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ action: 'add', id: +btn.dataset.add })
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Something went wrong.');
        if (window.umSetCounts) window.umSetCounts({ cart: data.cart.length });
        btn.textContent = 'Added \u2713';
        toast('Added to your cart.');
        setTimeout(() => { btn.textContent = 'Add to cart'; btn.disabled = false; }, 1500);
    } catch (err) { toast(err.message); btn.disabled = false; }
});
