<?php require_once __DIR__ . '/includes/functions.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf" content="<?= e(csrf_token()) ?>">
    <title>AI Gift Suggester - Upohar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/ai-chat.css">
</head>
<body>

    <?php $links = [['Home', 'index.php'], ['Gift Shop', 'gifts.php'], ['Find a Gift', 'filter.php'], ['Schedule Gift', 'schedule.php']]; require __DIR__ . '/includes/nav_top.php'; ?>

    <main class="chat-page">
        <div class="glow"></div>

        <!-- Living orb + greeting -->
        <section class="hero" id="hero">
            <div class="orb" id="orb"><span></span></div>
            <h1>Who are we gifting today?</h1>
            <p>Tell me the person, the occasion and your budget. I'll find three ideas.</p>
        </section>

        <!-- Conversation -->
        <div class="messages" id="messages" aria-live="polite"></div>

        <!-- Composer -->
        <div class="composer-wrap">
            <div class="chips" id="chips">
                <button type="button" class="chip">A birthday gift for my mom</button>
                <button type="button" class="chip">Wedding gift under ৳3000</button>
                <button type="button" class="chip">For a friend who loves tech</button>
                <button type="button" class="chip">Anniversary surprise for my wife</button>
            </div>

            <form class="composer" id="composer">
                <textarea id="aiInput" rows="1" maxlength="500" placeholder="Describe the person or the occasion..." aria-label="Describe your gift scenario"></textarea>
                <button type="submit" class="send" id="sendBtn" aria-label="Send">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </form>
            <button type="button" class="new-chat" id="newChat" hidden>Start a new chat</button>
        </div>
    </main>

    <script src="assets/js/ai.js"></script>
</body>
</html>
