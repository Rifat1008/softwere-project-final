<?php
require_once __DIR__ . '/includes/functions.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ====== SETTINGS: plan prices (BDT) ====== */
$PRICES = ['silver' => 500, 'gold' => 700];

$METHODS = [
    'bkash' => ['name' => 'bKash', 'number' => '01943504257', 'color' => '#e2136e', 'steps' => [
        'Open the bKash app or dial *247#.',
        'Choose "Send Money".',
        'Enter the receiver number: <b>01943504257</b>.',
        'Enter the amount: <b>{AMOUNT} BDT</b>.',
        'Confirm with your PIN. You will get an SMS with the <b>TrxID</b>.',
        'Enter your number and the TrxID below, then submit.',
    ]],
    'nagad' => ['name' => 'Nagad', 'number' => '01864365068', 'color' => '#f6921e', 'steps' => [
        'Open the Nagad app or dial *167#.',
        'Choose "Send Money".',
        'Enter the receiver number: <b>01864365068</b>.',
        'Enter the amount: <b>{AMOUNT} BDT</b>.',
        'Confirm with your PIN. You will get an SMS with the <b>Transaction ID</b>.',
        'Enter your number and the Transaction ID below, then submit.',
    ]],
];

$sid = (int)($_SESSION['pay_seller_id'] ?? 0);
if (!$sid) redirect('seller_login.php');

$st = $conn->prepare('SELECT id, shop_name, plan, requested_plan FROM sellers WHERE id = ? LIMIT 1');
$st->bind_param('i', $sid); $st->execute();
$seller = $st->get_result()->fetch_assoc(); $st->close();
if (!$seller || !isset($PRICES[$seller['requested_plan']]) || $seller['plan'] === $seller['requested_plan']) {
    unset($_SESSION['pay_seller_id']);
    redirect('seller_login.php');
}
$plan   = $seller['requested_plan'];
$amount = $PRICES[$plan];

// latest payment
$st = $conn->prepare('SELECT * FROM seller_payments WHERE seller_id = ? ORDER BY id DESC LIMIT 1');
$st->bind_param('i', $sid); $st->execute();
$last = $st->get_result()->fetch_assoc(); $st->close();
$waiting = $last && $last['status'] === 'pending';

