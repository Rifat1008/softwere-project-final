<?php
// Server-side bridge: the browser talks to this file, and only this file knows the API key.
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_client.php';
header('Content-Type: application/json; charset=utf-8');

function fail($code, $msg) {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

api_require_post();

/* save the question + answer in MySQL (table ai_chats) and send it to the browser */
function ai_respond(array $out, string $prompt): void {
    global $conn;
    $uid = is_logged_in() ? (int)$_SESSION['user_id'] : null;
    $replyText = json_encode($out, JSON_UNESCAPED_UNICODE);
    $prompt = mb_substr($prompt, 0, 1000);
    $log = $conn->prepare('INSERT INTO ai_chats (user_id, prompt, reply) VALUES (?, ?, ?)');
    $log->bind_param('iss', $uid, $prompt, $replyText);
    $log->execute();
    $log->close();
    echo $replyText;
    exit;
}

// Simple rate limit: one request every 2 seconds per visitor
$now = microtime(true);
if (isset($_SESSION['ai_last']) && $now - $_SESSION['ai_last'] < 2) fail(429, 'Please wait a moment.');
$_SESSION['ai_last'] = $now;

$body = json_decode(file_get_contents('php://input'), true);
$messages = $body['messages'] ?? [];
if (!is_array($messages) || !count($messages)) fail(400, 'Write something first.');

/* 1) the Python gift AI trained on our catalog (ai_service/) */
$userTexts = [];
foreach (array_slice($messages, -8) as $m) {
    $text = mb_substr(trim((string)($m['text'] ?? '')), 0, 500);
    if (($m['role'] ?? 'user') !== 'model' && $text !== '') $userTexts[] = ['role' => 'user', 'text' => $text];
}
$shown = array_values(array_filter(array_map('intval', is_array($body['shown'] ?? null) ? array_slice($body['shown'], -60) : [])));
if ($userTexts) {
    $r = ai_service_call('/suggest', ['messages' => $userTexts, 'shown' => $shown, 'limit' => 3], 20);
    if ($r['status'] === 200 && isset($r['data']['reply'])) {
        $out = $r['data'];
        $out['gifts'] = is_array($out['gifts'] ?? null) ? $out['gifts'] : [];
        ai_respond($out, end($userTexts)['text']);
    }
    if ($r['status'] > 0 && $r['status'] !== 200) {
        error_log('AI service error ' . $r['status'] . ': ' . json_encode($r['data']));
        fail(502, $r['data']['error'] ?? 'The AI had a problem. Try again.');
    }
}

/* 2) the Python service is not running: fall back to Gemini if a key is set */
if (GEMINI_API_KEY === 'PASTE_YOUR_NEW_KEY_HERE') {
    fail(503, 'The gift assistant is taking a short break. Please try again soon, or use Find a Gift to browse.'
        . (admin_logged_in() ? ' (Admin: start ai_service/start_ai.bat)' : ''));
}

$contents = [];
foreach (array_slice($messages, -8) as $m) {
    $role = ($m['role'] ?? '') === 'model' ? 'model' : 'user';
    $text = mb_substr(trim((string)($m['text'] ?? '')), 0, 1000);
    if ($text !== '') $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
}
if (!count($contents) || $contents[count($contents) - 1]['role'] !== 'user') fail(400, 'Write something first.');

$system = "You are the gift concierge for Upohar, a gift website in Bangladesh. "
    . "Reply ONLY with JSON: {\"reply\": string, \"gifts\": [{\"name\": string, \"why\": string, \"price\": string, \"tag\": string}]}. "
    . "reply is 1-2 warm sentences. Give exactly 3 gifts unless the user asks for a different number. "
    . "why is at most 25 words. price is an estimate in BDT like '৳1,500 - ৳2,500'. tag is one word (for example Birthday, Tech, Decor). "
    . "If the message is not about gifts or lacks key details, ask ONE short question in reply and return gifts as []. "
    . "Answer in the same language the user writes in (English, Bangla or Banglish). Keep it concise and professional.";

$payload = [
    'systemInstruction' => ['parts' => [['text' => $system]]],
    'contents' => $contents,
    'generationConfig' => ['responseMimeType' => 'application/json', 'maxOutputTokens' => 2048, 'temperature' => 0.9],
];

$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . GEMINI_API_KEY],
    CURLOPT_POSTFIELDS => json_encode($payload),
]);
$raw = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($raw === false) { error_log('AI curl error: ' . $curlErr); fail(502, 'Could not reach the AI service.'); }

$data = json_decode($raw, true);
if ($status !== 200) { error_log('AI API error ' . $status . ': ' . $raw); fail(502, 'The AI service returned an error. Try again.'); }

$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
$out = json_decode($text, true);
if (!is_array($out) || !isset($out['reply'])) $out = ['reply' => $text !== '' ? $text : 'I could not think of anything. Try again?', 'gifts' => []];
if (!isset($out['gifts']) || !is_array($out['gifts'])) $out['gifts'] = [];

ai_respond($out, (string)$contents[count($contents) - 1]['parts'][0]['text']);
