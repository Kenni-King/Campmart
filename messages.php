<?php session_start();
include_once 'includes/controller.php';

$userId = $_SESSION['userAppId'] ?? 0;

// Get conversation ID from URL
$conversation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch user's conversations with last message
$conversations_query = $db->query("SELECT 
    c.*,
    CASE 
        WHEN c.user1_id = '$userId' THEN u2.id
        ELSE u1.id
    END as other_user_id,
    CASE 
        WHEN c.user1_id = '$userId' THEN u2.full_name
        ELSE u1.full_name
    END as other_user_name,
    CASE 
        WHEN c.user1_id = '$userId' THEN u2.username
        ELSE u1.username
    END as other_username,
    CASE 
        WHEN c.user1_id = '$userId' THEN u2.profile_image
        ELSE u1.profile_image
    END as other_user_image,
    m.message as last_message,
    m.created_at as last_message_time,
    (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != '$userId' AND is_read = 0) as unread_count
    FROM conversations c
    LEFT JOIN users u1 ON c.user1_id = u1.id
    LEFT JOIN users u2 ON c.user2_id = u2.id
    LEFT JOIN messages m ON c.last_message_id = m.id
    WHERE c.user1_id = '$userId' OR c.user2_id = '$userId'
    ORDER BY c.updated_at DESC");

// If conversation selected, fetch messages
$messages = [];
$other_user = null;
if($conversation_id > 0) {
    // Verify user is part of this conversation
    $conv_check = $db->query("SELECT * FROM conversations WHERE id = '$conversation_id' AND (user1_id = '$userId' OR user2_id = '$userId')")->fetch_assoc();
    
    if($conv_check) {
        // Get other user info
        $other_user_id = $conv_check['user1_id'] == $userId ? $conv_check['user2_id'] : $conv_check['user1_id'];
        $other_user = $db->query("SELECT * FROM users WHERE id = '$other_user_id'")->fetch_assoc();
        
        // Fetch messages
        $messages_query = $db->query("SELECT m.*, u.full_name, u.username, u.profile_image 
            FROM messages m 
            JOIN users u ON m.sender_id = u.id 
            WHERE m.conversation_id = '$conversation_id' 
            ORDER BY m.created_at ASC");
        
        while($msg = $messages_query->fetch_assoc()) {
            $messages[] = $msg;
        }
        
        // Mark messages as read
        $db->query("UPDATE messages SET is_read = 1 WHERE conversation_id = '$conversation_id' AND sender_id != '$userId'");
    }
}

// Get total unread count
$total_unread = $db->query("SELECT COUNT(*) as count FROM messages m 
    JOIN conversations c ON m.conversation_id = c.id 
    WHERE (c.user1_id = '$userId' OR c.user2_id = '$userId') 
    AND m.sender_id != '$userId' AND m.is_read = 0")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Messages | CampMart</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#f48c25",
                        "brand-green": "#064E3B",
                        "brand-green-light": "#F0FDF4",
                        "background-main": "#F9FAFB",
                        "surface-white": "#FFFFFF",
                        "text-dark": "#1F2937",
                    },
                    fontFamily: {
                        "display": ["Inter"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: #1F2937;
        }

        .messages-container {
            overflow-y: auto;
            max-height: calc(100vh - 260px);
        }

        .messages-container::-webkit-scrollbar {
            width: 6px;
        }

        .messages-container::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .messages-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }

        .messages-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>

<body class="bg-background-main min-h-screen text-text-dark">
    <?php include_once 'includes/user-nav.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-brand-green">Messages</h1>
                    <p class="text-slate-500 mt-1">Chat with buyers and sellers</p>
                </div>
                <?php if($total_unread > 0): ?>
                <span class="px-3 py-1.5 bg-primary text-white rounded-full text-sm font-bold">
                    <?= $total_unread ?> unread
                </span>
                <?php endif; ?>
            </div>

            <?php if(isset($_SESSION['success'])): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg flex items-center gap-3 mb-6" id="successMessage">
                <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                <p class="flex-1"><?= htmlspecialchars($_SESSION['success']) ?></p>
                <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <?php unset($_SESSION['success']); endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-3 mb-6" id="errorMessage">
                <span class="material-symbols-outlined text-red-600">error</span>
                <p class="flex-1"><?= htmlspecialchars($_SESSION['error']) ?></p>
                <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <?php unset($_SESSION['error']); endif; ?>

            <!-- Messages Layout -->
            <div class="bg-surface-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="grid grid-cols-1 lg:grid-cols-3 h-[calc(100vh-240px)] min-h-0">
                    <!-- Conversations List -->
                    <div class="border-r border-slate-200 flex flex-col min-h-0">
                        <div class="p-4 border-b border-slate-200">
                            <div class="relative">
                                <input type="text" id="searchMessages" placeholder="Search conversations..." class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none text-sm">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">search</span>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto">
                            <?php if($conversations_query->num_rows > 0): ?>
                                <?php while($conv = $conversations_query->fetch_assoc()): ?>
                                <a href="?id=<?= $conv['id'] ?>" class="flex items-start gap-3 p-4 hover:bg-slate-50 transition-colors border-b border-slate-100 <?= $conversation_id == $conv['id'] ? 'bg-brand-green-light' : '' ?>">
                                    <div class="relative flex-shrink-0">
                                        <div class="w-12 h-12 rounded-full overflow-hidden bg-gradient-to-br from-primary/20 to-brand-green/20">
                                            <?php if($conv['other_user_image']): ?>
                                            <img src="<?= htmlspecialchars($conv['other_user_image']) ?>" alt="" class="w-full h-full object-cover">
                                            <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center">
                                                <span class="text-lg font-bold text-brand-green"><?= strtoupper(substr($conv['other_user_name'], 0, 1)) ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if($conv['unread_count'] > 0): ?>
                                        <span class="absolute -top-1 -right-1 bg-primary text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center"><?= $conv['unread_count'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-baseline justify-between mb-1">
                                            <h3 class="font-semibold text-text-dark truncate"><?= htmlspecialchars($conv['other_user_name']) ?></h3>
                                            <span class="text-xs text-slate-500 flex-shrink-0 ml-2">
                                                <?= $conv['last_message_time'] ? date('M d', strtotime($conv['last_message_time'])) : '' ?>
                                            </span>
                                        </div>
                                        <p class="text-sm text-slate-600 truncate <?= $conv['unread_count'] > 0 ? 'font-semibold' : '' ?>">
                                            <?= $conv['last_message'] ? htmlspecialchars(substr($conv['last_message'], 0, 50)) : 'No messages yet' ?>
                                        </p>
                                    </div>
                                </a>
                                <?php endwhile; ?>
                            <?php else: ?>
                            <div class="flex flex-col items-center justify-center h-full p-8 text-center">
                                <span class="material-symbols-outlined text-slate-300 text-6xl mb-3">chat_bubble</span>
                                <p class="text-slate-600 mb-2">No conversations yet</p>
                                <p class="text-sm text-slate-500">Start chatting with buyers and sellers</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Messages Area -->
                    <div class="col-span-2 flex flex-col min-h-0">
                        <?php if($other_user): ?>
                        <!-- Chat Header -->
                        <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full overflow-hidden bg-gradient-to-br from-primary/20 to-brand-green/20">
                                    <?php if($other_user['profile_image']): ?>
                                    <img src="<?= htmlspecialchars($other_user['profile_image']) ?>" alt="" class="w-full h-full object-cover">
                                    <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center">
                                        <span class="font-bold text-brand-green"><?= strtoupper(substr($other_user['full_name'], 0, 1)) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-text-dark"><?= htmlspecialchars($other_user['full_name']) ?></h3>
                                    <p class="text-xs text-slate-500">@<?= htmlspecialchars($other_user['username']) ?></p>
                                </div>
                            </div>
                            <button class="p-2 hover:bg-slate-200 rounded-lg transition-colors">
                                <span class="material-symbols-outlined text-slate-600">more_vert</span>
                            </button>
                        </div>

                        <!-- Messages -->
                        <div class="flex-1 overflow-y-auto p-4 space-y-4 messages-container" id="messagesContainer">
                            <?php if(count($messages) > 0): ?>
                                <?php foreach($messages as $msg): ?>
                                <?php $is_own = $msg['sender_id'] == $userId; ?>
                                <div class="flex items-start gap-3 <?= $is_own ? 'flex-row-reverse' : '' ?>">
                                    <div class="w-8 h-8 rounded-full overflow-hidden bg-gradient-to-br from-primary/20 to-brand-green/20 flex-shrink-0">
                                        <?php if($msg['profile_image']): ?>
                                        <img src="<?= htmlspecialchars($msg['profile_image']) ?>" alt="" class="w-full h-full object-cover">
                                        <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center">
                                            <span class="text-xs font-bold text-brand-green"><?= strtoupper(substr($msg['full_name'], 0, 1)) ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="w-fit max-w-[85%] <?= $is_own ? 'ml-auto bg-primary text-white' : 'bg-slate-100 text-text-dark' ?> rounded-2xl px-4 py-2.5 break-words">
                                            <p class="text-sm" style="overflow-wrap:anywhere"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                        </div>
                                        <p class="text-xs text-slate-500 mt-1 <?= $is_own ? 'text-right' : '' ?>">
                                            <?= date('M d, Y g:i A', strtotime($msg['created_at'])) ?>
                                        </p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <div class="flex items-center justify-center h-full">
                                <div class="text-center">
                                    <span class="material-symbols-outlined text-slate-300 text-6xl mb-3">chat</span>
                                    <p class="text-slate-600">No messages yet</p>
                                    <p class="text-sm text-slate-500">Send a message to start the conversation</p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Send Message Form -->
                        <div class="p-4 border-t border-slate-200 bg-slate-50">
                            <div class="flex items-center justify-between mb-2">
                                <button type="button" onclick="loadSmartReplies()" class="inline-flex items-center gap-1.5 text-xs font-bold text-primary px-3 py-1.5 rounded-full bg-primary/10 hover:bg-primary/20 transition-colors">
                                    <span class="material-symbols-outlined text-sm">auto_awesome</span> Smart reply
                                </button>
                                <span class="text-[11px] text-slate-400 hidden sm:inline">AI-generated replies based on the chat</span>
                            </div>
                            <div id="smartReplySuggestions" class="flex flex-wrap gap-2 mb-2"></div>
                            <form method="POST" class="flex gap-3" id="sendMessageForm">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>" />
                                <input type="hidden" name="conversation_id" value="<?= $conversation_id ?>" />
                                <input type="hidden" name="SendMessage" value="1" />
                                <textarea name="message" rows="1" required class="flex-1 px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none resize-none" placeholder="Type your message..." id="messageInput"></textarea>
                                <button type="submit" class="px-6 py-2.5 bg-primary text-white rounded-lg font-bold hover:bg-primary/90 transition-colors flex items-center gap-2">
                                    <span class="material-symbols-outlined">send</span>
                                    <span class="hidden sm:inline">Send</span>
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                        <!-- No Conversation Selected -->
                        <div class="flex items-center justify-center h-full">
                            <div class="text-center">
                                <span class="material-symbols-outlined text-slate-300 text-8xl mb-4">forum</span>
                                <h3 class="text-xl font-bold text-slate-600 mb-2">Select a conversation</h3>
                                <p class="text-slate-500">Choose a conversation from the list to view messages</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Auto-dismiss messages
        setTimeout(() => {
            const successMsg = document.getElementById('successMessage');
            if(successMsg) successMsg.remove();
            const errorMsg = document.getElementById('errorMessage');
            if(errorMsg) errorMsg.remove();
        }, 5000);

        // Auto-scroll to bottom of messages
        const messagesContainer = document.getElementById('messagesContainer');
        if(messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Auto-resize textarea
        const messageInput = document.getElementById('messageInput');
        if(messageInput) {
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });

            messageInput.addEventListener('keydown', function(e) {
                if(e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    const form = document.getElementById('sendMessageForm');
                    if(form && this.value.trim() !== '') form.submit();
                }
            });
        }

        // Search conversations
        const searchInput = document.getElementById('searchMessages');
        if(searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const conversations = document.querySelectorAll('.flex.items-start.gap-3.p-4');
                conversations.forEach(conv => {
                    const name = conv.querySelector('h3').textContent.toLowerCase();
                    const message = conv.querySelector('.text-sm').textContent.toLowerCase();
                    if(name.includes(searchTerm) || message.includes(searchTerm)) {
                        conv.style.display = 'flex';
                    } else {
                        conv.style.display = 'none';
                    }
                });
            });
        }

        // Smart replies
        function loadSmartReplies() {
            const box = document.getElementById('smartReplySuggestions');
            if (!box) return;
            box.innerHTML = '<span class="text-xs text-slate-400"><span class="material-symbols-outlined text-sm animate-spin align-middle">progress_activity</span> Generating...</span>';
            fetch('api/ai/smart-replies.php?conversation_id=<?= $conversation_id ?>&limit=3')
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.replies || !data.replies.length) {
                        box.innerHTML = '<span class="text-xs text-slate-400">No suggestions available right now.</span>';
                        return;
                    }
                    box.innerHTML = '';
                    data.replies.forEach(function (text) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'text-xs font-medium text-left px-3 py-2 rounded-lg bg-white border border-slate-200 hover:border-primary text-slate-700 transition-colors max-w-full break-words';
                        btn.textContent = text;
                        btn.addEventListener('click', function () {
                            const input = document.getElementById('messageInput');
                            if (input) {
                                input.value = text;
                                input.dispatchEvent(new Event('input'));
                                input.focus();
                            }
                        });
                        box.appendChild(btn);
                    });
                })
                .catch(function () {
                    box.innerHTML = '<span class="text-xs text-slate-400">Smart reply unavailable.</span>';
                });
        }

        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            if(sidebar) sidebar.classList.toggle('-translate-x-full');
            if(sidebarOverlay) sidebarOverlay.classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden');
        }

        if(menuToggle) menuToggle.addEventListener('click', toggleSidebar);
        if(sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);

        if(sidebar) {
            const sidebarLinks = sidebar.querySelectorAll('a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth < 1024) {
                        toggleSidebar();
                    }
                });
            });
        }

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024 && sidebar && !sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                if(sidebarOverlay) sidebarOverlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        });
    </script>
</body>

</html>
