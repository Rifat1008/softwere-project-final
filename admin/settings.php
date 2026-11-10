<?php
require_once __DIR__ . '/../includes/admin.php';
admin_boot();

$fields = ['site_name' => 100, 'tagline' => 150, 'contact_email' => 150, 'contact_phone' => 40, 'contact_address' => 250, 'footer_text' => 300, 'facebook' => 250, 'instagram' => 250];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    if (($_POST['action'] ?? '') === 'reset_logo') {
        $old = setting('logo');
        setting_set($conn, 'logo', 'assets/img/logo.png'); delete_upload($old);
        flash_set('ok', 'Default logo restored.'); redirect('settings.php');
    }
    $vals = [];
    foreach ($fields as $k => $max) $vals[$k] = mb_substr(trim($_POST[$k] ?? ''), 0, $max);
    if ($vals['site_name'] === '') $errors[] = 'Site name cannot be empty.';
    if ($vals['contact_email'] !== '' && !filter_var($vals['contact_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid contact email.';
    foreach (['facebook', 'instagram'] as $k) if ($vals[$k] !== '' && !preg_match('#^https?://#i', $vals[$k])) $errors[] = ucfirst($k) . ' link must start with http:// or https://';

    $logo = isset($_FILES['logo']) ? save_image_upload($_FILES['logo'], 'site', $errors) : null;
    if ($errors) { if ($logo) delete_upload($logo); flash_set('err', implode(' ', $errors)); }
    else {
        foreach ($vals as $k => $v) setting_set($conn, $k, $v);
        if ($logo) { $old = setting('logo'); setting_set($conn, 'logo', $logo); delete_upload($old); }
        flash_set('ok', 'Settings saved. The website is updated.');
    }
    redirect('settings.php');
}
admin_header('Site settings', 'settings');
$cur = fn($k, $d = '') => e(setting($k, $d));
?>
<form method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="card">
        <h2>Logo &amp; name</h2>
        <div class="inline" style="align-items:center">
            <div style="flex:0 0 auto"><img class="preview" src="../<?= e(site_logo()) ?>" alt="Current logo" style="height:90px"></div>
            <label class="f" style="flex:1">Upload a new logo (PNG with transparent background works best)<input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/gif"></label>
        </div>
        <div class="grid2">
            <label class="f">Website name<input type="text" name="site_name" maxlength="100" value="<?= $cur('site_name', 'Upohar') ?>" required></label>
            <label class="f">Tagline<input type="text" name="tagline" maxlength="150" value="<?= $cur('tagline') ?>"></label>
        </div>
    </div>
    <div class="card">
        <h2>Contact info <small>Shown in the footer of the home page.</small></h2>
        <div class="grid2">
            <label class="f">Email<input type="email" name="contact_email" value="<?= $cur('contact_email') ?>"></label>
            <label class="f">Phone<input type="text" name="contact_phone" value="<?= $cur('contact_phone') ?>"></label>
        </div>
        <label class="f">Address<input type="text" name="contact_address" value="<?= $cur('contact_address') ?>"></label>
        <label class="f">Footer message (optional)<input type="text" name="footer_text" value="<?= $cur('footer_text') ?>"></label>
        <div class="grid2">
            <label class="f">Facebook page link<input type="text" name="facebook" placeholder="https://facebook.com/..." value="<?= $cur('facebook') ?>"></label>
            <label class="f">Instagram link<input type="text" name="instagram" placeholder="https://instagram.com/..." value="<?= $cur('instagram') ?>"></label>
        </div>
    </div>
    <button class="btn" type="submit">Save settings</button>
</form>
<?php if (setting('logo') !== 'assets/img/logo.png'): ?>
    <form method="POST" style="margin-top:14px" data-confirm="Go back to the default logo?"><?= csrf_field() ?><input type="hidden" name="action" value="reset_logo"><button class="btn-line sm">Restore default logo</button></form>
<?php endif; ?>
<?php admin_footer();
