<?php
require_once __DIR__ . '/../includes/admin.php';
admin_boot();
$me = (int)$_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'password') {
        $st = $conn->prepare('SELECT password FROM admins WHERE id = ?'); $st->bind_param('i', $me); $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $new = $_POST['new'] ?? '';
        if (!$row || !password_verify($_POST['current'] ?? '', $row['password'])) flash_set('err', 'Your current password is wrong.');
        elseif (strlen($new) < 10) flash_set('err', 'Use at least 10 characters for an admin password.');
        elseif ($new !== ($_POST['new2'] ?? '')) flash_set('err', 'The two new passwords do not match.');
        else {
            $h = password_hash($new, PASSWORD_DEFAULT);
            $u = $conn->prepare('UPDATE admins SET password = ? WHERE id = ?'); $u->bind_param('si', $h, $me); $u->execute();
            session_regenerate_id(true); flash_set('ok', 'Password changed.');
        }
    } elseif ($act === 'add') {
        $name = trim($_POST['name'] ?? ''); $email = trim($_POST['email'] ?? ''); $pw = $_POST['password'] ?? '';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 10) flash_set('err', 'Enter a name, a valid email and a password of 10+ characters.');
        else {
            $h = password_hash($pw, PASSWORD_DEFAULT);
            $i = $conn->prepare('INSERT IGNORE INTO admins (name, email, password) VALUES (?,?,?)'); $i->bind_param('sss', $name, $email, $h); $i->execute();
            flash_set($i->affected_rows ? 'ok' : 'err', $i->affected_rows ? 'Admin added.' : 'An admin with that email already exists.');
        }
    } elseif ($act === 'remove') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === $me) flash_set('err', 'You cannot remove yourself.');
        else { $conn->query('DELETE FROM admins WHERE id = ' . $id); flash_set('ok', 'Admin removed.'); }
    }
    redirect('password.php');
}
$admins = $conn->query('SELECT id, name, email, last_login FROM admins ORDER BY id')->fetch_all(MYSQLI_ASSOC);
$default = (bool)$conn->query("SELECT 1 FROM admins WHERE email = 'admin@upohar.com'")->num_rows;
admin_header('Admins & password', 'password');
?>
<?php if ($default): ?><div class="flash err">The default admin (admin@upohar.com / Admin@12345) still exists. Change its password below, or add your own admin and remove the default one.</div><?php endif; ?>
<div class="grid2">
    <form method="POST" class="card"><h2>Change my password</h2><?= csrf_field() ?><input type="hidden" name="action" value="password">
        <label class="f">Current password<input type="password" name="current" required></label>
        <label class="f">New password (10+ characters)<input type="password" name="new" minlength="10" required></label>
        <label class="f">Repeat new password<input type="password" name="new2" minlength="10" required></label>
        <button class="btn">Change password</button></form>
    <div class="card"><h2>Admins</h2>
        <?php foreach ($admins as $a): ?>
            <div class="inline" style="justify-content:space-between;padding:8px 0;border-bottom:1px dashed var(--line)"><span><b><?= e($a['name']) ?></b><br><small class="muted"><?= e($a['email']) ?></small></span>
                <?php if ((int)$a['id'] !== $me): ?><form method="POST" data-confirm="Remove this admin?" style="flex:0 0 auto"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn-del sm">Remove</button></form><?php else: ?><span class="pill approved">You</span><?php endif; ?></div>
        <?php endforeach; ?>
        <form method="POST" style="margin-top:16px"><?= csrf_field() ?><input type="hidden" name="action" value="add"><h2 style="font-size:1rem">Add an admin</h2>
            <label class="f">Name<input type="text" name="name" required></label><label class="f">Email<input type="email" name="email" required></label><label class="f">Password (10+ characters)<input type="password" name="password" minlength="10" required></label>
            <button class="btn sm">Add admin</button></form>
    </div>
</div>
<?php admin_footer();
