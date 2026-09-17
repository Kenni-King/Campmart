<?php
class UserController {

    public static function profile() {
        global $db;

        $userId = $GLOBALS['api_user']['id'];

        $stmt = $db->prepare("SELECT id, username, email, full_name, firstname, lastname, phone, role, status, profile_image, bio, location, department, level, university_id, is_verified, created_at FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            errorResponse('User not found', 404);
        }

        $user['id'] = (int) $user['id'];

        if ($user['university_id']) {
            $stmt = $db->prepare("SELECT name, slug FROM universities WHERE id = ?");
            $stmt->bind_param("i", $user['university_id']);
            $stmt->execute();
            $uni = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $user['university'] = $uni ?: null;
        } else {
            $user['university'] = null;
        }
        unset($user['university_id']);

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM products WHERE user_id = ? AND status = 'approved' AND availability != 'deleted'");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user['listings_count'] = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM bookmarks WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user['bookmarks_count'] = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        successResponse($user);
    }

    public static function updateProfile() {
        global $db;

        $userId = $GLOBALS['api_user']['id'];
        $data = getJsonInput();

        $updateData = [];
        $allowedFields = ['firstname', 'lastname', 'phone', 'bio', 'location', 'username', 'department', 'level'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = sanitizeInput($data[$field]);
            }
        }

        if (empty($updateData)) {
            errorResponse('No fields to update', 422);
        }

        if (isset($updateData['username']) && !empty($updateData['username'])) {
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->bind_param("si", $updateData['username'], $userId);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $stmt->close();
                errorResponse('Username already taken', 409);
            }
            $stmt->close();
        }

        if (isset($updateData['firstname']) || isset($updateData['lastname'])) {
            $stmt = $db->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $current = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $fn = $updateData['firstname'] ?? $current['firstname'];
            $ln = $updateData['lastname'] ?? $current['lastname'];
            $updateData['full_name'] = $fn . ' ' . $ln;
        }

        $result = dbUpdate('users', $updateData, ['id' => $userId]);
        if ($result !== 'Successfully Updated') {
            errorResponse('Failed to update profile', 500);
        }

        successResponse(null, 'Profile updated successfully');
    }

    public static function changePassword() {
        global $db;

        $userId = $GLOBALS['api_user']['id'];
        $data = getJsonInput();

        $currentPassword = $data['current_password'] ?? '';
        $newPassword = $data['new_password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            errorResponse('All password fields are required', 422);
        }

        if (strlen($newPassword) < 8) {
            errorResponse('New password must be at least 8 characters', 422);
        }

        if ($newPassword !== $confirmPassword) {
            errorResponse('New passwords do not match', 422);
        }

        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!password_verify($currentPassword, $user['password_hash'])) {
            errorResponse('Current password is incorrect', 401);
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $result = dbUpdate('users', ['password_hash' => $newHash], ['id' => $userId]);
        if ($result !== 'Successfully Updated') {
            errorResponse('Failed to change password', 500);
        }

        successResponse(null, 'Password changed successfully');
    }

    public static function uploadAvatar() {
        global $db;

        $userId = $GLOBALS['api_user']['id'];

        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
            errorResponse('No image file provided', 422);
        }

        $file = $_FILES['avatar'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            errorResponse('Upload error', 422);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'])) {
            errorResponse('Invalid file type. Only JPG, PNG, and WEBP allowed', 422);
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            errorResponse('File too large. Maximum 5MB', 422);
        }

        $uploadDir = __DIR__ . '/../../../uploads/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $fileName = 'profile_' . $userId . '_' . time() . '.' . $extension;
        $destination = $uploadDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            errorResponse('Failed to save file', 500);
        }

        $dbPath = 'uploads/profiles/' . $fileName;

        $oldImage = $GLOBALS['api_user']['profile_image'] ?? null;
        if ($oldImage && file_exists(__DIR__ . '/../../../' . $oldImage)) {
            @unlink(__DIR__ . '/../../../' . $oldImage);
        }

        $result = dbUpdate('users', ['profile_image' => $dbPath], ['id' => $userId]);
        if ($result !== 'Successfully Updated') {
            errorResponse('Failed to update profile image', 500);
        }

        successResponse(['profile_image' => $dbPath], 'Avatar updated successfully');
    }
}
