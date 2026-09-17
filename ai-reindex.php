<?php
// Admin tool: backfill / refresh semantic embeddings for products, services
// and lost & found items.
// Visit: /campmartv2/ai-reindex.php?type=products|services|lostfound (admin only)
// Requires AI_ENABLED=true and a valid AI_API_KEY in .env.

session_start();
include_once 'includes/controller.php';

if (!isset($userId) || !isset($currentUser) || !in_array($currentUser['role'], ['admin', 'superadmin'], true)) {
    header('Location: index.php');
    exit;
}

if (!AI_ENABLED || empty(AI_API_KEY)) {
    $error = 'AI is not enabled. Set AI_ENABLED=true and AI_API_KEY in .env (or via environment variables).';
}

$types = [
    'products' => ['label' => 'Products (approved)', 'table' => 'products', 'where' => "status = 'approved'"],
    'services' => ['label' => 'Services (active)', 'table' => 'services', 'where' => "status = 'active'"],
    'lostfound' => ['label' => 'Lost & Found (open)', 'table' => 'lost_found_items', 'where' => "status = 'open'"],
];

$type = isset($_GET['type']) && isset($types[$_GET['type']]) ? $_GET['type'] : 'products';
$cfg = $types[$type];

$processed = 0;
$failed = 0;
$total = 0;

if (isset($_GET['run']) && empty($error)) {
    include_once 'includes/ai/search.php';
    include_once 'includes/ai/lostfound.php';

    $res = $db->query("SELECT id FROM `{$cfg['table']}` WHERE {$cfg['where']}");
    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int) $row['id'];
    }
    $total = count($ids);

    foreach ($ids as $id) {
        $ok = $type === 'products' ? ai_index_product($id)
            : ($type === 'services' ? ai_index_service($id) : ai_index_lost_found($id));
        if ($ok) {
            $processed++;
        } else {
            $failed++;
        }
    }
}

$counts = [];
foreach ($types as $key => $t) {
    $c = $db->query("SELECT COUNT(*) AS c FROM `{$t['table']}` WHERE {$t['where']}")->fetch_assoc()['c'];
    $counts[$key] = (int) $c;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>AI Embedding Reindex | CampMart</title>
    <script src="tailwind34.js"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md bg-white rounded-2xl border border-slate-200 p-8">
        <h1 class="text-xl font-extrabold text-brand-green mb-2">AI Embedding Reindex</h1>
        <p class="text-sm text-slate-500 mb-6">Generates semantic vectors so semantic search, recommendations, autocomplete and lost &amp; found matching work.</p>

        <?php if (!empty($error)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-6"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-3 gap-2 mb-6">
            <?php foreach ($types as $key => $t): ?>
                <a href="ai-reindex.php?type=<?= $key ?>"
                   class="rounded-xl border px-3 py-2 text-center text-sm font-bold <?= $type === $key ? 'bg-primary text-white border-primary' : 'bg-white text-slate-600 border-slate-200 hover:border-primary' ?>">
                    <?= $t['label'] ?><br><span class="text-xs font-normal opacity-80"><?= $counts[$key] ?> items</span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($total > 0): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-xl px-4 py-3 mb-6">
                Done: <?php echo $processed; ?> embedded, <?php echo $failed; ?> failed (out of <?php echo $total; ?> <?= $cfg['label'] ?>).
            </div>
        <?php endif; ?>

        <a href="ai-reindex.php?type=<?= $type ?>&run=1"
           class="inline-block w-full text-center bg-primary text-white font-bold rounded-xl px-4 py-3 hover:bg-primary/90">
            Reindex <?= strtolower($cfg['label']) ?>
        </a>
        <p class="text-[11px] text-slate-400 mt-3 text-center">Only runs once per request. Re-run to refresh.</p>
    </div>
</body>
</html>
