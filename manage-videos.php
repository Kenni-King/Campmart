<?php
session_start();
include_once 'includes/controller.php';

if (!isset($userId) || !isset($currentUser) || !in_array($currentUser['role'], ['admin', 'superadmin'])) {
    header('Location: index.php');
    exit;
}

$videos = $db->query("SELECT * FROM guide_videos ORDER BY display_order ASC, created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <base href="<?php echo SITE_URL; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Guide Videos | CampMart Admin</title>
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
    <style>.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }</style>
</head>
<body class="bg-background-main min-h-screen text-text-dark">
    <?php include_once 'includes/user-nav.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-6">

            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Admin / Content</p>
                    <h1 class="text-3xl font-bold text-brand-green">Manage Guide Videos</h1>
                    <p class="text-slate-500">Add, edit, or remove tutorial videos shown on user dashboards.</p>
                </div>
                <div class="flex gap-2">
                    <a href="admin-dashboard.php" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">
                        <span class="material-symbols-outlined text-base">arrow_back</span>
                        Back
                    </a>
                    <button onclick="openModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary/90">
                        <span class="material-symbols-outlined text-base">add</span>
                        Add Video
                    </button>
                </div>
            </div>

            <div id="toast" class="hidden fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg text-sm font-medium"></div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 text-sm text-slate-600">
                    <span id="video-count">0</span> videos total
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold w-12">Order</th>
                                <th class="px-4 py-3 text-left font-semibold">Video</th>
                                <th class="px-4 py-3 text-left font-semibold">Title</th>
                                <th class="px-4 py-3 text-left font-semibold">Status</th>
                                <th class="px-4 py-3 text-left font-semibold">Created</th>
                                <th class="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="videos-table" class="divide-y divide-slate-100">
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">Loading videos...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div id="empty-state" class="hidden px-4 py-12 text-center">
                    <span class="material-symbols-outlined text-5xl text-slate-300 mb-3">videocam</span>
                    <p class="text-slate-500 font-medium">No guide videos yet</p>
                    <p class="text-slate-400 text-sm mt-1">Click "Add Video" to get started.</p>
                </div>
            </div>
        </div>
    </main>

    <div id="modal" class="hidden fixed inset-0 z-40 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/50" onclick="closeModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 id="modal-title" class="text-lg font-bold text-text-dark">Add Video</h2>
                <button onclick="closeModal()" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form id="video-form" onsubmit="handleSubmit(event)" class="p-6 space-y-4">
                <input type="hidden" id="form-id" value="">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Title <span class="text-red-500">*</span></label>
                    <input type="text" id="form-title" required placeholder="e.g. Uploading a Product" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea id="form-description" rows="2" placeholder="Short description of this tutorial" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm resize-none"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">YouTube URL <span class="text-red-500">*</span></label>
                    <input type="text" id="form-video-url" required placeholder="https://youtube.com/watch?v=..." class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                    <p class="text-xs text-slate-400 mt-1">Paste a full YouTube URL. We'll extract the video ID automatically.</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Icon</label>
                        <select id="form-icon" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="play_circle">Play Circle</option>
                            <option value="shopping_bag">Shopping Bag</option>
                            <option value="work">Work</option>
                            <option value="school">School</option>
                            <option value="build">Build</option>
                            <option value="campaign">Campaign</option>
                            <option value="person">Person</option>
                            <option value="settings">Settings</option>
                            <option value="help">Help</option>
                            <option value="search">Search</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Icon Color</label>
                        <select id="form-icon-color" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="primary">Orange</option>
                            <option value="brand-green">Green</option>
                            <option value="blue-600">Blue</option>
                            <option value="purple-600">Purple</option>
                            <option value="pink-600">Pink</option>
                            <option value="red-600">Red</option>
                            <option value="emerald-600">Emerald</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Display Order</label>
                        <input type="number" id="form-display-order" value="0" min="0" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                            <input type="checkbox" id="form-is-active" checked class="rounded text-primary" />
                            Active (visible to users)
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal()" class="px-4 py-2.5 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200">Cancel</button>
                    <button type="submit" id="submit-btn" class="px-6 py-2.5 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary/90">Save Video</button>
                </div>
            </form>
        </div>
    </div>

    <div id="delete-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/50" onclick="closeDeleteModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 p-6 text-center">
            <span class="material-symbols-outlined text-5xl text-red-500 mb-3">delete</span>
            <h3 class="text-lg font-bold text-text-dark mb-2">Delete Video?</h3>
            <p class="text-sm text-slate-500 mb-6">This action cannot be undone.</p>
            <div class="flex justify-center gap-3">
                <button onclick="closeDeleteModal()" class="px-4 py-2.5 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200">Cancel</button>
                <button onclick="confirmDelete()" id="delete-confirm-btn" class="px-6 py-2.5 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700">Delete</button>
            </div>
        </div>
    </div>

    <script>
        const API = 'api/admin/manage-videos.php';
        let videos = [];
        let deleteId = null;

        function showToast(msg, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg text-sm font-medium ${type === 'success' ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white'}`;
            setTimeout(() => { toast.className = 'hidden'; }, 3000);
        }

        async function loadVideos() {
            try {
                const res = await fetch(API + '?action=list');
                const text = await res.text();
                const data = JSON.parse(text);
                if (data.success) {
                    videos = data.videos;
                    renderTable();
                }
            } catch (e) {
                console.error('Load error:', e);
            }
        }

        function renderTable() {
            const tbody = document.getElementById('videos-table');
            const empty = document.getElementById('empty-state');
            const count = document.getElementById('video-count');
            count.textContent = videos.length;

            if (videos.length === 0) {
                tbody.innerHTML = '';
                empty.classList.remove('hidden');
                return;
            }

            empty.classList.add('hidden');
            tbody.innerHTML = videos.map(v => {
                const iconColors = {
                    'primary': 'bg-primary/10 text-primary',
                    'brand-green': 'bg-brand-green/10 text-brand-green',
                    'blue-600': 'bg-blue-100 text-blue-600',
                    'purple-600': 'bg-purple-100 text-purple-600',
                    'pink-600': 'bg-pink-100 text-pink-600',
                    'red-600': 'bg-red-100 text-red-600',
                    'emerald-600': 'bg-emerald-100 text-emerald-600'
                };
                const colorClass = iconColors[v.icon_color] || 'bg-primary/10 text-primary';
                const videoId = extractYouTubeId(v.video_url);

                return `
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <span class="text-xs font-bold text-slate-400 bg-slate-100 px-2 py-1 rounded">${v.display_order}</span>
                    </td>
                    <td class="px-4 py-3">
                        ${videoId ? `<img src="https://img.youtube.com/vi/${videoId}/mqdefault.jpg" class="w-24 h-14 rounded-md object-cover border border-slate-200" alt="" />` : `<span class="material-symbols-outlined text-slate-400">videocam</span>`}
                    </td>
                    <td class="px-4 py-3">
                        <div class="font-semibold text-text-dark">${escapeHtml(v.title)}</div>
                        <div class="text-xs text-slate-500 truncate max-w-xs">${escapeHtml(v.description || '')}</div>
                    </td>
                    <td class="px-4 py-3">
                        <button onclick="toggleVideo(${v.id})" class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold ${v.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}">
                            ${v.is_active ? 'Active' : 'Inactive'}
                        </button>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600">${new Date(v.created_at).toLocaleDateString()}</td>
                    <td class="px-4 py-3 text-right space-x-1">
                        <button onclick="editVideo(${v.id})" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-700 hover:border-primary/60 hover:text-primary">
                            <span class="material-symbols-outlined text-sm">edit</span>
                            Edit
                        </button>
                        <button onclick="openDeleteModal(${v.id})" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-red-600 hover:border-red-400 hover:bg-red-50">
                            <span class="material-symbols-outlined text-sm">delete</span>
                        </button>
                    </td>
                </tr>`;
            }).join('');
        }

        function extractYouTubeId(url) {
            if (!url) return null;
            const match = url.match(/(?:youtube\.com\/(?:embed\/|watch\?v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
            return match ? match[1] : null;
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function openModal() {
            document.getElementById('form-id').value = '';
            document.getElementById('form-title').value = '';
            document.getElementById('form-description').value = '';
            document.getElementById('form-video-url').value = '';
            document.getElementById('form-icon').value = 'play_circle';
            document.getElementById('form-icon-color').value = 'primary';
            document.getElementById('form-display-order').value = videos.length;
            document.getElementById('form-is-active').checked = true;
            document.getElementById('modal-title').textContent = 'Add Video';
            document.getElementById('submit-btn').textContent = 'Save Video';
            document.getElementById('modal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
        }

        function editVideo(id) {
            const v = videos.find(x => x.id == id);
            if (!v) return;
            document.getElementById('form-id').value = v.id;
            document.getElementById('form-title').value = v.title;
            document.getElementById('form-description').value = v.description || '';
            document.getElementById('form-video-url').value = v.video_url;
            document.getElementById('form-icon').value = v.icon;
            document.getElementById('form-icon-color').value = v.icon_color;
            document.getElementById('form-display-order').value = v.display_order;
            document.getElementById('form-is-active').checked = v.is_active == 1;
            document.getElementById('modal-title').textContent = 'Edit Video';
            document.getElementById('submit-btn').textContent = 'Update Video';
            document.getElementById('modal').classList.remove('hidden');
        }

        async function handleSubmit(e) {
            e.preventDefault();
            const id = document.getElementById('form-id').value;
            const formData = new FormData();
            formData.append('title', document.getElementById('form-title').value);
            formData.append('description', document.getElementById('form-description').value);
            formData.append('video_url', document.getElementById('form-video-url').value);
            formData.append('icon', document.getElementById('form-icon').value);
            formData.append('icon_color', document.getElementById('form-icon-color').value);
            formData.append('display_order', document.getElementById('form-display-order').value);
            if (document.getElementById('form-is-active').checked) {
                formData.append('is_active', '1');
            }

            try {
                const action = id ? 'update' : 'create';
                if (id) formData.append('id', id);
                const res = await fetch(API + '?action=' + action, { method: 'POST', body: formData });
                const text = await res.text();
                try {
                    const data = JSON.parse(text);
                    showToast(data.message, data.success ? 'success' : 'error');
                    if (data.success) {
                        closeModal();
                        loadVideos();
                    }
                } catch(parseErr) {
                    console.error('API response:', text);
                    showToast('Server error. Check console for details.', 'error');
                }
            } catch (e) {
                console.error(e);
                showToast('An error occurred: ' + e.message, 'error');
            }
        }

        async function toggleVideo(id) {
            try {
                const formData = new FormData();
                formData.append('id', id);
                const res = await fetch(API + '?action=toggle', { method: 'POST', body: formData });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) loadVideos();
            } catch (e) {
                showToast('An error occurred', 'error');
            }
        }

        function openDeleteModal(id) {
            deleteId = id;
            document.getElementById('delete-modal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            deleteId = null;
            document.getElementById('delete-modal').classList.add('hidden');
        }

        async function confirmDelete() {
            if (!deleteId) return;
            try {
                const formData = new FormData();
                formData.append('id', deleteId);
                const res = await fetch(API + '?action=delete', { method: 'POST', body: formData });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                closeDeleteModal();
                if (data.success) loadVideos();
            } catch (e) {
                showToast('An error occurred', 'error');
            }
        }

        loadVideos();
    </script>
</body>
</html>
