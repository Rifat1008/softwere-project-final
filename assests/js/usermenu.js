/* User icon dropdown: open/close, plus a tiny helper other scripts use to refresh the numbers. */
(function () {
    const btn  = document.getElementById('umBtn');
    const menu = document.getElementById('umMenu');

    if (btn && menu) {
        const set = (open) => {
            menu.hidden = !open;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        };
        btn.addEventListener('click', (e) => { e.stopPropagation(); set(menu.hidden); });
        document.addEventListener('click', (e) => { if (!menu.hidden && !menu.contains(e.target)) set(false); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !menu.hidden) { set(false); btn.focus(); } });
    }

    /* umSetCounts({cart: 2, wishlist: 5, orders: 1, schedules: 0}) updates every number in the menu */
    window.umSetCounts = function (counts) {
        Object.keys(counts).forEach((key) => {
            document.querySelectorAll('[data-um="' + key + '"]').forEach((el) => {
                el.textContent = counts[key];
                if (key === 'cart' && el.classList.contains('um-badge')) el.hidden = !counts[key];
            });
        });
    };
})();
