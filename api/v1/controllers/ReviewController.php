<?php
class ReviewController {

    public static function productReviews($productId) {
        global $db;

        $productId = (int) $productId;
        $pagination = getPaginationParams();
        $page = $pagination['page'];
        $perPage = $pagination['per_page'];
        $offset = $pagination['offset'];

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM reviews WHERE product_id = ? AND status = 'approved'");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $stmt = $db->prepare("SELECT r.*, u.full_name as reviewer_name, u.username as reviewer_username,
                u.profile_image as reviewer_avatar
                FROM reviews r
                JOIN users u ON r.reviewer_id = u.id
                WHERE r.product_id = ? AND r.status = 'approved'
                ORDER BY r.created_at DESC
                LIMIT ? OFFSET ?");
        $stmt->bind_param("iii", $productId, $perPage, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = self::formatReview($row);
        }
        $stmt->close();

        paginatedResponse($reviews, paginate($total, $page, $perPage));
    }

    public static function serviceReviews($serviceId) {
        global $db;

        $serviceId = (int) $serviceId;
        $pagination = getPaginationParams();
        $page = $pagination['page'];
        $perPage = $pagination['per_page'];
        $offset = $pagination['offset'];

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM reviews WHERE service_id = ? AND status = 'approved'");
        $stmt->bind_param("i", $serviceId);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $stmt = $db->prepare("SELECT r.*, u.full_name as reviewer_name, u.username as reviewer_username,
                u.profile_image as reviewer_avatar
                FROM reviews r
                JOIN users u ON r.reviewer_id = u.id
                WHERE r.service_id = ? AND r.status = 'approved'
                ORDER BY r.created_at DESC
                LIMIT ? OFFSET ?");
        $stmt->bind_param("iii", $serviceId, $perPage, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = self::formatReview($row);
        }
        $stmt->close();

        paginatedResponse($reviews, paginate($total, $page, $perPage));
    }

    public static function userReviews($userId) {
        global $db;

        $userId = (int) $userId;
        $pagination = getPaginationParams();
        $page = $pagination['page'];
        $perPage = $pagination['per_page'];
        $offset = $pagination['offset'];

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM reviews WHERE reviewed_user_id = ? AND status = 'approved'");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $stmt = $db->prepare("SELECT r.*, u.full_name as reviewer_name, u.username as reviewer_username,
                u.profile_image as reviewer_avatar,
                p.title as product_title, s.title as service_title
                FROM reviews r
                JOIN users u ON r.reviewer_id = u.id
                LEFT JOIN products p ON r.product_id = p.id
                LEFT JOIN services s ON r.service_id = s.id
                WHERE r.reviewed_user_id = ? AND r.status = 'approved'
                ORDER BY r.created_at DESC
                LIMIT ? OFFSET ?");
        $stmt->bind_param("iii", $userId, $perPage, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $review = self::formatReview($row);
            if ($row['product_id'] && $row['product_title']) {
                $review['product'] = ['id' => (int) $row['product_id'], 'title' => $row['product_title']];
            }
            if ($row['service_id'] && $row['service_title']) {
                $review['service'] = ['id' => (int) $row['service_id'], 'title' => $row['service_title']];
            }
            $reviews[] = $review;
        }
        $stmt->close();

        paginatedResponse($reviews, paginate($total, $page, $perPage));
    }

    public static function create() {
        global $db;

        $data = getJsonInput();

        $errors = validateRequired($data, ['reviewed_user_id', 'rating', 'review_type']);
        if (!empty($errors)) {
            errorResponse('Validation failed', 422, $errors);
        }

        $rating = (int) $data['rating'];
        if ($rating < 1 || $rating > 5) {
            errorResponse('Rating must be between 1 and 5', 422);
        }

        $validTypes = ['product', 'service', 'seller', 'buyer'];
        if (!in_array($data['review_type'], $validTypes)) {
            errorResponse('Invalid review type', 422);
        }

        $userId = $GLOBALS['api_user']['id'];

        if ((int) $data['reviewed_user_id'] === $userId) {
            errorResponse('You cannot review yourself', 422);
        }

        if (!empty($data['transaction_id'])) {
            $stmt = $db->prepare("SELECT id FROM reviews WHERE reviewer_id = ? AND transaction_id = ?");
            $stmt->bind_param("ii", $userId, $data['transaction_id']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $stmt->close();
                errorResponse('You have already reviewed this transaction', 409);
            }
            $stmt->close();
        }

        $reviewId = dbInsert('reviews', [
            'reviewer_id' => $userId,
            'reviewed_user_id' => (int) $data['reviewed_user_id'],
            'transaction_id' => !empty($data['transaction_id']) ? (int) $data['transaction_id'] : null,
            'product_id' => !empty($data['product_id']) ? (int) $data['product_id'] : null,
            'service_id' => !empty($data['service_id']) ? (int) $data['service_id'] : null,
            'review_type' => sanitizeInput($data['review_type']),
            'rating' => $rating,
            'review_text' => sanitizeInput($data['review_text'] ?? ''),
            'images' => !empty($data['images']) ? json_encode($data['images']) : null,
            'is_verified_purchase' => !empty($data['transaction_id']) ? 1 : 0,
        ]);

        if (!$reviewId) {
            errorResponse('Failed to create review', 500);
        }

        successResponse(['id' => (int) $reviewId], 'Review submitted successfully', 201);
    }

    public static function delete($id) {
        global $db;

        $reviewId = (int) $id;
        $userId = $GLOBALS['api_user']['id'];

        $stmt = $db->prepare("SELECT id FROM reviews WHERE id = ? AND reviewer_id = ?");
        $stmt->bind_param("ii", $reviewId, $userId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt->close();
            errorResponse('Review not found or unauthorized', 404);
        }
        $stmt->close();

        $result = dbUpdate('reviews', ['status' => 'rejected'], ['id' => $reviewId]);
        if ($result !== 'Successfully Updated') {
            errorResponse('Failed to delete review', 500);
        }

        successResponse(null, 'Review deleted successfully');
    }

    private static function formatReview($row) {
        return [
            'id' => (int) $row['id'],
            'rating' => (int) $row['rating'],
            'review_text' => $row['review_text'],
            'images' => json_decode($row['images'] ?? '[]') ?: [],
            'is_verified_purchase' => (bool) $row['is_verified_purchase'],
            'helpful_count' => (int) ($row['helpful_count'] ?? 0),
            'review_type' => $row['review_type'],
            'reviewer' => [
                'id' => (int) $row['reviewer_id'],
                'full_name' => $row['reviewer_name'] ?? '',
                'username' => $row['reviewer_username'] ?? '',
                'profile_image' => $row['reviewer_avatar'] ?? null
            ],
            'created_at' => $row['created_at']
        ];
    }
}