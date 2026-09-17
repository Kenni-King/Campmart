<?php session_start();
include_once 'includes/controller.php';

checkLogin();
$userId = $_SESSION['userAppId'] ?? 0;

// Fetch user's support tickets
$tickets_query = $db->query("SELECT * FROM support_tickets WHERE user_id = '$userId' ORDER BY created_at DESC");

// Handle new ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    if (isAccountBlocked()) {
        header("Location: support-tickets.php?error=blocked");
        exit;
    }
    $subject = $db->real_escape_string($_POST['subject']);
    $category = $db->real_escape_string($_POST['category']);
    $priority = $db->real_escape_string($_POST['priority']);
    $description = $db->real_escape_string($_POST['description']);
    
    $insert_ticket = $db->query("INSERT INTO support_tickets (user_id, subject, category, priority, description, status, created_at) 
                                VALUES ('$userId', '$subject', '$category', '$priority', '$description', 'open', NOW())");
    
    if ($insert_ticket) {
        header("Location: support-tickets.php?success=ticket_created");
        exit;
    }
}

// Get ticket counts by status
$open_tickets = $db->query("SELECT COUNT(*) as count FROM support_tickets WHERE user_id = '$userId' AND status = 'open'")->fetch_assoc()['count'];
$in_progress_tickets = $db->query("SELECT COUNT(*) as count FROM support_tickets WHERE user_id = '$userId' AND status = 'in_progress'")->fetch_assoc()['count'];
$closed_tickets = $db->query("SELECT COUNT(*) as count FROM support_tickets WHERE user_id = '$userId' AND status = 'closed'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Support Tickets | CampMart</title>
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
    </style>
</head>

<body class="bg-background-main min-h-screen text-text-dark">
    <?php include_once 'includes/user-nav.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
        <div class="max-w-6xl mx-auto space-y-6 md:space-y-8">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-brand-green">Support Tickets</h1>
                    <p class="text-slate-500 mt-1">Manage your support requests and get help from our team.</p>
                </div>
                <button onclick="document.getElementById('createTicketModal').classList.remove('hidden')" class="flex items-center gap-2 px-4 py-2.5 bg-primary hover:bg-primary/90 text-white rounded-lg font-semibold transition-colors shadow-sm">
                    <span class="material-symbols-outlined text-[20px]">add</span>
                    <span>New Ticket</span>
                </button>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="flex flex-col gap-2 rounded-xl p-5 bg-surface-white border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-slate-500 text-sm font-medium">Open Tickets</p>
                        <span class="material-symbols-outlined text-amber-500">pending</span>
                    </div>
                    <p class="text-text-dark text-2xl font-bold tracking-tight"><?php echo $open_tickets; ?></p>
                </div>
                <div class="flex flex-col gap-2 rounded-xl p-5 bg-surface-white border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-slate-500 text-sm font-medium">In Progress</p>
                        <span class="material-symbols-outlined text-blue-500">schedule</span>
                    </div>
                    <p class="text-text-dark text-2xl font-bold tracking-tight"><?php echo $in_progress_tickets; ?></p>
                </div>
                <div class="flex flex-col gap-2 rounded-xl p-5 bg-surface-white border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-slate-500 text-sm font-medium">Resolved</p>
                        <span class="material-symbols-outlined text-emerald-500">check_circle</span>
                    </div>
                    <p class="text-text-dark text-2xl font-bold tracking-tight"><?php echo $closed_tickets; ?></p>
                </div>
            </div>

            <!-- Success Message -->
            <?php if (isset($_GET['success']) && $_GET['success'] == 'ticket_created'): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg flex items-center gap-3">
                <span class="material-symbols-outlined">check_circle</span>
                <p class="text-sm font-medium">Support ticket created successfully! Our team will respond soon.</p>
            </div>
            <?php endif; ?>

            <!-- Tickets List -->
            <div class="bg-surface-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-200">
                    <h2 class="text-lg font-bold text-brand-green">Your Tickets</h2>
                </div>
                <div class="divide-y divide-slate-200">
                    <?php if ($tickets_query && $tickets_query->num_rows > 0):
                        while($ticket = $tickets_query->fetch_assoc()):
                            $status_colors = [
                                'open' => 'bg-amber-100 text-amber-700',
                                'in_progress' => 'bg-blue-100 text-blue-700',
                                'closed' => 'bg-emerald-100 text-emerald-700',
                                'resolved' => 'bg-emerald-100 text-emerald-700'
                            ];
                            $priority_colors = [
                                'low' => 'bg-slate-100 text-slate-700',
                                'medium' => 'bg-amber-100 text-amber-700',
                                'high' => 'bg-red-100 text-red-700'
                            ];
                    ?>
                    <div class="p-5 hover:bg-slate-50 transition-colors">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-start gap-3">
                                    <div class="size-10 rounded-full bg-brand-green/10 text-brand-green flex items-center justify-center shrink-0">
                                        <span class="material-symbols-outlined text-[20px]">support_agent</span>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-text-dark"><?php echo htmlspecialchars($ticket['subject']); ?></h3>
                                        <p class="text-sm text-slate-600 mt-1"><?php echo htmlspecialchars(substr($ticket['description'], 0, 120)); ?>...</p>
                                        <div class="flex flex-wrap items-center gap-2 mt-3">
                                            <span class="<?php echo $status_colors[$ticket['status']] ?? 'bg-slate-100 text-slate-700'; ?> px-2 py-1 rounded text-xs font-semibold uppercase">
                                                <?php echo htmlspecialchars($ticket['status']); ?>
                                            </span>
                                            <span class="<?php echo $priority_colors[$ticket['priority']] ?? 'bg-slate-100 text-slate-700'; ?> px-2 py-1 rounded text-xs font-semibold uppercase">
                                                <?php echo htmlspecialchars($ticket['priority']); ?> Priority
                                            </span>
                                            <span class="text-slate-400 text-xs">
                                                <span class="material-symbols-outlined text-[14px] align-middle">category</span>
                                                <?php echo htmlspecialchars($ticket['category']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <p class="text-xs text-slate-400"><?php echo date('M d, Y g:i A', strtotime($ticket['created_at'])); ?></p>
                                <button onclick="viewTicket(<?php echo $ticket['id']; ?>)" class="text-primary hover:text-primary/80 text-sm font-semibold flex items-center gap-1">
                                    View Details
                                    <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; else: ?>
                    <div class="p-12 text-center">
                        <div class="size-16 rounded-full bg-slate-100 mx-auto flex items-center justify-center mb-4">
                            <span class="material-symbols-outlined text-slate-400 text-[32px]">support_agent</span>
                        </div>
                        <h3 class="text-lg font-semibold text-slate-700 mb-2">No Support Tickets Yet</h3>
                        <p class="text-slate-500 text-sm mb-6">Create your first support ticket if you need help.</p>
                        <button onclick="document.getElementById('createTicketModal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary/90 text-white rounded-lg font-semibold transition-colors">
                            <span class="material-symbols-outlined text-[20px]">add</span>
                            <span>Create Ticket</span>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Help Resources -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-surface-white rounded-xl border border-slate-200 p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="size-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">
                            <span class="material-symbols-outlined">help</span>
                        </div>
                        <h3 class="font-bold text-brand-green">Help Center</h3>
                    </div>
                    <p class="text-sm text-slate-600 mb-4">Browse our knowledge base for quick answers to common questions.</p>
                    <a href="help-center.php" class="text-primary hover:text-primary/80 text-sm font-semibold flex items-center gap-1">
                        Visit Help Center
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </a>
                </div>
                <div class="bg-surface-white rounded-xl border border-slate-200 p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="size-10 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center">
                            <span class="material-symbols-outlined">chat</span>
                        </div>
                        <h3 class="font-bold text-brand-green">Live Chat</h3>
                    </div>
                    <p class="text-sm text-slate-600 mb-4">Get instant help from our support team during business hours.</p>
                    <button class="text-primary hover:text-primary/80 text-sm font-semibold flex items-center gap-1">
                        Start Chat
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- Create Ticket Modal -->
    <div id="createTicketModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-surface-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="sticky top-0 bg-surface-white border-b border-slate-200 p-6 flex items-center justify-between">
                <h2 class="text-xl font-bold text-brand-green">Create Support Ticket</h2>
                <button onclick="document.getElementById('createTicketModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form method="POST" class="p-6 space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Subject *</label>
                    <input type="text" name="subject" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Brief description of your issue">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Category *</label>
                        <select name="category" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                            <option value="">Select Category</option>
                            <option value="account">Account Issues</option>
                            <option value="payments">Payments & Billing</option>
                            <option value="technical">Technical Problems</option>
                            <option value="listings">Listings & Products</option>
                            <option value="orders">Orders & Shipping</option>
                            <option value="verification">Verification</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Priority *</label>
                        <select name="priority" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Description *</label>
                    <textarea name="description" required rows="6" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Describe your issue in detail..."></textarea>
                </div>
                <div class="flex items-center gap-3 pt-4">
                    <button type="submit" name="create_ticket" class="flex-1 px-6 py-3 bg-primary hover:bg-primary/90 text-white rounded-lg font-semibold transition-colors">
                        Submit Ticket
                    </button>
                    <button type="button" onclick="document.getElementById('createTicketModal').classList.add('hidden')" class="px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-semibold transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            sidebar.classList.toggle('-translate-x-full');
            sidebarOverlay.classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden');
        }

        menuToggle.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);

        // Close sidebar when clicking a link on mobile
        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) {
                    toggleSidebar();
                }
            });
        });

        // View ticket details (placeholder function)
        function viewTicket(ticketId) {
            // You can implement a modal or redirect to a detailed ticket view page
            alert('View ticket #' + ticketId + '\n\nThis would open a detailed view of the ticket with all responses and updates.');
        }

        // Close modal when clicking outside
        document.getElementById('createTicketModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    </script>
</body>

</html>
