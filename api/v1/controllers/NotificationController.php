<?php
class NotificationController {

    public static function list() {
        global $db;

        $userId = $GLOBALS['api_user']['id'];
        $pagination = getPaginationParams();
        $page = $pagination['page'];
        $perPage = $pagination['per_page'];
        $offset = $pagination['offset'];

        $whereConditions = ["n.user_id = ?"];
        $params = [$userId];
        $types = 'i';

        if (!empty($_GET['type'])) {
            $type = $db->real_escape_string($_GET['type']);
            $whereConditions[] = "n.type = ?";
            $params[] = $type;
            $types .= 's';
        }

        if (isset($_GET['is_read'])) {
            $whereConditions[] = "n.is_read = ?";
            $params[] = (int) $_GET['is_read'];
            $types .= 'i';
        }

        $whereClause = implode(' AND ', $whereConditions);

        $countSql = "SELECT COUNT(*) as total FROM notifications n WHERE $whereClause";
        $countStmt = $db->prepare($countSql);
        $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();

        $sql = "SELECT n.* FROM notifications n
                WHERE $whereClause
                ORDER BY n.created_at DESC
                LIMIT ? OFFSET ?";

        $listParams = $params;
        $listTypes = $types . 'ii';
        $listParams[] = $perPage;
        $listParams[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->bind_param($listTypes, ...$listParams);
        $stmt->execute();
        $result = $stmt->get_result();

        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'message' => $row['message'],
                'type' => $row['type'],
                'related_id' => $row['related_id'] ? (int) $row['related_id'] : null,
                'related_type' => $row['related_type'],
                'action_url' => $row['action_url'],
                'is_read' => (bool) $row['is_read'],
                'read_at' => $row['read_at'],
                'created_at' => $row['created_at']
            ];
        }
        $stmt->close();

        paginatedResponse($notifications, paginate($total, $page, $perPage));
    }

    public static function unreadCount() {
        global $db;

        $userId = $GLOBALS['api_user']['id'];

        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $count = (int) $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();

        successResponse(['unread' => $count]);
    }

    public static function markRead($id) {
        global $db;

        $notifId = (int) $id;
        $userId = $GLOBALS['api_user']['id'];

        $stmt = $db->prepare("SELECT id FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notifId, $userId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt->close();
            errorResponse('Notification not found', 404);
        }
        $stmt->close();

        $result = dbUpdate('notifications', [
            'is_read' => 1,
            'read_at' => date('Y-m-d H:i:s')
        ], ['id' => $notifId]);

        if ($result !== 'Successfully Updated') {
            errorResponse('Failed to mark as read', 500);
        }

        successResponse(null, 'Notification marked as read');
    }

    public static function markAllRead() {
        global $db;

        $userId = $GLOBALS['api_user']['id'];

        $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        successResponse(['updated' => $affected], 'All notifications marked as read');
    }

    public static function delete($id) {
        global $db;

        $notifId = (int) $id;
        $userId = $GLOBALS['api_user']['id'];

        $stmt = $db->prepare("SELECT id FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notifId, $userId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt->close();
            errorResponse('Notification not found', 404);
        }
        $stmt->close();

        dbDelete('notifications', ['id' => $notifId]);

        successResponse(null, 'Notification deleted');
    }
}