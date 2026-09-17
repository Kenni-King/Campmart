<?php
class ServiceController {

    public static function list() {
        global $db;

        $pagination = getPaginationParams();
        $page = $pagination['page'];
        $perPage = $pagination['per_page'];
        $offset = $pagination['offset'];

        $whereConditions = ["s.status = 'active'"];
        $params = [];
        $types = '';

        if (!empty($_GET['category'])) {
            $catId = (int) $_GET['category'];
            $whereConditions[] = "s.service_category_id = ?";
            $params[] = $catId;
            $types .= 'i';
        }

        if (!empty($_GET['search'])) {
            $search = '%' . $db->real_escape_string($_GET['search']) . '%';
            $whereConditions[] = "(s.title LIKE ? OR s.description LIKE ? OR s.short_description LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $types .= 'sss';
        }

        if (!empty($_GET['min_price'])) {
            $whereConditions[] = "s.price >= ?";
            $params[] = (float) $_GET['min_price'];
            $types .= 'd';
        }

        if (!empty($_GET['max_price'])) {
            $whereConditions[] = "s.price <= ?";
            $params[] = (float) $_GET['max_price'];
            $types .= 'd';
        }

        if (!empty($_GET['pricing_type'])) {
            $pricingType = $db->real_escape_string($_GET['pricing_type']);
            $whereConditions[] = "s.pricing_type = ?";
            $params[] = $pricingType;
            $types .= 's';
        }

        if (!empty($_GET['availability'])) {
            $avail = $db->real_escape_string($_GET['availability']);
            $whereConditions[] = "s.availability = ?";
            $params[] = $avail;
            $types .= 's';
        }

        if (!empty($_GET['provider_id'])) {
            $whereConditions[] = "s.user_id = ?";
            $params[] = (int) $_GET['provider_id'];
            $types .= 'i';
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sortMap = [
            'price_low' => 's.price ASC',
            'price_high' => 's.price DESC',
            'popular' => 's.views_count DESC, s.bookmarks_count DESC',
            'rating' => 's.rating DESC',
            'oldest' => 's.created_at ASC',
        ];
        $orderBy = $sortMap[$_GET['sort'] ?? ''] ?? 's.created_at DESC';

        $countSql = "SELECT COUNT(*) as total FROM services s WHERE $whereClause";
        $countStmt = $db->prepare($countSql);
        if (!empty($params)) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();

        $sql = "SELECT s.*, u.full_name as provider_name, u.username as provider_username,
                       u.rating as provider_rating, u.profile_image as provider_avatar,
                       sc.name as category_name, sc.slug as category_slug,
                       sc.icon as category_icon, sc.color_code as category_color
                FROM services s
                JOIN users u ON s.user_id = u.id
                JOIN service_categories sc ON s.service_category_id = sc.id
                WHERE $whereClause
                ORDER BY $orderBy
                LIMIT ? OFFSET ?";

        $listParams = $params;
        $listTypes = $types . 'ii';
        $listParams[] = $perPage;
        $listParams[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->bind_param($listTypes, ...$listParams);
        $stmt->execute();
        $result = $stmt->get_result();

        $services = [];
        while ($row = $result->fetch_assoc()) {
            $services[] = self::formatService($row);
        }
        $stmt->close();

        paginatedResponse($services, paginate($total, $page, $perPage));
    }

    public static function detail($id) {
        global $db;

        $serviceId = (int) $id;

        $stmt = $db->prepare("SELECT s.*, u.full_name as provider_name, u.username as provider_username,
                u.rating as provider_rating, u.profile_image as provider_avatar, u.bio as provider_bio,
                sc.name as category_name, sc.slug as category_slug,
                sc.icon as category_icon, sc.color_code as category_color
                FROM services s
                JOIN users u ON s.user_id = u.id
                JOIN service_categories sc ON s.service_category_id = sc.id
                WHERE s.id = ?");
        $stmt->bind_param("i", $serviceId);
        $stmt->execute();
        $service = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$service) {
            errorResponse('Service not found', 404);
        }

        $stmt = $db->prepare("UPDATE services SET views_count = views_count + 1 WHERE id = ?");
        $stmt->bind_param("i", $serviceId);
        $stmt->execute();
        $stmt->close();

        $formatted = self::formatService($service);
        $formatted['portfolio_images'] = json_decode($service['portfolio_images'] ?? '[]') ?: [];
        $formatted['skills'] = json_decode($service['skills'] ?? '[]') ?: [];
        $formatted['provider']['bio'] = $service['provider_bio'] ?? null;

        AuthMiddleware::optional();
        $userId = $GLOBALS['api_user']['id'] ?? null;
        if ($userId) {
            $stmt = $db->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND service_id = ?");
            $stmt->bind_param("ii", $userId, $serviceId);
            $stmt->execute();
            $formatted['is_bookmarked'] = $stmt->get_result()->num_rows > 0;
            $stmt->close();
        } else {
            $formatted['is_bookmarked'] = false;
        }

        successResponse($formatted);
    }

    public static function create() {
        global $db;

        $data = getJsonInput();

        $errors = validateRequired($data, ['title', 'service_category_id', 'price', 'description']);
        if (!empty($errors)) {
            errorResponse('Validation failed', 422, $errors);
        }

        $userId = $GLOBALS['api_user']['id'];

        $title = sanitizeInput($data['title']);
        $slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)) . '-' . time();

        $serviceId = dbInsert('services', [
            'user_id' => $userId,
            'service_category_id' => (int) $data['service_category_id'],
            'title' => $title,
            'slug' => $slug,
            'description' => sanitizeInput($data['description']),
            'short_description' => sanitizeInput($data['short_description'] ?? ''),
            'pricing_type' => sanitizeInput($data['pricing_type'] ?? 'fixed'),
            'price' => (float) $data['price'],
            'delivery_time' => sanitizeInput($data['delivery_time'] ?? ''),
            'portfolio_images' => !empty($data['portfolio_images']) ? json_encode($data['portfolio_images']) : null,
            'skills' => !empty($data['skills']) ? json_encode($data['skills']) : null,
        ]);

        if (!$serviceId) {
            errorResponse('Failed to create service', 500);
        }

        successResponse(['id' => (int) $serviceId, 'slug' => $slug], 'Service created successfully', 201);
    }

