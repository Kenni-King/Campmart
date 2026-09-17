<?php
require_once(__DIR__ . '/../../../includes/constant.php');
require_once(__DIR__ . '/../../../includes/function.php');

define('BASE_URL', 'https://campmart.ng/');

if ($db->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}
