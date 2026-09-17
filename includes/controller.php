<?php
//session_start(); ob_start();
include_once 'constant.php';
include_once 'function.php';


class ImageProcessor {
    
    private $imageSizes = [
        'thumb'  => ['width' => 100],
        'medium' => ['width' => 800]
    ];

    private $quality = 80;

    public function __construct($quality = 80) {
        $this->quality = $quality;
        
        if (!extension_loaded('gd')) {
            throw new Exception("GD library is not installed.");
        }
    }

    public function processImage($sourcePath, $outputDir, $baseName) {
        if (!file_exists($sourcePath)) {
            throw new Exception("Source file does not exist.");
        }

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $results = [];
        $sourceImage = $this->createImageResource($sourcePath);
        
        if (!$sourceImage) {
            throw new Exception("Unsupported image format.");
        }

        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);

        foreach ($this->imageSizes as $key => $dimensions) {
            $targetWidth = $dimensions['width'];
            // Maintain aspect ratio: (original height / original width) * new width
            $targetHeight = round(($origHeight / $origWidth) * $targetWidth);

            $processedImage = $this->resizeImage($sourceImage, $origWidth, $origHeight, $targetWidth, $targetHeight);
            
            // Naming convention logic
            $fileName = ($key === 'thumb') ? "small_" . $baseName . ".webp" : $baseName . ".webp";
            $destination = rtrim($outputDir, '/') . '/' . $fileName;

            if (imagewebp($processedImage, $destination, $this->quality)) {
                $results[$key] = $destination;
            }

            imagedestroy($processedImage);
        }

        imagedestroy($sourceImage);
        return $results;
    }

    private function createImageResource($path) {
        $info = getimagesize($path);
        if (!$info) return null;
        
        switch ($info['mime']) {
            case 'image/jpeg': return imagecreatefromjpeg($path);
            case 'image/png':
                $img = imagecreatefrompng($path);
                imagepalettetotruecolor($img);
                imagealphablending($img, true);
                imagesavealpha($img, true);
                return $img;
            case 'image/webp': return imagecreatefromwebp($path);
            default: return null;
        }
    }

    private function resizeImage($source, $srcW, $srcH, $dstW, $dstH) {
        $targetImage = imagecreatetruecolor($dstW, $dstH);
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);
        $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
        imagefilledrectangle($targetImage, 0, 0, $dstW, $dstH, $transparent);

        imagecopyresampled($targetImage, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        return $targetImage;
    }
}




