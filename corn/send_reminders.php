<?php
// Run every 10-15 minutes from the command line / cPanel cron:
//   php /path/to/upohar/cron/send_reminders.php
// Emails the people who ticked "Email" when a reminder is due.
// (SMS needs a provider such as a Bangladesh SMS gateway - add it in send_sms() below.)
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

require_once __DIR__ . '/../includes/functions.php';

function send_sms(string $phone, string $text): void {
    // TODO: call your SMS gateway API here.
}

$sql = "SELECT s.*, u.full_name FROM schedules s JOIN users u ON u.id = s.user_id
        WHERE s.reminded = 0 AND s.send_at > NOW()
          AND DATE_SUB(s.send_at, INTERVAL s.remind_days DAY) <= NOW()";
$res = $conn->query($sql);

$mark = $conn->prepare('UPDATE schedules SET reminded = 1 WHERE id = ?');
while ($s = $res->fetch_assoc()) {
    $body = "Hi {$s['full_name']},\n\nReminder: gift for {$s['recipient']}"
          . ($s['occasion'] ? " ({$s['occasion']})" : '')
          . " is due on " . date('d M Y, h:i A', strtotime($s['send_at'])) . ".\n"
          . "Gift link: {$s['gift_url']}\n"
          . ($s['message'] ? "Your message: {$s['message']}\n" : '')
          . "\n- Upohar";
    if ($s['via_email'] && $s['email']) @mail($s['email'], 'Upohar gift reminder', $body, 'From: no-reply@upohar.com');
    if ($s['via_sms'] && $s['phone'])   send_sms($s['phone'], "Upohar: gift for {$s['recipient']} due " . date('d M h:i A', strtotime($s['send_at'])));
    $mark->bind_param('i', $s['id']);
    $mark->execute();
    echo "Reminded schedule #{$s['id']}\n";
}
