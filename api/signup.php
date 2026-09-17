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
$firstName = trim($_POST['firstName'] ?? '');
$lastName = trim($_POST['lastName'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirmPassword'] ?? '';
$university = $_POST['university'] ?? '';
$terms = isset($_POST['terms']);

// Validation
$errors = [];

if (empty($firstName)) {
    $errors[] = 'First name is required';
}

if (empty($lastName)) {
    $errors[] = 'Last name is required';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email is required';
}

if (empty($phone)) {
    $errors[] = 'Phone number is required';
}

if (empty($password)) {
    $errors[] = 'Password is required';
} elseif (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters';
}

if ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match';
}

if (empty($university)) {
    $errors[] = 'Please select a university';
}

if (!$terms) {
    $errors[] = 'You must agree to the terms and conditions';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
    exit;
}

// Check if email already exists
$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already registered']);
    exit;
}
$stmt->close();

// Check if phone already exists
$stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
$stmt->bind_param("s", $phone);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Phone number already registered']);
    exit;
}
$stmt->close();

// Get university ID
$university_id = null;
$university_map = [
    'university_of_lagos' => 2,
    'obafemi_awolowo_university' => 3,
    'university_of_ibadan' => 4,
    'ahmadu_bello_university' => 5,
    'university_of_nigeria' => 6,
    'covenant_university' => 7,
    'babcock_university' => 8,
    'other' => null
];

if (isset($university_map[$university])) {
    $university_id = $university_map[$university];
}

// Create username from email
$username = explode('@', $email)[0] . rand(1000, 9999);

// Hash password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Create full name
$full_name = $firstName . ' ' . $lastName;

// Insert user
$stmt = $db->prepare("INSERT INTO users (username, email, password_hash, firstname, lastname, full_name, phone, university_id, role, status, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'user', 'active', 0)");
$stmt->bind_param("ssssssi", $username, $email, $password_hash, $firstName, $lastName, $full_name, $phone, $university_id);

if ($stmt->execute()) {
    $user_id = $stmt->insert_id;
    
    // Set session
    $_SESSION['user_id'] = $user_id;
    $_SESSION['userAppId'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;
    $_SESSION['full_name'] = $full_name;
    $_SESSION['firstname'] = $firstName;
    $_SESSION['lastname'] = $lastName;
    $_SESSION['role'] = 'user';
    
    echo json_encode([
        'success' => true, 
        'message' => 'Account created successfully!',
        'redirect' => '../index.php'
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Registration failed. Please try again.'
    ]);
}

$stmt->close();
$db->close();
?>
