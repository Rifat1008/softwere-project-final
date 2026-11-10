<?php require_once __DIR__ . '/includes/functions.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Upohar</title>
    <link rel="stylesheet" href="assets/css/about.css">
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@600&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
</head>
<body>

    <section class="about-container">
        <div class="about-hero scroll-reveal">
            <div class="big-logo-wrapper">
                <img src="<?= e(site_logo()) ?>" alt="Upohar Logo" class="big-logo">
            </div>
            <h1>About Upohar</h1>
            <p class="tagline">Smart Gifting, Beautiful Memories</p>
        </div>

        <div class="about-content">
            <div class="content-box scroll-reveal">
                <h2>Our Mission</h2>
                <p>At <strong>Upohar</strong>, we believe that a gift is more than just an object—it’s a bridge between hearts. Our mission is to merge technology with emotion, making the art of gifting smart, personal, and effortless for everyone.</p>
            </div>

            <div class="content-grid">
                <div class="grid-item scroll-reveal">
                    <div class="icon">✨</div>
                    <h3>Smart Selection</h3>
                    <p>Our AI-powered suggestions help you find the perfect gift based on your specific scenario, ensuring your loved ones get exactly what they cherish.</p>
                </div>
                <div class="grid-item scroll-reveal">
                    <div class="icon">📅</div>
                    <h3>Never Forget</h3>
                    <p>With our reminder and scheduling system, we make sure you never miss a birthday, anniversary, or special moment again.</p>
                </div>
                <div class="grid-item scroll-reveal">
                    <div class="icon">🤝</div>
                    <h3>Empowering Sellers</h3>
                    <p>We provide a premium platform for sellers to showcase their unique products to a community that values quality and thoughtfulness.</p>
                </div>
            </div>
        </div>

        <div class="about-footer scroll-reveal">
            <p>Join us in redefining the way we share joy.</p>
            <a href="index.php" class="back-btn">Explore Upohar</a>
        </div>
    </section>

</body>
</html>