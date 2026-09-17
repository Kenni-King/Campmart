<?php
session_start();
require_once('../includes/constant.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get form data
$emailOrPhone = trim($_POST['emailOrPhone'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

// Validation
if (empty($emailOrPhone)) {
    echo json_encode(['success' => false, 'message' => 'Email or phone is required']);
    exit;
}

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit;
}

// Check if input is email or phone
$isEmail = filter_var($emailOrPhone, FILTER_VALIDATE_EMAIL);

if ($isEmail) {
    $stmt = $db->prepare("SELECT id, username, email, password_hash, firstname, lastname, full_name, role, status, is_verified FROM users WHERE email = ?");
} else {
    $stmt = $db->prepare("SELECT id, username, email, password_hash, firstname, lastname, full_name, role, status, is_verified FROM users WHERE phone = ?");
}

$stmt->bind_param("s", $emailOrPhone);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Verify password
if (!password_verify($password, $user['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}

// Check account status
if ($user['status'] === 'suspended') {
    echo json_encode(['success' => false, 'message' => 'Your account has been suspended. Please contact support.']);
    exit;
}

if ($user['status'] === 'banned') {
    echo json_encode(['success' => false, 'message' => 'Your account has been banned.']);
    exit;
}

// Update last login
$stmt = $db->prepare("UPDATE users SET last_login = NOW(), last_seen = NOW() WHERE id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$stmt->close();

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['userAppId'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['email'] = $user['email'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['firstname'] = $user['firstname'];
$_SESSION['lastname'] = $user['lastname'];
$_SESSION['role'] = $user['role'];
$_SESSION['is_verified'] = $user['is_verified'];

// Set remember me cookie (30 days)
if ($remember) {
    $token = bin2hex(random_bytes(32));
    setcookie('remember_token', $token, time() + (86400 * 30), '/');
    
    // Store token in database (you may want to create a remember_tokens table)
    $stmt = $db->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $stmt->close();
}

// Redirect based on role
$redirect = '../index.php';
if ($user['role'] === 'admin') {
    $redirect = '../admin/dashboard.php';
} elseif ($user['role'] === 'seller') {
    $redirect = '../dashboard.php';
}

echo json_encode([
    'success' => true,
    'message' => 'Login successful!',
    'redirect' => $redirect
]);

$db->close();
?>
