<?php
class ProductController {

    public static function list() {
        global $db;

        $pagination = getPaginationParams();
        $page = $pagination['page'];
        $perPage = $pagination['per_page'];
        $offset = $pagination['offset'];

        $whereConditions = ["p.status = 'approved'", "p.availability = 'available'"];
        $params = [];
        $types = '';

        AuthMiddleware::optional();
        $userId = $GLOBALS['api_user']['id'] ?? null;
        if ($userId) {
            $stmt = $db->prepare("SELECT university_id FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $uResult = $stmt->get_result();
            $uRow = $uResult->fetch_assoc();
            $stmt->close();
            if ($uRow && $uRow['university_id']) {
                $whereConditions[] = "p.university_id = ?";
                $params[] = (int) $uRow['university_id'];
                $types .= 'i';
            }
        }

        if (!empty($_GET['category'])) {
            $catId = (int) $_GET['category'];
            $whereConditions[] = "p.category_id = ?";
            $params[] = $catId;
            $types .= 'i';
        }

        if (!empty($_GET['search'])) {
            $search = '%' . $db->real_escape_string($_GET['search']) . '%';
            $whereConditions[] = "(p.title LIKE ? OR p.description LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $types .= 'ss';
        }

        if (!empty($_GET['condition'])) {
            $cond = $db->real_escape_string($_GET['condition']);
            $whereConditions[] = "p.condition_type = ?";
            $params[] = $cond;
            $types .= 's';
        }

        if (!empty($_GET['min_price'])) {
            $whereConditions[] = "p.price >= ?";
            $params[] = (float) $_GET['min_price'];
            $types .= 'd';
        }

        if (!empty($_GET['max_price'])) {
            $whereConditions[] = "p.price <= ?";
            $params[] = (float) $_GET['max_price'];
            $types .= 'd';
        }

        if (!empty($_GET['seller_id'])) {
            $whereConditions[] = "p.user_id = ?";
            $params[] = (int) $_GET['seller_id'];
            $types .= 'i';
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sortMap = [
            'price_low' => 'p.price ASC',
            'price_high' => 'p.price DESC',
            'popular' => 'p.views_count DESC, p.bookmarks_count DESC',
            'oldest' => 'p.created_at ASC',
        ];
        $orderBy = $sortMap[$_GET['sort'] ?? ''] ?? 'p.created_at DESC';

        $countSql = "SELECT COUNT(*) as total FROM products p WHERE $whereClause";
        $countStmt = $db->prepare($countSql);
        if (!empty($params)) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();

        $sql = "SELECT p.*, u.full_name as seller_name, u.username as seller_username, u.rating as seller_rating,
                       c.name as category_name, c.slug as category_slug, pi.image_url as primary_image
                FROM products p
                JOIN users u ON p.user_id = u.id
                JOIN categories c ON p.category_id = c.id
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = TRUE
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

        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = self::formatProduct($row);
        }
        $stmt->close();

        paginatedResponse($products, paginate($total, $page, $perPage));
    }

    public static function detail($id) {
        global $db;

        $productId = (int) $id;

        $stmt = $db->prepare("SELECT p.*, u.full_name as seller_name, u.username as seller_username,
                u.rating as seller_rating, u.profile_image as seller_avatar,
                c.name as category_name, c.slug as category_slug
                FROM products p
                JOIN users u ON p.user_id = u.id
                JOIN categories c ON p.category_id = c.id
                WHERE p.id = ?");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) {
            errorResponse('Product not found', 404);
        }

        $stmt = $db->prepare("SELECT image_url, is_primary, display_order FROM product_images WHERE product_id = ? ORDER BY display_order ASC");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $db->prepare("UPDATE products SET views_count = views_count + 1 WHERE id = ?");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $stmt->close();

        $metadata = json_decode($product['metadata'] ?? '{}', true) ?: [];
        $productFormatted = self::formatProduct($product);
        $productFormatted['images'] = $images;
        $productFormatted['stock_quantity'] = $metadata['stock_quantity'] ?? 1;
        $productFormatted['delivery_fee'] = $metadata['delivery_fee'] ?? 1500.0;

        AuthMiddleware::optional();
        $userId = $GLOBALS['api_user']['id'] ?? null;
        if ($userId) {
            $stmt = $db->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param("ii", $userId, $productId);
            $stmt->execute();
            $productFormatted['is_bookmarked'] = $stmt->get_result()->num_rows > 0;
            $stmt->close();
        } else {
            $productFormatted['is_bookmarked'] = false;
        }

        successResponse($productFormatted);
    }

    public static function create() {
        global $db;

        $data = getJsonInput();

        $errors = validateRequired($data, ['title', 'category_id', 'price', 'description']);
        if (!empty($errors)) {
            errorResponse('Validation failed', 422, $errors);
        }

        $userId = $GLOBALS['api_user']['id'];

        $stmt = $db->prepare("SELECT university_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $uData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $title = sanitizeInput($data['title']);
        $slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)) . '-' . time();
        $negotiable = isset($data['negotiable']) ? (int) $data['negotiable'] : 0;
        $availableQuantity = max(1, (int) ($data['available_quantity'] ?? 1));
        $deliveryFee = max(0, (float) ($data['delivery_fee'] ?? 1500));

        $metadata = json_encode([
            'stock_quantity' => $availableQuantity,
            'stock_updated_at' => date('Y-m-d H:i:s'),
            'delivery_fee' => $deliveryFee,
            'delivery_fee_updated_at' => date('Y-m-d H:i:s')
        ]);

        $productId = dbInsert('products', [
            'user_id' => $userId,
            'university_id' => $uData['university_id'] ?? null,
            'category_id' => (int) $data['category_id'],
            'title' => $title,
            'slug' => $slug,
            'description' => sanitizeInput($data['description']),
            'price' => (float) $data['price'],
            'original_price' => !empty($data['original_price']) ? (float) $data['original_price'] : null,
            'condition_type' => sanitizeInput($data['condition_type'] ?? 'good'),
            'negotiable' => $negotiable,
            'availability' => $availableQuantity > 0 ? 'available' : 'sold',
            'metadata' => $metadata
        ]);

        if (!$productId) {
            errorResponse('Failed to create product', 500);
        }

        if (!empty($_FILES['images'])) {
            $uploadDir = __DIR__ . '/../../../uploads/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            require_once(__DIR__ . '/../../../includes/controller.php');
            $processor = new ImageProcessor(85);
            $imageCount = 0;

            $files = $_FILES['images'];
            $fileCount = is_array($files['name']) ? count($files['name']) : 1;

            for ($i = 0; $i < $fileCount && $imageCount < 5; $i++) {
                $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                $fileTmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                $fileError = is_array($files['error']) ? $files['error'][$i] : $files['error'];

                if ($fileError !== UPLOAD_ERR_OK) continue;

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $fileTmp);
                finfo_close($finfo);

                if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'])) continue;

                try {
                    $uniqueId = time() . '_' . bin2hex(random_bytes(4));
                    $baseFileName = "product_" . $productId . "_" . $uniqueId;
                    $processedImages = $processor->processImage($fileTmp, $uploadDir, $baseFileName);
                    $imageUrl = $processedImages['medium'] ?? $processedImages['thumb'] ?? null;

                    if ($imageUrl) {
                        $dbPath = ltrim(str_replace(__DIR__ . '/../../../', '', $imageUrl), '/');
                        dbInsert('product_images', [
                            'product_id' => $productId,
                            'image_url' => $dbPath,
                            'display_order' => $imageCount,
                            'is_primary' => $imageCount === 0 ? 1 : 0
                        ]);
                        $imageCount++;
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }

        successResponse(['id' => (int) $productId, 'slug' => $slug], 'Product created successfully', 201);
    }

    public static function update($id) {
        global $db;

        $productId = (int) $id;
        $userId = $GLOBALS['api_user']['id'];

        $stmt = $db->prepare("SELECT id FROM products WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $productId, $userId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt->close();
            errorResponse('Product not found or unauthorized', 404);
        }
        $stmt->close();

        $data = getJsonInput();
        $updateData = [];

        if (isset($data['title'])) {
            $updateData['title'] = sanitizeInput($data['title']);
            $updateData['slug'] = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title'])) . '-' . time();
        }
        if (isset($data['description'])) $updateData['description'] = sanitizeInput($data['description']);
        if (isset($data['price'])) $updateData['price'] = (float) $data['price'];
        if (isset($data['original_price'])) $updateData['original_price'] = (float) $data['original_price'];
        if (isset($data['category_id'])) $updateData['category_id'] = (int) $data['category_id'];
        if (isset($data['condition_type'])) $updateData['condition_type'] = sanitizeInput($data['condition_type']);
        if (isset($data['negotiable'])) $updateData['negotiable'] = (int) $data['negotiable'];

        if (isset($data['available_quantity'])) {
            $qty = max(0, (int) $data['available_quantity']);
            $metadata = json_decode($db->query("SELECT metadata FROM products WHERE id = $productId")->fetch_assoc()['metadata'] ?? '{}', true) ?: [];
            $metadata['stock_quantity'] = $qty;
            $metadata['stock_updated_at'] = date('Y-m-d H:i:s');
            $updateData['metadata'] = json_encode($metadata);
            $updateData['availability'] = $qty > 0 ? 'available' : 'sold';
        }

        if (empty($updateData)) {
            errorResponse('No fields to update', 422);
        }

        $result = dbUpdate('products', $updateData, ['id' => $productId]);
        if ($result !== 'Successfully Updated') {
            errorResponse('Failed to update product', 500);
        }

        successResponse(null, 'Product updated successfully');
    }

    public static function delete($id) {
        global $db;

        $productId = (int) $id;
        $userId = $GLOBALS['api_user']['id'];

        $stmt = $db->prepare("SELECT id FROM products WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $productId, $userId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt->close();
            errorResponse('Product not found or unauthorized', 404);
        }
        $stmt->close();

        $result = dbUpdate('products', ['availability' => 'deleted', 'status' => 'removed'], ['id' => $productId]);
        if ($result !== 'Successfully Updated') {
            errorResponse('Failed to delete product', 500);
        }

        successResponse(null, 'Product deleted successfully');
    }

    private static function formatProduct($row) {
        $metadata = json_decode($row['metadata'] ?? '{}', true) ?: [];
        return [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'slug' => $row['slug'],
            'description' => $row['description'],
            'price' => (float) $row['price'],
            'original_price' => $row['original_price'] ? (float) $row['original_price'] : null,
            'condition_type' => $row['condition_type'],
            'negotiable' => (bool) $row['negotiable'],
            'availability' => $row['availability'],
            'stock_quantity' => $metadata['stock_quantity'] ?? 1,
            'delivery_fee' => $metadata['delivery_fee'] ?? 1500.0,
            'category' => [
                'id' => (int) $row['category_id'],
                'name' => $row['category_name'] ?? '',
                'slug' => $row['category_slug'] ?? ''
            ],
            'seller' => [
                'id' => (int) $row['user_id'],
                'full_name' => $row['seller_name'] ?? '',
                'username' => $row['seller_username'] ?? '',
                'rating' => (float) ($row['seller_rating'] ?? 0)
            ],
            'primary_image' => BASE_URL . $row['primary_image'] ?? null,
            'views_count' => (int) ($row['views_count'] ?? 0),
            'bookmarks_count' => (int) ($row['bookmarks_count'] ?? 0),
            'created_at' => $row['created_at']
        ];
    }
}