if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['product_images'])) {
    try {
        $files = $_FILES['product_images'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $uploadDirectory = 'uploads/'; // Flexible upload directory
        $processor = new ImageProcessor(85); // 85% quality
        $processedCount = 0;
        $errorCount = 0;

        // Handle single file or multiple files
        $fileCount = is_array($files['name']) ? count($files['name']) : 1;
        
        for ($i = 0; $i < $fileCount; $i++) {
            try {
                // Get individual file details
                $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                $fileTmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                $fileType = is_array($files['type']) ? $files['type'][$i] : $files['type'];
                $fileError = is_array($files['error']) ? $files['error'][$i] : $files['error'];

                // Skip if there's an error with this file
                if ($fileError !== UPLOAD_ERR_OK) {
                    $errorCount++;
                    continue;
                }

                // Validate file type
                if (!in_array($fileType, $allowedTypes)) {
                    $errorCount++;
                    continue;
                }

                // Generate unique ID and process
                $uniqueId = time() . '_' . bin2hex(random_bytes(4));
                $baseFileName = "processed_" . $uniqueId;
                $generatedImages = $processor->processImage($fileTmp, $uploadDirectory, $baseFileName);
                
                $_SESSION['processed_images'][] = [
                    'original' => $fileName,
                    'images' => $generatedImages
                ];
                $processedCount++;
            } catch (Exception $e) {
                $errorCount++;
            }
        }

        if ($processedCount > 0) {
            $_SESSION['message'] = "Successfully processed $processedCount image(s).";
            if ($errorCount > 0) {
                $_SESSION['message'] .= " ($errorCount file(s) skipped due to errors)";
            }
        } else {
            $_SESSION['error'] = "Error: No valid images were processed.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}


class Profile{

    function __construct() {
        if (array_key_exists('LoginUser', $_POST)) {
            $this->LoginUser();
        }
        elseif(array_key_exists('SignupUser', $_POST)){
            $this->SignupUser();
        }
        elseif(isAccountBlocked()){
            $_SESSION['error'] = 'Your account has been suspended. You cannot perform this action.';
            return;
        }
        elseif(array_key_exists('UpdateProfile', $_POST)){
            $this->UpdateProfile();
        }
        elseif(array_key_exists('ChangePassword', $_POST)){
            $this->ChangePassword();
        }
        elseif(array_key_exists('CreateProduct', $_POST)){
            $this->CreateProduct();
        }
        elseif(array_key_exists('CreateService', $_POST)){
            $this->CreateService();
        }
        elseif(array_key_exists('CreateLostFound', $_POST)){
            $this->CreateLostFound();
        }
        elseif(array_key_exists('UploadProfileImage', $_POST)){
            $this->UploadProfileImage();
        }
        elseif(array_key_exists('SendMessage', $_POST)){
            $this->SendMessage();
        }
        elseif(array_key_exists('ResendVerification', $_POST)){
            $this->ResendVerification();
        }

    }


function accountBalance($userId){
    return colSum('wallet', 'amount', ['user_id' => $userId, 'status' => 'approved']);  
}

    function SignupUser(){
        if(empty($_POST['email']) || empty($_POST['firstName']) || empty($_POST['lastName']) || 
           empty($_POST['password']) || empty($_POST['phone'])){
            $_SESSION['error'] = 'All fields are required';
           return;
        }
        
        extract($_POST);
        
        // Check if passwords match
        if($password !== $_POST['confirmPassword']){
            $_SESSION['error'] = 'Passwords do not match';
            return;
        }
        
        // Validate password strength
        if(strlen($password) < 8){
            $_SESSION['error'] = 'Password must be at least 8 characters long';
            return;
        }
        
        // Check if email exists
        if(countRows('users',['email'=>$email]) > 0){
            $_SESSION['error'] = 'This email address is already registered. Please login or use a different email.';
            return;
        }
        
        // Check if phone exists
        if(countRows('users',['phone'=>$phone]) > 0){
            $_SESSION['error'] = 'This phone number is already registered. Please use a different number.';
            return;
        }
        
    
        
        $referral_code = sanitize($referral_code ?? '0');
    if(countRows('users', ['affiliate_code' => $referral_code]) > 0){
        $referred_by = tableRowItem('users', ['affiliate_code' => $referral_code], 'id');
    } 

        // if(isset($university) && isset($university_map[$university])){
        //     $university_id = $university_map[$university];
        // }
        
        // Create username from email
        $username = explode('@', $email)[0];
        while(countRows('users', ['username' => $username]) > 0){
            $username .= rand(1,9);
        }
        
        // Create full name
        $full_name = $firstName . ' ' . $lastName;
        // Generate unique affiliate code
        $affiliate_code = win_hashs(6);
        while(countRows('users', ['affiliate_code' => $affiliate_code]) > 0){
            $affiliate_code .= rand(1,9);
        }


        $pubkey = win_hashs(22);
        
        $array = [
           'pubkey' => $pubkey,
           'affiliate_code' => $affiliate_code,
           'referred_by' => $referred_by,
           'username' => sanitize($username),
           'firstname' => sanitize($firstName),
           'lastname' => sanitize($lastName),
           'full_name' => sanitize($full_name),
           'email' => sanitize($email),
           'phone' => sanitize($phone),
           'password_hash' => password_hash($password, PASSWORD_BCRYPT),
           'university_id' => $university,
           'role' => in_array($_POST['role'] ?? '', ['user', 'rider']) ? $_POST['role'] : 'user',
           'status' => 'active',
           'is_verified' => 0
        ];

        // Generate email verification token
        $verificationToken = bin2hex(random_bytes(32));
        $verificationExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $array['email_verification_token'] = $verificationToken;
        $array['email_verification_expiry'] = $verificationExpiry;
        $array['email_verified'] = 0;
        
        $userId = dbInsert('users', $array);
        
        if($userId){
            // Send welcome email with verification link
            sendWelcomeEmail($email, $firstName, $verificationToken);
            
            // Set session
            $_SESSION['userAppId'] = $userId;
            $_SESSION['user_id'] = $userId;
            $_SESSION['success'] = "Account created successfully! Please check your email to verify your account.";
            $redirect = $_SESSION['login_redirect'] ?? './';
            if ($array['role'] === 'rider') {
                $redirect = 'rider/dashboard.php';
            }
            unset($_SESSION['login_redirect']);
            header('Location: ' . $redirect);
            exit();
        } else {
            $_SESSION['error'] = 'Registration failed. Please try again later.';
            return;
        }
    }

    function LoginUser()
    {
        if (empty($_POST['emailOrPhone']) || empty($_POST['password'])){
            $_SESSION['error'] = 'Please enter both email/phone and password';
            return;
        }
        
        global $db;
        $emailOrPhone = sanitize($_POST['emailOrPhone']);
        $password = sanitize($_POST['password']);
        
        // Check if input is email or phone
        $isEmail = filter_var($emailOrPhone, FILTER_VALIDATE_EMAIL);
        
        if($isEmail){
            $sql = $db->query("SELECT * FROM users WHERE email='$emailOrPhone'");
        } else {
            $sql = $db->query("SELECT * FROM users WHERE phone='$emailOrPhone'");
        }
        
        $num = mysqli_num_rows($sql);
        
        if ($num == 1) {
            $row = mysqli_fetch_array($sql);
            
            if ($row['status'] == 'suspended') {
                $_SESSION['error'] = 'Your account has been suspended. Please contact support for assistance.';
                return;
            }
            
            if ($row['status'] == 'banned') {
                $_SESSION['error'] = 'Your account has been banned.';
                return;
            }
            
            if (password_verify($password, $row['password_hash'])) {
                // Update last login
                $db->query("UPDATE users SET last_login = NOW(), last_seen = NOW() WHERE id='" . $row['id'] . "'");
                
                $_SESSION['userAppId'] = $row['id'];
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['success'] = 'Welcome back, ' . htmlspecialchars($row['firstname']) . '!';
                $redirect = $_SESSION['login_redirect'] ?? './';
                if ($row['role'] === 'rider' && !isset($_SESSION['login_redirect'])) {
                    $redirect = 'rider/dashboard.php';
                }
                unset($_SESSION['login_redirect']);
                header('Location: ' . $redirect);
                return;
            } else {
                $_SESSION['error'] = 'Invalid credentials. Please check and try again.';
                return;
            }
        } else {
            $_SESSION['error'] = 'No account found with this email or phone number.';
            return;
        }
    }

    // ========================================
    // PROFILE MANAGEMENT
    // ========================================


    function UpdateProfile(){
        checkLogin();
        
        if(!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])){
            $_SESSION['error'] = 'Invalid security token';
   return;
        }
        
      
        
        extract($_POST);
        $userId = $this->userId();
        
        
        // Check if email is taken by another user
        $existing = tableRowItem('users', ['email' => sanitize($email)], 'id');
        if($existing && $existing != $userId){
            $_SESSION['error'] = 'Email is already registered';
            return;
        }
        
        // Check if username is taken by another user
        if(isset($username) && !empty($username)){
            $username_taken = tableRowItem('users', ['username' => sanitize($username)], 'id');
            if($username_taken && $username_taken != $userId){
                $_SESSION['error'] = 'This username is already taken. Please choose a different one';
                return;
            }
        }
        
        $updateData = [
            'firstname' => sanitize($firstname),
            'lastname' => sanitize($lastname),
            'full_name' => sanitize($firstname) . ' ' . sanitize($lastname),
            // 'email' => sanitize($email),
            'phone' => sanitize($phone ?? ''),
            'bio' => sanitize($bio ?? ''),
            'location' => sanitize($location ?? ''),
            'username' => sanitize($username ?? ''),
            // 'date_of_birth' => sanitize($date_of_birth ?? ''),
            'department' => sanitize($department ?? ''),
            'level' => sanitize($level ?? '')
        ];
        
        $result = dbUpdate('users', $updateData, ['id' => $userId]);
        
        if($result){
            $_SESSION['success'] = 'Profile updated successfully';
        } else {
            $_SESSION['error'] = 'Failed to update profile';
        }
        
        return;
    }
    
    function ResendVerification(){
        // Can be called when logged in or with user_id parameter
        if(isset($_POST['user_id'])){
            $userId = sanitize($_POST['user_id']);
        } elseif(isset($_SESSION['userAppId'])){
            $userId = $_SESSION['userAppId'];
        } else {
            $_SESSION['error'] = 'Session expired. Please login.';
            header('Location: login.php');
            return;
        }
        
        $result = resendVerificationEmail($userId);
        
        if($result['success']){
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }
        
        // Redirect appropriately
        if(isset($_SESSION['userAppId'])){
            header('Location: my-profile.php');
        } else {
            header('Location: login.php');
        }
        exit();
    }
    
    function UploadProfileImage(){
        checkLogin();
        
        if(!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])){
            $_SESSION['error'] = 'Invalid security token';
           return;
        }
        
        if(!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] === UPLOAD_ERR_NO_FILE){
            $_SESSION['error'] = 'No file selected';
           return;
        }
        
        $file = $_FILES['profile_image'];
        
        // Check for upload errors
        if($file['error'] !== UPLOAD_ERR_OK){
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
            ];
            $_SESSION['error'] = $errors[$file['error']] ?? 'Unknown upload error';
            return;
        }
        
        $userId = $this->userId();
        
        // Validate file type
        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if(!in_array($mimeType, $allowed)){
            $_SESSION['error'] = 'Invalid file type. Only JPG, PNG, GIF, and WEBP images are allowed';
            return;
        }
        
        // Validate file size (5MB max)
        if($file['size'] > 5 * 1024 * 1024){
            $_SESSION['error'] = 'File too large. Maximum size is 5MB';
            return;
        }
        
        // Create upload directory if it doesn't exist (relative to controller.php in includes/)
        $uploadDir = __DIR__ . '/../uploads/profiles/';
        if(!is_dir($uploadDir)){
            if(!mkdir($uploadDir, 0777, true)){
                $_SESSION['error'] = 'Failed to create upload directory';
                return;
            }
        }
        
        try {
            $uniqueId = $userId . '_' . time();
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
            $fileName = 'profile_' . $uniqueId . '.' . $extension;
            $destination = rtrim($uploadDir, '/') . '/' . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                $_SESSION['error'] = 'Failed to save uploaded file';
                return;
            }

            $dbPath = ltrim(str_replace(__DIR__ . '/../', '', $destination), '/');
            
            // Delete old profile image if exists
            $oldImage = tableRowItem('users', ['id' => $userId], 'profile_image');
            if($oldImage && file_exists(__DIR__ . '/../' . $oldImage)){
                @unlink(__DIR__ . '/../' . $oldImage);
            }
            
            $result = dbUpdate('users', ['profile_image' => $dbPath], ['id' => $userId]);
            
            if($result){
                $_SESSION['success'] = 'Profile image updated successfully';
            } else {
                $_SESSION['error'] = 'Failed to update database';
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Image processing failed: ' . $e->getMessage();
        }
        
       return;
    }

    function ChangePassword(){
        checkLogin();
        
        // CSRF token validation
        if(!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])){
            $_SESSION['error'] = 'Invalid security token';
            return;
        }
        
        if(empty($_POST['current_password']) || empty($_POST['new_password']) || empty($_POST['confirm_password'])){
            $_SESSION['error'] = 'All fields are required';
            return;
        }
        
        extract($_POST);
        $userId = $this->userId();
        
        // Verify current password
        $user = dbSelect('users', ['id' => $userId])->fetch_assoc();
        
        if(!$user || !password_verify($current_password, $user['password_hash'])){
            $_SESSION['error'] = 'Current password is incorrect';
            return;
        }
        
        // Validate password match
        if($new_password != $confirm_password){
            $_SESSION['error'] = 'New passwords do not match';
            return;
        }
        
        // Validate password length
        if(strlen($new_password) < 8){
            $_SESSION['error'] = 'New password must be at least 8 characters long';
            return;
        }
        
        // Hash and update password
        $newHash = password_hash($new_password, PASSWORD_DEFAULT);
        $result = dbUpdate('users', ['password_hash' => $newHash], ['id' => $userId]);
        
        if($result){
            // Log activity if function exists
            // if(function_exists('logActivity')){
            //     logActivity($userId, 'Password Change', 'User changed password');
            // }
            $_SESSION['success'] = 'Password changed successfully';
        } else {
            $_SESSION['error'] = 'Failed to change password';
        }
    }

    function userId(){
        return $_SESSION['userAppId'] ?? null;
    }
    
