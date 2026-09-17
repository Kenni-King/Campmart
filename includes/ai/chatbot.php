<?php
// Support chatbot. Answers questions about CampMart using a built-in knowledge
// base (kept in sync with help-center.php) + the conversation so far.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/llm.php';

function ai_chatbot_knowledge_base() {
    return <<<KB
CampMart is a peer-to-peer campus marketplace for university students in Nigeria.

ACCOUNTS & VERIFICATION
- Sign up with your official university email; verify it to unlock buying/selling.
- Verification link is emailed; resend from your profile.
- Free to create an account and browse.

BUYING
- Browse/search listings, open an item, click Chat to message the seller.
- Payment is arranged between buyer and seller; always pay AFTER inspecting the item in person.
- Prefer cash; never pay before seeing the item. Meet in safe, public campus locations.
- Check the seller's verified badge, ratings and reviews before buying.
- Inspect items for defects before paying.

SELLING
- Sell via "My Listings" -> new listing; set title, category, price, photos.
- Listings are reviewed by admins before going live.
- No listing fees. CampMart charges a 5% commission only on completed sales.
- Mark an item sold once the transaction is finished.

LOST & FOUND
- Report lost or found items through the Lost & Found portal.
- Include a clear title, category, location and date.
- AI matches lost items with found items automatically.

ORDERS & PAYMENT
- Delivery is arranged between buyer and seller or via campus riders.
- Never release payment until you receive and inspect the item.

SAFETY
- Meet in public campus locations, bring a friend if possible.
- Report scams, suspicious users, or inappropriate content via the report button.

ACCOUNT / TICKETS
- For anything unresolved, create a support ticket in Support Tickets.
- Chat support is available during business hours.

If you do not know the answer, be honest and point the user to the Help Center (help-center.php), support tickets (support-tickets.php), or contact support@campmart.ng. Keep answers short, friendly, and formatted with short paragraphs or bullets.
KB;
}

/**
 * Run a support chat turn. $messages is the full history as
 * [['role' => 'user'|'assistant', 'content' => '...'], ...]
 * Returns the assistant reply string, or null on failure/disabled.
 */
function ai_support_chat($messages) {
    if (!AI_ENABLED || empty(AI_API_KEY)) {
        return null;
    }

    $system = [
        'role' => 'system',
        'content' =>
            "You are CampMart Assistant, the friendly support bot for CampMart, a Nigerian campus marketplace. " .
            "Answer only using the knowledge base below.\n\n" .
            "KNOWLEDGE BASE:\n" . ai_chatbot_knowledge_base(),
    ];

    $history = [];
    foreach ((array) $messages as $m) {
        $role = ($m['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
        $content = trim((string) ($m['content'] ?? ''));
        if ($content === '') {
            continue;
        }
        $history[] = ['role' => $role, 'content' => mb_substr($content, 0, 4000)];
    }
    if (empty($history) || end($history)['role'] !== 'user') {
        $history[] = ['role' => 'user', 'content' => 'Hello'];
    }

    $payload = array_merge([$system], array_slice($history, -12));

    return ai_chat($payload, ['temperature' => 0.4, 'max_tokens' => 400, 'cache' => null]);
}
