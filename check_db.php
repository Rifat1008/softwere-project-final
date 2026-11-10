<?php
// Upohar database checker. Open: http://localhost/upohar/upohar/check_db.php
// It shows the REAL reason why the site cannot connect. DELETE this file when you are done.
mysqli_report(MYSQLI_REPORT_OFF);
require __DIR__ . '/config/config.php';

function line($ok, $text) { echo '<p style="margin:6px 0">' . ($ok ? '✅' : '❌') . ' ' . htmlspecialchars($text) . '</p>'; }
echo '<body style="font-family:system-ui,sans-serif;max-width:760px;margin:30px auto;line-height:1.5"><h2>Upohar database check</h2>';
echo '<p>Settings in config/config.php: host <b>' . htmlspecialchars(DB_HOST) . '</b>, user <b>' . htmlspecialchars(DB_USER) . '</b>, database <b>' . htmlspecialchars(DB_NAME) . '</b>, password ' . (DB_PASS === '' ? '<b>(empty)</b>' : '<b>(set)</b>') . '</p>';

line(extension_loaded('mysqli'), 'PHP mysqli extension is ' . (extension_loaded('mysqli') ? 'on' : 'OFF (enable extension=mysqli in php.ini)'));

// 1) can we reach the MySQL server at all?
$srv = @new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($srv->connect_errno) {
    line(false, 'Cannot log in to the MySQL server: [' . $srv->connect_errno . '] ' . $srv->connect_error);
    $hints = [
        2002 => 'MySQL is NOT running (or runs on another port). Open the XAMPP Control Panel and press Start next to MySQL. If it turns red again, another program is using port 3306: click Config > my.ini and change the port, then use DB_HOST "127.0.0.1:3307" (your port) in config/config.php.',
        1045 => 'Wrong user or password. In XAMPP the default is user "root" and an EMPTY password. If you set a password in phpMyAdmin, put it in DB_PASS in config/config.php.',
        2006 => 'MySQL stopped while connecting. Restart MySQL in the XAMPP Control Panel.',
        1130 => 'This user is not allowed to connect from this host. Use user "root" on localhost.',
    ];
    echo '<p style="background:#fff8db;border:1px solid #ffc400;padding:12px;border-radius:10px"><b>What to do:</b> ' . ($hints[$srv->connect_errno] ?? 'Check host, user and password in config/config.php.') . '</p>';
    exit;
}
line(true, 'MySQL server is running (version ' . $srv->server_info . ')');

// 2) does the database exist?
$db = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_errno) {
    line(false, 'Database "' . DB_NAME . '" cannot be opened: [' . $db->connect_errno . '] ' . $db->connect_error);
    echo '<p style="background:#fff8db;border:1px solid #ffc400;padding:12px;border-radius:10px"><b>What to do:</b> in phpMyAdmin check the database name (it must be exactly <b>' . htmlspecialchars(DB_NAME) . '</b>) and import database/upohar_safe.sql, then upohar_update.sql, then upohar_admin.sql.</p>';
    exit;
}
line(true, 'Database "' . DB_NAME . '" opens fine');

// 3) are all tables there?
$need = ['users' => 'upohar_safe.sql', 'sellers' => 'upohar_safe.sql', 'products' => 'upohar_safe.sql', 'orders' => 'upohar_safe.sql', 'order_items' => 'upohar_safe.sql', 'schedules' => 'upohar_safe.sql', 'ai_chats' => 'upohar_safe.sql',
         'cart_items' => 'upohar_update.sql', 'wishlist' => 'upohar_update.sql',
         'admins' => 'upohar_admin.sql', 'settings' => 'upohar_admin.sql', 'banners' => 'upohar_admin.sql', 'categories' => 'upohar_admin.sql', 'filter_options' => 'upohar_admin.sql', 'product_tags' => 'upohar_admin.sql'];
$have = [];
foreach ($db->query('SHOW TABLES') as $r) $have[] = array_values($r)[0];
$missing = [];
foreach ($need as $t => $file) {
    if (in_array($t, $have, true)) { $n = $db->query("SELECT COUNT(*) FROM `$t`")->fetch_row()[0]; line(true, "$t ($n rows)"); }
    else { line(false, "$t is MISSING. Import database/$file"); $missing[$file] = true; }
}
echo $missing
    ? '<p style="background:#fff8db;border:1px solid #ffc400;padding:12px;border-radius:10px"><b>Import these files in phpMyAdmin (in this order):</b> ' . htmlspecialchars(implode(', ', array_keys($missing))) . '</p>'
    : '<p style="background:#f0fff4;border:1px solid #7bc99a;padding:12px;border-radius:10px"><b>Everything is fine.</b> The website should work now. Delete check_db.php.</p>';
