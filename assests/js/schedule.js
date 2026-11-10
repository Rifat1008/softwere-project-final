/* Schedule a gift: wizard, live preview, and a saved list (stored in MySQL through api/schedule.php). */
const $ = (id) => document.getElementById(id);
const CSRF = document.querySelector('meta[name="csrf"]').content;
let items = [];   // the user's saved schedules, loaded from the server

let step = 1;
let occasion = '';
let ahead = 0;
let imgData = '';

/* ---------- storage ---------- */
async function api(body) {
    const res = await fetch('api/schedule.php', body ? {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify(body)
    } : undefined);
    let data = {};
    try { data = await res.json(); } catch (e) { /* not JSON */ }
    if (!res.ok) { const err = new Error(data.error || 'Something went wrong.'); err.login = !!data.login; throw err; }
    return data;
}
async function refresh() {
    if (!LOGGED_IN) { items = []; count(); return; }
    try { items = (await api()).items; } catch (e) { items = []; }
    count();
}

/* ---------- helpers ---------- */
const pad = (n) => String(n).padStart(2, '0');
const toLocalInput = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
const fmt = (iso) => new Date(iso).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });

function left(iso) {
    const ms = new Date(iso) - Date.now();
    if (isNaN(ms)) return null;
    const s = Math.max(0, Math.floor(ms / 1000));
    return { past: ms <= 0, d: Math.floor(s / 86400), h: Math.floor(s % 86400 / 3600), m: Math.floor(s % 3600 / 60), s: s % 60 };
}

/* ---------- tabs ---------- */
function showTab(name) {
    document.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t.dataset.tab === name));
    $('panel-new').hidden = name !== 'new';
    $('panel-list').hidden = name !== 'list';
    if (name === 'list') renderList();
}
document.querySelectorAll('.tab').forEach(t => t.addEventListener('click', () => showTab(t.dataset.tab)));

/* ---------- chips ---------- */
$('occasions').addEventListener('click', (e) => {
    const c = e.target.closest('.chip'); if (!c) return;
    const same = c.classList.contains('active');
    document.querySelectorAll('#occasions .chip').forEach(x => x.classList.remove('active'));
    occasion = same ? '' : c.dataset.v;
    if (!same) c.classList.add('active');
    preview();
});

$('presets').addEventListener('click', (e) => {
    const c = e.target.closest('.chip'); if (!c) return;
    const d = new Date(); d.setDate(d.getDate() + Number(c.dataset.days)); d.setHours(9, 0, 0, 0);
    $('scheduleTime').value = toLocalInput(d);
    document.querySelectorAll('#presets .chip').forEach(x => x.classList.toggle('active', x === c));
    clearErr('scheduleTime'); preview();
});

$('ahead').addEventListener('click', (e) => {
    const c = e.target.closest('.chip'); if (!c) return;
    ahead = Number(c.dataset.v);
    document.querySelectorAll('#ahead .chip').forEach(x => x.classList.toggle('active', x === c));
    preview();
});

$('templates').addEventListener('click', (e) => {
    const c = e.target.closest('.chip'); if (!c) return;
    $('message').value = c.dataset.t; counter(); preview();
});

/* ---------- photo (shrunk so it fits in browser storage) ---------- */
$('giftImage').addEventListener('change', () => {
    const f = $('giftImage').files[0];
    clearErr('giftImage');
    if (!f) return;
    if (!f.type.startsWith('image/')) return setErr('giftImage', 'Please choose an image file.');
    if (f.size > 5 * 1024 * 1024) return setErr('giftImage', 'Image must be under 5 MB.');
    const reader = new FileReader();
    reader.onload = () => {
        const img = new Image();
        img.onload = () => {
            const k = Math.min(1, 480 / Math.max(img.width, img.height));
            const c = document.createElement('canvas');
            c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
            c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
            imgData = c.toDataURL('image/jpeg', .75);
            $('thumb').src = imgData; $('thumb').hidden = false;
            $('dropText').textContent = 'Change photo';
            preview();
        };
        img.src = reader.result;
    };
    reader.readAsDataURL(f);
});

/* ---------- channels ---------- */
function channels() {
    $('phoneField').hidden = !$('sms').checked;
    $('emailField').hidden = !$('gmail').checked;
    clearErr('channels');
    preview();
}
['sms', 'gmail', 'app'].forEach(id => $(id).addEventListener('change', channels));

function counter() { $('counter').textContent = `${$('message').value.length}/200`; }
$('message').addEventListener('input', () => { counter(); preview(); });
['recipient', 'giftUrl', 'scheduleTime'].forEach(id => $(id).addEventListener('input', () => { clearErr(id); preview(); }));
['phone', 'email'].forEach(id => $(id).addEventListener('input', () => clearErr(id)));

