<?php
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function successResponse($data = null, $message = 'Success', $statusCode = 200) {
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    jsonResponse($response, $statusCode);
}

function errorResponse($message = 'An error occurred', $statusCode = 400, $errors = null) {
    $response = ['success' => false, 'message' => $message];
    if ($errors !== null) {
        $response['errors'] = $errors;
    }
    jsonResponse($response, $statusCode);
}

function paginatedResponse($data, $pagination, $message = 'Success') {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'pagination' => $pagination
    ], 200);
}
