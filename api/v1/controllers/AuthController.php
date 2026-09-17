<?php
require_once(__DIR__ . '/../config/jwt.php');

class AuthController {

    public static function signup() {
        global $db;

        $data = getJsonInput();

        $errors = validateRequired($data, ['firstname', 'lastname', 'email', 'phone', 'password']);
        if (!empty($errors)) {
            errorResponse('Validation failed', 422, $errors);
        }

        $firstname = sanitizeInput($data['firstname']);
        $lastname = sanitizeInput($data['lastname']);
        $email = strtolower(trim($data['email']));
        $phone = preg_replace('/[^0-9]/', '', $data['phone']);
        $password = $data['password'];
        $universityId = isset($data['university_id']) ? (int) $data['university_id'] : null;

        if (!validateEmail($email)) {
            errorResponse('Invalid email address', 422, ['email' => 'Invalid email address']);
        }

        if (!validatePassword($password)) {
            errorResponse('Password must be at least 8 characters', 422);
        }

        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            errorResponse('Email already registered', 409);
        }
        $stmt->close();

        $stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            errorResponse('Phone number already registered', 409);
        }
        $stmt->close();

        $username = explode('@', $email)[0] . rand(1000, 9999);
        while (countRows('users', ['username' => $username]) > 0) {
            $username .= rand(1, 9);
        }

        $fullName = $firstname . ' ' . $lastname;
        $affiliateCode = win_hashs(6);
        while (countRows('users', ['affiliate_code' => $affiliateCode]) > 0) {
            $affiliateCode .= rand(1, 9);
        }

        $verificationToken = bin2hex(random_bytes(32));
        $verificationExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $userId = dbInsert('users', [
            'username' => $username,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'university_id' => $universityId,
            'affiliate_code' => $affiliateCode,
            'role' => 'user',
            'status' => 'active',
            'is_verified' => 0,
            'email_verified' => 0,
            'email_verification_token' => $verificationToken,
            'email_verification_expiry' => $verificationExpiry
        ]);

        if (!$userId) {
            errorResponse('Registration failed. Please try again.', 500);
        }

        sendWelcomeEmail($email, $firstname, $verificationToken);

        $accessToken = JWT::generateToken($userId, 'user', 24);
        $refreshToken = JWT::generateRefreshToken($userId, 30);

        successResponse([
            'user' => [
                'id' => (int) $userId,
                'username' => $username,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone,
                'role' => 'user'
            ],
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 86400
        ], 'Account created successfully', 201);
    }

    public static function login() {
        global $db;

        $data = getJsonInput();

        $emailOrPhone = trim($data['emailOrPhone'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($emailOrPhone) || empty($password)) {
            errorResponse('Email/phone and password are required', 422);
        }

        $isEmail = validateEmail($emailOrPhone);

        if ($isEmail) {
            $stmt = $db->prepare("SELECT id, username, email, password_hash, firstname, lastname, full_name, phone, role, status, profile_image, is_verified FROM users WHERE email = ?");
        } else {
            $phone = preg_replace('/[^0-9]/', '', $emailOrPhone);
            $stmt = $db->prepare("SELECT id, username, email, password_hash, firstname, lastname, full_name, phone, role, status, profile_image, is_verified FROM users WHERE phone = ?");
        }

        $param = $isEmail ? $emailOrPhone : $phone;
        $stmt->bind_param("s", $param);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            errorResponse('Invalid credentials', 401);
        }

        if ($user['status'] === 'suspended') {
            errorResponse('Your account has been suspended', 403);
        }
        if ($user['status'] === 'banned') {
            errorResponse('Your account has been banned', 403);
        }

        $stmt = $db->prepare("UPDATE users SET last_login = NOW(), last_seen = NOW() WHERE id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $stmt->close();

        $accessToken = JWT::generateToken($user['id'], $user['role'], 24);
        $refreshToken = JWT::generateRefreshToken($user['id'], 30);

        unset($user['password_hash']);

        successResponse([
            'user' => $user,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 86400
        ], 'Login successful');
    }

    public static function logout() {
        successResponse(null, 'Logged out successfully');
    }

    public static function refresh() {
        global $db;

        $data = getJsonInput();
        $refreshToken = $data['refresh_token'] ?? '';

        if (empty($refreshToken)) {
            errorResponse('Refresh token required', 422);
        }

        $payload = JWT::decode($refreshToken);
        if (!$payload || ($payload['type'] ?? '') !== 'refresh') {
            errorResponse('Invalid or expired refresh token', 401);
        }

        $userId = (int) $payload['user_id'];
        $stmt = $db->prepare("SELECT id, role, status FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            errorResponse('User not found', 401);
        }

        if (in_array($user['status'], ['suspended', 'banned'])) {
            errorResponse('Account is ' . $user['status'], 403);
        }

        $newAccessToken = JWT::generateToken($user['id'], $user['role'], 24);
        $newRefreshToken = JWT::generateRefreshToken($user['id'], 30);

        successResponse([
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 86400
        ], 'Token refreshed');
    }

    public static function me() {
        $user = $GLOBALS['api_user'];
        unset($user['password_hash']);
        successResponse($user);
    }
}
