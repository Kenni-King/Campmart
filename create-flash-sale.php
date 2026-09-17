<?php
session_start();
include_once 'includes/controller.php';

// Admin-only access
if (!isset($userId) || !isset($currentUser) || !in_array($currentUser['role'], ['admin','superadmin'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $discount_percentage = isset($_POST['discount_percentage']) ? floatval($_POST['discount_percentage']) : 0;
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $status = $_POST['status'] ?? 'scheduled';
    $visibility = $_POST['visibility'] ?? 'public';

    if (empty($title) || empty($start_time) || empty($end_time)) {
        $error = 'Please fill in all required fields.';
    } elseif ($discount_percentage <= 0 || $discount_percentage > 100) {
        $error = 'Discount percentage must be between 1 and 100.';
    } elseif (strtotime($end_time) <= strtotime($start_time)) {
        $error = 'End time must be after start time.';
    } else {
        $stmt = $db->prepare("INSERT INTO flash_sales (title, description, discount_percentage, start_time, end_time, is_featured, status, visibility, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $created_by = $userId;
        $stmt->bind_param(
            'ssdssissi',
            $title,
            $description,
            $discount_percentage,
            $start_time,
            $end_time,
            $is_featured,
            $status,
            $visibility,
            $created_by
        );

        if ($stmt->execute()) {
            $sale_id = $db->insert_id;
            header("Location: flash-sale-products.php?sale_id=$sale_id");
            exit;
        }

        $error = 'Failed to create flash sale: ' . $db->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Create Flash Sale | CampMart Admin</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#f48c25',
                        secondary: '#FF6B35',
                        accent: '#FFE66D',
                        'brand-green': '#064E3B',
                        'background-main': '#F9FAFB',
                        'surface-white': '#FFFFFF',
                        'text-dark': '#1F2937',
                    },
                    fontFamily: { display: ['Inter'] }
                }
            }
        }
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
</head>
<body class="bg-background-main min-h-screen text-text-dark">
    <?php include_once 'includes/user-nav.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
        <div class="mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-brand-green">Create Flash Sale</h1>
                    <p class="text-slate-500">Configure timing, discounts, and visibility.</p>
                </div>
            </div>
            <?php if (!empty($error)): ?>
                <div class="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
                <div>
                    <label class="text-sm font-medium text-slate-700">Title</label>
                    <input type="text" name="title" required class="mt-1 block w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" />
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Description</label>
                    <textarea name="description" rows="3" class="mt-1 block w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary" placeholder="Describe the sale focus"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Discount Percentage</label>
                        <input type="number" name="discount_percentage" min="1" max="100" required class="mt-1 block w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary" value="<?= htmlspecialchars($_POST['discount_percentage'] ?? '') ?>" />
                    </div>
                    <div class="flex items-center gap-3 mt-6">
                        <input type="checkbox" name="is_featured" id="is_featured" class="rounded text-primary" <?= isset($_POST['is_featured']) ? 'checked' : '' ?> />
                        <label for="is_featured" class="text-sm font-medium text-slate-700">Mark as featured</label>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Start Time</label>
                        <input type="datetime-local" name="start_time" required class="mt-1 block w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary" value="<?= htmlspecialchars($_POST['start_time'] ?? '') ?>" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">End Time</label>
                        <input type="datetime-local" name="end_time" required class="mt-1 block w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary" value="<?= htmlspecialchars($_POST['end_time'] ?? '') ?>" />
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Status</label>
                    <select name="status" class="mt-1 block w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary">
                        <?php $status = $_POST['status'] ?? 'scheduled'; ?>
                        <?php foreach(['scheduled','active','expired','cancelled'] as $opt): ?>
                            <option value="<?= $opt ?>" <?= $status === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-center gap-3">
                    <label class="text-sm font-medium text-slate-700">Visibility</label>
                    <div class="flex items-center gap-2">
                        <input type="radio" name="visibility" value="public" id="vis_public" class="text-primary" <?= ($_POST['visibility'] ?? 'public') === 'public' ? 'checked' : '' ?> />
                        <label for="vis_public" class="text-sm">Public</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="radio" name="visibility" value="private" id="vis_private" class="text-primary" <?= ($_POST['visibility'] ?? 'public') === 'private' ? 'checked' : '' ?> />
                        <label for="vis_private" class="text-sm">Private</label>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white font-semibold rounded-lg hover:bg-primary/90">
                        <span class="material-symbols-outlined">save</span>
                        Create Sale
                    </button>
                    <a href="flash-sales.php" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 text-slate-700 rounded-lg font-semibold hover:bg-slate-50">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
