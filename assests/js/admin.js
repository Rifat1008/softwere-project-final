/* admin panel: mobile menu + confirm dialogs + reject reason prompt */
const burger = document.getElementById('burger'), side = document.getElementById('side');
if (burger) burger.addEventListener('click', () => side.classList.toggle('open'));

document.addEventListener('submit', (e) => {
    const msg = e.target.dataset.confirm;
    if (msg && !confirm(msg)) e.preventDefault();
});

// "Reject" asks for a short reason that the vendor will see
document.querySelectorAll('form[data-reject]').forEach(f => f.addEventListener('submit', (e) => {
    const reason = prompt('Reason for rejecting (the vendor will see this):', '');
    if (reason === null) { e.preventDefault(); return; }
    f.querySelector('input[name=reason]').value = reason;
}));
