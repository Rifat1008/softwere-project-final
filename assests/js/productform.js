/* product form: "Select all" buttons for tag groups + live photo preview */
document.querySelectorAll('.pf-all').forEach(btn => btn.addEventListener('click', () => {
    const boxes = btn.closest('fieldset').querySelectorAll('input[type=checkbox]');
    const allOn = [...boxes].every(b => b.checked);
    boxes.forEach(b => { b.checked = !allOn; });
    btn.textContent = allOn ? 'Select all' : 'Clear';
}));

const pfFile = document.getElementById('pfFile'), pfUrl = document.getElementById('pfUrl'), pfImg = document.getElementById('pfPreview');
function pfShow(src) { if (!pfImg) return; pfImg.src = src; pfImg.hidden = !src; }
if (pfFile) pfFile.addEventListener('change', () => { const f = pfFile.files[0]; if (f) pfShow(URL.createObjectURL(f)); });
if (pfUrl) pfUrl.addEventListener('input', () => { if (/^https?:\/\//i.test(pfUrl.value)) pfShow(pfUrl.value); });
