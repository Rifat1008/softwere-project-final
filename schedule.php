<?php require_once __DIR__ . '/includes/functions.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf" content="<?= e(csrf_token()) ?>">
    <title>Schedule Gift - Upohar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/schedule.css">
</head>
<body>

<?php $links = [['Home', 'index.php'], ['Gift Shop', 'gifts.php'], ['Find a Gift', 'filter.php'], ['AI Suggestion', 'ai.php']]; require __DIR__ . '/includes/nav_top.php'; ?>

<header class="head">
    <div class="blob b1"></div><div class="blob b2"></div>
    <div class="head-inner">
        <h1>Schedule a gift</h1>
        <p>Pick the gift and the day. We'll remind you in time.</p>
        <?php if (!is_logged_in()): ?>
        <p class="notice">Only registered users can use this offer. <a href="login.php?next=schedule.php">Login</a> or <a href="signup.php">Sign up</a></p>
        <?php endif; ?>
    </div>
</header>

<main class="wrap">

    <div class="tabs" role="tablist">
        <button type="button" class="tab active" data-tab="new" role="tab">New schedule</button>
        <button type="button" class="tab" data-tab="list" role="tab">My scheduled gifts <span class="count" id="count">0</span></button>
    </div>

    <!-- NEW SCHEDULE -->
    <section id="panel-new" class="grid">

        <form class="wizard" id="form" novalidate>
            <ol class="steps" id="steps">
                <li class="on"><span>1</span>Gift</li>
                <li><span>2</span>When</li>
                <li><span>3</span>Notify</li>
            </ol>
            <div class="bar"><i id="bar"></i></div>

            <!-- Step 1 -->
            <fieldset class="step active" data-step="1">
                <div class="field">
                    <label for="recipient">Who is it for?</label>
                    <input type="text" id="recipient" placeholder="e.g. Ayesha" maxlength="40">
                    <small class="err" data-for="recipient"></small>
                </div>

                <div class="field">
                    <span class="lbl">Occasion</span>
                    <div class="chips" id="occasions">
                        <button type="button" class="chip" data-v="Birthday">Birthday</button>
                        <button type="button" class="chip" data-v="Anniversary">Anniversary</button>
                        <button type="button" class="chip" data-v="Wedding">Wedding</button>
                        <button type="button" class="chip" data-v="Eid">Eid</button>
                        <button type="button" class="chip" data-v="Just because">Just because</button>
                    </div>
                </div>

                <div class="field">
                    <label for="giftUrl">Gift link</label>
                    <input type="text" id="giftUrl" placeholder="https://example.com/item">
                    <small class="err" data-for="giftUrl"></small>
                </div>

                <div class="field">
                    <span class="lbl">Gift photo (optional)</span>
                    <label class="drop" for="giftImage" id="drop">
                        <img id="thumb" alt="" hidden>
                        <span id="dropText">Click to choose a photo</span>
                    </label>
                    <input type="file" id="giftImage" accept="image/*" hidden>
                    <small class="err" data-for="giftImage"></small>
                </div>
            </fieldset>

            <!-- Step 2 -->
            <fieldset class="step" data-step="2">
                <div class="field">
                    <span class="lbl">Quick pick</span>
                    <div class="chips" id="presets">
                        <button type="button" class="chip" data-days="1">Tomorrow</button>
                        <button type="button" class="chip" data-days="7">In 1 week</button>
                        <button type="button" class="chip" data-days="30">In 1 month</button>
                    </div>
                </div>

                <div class="field">
                    <label for="scheduleTime">Date and time</label>
                    <input type="datetime-local" id="scheduleTime">
                    <small class="err" data-for="scheduleTime"></small>
                </div>

                <div class="field">
                    <span class="lbl">Remind me ahead</span>
                    <div class="chips" id="ahead">
                        <button type="button" class="chip active" data-v="0">On the day</button>
                        <button type="button" class="chip" data-v="1">1 day before</button>
                        <button type="button" class="chip" data-v="3">3 days before</button>
                        <button type="button" class="chip" data-v="7">1 week before</button>
                    </div>
                </div>
            </fieldset>

            <!-- Step 3 -->
            <fieldset class="step" data-step="3">
                <span class="lbl">How should we remind you?</span>
                <div class="channels">
                    <label class="channel"><input type="checkbox" id="sms"><b>SMS</b><small>Text message</small></label>
                    <label class="channel"><input type="checkbox" id="gmail"><b>Email</b><small>Gmail or any email</small></label>
                    <label class="channel"><input type="checkbox" id="app" checked><b>App</b><small>Notification</small></label>
                </div>
                <small class="err" data-for="channels"></small>

                <div class="field reveal" id="phoneField" hidden>
                    <label for="phone">Phone number</label>
                    <input type="text" id="phone" placeholder="+8801XXXXXXXXX">
                    <small class="err" data-for="phone"></small>
                </div>

                <div class="field reveal" id="emailField" hidden>
                    <label for="email">Email address</label>
                    <input type="email" id="email" placeholder="example@gmail.com">
                    <small class="err" data-for="email"></small>
                </div>

                <div class="field">
                    <label for="message">Your surprise message</label>
                    <textarea id="message" maxlength="200" placeholder="Write something sweet..."></textarea>
                    <div class="msg-foot">
                        <div class="chips small" id="templates">
                            <button type="button" class="chip" data-t="Happy birthday! Hope your day is full of joy.">Birthday</button>
                            <button type="button" class="chip" data-t="Happy anniversary! Here's to many more years together.">Anniversary</button>
                            <button type="button" class="chip" data-t="Thinking of you. This gift is just a small thank you.">Thank you</button>
                        </div>
                        <span class="counter" id="counter">0/200</span>
                    </div>
                </div>
            </fieldset>

            <div class="nav">
                <button type="button" class="btn ghost" id="back" hidden>Back</button>
                <button type="button" class="btn gold" id="next">Next</button>
            </div>
            <div id="status" class="status" aria-live="polite"></div>
        </form>

        <!-- LIVE PREVIEW -->
        <aside class="preview" aria-label="Preview">
            <div class="ticket">
                <div class="ticket-img" id="pImgBox"><img id="pImg" alt="" hidden><span id="pImgText">Your gift photo</span></div>
                <div class="ticket-body">
                    <span class="tag" id="pOcc">Occasion</span>
                    <h3 id="pName">For someone special</h3>
                    <div class="count-row" id="pCount">
                        <div><b id="cd">--</b><small>days</small></div>
                        <div><b id="ch">--</b><small>hours</small></div>
                        <div><b id="cm">--</b><small>min</small></div>
                        <div><b id="cs">--</b><small>sec</small></div>
                    </div>
                    <p class="when" id="pWhen">Pick a date to start the countdown</p>
                    <blockquote id="pMsg">Your message will appear here.</blockquote>
                    <div class="via" id="pVia"></div>
                </div>
            </div>
        </aside>
    </section>

    <!-- MY GIFTS -->
    <section id="panel-list" hidden>
        <div class="list" id="list"></div>
    </section>
</main>

<!-- SUCCESS -->
<div class="done" id="done" hidden>
    <div class="done-card">
        <svg viewBox="0 0 52 52" class="tick"><circle cx="26" cy="26" r="24"/><path d="M14 27l8 8 16-17"/></svg>
        <h2>Gift scheduled!</h2>
        <p id="doneText"></p>
        <div class="done-actions">
            <button type="button" class="btn gold" id="viewList">View my gifts</button>
            <button type="button" class="btn ghost" id="another">Schedule another</button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer_page.php'; ?>

<script>const LOGGED_IN = <?= is_logged_in() ? 'true' : 'false' ?>;</script>
<script src="assets/js/schedule.js"></script>
</body>
</html>
