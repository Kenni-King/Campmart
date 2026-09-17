<?php
function paginate($total, $page, $perPage = 20) {
    $totalPages = max(1, ceil($total / $perPage));
    $currentPage = max(1, min($page, $totalPages));

    return [
        'current_page' => (int) $currentPage,
        'per_page' => (int) $perPage,
        'total_items' => (int) $total,
        'total_pages' => (int) $totalPages,
        'has_next' => $currentPage < $totalPages,
        'has_prev' => $currentPage > 1
    ];
}

function getPaginationParams($defaultPerPage = 20) {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = max(1, min(100, (int) ($_GET['per_page'] ?? $defaultPerPage)));
    $offset = ($page - 1) * $perPage;

    return ['page' => $page, 'per_page' => $perPage, 'offset' => $offset];
}