function startCOnversation($otherUserId){
        checkLogin();
        
        $userId = $this->userId();
        $otherUserId = intval($otherUserId);
        
        global $db;
        
        // Check if conversation already exists
        $conv_check = $db->query("SELECT * FROM conversations WHERE 
            (user1_id = '$userId' AND user2_id = '$otherUserId') OR 
            (user1_id = '$otherUserId' AND user2_id = '$userId')")->fetch_assoc();
        
        if($conv_check){
            return $conv_check['id'];
        }
        
        // Create new conversation
        $insert = dbInsert('conversations', [
            'user1_id' => $userId,
            'user2_id' => $otherUserId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

 return $insert;

    }


    function SendMessage(){
        checkLogin();
        
        if(!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])){
            $_SESSION['error'] = 'Invalid security token';
           return;
        }
        
        if(empty($_POST['message']) || empty($_POST['conversation_id'])){
            $_SESSION['error'] = 'Message and conversation required';
           return;
        }
        
        $userId = $_SESSION['userAppId'];
        $conversation_id = intval($_POST['conversation_id']);
        $message = $_POST['message'];
        
        global $db;
        
        // Verify user is part of this conversation and get receiver
        $conv_check = $db->query("SELECT * FROM conversations WHERE id = '$conversation_id' AND (user1_id = '$userId' OR user2_id = '$userId')")->fetch_assoc();
        
        if(!$conv_check){
            $_SESSION['error'] = 'Invalid conversation';
            header('Location: messages.php');
            exit;
        }
        
        // Determine receiver_id (the other user in the conversation)
        $receiver_id = $conv_check['user1_id'] == $userId ? $conv_check['user2_id'] : $conv_check['user1_id'];
        
        // Insert message
        $insert = dbInsert('messages', [
            'conversation_id' => $conversation_id,
            'sender_id' => $userId,
            'receiver_id' => $receiver_id,
            'message' => $message,
            'is_read' => 0
        ]);
        
        if($insert){
            // Get the last inserted message ID
            $message_id = $db->insert_id;
            
            // Update conversation's last_message_id and updated_at
            dbUpdate('conversations', [
                'last_message_id' => $message_id,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $conversation_id]);
            
            $_SESSION['success'] = 'Message sent successfully';
        } else {
            $_SESSION['error'] = 'Failed to send message';
        }
        
        // Post/Redirect/Get: prevent duplicate sends on refresh
        header('Location: messages.php?id=' . $conversation_id);
        exit;
    }

    // ========================================
    // LISTING MANAGEMENT
    // ========================================

    function CreateProduct(){
       checkLogin();
        
        if(empty($_POST['title']) || empty($_POST['category_id']) || empty($_POST['price']) || 
           empty($_POST['description'])){
            $_SESSION['error'] = 'All required fields must be filled';
            return;
        }
        
        global $db;
        extract($_POST);
        $userId = $this->userId();
        
        // Get user's university_id
        $user_query = $db->query("SELECT university_id FROM users WHERE id = $userId");
        $user_data = $user_query->fetch_assoc();
        $university_id = $user_data['university_id'] ?? null;
        
        // Create slug from title
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $slug = $slug . '-' . time();
        
        // Handle negotiable checkbox
        $negotiable = isset($negotiable) ? 1 : 0;
        $availableQuantity = max(1, (int) ($_POST['available_quantity'] ?? 1));
        $deliveryFee = max(0, (float) ($_POST['delivery_fee'] ?? 1500));
        $productMetadata = setProductStockQuantity(null, $availableQuantity);
        $productMetadata = setProductDeliveryFee($productMetadata, $deliveryFee);
        
        $productData = [
            'user_id' => $userId,
            'university_id' => $university_id,
            'category_id' => sanitize($category_id),
            'title' => sanitize($title),
            'slug' => $slug,
            'description' => sanitize($description),
            'price' => floatval($price),
            'original_price' => !empty($original_price) ? floatval($original_price) : null,
            'condition_type' => sanitize($condition_type ?? 'good'),
            // 'location' => sanitize($location),
            'negotiable' => $negotiable,
            'availability' => $availableQuantity > 0 ? 'available' : 'sold',
            'metadata' => $productMetadata
        ];
        
        $productId = dbInsert('products', $productData);
        
        if($productId){
            // AI: index this product for semantic search & recommendations.
            include_once __DIR__ . '/ai/search.php';
            ai_index_product_safe((int) $productId);
            // Handle image uploads with compression
            if(isset($_FILES['images']) && !empty($_FILES['images']['name'][0])){
                $uploadDir = 'uploads/products/';
                if(!is_dir($uploadDir)){
                    mkdir($uploadDir, 0777, true);
                }
                
                try {
                    $processor = new ImageProcessor(85); // 85% quality for WebP
                    $imageCount = 0;
                    
                    foreach($_FILES['images']['name'] as $key => $filename){
                        if($_FILES['images']['error'][$key] == 0 && $imageCount < 5){
                            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mimeType = finfo_file($finfo, $_FILES['images']['tmp_name'][$key]);
                            finfo_close($finfo);
                            
                            // Validate file type
                            if(!in_array($mimeType, $allowedTypes)){
                                continue;
                            }
                            
                            // Validate file size (10MB max)
                            if($_FILES['images']['size'][$key] > 10 * 1024 * 1024){
                                continue;
                            }
                            
                            try {
                                // Generate unique base filename
                                $uniqueId = time() . '_' . bin2hex(random_bytes(4));
                                $baseFileName = "product_" . $productId . "_" . $uniqueId;
                                
                                // Process image (generates thumb and medium WebP versions)
                                $processedImages = $processor->processImage(
                                    $_FILES['images']['tmp_name'][$key],
                                    $uploadDir,
                                    $baseFileName
                                );
                                
                                // Store the medium version as primary, use thumb as fallback
                                $imageUrl = isset($processedImages['medium']) ? $processedImages['medium'] : $processedImages['thumb'];
                                
                                $imageData = [
                                    'product_id' => $productId,
                                    'image_url' => $imageUrl,
                                    'display_order' => $imageCount,
                                    'is_primary' => $imageCount == 0 ? 1 : 0
                                ];
                                dbInsert('product_images', $imageData);
                                $imageCount++;
                            } catch (Exception $e) {
                                // Skip this image if processing fails
                                continue;
                            }
                        }
                    }
                    
                    if($imageCount == 0){
                        $_SESSION['success'] = 'Product created successfully, but no images were processed.';
                    } else {
                        $_SESSION['success'] = 'Product created successfully with ' . $imageCount . ' image(s)!';
                    }
                } catch (Exception $e) {
                    $_SESSION['success'] = 'Product created successfully, but image processing failed: ' . $e->getMessage();
                }
            } else {
                $_SESSION['success'] = 'Product created successfully! It will be reviewed by admin.';
            }
        } else {
            $_SESSION['error'] = 'Failed to create product. Please try again.';
        }
        
      return;
    }

    function CreateService(){
        checkLogin();
        
        // Validate CSRF token
        if(!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])){
            $_SESSION['error'] = 'Invalid security token. Please try again.';
           // header('Location: my-listings.php');
            return;
        }
        
        if(empty($_POST['title']) || empty($_POST['service_category_id']) || empty($_POST['price']) || 
           empty($_POST['description'])){
            $_SESSION['error'] = 'All required fields must be filled';
         //   header('Location: my-listings.php');
            return;
        }
        
        global $db;
        extract($_POST);
        $userId = $this->userId();
        
        // Get user's university_id
        $user_query = $db->query("SELECT university_id FROM users WHERE id = $userId");
        $user_data = $user_query->fetch_assoc();
        $university_id = $user_data['university_id'] ?? null;
        
        // Create slug from title
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $slug = $slug . '-' . time();
        
        // Handle image uploads with compression
        $portfolio_images = [];
        if(isset($_FILES['portfolio_images']) && !empty($_FILES['portfolio_images']['name'][0])){
            $upload_dir = 'uploads/services/';
            if(!is_dir($upload_dir)){
                mkdir($upload_dir, 0777, true);
            }
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
            $max_size = 10 * 1024 * 1024; // 10MB
            $max_files = 5;
            
            $file_count = min(count($_FILES['portfolio_images']['name']), $max_files);
            
            try {
                $processor = new ImageProcessor(85); // 85% quality for WebP
                
                for($i = 0; $i < $file_count; $i++){
                    if($_FILES['portfolio_images']['error'][$i] === UPLOAD_ERR_OK){
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $file_type = finfo_file($finfo, $_FILES['portfolio_images']['tmp_name'][$i]);
                        finfo_close($finfo);
                        
                        $file_size = $_FILES['portfolio_images']['size'][$i];
                        
                        if(!in_array($file_type, $allowed_types)){
                            continue; // Skip invalid file types
                        }
                        
                        if($file_size > $max_size){
                            continue; // Skip files that are too large
                        }
                        
                        try {
                            // Generate unique base filename
                            $uniqueId = time() . '_' . bin2hex(random_bytes(4));
                            $baseFileName = "service_" . $userId . "_" . $uniqueId;
                            
                            // Process image (generates thumb and medium WebP versions)
                            $processedImages = $processor->processImage(
                                $_FILES['portfolio_images']['tmp_name'][$i],
                                $upload_dir,
                                $baseFileName
                            );
                            
                            // Store the medium version as primary
                            $imageUrl = isset($processedImages['medium']) ? $processedImages['medium'] : $processedImages['thumb'];
                            $portfolio_images[] = $imageUrl;
                        } catch (Exception $e) {
                            // Skip this image if processing fails
                            continue;
                        }
                    }
                }
            } catch (Exception $e) {
                // Continue without images if processor fails
            }
        }
        
        $serviceData = [
            'user_id' => $userId,
            'university_id' => $university_id,
            'service_category_id' => sanitize($service_category_id),
            'title' => sanitize($title),
            'slug' => $slug,
            'description' => sanitize($description),
            'short_description' => sanitize($short_description ?? ''),
            'pricing_type' => sanitize($pricing_type ?? 'fixed'),
            'price' => floatval($price),
            'delivery_time' => sanitize($delivery_time ?? ''),
            'availability' => 'available',
            'portfolio_images' => !empty($portfolio_images) ? json_encode($portfolio_images) : NULL,
            'status' => 'active'
        ];
        
        $serviceId = dbInsert('services', $serviceData);
        
        if($serviceId){
            // AI: index this service for semantic search.
            include_once __DIR__ . '/ai/search.php';
            ai_index_service((int) $serviceId);
            $_SESSION['success'] = 'Service created successfully!' . (!empty($portfolio_images) ? ' (' . count($portfolio_images) . ' image(s) uploaded)' : '');
        } else {
            $_SESSION['error'] = 'Failed to create service. Please try again.';
        }
        
  return;
    }

    function CreateLostFound(){
        checkLogin();
        
        // Validate CSRF token
        if(!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])){
            $_SESSION['error'] = 'Invalid security token. Please try again.';
           // header('Location: my-listings.php');
            return;
        }
        
        if(empty($_POST['type']) || empty($_POST['title']) || empty($_POST['category']) || 
           empty($_POST['date_lost_found']) || empty($_POST['location_lost_found']) || 
           empty($_POST['description']) || empty($_POST['contact_info'])){
            $_SESSION['error'] = 'All required fields must be filled';
           // header('Location: my-listings.php');
            return;
        }
        
        global $db;
        extract($_POST);
        $userId = $_SESSION['userAppId'];
        
        // Get user's university_id
        $user_query = $db->query("SELECT university_id FROM users WHERE id = $userId");
        $user_data = $user_query->fetch_assoc();
        $university_id = $user_data['university_id'] ?? null;
        
        $lostFoundData = [
            'user_id' => $userId,
            'university_id' => $university_id,
            'type' => sanitize($type),
            'title' => sanitize($title),
            'description' => sanitize($description),
            'category' => sanitize($category),
            'location_lost_found' => sanitize($location_lost_found),
            'date_lost_found' => sanitize($date_lost_found),
            'contact_info' => sanitize($contact_info),
            'status' => 'open'
        ];
        
        // Handle image upload with compression
        if(isset($_FILES['image']) && $_FILES['image']['error'] == 0){
            $uploadDir = 'uploads/lost-found/';
            if(!is_dir($uploadDir)){
                mkdir($uploadDir, 0777, true);
            }
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES['image']['tmp_name']);
            finfo_close($finfo);
            
            // Validate file type
            if(in_array($mimeType, $allowed_types)){
                // Validate file size (10MB max)
                if($_FILES['image']['size'] <= 10 * 1024 * 1024){
                    try {
                        $processor = new ImageProcessor(85); // 85% quality for WebP
                        
                        // Generate unique base filename
                        $uniqueId = time() . '_' . bin2hex(random_bytes(4));
                        $baseFileName = "lostfound_" . $userId . "_" . $uniqueId;
                        
                        // Process image (generates thumb and medium WebP versions)
                        $processedImages = $processor->processImage(
                            $_FILES['image']['tmp_name'],
                            $uploadDir,
                            $baseFileName
                        );
                        
                        // Store the medium version as primary
                        $imageUrl = isset($processedImages['medium']) ? $processedImages['medium'] : $processedImages['thumb'];
                        $lostFoundData['image_url'] = $imageUrl;
                    } catch (Exception $e) {
                        // Continue without image if processing fails
                    }
                }
            }
        }
        
        $itemId = dbInsert('lost_found_items', $lostFoundData);
        
        if($itemId){
            // AI: index this item so lost/found matching works.
            include_once __DIR__ . '/ai/lostfound.php';
            ai_index_lost_found((int) $itemId);
            $_SESSION['success'] = 'Item posted successfully!';
        } else {
            $_SESSION['error'] = 'Failed to post item. Please try again.';
        }
        
       // header('Location: my-listings.php');
        exit;
    }





}