$errors = [];
$old = ['method' => 'bkash', 'sender_number' => '', 'trx_id' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$waiting) {
    if (!csrf_ok($_POST['csrf'] ?? null)) $errors[] = 'Session expired. Please try again.';
    $old['method']        = $_POST['method'] ?? '';
    $old['sender_number'] = trim($_POST['sender_number'] ?? '');
    $old['trx_id']        = strtoupper(trim($_POST['trx_id'] ?? ''));

    if (!isset($METHODS[$old['method']])) $errors[] = 'Please select a payment method.';
    if (!preg_match('/^\+?[0-9\s-]{8,20}$/', $old['sender_number'])) $errors[] = 'Enter the number you sent the money from.';
    if (!preg_match('/^[A-Z0-9]{6,30}$/', $old['trx_id'])) $errors[] = 'Invalid Transaction ID (letters and numbers only, 6-30 characters).';

    if (!$errors) {
        $st = $conn->prepare('SELECT id FROM seller_payments WHERE method = ? AND trx_id = ? LIMIT 1');
        $st->bind_param('ss', $old['method'], $old['trx_id']); $st->execute();
        if ($st->get_result()->num_rows) $errors[] = 'This Transaction ID has already been used.';
        $st->close();
    }
    if (!$errors) {
        $st = $conn->prepare('INSERT INTO seller_payments (seller_id, plan, method, amount, sender_number, trx_id) VALUES (?,?,?,?,?,?)');
        $st->bind_param('ississ', $sid, $plan, $old['method'], $amount, $old['sender_number'], $old['trx_id']);
        $st->execute(); $st->close();
        redirect('seller_payment.php?sent=1');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - UPOHAR</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/seller.css">
    <style>
        .box.pay { max-width:520px; }
        .summary { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:22px; }
        .summary div { padding:12px 8px; text-align:center; background:var(--pale); border:1px solid #FFE680; border-radius:16px; }
        .summary small { display:block; font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; color:#6b6540; font-weight:600; margin-bottom:2px; }
        .summary b { display:block; font-size:.95rem; font-weight:600; word-break:break-word; }
        .summary .amt { color:var(--gold-deep); font-size:1.2rem; font-weight:700; }

        .methods { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px; }
        .method { position:relative; cursor:pointer; display:block; }
        .method input { position:absolute; opacity:0; inset:0; width:100%; height:100%; margin:0; cursor:pointer; }
        .method span { display:block; padding:14px; text-align:center; font-weight:600; border:2px solid #e5d9a8; border-radius:14px; background:#fffef7; transition:.25s; }
        .method:hover span { transform:translateY(-2px); }
        .method.on span { border-color:var(--mc); background:var(--mc); color:#fff; box-shadow:0 8px 18px rgba(0,0,0,.12); }
        .method input:focus-visible + span { outline:2px solid var(--gold); outline-offset:2px; }

        .panel { margin-bottom:20px; padding:18px; border:2px dashed var(--mc); border-radius:18px; background:#fff; }
        .panel[hidden] { display:none; }
        .sendto { font-weight:600; font-size:.85rem; color:#6b6540; margin-bottom:8px; }
        .numrow { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 14px; border-radius:12px; background:var(--pale); margin-bottom:14px; }
        .num { font-size:1.4rem; letter-spacing:.04em; color:var(--mc); font-weight:700; }
        .copy { border:0; padding:7px 16px; border-radius:999px; background:var(--ink); color:var(--gold); font-weight:600; font-size:.78rem; cursor:pointer; }
        .steps { padding-left:20px; font-size:.86rem; color:#555; line-height:1.55; }
        .steps li { padding:3px 0; }
        @media (max-width:480px) { .summary { grid-template-columns:1fr; } .num { font-size:1.15rem; } }
    </style>
</head>
<body>
<div class="center">
    <div class="box pay">
        <div class="brand"><h1>UPOHAR</h1><p>Complete your plan payment</p></div>
        <h2>Payment</h2>

        <div class="summary">
            <div><small>Shop</small><b><?= e($seller['shop_name']) ?></b></div>
            <div><small>Plan</small><b><span class="badge"><?= e(ucfirst($plan)) ?></span></b></div>
            <div><small>Amount</small><b class="amt"><?= (int)$amount ?> BDT</b></div>
        </div>

        <?php if ($waiting): ?>
            <div class="alert ok">
                <p><b>Payment submitted!</b> Our team is verifying your <?= $last['method'] === 'bkash' ? 'bKash' : 'Nagad' ?> transaction <b><?= e($last['trx_id']) ?></b>. Your <?= e(ucfirst($plan)) ?> plan will be activated once it is approved.</p>
            </div>
            <p class="alt"><a href="seller_login.php">Go to Login</a></p>
        <?php else: ?>
            <?php if ($last && $last['status'] === 'rejected'): ?>
                <div class="alert"><p><b>Your previous payment was rejected.</b><?= $last['admin_note'] ? ' Reason: ' . e($last['admin_note']) : '' ?> Please submit again with the correct Transaction ID.</p></div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
            <?php endif; ?>

            <form method="POST" action="seller_payment.php" id="payform">
                <?= csrf_field() ?>
                <div class="methods">
                    <?php foreach ($METHODS as $k => $m): ?>
                        <label class="method" style="--mc:<?= e($m['color']) ?>">
                            <input type="radio" name="method" value="<?= e($k) ?>" <?= $old['method'] === $k ? 'checked' : '' ?>>
                            <span><?= e($m['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($METHODS as $k => $m): ?>
                    <div class="panel" data-panel="<?= e($k) ?>" style="--mc:<?= e($m['color']) ?>">
                        <p class="sendto">Send Money to <?= e($m['name']) ?> (Personal)</p>
                        <div class="numrow">
                            <b class="num"><?= e($m['number']) ?></b>
                            <button type="button" class="copy" data-copy="<?= e($m['number']) ?>">Copy</button>
                        </div>
                        <ol class="steps">
                            <?php foreach ($m['steps'] as $s): ?><li><?= str_replace('{AMOUNT}', (int)$amount, $s) ?></li><?php endforeach; ?>
                        </ol>
                    </div>
                <?php endforeach; ?>

                <div class="input-group"><label>Your <span class="mname">bKash</span> number (the one you sent from)</label>
                    <input type="text" name="sender_number" value="<?= e($old['sender_number']) ?>" placeholder="01XXXXXXXXX" required></div>
                <div class="input-group"><label>Transaction ID</label>
                    <input type="text" name="trx_id" value="<?= e($old['trx_id']) ?>" placeholder="e.g. 8N7A6D5EF3" maxlength="30" style="text-transform:uppercase" required></div>

                <button type="submit" class="btn">Submit Payment</button>
            </form>
            <p class="alt"><a href="seller_login.php">I'll pay later &rarr; Login</a></p>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
    var radios = document.querySelectorAll('input[name=method]');
    var panels = document.querySelectorAll('.panel');
    var names = {bkash: 'bKash', nagad: 'Nagad'};
    function sync() {
        var v = (document.querySelector('input[name=method]:checked') || {}).value;
        panels.forEach(function (p) { p.hidden = p.dataset.panel !== v; });
        document.querySelectorAll('.method').forEach(function (l) { l.classList.toggle('on', l.querySelector('input').checked); });
        var n = document.querySelector('.mname'); if (n && names[v]) n.textContent = names[v];
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); }); sync();
    document.querySelectorAll('.copy').forEach(function (b) {
        b.addEventListener('click', function () {
            if (navigator.clipboard) navigator.clipboard.writeText(b.dataset.copy);
            b.textContent = 'Copied!'; setTimeout(function () { b.textContent = 'Copy'; }, 1500);
        });
    });
})();
</script>
</body>
</html>
