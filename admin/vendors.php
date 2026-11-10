<?php
require_once __DIR__ . '/../includes/admin.php';
admin_boot();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0); $act = $_POST['action'] ?? '';
    if ($id && in_array($act, ['approve', 'suspend'], true)) {
        $new = $act === 'approve' ? 'approved' : 'suspended';
        $st = $conn->prepare('UPDATE sellers SET status = ? WHERE id = ?');
        $st->bind_param('si', $new, $id); $st->execute();
        flash_set('ok', $act === 'approve' ? 'Vendor approved. They can now add products.' : 'Vendor suspended. Their products are hidden.');
    } elseif ($id && $act === 'plan' && in_array($_POST['plan'] ?? '', ['free', 'silver', 'gold'], true)) {
        $st = $conn->prepare('UPDATE sellers SET plan = ? WHERE id = ?');
        $st->bind_param('si', $_POST['plan'], $id); $st->execute();
        flash_set('ok', 'Plan updated.');
    } elseif ($id && in_array($act, ['pay_approve', 'pay_reject'], true)) {
        // Payment review (bKash / Nagad transaction)
        $pid = (int)($_POST['pid'] ?? 0);
        $st = $conn->prepare("SELECT id, seller_id, plan FROM seller_payments WHERE id = ? AND seller_id = ? AND status = 'pending'");
        $st->bind_param('ii', $pid, $id); $st->execute();
        $pay = $st->get_result()->fetch_assoc(); $st->close();

        if (!$pay) {
            flash_set('err', 'Payment not found or already reviewed.');
        } elseif ($act === 'pay_approve') {
            $conn->begin_transaction();
            try {
                $st = $conn->prepare("UPDATE seller_payments SET status = 'approved', reviewed_at = NOW() WHERE id = ?");
                $st->bind_param('i', $pid); $st->execute();
                $st = $conn->prepare('UPDATE sellers SET plan = ? WHERE id = ?');
                $st->bind_param('si', $pay['plan'], $id); $st->execute();
                $conn->commit();
                flash_set('ok', 'Payment approved. Plan is now ' . ucfirst($pay['plan']) . '.');
            } catch (Throwable $ex) {
                $conn->rollback();
                flash_set('err', 'Could not approve payment. Try again.');
            }
        } else {
            $reason = mb_substr(trim($_POST['reason'] ?? ''), 0, 250);
            $st = $conn->prepare("UPDATE seller_payments SET status = 'rejected', admin_note = ?, reviewed_at = NOW() WHERE id = ?");
            $st->bind_param('si', $reason, $pid); $st->execute();
            flash_set('ok', 'Payment rejected.');
        }
    }
    redirect('vendors.php' . (!empty($_POST['back']) ? '?status=' . urlencode($_POST['back']) : ''));
}

$status = $_GET['status'] ?? 'all';
if (!in_array($status, ['pending', 'approved', 'suspended'], true)) $status = 'all';
$sql = "SELECT s.*, (SELECT COUNT(*) FROM products p WHERE p.seller_id = s.id AND p.status = 'approved') AS live,
               (SELECT COUNT(*) FROM products p WHERE p.seller_id = s.id AND p.status = 'pending') AS waiting
        FROM sellers s" . ($status !== 'all' ? " WHERE s.status = '$status'" : '') . " ORDER BY (s.status = 'pending') DESC, s.id DESC";
$rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
$c = $conn->query("SELECT COUNT(*) a, SUM(status='pending') p, SUM(status='approved') ok, SUM(status='suspended') s FROM sellers")->fetch_assoc();

// latest payment per seller
$pays = []; $payTableMissing = false;
try {
    $q = $conn->query("SELECT * FROM seller_payments WHERE id IN (SELECT MAX(id) FROM seller_payments GROUP BY seller_id)");
    foreach ($q->fetch_all(MYSQLI_ASSOC) as $p) $pays[(int)$p['seller_id']] = $p;
} catch (Throwable $ex) { $payTableMissing = true; }

admin_header('Vendors', 'vendors');
?>
<?php if ($payTableMissing): ?>
    <div class="flash err">The <b>seller_payments</b> table was not found. Run <b>seller_payments.sql</b> in phpMyAdmin first.</div>
<?php endif; ?>
<div class="tabs">
    <?php foreach (['all' => ['All', $c['a']], 'pending' => ['Waiting', $c['p']], 'approved' => ['Approved', $c['ok']], 'suspended' => ['Suspended', $c['s']]] as $k => [$l, $n]): ?>
        <a href="?status=<?= $k ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= $l ?><em><?= (int)$n ?></em></a>
    <?php endforeach; ?>