/* ---------- errors ---------- */
function setErr(id, text) {
    const el = document.querySelector(`.err[data-for="${id}"]`);
    if (el) el.textContent = text;
    if ($(id)) $(id).classList.add('invalid');
    return false;
}
function clearErr(id) {
    const el = document.querySelector(`.err[data-for="${id}"]`);
    if (el) el.textContent = '';
    if ($(id)) $(id).classList.remove('invalid');
}

/* ---------- validation per step ---------- */
function validUrl(v) { try { const u = new URL(v); return u.protocol === 'http:' || u.protocol === 'https:'; } catch (e) { return false; } }

function validate(n) {
    let ok = true;
    if (n === 1) {
        if (!$('recipient').value.trim()) ok = setErr('recipient', 'Tell us who the gift is for.');
        const url = $('giftUrl').value.trim();
        if (!url) ok = setErr('giftUrl', 'Add the link to the gift.');
        else if (!validUrl(url)) ok = setErr('giftUrl', 'Enter a full link starting with http:// or https://');
    }
    if (n === 2) {
        const t = $('scheduleTime').value;
        if (!t) ok = setErr('scheduleTime', 'Choose a date and time.');
        else if (new Date(t) <= new Date()) ok = setErr('scheduleTime', 'Pick a time in the future.');
    }
    if (n === 3) {
        if (!$('sms').checked && !$('gmail').checked && !$('app').checked) ok = setErr('channels', 'Choose at least one way to be reminded.');
        if ($('sms').checked && !/^(\+?88)?01[3-9]\d{8}$/.test($('phone').value.replace(/[\s-]/g, ''))) ok = setErr('phone', 'Enter a valid Bangladesh number, like +8801XXXXXXXXX');
        if ($('gmail').checked && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($('email').value.trim())) ok = setErr('email', 'Enter a valid email address.');
    }
    return ok;
}

/* ---------- steps ---------- */
function go(n) {
    step = n;
    document.querySelectorAll('.step').forEach(s => s.classList.toggle('active', Number(s.dataset.step) === n));
    document.querySelectorAll('#steps li').forEach((li, i) => li.classList.toggle('on', i < n));
    $('bar').style.width = (n * 33.3) + '%';
    $('back').hidden = n === 1;
    $('next').textContent = n === 3 ? 'Confirm schedule' : 'Next';
    $('status').textContent = '';
}
$('next').addEventListener('click', () => {
    if (!validate(step)) return;
    if (step < 3) go(step + 1); else submitSchedule();
});
$('back').addEventListener('click', () => go(step - 1));
$('form').addEventListener('keydown', (e) => { if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') { e.preventDefault(); $('next').click(); } });

/* ---------- live preview ---------- */
function preview() {
    $('pName').textContent = $('recipient').value.trim() ? 'For ' + $('recipient').value.trim() : 'For someone special';
    $('pOcc').textContent = occasion || 'Occasion';
    $('pMsg').textContent = $('message').value.trim() || 'Your message will appear here.';
    if (imgData) { $('pImg').src = imgData; $('pImg').hidden = false; $('pImgText').hidden = true; }

    const t = $('scheduleTime').value;
    const ahd = ahead ? ` (reminder ${ahead === 7 ? '1 week' : ahead + ' day' + (ahead > 1 ? 's' : '')} before)` : '';
    $('pWhen').textContent = t ? fmt(t) + ahd : 'Pick a date to start the countdown';

    $('pVia').innerHTML = '';
    [['sms', 'SMS'], ['gmail', 'Email'], ['app', 'App']].forEach(([id, name]) => {
        if ($(id).checked) { const s = document.createElement('span'); s.textContent = name; $('pVia').appendChild(s); }
    });
    tick();
}

function tick() {
    const t = $('scheduleTime').value;
    const l = t ? left(t) : null;
    $('cd').textContent = l ? l.d : '--';
    $('ch').textContent = l ? pad(l.h) : '--';
    $('cm').textContent = l ? pad(l.m) : '--';
    $('cs').textContent = l ? pad(l.s) : '--';
}
setInterval(tick, 1000);

/* ---------- confirm ---------- */
async function submitSchedule() {
    const btn = $('next');

    if (!LOGGED_IN) {
        $('status').innerHTML = 'Please <a href="login.php?next=schedule.php">log in</a> or <a href="signup.php">sign up</a> to schedule a gift.';
        return;
    }

    btn.disabled = true; btn.textContent = 'Scheduling...';

    const item = {
        action: 'create',
        recipient: $('recipient').value.trim(),
        occasion,
        url: $('giftUrl').value.trim(),
        img: imgData,
        time: new Date($('scheduleTime').value).toISOString(),
        ahead,
        sms: $('sms').checked, gmail: $('gmail').checked, app: $('app').checked,
        phone: $('phone').value.trim(), email: $('email').value.trim(),
        message: $('message').value.trim()
    };

    try {
        const data = await api(item);
        items = data.items;
        count();
        $('doneText').textContent = `${item.occasion ? item.occasion + ' gift' : 'Gift'} for ${item.recipient} is set for ${fmt(item.time)}.`;
        $('done').hidden = false;
    } catch (err) {
        if (err.login) $('status').innerHTML = 'Your session ended. Please <a href="login.php?next=schedule.php">log in</a> again.';
        else $('status').textContent = err.message;
    } finally {
        btn.disabled = false; btn.textContent = 'Confirm schedule';
    }
}

function resetForm() {
    $('form').reset();
    occasion = ''; ahead = 0; imgData = '';
    document.querySelectorAll('.chip.active').forEach(c => c.classList.remove('active'));
    document.querySelector('#ahead .chip[data-v="0"]').classList.add('active');
    $('thumb').hidden = true; $('thumb').src = ''; $('dropText').textContent = 'Click to choose a photo';
    $('pImg').hidden = true; $('pImgText').hidden = false;
    $('app').checked = true;
    document.querySelectorAll('.invalid').forEach(x => x.classList.remove('invalid'));
    channels(); counter(); go(1); preview();
}

$('another').addEventListener('click', () => { $('done').hidden = true; resetForm(); });
$('viewList').addEventListener('click', () => { $('done').hidden = true; resetForm(); showTab('list'); });

/* ---------- my scheduled gifts ---------- */
function count() { $('count').textContent = items.length; }

function calendarFile(it) {
    const stamp = (d) => d.toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';
    const start = new Date(it.time), end = new Date(start.getTime() + 3600000);
    const text = (s) => String(s).replace(/[\\;,]/g, m => '\\' + m).replace(/\n/g, '\\n');
    const lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Upohar//Schedule//EN', 'BEGIN:VEVENT',
        `UID:${it.id}@upohar`, `DTSTAMP:${stamp(new Date())}`, `DTSTART:${stamp(start)}`, `DTEND:${stamp(end)}`,
        `SUMMARY:${text('Gift for ' + it.recipient)}`, `DESCRIPTION:${text(it.url + (it.message ? ' | ' + it.message : ''))}`];
    if (it.ahead) lines.push('BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:Gift reminder', `TRIGGER:-P${it.ahead}D`, 'END:VALARM');
    lines.push('END:VEVENT', 'END:VCALENDAR');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([lines.join('\r\n')], { type: 'text/calendar' }));
    a.download = 'upohar-gift.ics';
    a.click();
    URL.revokeObjectURL(a.href);
}