$pro = new Profile();

$universityId = null;
$department = null;
$accountStatus = null;
if(isLoggedIn()){
    $userId = $pro->userId();
    $currentUser = dbSelect('users', ['id' => $userId])->fetch_assoc();
    $universityId = $currentUser['university_id'];
    $department = $currentUser['department'];
    $accountStatus = $currentUser['status'] ?? 'active';

    // Restrict riders to rider operations only: keep them out of buyer/seller pages.
    if (($currentUser['role'] ?? '') === 'rider') {
        $scriptPath = $_SERVER['PHP_SELF'];
        if (strpos($scriptPath, '/rider/') === false && strpos($scriptPath, '/api/') === false) {
            $riderBlockedPages = [
                'index.php', 'products.php', 'product.php', 'product-profile.php',
                'categories.php', 'services.php', 'service.php', 'store.php',
                'seller-profile.php', 'cart.php', 'checkout.php', 'my-orders.php',
                'invoice.php', 'dashboard.php', 'my-products.php', 'my-services.php',
                'my-listings.php', 'my-ads.php', 'my-bookmarks.php', 'my-qrstore.php',
                'my-lost-found.php', 'create-flash-sale.php', 'flash-sales.php',
                'flash-sale-products.php', 'affiliate-center.php', 'affiliate-program.php',
                'getting-started.php', 'manage-products.php', 'manage-services.php',
                'manage-categories.php', 'manage-ads.php', 'manage-lost-found.php',
                'manage-videos.php', 'manage-plans.php'
            ];
            if (in_array(basename($scriptPath), $riderBlockedPages, true)) {
                header('Location: rider/dashboard.php');
                exit;
            }
        }
    }
}



?>
