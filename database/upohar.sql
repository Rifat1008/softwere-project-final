-- ============================================================
--  UPOHAR  -  Gift Giving Management
--  Database: upohar        (import this file in phpMyAdmin)
-- ============================================================
CREATE DATABASE IF NOT EXISTS upohar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE upohar;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS ai_chats, schedules, order_items, orders, products, filter_gifts, sellers, users;
SET FOREIGN_KEY_CHECKS = 1;

-- Customers ---------------------------------------------------
CREATE TABLE users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login  DATETIME NULL
) ENGINE=InnoDB;

-- Sellers -----------------------------------------------------
-- plan is what the seller really has; requested_plan is what they picked
-- on the pricing page (paid plans are switched on by the admin after payment).
CREATE TABLE sellers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL UNIQUE,
    phone           VARCHAR(30)  NOT NULL,
    shop_name       VARCHAR(120) NOT NULL,
    password        VARCHAR(255) NOT NULL,
    plan            ENUM('free','silver','gold') NOT NULL DEFAULT 'free',
    requested_plan  ENUM('free','silver','gold') NOT NULL DEFAULT 'free',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Gift shop products (Exclusive gift items page) -------------
CREATE TABLE products (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seller_id       INT UNSIGNED NULL,
    name            VARCHAR(150) NOT NULL,
    category        VARCHAR(30)  NOT NULL,
    category_label  VARCHAR(40)  NOT NULL,
    price           DECIMAL(10,2) NOT NULL,
    old_price       DECIMAL(10,2) NULL,
    badge           VARCHAR(20)  NULL,
    badge_label     VARCHAR(30)  NULL,
    stars           TINYINT UNSIGNED NOT NULL DEFAULT 5,
    reviews_k       DECIMAL(4,1) NOT NULL DEFAULT 0,
    description     VARCHAR(500) NOT NULL DEFAULT '',
    image           VARCHAR(500) NOT NULL DEFAULT '',
    emoji           VARCHAR(16)  NOT NULL DEFAULT '🎁',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (category), INDEX (seller_id),
    CONSTRAINT fk_products_seller FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Find-a-gift finder (filter page) ----------------------------
CREATE TABLE filter_gifts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    image       VARCHAR(600) NOT NULL,
    gender      ENUM('male','female') NOT NULL,
    age_group   ENUM('teen','adult')  NOT NULL,
    occasion    VARCHAR(30) NOT NULL,
    hobby       VARCHAR(30) NOT NULL,
    profession  VARCHAR(30) NOT NULL,
    INDEX (gender), INDEX (age_group), INDEX (occasion), INDEX (hobby), INDEX (profession)
) ENGINE=InnoDB;

