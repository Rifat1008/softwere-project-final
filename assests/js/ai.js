// Every request goes to api/ai.php, which asks the Python gift AI (trained on our catalog).
const messagesEl = document.getElementById('messages');
const form = document.getElementById('composer');
const input = document.getElementById('aiInput');
const sendBtn = document.getElementById('sendBtn');
const orb = document.getElementById('orb');
const chips = document.querySelectorAll('.chip');
const newChat = document.getElementById('newChat');

let history = [];   // sent to ai.php so follow-ups like "make it cheaper" work
let shown = [];     // ids of gifts already suggested, so "show me more" gives new ones
let busy = false;

function scrollDown() {
    messagesEl.scrollTo({ top: messagesEl.scrollHeight, behavior: 'smooth' });
}

function addUser(text) {
    const m = document.createElement('div');
    m.className = 'msg user';
    const b = document.createElement('div');
    b.className = 'bubble';
    b.textContent = text;
    m.appendChild(b);
    messagesEl.appendChild(m);
    scrollDown();
}

function addAI() {
    const m = document.createElement('div');
    m.className = 'msg ai';
    m.innerHTML = '<div class="avatar"></div><div class="ai-body"><div class="bubble"><span class="typing"><i></i><i></i><i></i></span></div></div>';
    messagesEl.appendChild(m);
    scrollDown();
    return m;
}

// Types the reply out letter by letter
function typewrite(el, text, done) {
    el.textContent = '';
    let i = 0;
    const speed = Math.max(8, Math.min(24, 1400 / text.length));
    (function tick() {
        el.textContent = text.slice(0, ++i);
        scrollDown();
        if (i < text.length) setTimeout(tick, speed); else done && done();
    })();
}

const CSRF = document.querySelector('meta[name="csrf"]').content;

// "AI understood: Female · Birthday · up to $25" so the visitor sees how the message was read
function renderUnderstood(body, items) {
    if (!items || !items.length) return;
    const row = document.createElement('div');
    row.className = 'understood';
    const label = document.createElement('span');
    label.textContent = 'I understood:';
    row.appendChild(label);
    items.forEach(x => { const c = document.createElement('i'); c.textContent = x; row.appendChild(c); });
    body.appendChild(row);
}

async function addToCart(id, btn) {
    btn.disabled = true;
    try {
        const res = await fetch('api/cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ action: 'add', id })
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Could not add.');
        btn.textContent = 'Added \u2713';
        if (window.umSetCounts) window.umSetCounts({ cart: data.cart.length });
    } catch (err) {
        btn.textContent = 'Try again';
        btn.disabled = false;
    }
}

function renderGifts(body, gifts, extra) {
    extra = extra || {};
    renderUnderstood(body, extra.understood);
    if (!gifts.length) return;
    const list = document.createElement('div');
    list.className = 'gift-list';
    gifts.forEach((g, i) => {
        const card = document.createElement('div');
        card.className = 'gift' + (g.top ? ' top' : '');
        card.style.setProperty('--i', i);

        if (g.image || g.emoji) {
            const th = document.createElement('div');
            th.className = 'g-thumb';
            if (g.image) {
                const im = document.createElement('img');
                im.loading = 'lazy'; im.alt = '';
                im.onerror = () => { th.textContent = g.emoji || '\ud83c\udf81'; };
                im.src = g.image;
                th.appendChild(im);
            } else th.textContent = g.emoji;
            card.appendChild(th);
        }
        if (g.top) { const b = document.createElement('span'); b.className = 'best'; b.textContent = 'Top pick'; card.appendChild(b); }

        const tag = document.createElement('span'); tag.className = 'tag'; tag.textContent = g.tag || 'Gift'; card.appendChild(tag);
        const h = document.createElement('h3'); h.textContent = g.name || ''; card.appendChild(h);
        const p = document.createElement('p'); p.textContent = g.why || ''; card.appendChild(p);
        const price = document.createElement('span'); price.className = 'price'; price.textContent = g.price || ''; card.appendChild(price);

        const actions = document.createElement('div');
        actions.className = 'g-actions';
        if (g.id) {
            const add = document.createElement('button');
            add.type = 'button'; add.className = 'add'; add.textContent = 'Add to cart';
            add.addEventListener('click', () => addToCart(g.id, add));
            actions.appendChild(add);
        }
        const find = document.createElement('a');
        find.className = 'find'; find.href = g.link || 'filter.php'; find.textContent = g.id ? 'See in finder' : 'Browse similar gifts';
        actions.appendChild(find);
        card.appendChild(actions);
        list.appendChild(card);
    });
    body.appendChild(list);
    if (extra.browse_url) {
        const all = document.createElement('a');
        all.className = 'browse-all'; all.href = extra.browse_url; all.textContent = 'See all matching gifts \u2192';
        body.appendChild(all);
    }
    scrollDown();
}

function showError(aiMsg, text) {
    aiMsg.className = 'msg ai error';
    aiMsg.querySelector('.bubble').textContent = text;
}

async function ask(text) {
    if (busy || !text.trim()) return;
    busy = true;
    sendBtn.disabled = true;
    document.body.classList.add('chatting');
    newChat.hidden = false;

    addUser(text);
    history.push({ role: 'user', text });
    input.value = '';
    input.style.height = 'auto';

    orb.classList.add('thinking');
    const aiMsg = addAI();
    const body = aiMsg.querySelector('.ai-body');
    const bubble = aiMsg.querySelector('.bubble');

    try {
        const res = await fetch('api/ai.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf"]').content },
            body: JSON.stringify({ messages: history, shown })
        });
        const data = await res.json();
        if (!res.ok || data.error) throw new Error(data.error || 'Something went wrong.');

        const reply = data.reply || 'Here are some ideas.';
        history.push({
            role: 'model',
            text: reply + ((data.gifts || []).length ? ' Suggested: ' + data.gifts.map(g => g.name).join(', ') : '')
        });

        (data.gifts || []).forEach(g => { if (g.id) shown.push(g.id); });

        orb.classList.remove('thinking');
        typewrite(bubble, reply, () => renderGifts(body, data.gifts || [], data));
    } catch (err) {
        orb.classList.remove('thinking');
        history.pop(); // drop the failed question so a retry is clean
        showError(aiMsg, err.message || 'Could not reach the AI. Check your connection and try again.');
    } finally {
        busy = false;
        sendBtn.disabled = false;
        input.focus();
    }
}

form.addEventListener('submit', (e) => { e.preventDefault(); ask(input.value); });

// Enter sends, Shift+Enter makes a new line
input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); ask(input.value); }
});
input.addEventListener('input', () => {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 140) + 'px';
});

chips.forEach(c => c.addEventListener('click', () => ask(c.textContent)));

newChat.addEventListener('click', () => {
    history = [];
    shown = [];
    messagesEl.innerHTML = '';
    document.body.classList.remove('chatting');
    newChat.hidden = true;
    input.focus();
});
