<?php
class BookmarkController {

    public static function toggle() {
        global $db;

        $userId = $GLOBALS['api_user']['id'];
        $data = getJsonInput();

        $productId = isset($data['product_id']) ? (int) $data['product_id'] : null;
        $serviceId = isset($data['service_id']) ? (int) $data['service_id'] : null;

        if (!$productId && !$serviceId) {
            errorResponse('product_id or service_id is required', 422);
        }

        $bookmarkType = $productId ? 'product' : 'service';
        $itemId = $productId ?: $serviceId;

        $stmt = $db->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND {$bookmarkType}_id = ?");
        $stmt->bind_param("ii", $userId, $itemId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            $stmt = $db->prepare("DELETE FROM bookmarks WHERE id = ?");
            $stmt->bind_param("i", $existing['id']);
            $stmt->execute();
            $stmt->close();
            successResponse(['bookmarked' => false], 'Bookmark removed');
        } else {
            $col = $bookmarkType . '_id';
            dbInsert('bookmarks', [
                'user_id' => $userId,
                $col => $itemId,
                'bookmark_type' => $bookmarkType
            ]);
            successResponse(['bookmarked' => true], 'Bookmarked successfully', 201);
        }
    }

    public static function list() {
        global $db;

        $userId = $GLOBALS['api_user']['id'];
        $pagination = getPaginationParams();

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM bookmarks WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $stmt = $db->prepare("SELECT b.*, 
            p.title as product_title, p.price as product_price, p.slug as product_slug, pi.image_url as product_image,
            s.title as service_title, s.price as service_price, s.slug as service_slug
            FROM bookmarks b
            LEFT JOIN products p ON b.product_id = p.id
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = TRUE
            LEFT JOIN services s ON b.service_id = s.id
            WHERE b.user_id = ?
            ORDER BY b.created_at DESC
            LIMIT ? OFFSET ?");

        $perPage = $pagination['per_page'];
        $offset = $pagination['offset'];
        $stmt->bind_param("iii", $userId, $perPage, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $bookmarks = [];
        while ($row = $result->fetch_assoc()) {
            $bookmark = [
                'id' => (int) $row['id'],
                'type' => $row['bookmark_type'],
                'created_at' => $row['created_at']
            ];

            if ($row['bookmark_type'] === 'product') {
                $bookmark['product'] = [
                    'id' => (int) $row['product_id'],
                    'title' => $row['product_title'],
                    'price' => (float) $row['product_price'],
                    'slug' => $row['product_slug'],
                    'image' => $row['product_image']
                ];
            } else {
                $bookmark['service'] = [
                    'id' => (int) $row['service_id'],
                    'title' => $row['service_title'],
                    'price' => (float) $row['service_price'],
                    'slug' => $row['service_slug']
                ];
            }

            $bookmarks[] = $bookmark;
        }
        $stmt->close();

        paginatedResponse($bookmarks, paginate($total, $pagination['page'], $perPage));
    }
}