-- Orders (checkout from the cart) -----------------------------
CREATE TABLE orders (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    total       DECIMAL(10,2) NOT NULL,
    status      ENUM('pending','paid','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id    INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED NULL,
    name        VARCHAR(150) NOT NULL,
    price       DECIMAL(10,2) NOT NULL,
    qty         INT UNSIGNED NOT NULL,
    CONSTRAINT fk_items_order   FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Scheduled gifts ---------------------------------------------
CREATE TABLE schedules (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    recipient   VARCHAR(40)  NOT NULL,
    occasion    VARCHAR(30)  NOT NULL DEFAULT '',
    gift_url    VARCHAR(500) NOT NULL,
    image_path  VARCHAR(255) NULL,
    send_at     DATETIME NOT NULL,
    remind_days TINYINT UNSIGNED NOT NULL DEFAULT 0,
    via_sms     TINYINT(1) NOT NULL DEFAULT 0,
    via_email   TINYINT(1) NOT NULL DEFAULT 0,
    via_app     TINYINT(1) NOT NULL DEFAULT 1,
    phone       VARCHAR(20)  NULL,
    email       VARCHAR(150) NULL,
    message     VARCHAR(200) NOT NULL DEFAULT '',
    reminded    TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id), INDEX (send_at),
    CONSTRAINT fk_sched_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- AI suggester log --------------------------------------------
CREATE TABLE ai_chats (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    prompt      VARCHAR(1000) NOT NULL,
    reply       TEXT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ai_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Seed: products -----------------------------------------------
INSERT INTO products (id,name,category,category_label,price,old_price,badge,badge_label,stars,reviews_k,description,image,emoji) VALUES
(1,'Chanel No. 5 Eau de Parfum','perfume','Perfume',299,349,'luxury','Luxury',5,2.4,'The world''s most iconic fragrance. Floral aldehydic, timeless elegance in a gilded bottle.','https://images.unsplash.com/photo-1541643600914-78b084683702?w=600&h=600&fit=crop','🌸'),
(2,'Tom Ford Black Orchid','perfume','Perfume',245,290,'hot','Hot',5,1.8,'A rich, dark, and sensual fragrance with black truffle, ylang-ylang, and orchid notes.','https://images.unsplash.com/photo-1592945403537-a63e2f6e3eff?w=600&h=600&fit=crop','🌑'),
(3,'Dior Sauvage EDP','perfume','Perfume',189,220,'new','New',5,3.1,'Bold and noble. Fresh pepper bergamot top notes, raw ambroxan and cedar base.','https://images.unsplash.com/photo-1583922606661-0822ed0bd916?w=600&h=600&fit=crop','🏜️'),
(4,'YSL Libre Intense','perfume','Perfume',220,260,'hot','Hot',4,1.2,'Freedom never smelled so bold — mandarin, lavender, and musks in pure alchemy.','https://images.unsplash.com/photo-1615634260167-c8cdede054de?w=600&h=600&fit=crop','🌿'),
(5,'Jo Malone Peony & Blush Suede','perfume','Perfume',175,NULL,'luxury','Luxury',5,0.9,'Opulent peony layered on red apple, white rose, and suede — quintessentially British.','https://images.unsplash.com/photo-1590156562745-5bcd3cb30cc5?w=600&h=600&fit=crop','🌷'),
(6,'Versace Eros EDT','perfume','Perfume',129,155,'sale','Sale',4,2,'The power of love. Mint, Italian lemon, green apple, and tonka beans unite.','https://images.unsplash.com/photo-1519681393784-d120267933ba?w=600&h=600&fit=crop','💙'),
(7,'Apple AirPods Pro (2nd Gen)','gadget','Gadget',249,279,'hot','Hot',5,12.5,'Adaptive transparency, active noise cancellation, MagSafe charging case. Pure audio bliss.','https://images.unsplash.com/photo-1600294037681-c80b4cb5b434?w=600&h=600&fit=crop','🎧'),
(8,'Sony WH-1000XM5 Headphones','gadget','Gadget',379,420,'luxury','Luxury',5,8.3,'Industry-leading noise cancellation, 30-hour battery, crystal-clear call quality.','https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&h=600&fit=crop','🎧'),
(9,'Apple Watch Ultra 2','gadget','Gadget',799,NULL,'luxury','Luxury',5,4.7,'Titanium case, precision dual-frequency GPS, up to 60 hours battery. The ultimate adventure watch.','https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&h=600&fit=crop','⌚'),
(10,'iPad Mini 6th Gen (Wi-Fi)','gadget','Gadget',499,549,'new','New',5,6.1,'All-screen design, A15 Bionic chip, Apple Pencil support. Perfect creative companion.','https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=600&h=600&fit=crop','📱'),
(11,'Kindle Paperwhite Signature','gadget','Gadget',189,210,'sale','Sale',4,9.2,'32 GB, wireless charging, auto-adjusting front light. 10 weeks battery life.','https://images.unsplash.com/photo-1512436991641-6745cdb1723f?w=600&h=600&fit=crop','📖'),
(12,'GoPro Hero 12 Black','gadget','Gadget',349,399,'hot','Hot',5,5.4,'5.3K60 video, HyperSmooth 6.0, waterproof to 33 ft. The world in your hands.','https://images.unsplash.com/photo-1502920917128-1aa500764cbd?w=600&h=600&fit=crop','📷'),
(13,'DJI Mini 4 Pro Drone','gadget','Gadget',759,849,'new','New',5,2.9,'4K HDR video, omnidirectional obstacle sensing, 34-min flight time under 249g.','https://images.unsplash.com/photo-1473968512647-3e447244af8f?w=600&h=600&fit=crop','🚁'),
(14,'JBL Charge 5 Speaker','gadget','Gadget',159,180,'hot','Hot',4,14.6,'Powerful JBL Pro Sound, 20h battery, IP67 waterproof, powerbank feature.','https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=600&h=600&fit=crop','🔊'),
(15,'Nike Air Jordan 1 Retro High OG','sneakers','Sneakers',180,210,'hot','Hot',5,18.7,'The sneaker that changed everything. Premium leather, Air-Sole cushioning, icon status.','https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&h=600&fit=crop','👟'),
(16,'Adidas Yeezy Boost 350 V2','sneakers','Sneakers',230,280,'luxury','Luxury',5,7.8,'Kanye West x Adidas. Primeknit upper, Boost cushioning. Streetwear royalty.','https://images.unsplash.com/photo-1491553895911-0055eca6402d?w=600&h=600&fit=crop','👟'),
(17,'New Balance 2002R','sneakers','Sneakers',150,NULL,'new','New',5,5.6,'Superior comfort meets street style. ABZORB midsole with N-ergy heel cushioning.','https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=600&h=600&fit=crop','👟'),
(18,'Nike Air Max 270','sneakers','Sneakers',150,170,'sale','Sale',4,11.3,'Largest Air unit ever in a Nike lifestyle shoe. Breathable mesh upper, heel pull tab.','https://images.unsplash.com/photo-1460353581641-37baddab0fa2?w=600&h=600&fit=crop','👟'),
(19,'Puma RS-X Reinvention','sneakers','Sneakers',110,130,'hot','Hot',4,4.4,'Bold ''80s-inspired chunky silhouette. RS foam cushioning, mesh and leather upper.','https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=600&h=600&fit=crop','👟'),
(20,'Converse Chuck 70 Hi','sneakers','Sneakers',95,NULL,'new','New',4,16.2,'Premium canvas, OrthoLite cushioning, vintage rubber sole. Elevated classic.','https://images.unsplash.com/photo-1520256862855-398228c41684?w=600&h=600&fit=crop','👟'),
(21,'Vans Old Skool Pro','sneakers','Sneakers',85,100,'sale','Sale',4,13.8,'Waffle outsole, UltraCush HD insole, suede and canvas upper. Skate heritage.','https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?w=600&h=600&fit=crop','👟'),
(22,'Godiva Signature Gold Ballotin','chocolate','Chocolate',89,105,'luxury','Luxury',5,3.5,'27 exquisite Belgian chocolates in Godiva''s iconic gold box. Perfect gift.','https://images.unsplash.com/photo-1606312619070-d48b4c652a52?w=600&h=600&fit=crop','🍫'),
(23,'Lindt EXCELLENCE 90% Dark Box','chocolate','Chocolate',48,60,'hot','Hot',4,7.1,'Intense, bold dark chocolate with exceptional cocoa from premium origins.','https://images.unsplash.com/photo-1548907040-4bea42c73e83?w=600&h=600&fit=crop','🍫'),
(24,'Ferrero Rocher Grand Collection','chocolate','Chocolate',55,65,'hot','Hot',5,21,'48-piece luxury collection of Ferrero Rocher, Raffaello, and Rondnoir pralines.','https://images.unsplash.com/photo-1481391243133-f96216dcb5d2?w=600&h=600&fit=crop','🎁'),
(25,'Teuscher Champagne Truffles','chocolate','Chocolate',78,NULL,'luxury','Luxury',5,1.4,'Swiss master chocolatiers since 1932. Dom Pérignon infused ganache in dark chocolate.','https://images.unsplash.com/photo-1511381939415-e44cd2b011f3?w=600&h=600&fit=crop','🫧'),
(26,'Valrhona Cœur de Guanaja Set','chocolate','Chocolate',65,80,'new','New',5,0.8,'70% cocoa, liquid chocolate center. The world''s bitterest, most complex dark chocolate.','https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=600&h=600&fit=crop','🍫'),
(27,'Fossil Gen 6 Hybrid Smartwatch','watch','Watch',249,295,'new','New',4,3.9,'Wellness tracking meets analog style. Alexa built-in, 2-week battery life.','https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&h=600&fit=crop','⌚'),
(28,'Seiko Presage Automatic','watch','Watch',450,520,'luxury','Luxury',5,2.1,'Japanese Prestige Hardlex crystal, enamel dial, automatic winding, sapphire case back.','https://images.unsplash.com/photo-1547996160-81dfa63595aa?w=600&h=600&fit=crop','⌚'),
(29,'Casio G-Shock GWG-2000','watch','Watch',299,350,'hot','Hot',5,6.8,'Master of G series. Solar powered, atomic timekeeping, 200m water resistance.','https://images.unsplash.com/photo-1434493789847-2f02dc6ca35d?w=600&h=600&fit=crop','⌚'),
(30,'Tumi Alpha 3 Backpack','bag','Bag',595,695,'luxury','Luxury',5,2.6,'Premium FXT ballistic nylon, laptop sleeve, TSA lock. The executive traveler''s choice.','https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=600&h=600&fit=crop','🎒'),
(31,'Kate Spade Sam Icon Bag','bag','Bag',348,420,'hot','Hot',4,4.3,'Iconic Sam silhouette in pebbled leather. Gold spade hardware, removable crossbody strap.','https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=600&h=600&fit=crop','👜'),
(32,'Samsonite Omni PC Luggage Set','bag','Bag',429,499,'sale','Sale',4,8,'3-piece hardside set, TSA-approved lock, spinner wheels, scratch-resistant texture.','https://images.unsplash.com/photo-1580237072353-751a8a5b2561?w=600&h=600&fit=crop','🧳'),
(33,'Luxury Wellness Spa Gift Set','gadget','Gift Set',139,165,'new','New',5,1.9,'Premium bath salts, essential oils, scented candles, and a plush robe — pure indulgence.','https://images.unsplash.com/photo-1560750588-73207b1ef5b8?w=600&h=600&fit=crop','🛁');

-- Seed: finder gifts -------------------------------------------
INSERT INTO filter_gifts (name,image,gender,age_group,occasion,hobby,profession) VALUES
('Vintage Pen','https://www.dromgooles.com/cdn/shop/files/TornadoFPNikolaTesla.png?v=1684505315&width=533','female','adult','birthday','tech','engineer'),
('RGB Lamp','https://www.quntis.com/cdn/shop/files/4_5b98205a-3a72-46cf-88ed-db6d39dcc68a.jpg?v=1744788876&width=1600','female','teen','birthday','tech','student'),
('Flower Vase','https://m.media-amazon.com/images/I/717COgJSyAL.jpg','female','adult','birthday','tech','engineer'),
('Wallet','https://www.vibrantbd.com/cdn/shop/files/9446118.jpg?v=1760849716','female','teen','birthday','tech','student'),
('Foam Pillow','https://img.drz.lazcdn.com/static/bd/p/004b827527ce93f0922a2aaf39758803.jpg_720x720q80.jpg','female','adult','birthday','tech','engineer'),
('Earbuds','https://diamu.com.bd/wp-content/uploads/2025/01/Beats-Solo-Buds-True-Wireless-Bluetooth-Earbuds.jpg','female','teen','birthday','tech','student'),
('Indoor Plant','https://hips.hearstapps.com/hmg-prod/images/aglaonema-foliage-in-pot-at-living-home-spring-snow-royalty-free-image-1705612028.jpg?crop=0.668xw:1.00xh;0.0385xw,0&resize=640:*','female','adult','birthday','tech','engineer'),
('Coffee Kit','https://www.tglcompany.com/cdn/shop/files/tgl-craft-coffee-kit-v60-brewing.jpg?v=1773144810','female','teen','birthday','tech','student'),
('Vintage Pen','https://cdn.othoba.com/images/thumbs/1286940_hero-vintage-fountain-pen-fine-nib.jpeg','female','adult','birthday','tech','engineer'),
('RGB Lamp','https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSbkypGWryd4I452tgIP7jRai1L7O3jJ_aa_A&s','female','teen','birthday','tech','student'),
('Flower Vase','https://img.drz.lazcdn.com/static/bd/p/585b3e36dd5dc28b455823363005458f.jpg_720x720q80.jpg','female','adult','birthday','tech','engineer'),
('Wallet','https://www.fastfashionbd.com/cdn/shop/files/2_1e8ae9b3-f19a-462d-b401-0f50e2a5c64e.jpg?v=1706088483&width=1946','female','teen','birthday','tech','student'),
('Foam Pillow','https://cdn.thewirecutter.com/wp-content/media/2025/12/BEST-MEMORY-FOAM-PILLOWS-2048px-6824-3x2-1.jpg?auto=webp&quality=75&crop=1:1,smart&width=1024','female','adult','birthday','tech','engineer'),
('Earbuds','https://gadgetstudiobd.com/wp-content/uploads/2024/03/earfun-air-2-best-budget-hi-res-audio-wireless-earbuds-1-800x800.webp','female','teen','birthday','tech','student'),
('Indoor Plant','https://hips.hearstapps.com/hmg-prod/images/aglaonema-foliage-in-pot-at-living-home-spring-snow-royalty-free-image-1705612028.jpg?crop=0.668xw:1.00xh;0.0385xw,0&resize=640:*','female','adult','birthday','tech','engineer'),
('Coffee Kit','https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQ2YpMcaHeelE4NYbuTEmi6v5hj8qPtlO4vXQ&s','female','teen','birthday','tech','student'),
('Vintage Pen','https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQixvgmvtECC8MHI6WSZL4D8ZqHjE9vLPGhow&s','female','adult','birthday','tech','engineer'),
('perfume','https://smartdeal.com.bd/public/uploads/all/xPSm1OadiCisei2mujn1VaS7IPKxKutFAhebKUVV.jpg','female','teen','birthday','tech','student'),
('Flower Vase','https://cdn.othoba.com/images/thumbs/0973960_orchid-and-rose-flower-tub-with-vase-flower-vase-with-artificial-flower-for-tabletop-living-roomoutd.jpeg','female','adult','birthday','tech','engineer'),
('jersey','https://cdn.othoba.com/images/thumbs/1401274_bangladesh-away-thai-premium-football-jersey-for-men-bangladesh-football-away-jersey-2025.png','female','teen','birthday','tech','student'),
('cap','https://img.drz.lazcdn.com/static/bd/p/2091c75fd6bc296b6317df7c04ba841f.jpg_720x720q80.jpg','female','adult','birthday','tech','engineer'),
('Earbuds','https://img.drz.lazcdn.com/static/bd/p/2d6637f4fc80f74a2b17dcb17cecdd8f.jpg_720x720q80.jpg','female','teen','birthday','tech','student'),
('Indoor Plant','https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR-cHq8OBNIm1gpxT2NZLyu2prcUkYgGQooMg&s','female','adult','birthday','tech','engineer'),
('Coffee Kit','https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQYN_FF8zrJO-GEkuCPtBvhcjXwvQbsi_qbLA&s','female','teen','birthday','tech','student'),
('Vintage Pen','https://i.etsystatic.com/28423863/r/il/1590be/4491397939/il_570xN.4491397939_bm3t.jpg','female','adult','birthday','tech','engineer'),
('Gift Box','26.jpg','female','teen','wedding','gardening','student'),
('Botanical Vase','27.jpg','female','adult','wedding','gardening','engineer'),
('Gift Box','28.jpg','female','teen','wedding','gardening','student'),
('Botanical Vase','29.jpg','female','adult','wedding','gardening','engineer'),
('Gift Box','30.jpg','female','teen','wedding','gardening','student'),
('Botanical Vase','31.jpg','female','adult','wedding','gardening','engineer'),
('Gift Box','32.jpg','female','teen','wedding','gardening','student'),
('Botanical Vase','33.jpg','female','adult','wedding','gardening','engineer'),
('Gift Box','34.jpg','female','teen','wedding','gardening','student'),
('Botanical Vase','35.jpg','female','adult','wedding','gardening','engineer'),
('Gift Box','36.jpg','female','teen','wedding','gardening','student'),
('Botanical Vase','37.jpg','female','adult','wedding','gardening','engineer'),
('Gift Box','38.jpg','female','teen','wedding','gardening','student'),
('Botanical Vase','39.jpg','female','adult','wedding','gardening','engineer'),
('Gift Box','40.jpg','female','adult','wedding','gardening','engineer'),
('Botanical Vase','41.jpg','female','adult','wedding','gardening','engineer'),
('Gift Box','42.jpg','female','adult','wedding','gardening','engineer'),
('Botanical Vase','43.jpg','female','adult','wedding','gardening','engineer'),
('Gift Box','44.jpg','female','adult','wedding','gardening','engineer'),
('Botanical Vase','45.jpg','female','adult','wedding','gardening','engineer'),
('Gift Box','46.jpg','female','adult','wedding','gardening','engineer'),
('Botanical Vase','47.jpg','female','adult','wedding','gardening','engineer'),
('Gift Box','48.jpg','female','adult','wedding','gardening','engineer'),
('Botanical Vase','49.jpg','female','adult','wedding','gardening','engineer'),
('Gift Box','50.jpg','female','adult','wedding','gardening','engineer'),
('Gaming Set','51.jpg','male','adult','birthday','tech','engineer'),
('Leather Kit','52.jpg','male','teen','birthday','tech','student'),
('Gaming Set','53.jpg','male','adult','birthday','tech','engineer'),
('Leather Kit','54.jpg','male','teen','birthday','tech','student'),
('Gaming Set','55.jpg','male','adult','birthday','tech','engineer'),
('Leather Kit','56.jpg','male','teen','birthday','tech','student'),
('Gaming Set','57.jpg','male','adult','birthday','tech','engineer'),
('Leather Kit','58.jpg','male','teen','birthday','tech','student'),
('Gaming Set','59.jpg','male','adult','birthday','tech','engineer'),
('Leather Kit','60.jpg','male','teen','birthday','tech','student'),
('Groom Kit','61.jpg','male','adult','wedding','gardening','engineer'),
('Tech Shaver','62.jpg','male','teen','wedding','gardening','student'),
('Leather Care','63.jpg','male','adult','wedding','gardening','engineer'),
('Smart Watch','64.jpg','male','teen','wedding','gardening','student'),
('Perfume Box','65.jpg','male','adult','wedding','gardening','engineer'),
('Headphone','66.jpg','male','teen','wedding','gardening','student'),
('Cufflinks','67.jpg','male','adult','wedding','gardening','engineer'),
('Speaker','68.jpg','male','teen','wedding','gardening','student'),
('Tie Box','69.jpg','male','adult','wedding','gardening','engineer'),
('Game Console','70.jpg','male','teen','wedding','gardening','student'),
('Groom Tool','71.jpg','male','adult','wedding','gardening','engineer'),
('Powerbank','72.jpg','male','teen','wedding','gardening','student'),
('Leather Bag','73.jpg','male','adult','wedding','gardening','engineer'),
('Charging Dock','74.jpg','male','teen','wedding','gardening','student'),
('Sunglasses','75.jpg','male','adult','wedding','gardening','engineer'),
('Bookend','76.jpg','male','teen','official','reading','student'),
('File Binder','77.jpg','male','adult','official','reading','engineer'),
('Reading Light','78.jpg','male','teen','official','reading','student'),
('Desk Pad','79.jpg','male','adult','official','reading','engineer'),
('Bookmark Pen','80.jpg','male','teen','official','reading','student'),
('Ballpoint Kit','81.jpg','male','adult','official','reading','engineer'),
('Book Stand','82.jpg','male','teen','official','reading','student'),
('Desk Clock','83.jpg','male','adult','official','reading','engineer'),
('Memo Pad','84.jpg','male','teen','official','reading','student'),
('Card Pack','85.jpg','male','adult','official','reading','engineer'),
('Cable Wrap','86.jpg','male','teen','official','reading','student'),
('Laptop Sleeve','87.jpg','male','adult','official','reading','engineer'),
('Desk Stand','88.jpg','male','teen','official','reading','student'),
('Pen Holder','89.jpg','male','adult','official','reading','engineer'),
('Wireless Pad','90.jpg','male','teen','official','reading','student'),
('Key Holder','91.jpg','male','adult','official','reading','engineer'),
('Cup Warmer','92.jpg','male','adult','official','reading','engineer'),
('Briefcase','93.jpg','male','adult','official','reading','engineer'),
('Footrest','94.jpg','male','adult','official','reading','engineer'),
('Laser Pointer','95.jpg','male','adult','official','reading','engineer'),
('Desk Divider','96.jpg','male','adult','official','reading','engineer'),
('Office Candle','97.jpg','male','adult','official','reading','engineer'),
('Ruler Set','98.jpg','male','adult','official','reading','engineer'),
('Reading Glass','https://www.blindasabat.com.au/cdn/shop/products/reading-glasses-r078-24.jpg?v=1683434035','male','adult','official','reading','engineer'),
('Tech Pack','https://rokbucket.rokomari.io/ProductNew20190903/260X372/Havit_KB380L_Gaming_Wired_RGB_Mechanical-Havit-50d78-319187.jpg','male','adult','official','reading','engineer');
