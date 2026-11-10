<?php
// Wishlist API (saved in MySQL table "wishlist").
//  GET  -> {ids: [...]}            POST {id} -> toggles that product on/off
require_once __DIR__ . '/../includes/functions.php';

function wish_ids(mysqli $conn, int $uid): array {
    $st = $conn->prepare('SELECT product_id FROM wishlist WHERE user_id = ? ORDER BY id DESC');
    $st->bind_param('i', $uid); $st->execute();
    $ids = array_map(fn($r) => (int)$r['product_id'], $st->get_result()->fetch_all(MYSQLI_ASSOC));
    $st->close();
    return $ids;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_out(['ids' => is_logged_in() ? wish_ids($conn, (int)$_SESSION['user_id']) : []]);
}

api_require_post();
if (!is_logged_in()) json_out(['error' => 'Please log in to use your wishlist.', 'login' => true], 401);

$uid = (int)$_SESSION['user_id'];
$pid = (int)(api_body()['id'] ?? 0);

$st = $conn->prepare('SELECT id FROM products WHERE id = ? AND is_active = 1');
$st->bind_param('i', $pid); $st->execute();
if (!$st->get_result()->num_rows) json_out(['error' => 'Product not found.'], 404);
$st->close();

$st = $conn->prepare('SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?');
$st->bind_param('ii', $uid, $pid); $st->execute();
$exists = $st->get_result()->num_rows > 0;
$st->close();

if ($exists) {
    $q = $conn->prepare('DELETE FROM wishlist WHERE user_id = ? AND product_id = ?');
} else {
    $q = $conn->prepare('INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)');
}
$q->bind_param('ii', $uid, $pid); $q->execute(); $q->close();

$ids = wish_ids($conn, $uid);
json_out(['ok' => true, 'active' => !$exists, 'ids' => $ids, 'count' => count($ids)]);
