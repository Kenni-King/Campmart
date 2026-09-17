<?php
require_once(__DIR__ . '/../config/jwt.php');
require_once(__DIR__ . '/../helpers/response.php');

class AuthMiddleware {

    public static function authenticate() {
        $token = JWT::getTokenFromHeader();

        if (!$token) {
            errorResponse('Authorization token required', 401);
        }

        $payload = JWT::decode($token);

        if (!$payload) {
            errorResponse('Invalid or expired token', 401);
        }

        if (($payload['type'] ?? '') !== 'access') {
            errorResponse('Invalid token type', 401);
        }

        global $db;
        $userId = (int) $payload['user_id'];
        $stmt = $db->prepare("SELECT id, username, email, full_name, firstname, lastname, role, status, profile_image FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            errorResponse('User not found', 401);
        }

        if (in_array($user['status'], ['suspended', 'banned'])) {
            errorResponse('Your account has been ' . $user['status'], 403);
        }

        $GLOBALS['api_user'] = $user;
        return $user;
    }

    public static function optional() {
        $token = JWT::getTokenFromHeader();

        if (!$token) {
            $GLOBALS['api_user'] = null;
            return null;
        }

        $payload = JWT::decode($token);
        if (!$payload || ($payload['type'] ?? '') !== 'access') {
            $GLOBALS['api_user'] = null;
            return null;
        }

        global $db;
        $userId = (int) $payload['user_id'];
        $stmt = $db->prepare("SELECT id, username, email, full_name, firstname, lastname, role, status, profile_image FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user || in_array($user['status'], ['suspended', 'banned'])) {
            $GLOBALS['api_user'] = null;
            return null;
        }

        $GLOBALS['api_user'] = $user;
        return $user;
    }

    public static function requireRole($role) {
        $user = self::authenticate();
        if ($user['role'] !== $role) {
            errorResponse('Insufficient permissions', 403);
        }
        return $user;
    }
}