function renderList() {
    const box = $('list');
    const list = [...items].sort((a, b) => new Date(a.time) - new Date(b.time));
    box.innerHTML = '';
    count();

    if (!list.length) {
        box.innerHTML = '<div class="empty"><h3>' + (LOGGED_IN ? 'Nothing scheduled yet' : 'Log in to see your gifts') + '</h3><p>' + (LOGGED_IN ? 'Plan your first surprise. It takes under a minute.' : 'Your scheduled gifts are saved to your account.') + '</p><button type="button" class="btn gold" id="startNew" style="margin:0">Schedule a gift</button></div>';
        $('startNew').addEventListener('click', () => showTab('new'));
        return;
    }

    list.forEach((it, i) => {
        const l = left(it.time);
        const card = document.createElement('div');
        card.className = 'item';
        card.style.animationDelay = (i * 70) + 'ms';
        card.innerHTML = '<div class="pic"></div><div><h3></h3><p class="meta"></p><p class="left"></p><div class="acts"><button type="button" data-a="open">Open gift</button><button type="button" data-a="ics">Add to calendar</button><button type="button" data-a="del">Delete</button></div></div>';
        if (it.img) card.querySelector('.pic').style.backgroundImage = `url("${encodeURI(it.img)}")`;
        card.querySelector('h3').textContent = 'For ' + it.recipient;
        card.querySelector('.meta').textContent = (it.occasion ? it.occasion + ' · ' : '') + fmt(it.time);
        card.querySelector('.left').textContent = l.past ? 'Date has passed' : `${l.d}d ${l.h}h ${l.m}m left`;
        card.querySelector('.acts').addEventListener('click', (e) => {
            const a = e.target.dataset.a;
            if (a === 'open') window.open(it.url, '_blank', 'noopener');
            if (a === 'ics') calendarFile(it);
            if (a === 'del' && confirm('Delete this scheduled gift?')) { api({ action: 'delete', id: it.id }).then(d => { items = d.items; renderList(); }).catch(err => alert(err.message)); }
        });
        box.appendChild(card);
    });
}

/* ---------- start ---------- */
$('scheduleTime').min = toLocalInput(new Date());
counter(); preview();
refresh();
