<?php
session_start();
include_once "includes/controller.php";

// Get logged-in user's university_id for filtering
$current_user_university_id = null;
if(isset($_SESSION['userAppId'])){
    $user_id = $_SESSION['userAppId'];
    $user_query = $db->query("SELECT university_id FROM users WHERE id = $user_id");
    if($user_query && $user_data = $user_query->fetch_assoc()){
        $current_user_university_id = $user_data['university_id'];
    }
}

// Get lost & found items
$item_type = isset($_GET['type']) ? mysqli_real_escape_string($db, $_GET['type']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($db, $_GET['search']) : '';
$status = isset($_GET['status']) ? mysqli_real_escape_string($db, $_GET['status']) : 'open';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

$where_conditions = [];

// Add university filter
if ($current_user_university_id) {
    $where_conditions[] = "lf.university_id = $current_user_university_id";
}

if (!empty($item_type)) {
    $where_conditions[] = "lf.type = '$item_type'";
}

if (!empty($search)) {
    $where_conditions[] = "(lf.title LIKE '%$search%' OR lf.description LIKE '%$search%' OR lf.location LIKE '%$search%')";
}

if (!empty($status)) {
    $where_conditions[] = "lf.status = '$status'";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$order_by = match($sort) {
    'oldest' => 'lf.created_at ASC',
    default => 'lf.created_at DESC'
};

$query = "
    SELECT lf.*, u.username as reporter_name, u.profile_image
    FROM lost_found_items lf
    JOIN users u ON lf.user_id = u.id
    $where_clause
    ORDER BY $order_by
    LIMIT 50
";

$items_result = $db->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Lost & Found Portal | CampMart</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#f48c25",
                        "secondary": "#FF6B35",
                        "brand-green": "#064E3B",
                        "background-main": "#F9FAFB",
                    },
                    fontFamily: { "display": ["Inter"] }
                }
            }
        }
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
</head>
<body class="bg-background-main">
    <?php include_once 'includes/header.php'; ?>

    <main class="max-w-[1440px] mx-auto px-6 py-8">
        <!-- Hero -->
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-amber-100 mb-4">
                <span class="material-symbols-outlined text-5xl text-amber-600">travel_explore</span>
            </div>
            <h1 class="text-4xl font-extrabold text-brand-green mb-3">Lost & Found Portal</h1>
            <p class="text-lg text-slate-600 max-w-2xl mx-auto">Reuniting students with their belongings. Report lost items or help return found items.</p>
        </div>

        <!-- Quick Actions -->
        <div class="grid md:grid-cols-2 gap-4 mb-10">
            <button onclick="window.location.href='my-lost-found.php'" class="bg-red-50 border-2 border-red-200 rounded-xl p-6 hover:border-red-300 transition-all text-left">
                <span class="material-symbols-outlined text-4xl text-red-600 mb-3 block">report_problem</span>
                <h3 class="text-xl font-bold text-red-900 mb-2">Report Lost Item</h3>
                <p class="text-sm text-red-700">Lost something? Create a listing to help others find it</p>
            </button>
            <button onclick="window.location.href='my-lost-found.php'" class="bg-emerald-50 border-2 border-emerald-200 rounded-xl p-6 hover:border-emerald-300 transition-all text-left">
                <span class="material-symbols-outlined text-4xl text-emerald-600 mb-3 block">check_circle</span>
                <h3 class="text-xl font-bold text-emerald-900 mb-2">Report Found Item</h3>
                <p class="text-sm text-emerald-700">Found something? List it to help return to owner</p>
            </button>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl border border-slate-200 p-6 mb-8">
            <form method="GET" class="grid md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Type</label>
                    <select name="type" class="w-full px-4 py-2 rounded-lg border border-slate-200">
                        <option value="">All Items</option>
                        <option value="lost" <?= $item_type === 'lost' ? 'selected' : '' ?>>Lost</option>
                        <option value="found" <?= $item_type === 'found' ? 'selected' : '' ?>>Found</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2 rounded-lg border border-slate-200">
                        <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="claimed" <?= $status === 'claimed' ? 'selected' : '' ?>>Claimed</option>
                        <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Search</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Item name or location" class="w-full px-4 py-2 rounded-lg border border-slate-200" />
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-primary text-white px-6 py-2 rounded-lg font-bold hover:bg-primary/90">Search</button>
                </div>
            </form>
        </div>

        <!-- Items Grid -->
        <?php if ($items_result && $items_result->num_rows > 0): ?>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php while($item = $items_result->fetch_assoc()): 
                $badge_config = $item['type'] === 'lost' 
                    ? ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'label' => 'LOST']
                    : ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'label' => 'FOUND'];
            ?>
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden hover:shadow-lg transition-all">
                <div class="relative h-48 bg-slate-100">
                    <?php if ($item['image_url']): ?>
                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-full object-cover" />
                    <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center">
                        <span class="material-symbols-outlined text-6xl text-slate-300">help</span>
                    </div>
                    <?php endif; ?>
                    <div class="absolute top-3 left-3">
                        <span class="inline-flex px-3 py-1 rounded-full text-xs font-black <?= $badge_config['bg'] . ' ' . $badge_config['text'] ?>">
                            <?= $badge_config['label'] ?>
                        </span>
                    </div>
                </div>
                <div class="p-5">
                    <h3 class="font-bold text-lg text-slate-800 mb-2"><?= htmlspecialchars($item['title']) ?></h3>
                    <p class="text-sm text-slate-600 mb-3 line-clamp-2"><?= htmlspecialchars($item['description']) ?></p>
                    <?php if ($item['location_lost_found']): ?>
                    <div class="flex items-center gap-2 text-sm text-slate-500 mb-3">
                        <span class="material-symbols-outlined text-base">location_on</span>
                        <span><?= htmlspecialchars($item['location_lost_found']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($item['date_lost_found']): ?>
                    <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
                        <span class="material-symbols-outlined text-base">calendar_today</span>
                        <span><?= date('M d, Y', strtotime($item['date_lost_found'])) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                        <div class="flex items-center gap-2">
                            <?php if ($item['profile_image']): ?>
                            <img src="<?= htmlspecialchars($item['profile_image']) ?>" alt="<?= htmlspecialchars($item['reporter_name']) ?>" class="w-8 h-8 rounded-full object-cover" />
                            <?php else: ?>
                            <div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center">
                                <span class="material-symbols-outlined text-sm text-slate-400">person</span>
                            </div>
                            <?php endif; ?>
                            <span class="text-sm font-medium text-slate-700"><?= htmlspecialchars($item['reporter_name']) ?></span>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" class="text-sm font-bold text-primary hover:underline flex items-center gap-0.5"
                                    onclick="openMatchModal(<?= $item['id'] ?>, '<?= htmlspecialchars($item['title'], ENT_QUOTES) ?>')">
                                <span class="material-symbols-outlined text-sm">auto_awesome</span> Match
                            </button>
                            <button class="text-sm font-bold text-primary hover:underline">Contact</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-16 bg-white rounded-xl border border-slate-200">
            <span class="material-symbols-outlined text-8xl text-slate-300 mb-4">search_off</span>
            <h3 class="text-2xl font-bold text-slate-700 mb-2">No items found</h3>
            <p class="text-slate-500">Try adjusting your filters</p>
        </div>
        <?php endif; ?>
    </main>

    <!-- AI Match Modal -->
    <div id="matchModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-3xl w-full max-h-[85vh] overflow-y-auto shadow-2xl">
            <div class="sticky top-0 bg-white border-b border-slate-200 p-5 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-brand-green flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">auto_awesome</span>
                        AI Matches for <span id="matchModalTitle" class="truncate max-w-[240px] inline-block">...</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Semantically matched <?= $item_type === 'lost' ? 'found items' : 'lost items' ?> near your campus</p>
                </div>
                <button onclick="closeMatchModal()" class="text-slate-400 hover:text-slate-600">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div id="matchModalBody" class="p-5">
                <div class="flex items-center justify-center py-12 text-slate-400">
                    <span class="material-symbols-outlined animate-spin mr-2">progress_activity</span>
                    <span>Finding matches...</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openMatchModal(itemId, title) {
            document.getElementById('matchModalTitle').textContent = title;
            const body = document.getElementById('matchModalBody');
            body.innerHTML = '<div class="flex items-center justify-center py-12 text-slate-400"><span class="material-symbols-outlined animate-spin mr-2">progress_activity</span><span>Finding matches...</span></div>';
            document.getElementById('matchModal').classList.remove('hidden');

            fetch('api/ai/lostfound-match.php?item_id=' + itemId + '&limit=6')
                .then(r => r.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Failed');
                    renderMatches(body, data.matches);
                })
                .catch(() => {
                    body.innerHTML = '<div class="text-center py-10 text-slate-500"><span class="material-symbols-outlined text-4xl mb-3">error_outline</span><p>Could not load matches. Try again later.</p></div>';
                });
        }

        function closeMatchModal() {
            document.getElementById('matchModal').classList.add('hidden');
        }

        function renderMatches(body, matches) {
            if (!matches.length) {
                body.innerHTML = '<div class="text-center py-10 text-slate-500"><span class="material-symbols-outlined text-5xl mb-3">travel_explore</span><p>No strong matches found yet. Check back after more items are posted.</p></div>';
                return;
            }
            let html = '<div class="grid sm:grid-cols-2 gap-4">';
            matches.forEach(m => {
                const badge = m.type === 'lost'
                    ? '<span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-red-100 text-red-700">LOST</span>'
                    : '<span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700">FOUND</span>';
                const img = m.image_url
                    ? '<img src="' + m.image_url + '" class="w-full h-28 object-cover" alt="' + m.title + '">'
                    : '<div class="w-full h-28 bg-slate-100 flex items-center justify-center"><span class="material-symbols-outlined text-slate-300 text-4xl">help</span></div>';
                const score = m.score > 0 ? Math.round(m.score * 100) : null;
                html += '<div class="border border-slate-200 rounded-xl overflow-hidden">'
                    + img
                    + '<div class="p-4">'
                    + '<div class="flex items-center justify-between mb-1">' + badge
                    + (score ? '<span class="text-[11px] font-bold text-primary">' + score + '% match</span>' : '')
                    + '</div>'
                    + '<h4 class="font-bold text-slate-800 text-sm mb-1">' + m.title + '</h4>'
                    + '<p class="text-xs text-slate-500 mb-2 line-clamp-2">' + m.description + '</p>'
                    + '<div class="flex items-center gap-1 text-xs text-slate-500 mb-2"><span class="material-symbols-outlined text-sm">location_on</span>' + (m.location_lost_found || 'N/A') + '</div>'
                    + '<p class="text-[11px] text-slate-400">Reported by <span class="font-medium text-slate-600">' + m.reporter_name + '</span></p>'
                    + '<p class="text-[11px] text-slate-400 mt-1">Contact: ' + (m.contact_info || 'N/A') + '</p>'
                    + '</div></div>';
            });
            html += '</div>';
            body.innerHTML = html;
        }

        document.getElementById('matchModal').addEventListener('click', function (e) {
            if (e.target === this) closeMatchModal();
        });
    </script>

    <?php include_once 'includes/footer.php'; ?>
</body>
</html>
