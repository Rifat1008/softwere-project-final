document.addEventListener("DOMContentLoaded", () => {

    /* ---------- Navbar: solid background on scroll ---------- */
    const navbar = document.getElementById("navbar");
    const onScroll = () => navbar.classList.toggle("scrolled", window.scrollY > 50);
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();

    /* ---------- Sliding highlight behind nav links ---------- */
    const navList = document.getElementById("navLinks");
    const pill = document.createElement("span");
    pill.className = "nav-pill";
    navList.prepend(pill);
    navList.querySelectorAll("a:not(.btn-login):not(.btn-signup):not(.um-item)").forEach(a => {
        a.addEventListener("mouseenter", () => {
            pill.style.left = a.offsetLeft + "px";
            pill.style.width = a.offsetWidth + "px";
            pill.classList.add("show");
        });
    });
    navList.addEventListener("mouseleave", () => pill.classList.remove("show"));

    /* ---------- Mobile menu ---------- */
    const toggle = document.getElementById("menuToggle");
    const links = document.getElementById("navLinks");

    const setMenu = (open) => {
        toggle.classList.toggle("open", open);
        links.classList.toggle("open", open);
        navbar.classList.toggle("menu-open", open);
        toggle.setAttribute("aria-expanded", open);
    };
    toggle.addEventListener("click", () => setMenu(!links.classList.contains("open")));
    links.querySelectorAll("a").forEach(a => a.addEventListener("click", () => setMenu(false)));

    /* ---------- Hero slideshow ---------- */
    const slides = document.querySelectorAll("#heroSlides .slide");
    const dotsBox = document.getElementById("heroDots");
    let current = 0;
    let timer;

    slides.forEach((_, i) => {
        const dot = document.createElement("button");
        dot.setAttribute("aria-label", "Go to slide " + (i + 1));
        dot.addEventListener("click", () => { goTo(i); restart(); });
        dotsBox.appendChild(dot);
    });
    const dots = dotsBox.querySelectorAll("button");

    function goTo(index) {
        slides[current].classList.remove("active");
        dots[current].classList.remove("active");
        current = (index + slides.length) % slides.length;
        slides[current].classList.add("active");
        dots[current].classList.add("active");
    }
    function restart() {
        clearInterval(timer);
        timer = setInterval(() => goTo(current + 1), 5000);
    }

    if (slides.length) {
        goTo(0);
        restart();

        document.getElementById("bannerPrev").addEventListener("click", () => { goTo(current - 1); restart(); });
        document.getElementById("bannerNext").addEventListener("click", () => { goTo(current + 1); restart(); });

        // pause while the visitor is looking at a banner
        const frame = document.querySelector(".banner-frame");
        frame.addEventListener("mouseenter", () => clearInterval(timer));
        frame.addEventListener("mouseleave", restart);
    }

    /* ---------- Scroll reveal (plays once) ---------- */
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("active");
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    document.querySelectorAll(".reveal").forEach(el => observer.observe(el));

    /* ---------- Footer year ---------- */
    const year = document.getElementById("year");
    if (year) year.textContent = new Date().getFullYear();
});
