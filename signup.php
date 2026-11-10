<?php
 

require_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
        exit;
}

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_ok($_POST['csrf'] ?? null)) { $errors[] = 'Session expired. Please try again.'; }

    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']       ?? '';

    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    } elseif (strlen($full_name) > 100) {
        $errors[] = 'Full name must be 100 characters or fewer.';
    }

    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

   
    if (empty($errors)) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = 'An account with this email already exists.';
        }
        $stmt->close();
    }

    
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare('INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)');
        $stmt->bind_param('sss', $full_name, $email, $hash);

        if ($stmt->execute()) {
            $success = true;
        } else {
            $errors[] = 'Something went wrong. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Upohar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="assets/css/signup.css">
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
            <h1 class="stage-title" id="stageTitle">Join Upohar.</h1>
            <p class="stage-sub" id="stageSub">Create your account and start gifting.</p>
        </section>

        <!-- RIGHT: the form -->
        <section class="card" id="card" <?= $success ? 'data-success="1"' : '' ?>>
            <div class="auth-logo"><img src="<?= e(site_logo()) ?>" alt="Upohar"></div>
            <h2>Create account</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" role="alert">
                    <?php foreach ($errors as $e): ?>
                        <p><?= htmlspecialchars($e) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p>Account created successfully! <a href="login.php">Click here to Login</a>.</p>
                </div>
            <?php else: ?>

            <form id="signupForm" method="POST" action="signup.php" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <input type="text" id="full_name" name="full_name" placeholder=" "
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                           autocomplete="name" maxlength="100" required>
                    <label for="full_name">Full name</label>
                </div>

                <div class="field">
                    <input type="email" id="email" name="email" placeholder=" "
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           autocomplete="email" required>
                    <label for="email">Email address</label>
                </div>

                <div class="field">
                    <input type="password" id="password" name="password" placeholder=" "
                           autocomplete="new-password" required>
                    <label for="password">Password (8+ characters)</label>
                    <button type="button" class="eye-toggle" id="toggle" aria-label="Show password">Show</button>
                </div>

                <div class="strength" aria-hidden="true"><i id="strengthBar"></i></div>
                <p class="strength-label" id="strengthLabel"></p>

                <button type="submit" class="submit" id="submitBtn">
                    <span class="submit-text">Sign Up</span>
                    <span class="spinner"></span>
                </button>

                <p class="footer-link">Already a member? <a href="login.php">Login</a></p>
            </form>

            <?php endif; ?>
        </section>
    </main>

    <script src="assets/js/signup.js"></script>
</body>
</html>

