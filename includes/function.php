<?php
// Enable centralized error logging to a writable location


if(isset($_COOKIE['agent'])){
$agent = $_COOKIE['agent'];
}
else{
  $agent = win_hashs(14);
  setcookie('agent', $agent, time() + (86400 * 730), '/'); // 86400 = 1 day
}

$uri = basename($_SERVER['REQUEST_URI'], '?' . $_SERVER['QUERY_STRING']);

function win_hashs($length)
    {
        return substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz123456789', $length)), 0, $length);
    }
function win_hash($length)
    {
        return substr(str_shuffle(str_repeat('123456789', $length)), 0, $length);
    }



function countRows($table, $conditions = []) {
    global $db;

    $whereClause = "";
    if (!empty($conditions)) {
        $where = [];
        foreach ($conditions as $col => $val) {
            $where[] = "$col='" . mysqli_real_escape_string($db, $val) . "'";
        }
        $whereClause = "WHERE " . implode(" AND ", $where);
    }

    $sql = $db->query("SELECT * FROM $table $whereClause") or die(mysqli_error($db));
    return mysqli_num_rows($sql);
}

 
function countRowsOr($table, $conditions = []) {
    global $db;

    // Build WHERE clause only if conditions exist
    $whereClause = "";
    if (!empty($conditions)) {
        $where = [];
        foreach ($conditions as $col => $val) {
            $escapedVal = mysqli_real_escape_string($db, $val);
            $where[] = "$col='$escapedVal'";
        }
        $whereClause = "WHERE " . implode(" OR ", $where);
    }

    // Execute query and return row count
    $sql = $db->query("SELECT * FROM $table $whereClause") or die(mysqli_error($db));
    return mysqli_num_rows($sql);
}



function tableRowItem($table, $conditions, $item, $extra = '') {
    global $db;

    // Build the WHERE clause dynamically
    $where = [];
    foreach ($conditions as $col => $val) {
        $where[] = "$col='" . mysqli_real_escape_string($db, $val) . "'";
    }
    $whereClause = implode(" AND ", $where);

    // Execute the query
    $sql = $db->query("SELECT * FROM $table WHERE $whereClause $extra") or die(mysqli_error($db));
    $row = mysqli_fetch_assoc($sql);

    return $row[$item] ?? null;
}

function tableRowItemOr($table, $conditions, $item) {
    global $db;

    // Build the WHERE clause with OR logic
    $where = [];
    foreach ($conditions as $col => $val) {
        $where[] = "$col='" . mysqli_real_escape_string($db, $val) . "'";
    }
    $whereClause = implode(" OR ", $where);

    // Execute the query
    $sql = $db->query("SELECT * FROM $table WHERE $whereClause") or die(mysqli_error($db));
    $row = mysqli_fetch_assoc($sql);

    return $row[$item] ?? null;
}


function bcrypt($pass){
    return password_hash($pass,PASSWORD_BCRYPT);
}


function get_time_ago($time){
    $time_difference = time()-$time;
    if($time_difference < 1 ){return "less than 1 second ago";}
    $condition = array(12*30*24*60*60 => 'Year',
        30*24*60*60 => 'Month',
        24*60*60 => 'Day',
        60*60 => 'Hour',
        60 => 'Second'
        );
    foreach($condition as $secs => $str){
        $d = $time_difference / $secs;
        if($d >= 1){
            $t = round($d);
            return $t.' '.$str.($t> 1?'s':'').' ago';
        }
    }
}


function userName($id,$col=''){
    global $db;

    $sql = $db->query("SELECT * FROM user WHERE id='$id'");
    $row = $sql->fetch_assoc();
    if(mysqli_num_rows($sql)==0){return ''; }
    $val = ($col=='')?$row['firstname'].' '.$row['lastname']:$row[$col];
    return $val;
}




function colSum($table, $targetCol, $conditions = [], $extra = '') {
    global $db;

    // Build WHERE clause if conditions exist
    $whereClause = "";
    if (!empty($conditions)) {
        $where = [];
        foreach ($conditions as $col => $val) {
            $escapedVal = mysqli_real_escape_string($db, $val);
            $where[] = "$col='$escapedVal'";
        }
        $whereClause = "WHERE " . implode(" AND ", $where);
    }

    // Execute query
    $sql = $db->query("SELECT SUM($targetCol) AS value_sum FROM $table $whereClause $extra") or die(mysqli_error($db));
    $row = mysqli_fetch_assoc($sql);

    return $row['value_sum'] ?? 0;
}
  
   
function rangeSum($table, $targetCol, $rangeCol, $start, $end) {
    global $db;

    // Escape inputs to prevent SQL injection
    $startEscaped = mysqli_real_escape_string($db, $start);
    $endEscaped = mysqli_real_escape_string($db, $end);

    // Build and execute query
    $sql = $db->query("SELECT SUM($targetCol) AS value_sum FROM $table WHERE $rangeCol BETWEEN '$startEscaped' AND '$endEscaped'")
        or die(mysqli_error($db));
    
    $row = mysqli_fetch_assoc($sql);
    return $row['value_sum'] ?? 0;
}


function sanitize($str){
	global $db;
	return mysqli_real_escape_string($db, $str);
}





function dbInsert($table, $arr) {
  global $db;

  $columns = implode(", ", array_keys($arr));
  $placeholders = implode(", ", array_fill(0, count($arr), '?'));
  $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";

  $stmt = $db->prepare($sql);
  if (!$stmt) {
      die("Prepare failed: " . $db->error);
  }

  // Detect and build type string
  $types = '';
  $values = [];
  foreach ($arr as $value) {
      if (is_int($value)) {
          $types .= 'i';
      } elseif (is_float($value)) {
          $types .= 'd';
      } elseif (is_null($value)) {
          $types .= 's'; // NULL can be passed as string
      } else {
          $types .= 's';
      }
      $values[] = $value;
  }

  // Create references for bind_param
  $bind_params = [];
  $bind_params[] = $types;
  foreach ($values as $key => $value) {
      $bind_params[] = &$values[$key]; // bind_param requires references
  }

  // Bind and execute
  call_user_func_array([$stmt, 'bind_param'], $bind_params);

  if ($stmt->execute()) {
      return $stmt->insert_id;
  } else {
      die("Execute failed: " . $stmt->error);
  }
}


