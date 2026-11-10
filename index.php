<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/products.php';
$banners    = home_banners($conn);
$topSellers = top_sellers_week($conn, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(setting('site_name', 'Upohar')) ?> - <?= e(setting('tagline', 'Gift Giving Management')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Hind+Siliguri:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/home.css?v=<?= @filemtime(__DIR__ . '/assets/css/home.css') ?>">
</head>
<body>

<?php require __DIR__ . '/includes/nav_home.php'; ?>

<!-- HERO (text) -->
<header class="hero">
    <div class="hero-content">
        <span class="eyebrow"><i></i> AI ideas &middot; Smart scheduling</span>
        <h1>
            <span class="line"><span>A gift that says</span></span>
            <span class="line"><span>exactly what you feel.</span></span>
        </h1>
        <p class="hero-sub">Find the right present with AI, and schedule it so it arrives on the day that matters.</p>
        <div class="hero-actions">
            <a href="filter.php" class="btn btn-gold">Find a gift</a>
            <a href="schedule.php" class="btn btn-ghost">Schedule a gift</a>
        </div>
    </div>
</header>

<!-- BANNER (below the headline) -->
<section class="banner" aria-label="Upohar highlights">
    <div class="banner-frame">
        <div class="hero-slides" id="heroSlides">
<?php foreach ($banners as $b): ?>
            <div class="slide"><?php if ($b['link']): ?><a href="<?= e($b['link']) ?>"><?php endif; ?><img src="<?= e(img_url($b['image'])) ?>" alt="<?= e($b['alt'] ?: 'Upohar banner') ?>"><?php if ($b['link']): ?></a><?php endif; ?></div>
<?php endforeach; ?>
        </div>
        <button type="button" class="banner-arrow prev" id="bannerPrev" aria-label="Previous banner"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg></button>
        <button type="button" class="banner-arrow next" id="bannerNext" aria-label="Next banner"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg></button>
    </div>
    <div class="hero-dots" id="heroDots"></div>
</section>

<!-- TOP SELLERS OF THE WEEK -->
<section class="topsellers" id="top-sellers">
    <div class="ts-head reveal">
        <span class="ts-eyebrow"><i></i> Last 7 days</span>
        <h2>Top sellers of the week</h2>
        <p>The shops our customers loved most this week. Tap a shop to see its gifts.</p>
    </div>
<?php if ($topSellers): ?>
    <div class="ts-grid">
    <?php foreach ($topSellers as $i => $s): ?>
        <a class="ts-card reveal <?= $i === 0 ? 'first' : '' ?>" href="gifts.php?seller=<?= (int)$s['id'] ?>" style="transition-delay:<?= $i * 80 ?>ms">
            <span class="ts-rank"><?= $i + 1 ?></span>
            <?php if ($i === 0): ?><span class="ts-crown">Top seller</span><?php endif; ?>
            <div class="ts-thumb">
                <?php if ($s['image'] !== ''): ?><img src="<?= e(img_url($s['image'])) ?>" alt="" loading="lazy" onerror="this.remove()"><?php else: ?><span><?= e($s['emoji']) ?></span><?php endif; ?>
            </div>
            <h3><?= e($s['shop_name']) ?></h3>
            <p><?= $s['sold'] > 0 ? '<b>' . (int)$s['sold'] . '</b> gift' . ($s['sold'] === 1 ? '' : 's') . ' sold this week' : '<b>' . (int)$s['listed'] . '</b> gift' . ($s['listed'] === 1 ? '' : 's') . ' listed' ?></p>
            <span class="ts-go">Visit shop</span>
        </a>
    <?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="ts-empty reveal">No shops have joined yet. <a href="seller.php">Open your shop on Upohar</a> and be the first one here.</p>
<?php endif; ?>
</section>

<!-- OCCASIONS MARQUEE -->
<div class="marquee" aria-hidden="true">
    <div class="marquee-track">
        <span>Birthdays</span><span>Anniversaries</span><span>Weddings</span><span>Graduations</span><span>Eid</span><span>Thank-yous</span><span>New homes</span><span>Surprises</span>
        <span>Birthdays</span><span>Anniversaries</span><span>Weddings</span><span>Graduations</span><span>Eid</span><span>Thank-yous</span><span>New homes</span><span>Surprises</span>
    </div>
</div>

<!-- SERVICES -->
<section class="services">
    <div class="section-head reveal">
        <h2>Everything gifting, in one place</h2>
        <p>Choose how you want to start.</p>
    </div>

    <div class="service-grid">
        <a href="gifts.php" class="service reveal" style="--d:0s">
            <img src="assets/img/a.png" alt="Exclusive gift items">
            <div class="service-text"><h3>Exclusive gift items</h3><p>Browse hand-picked presents.</p></div>
        </a>
        <a href="schedule.php" class="service reveal" style="--d:.12s">
            <img src="assets/img/b.png" alt="Schedule a gift">
            <div class="service-text"><h3>Schedule a gift</h3><p>Set the date. We handle the rest.</p></div>
        </a>
        <a href="ai.php" class="service reveal" style="--d:.24s">
            <img src="assets/img/c.png" alt="Find a gift idea">
            <div class="service-text"><h3>Find a gift idea</h3><p>Let AI suggest what fits them.</p></div>
        </a>
        <a href="seller_register.php" class="service reveal" style="--d:.36s">
            <img src="assets/img/d.png" alt="Become a seller">
            <div class="service-text"><h3>Become a seller</h3><p>Sell your products on Upohar.</p></div>
        </a>
    </div>
</section>

<!-- ABOUT -->
<section class="about">
    <div class="about-text reveal">
        <h2>About Upohar</h2>
        <p>A gift is more than an object. It is a bridge between hearts. Upohar brings technology and emotion together so gifting feels smart, personal, and effortless.</p>
        <p>Our AI helps you find the perfect gift, and scheduling means you never miss a special day again.</p>
        <a href="about.php" class="btn btn-wine">Learn more</a>
    </div>

    <ol class="steps">
        <li class="reveal" style="--d:0s"><span class="step-no">1</span><div><h3>Tell us who it's for</h3><p>Add the person, the occasion, and your budget.</p></div></li>
        <li class="reveal" style="--d:.15s"><span class="step-no">2</span><div><h3>Pick from smart ideas</h3><p>Compare suggestions made for that person.</p></div></li>
        <li class="reveal" style="--d:.3s"><span class="step-no">3</span><div><h3>Schedule the surprise</h3><p>Choose the date and your gift arrives on time.</p></div></li>
    </ol>
</section>

<!-- FOOTER / CONTACT -->
<footer class="footer">
    <div class="footer-inner reveal">
        <h2>Contact Info</h2>
        <div class="content-placeholder">
            <?php if (setting('contact_email')): ?><p>Email: <a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email', 'hello@upohar.com')) ?></a></p><?php endif; ?>
            <?php if (setting('contact_phone')): ?><p>Phone: <?= e(setting('contact_phone')) ?></p><?php endif; ?>
            <?php if (setting('contact_address')): ?><p>Address: <?= e(setting('contact_address')) ?></p><?php endif; ?>
            <?php if (setting('footer_text')): ?><p><?= e(setting('footer_text')) ?></p><?php endif; ?>
            <?php if (setting('facebook') || setting('instagram')): ?>
                <p class="footer-social">
                    <?php if (setting('facebook')): ?><a href="<?= e(setting('facebook')) ?>" target="_blank" rel="noopener noreferrer">Facebook</a><?php endif; ?>
                    <?php if (setting('instagram')): ?><a href="<?= e(setting('instagram')) ?>" target="_blank" rel="noopener noreferrer">Instagram</a><?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    <p class="copy">&copy; <span id="year"><?= date('Y') ?></span> <?= e(setting('site_name', 'Upohar')) ?>. All rights reserved.</p>
</footer>

<script src="assets/js/home.js?v=<?= @filemtime(__DIR__ . '/assets/js/home.js') ?>"></script>
</body>
</html>
