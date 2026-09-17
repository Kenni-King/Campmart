<?php
require_once(__DIR__ . '/config/cors.php');
require_once(__DIR__ . '/config/database.php');
require_once(__DIR__ . '/helpers/response.php');
require_once(__DIR__ . '/helpers/validator.php');
require_once(__DIR__ . '/helpers/pagination.php');
require_once(__DIR__ . '/middleware/AuthMiddleware.php');
require_once(__DIR__ . '/controllers/AuthController.php');
require_once(__DIR__ . '/controllers/ProductController.php');
require_once(__DIR__ . '/controllers/CategoryController.php');
require_once(__DIR__ . '/controllers/UserController.php');
require_once(__DIR__ . '/controllers/BookmarkController.php');
require_once(__DIR__ . '/controllers/CartController.php');
require_once(__DIR__ . '/controllers/OrderController.php');
require_once(__DIR__ . '/controllers/ServiceController.php');
require_once(__DIR__ . '/controllers/ReviewController.php');
require_once(__DIR__ . '/controllers/NotificationController.php');
require_once(__DIR__ . '/controllers/MessageController.php');
require_once(__DIR__ . '/controllers/SearchController.php');

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

$path = parse_url($uri, PHP_URL_PATH);
$path = preg_replace('#^/campmartv2/api/v1#', '', $path);
$path = '/' . trim($path, '/');

$segments = array_values(array_filter(explode('/', $path)));

$resource = $segments[0] ?? '';
$id = $segments[1] ?? null;
$sub = $segments[2] ?? null;