function dbSelect($table, $arr = [], $extra = '') {
  global $db;

  // If no conditions, fetch all
  if (empty($arr)) {
      $sql = "SELECT * FROM `$table` $extra";
      $stmt = $db->prepare($sql);
      if (!$stmt) return false;
  } else {
      $conditions = [];
      $values = [];

      foreach ($arr as $key => $value) {
          $conditions[] = "`$key` = ?";
          $values[] = $value;
      }

      $whereClause = implode(" AND ", $conditions);
      $sql = "SELECT * FROM `$table` WHERE $whereClause $extra";

      $stmt = $db->prepare($sql);
      if (!$stmt) return false;

      $types = str_repeat("s", count($values)); // assumes all values are strings
      $stmt->bind_param($types, ...$values);
  }

  if ($stmt->execute()) {
      return $stmt->get_result(); // returns mysqli_result object
  } else {
      return false;
  }
}


function dbSelectCol($table, $columns = ['*'], $arr = []) {
    global $db;

    // 1. Construct the SELECT clause from the $columns array
    if (is_array($columns) && $columns !== ['*']) {
        // Wrap each column name in backticks and join with a comma
        $selectClause = implode(", ", array_map(fn($c) => "`$c`", $columns));
    } else {
        // Default to selecting all columns
        $selectClause = "*";
    }

    // 2. Build the rest of the query
    if (empty($arr)) {
        // Query without a WHERE clause
        $sql = "SELECT $selectClause FROM `$table`";
        $stmt = $db->prepare($sql);
        if (!$stmt) return false;
    } else {
        // Query with a WHERE clause
        $conditions = [];
        $values = [];

        foreach ($arr as $key => $value) {
            $conditions[] = "`$key` = ?";
            $values[] = $value;
        }

        $whereClause = implode(" AND ", $conditions);
        $sql = "SELECT $selectClause FROM `$table` WHERE $whereClause";

        $stmt = $db->prepare($sql);
        if (!$stmt) return false;

        // Assumes all bound values are strings. Adjust if needed.
        $types = str_repeat("s", count($values));
        $stmt->bind_param($types, ...$values);
    }

    // 3. Execute the statement and return the result
    if ($stmt->execute()) {
        return $stmt->get_result(); // returns mysqli_result object
    } else {
        return false;
    }
}


function resultToArray($result) {
  $array = array();

  while ($row = $result->fetch_assoc()) {
      $array[] = $row;
  }
  return $array;
}



function dbSelectOr($table, $arr) {
  global $db;

  if (empty($arr)) {
      return false; // or handle as "select all"
  }

  $conditions = [];
  $values = [];

  foreach ($arr as $key => $value) {
      $conditions[] = "`$key` = ?";
      $values[] = $value;
  }

  $whereClause = implode(" OR ", $conditions);
  $sql = "SELECT * FROM `$table` WHERE $whereClause";

  $stmt = $db->prepare($sql);
  if (!$stmt) return false;

  $types = str_repeat("s", count($values)); // assuming all are strings
  $stmt->bind_param($types, ...$values);

  if ($stmt->execute()) {
      return $stmt->get_result(); // mysqli_result object
  } else {
      return false;
  }
}


function dbUpdate($table, $arr, $pkey, $extra = '') {
    global $db;

    if (empty($arr) || empty($pkey)) return 'Invalid Input';

    $setParts = [];
    $values = [];

    foreach ($arr as $column => $value) {
            $setParts[] = "`$column` = ?";
            $values[] = $value;
    }

    $setClause = implode(", ", $setParts);

    // Build WHERE clause for multiple primary keys
    $whereParts = [];
    foreach ($pkey as $key => $val) {
            $whereParts[] = "`$key` = ?";
            $values[] = $val;
    }
    $whereClause = implode(" AND ", $whereParts);

    $sql = "UPDATE `$table` SET $setClause WHERE $whereClause $extra";

    $stmt = $db->prepare($sql);
    if (!$stmt) return 'Query Prepare Failed';

    // Bind parameter types: all as strings 's' for simplicity (can be enhanced to detect types)
    $types = str_repeat("s", count($values));
    $stmt->bind_param($types, ...$values);

    if ($stmt->execute()) {
            return 'Successfully Updated';
    } else {
            return 'Operation Failed';
    }
}

function getCurrentDate() {
    return date('Y-m-d');
}
function getCurrentTime() {
    return date('H-i-s');
}

// // Example usage:
// $current_time = getCurrentDateTime();
// // $current_time would be something like "2025-08-14 11:06:00"


function dbDelete($table, $conditions) {
  global $db;

  if (empty($conditions)) return 'Invalid Input';

  $whereParts = [];
  $values = [];

  foreach ($conditions as $column => $value) {
      $whereParts[] = "`$column` = ?";
      $values[] = $value;
  }

  $whereClause = implode(" AND ", $whereParts);

  $sql = "DELETE FROM `$table` WHERE $whereClause";

  $stmt = $db->prepare($sql);
  if (!$stmt) return 'Query Prepare Failed';

  // Bind parameters: assuming all are strings for simplicity
  $types = str_repeat("s", count($values));
  $stmt->bind_param($types, ...$values);

  if ($stmt->execute()) {
      return 'Successfully Deleted';
  } else {
      return 'Operation Failed';
  }
}




    function Emailer($email,$subject,$message,$mailid=1)
    {global $db;
       $token = sha1($email);
        $headers = 'From: CampMart <noreply@campmart.ng>' . "\r\n";
        $headers .= 'Reply-To: support@campmart.ng' . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        $name = $email;
        $message = '<!doctype html>
<html>
  <head>
    <meta name="viewport" content="width=device-width" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  </head>
  <body>
    <div style="font-family: sans-serif; font-size: 16px; padding:10px; line-height: 1.4;">
    <div>Hi '.$name.',<br><br>'.$message
      
    .'<hr style="border: 0; border-bottom: 1px solid #f6f6f6; Margin: 20px 0;">
  </div>


              <div style="Margin-top: 10px; text-align: center; font-size:12px; color: #9a9ea6;">
                Copyright &copy; '.date('Y').' CampMart. All Rights Reserved<br> 
                Your trusted campus marketplace
              </div>
            </div>
  </body>
</html>';


        @$send = mail($email, $subject, $message, $headers);

        return;
    }


/**
 * Send welcome email with verification link
 */
