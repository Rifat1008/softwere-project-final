<?php
// Scheduled gifts API: list / create / delete (logged-in users only).
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) json_out(['error' => 'Please log in to schedule gifts.', 'login' => true], 401);
$uid = (int)$_SESSION['user_id'];

function row_out(array $r): array {
    return [
        'id'       => (int)$r['id'],
        'recipient'=> $r['recipient'],
        'occasion' => $r['occasion'],
        'url'      => $r['gift_url'],
        'img'      => $r['image_path'] ? $r['image_path'] : '',
        'time'     => (new DateTime($r['send_at']))->format(DATE_ATOM),
        'ahead'    => (int)$r['remind_days'],
        'sms'      => (bool)$r['via_sms'],
        'gmail'    => (bool)$r['via_email'],
        'app'      => (bool)$r['via_app'],
        'phone'    => $r['phone'] ?? '',
        'email'    => $r['email'] ?? '',
        'message'  => $r['message'],
    ];
}

function list_for(mysqli $conn, int $uid): array {
    $st = $conn->prepare('SELECT * FROM schedules WHERE user_id = ? ORDER BY send_at ASC');
    $st->bind_param('i', $uid); $st->execute();
    $out = array_map('row_out', $st->get_result()->fetch_all(MYSQLI_ASSOC));
    $st->close();
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') json_out(['items' => list_for($conn, $uid)]);

api_require_post();
$b = api_body();
$action = $b['action'] ?? '';

if ($action === 'delete') {
    $id = (int)($b['id'] ?? 0);
    $st = $conn->prepare('SELECT image_path FROM schedules WHERE id = ? AND user_id = ?');
    $st->bind_param('ii', $id, $uid); $st->execute();
    $row = $st->get_result()->fetch_assoc(); $st->close();
    if ($row) {
        $del = $conn->prepare('DELETE FROM schedules WHERE id = ? AND user_id = ?');
        $del->bind_param('ii', $id, $uid); $del->execute(); $del->close();
        if ($row['image_path']) {
            $file = __DIR__ . '/../' . $row['image_path'];
            if (is_file($file) && strpos(realpath($file), realpath(__DIR__ . '/../uploads/schedules')) === 0) unlink($file);
        }
    }
    json_out(['items' => list_for($conn, $uid)]);
}

if ($action !== 'create') json_out(['error' => 'Unknown action.'], 400);

/* ---------- validate ---------- */
$errors = [];
$recipient = trim((string)($b['recipient'] ?? ''));
$occasion  = trim((string)($b['occasion'] ?? ''));
$url       = trim((string)($b['url'] ?? ''));
$ahead     = (int)($b['ahead'] ?? 0);
$sms   = !empty($b['sms']);  $gmail = !empty($b['gmail']);  $app = !empty($b['app']);
$phone = preg_replace('/[\s-]/', '', (string)($b['phone'] ?? ''));
$email = trim((string)($b['email'] ?? ''));
$message = trim((string)($b['message'] ?? ''));

if ($recipient === '' || mb_strlen($recipient) > 40) $errors[] = 'Recipient name is required (max 40 characters).';
if (!in_array($occasion, ['', 'Birthday', 'Anniversary', 'Wedding', 'Eid', 'Just because'], true)) $errors[] = 'Invalid occasion.';
if (!preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL) || strlen($url) > 500) $errors[] = 'Enter a valid gift link starting with http:// or https://';
if (!in_array($ahead, [0, 1, 3, 7], true)) $errors[] = 'Invalid reminder time.';
if (!$sms && !$gmail && !$app) $errors[] = 'Choose at least one way to be reminded.';
if ($sms && !preg_match('/^(\+?88)?01[3-9]\d{8}$/', $phone)) $errors[] = 'Enter a valid Bangladesh phone number.';
if ($gmail && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
if (mb_strlen($message) > 200) $errors[] = 'Message is too long (max 200 characters).';

try {
    $when = new DateTime((string)($b['time'] ?? ''));
    $when->setTimezone(new DateTimeZone(date_default_timezone_get()));
    if ($when <= new DateTime('now')) $errors[] = 'Pick a time in the future.';
} catch (Throwable $ex) { $errors[] = 'Choose a valid date and time.'; $when = null; }

$cnt = $conn->prepare('SELECT COUNT(*) FROM schedules WHERE user_id = ?');
$cnt->bind_param('i', $uid); $cnt->execute();
if ((int)$cnt->get_result()->fetch_row()[0] >= 50) $errors[] = 'You reached the limit of 50 scheduled gifts.';
$cnt->close();

/* ---------- optional photo: JPEG data URL -> file ---------- */
$imagePath = null;
$img = (string)($b['img'] ?? '');
if ($img !== '' && !$errors) {
    if (!preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#', $img, $m) || strlen($m[1]) > 1500000) {
        $errors[] = 'Photo must be a JPEG under about 1 MB.';
    } else {
        $bin = base64_decode($m[1], true);
        $info = $bin ? @getimagesizefromstring($bin) : false;
        if (!$info || $info[2] !== IMAGETYPE_JPEG) {
            $errors[] = 'The photo file is not a valid image.';
        } else {
            $dir = __DIR__ . '/../uploads/schedules';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $name = bin2hex(random_bytes(12)) . '.jpg';
            file_put_contents($dir . '/' . $name, $bin);
            $imagePath = 'uploads/schedules/' . $name;
        }
    }
}

if ($errors) json_out(['error' => implode(' ', $errors)], 422);

$sendAt = $when->format('Y-m-d H:i:s');
$phoneDb = $sms ? $phone : null;
$emailDb = $gmail ? $email : null;
$smsI = (int)$sms; $gmI = (int)$gmail; $apI = (int)$app;

$st = $conn->prepare('INSERT INTO schedules (user_id, recipient, occasion, gift_url, image_path, send_at, remind_days, via_sms, via_email, via_app, phone, email, message) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
$st->bind_param('isssssiiiisss', $uid, $recipient, $occasion, $url, $imagePath, $sendAt, $ahead, $smsI, $gmI, $apI, $phoneDb, $emailDb, $message);
$st->execute();
$st->close();

json_out(['ok' => true, 'items' => list_for($conn, $uid)]);