    public static function update($id) {
        global $db;

        $serviceId = (int) $id;
        $userId = $GLOBALS['api_user']['id'];

        $stmt = $db->prepare("SELECT id FROM services WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $serviceId, $userId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt->close();
            errorResponse('Service not found or unauthorized', 404);
        }
        $stmt->close();

        $data = getJsonInput();
        $updateData = [];

        if (isset($data['title'])) {
            $updateData['title'] = sanitizeInput($data['title']);
            $updateData['slug'] = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title'])) . '-' . time();
        }
        if (isset($data['description'])) $updateData['description'] = sanitizeInput($data['description']);
        if (isset($data['short_description'])) $updateData['short_description'] = sanitizeInput($data['short_description']);
        if (isset($data['price'])) $updateData['price'] = (float) $data['price'];
        if (isset($data['pricing_type'])) $updateData['pricing_type'] = sanitizeInput($data['pricing_type']);
        if (isset($data['delivery_time'])) $updateData['delivery_time'] = sanitizeInput($data['delivery_time']);
        if (isset($data['availability'])) $updateData['availability'] = sanitizeInput($data['availability']);
        if (isset($data['service_category_id'])) $updateData['service_category_id'] = (int) $data['service_category_id'];
        if (isset($data['portfolio_images'])) $updateData['portfolio_images'] = json_encode($data['portfolio_images']);
        if (isset($data['skills'])) $updateData['skills'] = json_encode($data['skills']);

        if (empty($updateData)) {
            errorResponse('No fields to update', 422);
        }

        $result = dbUpdate('services', $updateData, ['id' => $serviceId]);
        if ($result !== 'Successfully Updated') {
            errorResponse('Failed to update service', 500);
        }

        successResponse(null, 'Service updated successfully');
    }

    public static function delete($id) {
        global $db;

        $serviceId = (int) $id;
        $userId = $GLOBALS['api_user']['id'];

        $stmt = $db->prepare("SELECT id FROM services WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $serviceId, $userId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt->close();
            errorResponse('Service not found or unauthorized', 404);
        }
        $stmt->close();

        $result = dbUpdate('services', ['status' => 'inactive'], ['id' => $serviceId]);
        if ($result !== 'Successfully Updated') {
            errorResponse('Failed to delete service', 500);
        }

        successResponse(null, 'Service deleted successfully');
    }

    public static function categories() {
        global $db;

        $stmt = $db->prepare("SELECT id, name, slug, description, icon, color_code FROM service_categories WHERE is_active = 1 ORDER BY name ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'slug' => $row['slug'],
                'description' => $row['description'],
                'icon' => $row['icon'],
                'color_code' => $row['color_code']
            ];
        }

        successResponse($categories);
    }

    public static function userServices($userId) {
        global $db;

        $userId = (int) $userId;
        $pagination = getPaginationParams();
        $page = $pagination['page'];
        $perPage = $pagination['per_page'];
        $offset = $pagination['offset'];

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM services WHERE user_id = ? AND status = 'active'");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $stmt = $db->prepare("SELECT s.*, sc.name as category_name, sc.slug as category_slug,
                sc.icon as category_icon, sc.color_code as category_color
                FROM services s
                JOIN service_categories sc ON s.service_category_id = sc.id
                WHERE s.user_id = ? AND s.status = 'active'
                ORDER BY s.created_at DESC
                LIMIT ? OFFSET ?");
        $stmt->bind_param("iii", $userId, $perPage, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $services = [];
        while ($row = $result->fetch_assoc()) {
            $services[] = self::formatService($row);
        }
        $stmt->close();

        paginatedResponse($services, paginate($total, $page, $perPage));
    }

    private static function formatService($row) {
        return [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'slug' => $row['slug'],
            'description' => $row['description'],
            'short_description' => $row['short_description'],
            'pricing_type' => $row['pricing_type'],
            'price' => (float) $row['price'],
            'delivery_time' => $row['delivery_time'],
            'availability' => $row['availability'],
            'rating' => (float) ($row['rating'] ?? 0),
            'total_ratings' => (int) ($row['total_ratings'] ?? 0),
            'total_orders' => (int) ($row['total_orders'] ?? 0),
            'views_count' => (int) ($row['views_count'] ?? 0),
            'bookmarks_count' => (int) ($row['bookmarks_count'] ?? 0),
            'is_featured' => (bool) ($row['is_featured'] ?? 0),
            'category' => [
                'id' => (int) $row['service_category_id'],
                'name' => $row['category_name'] ?? '',
                'slug' => $row['category_slug'] ?? '',
                'icon' => $row['category_icon'] ?? null,
                'color_code' => $row['category_color'] ?? null
            ],
            'provider' => [
                'id' => (int) $row['user_id'],
                'full_name' => $row['provider_name'] ?? '',
                'username' => $row['provider_username'] ?? '',
                'rating' => (float) ($row['provider_rating'] ?? 0),
                'profile_image' => $row['provider_avatar'] ?? null
            ],
            'created_at' => $row['created_at']
        ];
    }
}