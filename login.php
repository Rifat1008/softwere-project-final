<?php

require_once __DIR__ . '/includes/functions.php';

// Already logged in → skip to dashboard
$next = safe_next($_POST['next'] ?? $_GET['next'] ?? null);

if (isset($_SESSION['user_id'])) {
    header('Location: ' . $next);
        exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_ok($_POST['csrf'] ?? null)) { $errors[] = 'Session expired. Please try again.'; }

    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';

    // ── Basic validation ──────────────────────────────────────
    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    }

    // ── DB lookup ─────────────────────────────────────────────
    if (empty($errors)) {
        $stmt = $conn->prepare('SELECT id, full_name, password FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Invalid email or password. Please try again.';
        } else {
            // ── Success: update last_login, set session, redirect ──
            $upd = $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
            $upd->bind_param('i', $user['id']);
            $upd->execute();
            $upd->close();

            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            cart_merge_guest($conn, (int)$user['id']);   // keep items added before logging in

            header('Location: ' . $next);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Upohar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css">
    <style>
        /* Fix: browser autofill overlapping the floating label + blue autofill background */
        .field input:-webkit-autofill,
        .field input:-webkit-autofill:hover,
        .field input:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 1000px #fff inset !important;
            -webkit-text-fill-color: #2a2410 !important;
            caret-color: #2a2410;
            font-family: 'Poppins', sans-serif !important;
            font-size: 1rem;
            transition: background-color 9999s ease-in-out 0s;
        }
        /* label would sit on top of the autofilled value, so hide it while autofilled */
        .field input:-webkit-autofill ~ label { opacity: 0; visibility: hidden; }
    </style>
</head>
<body>
    <canvas id="fx" aria-hidden="true"></canvas>
    <div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div>

    <a href="index.php" class="back">&larr; Back to home</a>

    <main class="login">

        <!-- LEFT: the gift box that reacts to you -->
        <section class="stage" aria-hidden="true">
            <div class="gift" id="gift">
                <div class="lid" id="lid">
                    <span class="bow bow-l"></span><span class="bow bow-r"></span><span class="knot"></span>
                </div>
                <div class="box">
                    <span class="ribbon"></span>
                    <div class="face">
                        <span class="eye"><i class="pupil"></i></span>
                        <span class="eye"><i class="pupil"></i></span>
                    </div>
                    <span class="blush l"></span><span class="blush r"></span>
                </div>
                <div class="shadow"></div>
            </div>
            <h1 class="stage-title" id="stageTitle">Welcome back.</h1>
            <p class="stage-sub" id="stageSub">Your gifts are waiting for you.</p>
        </section>

        <!-- RIGHT: the form -->
        <section class="card" id="card">
            <div class="auth-logo"><img src="<?= e(site_logo()) ?>" alt="Upohar"></div>
            <h2>Login</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" role="alert">
                    <?php foreach ($errors as $e): ?>
                        <p><?= htmlspecialchars($e) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="login.php" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">
                <div class="field">
                    <input type="email" id="email" name="email" placeholder=" "
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           autocomplete="email" required>
                    <label for="email">Email address</label>
                </div>

                <div class="field">
                    <input type="password" id="password" name="password" placeholder=" "
                           autocomplete="current-password" required>
                    <label for="password">Password</label>
                    <button type="button" class="eye-toggle" id="toggle" aria-label="Show password">Show</button>
                </div>

                <button type="submit" class="submit" id="submitBtn">
                    <span class="submit-text">Login</span>
                    <span class="spinner"></span>
                </button>

                <p class="footer-link">New here? <a href="signup.php">Create Account</a></p>
            </form>
        </section>
    </main>

    <script src="assets/js/login.js"></script>
</body>
</html>
