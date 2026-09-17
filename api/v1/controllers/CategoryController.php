<?php
class CategoryController {

    public static function list() {
        global $db;

        $stmt = $db->query("SELECT c.*, 
            (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status = 'approved' AND p.availability = 'available') as product_count
            FROM categories c 
            WHERE c.is_active = 1 AND c.parent_id IS NULL 
            ORDER BY c.display_order ASC, c.name ASC");

        $categories = [];
        while ($row = $stmt->fetch_assoc()) {
            $categories[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'slug' => $row['slug'],
                'description' => $row['description'],
                'icon' => $row['icon'],
                'product_count' => (int) $row['product_count']
            ];
        }

        successResponse($categories);
    }

    public static function detail($id) {
        global $db;

        $categoryId = (int) $id;

        $stmt = $db->prepare("SELECT * FROM categories WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();
        $category = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$category) {
            errorResponse('Category not found', 404);
        }

        $stmt = $db->prepare("SELECT id, name, slug, description, icon FROM categories WHERE parent_id = ? AND is_active = 1 ORDER BY display_order ASC");
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();
        $subcategories = [];
        while ($sub = $stmt->get_result()->fetch_assoc()) {
            $subcategories[] = [
                'id' => (int) $sub['id'],
                'name' => $sub['name'],
                'slug' => $sub['slug'],
                'description' => $sub['description'],
                'icon' => $sub['icon']
            ];
        }
        $stmt->close();

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM products WHERE category_id = ? AND status = 'approved' AND availability = 'available'");
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();
        $productCount = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        successResponse([
            'id' => (int) $category['id'],
            'name' => $category['name'],
            'slug' => $category['slug'],
            'description' => $category['description'],
            'icon' => $category['icon'],
            'product_count' => (int) $productCount,
            'subcategories' => $subcategories
        ]);
    }
}
