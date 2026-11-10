document.addEventListener("DOMContentLoaded", () => {
    const gift = document.getElementById("gift");
    const pupils = document.querySelectorAll(".pupil");
    const email = document.getElementById("email");
    const password = document.getElementById("password");
    const toggle = document.getElementById("toggle");
    const form = document.getElementById("loginForm");
    const card = document.getElementById("card");
    const submitBtn = document.getElementById("submitBtn");
    const title = document.getElementById("stageTitle");
    const sub = document.getElementById("stageSub");
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    const say = (t, s) => { title.textContent = t; sub.textContent = s; };
    const look = (x, y) => pupils.forEach(p => p.style.transform = `translate(${x}px, ${y}px)`);

    /* ---------- Eyes follow the mouse ---------- */
    document.addEventListener("mousemove", (e) => {
        if (document.activeElement === email) return;
        const r = gift.getBoundingClientRect();
        const dx = e.clientX - (r.left + r.width / 2);
        const dy = e.clientY - (r.top + r.height / 2);
        const d = Math.hypot(dx, dy) || 1;
        look((dx / d) * Math.min(5, d / 40), (dy / d) * Math.min(5, d / 40));
        if (!reduce) gift.style.transform = `rotateY(${dx / 60}deg) rotateX(${-dy / 80}deg)`;
    });

    /* ---------- Gift reacts to the form ---------- */
    email.addEventListener("focus", () => {
        gift.classList.remove("shy", "peek");
        say("Who's there?", "Type your email and I'll take a look.");
    });
    email.addEventListener("input", () => {
        const ratio = Math.min(email.value.length / 28, 1);
        look(-5 + ratio * 10, 4);
    });
    email.addEventListener("blur", () => look(0, 0));

    password.addEventListener("focus", () => {
        gift.classList.add("shy");
        gift.classList.toggle("peek", password.type === "text");
        say("I won't peek!", "Your password stays secret.");
    });
    password.addEventListener("blur", () => {
        gift.classList.remove("shy", "peek");
        say("Welcome back.", "Your gifts are waiting for you.");
    });

    toggle.addEventListener("click", () => {
        const show = password.type === "password";
        password.type = show ? "text" : "password";
        toggle.textContent = show ? "Hide" : "Show";
        toggle.setAttribute("aria-label", show ? "Hide password" : "Show password");
        gift.classList.toggle("peek", show);
        password.focus();
    });

    /* ---------- Error shake (server-side error shown by PHP) ---------- */
    if (document.querySelector(".alert-error")) {
        card.classList.add("shake");
        say("Oops.", "Check your details and try again.");
    }

    /* ---------- Submit: validate, celebrate, then send to login.php ---------- */
    form.addEventListener("submit", (e) => {
        e.preventDefault();
        if (!email.value.trim() || !password.value) {
            card.classList.remove("shake");
            void card.offsetWidth;
            card.classList.add("shake");
            (email.value.trim() ? password : email).focus();
            return;
        }
        submitBtn.classList.add("loading");
        submitBtn.disabled = true;
        gift.classList.remove("shy", "peek");
        gift.classList.add("happy");
        say("Opening up!", "Taking you in...");
        burst();
        setTimeout(() => form.submit(), 750);
    });

    /* ---------- Canvas: ambient sparkles + confetti burst ---------- */
    const canvas = document.getElementById("fx");
    const ctx = canvas.getContext("2d");
    let W, H, sparks = [], confetti = [];
    const colors = ["#FFC400", "#FFD54A", "#FFE066", "#F5B800", "#2a2410"];

    const resize = () => { W = canvas.width = innerWidth; H = canvas.height = innerHeight; };
    resize();
    addEventListener("resize", resize);

    const makeSpark = (initial) => ({
        x: Math.random() * W,
        y: initial ? Math.random() * H : H + 10,
        r: Math.random() * 2 + .6,
        v: Math.random() * .6 + .2,
        o: Math.random() * .6 + .2
    });
    if (!reduce) for (let i = 0; i < 55; i++) sparks.push(makeSpark(true));

    function burst() {
        if (reduce) return;
        const r = gift.getBoundingClientRect();
        const cx = r.left + r.width / 2, cy = r.top + 40;
        for (let i = 0; i < 110; i++) {
            const a = Math.random() * Math.PI * 2, s = Math.random() * 9 + 3;
            confetti.push({
                x: cx, y: cy,
                vx: Math.cos(a) * s, vy: Math.sin(a) * s - 6,
                w: Math.random() * 8 + 4, h: Math.random() * 5 + 3,
                rot: Math.random() * 6, vr: (Math.random() - .5) * .4,
                c: colors[(Math.random() * colors.length) | 0], life: 1
            });
        }
    }

    (function draw() {
        ctx.clearRect(0, 0, W, H);
        sparks.forEach((p, i) => {
            p.y -= p.v;
            ctx.globalAlpha = p.o;
            ctx.fillStyle = "#FFC400";
            ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 7); ctx.fill();
            if (p.y < -10) sparks[i] = makeSpark(false);
        });
        confetti = confetti.filter(c => c.life > 0);
        confetti.forEach(c => {
            c.vy += .28; c.vx *= .99;
            c.x += c.vx; c.y += c.vy; c.rot += c.vr; c.life -= .008;
            ctx.save();
            ctx.globalAlpha = Math.max(c.life, 0);
            ctx.translate(c.x, c.y); ctx.rotate(c.rot);
            ctx.fillStyle = c.c;
            ctx.fillRect(-c.w / 2, -c.h / 2, c.w, c.h);
            ctx.restore();
        });
        requestAnimationFrame(draw);
    })();
});