</div>
<div class="card tablewrap">
<table>
    <tr><th>Shop</th><th>Owner</th><th>Plan</th><th>Payment</th><th>Products</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
    <?php foreach ($rows as $s): $p = $pays[(int)$s['id']] ?? null; ?>
        <tr>
            <td><b><?= e($s['shop_name']) ?></b><br><small class="muted">#<?= (int)$s['id'] ?></small></td>
            <td><?= e($s['full_name']) ?><br><small class="muted"><?= e($s['email']) ?> &middot; <?= e($s['phone']) ?></small></td>
            <td>
                <form method="POST" class="actions"><?= csrf_field() ?><input type="hidden" name="action" value="plan"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="back" value="<?= e($status) ?>">
                    <select name="plan" style="padding:6px 8px;border:1px solid #e5d9a8;border-radius:10px;font:inherit">
                        <?php foreach (['free', 'silver', 'gold'] as $pl): ?><option value="<?= $pl ?>" <?= $s['plan'] === $pl ? 'selected' : '' ?>><?= ucfirst($pl) ?></option><?php endforeach; ?>
                    </select><button class="btn-line sm">Set</button></form>
                <?php if ($s['requested_plan'] !== $s['plan']): ?><small class="muted">asked for <?= e(ucfirst($s['requested_plan'])) ?></small><?php endif; ?>
            </td>
            <td style="min-width:210px">
                <?php if ($p): ?>
                    <b style="color:<?= $p['method'] === 'bkash' ? '#e2136e' : '#f6921e' ?>"><?= $p['method'] === 'bkash' ? 'bKash' : 'Nagad' ?></b>
                    &middot; <?= e(ucfirst($p['plan'])) ?> &middot; <?= (int)$p['amount'] ?> BDT<br>
                    <small class="muted">TrxID:</small> <b><?= e($p['trx_id']) ?></b><br>
                    <small class="muted">From: <?= e($p['sender_number']) ?></small><br>
                    <?= status_pill($p['status']) ?>
                    <?php if ($p['status'] === 'rejected' && $p['admin_note']): ?><br><small class="muted"><?= e($p['admin_note']) ?></small><?php endif; ?>
                    <?php if ($p['status'] === 'pending'): ?>
                        <div class="actions" style="margin-top:8px">
                            <form method="POST" data-confirm="Have you confirmed that the payment for Trx ID <?= e($p['trx_id']) ?> was received? Approving will activate the <?= e(ucfirst($p['plan'])) ?> plan."><?= csrf_field() ?><input type="hidden" name="action" value="pay_approve"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="pid" value="<?= (int)$p['id'] ?>"><input type="hidden" name="back" value="<?= e($status) ?>"><button class="btn sm">Approve payment</button></form>
                            <form method="POST" data-reject><?= csrf_field() ?><input type="hidden" name="action" value="pay_reject"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="pid" value="<?= (int)$p['id'] ?>"><input type="hidden" name="back" value="<?= e($status) ?>"><input type="hidden" name="reason" value=""><button class="btn-del sm">Reject</button></form>
                        </div>
                    <?php endif; ?>
                <?php elseif ($s['requested_plan'] !== 'free' && $s['requested_plan'] !== $s['plan']): ?>
                    <small class="muted">Payment not submitted yet</small>
                <?php else: ?>
                    <span class="muted">-</span>
                <?php endif; ?>
            </td>
            <td><a href="products.php?seller=<?= (int)$s['id'] ?>" style="color:var(--gold-deep);font-weight:700"><?= (int)$s['live'] ?> live</a><?= $s['waiting'] ? ' &middot; <b style="color:var(--red)">' . (int)$s['waiting'] . ' waiting</b>' : '' ?></td>
            <td><?= status_pill($s['status']) ?></td>
            <td class="nowrap"><?= !empty($s['created_at']) ? e(date('d M Y', strtotime($s['created_at']))) : '-' ?></td>
            <td><div class="actions">
                <?php if ($s['status'] !== 'approved'): ?><form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="back" value="<?= e($status) ?>"><button class="btn sm">Approve</button></form><?php endif; ?>
                <?php if ($s['status'] !== 'suspended'): ?><form method="POST" data-confirm="Suspend this vendor? Their products will be hidden."><?= csrf_field() ?><input type="hidden" name="action" value="suspend"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="back" value="<?= e($status) ?>"><button class="btn-del sm">Suspend</button></form><?php endif; ?>
            </div></td>
        </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="muted">No vendors here.</td></tr><?php endif; ?>
</table>
</div>
<?php admin_footer();
