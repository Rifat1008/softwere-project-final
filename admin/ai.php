<?php
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/ai_client.php';
admin_boot();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'retrain') {
    $r = ai_service_call('/retrain', [], 180);
    if ($r['status'] === 200) flash_set('ok', 'AI retrained in ' . ($r['data']['model']['train_seconds'] ?? '?') . ' seconds (accuracy score ' . ($r['data']['model']['f1'] ?? '?') . ').');
    else flash_set('err', $r['status'] === 0 ? 'The AI service is not running. Start ai_service/start_ai.bat first.' : ($r['data']['error'] ?? 'Retraining failed.'));
    redirect('ai.php');
}

$st = ai_service_call('/status');
$online = $st['status'] === 200 && is_array($st['data']);
$s = $online ? $st['data'] : [];
$m = $s['model'] ?? [];

$q = trim($_GET['q'] ?? '');
$test = null;
if ($online && $q !== '') $test = ai_service_call('/suggest', ['messages' => [['role' => 'user', 'text' => mb_substr($q, 0, 300)]], 'limit' => 5], 20)['data'] ?? null;

$chats = [];
try { $chats = $conn->query('SELECT c.prompt, c.created_at, u.full_name FROM ai_chats c LEFT JOIN users u ON u.id = c.user_id ORDER BY c.id DESC LIMIT 10')->fetch_all(MYSQLI_ASSOC); } catch (Throwable $e) {}
admin_header('AI model', 'ai');
?>
<div class="card">
    <h2>Gift AI (Python) <?= $online ? '<span class="pill approved">Online</span>' : '<span class="pill rejected">Offline</span>' ?></h2>
    <?php if (!$online): ?>
        <p>The AI service is not running, so the AI page cannot suggest gifts from your catalog.</p>
        <ol style="margin:10px 0 0 20px;line-height:1.9">
            <li>Install Python from <b>python.org</b> (tick "Add python.exe to PATH").</li>
            <li>Double-click <b>ai_service\start_ai.bat</b> and keep the black window open.</li>
            <li>Refresh this page.</li>
        </ol>
    <?php else: ?>
        <div class="tiles" style="margin:14px 0">
            <div class="tile"><b><?= (int)$s['products'] ?></b><span>Products it knows</span></div>
            <div class="tile"><b><?= (int)$s['tags'] ?></b><span>Tags learned from</span></div>
            <div class="tile"><b><?= e($m['f1'] ?? '-') ?></b><span>Accuracy score (F1)</span></div>
            <div class="tile"><b><?= (int)($m['queries'] ?? 0) ?></b><span>Training sentences</span></div>
        </div>
        <p class="muted small">Last trained: <b><?= e($m['trained_at'] ?? '-') ?></b> (took <?= e($m['train_seconds'] ?? '?') ?>s). New, approved or hidden products are picked up automatically. Retrain after you add or rename <b>filter options</b>.</p>
        <form method="POST" style="margin-top:14px"><?= csrf_field() ?><input type="hidden" name="action" value="retrain"><button class="btn">Retrain now</button></form>
    <?php endif; ?>
</div>

<?php if ($online): ?>
<div class="card">
    <h2>Try it</h2>
    <form method="GET" class="inline"><input type="text" name="q" value="<?= e($q) ?>" placeholder="e.g. birthday gift for my mom under 3000 taka" maxlength="300"><button class="btn">Ask the AI</button></form>
    <?php if ($test): ?>
        <p style="margin-top:14px"><b><?= e($test['reply'] ?? '') ?></b></p>
        <?php if (!empty($test['understood'])): ?><p class="muted small">Understood: <?= e(implode(' · ', $test['understood'])) ?></p><?php endif; ?>
        <?php if (!empty($test['gifts'])): ?>
            <div class="tablewrap"><table><tr><th>Gift</th><th>Category</th><th>Price</th><th>Why</th></tr>
            <?php foreach ($test['gifts'] as $g): ?><tr><td><b><?= e($g['name']) ?></b></td><td><?= e($g['tag']) ?></td><td class="nowrap"><?= e($g['price']) ?></td><td class="small"><?= e($g['why']) ?></td></tr><?php endforeach; ?>
            </table></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <h2>What visitors asked recently</h2>
    <?php if (!$chats): ?><p class="muted">Nothing yet.</p><?php else: foreach ($chats as $c): ?>
        <div class="line" style="display:flex;justify-content:space-between;gap:12px;padding:7px 0;border-bottom:1px dashed var(--line)"><span><?= e($c['prompt']) ?></span><small class="muted nowrap"><?= e($c['full_name'] ?? 'Guest') ?> &middot; <?= e(date('d M, h:i A', strtotime($c['created_at']))) ?></small></div>
    <?php endforeach; endif; ?>
</div>
<?php admin_footer();
