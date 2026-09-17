<?php
class SearchController {

    public static function search() {
        global $db;

        $query = $_GET['q'] ?? '';
        $type = $_GET['type'] ?? 'all';

        if (empty(trim($query))) {
            errorResponse('Search query is required', 422);
        }

        $searchTerm = '%' . $db->real_escape_string(trim($query)) . '%';
        $results = [];

        if ($type === 'all' || $type === 'product') {
            $stmt = $db->prepare("SELECT p.id, p.title, p.slug, p.price, p.condition_type, p.availability,
                    p.is_featured, p.views_count, p.primary_image, c.name as category_name,
                    u.full_name as seller_name, u.username as seller_username, u.profile_image as seller_avatar
                    FROM products p
                    JOIN categories c ON p.category_id = c.id
                    JOIN users u ON p.user_id = u.id
                    WHERE p.status = 'approved' AND p.availability != 'deleted'
                    AND (p.title LIKE ? OR p.description LIKE ?)
                    ORDER BY p.is_featured DESC, p.created_at DESC
                    LIMIT 10");
            $stmt->bind_param("ss", $searchTerm, $searchTerm);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();

            $results['products'] = [];
            while ($row = $result->fetch_assoc()) {
                $results['products'][] = [
                    'id' => (int) $row['id'],
                    'title' => $row['title'],
                    'slug' => $row['slug'],
                    'price' => (float) $row['price'],
                    'condition_type' => $row['condition_type'],
                    'availability' => $row['availability'],
                    'is_featured' => (bool) $row['is_featured'],
                    'views_count' => (int) $row['views_count'],
                    'primary_image' => $row['primary_image'],
                    'category' => ['name' => $row['category_name']],
                    'seller' => [
                        'full_name' => $row['seller_name'],
                        'username' => $row['seller_username'],
                        'profile_image' => $row['seller_avatar']
                    ]
                ];
            }
        }

        if ($type === 'all' || $type === 'service') {
            $stmt = $db->prepare("SELECT s.id, s.title, s.slug, s.short_description, s.price, s.pricing_type,
                    s.rating, s.total_ratings, s.availability, s.is_featured,
                    sc.name as category_name, sc.icon as category_icon,
                    u.full_name as provider_name, u.username as provider_username, u.profile_image as provider_avatar
                    FROM services s
                    JOIN service_categories sc ON s.service_category_id = sc.id
                    JOIN users u ON s.user_id = u.id
                    WHERE s.status = 'active'
                    AND (s.title LIKE ? OR s.description LIKE ? OR s.short_description LIKE ?)
                    ORDER BY s.is_featured DESC, s.rating DESC
                    LIMIT 10");
            $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();

            $results['services'] = [];
            while ($row = $result->fetch_assoc()) {
                $results['services'][] = [
                    'id' => (int) $row['id'],
                    'title' => $row['title'],
                    'slug' => $row['slug'],
                    'short_description' => $row['short_description'],
                    'price' => (float) $row['price'],
                    'pricing_type' => $row['pricing_type'],
                    'rating' => (float) $row['rating'],
                    'total_ratings' => (int) $row['total_ratings'],
                    'availability' => $row['availability'],
                    'is_featured' => (bool) $row['is_featured'],
                    'category' => [
                        'name' => $row['category_name'],
                        'icon' => $row['category_icon']
                    ],
                    'provider' => [
                        'full_name' => $row['provider_name'],
                        'username' => $row['provider_username'],
                        'profile_image' => $row['provider_avatar']
                    ]
                ];
            }
        }

        if ($type === 'all' || $type === 'user') {
            $stmt = $db->prepare("SELECT id, username, full_name, profile_image, rating, total_ratings,
                    total_sales, is_verified
                    FROM users
                    WHERE status = 'active' AND role != 'admin'
                    AND (full_name LIKE ? OR username LIKE ?)
                    ORDER BY rating DESC, total_sales DESC
                    LIMIT 10");
            $stmt->bind_param("ss", $searchTerm, $searchTerm);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();

            $results['users'] = [];
            while ($row = $result->fetch_assoc()) {
                $results['users'][] = [
                    'id' => (int) $row['id'],
                    'username' => $row['username'],
                    'full_name' => $row['full_name'],
                    'profile_image' => $row['profile_image'],
                    'rating' => (float) $row['rating'],
                    'total_ratings' => (int) $row['total_ratings'],
                    'total_sales' => (int) $row['total_sales'],
                    'is_verified' => (bool) $row['is_verified']
                ];
            }
        }

        $totalResults = count($results['products'] ?? []) +
                        count($results['services'] ?? []) +
                        count($results['users'] ?? []);

        successResponse([
            'query' => trim($query),
            'type' => $type,
            'total_results' => $totalResults,
            'products' => $results['products'] ?? [],
            'services' => $results['services'] ?? [],
            'users' => $results['users'] ?? []
        ]);
    }
}