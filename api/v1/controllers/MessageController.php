<?php
class MessageController {

    public static function conversations() {
        global $db;

        $userId = $GLOBALS['api_user']['id'];
        $pagination = getPaginationParams();
        $page = $pagination['page'];
        $perPage = $pagination['per_page'];
        $offset = $pagination['offset'];

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM conversations WHERE user1_id = ? OR user2_id = ?");
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $stmt = $db->prepare("SELECT c.*,
                u1.full_name as user1_name, u1.username as user1_username, u1.profile_image as user1_avatar,
                u2.full_name as user2_name, u2.username as user2_username, u2.profile_image as user2_avatar,
                p.title as product_title, p.id as product_id,
                pi.image_url as product_image,
                s.title as service_title, s.id as service_id
                FROM conversations c
                JOIN users u1 ON c.user1_id = u1.id
                JOIN users u2 ON c.user2_id = u2.id
                LEFT JOIN products p ON c.product_id = p.id
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = TRUE
                LEFT JOIN services s ON c.service_id = s.id
                WHERE c.user1_id = ? OR c.user2_id = ?
                ORDER BY c.last_message_at DESC
                LIMIT ? OFFSET ?");
        $stmt->bind_param("iiii", $userId, $userId, $perPage, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $conversations = [];
        while ($row = $result->fetch_assoc()) {
            $otherUserId = (int) $row['user1_id'] === $userId ? (int) $row['user2_id'] : (int) $row['user1_id'];
            $otherUserName = (int) $row['user1_id'] === $userId ? $row['user2_name'] : $row['user1_name'];
            $otherUsername = (int) $row['user1_id'] === $userId ? $row['user2_username'] : $row['user1_username'];
            $otherUserAvatar = (int) $row['user1_id'] === $userId ? $row['user2_avatar'] : $row['user1_avatar'];

            $unreadStmt = $db->prepare("SELECT COUNT(*) as count FROM messages WHERE conversation_id = ? AND sender_id != ? AND is_read = 0");
            $unreadStmt->bind_param("ii", $row['id'], $userId);
            $unreadStmt->execute();
            $unreadCount = (int) $unreadStmt->get_result()->fetch_assoc()['count'];
            $unreadStmt->close();

            $conversation = [
                'id' => (int) $row['id'],
                'other_user' => [
                    'id' => $otherUserId,
                    'full_name' => $otherUserName,
                    'username' => $otherUsername,
                    'profile_image' => $otherUserAvatar
                ],
                'last_message' => null,
                'unread_count' => $unreadCount,
                'last_message_at' => $row['last_message_at'],
                'created_at' => $row['created_at']
            ];

            if ($row['last_message_id']) {
                $msgStmt = $db->prepare("SELECT message, sender_id, created_at FROM messages WHERE id = ?");
                $msgStmt->bind_param("i", $row['last_message_id']);
                $msgStmt->execute();
                $lastMsg = $msgStmt->get_result()->fetch_assoc();
                $msgStmt->close();
                if ($lastMsg) {
                    $conversation['last_message'] = [
                        'message' => $lastMsg['message'],
                        'sender_id' => (int) $lastMsg['sender_id'],
                        'created_at' => $lastMsg['created_at']
                    ];
                }
            }

            if ($row['product_id']) {
                $conversation['product'] = [
                    'id' => (int) $row['product_id'],
                    'title' => $row['product_title'],
                    'image' => $row['product_image']
                ];
            }
            if ($row['service_id']) {
                $conversation['service'] = [
                    'id' => (int) $row['service_id'],
                    'title' => $row['service_title']
                ];
            }

            $conversations[] = $conversation;
        }
        $stmt->close();

        paginatedResponse($conversations, paginate($total, $page, $perPage));
    }

    public static function messages($conversationId) {
        global $db;

        $convId = (int) $conversationId;
        $userId = $GLOBALS['api_user']['id'];

        $stmt = $db->prepare("SELECT id FROM conversations WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
        $stmt->bind_param("iii", $convId, $userId, $userId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt->close();
            errorResponse('Conversation not found', 404);
        }
        $stmt->close();

        $pagination = getPaginationParams(50);
        $page = $pagination['page'];
        $perPage = $pagination['per_page'];
        $offset = $pagination['offset'];

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM messages WHERE conversation_id = ?");
        $stmt->bind_param("i", $convId);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $stmt = $db->prepare("SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC LIMIT ? OFFSET ?");
        $stmt->bind_param("iii", $convId, $perPage, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = [
                'id' => (int) $row['id'],
                'message' => $row['message'],
                'attachment_url' => $row['attachment_url'],
                'sender_id' => (int) $row['sender_id'],
                'is_read' => (bool) $row['is_read'],
                'read_at' => $row['read_at'],
                'created_at' => $row['created_at']
            ];
        }
        $stmt->close();

        paginatedResponse($messages, paginate($total, $page, $perPage));
    }

    public static function startConversation() {
        global $db;

        $userId = $GLOBALS['api_user']['id'];
        $data = getJsonInput();

        $targetUserId = isset($data['user_id']) ? (int) $data['user_id'] : 0;
        $productId = !empty($data['product_id']) ? (int) $data['product_id'] : null;
        $serviceId = !empty($data['service_id']) ? (int) $data['service_id'] : null;
        $message = $data['message'] ?? '';

        if (!$targetUserId) {
            errorResponse('user_id is required', 422);
        }

        if (empty($message)) {
            errorResponse('message is required', 422);
        }

        if ($targetUserId === $userId) {
            errorResponse('Cannot start conversation with yourself', 422);
        }

        $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->bind_param("i", $targetUserId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt->close();
            errorResponse('User not found', 404);
        }
        $stmt->close();

        $existingConvId = null;
        if ($productId) {
            $stmt = $db->prepare("SELECT id FROM conversations WHERE ((user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)) AND product_id = ?");
            $stmt->bind_param("iiiii", $userId, $targetUserId, $targetUserId, $userId, $productId);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($existing) $existingConvId = (int) $existing['id'];
        }

        if (!$existingConvId) {
            $convId = dbInsert('conversations', [
                'user1_id' => $userId,
                'user2_id' => $targetUserId,
                'product_id' => $productId,
                'service_id' => $serviceId,
            ]);

            if (!$convId) {
                errorResponse('Failed to create conversation', 500);
            }
        } else {
            $convId = $existingConvId;
        }

        $msgId = dbInsert('messages', [
            'conversation_id' => $convId,
            'sender_id' => $userId,
            'receiver_id' => $targetUserId,
            'message' => strip_tags(trim($message)),
        ]);

        if (!$msgId) {
            errorResponse('Failed to send message', 500);
        }

        dbUpdate('conversations', [
            'last_message_at' => date('Y-m-d H:i:s'),
            'last_message_id' => $msgId,
        ], ['id' => $convId]);

        dbInsert('notifications', [
            'user_id' => $targetUserId,
            'title' => 'New Message',
            'message' => 'You have a new message from ' . $GLOBALS['api_user']['full_name'],
            'type' => 'message',
            'related_id' => $convId,
            'related_type' => 'conversation',
        ]);

        successResponse([
            'conversation_id' => (int) $convId,
            'message_id' => (int) $msgId
        ], 'Conversation started', 201);
    }

    public static function sendMessage($conversationId) {
        global $db;

        $convId = (int) $conversationId;
        $userId = $GLOBALS['api_user']['id'];

        $stmt = $db->prepare("SELECT id, user1_id, user2_id FROM conversations WHERE id = ?");
        $stmt->bind_param("i", $convId);
        $stmt->execute();
        $conv = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$conv) {
            errorResponse('Conversation not found', 404);
        }

        if ((int) $conv['user1_id'] !== $userId && (int) $conv['user2_id'] !== $userId) {
            errorResponse('Unauthorized', 403);
        }

        $data = getJsonInput();
        $message = $data['message'] ?? '';
        if (empty($message)) {
            errorResponse('message is required', 422);
        }

        $receiverId = (int) $conv['user1_id'] === $userId ? (int) $conv['user2_id'] : (int) $conv['user1_id'];

        $msgId = dbInsert('messages', [
            'conversation_id' => $convId,
            'sender_id' => $userId,
            'receiver_id' => $receiverId,
            'message' => strip_tags(trim($message)),
            'attachment_url' => !empty($data['attachment_url']) ? strip_tags(trim($data['attachment_url'])) : null,
        ]);

        if (!$msgId) {
            errorResponse('Failed to send message', 500);
        }

        dbUpdate('conversations', [
            'last_message_at' => date('Y-m-d H:i:s'),
            'last_message_id' => $msgId,
        ], ['id' => $convId]);

        dbInsert('notifications', [
            'user_id' => $receiverId,
            'title' => 'New Message',
            'message' => 'You have a new message from ' . $GLOBALS['api_user']['full_name'],
            'type' => 'message',
            'related_id' => $convId,
            'related_type' => 'conversation',
        ]);

        successResponse([
            'id' => (int) $msgId,
            'conversation_id' => $convId,
            'sender_id' => $userId,
            'message' => strip_tags(trim($message)),
            'created_at' => date('Y-m-d H:i:s')
        ], 'Message sent', 201);
    }

    public static function markRead($conversationId) {
        global $db;

        $convId = (int) $conversationId;
        $userId = $GLOBALS['api_user']['id'];

        $stmt = $db->prepare("SELECT id FROM conversations WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
        $stmt->bind_param("iii", $convId, $userId, $userId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt->close();
            errorResponse('Conversation not found', 404);
        }
        $stmt->close();

        $stmt = $db->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0");
        $stmt->bind_param("ii", $convId, $userId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        successResponse(['updated' => $affected], 'Messages marked as read');
    }

    public static function unreadCount() {
        global $db;

        $userId = $GLOBALS['api_user']['id'];

        $stmt = $db->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $count = (int) $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();

        successResponse(['unread' => $count]);
    }
}