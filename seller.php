<?php require_once __DIR__ . '/includes/functions.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UPOHAR Seller Plans</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/seller.css">
</head>
<body>
<div class="pricing-container">
    <div class="top-bar">
        <h1>Grow Your Brand with UPOHAR</h1>
        <div class="top-actions">
            <a href="index.php" class="ghost-link">Home</a>
            <a href="seller_login.php" class="seller-login-btn">Seller Login</a>
        </div>
    </div>

    <div class="pricing-wrapper">
        <div class="price-box">
            <h3>Free</h3>
            <div class="price">0 BDT<span>/month</span></div>
            <ul class="features">
                <li>5 Products Listing</li>
                <li>3 Photos per Product</li>
                <li>Monthly Feedback</li>
            </ul>
            <a href="seller_register.php?plan=free" class="plan-btn">Start Free</a>
        </div>

        <div class="price-box">
            <h3>Silver</h3>
            <div class="price">300 BDT<span>/month</span></div>
            <ul class="features">
                <li>15 Products Listing</li>
                <li>10 Photos per Product</li>
                <li>Discount Price Showing</li>
                <li>Monthly Feedback</li>
                <li>Admin Support</li>
            </ul>
            <a href="seller_register.php?plan=silver" class="plan-btn">Choose Silver</a>
        </div>

        <div class="price-box gold">
            <div class="popular-tag">Recommended</div>
            <h3>Gold</h3>
            <div class="price">700 BDT<span>/month</span></div>
            <ul class="features">
                <li>Unlimited Products</li>
                <li>Unlimited Photos &amp; Videos</li>
                <li>Discount Coupons</li>
                <li>Bestseller List Feature</li>
                <li>Priority Admin Support</li>
            </ul>
            <a href="seller_register.php?plan=gold" class="plan-btn">Choose Gold</a>
        </div>
    </div>
</div>
</body>
</html>
