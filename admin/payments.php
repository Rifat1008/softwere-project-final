<?php
require_once __DIR__ . '/../includes/admin.php';
admin_boot();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0); $act = $_POST['action'] ?? '';
    if ($id && in_array($act, ['approve', 'reject'], true)) {
        $st = $conn->prepare("SELECT id, seller_id, plan FROM seller_payments WHERE id = ? AND status = 'pending'");
        $st->bind_param('i', $id); $st->execute();
        $pay = $st->get_result()->fetch_assoc(); $st->close();

        if (!$pay) {
            flash_set('err', 'Payment not found or already reviewed.');
        } elseif ($act === 'approve') {
            $conn->begin_transaction();
            try {
                $st = $conn->prepare("UPDATE seller_payments SET status = 'approved', reviewed_at = NOW() WHERE id = ?");
                $st->bind_param('i', $id); $st->execute();
                $st = $conn->prepare('UPDATE sellers SET plan = ? WHERE id = ?');
                $st->bind_param('si', $pay['plan'], $pay['seller_id']); $st->execute();
                $conn->commit();
                flash_set('ok', 'Payment approved. Seller plan is now ' . ucfirst($pay['plan']) . '.');
            } catch (Throwable $ex) {
                $conn->rollback();
                flash_set('err', 'Could not approve payment. Try again.');
            }
        } else {
            $reason = mb_substr(trim($_POST['reason'] ?? ''), 0, 250);
            $st = $conn->prepare("UPDATE seller_payments SET status = 'rejected', admin_note = ?, reviewed_at = NOW() WHERE id = ?");
            $st->bind_param('si', $reason, $id); $st->execute();
            flash_set('ok', 'Payment rejected.');
        }
    }
    redirect('payments.php' . (!empty($_POST['back']) ? '?status=' . urlencode($_POST['back']) : ''));
}

$status = $_GET['status'] ?? 'pending';
if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) $status = 'pending';

$sql = "SELECT p.*, s.shop_name, s.full_name, s.email, s.plan AS current_plan
        FROM seller_payments p JOIN sellers s ON s.id = p.seller_id"
     . ($status !== 'all' ? " WHERE p.status = '$status'" : '') . " ORDER BY (p.status = 'pending') DESC, p.id DESC";
$rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
$c = $conn->query("SELECT COUNT(*) a, SUM(status='pending') p, SUM(status='approved') ok, SUM(status='rejected') r FROM seller_payments")->fetch_assoc();

admin_header('Payments', 'payments');
?>
<div class="tabs">
    <?php foreach (['pending' => ['Waiting', $c['p']], 'approved' => ['Approved', $c['ok']], 'rejected' => ['Rejected', $c['r']], 'all' => ['All', $c['a']]] as $k => [$l, $n]): ?>
        <a href="?status=<?= $k ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= $l ?><em><?= (int)$n ?></em></a>
    <?php endforeach; ?>
</div>
<div class="card tablewrap">
<table>
    <tr><th>Shop</th><th>Plan</th><th>Method</th><th>Sender</th><th>Transaction ID</th><th>Amount</th><th>Status</th><th>Date</th><th>Actions</th></tr>
    <?php foreach ($rows as $p): ?>
        <tr>
            <td><b><?= e($p['shop_name']) ?></b><br><small class="muted"><?= e($p['full_name']) ?> &middot; <?= e($p['email']) ?></small></td>
            <td><?= e(ucfirst($p['plan'])) ?><br><small class="muted">now: <?= e(ucfirst($p['current_plan'])) ?></small></td>
            <td><b style="color:<?= $p['method'] === 'bkash' ? '#e2136e' : '#f6921e' ?>"><?= $p['method'] === 'bkash' ? 'bKash' : 'Nagad' ?></b></td>
            <td class="nowrap"><?= e($p['sender_number']) ?></td>
            <td class="nowrap"><b><?= e($p['trx_id']) ?></b></td>
            <td class="num"><?= (int)$p['amount'] ?> BDT</td>
            <td><?= status_pill($p['status']) ?><?= $p['admin_note'] ? '<br><small class="muted">' . e($p['admin_note']) . '</small>' : '' ?></td>
            <td class="nowrap"><?= e(date('d M Y, h:i A', strtotime($p['created_at']))) ?></td>
            <td><div class="actions">
                <?php if ($p['status'] === 'pending'): ?>
                    <form method="POST" data-confirm="Have you confirmed that the payment for Trx ID <?= e($p['trx_id']) ?> was received? Approving will activate the plan."><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="back" value="<?= e($status) ?>"><button class="btn sm">Approve</button></form>
                    <form method="POST" data-reject><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="back" value="<?= e($status) ?>"><input type="hidden" name="reason" value=""><button class="btn-del sm">Reject</button></form>
                <?php else: ?><span class="muted small"><?= $p['reviewed_at'] ? e(date('d M Y', strtotime($p['reviewed_at']))) : '-' ?></span><?php endif; ?>
            </div></td>
        </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="9" class="muted">No payments here.</td></tr><?php endif; ?>
</table>
</div>
<?php admin_footer();