try {
    switch ($resource) {

        case 'auth':
            switch ($method . ' ' . ($id ?? '')) {
                case 'POST signup':
                    AuthController::signup();
                    break;
                case 'POST login':
                    AuthController::login();
                    break;
                case 'POST logout':
                    AuthMiddleware::authenticate();
                    AuthController::logout();
                    break;
                case 'POST refresh':
                    AuthController::refresh();
                    break;
                case 'GET me':
                    AuthMiddleware::authenticate();
                    AuthController::me();
                    break;
                default:
                    errorResponse('Endpoint not found', 404);
            }
            break;

        case 'products':
            switch ($method) {
                case 'GET':
                    if ($id) {
                        ProductController::detail($id);
                    } else {
                        ProductController::list();
                    }
                    break;
                case 'POST':
                    AuthMiddleware::authenticate();
                    ProductController::create();
                    break;
                case 'PUT':
                    AuthMiddleware::authenticate();
                    ProductController::update($id);
                    break;
                case 'DELETE':
                    AuthMiddleware::authenticate();
                    ProductController::delete($id);
                    break;
                default:
                    errorResponse('Method not allowed', 405);
            }
            break;

        case 'categories':
            switch ($method) {
                case 'GET':
                    if ($id) {
                        CategoryController::detail($id);
                    } else {
                        CategoryController::list();
                    }
                    break;
                default:
                    errorResponse('Method not allowed', 405);
            }
            break;

        case 'user':
            switch ($method . ' ' . ($id ?? '')) {
                case 'GET profile':
                    AuthMiddleware::authenticate();
                    UserController::profile();
                    break;
                case 'PUT profile':
                    AuthMiddleware::authenticate();
                    UserController::updateProfile();
                    break;
                case 'PUT password':
                    AuthMiddleware::authenticate();
                    UserController::changePassword();
                    break;
                case 'POST avatar':
                    AuthMiddleware::authenticate();
                    UserController::uploadAvatar();
                    break;
                default:
                    errorResponse('Endpoint not found', 404);
            }
            break;

        case 'bookmarks':
            switch ($method) {
                case 'GET':
                    AuthMiddleware::authenticate();
                    BookmarkController::list();
                    break;
                case 'POST':
                    AuthMiddleware::authenticate();
                    BookmarkController::toggle();
                    break;
                default:
                    errorResponse('Method not allowed', 405);
            }
            break;

        case 'cart':
            switch ($method) {
                case 'GET':
                    AuthMiddleware::authenticate();
                    CartController::get();
                    break;
                case 'POST':
                    AuthMiddleware::authenticate();
                    CartController::add();
                    break;
                case 'PUT':
                    AuthMiddleware::authenticate();
                    CartController::update($id);
                    break;
                case 'DELETE':
                    AuthMiddleware::authenticate();
                    CartController::remove($id);
                    break;
                default:
                    errorResponse('Method not allowed', 405);
            }
            break;

        case 'orders':
            switch ($method) {
                case 'GET':
                    AuthMiddleware::authenticate();
                    if ($id) {
                        OrderController::detail($id);
                    } else {
                        OrderController::list();
                    }
                    break;
                case 'POST':
                    AuthMiddleware::authenticate();
                    OrderController::place();
                    break;
                default:
                    errorResponse('Method not allowed', 405);
            }
            break;

        case 'services':
            switch ($method) {
                case 'GET':
                    if ($id) {
                        ServiceController::detail($id);
                    } else {
                        ServiceController::list();
                    }
                    break;
                case 'POST':
                    AuthMiddleware::authenticate();
                    ServiceController::create();
                    break;
                case 'PUT':
                    AuthMiddleware::authenticate();
                    ServiceController::update($id);
                    break;
                case 'DELETE':
                    AuthMiddleware::authenticate();
                    ServiceController::delete($id);
                    break;
                default:
                    errorResponse('Method not allowed', 405);
            }
            break;

        case 'service-categories':
            switch ($method) {
                case 'GET':
                    ServiceController::categories();
                    break;
                default:
                    errorResponse('Method not allowed', 405);
            }
            break;

        case 'reviews':
            switch ($method) {
                case 'GET':
                    if (!empty($_GET['product_id'])) {
                        ReviewController::productReviews($_GET['product_id']);
                    } elseif (!empty($_GET['service_id'])) {
                        ReviewController::serviceReviews($_GET['service_id']);
                    } elseif (!empty($_GET['user_id'])) {
                        ReviewController::userReviews($_GET['user_id']);
                    } else {
                        errorResponse('product_id, service_id, or user_id is required', 422);
                    }
                    break;
                case 'POST':
                    AuthMiddleware::authenticate();
                    ReviewController::create();
                    break;
                case 'DELETE':
                    AuthMiddleware::authenticate();
                    ReviewController::delete($id);
                    break;
                default:
                    errorResponse('Method not allowed', 405);
            }
            break;

        case 'notifications':
            switch ($method . ' ' . ($id ?? '')) {
                case 'GET count':
                    AuthMiddleware::authenticate();
                    NotificationController::unreadCount();
                    break;
                case 'GET':
                    AuthMiddleware::authenticate();
                    NotificationController::list();
                    break;
                case 'PUT read-all':
                    AuthMiddleware::authenticate();
                    NotificationController::markAllRead();
                    break;
                case 'PUT':
                    AuthMiddleware::authenticate();
                    NotificationController::markRead($id);
                    break;
                case 'DELETE':
                    AuthMiddleware::authenticate();
                    NotificationController::delete($id);
                    break;
                default:
                    errorResponse('Endpoint not found', 404);
            }
            break;

        case 'chats':
            switch ($method . ' ' . ($id ?? '')) {
                case 'GET count':
                    AuthMiddleware::authenticate();
                    MessageController::unreadCount();
                    break;
                case 'GET':
                    AuthMiddleware::authenticate();
                    MessageController::conversations();
                    break;
                case 'POST':
                    AuthMiddleware::authenticate();
                    MessageController::startConversation();
                    break;
                default:
                    if ($id && $method === 'GET' && $sub === null) {
                        AuthMiddleware::authenticate();
                        MessageController::messages($id);
                    } elseif ($id && $method === 'POST' && $sub === 'messages') {
                        AuthMiddleware::authenticate();
                        MessageController::sendMessage($id);
                    } elseif ($id && $method === 'PUT' && $sub === 'read') {
                        AuthMiddleware::authenticate();
                        MessageController::markRead($id);
                    } else {
                        errorResponse('Endpoint not found', 404);
                    }
            }
            break;

        case 'search':
            switch ($method) {
                case 'GET':
                    SearchController::search();
                    break;
                default:
                    errorResponse('Method not allowed', 405);
            }
            break;

        case 'users':
            if ($id && $sub === 'services') {
                switch ($method) {
                    case 'GET':
                        ServiceController::userServices($id);
                        break;
                    default:
                        errorResponse('Method not allowed', 405);
                }
            } else {
                errorResponse('API endpoint not found', 404);
            }
            break;

        default:
            errorResponse('API endpoint not found', 404);
    }
} catch (Exception $e) {
    errorResponse('Server error: ' . $e->getMessage(), 500);
}