function sendWelcomeEmail($userEmail, $userName, $verificationToken) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $baseUrl = $protocol . $_SERVER['HTTP_HOST'];
    $verificationLink = $baseUrl . '/verify-email.php?token=' . $verificationToken;
    
    $subject = 'Welcome to CampMart - Verify Your Email';
    
    $headers = 'From: CampMart <noreply@campmart.ng>' . "\r\n";
    $headers .= 'Reply-To: support@campmart.ng' . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $message = '<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #064E3B 0%, #065F46 100%); padding: 40px 30px; text-align: center; }
        .logo { background: #ffffff; width: 60px; height: 60px; border-radius: 16px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 30px; font-weight: bold; color: #064E3B; }
        .header-text { color: #ffffff; font-size: 28px; font-weight: bold; margin: 0; }
        .content { padding: 40px 30px; }
        .greeting { font-size: 24px; font-weight: bold; color: #1e293b; margin-bottom: 20px; }
        .text { font-size: 16px; line-height: 1.6; color: #475569; margin-bottom: 20px; }
        .btn-container { text-align: center; margin: 35px 0; }
        .btn { display: inline-block; background: #064E3B; color: #ffffff !important; text-decoration: none; padding: 16px 40px; border-radius: 12px; font-weight: bold; font-size: 16px; }
        .btn:hover { background: #065F46; }
        .info-box { background: #f8fafc; border-left: 4px solid #064E3B; padding: 20px; margin: 25px 0; border-radius: 8px; }
        .features { margin: 30px 0; }
        .feature-item { display: flex; align-items: start; margin-bottom: 20px; }
        .feature-icon { background: #ecfdf5; color: #064E3B; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 15px; font-size: 20px; flex-shrink: 0; }
        .feature-text { flex: 1; }
        .feature-title { font-weight: bold; color: #1e293b; margin-bottom: 5px; }
        .feature-desc { color: #64748b; font-size: 14px; }
        .footer { background: #f8fafc; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0; }
        .footer-text { font-size: 14px; color: #64748b; margin: 5px 0; }
        .divider { height: 1px; background: #e2e8f0; margin: 30px 0; }
        .alt-link { font-size: 13px; color: #64748b; word-break: break-all; padding: 15px; background: #f8fafc; border-radius: 8px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🛍️</div>
            <div class="header-text">Welcome to CampMart!</div>
        </div>
        
        <div class="content">
            <div class="greeting">Hi ' . htmlspecialchars($userName) . '! 👋</div>
            
            <p class="text">
                Welcome to <strong>CampMart</strong> - your trusted campus marketplace! We\'re thrilled to have you join our growing community of students buying, selling, and trading safely.
            </p>
            
            <div class="info-box">
                <p class="text" style="margin: 0;">
                    <strong>📧 Verify Your Email Address</strong><br>
                    To get started and unlock all features, please verify your email address by clicking the button below.
                </p>
            </div>
            
            <div class="btn-container">
                <a href="' . $verificationLink . '" class="btn">Verify Email Address</a>
            </div>
            
            <div class="alt-link">
                <strong>Button not working?</strong> Copy and paste this link into your browser:<br>
                ' . $verificationLink . '
            </div>
            
            <div class="divider"></div>
            
            <div class="features">
                <p class="text" style="font-weight: bold; color: #1e293b; margin-bottom: 20px;">What You Can Do on CampMart:</p>
                
                <div class="feature-item">
                    <div class="feature-icon">📦</div>
                    <div class="feature-text">
                        <div class="feature-title">Buy & Sell Products</div>
                        <div class="feature-desc">List your items or discover great deals from fellow students</div>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">💼</div>
                    <div class="feature-text">
                        <div class="feature-title">Offer Services</div>
                        <div class="feature-desc">Freelance, tutor, or provide services to your campus community</div>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">🔍</div>
                    <div class="feature-text">
                        <div class="feature-title">Lost & Found</div>
                        <div class="feature-desc">Report lost items or help others find their belongings</div>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">💬</div>
                    <div class="feature-text">
                        <div class="feature-title">Direct Messaging</div>
                        <div class="feature-desc">Chat securely with buyers and sellers</div>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">✅</div>
                    <div class="feature-text">
                        <div class="feature-title">Verified Community</div>
                        <div class="feature-desc">Trade with confidence within your verified campus network</div>
                    </div>
                </div>
            </div>
            
            <div class="divider"></div>
            
            <p class="text">
                <strong>Need Help?</strong> Visit our <a href="' . $baseUrl . '/help-center.php" style="color: #064E3B;">Help Center</a> or contact our support team at <a href="mailto:support@campmart.ng" style="color: #064E3B;">support@campmart.ng</a>
            </p>
            
            <p class="text" style="color: #64748b; font-size: 14px; margin-top: 30px;">
                This verification link will expire in 24 hours. If you didn\'t create a CampMart account, please ignore this email.
            </p>
        </div>
        
        <div class="footer">
            <div class="footer-text" style="font-weight: bold; color: #1e293b; margin-bottom: 10px;">CampMart</div>
            <div class="footer-text">Your Trusted Campus Marketplace</div>
            <div class="footer-text" style="margin-top: 15px;">Copyright © ' . date('Y') . ' CampMart. All Rights Reserved.</div>
        </div>
    </div>
</body>
</html>';
    
    return @mail($userEmail, $subject, $message, $headers);
}

/**
 * Send verification email resend
 */
function resendVerificationEmail($userId) {
    global $db;
    
    $user = dbSelect('users', ['id' => $userId])->fetch_assoc();
    
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    if ($user['email_verified']) {
        return ['success' => false, 'message' => 'Email is already verified'];
    }
    
    // Generate new verification token
    $verificationToken = bin2hex(random_bytes(32));
    $verificationExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Update user with new token
    dbUpdate('users', [
        'email_verification_token' => $verificationToken,
        'email_verification_expiry' => $verificationExpiry
    ], ['id' => $userId]);
    
    // Send email
    $emailSent = sendWelcomeEmail($user['email'], $user['firstname'], $verificationToken);
    
    if ($emailSent) {
        return ['success' => true, 'message' => 'Verification email sent successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to send email. Please try again.'];
    }
}


function sendSms($recipients, $message) {
  $token = "gpocGwjHBXDeq65bULvSf1rOnxdtAVYi437NkJWRa9hylKPC0uQEImzZTsM28F";
  $senderID = "Livepetals";
  $gateway = 2;

  $url = "https://my.kudisms.net/api/sms";

  // Prepare the full URL with query parameters
  $params = http_build_query([
      'token' => $token,
      'senderID' => $senderID,
      'recipients' => $recipients,
      'message' => $message,
      'gateway' => $gateway
  ]);

  $finalUrl = $url . '?' . $params;

  // Initialize cURL
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $finalUrl);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  // Execute request
  $response = curl_exec($ch);

  // Handle errors
  if(curl_errno($ch)) {
      return 'Curl error: ' . curl_error($ch);
  }

  curl_close($ch);
  return $response;
}


function slugify($text) {
  // Replace non-letter or digits by -
  $text = preg_replace('~[^\pL\d]+~u', '-', $text);
  // Transliterate
  $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
  // Remove unwanted characters
  $text = preg_replace('~[^-\w]+~', '', $text);
  // Trim
  $text = trim($text, '-');
  // Remove duplicate -
  $text = preg_replace('~-+~', '-', $text);
  // Lowercase
  $text = strtolower($text);
  if (empty($text)) {
      return 'n-a';
  }
  return $text;
}


function getSubject($id) {
    return tableRowItem('subject', ['id' => $id], 'subject');
}

function getClass($id) {
    return tableRowItem('classes', ['id' => $id], 'class');
}

// ============================================
// LANDREMIT SPECIFIC FUNCTIONS
// ============================================

/**
 * Generate unique affiliate code
 */
function generateAffiliateCode($length = 8) {
    return strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length));
}

/**
 * Generate unique transaction reference
 */
function generateTransactionRef($prefix = 'TXN') {
    return $prefix . '_' . time() . '_' . win_hash(6);
}

/**
 * Generate a mostly time-based order number with a short suffix for same-millisecond safety.
 */
function generateOrderNumber($prefix = 'ORD') {
    return $prefix . '-' . date('ymdHisv') . '-' . win_hash(2);
}

/**
 * Decode product metadata safely.
 */
function decodeProductMetadata($metadata): array {
    if (is_array($metadata)) {
        return $metadata;
    }

    if (empty($metadata) || !is_string($metadata)) {
        return [];
    }

    $decoded = json_decode($metadata, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Return the current available stock quantity for a product.
 */
function getProductStockQuantity($productOrMetadata, ?string $availability = null): int {
    if (is_array($productOrMetadata)) {
        $metadata = decodeProductMetadata($productOrMetadata['metadata'] ?? []);
        $availability = $availability ?? ($productOrMetadata['availability'] ?? null);
    } else {
        $metadata = decodeProductMetadata($productOrMetadata);
    }

    if (array_key_exists('stock_quantity', $metadata)) {
        return max(0, (int) $metadata['stock_quantity']);
    }

    if ($availability === 'available' || $availability === 'reserved') {
        return 1;
    }

    return 0;
}

/**
 * Persist stock information inside product metadata.
 */
function setProductStockQuantity($metadata, int $quantity): string {
    $data = decodeProductMetadata($metadata);
    $data['stock_quantity'] = max(0, $quantity);
    $data['stock_updated_at'] = date('Y-m-d H:i:s');
    return json_encode($data, JSON_UNESCAPED_SLASHES);
}

/**
 * Return the configured delivery fee for a product.
 */
function getProductDeliveryFee($productOrMetadata, string $deliveryOption = 'riders'): float {
    if ($deliveryOption !== 'riders') {
        return 0.0;
    }

    if (is_array($productOrMetadata)) {
        $metadata = decodeProductMetadata($productOrMetadata['metadata'] ?? []);
    } else {
        $metadata = decodeProductMetadata($productOrMetadata);
    }

    if (array_key_exists('delivery_fee', $metadata)) {
        return max(0, (float) $metadata['delivery_fee']);
    }

    return 1500.0;
}

/**
 * Persist delivery fee inside product metadata.
 */
function setProductDeliveryFee($metadata, float $deliveryFee): string {
    $data = decodeProductMetadata($metadata);
    $data['delivery_fee'] = max(0, $deliveryFee);
    $data['delivery_fee_updated_at'] = date('Y-m-d H:i:s');
    return json_encode($data, JSON_UNESCAPED_SLASHES);
}

/**
 * Extract purchased quantity from an order note fallback.
 */
function getOrderQuantityFromNotes(?string $notes): int {
    if (is_string($notes) && preg_match('/(?:^|\R)Quantity:\s*(\d+)/i', $notes, $matches)) {
        return max(1, (int) $matches[1]);
    }

    return 1;
}

/**
 * Format currency
 */
function formatCurrency($amount, $round = 0) {
    $symbol = getSetting('currency_symbol', '₦');
    return $symbol . number_format($amount, $round);
}

/**
 * Get system setting value
 */
function getSetting($key, $default = '') {
    global $db;
    $key = sanitize($key);
    $sql = $db->query("SELECT setting_value FROM system_settings WHERE setting_key='$key' LIMIT 1");
    if ($sql && mysqli_num_rows($sql) > 0) {
        $row = mysqli_fetch_assoc($sql);
        return $row['setting_value'];
    }
    return $default;
}

/**
 * Update system setting
 */
function updateSetting($key, $value) {
    global $db;
    $key = sanitize($key);
    $value = sanitize($value);
    
    $check = $db->query("SELECT id FROM system_settings WHERE setting_key='$key'");
    if (mysqli_num_rows($check) > 0) {
        return dbUpdate('system_settings', ['setting_value' => $value], ['setting_key' => $key]);
    } else {
        return dbInsert('system_settings', ['setting_key' => $key, 'setting_value' => $value]);
    }
}

/**
 * Calculate payment amount based on plan and frequency
 */
function calculatePaymentAmount($totalAmount, $paymentPlan, $paymentFrequency = 'monthly') {
    $months = 0;
    
    switch($paymentPlan) {
        case 'outright':
            return $totalAmount;
        case '3months':
            $months = 3;
            break;
        case '6months':
            $months = 6;
            break;
        case '12months':
            $months = 12;
            break;
        case '18months':
            $months = 18;
            break;
        default:
            return 0;
    }
    
    $monthlyAmount = $totalAmount / $months;
    
    switch($paymentFrequency) {
        case 'daily':
            return $monthlyAmount / 30;
        case 'weekly':
            return $monthlyAmount / 4;
        case 'monthly':
        default:
            return $monthlyAmount;
    }
}

/**
 * Get property details
 */
function getPropertyDetails($propertyId) {
    $result = dbSelect('properties', ['id' => $propertyId]);
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

/**
 * Get active discount for property
 */
function getActiveDiscount($propertyId) {
    global $db;
    $propertyId = sanitize($propertyId);
    $now = date('Y-m-d H:i:s');
    
    $sql = $db->query("SELECT * FROM discounts WHERE property_id='$propertyId' 
                       AND is_active=1 AND start_date <= '$now' AND end_date >= '$now' 
                       ORDER BY discount_percentage DESC LIMIT 1");
    
    if ($sql && mysqli_num_rows($sql) > 0) {
        return mysqli_fetch_assoc($sql);
    }
    return null;
}

/**
 * Calculate discounted price
 */
function getDiscountedPrice($propertyId, $originalPrice) {
    $discount = getActiveDiscount($propertyId);
    if ($discount) {
        $discountAmount = ($originalPrice * $discount['discount_percentage']) / 100;
        return $originalPrice - $discountAmount;
    }
    return $originalPrice;
}

/**
 * Get property commission rates
 */
function getPropertyCommission($propertyId) {
    $result = dbSelect('property_commission', ['property_id' => $propertyId]);
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return ['regular_commission_percentage' => 0, 'premium_commission_percentage' => 0];
}

/**
 * Calculate affiliate commission
 */
function calculateCommission($amount, $userId, $propertyId) {
    $commission = getPropertyCommission($propertyId);
    $user = dbSelect('user', ['id' => $userId])->fetch_assoc();
    
    $rate = ($user['is_premium_affiliate'] == 1) 
            ? $commission['premium_commission_percentage'] 
            : $commission['regular_commission_percentage'];
    
    return ($amount * $rate) / 100;
}

/**
 * Get user wallet balance
 */
function getWalletBalance($userId) {
    $result = dbSelect('wallet', ['user_id' => $userId]);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['balance'];
    }
    return 0;
}

/**
 * Update wallet balance
 */
function updateWallet($userId, $amount, $type = 'credit') {
    global $db;
    $userId = sanitize($userId);
    
    // Check if wallet exists
    $check = dbSelect('wallet', ['user_id' => $userId]);
    if (!$check || mysqli_num_rows($check) == 0) {
        dbInsert('wallet', ['user_id' => $userId, 'balance' => 0, 'total_earned' => 0, 'total_withdrawn' => 0]);
    }
    
    $wallet = dbSelect('wallet', ['user_id' => $userId])->fetch_assoc();
    
    if ($type == 'credit') {
        $newBalance = $wallet['balance'] + $amount;
        $totalEarned = $wallet['total_earned'] + $amount;
        return dbUpdate('wallet', 
            ['balance' => $newBalance, 'total_earned' => $totalEarned], 
            ['user_id' => $userId]
        );
    } else {
        $newBalance = $wallet['balance'] - $amount;
        $totalWithdrawn = $wallet['total_withdrawn'] + $amount;
        return dbUpdate('wallet', 
            ['balance' => $newBalance, 'total_withdrawn' => $totalWithdrawn], 
            ['user_id' => $userId]
        );
    }
}

/**
 * Get documentation wallet balance
 */
function getDocumentationWalletBalance($userId) {
    $result = dbSelect('documentation_wallet', ['user_id' => $userId]);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['balance'];
    }
    return 0;
}

/**
 * Update documentation wallet
 */
function updateDocumentationWallet($userId, $amount, $type = 'credit') {
    global $db;
    $userId = sanitize($userId);
    
    $check = dbSelect('documentation_wallet', ['user_id' => $userId]);
    if (!$check || mysqli_num_rows($check) == 0) {
        dbInsert('documentation_wallet', ['user_id' => $userId, 'balance' => 0]);
    }
    
    $wallet = dbSelect('documentation_wallet', ['user_id' => $userId])->fetch_assoc();
    
    if ($type == 'credit') {
        $newBalance = $wallet['balance'] + $amount;
        $totalDeposited = $wallet['total_deposited'] + $amount;
        return dbUpdate('documentation_wallet', 
            ['balance' => $newBalance, 'total_deposited' => $totalDeposited], 
            ['user_id' => $userId]
        );
    } else {
        $newBalance = $wallet['balance'] - $amount;
        $totalUsed = $wallet['total_used'] + $amount;
        return dbUpdate('documentation_wallet', 
            ['balance' => $newBalance, 'total_used' => $totalUsed], 
            ['user_id' => $userId]
        );
    }
}

/**
 * Get reserve to own balance
 */
function getReserveToOwnBalance($userId) {
    $result = dbSelect('reserve_to_own', ['user_id' => $userId]);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['available_balance'];
    }
    return 0;
}

/**
 * Update reserve to own balance
 */
function updateReserveToOwn($userId, $amount, $type = 'credit') {
    global $db;
    $userId = sanitize($userId);
    
    $check = dbSelect('reserve_to_own', ['user_id' => $userId]);
    if (!$check || mysqli_num_rows($check) == 0) {
        dbInsert('reserve_to_own', ['user_id' => $userId, 'total_reserved' => 0, 'available_balance' => 0]);
    }
    
    $reserve = dbSelect('reserve_to_own', ['user_id' => $userId])->fetch_assoc();
    
    if ($type == 'credit') {
        $newBalance = $reserve['available_balance'] + $amount;
        $totalReserved = $reserve['total_reserved'] + $amount;
        return dbUpdate('reserve_to_own', 
            ['available_balance' => $newBalance, 'total_reserved' => $totalReserved], 
            ['user_id' => $userId]
        );
    } else {
        $newBalance = $reserve['available_balance'] - $amount;
        $usedBalance = $reserve['used_balance'] + $amount;
        return dbUpdate('reserve_to_own', 
            ['available_balance' => $newBalance, 'used_balance' => $usedBalance], 
            ['user_id' => $userId]
        );
    }
}

/**
 * Auto-assign manager to customer
 */
function autoAssignManager($userId) {
    global $db;
    
    // Check if auto-assignment is enabled
    if (getSetting('auto_assign_managers', '1') != '1') {
        return false;
    }
    
    // Get all managers
    $managers = $db->query("SELECT id FROM user WHERE role='manager' AND status='active'");
    if (mysqli_num_rows($managers) == 0) {
        return false;
    }
    
    // Count customers per manager
    $managerLoads = [];
    while ($manager = mysqli_fetch_assoc($managers)) {
        $load = countRows('user', ['manager_id' => $manager['id']]);
        $managerLoads[$manager['id']] = $load;
    }
    
    // Assign to manager with least customers
    asort($managerLoads);
    $assignedManagerId = key($managerLoads);
    
    dbUpdate('user', ['manager_id' => $assignedManagerId], ['id' => $userId]);
    
    // Create notification
    createNotification($userId, 'Account Manager Assigned', 
        'An account manager has been assigned to you. You can now contact them for assistance.', 
        'info');
    
    return $assignedManagerId;
}

/**
 * Create notification
 */
function createNotification($userId, $title, $message, $type = 'info', $link = null) {
    return dbInsert('notifications', [
        'user_id' => $userId,
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'link' => $link
    ]);
}

/**
 * Get unread notifications count
 */
function getUnreadNotificationsCount($userId) {
    return countRows('notifications', ['user_id' => $userId, 'is_read' => 0]);
}

/**
 * Check if user can claim signup bonus
 */
function canClaimSignupBonus($userId) {
    global $db;
    
    // Get active bonus
    $now = date('Y-m-d H:i:s');
    $bonus = $db->query("SELECT * FROM signup_bonus WHERE is_active=1 
                        AND start_date <= '$now' AND end_date >= '$now' 
                        ORDER BY id DESC LIMIT 1");
    
    if (mysqli_num_rows($bonus) == 0) {
        return ['can_claim' => false, 'message' => 'No active bonus'];
    }
    
    $bonusData = mysqli_fetch_assoc($bonus);
    
    // Check if already claimed
    $claimed = countRows('user_bonus_claims', ['user_id' => $userId, 'bonus_id' => $bonusData['id']]);
    if ($claimed > 0) {
        return ['can_claim' => false, 'message' => 'Already claimed'];
    }
    
    // Check payment percentage
    $userProperties = $db->query("SELECT SUM(total_amount) as total, SUM(amount_paid) as paid 
                                  FROM user_properties WHERE user_id='$userId'");
    $payment = mysqli_fetch_assoc($userProperties);
    
    if ($payment['total'] == 0) {
        return ['can_claim' => false, 'message' => 'No properties purchased'];
    }
    
    $percentagePaid = ($payment['paid'] / $payment['total']) * 100;
    
    if ($percentagePaid >= $bonusData['claim_percentage_threshold']) {
        return ['can_claim' => true, 'bonus' => $bonusData];
    }
    
    return ['can_claim' => false, 'message' => 'Payment threshold not met'];
}

/**
 * Claim signup bonus
 */
function claimSignupBonus($userId) {
    $check = canClaimSignupBonus($userId);
    if (!$check['can_claim']) {
        return ['success' => false, 'message' => $check['message']];
    }
    
    $bonus = $check['bonus'];
    
    // Credit wallet
    updateWallet($userId, $bonus['bonus_amount'], 'credit');
    
    // Record claim
    dbInsert('user_bonus_claims', [
        'user_id' => $userId,
        'bonus_id' => $bonus['id'],
        'amount_claimed' => $bonus['bonus_amount']
    ]);
    
    // Create transaction record
    $transactionRef = generateTransactionRef('BONUS');
    dbInsert('transactions', [
        'user_id' => $userId,
        'transaction_ref' => $transactionRef,
        'transaction_type' => 'bonus_claim',
        'amount' => $bonus['bonus_amount'],
        'status' => 'completed',
        'description' => 'Signup bonus claimed'
    ]);
    
    createNotification($userId, 'Bonus Claimed!', 
        'Your signup bonus of ' . formatCurrency($bonus['bonus_amount']) . ' has been credited to your wallet.', 
        'success');
    
    return ['success' => true, 'amount' => $bonus['bonus_amount']];
}

/**
 * Get available payment plans for property
 */
function getPropertyPaymentPlans($propertyId) {
    global $db;
    $propertyId = sanitize($propertyId);
    $plans = [];
    
    $result = $db->query("SELECT plan_type FROM payment_plans 
                         WHERE property_id='$propertyId' AND is_active=1");
    
    while ($row = mysqli_fetch_assoc($result)) {
        $plans[] = $row['plan_type'];
    }
    
    return $plans;
}

/**
 * Check if payment plan is available for property
 */
function isPaymentPlanAvailable($propertyId, $planType) {
    $count = countRows('payment_plans', [
        'property_id' => $propertyId, 
        'plan_type' => $planType, 
        'is_active' => 1
    ]);
    return $count > 0;
}

/**
 * Log activity
 */
function logActivity($userId, $action, $description = null) {
    global $db;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    return dbInsert('activity_log', [
        'user_id' => $userId,
        'action' => $action,
        'description' => $description,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent
    ]);
}

/**
 * Get user's referred customers
 */
function getReferredCustomers($affiliateCode) {
    global $db;
    $affiliateCode = sanitize($affiliateCode);
    return $db->query("SELECT * FROM user WHERE referred_by='$affiliateCode' ORDER BY created_at DESC");
}

/**
 * Get total commission earned
 */
function getTotalCommissionEarned($userId) {
    return colSum('affiliate_commissions', 'commission_amount', ['affiliate_user_id' => $userId, 'status' => 'paid']);
}

/**
 * Get pending commission
 */
function getPendingCommission($userId) {
    return colSum('affiliate_commissions', 'commission_amount', ['affiliate_user_id' => $userId, 'status' => 'pending']);
}

/**
 * Upload file helper
 */
function uploadFile($file, $targetDir, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif']) {
    if (!isset($file['name']) || $file['error'] != 0) {
        return ['success' => false, 'message' => 'No file uploaded or upload error'];
    }
    
    $fileName = basename($file['name']);
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    $newFileName = time() . '_' . win_hash(6) . '.' . $fileExtension;
    $targetPath = $targetDir . '/' . $newFileName;
    
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $newFileName, 'path' => $targetPath];
    }
    
    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}

/**
 * Get user full name
 */
function getUserFullName($userId) {
    $user = dbSelect('user', ['id' => $userId]);
    if ($user && mysqli_num_rows($user) > 0) {
        $row = mysqli_fetch_assoc($user);
        return $row['firstname'] . ' ' . $row['lastname'];
    }
    return 'Unknown User';
}

/**
 * Format payment plan display
 */
function formatPaymentPlan($plan) {
    $plans = [
        'outright' => 'Outright Purchase',
        '3months' => '3 Months',
        '6months' => '6 Months',
        '12months' => '12 Months',
        '18months' => '18 Months',
        'reserve_to_own' => 'Reserve to Own'
    ];
    return $plans[$plan] ?? $plan;
}

/**
 * Get next payment date
 */
function calculateNextPaymentDate($currentDate, $frequency) {
    $date = new DateTime($currentDate);
    
    switch($frequency) {
        case 'daily':
            $date->modify('+1 day');
            break;
        case 'weekly':
            $date->modify('+7 days');
            break;
        case 'monthly':
            $date->modify('+1 month');
            break;
    }
    
    return $date->format('Y-m-d H:i:s');
}

/**
 * Check admin permission
 */
function hasAdminPermission($userId, $permission) {
    global $db;
    $userId = sanitize($userId);
    
    $user = dbSelect('user', ['id' => $userId])->fetch_assoc();
    if ($user['role'] == 'admin') {
        return true; // Admin has all permissions
    }
    
    if ($user['role'] == 'manager') {
        $roles = dbSelect('admin_roles', ['user_id' => $userId]);
        if ($roles && mysqli_num_rows($roles) > 0) {
            $role = mysqli_fetch_assoc($roles);
            return isset($role[$permission]) && $role[$permission] == 1;
        }
    }
    
    return false;
}

/**
 * Get YouTube video ID from URL
 */
function getYouTubeVideoId($url) {
    $pattern = '/(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
    preg_match($pattern, $url, $matches);
    return $matches[1] ?? null;
}

/**
 * Truncate text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (isset($_SESSION['userAppId'])) {
        $result = dbSelect('user', ['id' => $_SESSION['userAppId']]);
        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }
    }
    return null;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['userAppId'])?true:false;
}

// Session check function
function checkLogin(){
    if(!isset($_SESSION['userAppId'])){
        header('Location: login.php');
        exit;
    }
}

/**
 * Check if the current user's account is active (not suspended/banned).
 * Returns the user's status string, or false if not logged in.
 */
function getAccountStatus() {
    if (!isset($_SESSION['userAppId'])) {
        return false;
    }
    global $db;
    $userId = (int) $_SESSION['userAppId'];
    $result = $db->query("SELECT status FROM users WHERE id = $userId LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        return $row['status'];
    }
    return false;
}

/**
 * Check if current user is suspended or banned. Returns true if blocked.
 */
function isAccountBlocked() {
    $status = getAccountStatus();
    return in_array($status, ['suspended', 'banned'], true);
}
/**
 * Redirect helper
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Generate affiliate link
 */
function generateAffiliateLink($affiliateCode, $baseUrl = null) {
    if ($baseUrl === null) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $baseUrl = $protocol . $_SERVER['HTTP_HOST'];
    }
    return $baseUrl . '/register.php?ref=' . $affiliateCode;
}

/**
 * Send email notification
 */
function sendEmailNotification($to, $subject, $message) {
    $siteName = getSetting('site_name', 'LandRemit');
    $siteEmail = getSetting('site_email', 'info@landremit.com');
    
    $headers = "From: $siteName <$siteEmail>\r\n";
    $headers .= "Reply-To: $siteEmail\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $htmlMessage = '<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #71001E; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . $siteName . '</h1>
        </div>
        <div class="content">
            ' . $message . '
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' ' . $siteName . '. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
    
    return mail($to, $subject, $htmlMessage, $headers);
}

/**
 * CSRF Token Functions
 */
function generateCSRFToken(){
    if(!isset($_SESSION['csrf_token'])){
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token){
    if(!isset($_SESSION['csrf_token']) || empty($token)){
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function regenerateCSRFToken(){
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/**
 * Format phone number to Nigeria country code (234)
 * 
 * @param string $phone The phone number to format
 * @return string Formatted phone number with 234 country code
 * 
 * Examples:
 * - 08012345678 -> 2348012345678
 * - +2348012345678 -> 2348012345678
 * - 2348012345678 -> 2348012345678
 * - 07012345678 -> 2347012345678
 */
function formatNigeriaPhone($phone) {
    if (empty($phone)) {
        return '';
    }
    
    // Remove all spaces, hyphens, and parentheses
    $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
    
    // Remove plus sign if present
    if (strpos($phone, '+') === 0) {
        $phone = substr($phone, 1);
    }
    
    // If starts with 0, replace with 234
    if (strpos($phone, '0') === 0) {
        $phone = '234' . substr($phone, 1);
    }
    
    // If already starts with 234, leave as is
    // Otherwise, prepend 234 if it's a valid Nigerian number length (10 digits)
    if (strpos($phone, '234') !== 0 && strlen($phone) == 10) {
        $phone = '234' . $phone;
    }
    
    return $phone;
}

/**
 * Sync the is_featured column on entity tables based on active subscriptions
 */
function syncFeaturedStatus($db, $entityType, $entityId) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as cnt FROM featured_subscriptions
        WHERE entity_type = ? AND entity_id = ? AND status = 'active' AND end_date > NOW()
    ");
    $stmt->bind_param('si', $entityType, $entityId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $isFeatured = (int) ($result['cnt'] > 0);

    if ($entityType === 'product') {
        $stmt2 = $db->prepare("UPDATE products SET is_featured = ? WHERE id = ?");
        $stmt2->bind_param('ii', $isFeatured, $entityId);
        $stmt2->execute();
    } elseif ($entityType === 'service') {
        $stmt2 = $db->prepare("UPDATE services SET is_featured = ? WHERE id = ?");
        $stmt2->bind_param('ii', $isFeatured, $entityId);
        $stmt2->execute();
    } elseif ($entityType === 'profile') {
        if ($isFeatured) {
            $stmt2 = $db->prepare("UPDATE users SET is_featured = 1, featured_at = COALESCE(featured_at, NOW()), featured_until = (SELECT MAX(end_date) FROM featured_subscriptions WHERE entity_type = 'profile' AND entity_id = ? AND status = 'active' AND end_date > NOW()) WHERE id = ?");
        } else {
            $stmt2 = $db->prepare("UPDATE users SET is_featured = 0, featured_at = NULL, featured_until = NULL WHERE id = ?");
        }
        $stmt2->bind_param('i', $entityId);
        $stmt2->execute();
    }
}

/**
 * Expire all overdue featured subscriptions
 */
function expireOldSubscriptions($db) {
    $db->query("UPDATE featured_subscriptions SET status = 'expired' WHERE status = 'active' AND end_date <= NOW()");
}

/**
 * Generate clean URL for product
 * 
 * @param string $slug Product slug
 * @return string Clean URL
 */
function productUrl($slug) {
    return SITE_URL . 'product/' . $slug;
}

/**
 * Generate clean URL for service
 * 
 * @param string $slug Service slug
 * @return string Clean URL
 */
function serviceUrl($slug) {
    return SITE_URL . 'service/' . $slug;
}

/**
 * Generate clean URL for category
 * 
 * @param string $slug Category slug
 * @return string Clean URL
 */
function categoryUrl($slug) {
    return SITE_URL . 'category/' . $slug;
}

/**
 * Generate clean URL for store page
 * 
 * @param string $username Seller username
 * @return string Clean URL
 */
function storeUrl($username) {
    return SITE_URL . 'store/' . $username;
}

/**
 * Generate clean URL for seller profile
 * 
 * @param string $username Seller username
 * @return string Clean URL
 */
function sellerUrl($username) {
    return storeUrl($username);
}

/**
 * Check if a payment option is enabled by admin
 */
function isPaymentOptionEnabled($option) {
    $val = getSetting('payment_option_' . $option, '0');
    return $val === '1';
}

/**
 * Get available payment methods for checkout
 */
function getAvailablePaymentMethods() {
    $methods = [];
    if (isPaymentOptionEnabled('pod')) {
        $methods['pod'] = [
            'value' => 'cash',
            'label' => 'Pay on Delivery (Physical Pickup)',
            'desc' => 'Pay with cash when you pick up the item at the seller\'s physical store.',
            'icon' => 'local_shipping',
            'color' => 'primary'
        ];
    }
    if (isPaymentOptionEnabled('paystack')) {
        $methods['paystack'] = [
            'value' => 'paystack',
            'label' => 'Pay with Paystack',
            'desc' => 'Pay online via card, USSD, or bank transfer. Funds held securely until order is completed.',
            'icon' => 'account_balance',
            'color' => 'secondary'
        ];
    }
    if (isPaymentOptionEnabled('flutterwave')) {
        $methods['flutterwave'] = [
            'value' => 'flutterwave',
            'label' => 'Pay with Flutterwave',
            'desc' => 'Pay online via card, USSD, or mobile money. Funds held securely until order is completed.',
            'icon' => 'payments',
            'color' => 'secondary'
        ];
    }
    return $methods;
}

/**
 * Generate payment gateway checkout URL
 */
function getPaymentGatewayUrl($gateway) {
    if ($gateway === 'paystack') {
        return 'https://checkout.paystack.com';
    }
    if ($gateway === 'flutterwave') {
        return 'https://api.flutterwave.com/v3';
    }
    return '';
}

/**
 * Initiate Paystack payment
 */
function initiatePaystackPayment($order, $callbackUrl) {
    $secretKey = getSetting('paystack_secret_key', '');
    if (empty($secretKey)) {
        return ['status' => false, 'message' => 'Paystack not configured'];
    }

    $amount = (float) $order['total_amount'] * 100;
    $email = $order['buyer_email'] ?? '';

    $postData = [
        'email' => $email,
        'amount' => (int) $amount,
        'reference' => $order['order_number'] . '-' . time(),
        'callback_url' => $callbackUrl,
        'metadata' => [
            'order_id' => $order['id'],
            'order_number' => $order['order_number']
        ]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.paystack.co/transaction/initialize',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/json'
        ]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);
    if ($httpCode === 200 && ($result['status'] ?? false)) {
        return [
            'status' => true,
            'authorization_url' => $result['data']['authorization_url'],
            'reference' => $postData['reference']
        ];
    }

    return ['status' => false, 'message' => $result['message'] ?? 'Paystack initialization failed'];
}

/**
 * Verify Paystack payment
 */
function verifyPaystackPayment($reference) {
    $secretKey = getSetting('paystack_secret_key', '');
    if (empty($secretKey)) {
        return ['status' => false, 'message' => 'Paystack not configured'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.paystack.co/transaction/verify/' . urlencode($reference),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/json'
        ]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);
    if ($httpCode === 200 && ($result['status'] ?? false)) {
        $data = $result['data'];
        return [
            'status' => ($data['status'] === 'success'),
            'reference' => $data['reference'],
            'amount' => $data['amount'] / 100,
            'gateway_response' => $data['gateway_response'] ?? '',
            'full_response' => json_encode($result)
        ];
    }

    return ['status' => false, 'message' => $result['message'] ?? 'Verification failed'];
}

/**
 * Initiate Flutterwave payment
 */
function initiateFlutterwavePayment($order, $callbackUrl) {
    $secretKey = getSetting('flutterwave_secret_key', '');
    if (empty($secretKey)) {
        return ['status' => false, 'message' => 'Flutterwave not configured'];
    }

    $amount = (float) $order['total_amount'];
    $email = $order['buyer_email'] ?? '';

    $postData = [
        'tx_ref' => $order['order_number'] . '-' . time(),
        'amount' => $amount,
        'currency' => 'NGN',
        'redirect_url' => $callbackUrl,
        'customer' => [
            'email' => $email,
            'name' => $order['buyer_name'] ?? 'Customer'
        ],
        'meta' => [
            'order_id' => $order['id'],
            'order_number' => $order['order_number']
        ],
        'customizations' => [
            'title' => 'CampMart Order Payment',
            'description' => 'Payment for order ' . $order['order_number']
        ]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.flutterwave.com/v3/payments',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/json'
        ]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);
    if ($httpCode === 200 && ($result['status'] === 'success')) {
        return [
            'status' => true,
            'authorization_url' => $result['data']['link'],
            'reference' => $postData['tx_ref']
        ];
    }

    return ['status' => false, 'message' => $result['message'] ?? 'Flutterwave initialization failed'];
}

/**
 * Verify Flutterwave payment
 */
function verifyFlutterwavePayment($transactionId) {
    $secretKey = getSetting('flutterwave_secret_key', '');
    if (empty($secretKey)) {
        return ['status' => false, 'message' => 'Flutterwave not configured'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.flutterwave.com/v3/transactions/' . urlencode($transactionId) . '/verify',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: ' . 'application/json'
        ]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);
    if ($httpCode === 200 && ($result['status'] === 'success')) {
        $data = $result['data'];
        return [
            'status' => ($data['status'] === 'successful'),
            'reference' => $data['tx_ref'],
            'amount' => $data['amount'],
            'gateway_response' => $data['processor_response'] ?? '',
            'full_response' => json_encode($result)
        ];
    }

    return ['status' => false, 'message' => $result['message'] ?? 'Verification failed'];
}

/**
 * Check if an order is eligible for escrow release (both parties completed)
 */
function isOrderReadyForDisbursement($order) {
    return (
        ($order['payment_status'] ?? '') === 'paid' &&
        ($order['escrow_status'] ?? '') === 'held' &&
        !empty($order['buyer_completed']) &&
        !empty($order['seller_completed'])
    );
}

/**
 * Mark escrow as released (admin action)
 */
function releaseEscrowPayment($orderId) {
    global $db;
    $stmt = $db->prepare("
        UPDATE orders 
        SET escrow_status = 'released', 
            disbursed_at = NOW(),
            status = 'completed',
            completed_at = NOW()
        WHERE id = ? AND escrow_status = 'held'
    ");
    $stmt->bind_param('i', $orderId);
    return $stmt->execute();
}

/**
 * Format payment method display name
 */
function formatPaymentMethodDisplay($order) {
    $method = $order['payment_method'] ?? '';
    $gateway = $order['payment_gateway'] ?? '';
    
    if ($method === 'cash') {
        return 'Pay on Delivery';
    }
    if ($gateway === 'paystack') {
        return 'Paystack Online Payment';
    }
    if ($gateway === 'flutterwave') {
        return 'Flutterwave Online Payment';
    }
    if ($method === 'card') {
        return 'Online Payment';
    }
    return ucwords(str_replace('_', ' ', $method));
}
