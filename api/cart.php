<?php
// Cart API.
//  - Logged in  : cart is saved in MySQL (table cart_items) and survives logout/login.
//  - Guest      : cart lives in the session; it is merged into the saved cart at login.
// Checkout buys ONLY the selected items (ids); everything else stays in the cart.
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/products.php';

/* [product_id => qty] */
function cart_raw(mysqli $conn): array {
    if (!is_logged_in()) return $_SESSION['cart'] ?? [];
    $uid = (int)$_SESSION['user_id'];
    $st = $conn->prepare('SELECT product_id, qty FROM cart_items WHERE user_id = ? ORDER BY id ASC');
    $st->bind_param('i', $uid); $st->execute();
    $out = [];
    foreach ($st->get_result() as $r) $out[(int)$r['product_id']] = (int)$r['qty'];
    $st->close();
    return $out;
}

function cart_set(mysqli $conn, int $pid, int $qty): void {
    $qty = min(99, $qty);
    if (!is_logged_in()) {
        if ($qty <= 0) unset($_SESSION['cart'][$pid]); else $_SESSION['cart'][$pid] = $qty;
        return;
    }
    $uid = (int)$_SESSION['user_id'];
    if ($qty <= 0) {
        $st = $conn->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
        $st->bind_param('ii', $uid, $pid);
    } else {
        $st = $conn->prepare('INSERT INTO cart_items (user_id, product_id, qty) VALUES (?,?,?) ON DUPLICATE KEY UPDATE qty = VALUES(qty)');
        $st->bind_param('iii', $uid, $pid, $qty);
    }
    $st->execute(); $st->close();
}

function cart_clear(mysqli $conn): void {
    if (!is_logged_in()) { $_SESSION['cart'] = []; return; }
    $uid = (int)$_SESSION['user_id'];
    $st = $conn->prepare('DELETE FROM cart_items WHERE user_id = ?');
    $st->bind_param('i', $uid); $st->execute(); $st->close();
}

function cart_payload(mysqli $conn): array {
    $cart = cart_raw($conn);
    $items = [];
    if ($cart) {
        $in  = implode(',', array_map('intval', array_keys($cart)));   // ints only
        $res = $conn->query("SELECT * FROM products WHERE is_active = 1 AND id IN ($in)");
        $found = [];
        while ($r = $res->fetch_assoc()) $found[(int)$r['id']] = $r;
        foreach ($cart as $pid => $qty) {
            if (!isset($found[$pid])) { cart_set($conn, $pid, 0); continue; }   // product was removed from the shop
            $items[] = product_for_js($found[$pid]) + ['qty' => (int)$qty];
        }
    }
    $total = 0; $count = 0;
    foreach ($items as $i) { $total += $i['price'] * $i['qty']; $count += $i['qty']; }
    return ['cart' => $items, 'count' => $count, 'total' => round($total, 2), 'lines' => count($items)];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') json_out(cart_payload($conn));

api_require_post();
$b      = api_body();
$action = $b['action'] ?? '';
$id     = (int)($b['id'] ?? 0);
$cur    = cart_raw($conn);

switch ($action) {
    case 'add':
        $st = $conn->prepare('SELECT id FROM products WHERE id = ? AND is_active = 1');
        $st->bind_param('i', $id); $st->execute();
        if (!$st->get_result()->num_rows) json_out(['error' => 'Product not found.'], 404);
        cart_set($conn, $id, ($cur[$id] ?? 0) + 1);
        break;

    case 'change':
        if (isset($cur[$id])) cart_set($conn, $id, $cur[$id] + (int)($b['delta'] ?? 0));
        break;

    case 'remove':
        cart_set($conn, $id, 0);
        break;

    case 'clear':
        cart_clear($conn);
        break;

    case 'checkout':
        if (!is_logged_in()) json_out(['error' => 'Please log in to buy.', 'login' => true], 401);

        $ids = array_values(array_unique(array_map('intval', is_array($b['ids'] ?? null) ? $b['ids'] : [])));
        if (!$ids) json_out(['error' => 'Select at least one item to buy.'], 400);

        $data   = cart_payload($conn);
        $chosen = array_values(array_filter($data['cart'], fn($i) => in_array($i['id'], $ids, true)));
        if (!$chosen) json_out(['error' => 'Those items are not in your cart.'], 400);

        $total = 0;
        foreach ($chosen as $i) $total += $i['price'] * $i['qty'];

        $uid = (int)$_SESSION['user_id'];
        $conn->begin_transaction();
        try {
            $st = $conn->prepare('INSERT INTO orders (user_id, total) VALUES (?, ?)');
            $st->bind_param('id', $uid, $total); $st->execute();
            $oid = $conn->insert_id;

            $it  = $conn->prepare('INSERT INTO order_items (order_id, product_id, name, price, qty) VALUES (?,?,?,?,?)');
            $del = $conn->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
            foreach ($chosen as $i) {
                $nm = html_entity_decode($i['name'], ENT_QUOTES, 'UTF-8');   // store the raw name
                $it->bind_param('iisdi', $oid, $i['id'], $nm, $i['price'], $i['qty']);
                $it->execute();
                $del->bind_param('ii', $uid, $i['id']);   // only the bought items leave the cart
                $del->execute();
            }
            $conn->commit();
        } catch (Throwable $ex) {
            $conn->rollback();
            error_log('Checkout failed: ' . $ex->getMessage());
            json_out(['error' => 'Could not place the order. Try again.'], 500);
        }
        json_out(['ok' => true, 'order_id' => $oid, 'bought' => count($chosen)] + cart_payload($conn));
        break;

    default:
        json_out(['error' => 'Unknown action.'], 400);
}

json_out(cart_payload($conn));
