<?php
// Temporary debug log to help diagnose 500 errors when browser shows no response.
// The webserver must be able to write files in this directory for these logs to appear.
@file_put_contents(__DIR__ . '/debug_display_exec.log', date('c') . " - display.php loaded\n", FILE_APPEND);

// Increase PHP limits for file uploads (5MB + overhead)
ini_set('upload_max_filesize', '5M');
ini_set('post_max_size', '15M');
ini_set('memory_limit', '256M');

// Maximum supported session lifetime (seconds) - 60 days
// For AJAX/API requests, suppress error output to avoid breaking JSON responses
$is_ajax_request = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_reporting(E_ALL);

// Ensure UTF-8 throughout the script to avoid character corruption (Norwegian letters etc.)
ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

// Set custom error handler to log errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    @file_put_contents(__DIR__ . '/php_errors.log', 
        date('c') . " [$errno] $errstr in $errfile:$errline\n", 
        FILE_APPEND);
    return false;
});

// Configure session GC and cookie parameters for indefinite sessions.
ini_set('session.gc_maxlifetime', 31536000); // 1 year

// Helper: detect if the current request is secure (HTTPS) — robust across servers/proxies
function request_is_secure(){
    if(!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') return true;
    if(!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') return true;
    if(!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return true;
    return false;
}

// session_set_cookie_params() accepts an options array since PHP 7.3. For older PHP versions
// pass the classic signature (lifetime, path, domain, secure, httponly).
if(defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300){
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
} else {
    $isSecure = request_is_secure();
    session_set_cookie_params(0, '/', '', $isSecure, true);
}

session_start();



function clear_session_cookie(){
    $name = session_name();
    $isSecure = request_is_secure();
    if(defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300){
        setcookie($name, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        setcookie($name, '', time() - 3600, '/', '', $isSecure, true);
    }
}

// Defaults exposed to pages that `require_once 'display.php'.
$is_logged_in = false;
$user_name = "Guest";
$user_email = '';
$user_created = null;
$email_verified = false;

// Database credentials (same as other files)
$DB_HOST = 'localhost';
$DB_NAME = 'lokale-tjenester';
$DB_USER = 'lokale-tjenester';
$DB_PASS = 'pwlt01!';

// Try to connect; fail quietly but log errors.
//connect (fail quietly)

if ($conn->connect_error) {
    @file_put_contents(__DIR__ . '/debug_db.log', 'Connect error: ' . $conn->connect_error . "\n", FILE_APPEND);
} else {
    @file_put_contents(__DIR__ . '/debug_db.log', 'Connected successfully' . "\n", FILE_APPEND);
}
$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn && $conn->connect_error) {
    error_log('display.php: DB connect error: ' . $conn->connect_error);
}

function safe_prepare($conn, $sql){
    try {
        // suppress warnings but allow exceptions to be caught
        $stmt = $conn->prepare($sql);
        if(!$stmt){
            error_log('display.php: prepare failed: ' . $conn->error . ' -- SQL: ' . $sql);
            return null;
        }
        return $stmt;
    } catch (mysqli_sql_exception $ex) {
        error_log('display.php: prepare exception: ' . $ex->getMessage() . ' -- SQL: ' . $sql);
        return null;
    }
}

// Helper: get human-readable upload error message
function getUploadErrorMessage($code){
    $messages = [
        UPLOAD_ERR_OK => 'OK',
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp directory',
        UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
        UPLOAD_ERR_EXTENSION => 'PHP extension blocked upload',
    ];
    return $messages[$code] ?? 'Unknown error (' . $code . ')';
}

// Create email_tokens table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS email_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY (user_id),
    KEY (token)
)");

// Create remember_tokens table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY (token),
    KEY (expires_at)
)");

// Create posts table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT NOT NULL,
    category VARCHAR(100),
    budget INT DEFAULT 0,
    location VARCHAR(255),
    user_id INT UNSIGNED,
    image MEDIUMBLOB DEFAULT NULL,
    image_type VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY (user_id),
    KEY (category),
    KEY (created_at)
)");

// Create post_images table for multiple images per post
$conn->query("CREATE TABLE IF NOT EXISTS post_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    image MEDIUMBLOB NOT NULL,
    image_type VARCHAR(50) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    KEY (post_id),
    KEY (sort_order)
)");

// Check if user has a valid remember token in cookie
if(!$is_logged_in && isset($_COOKIE['remember_token'])){
    $token = $_COOKIE['remember_token'];
    $stmt = safe_prepare($conn, "SELECT user_id, username, email FROM users u JOIN remember_tokens rt ON u.id = rt.user_id WHERE rt.token = ? AND rt.expires_at > NOW() LIMIT 1");
    if($stmt){
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $stmt->bind_result($uid, $uname, $uemail);
        if($stmt->fetch()){
            // Token is valid, log user in
            $is_logged_in = true;
            $user_name = $uname;
            $user_email = $uemail;
            $_SESSION['user_id'] = $uid;
            $_SESSION['user_name'] = $uname;
            $_SESSION['user_email'] = $uemail;
        }
        $stmt->close();
    }
}

// Logout via GET (link uses display.php?action=logout)
if(isset($_GET['action']) && $_GET['action'] === 'logout'){
    session_unset();
    session_destroy();
    clear_session_cookie();
    // If requested via XHR, return JSON; otherwise redirect back to home.
    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'){
        header('Content-Type: application/json');
        echo json_encode(['status'=>'success','message'=>'Logged out']);
        exit;
    }
    header('Location: index.php');
    exit;
}
session_start();
require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ---- DEBUGGING: log every POST ----
error_log("=== display.php hit ===");
error_log("POST keys: " . implode(',', array_keys($_POST)));
error_log("SESSION keys: " . implode(',', array_keys($_SESSION)));
error_log("REMOTE_ADDR: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// Make sure $action exists
$action = $_POST['action'] ?? null;
error_log("Action received: " . var_export($action, true));

// Only handle resend_verification
if ($action === 'resend_verification') {

    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        error_log("No user_id in session");
        echo json_encode(['success'=>false,'message'=>'Not logged in']);
        exit;
    }

    // Fetch email from DB
    $stmt = $conn->prepare("SELECT email FROM users WHERE id=?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $user_email = $user['email'] ?? null;
    if (!$user_email) {
        error_log("User email not found for ID $user_id");
        echo json_encode(['success'=>false,'message'=>'Email not found']);
        exit;
    }

    error_log("Sending verification email to $user_email for user ID $user_id");

    $ok = send_verification_email($conn, $user_email, $user_id);

    if ($ok) {
        error_log("Mail sent successfully to $user_email");
    } else {
        error_log("Failed to send mail to $user_email");
    }

    echo json_encode([
        'success' => $ok,
        'message' => $ok ? 'Verification email resent!' : 'Failed to send verification email'
    ]);
    exit;
} else {
    error_log("Unknown action: " . var_export($action, true));
}

function send_verification_email(mysqli $conn, string $email, int $user_id): bool {
    $token = bin2hex(random_bytes(32));

    // Remove old tokens
    $stmt = $conn->prepare("DELETE FROM email_tokens WHERE user_id=?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();

    // Insert new token
    $stmt = $conn->prepare(
        "INSERT INTO email_tokens (user_id, token, created_at) VALUES (?, ?, NOW())"
    );
    $stmt->bind_param('is', $user_id, $token);
    $stmt->execute();
    $stmt->close();

    $verifyLink = "https://" . getenv('DOMAIN') . "/pages.php?page=verify&token=" . urlencode($token);

    $mail = new PHPMailer(true);
    try {
        // Ensure outgoing mail uses UTF-8 charset so Norwegian characters are preserved
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->isSendmail();
        $mail->setFrom(getenv('FROM_EMAIL'), getenv('FROM_NAME') ?: 'Lokale Tjenester');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Verify your email';
        $mail->Body = "<p><a href='$verifyLink'>Verify Email</a></p>";

        return $mail->send();
    } catch (Exception $e) {
        error_log($mail->ErrorInfo);
        return false;
    }
}


function get_profile_picture_url(mysqli $conn, int $user_id): string {
    $stmt = safe_prepare($conn, "SELECT profile_picture, profile_picture_type FROM users WHERE id = ?");
    if(!$stmt) return ''; 
    
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($imgData, $imgType);
    $stmt->fetch();
    $stmt->close();
    
    if(!$imgData || !$imgType) return '';
    
    $base64 = base64_encode($imgData);
    return "data:{$imgType};base64,{$base64}";
}

function get_user_remaining_posts(mysqli $conn, int $user_id): int {
    $stmt = safe_prepare($conn, "SELECT posts_today, last_reset_date FROM user_posts_daily WHERE user_id = ?");
    if(!$stmt) return 3; // Default to 3 if error
    
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($postsToday, $lastResetDate);
    $stmt->fetch();
    $stmt->close();
    
    $today = date('Y-m-d');
    $postsToday = intval($postsToday ?? 0);
    $lastResetDate = $lastResetDate ?? '1970-01-01';
    
    // Reset counter if it's a new day AND update database
    if($lastResetDate !== $today) {
        $postsToday = 0;
        // Update or create database row to reflect the reset
        $resetStmt = safe_prepare($conn, "INSERT INTO user_posts_daily (user_id, posts_today, last_reset_date) VALUES (?, 0, ?) ON DUPLICATE KEY UPDATE posts_today = 0, last_reset_date = ?");
        if($resetStmt) {
            $resetStmt->bind_param('iss', $user_id, $today, $today);
            $resetStmt->execute();
            $resetStmt->close();
        }
    }
    
    $POST_LIMIT = 3;
    return max(0, $POST_LIMIT - $postsToday);
}



// AJAX POST handlers for login/signup (mirrors the backup behavior)
// Only handle AJAX-style POST actions when this file is the requested endpoint
// (i.e. avoid intercepting POSTs intended for pages that `require_once 'display.php'`).
if(realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])){
    // Log the incoming POST request for debugging (temporary)
    $post_log = date('c') . " - POST start: action=" . ($_POST['action'] ?? '(none)') . " method=" . ($_SERVER['REQUEST_METHOD'] ?? '') . " remote=" . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
    @file_put_contents(__DIR__ . '/debug_display_exec.log', $post_log, FILE_APPEND);

    $action = $_POST['action'] ?? null;


    header('Content-Type: application/json; charset=utf-8');
    // Also log POST keys and cookies briefly
    @file_put_contents(__DIR__ . '/debug_display_exec.log', date('c') . " - POST keys: " . implode(',', array_keys($_POST)) . "\n", FILE_APPEND);
    @file_put_contents(__DIR__ . '/debug_display_exec.log', date('c') . " - COOKIES: " . json_encode(array_keys($_COOKIE)) . "\n", FILE_APPEND);

    if($action === 'signup'){
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validate input
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            echo json_encode(['status'=>'error','message'=>'Invalid email']); exit;
        }
        if(strlen($username) < 3 || strlen($username) > 32){ 
            echo json_encode(['status'=>'error','message'=>'Username must be 3-32 characters']); exit; 
        }
        if(!preg_match('/^[A-Za-z0-9_\-]+$/', $username)){ 
            echo json_encode(['status'=>'error','message'=>'Username may only contain letters, numbers, dash or underscore']); exit; 
        }
        if(strlen($password) < 6){ 
            echo json_encode(['status'=>'error','message'=>'Password must be at least 6 characters']); exit; 
        }

        // Check if username or email already exists
        $stmt = safe_prepare($conn, "SELECT id FROM users WHERE username=? OR email=?");
        if($stmt){
            $stmt->bind_param('ss', $username, $email);
            $stmt->execute();
            $stmt->store_result();
            if($stmt->num_rows > 0){ 
                echo json_encode(['status'=>'error','message'=>'Username or email exists']); exit; 
            }
            $stmt->close();
        } else { 
            echo json_encode(['status'=>'error','message'=>'Database error']); exit; 
        }

        // Create user account
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = safe_prepare($conn, "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        if($stmt){
            $stmt->bind_param('sss', $username, $email, $hash);
            if(!$stmt->execute()){ 
                echo json_encode(['status'=>'error','message'=>'DB insert failed']); exit; 
            }
            $new_id = $conn->insert_id;
            session_regenerate_id(true);
            $_SESSION['user_id'] = $new_id;
            $_SESSION['user_name'] = $username;
            $_SESSION['user_email'] = $email;

            // Send verification email
            if(send_verification_email($conn, $email, $new_id)){
                echo json_encode(['status'=>'success','message'=>'Signup successful! Verification email sent.']);
            } else {
                echo json_encode(['status'=>'success','message'=>'Signup successful but failed to send verification email.']);
            }
            exit;
        } else { 
            echo json_encode(['status'=>'error','message'=>'Database error']); exit; 
        }
    }

    if($action === 'login'){
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember_me']);
        
        if(empty($username) || empty($password)){
            echo json_encode(['status'=>'error','message'=>'Username and password are required']); exit;
        }
        
        $stmt = safe_prepare($conn, "SELECT id, username, email, password_hash FROM users WHERE username=? OR email=?");
        if($stmt){
            $stmt->bind_param('ss', $username, $username);
            $stmt->execute();
            @file_put_contents(__DIR__ . '/debug_display_exec.log', date('c') . " - Login: query executed for username=$username\n", FILE_APPEND);
            $stmt->bind_result($user_id, $user_name, $user_email, $password_hash);
            if($stmt->fetch()){
                @file_put_contents(__DIR__ . '/debug_display_exec.log', date('c') . " - Login: user found, id=$user_id, hash starts with " . substr($password_hash, 0, 10) . "\n", FILE_APPEND);
                if(password_verify($password, $password_hash)){
                    @file_put_contents(__DIR__ . '/debug_display_exec.log', date('c') . " - Login: password correct\n", FILE_APPEND);
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['user_name'] = $user_name;
                    $_SESSION['user_email'] = $user_email;
                    
                    // If "remember me" is checked, create a token
                    if($remember){
                        $token = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days
                        $stmt2 = safe_prepare($conn, "INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                        if($stmt2){
                            $stmt2->bind_param('iss', $user_id, $token, $expires);
                            if($stmt2->execute()){
                                // Set cookie for 30 days
                                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                            }
                            $stmt2->close();
                        }
                    }
                    
                    echo json_encode(['status'=>'success','message'=>'Logged in successfully']);
                } else {
                    @file_put_contents(__DIR__ . '/debug_display_exec.log', date('c') . " - Login: password incorrect\n", FILE_APPEND);
                    echo json_encode(['status'=>'error','message'=>'Invalid username or password']);
                }
            } else {
                @file_put_contents(__DIR__ . '/debug_display_exec.log', date('c') . " - Login: user not found\n", FILE_APPEND);
                echo json_encode(['status'=>'error','message'=>'Invalid username or password']);
            }
            $stmt->close();
        } else {
            @file_put_contents(__DIR__ . '/debug_display_exec.log', date('c') . " - Login: DB error preparing statement\n", FILE_APPEND);
            echo json_encode(['status'=>'error','message'=>'Database error']); exit;
        }
        exit;
    }

    if ($action === 'resend_verification') {
        $user_email = $GLOBALS['user_email'] ?? null;
        if (!empty($_SESSION['user_id']) && $user_email) {
            send_verification_email($conn, $user_email, $_SESSION['user_id']);
            echo json_encode(['success'=>true,'message'=>'Verification email resent!']);
        } else {
            echo json_encode(['success'=>false,'message'=>'Not logged in or email unknown']);
        }
        exit;
    }
    
    if($action === 'debug'){
        // Return diagnostics for debugging (temporary)
        $db_ok = false;
        $db_err = null;
        if($conn){
            if($conn->connect_error){ $db_err = $conn->connect_error; }
            else $db_ok = true;
        } else {
            $db_err = 'No $conn';
        }
        $info = [
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'is_secure' => request_is_secure(),
            'session_name' => session_name(),
            'session_id' => session_id(),
            'cookies' => $_COOKIE,
            'session' => $_SESSION,
            'db_connected' => $db_ok,
            'db_error' => $db_err
        ];
        echo json_encode(['status'=>'success','debug'=>$info]);
        exit;
    }

    if($action === 'login'){
        $identifier = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $stmt = safe_prepare($conn, "SELECT id, password_hash FROM users WHERE username=? OR email=? LIMIT 1");
        if($stmt){
            $stmt->bind_param('ss', $identifier, $identifier);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($id, $hash);
            $stmt->fetch();
            if($stmt->num_rows === 1 && password_verify($password, $hash)){
                session_regenerate_id(true);
                $_SESSION['user_id'] = $id;
                echo json_encode(['status'=>'success','message'=>'Login successful']);
                exit;
            } else {
                echo json_encode(['status'=>'error','message'=>'Invalid credentials']); exit;
            }
        } else { echo json_encode(['status'=>'error','message'=>'Database error']); exit; }
    }

    if($action === 'reset_post_limit'){
        // Admin-only endpoint to reset daily post limit for current user (for testing)
        if(empty($_SESSION['user_id'])){ 
            echo json_encode(['status'=>'error','message'=>'Not logged in']); 
            exit; 
        }
        
        // Verify admin status by checking database
        $uid = intval($_SESSION['user_id']);
        $adminCheckStmt = safe_prepare($conn, "SELECT COALESCE(is_admin,0) AS is_admin FROM users WHERE id = ? LIMIT 1");
        $is_admin_user = false;
        if($adminCheckStmt) {
            $adminCheckStmt->bind_param('i', $uid);
            $adminCheckStmt->execute();
            $adminCheckStmt->bind_result($admin_flag);
            $adminCheckStmt->fetch();
            $adminCheckStmt->close();
            $is_admin_user = !empty($admin_flag) ? true : false;
        }
        
        // Also check protected runtime admins
        $user_name = $_SESSION['user_name'] ?? '';
        $protected_runtime_admins = array('pyxis', 'adminpyx', 'kentanto65', 'lokale-tjenester');
        if(!$is_admin_user && in_array($user_name, $protected_runtime_admins, true)){
            $is_admin_user = true;
        }
        
        if(!$is_admin_user) {
            echo json_encode(['status'=>'error','message'=>'Admin access required']); 
            exit; 
        }
        
        $today = date('Y-m-d');
        
        $resetStmt = safe_prepare($conn, "INSERT INTO user_posts_daily (user_id, posts_today, last_reset_date) VALUES (?, 0, ?) ON DUPLICATE KEY UPDATE posts_today = 0, last_reset_date = ?");
        if($resetStmt) {
            $resetStmt->bind_param('iss', $uid, $today, $today);
            if($resetStmt->execute()) {
                echo json_encode(['status'=>'success','message'=>'Daily post limit reset']);
            } else {
                echo json_encode(['status'=>'error','message'=>'Failed to reset: ' . $resetStmt->error]);
            }
            $resetStmt->close();
        } else {
            echo json_encode(['status'=>'error','message'=>'Database error']);
        }
        exit;
    }

    if($action === 'create_post'){
        try {
            // create a job/post supporting multiple images; convert uploads to web-friendly format (webp/jpeg)
            error_log('=== CREATE_POST START ===');
            error_log('PHP limits: upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size') . ', memory_limit=' . ini_get('memory_limit'));
            error_log('POST data: title=' . ($_POST['title'] ?? 'MISSING') . ', desc=' . (strlen($_POST['description'] ?? '') > 0 ? 'OK' : 'MISSING') . ', FILES count=' . count($_FILES));
            error_log('GD library available: ' . (extension_loaded('gd') ? 'YES' : 'NO'));
            error_log('imagewebp function: ' . (function_exists('imagewebp') ? 'YES' : 'NO'));
            
            // Log file upload errors explicitly
            if(!empty($_FILES['image'])){
                if(is_array($_FILES['image']['error'])){
                    foreach($_FILES['image']['error'] as $idx => $err){
                        if($err !== UPLOAD_ERR_OK){
                            error_log('File upload error at index ' . $idx . ': ' . getUploadErrorMessage($err));
                        }
                    }
                } elseif($_FILES['image']['error'] !== UPLOAD_ERR_OK){
                    error_log('File upload error: ' . getUploadErrorMessage($_FILES['image']['error']) . ' (size=' . ($_FILES['image']['size'] ?? 0) . ' bytes)');
                }
            }
            error_log('FILES[image]: ' . json_encode($_FILES['image'] ?? 'NOT SET'));
        
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $budget = intval($_POST['budget'] ?? 0);
            $location = trim($_POST['location'] ?? '');

            if(!$title || !$desc){ error_log('create_post: Missing title or desc'); echo json_encode(['status'=>'error','message'=>'Title and description required']); exit; }
            if(empty($_SESSION['user_id'])){ error_log('create_post: No user_id in session'); echo json_encode(['status'=>'error','message'=>'You must be logged in to create a job']); exit; }
            $uid = intval($_SESSION['user_id']);

            // Check rate limit: 3 posts per day (resets at midnight)
            $postsToday = 0;
            $lastResetDate = date('Y-m-d');
            $rateCheckStmt = safe_prepare($conn, "SELECT posts_today, last_reset_date FROM user_posts_daily WHERE user_id = ?");
            if($rateCheckStmt) {
                $rateCheckStmt->bind_param('i', $uid);
                if($rateCheckStmt->execute()) {
                    $rateCheckStmt->bind_result($postsToday, $lastResetDate);
                    $rateCheckStmt->fetch();
                }
                $rateCheckStmt->close();
            }

            $today = date('Y-m-d');
            $postsToday = intval($postsToday ?? 0);
            $lastResetDate = $lastResetDate ?? '1970-01-01';

            // Reset counter if it's a new day
            if($lastResetDate !== $today){
                $postsToday = 0;
            }

            // Enforce 3 posts per day limit
            $POST_LIMIT = 3;
            if($postsToday >= $POST_LIMIT){
                error_log('create_post: User ' . $uid . ' exceeded daily post limit (' . $postsToday . '/' . $POST_LIMIT . ')');
                echo json_encode(['status'=>'error','message'=>'Du har nådd maksimalt antall poster (3) per dag. Prøv igjen i morgen!', 'remaining' => 0]);
                exit;
            }

            // Insert post record first (no image columns relied on here)
            $stmt = safe_prepare($conn, "INSERT INTO posts (title, description, category, budget, location, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            if(!$stmt){ error_log('create_post: safe_prepare posts insert failed - ' . ($conn ? $conn->error : 'No connection')); echo json_encode(['status'=>'error','message'=>'Database error']); exit; }
            $stmt->bind_param('sssisi', $title, $desc, $category, $budget, $location, $uid);
            if(!$stmt->execute()){ error_log('create_post: execute posts insert failed - ' . $stmt->error); echo json_encode(['status'=>'error','message'=>'Failed to create job']); exit; }
            $post_id = $conn->insert_id;
            error_log('create_post: Successfully created post with id=' . $post_id);
            $stmt->close();

            // Update daily post count for this user
            $newCount = $postsToday + 1;
            $updateCountStmt = safe_prepare($conn, "INSERT INTO user_posts_daily (user_id, posts_today, last_reset_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE posts_today = ?, last_reset_date = ?");
            if(!$updateCountStmt){ error_log('create_post: safe_prepare update post count failed'); }
            else {
                $updateCountStmt->bind_param('iisis', $uid, $newCount, $today, $newCount, $today);
                if(!$updateCountStmt->execute()){ error_log('create_post: execute update post count failed - ' . $updateCountStmt->error); }
                $updateCountStmt->close();
            }

            // Normalize uploaded files (support single or multiple inputs)
            $files = [];
            if(isset($_FILES['image'])){
                if(is_array($_FILES['image']['name'])){
                    for($i=0;$i<count($_FILES['image']['name']);$i++){
                        $files[] = [
                            'name' => $_FILES['image']['name'][$i] ?? '',
                            'tmp_name' => $_FILES['image']['tmp_name'][$i] ?? '',
                            'size' => $_FILES['image']['size'][$i] ?? 0,
                            'error' => $_FILES['image']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                        ];
                    }
                } else {
                    $files[] = [ 'name'=>$_FILES['image']['name'],'tmp_name'=>$_FILES['image']['tmp_name'],'size'=>$_FILES['image']['size'],'error'=>$_FILES['image']['error'] ];
                }
            }

            // Helper: convert an uploaded file to webp (if available) or jpeg and return [data, type] or false
            $convertImage = function(string $tmpPath){
                error_log('convertImage: reading ' . $tmpPath);
                $raw = @file_get_contents($tmpPath);
                if($raw === false){ error_log('convertImage: file_get_contents failed'); return false; }
                error_log('convertImage: raw file size=' . strlen($raw) . ' bytes');
                
                // Detect image type from magic bytes
                $img = null;
                if(strpos($raw, "\x89PNG") === 0){
                    error_log('convertImage: detected PNG format');
                    $tempFile = tempnam(sys_get_temp_dir(), 'img_');
                    if(file_put_contents($tempFile, $raw)){
                        $img = @imagecreatefrompng($tempFile);
                        if(!$img) error_log('convertImage: imagecreatefrompng failed');
                        else error_log('convertImage: imagecreatefrompng SUCCESS');
                        @unlink($tempFile);
                    }
                } elseif(strpos($raw, "\xFF\xD8\xFF") === 0){
                    error_log('convertImage: detected JPEG format');
                    $tempFile = tempnam(sys_get_temp_dir(), 'img_');
                    if(file_put_contents($tempFile, $raw)){
                        $img = @imagecreatefromjpeg($tempFile);
                        if(!$img) error_log('convertImage: imagecreatefromjpeg failed');
                        else error_log('convertImage: imagecreatefromjpeg SUCCESS');
                        @unlink($tempFile);
                    }
                } elseif(strpos($raw, "RIFF") === 0 && strpos($raw, "WEBP") !== false){
                    error_log('convertImage: detected WebP format');
                    $tempFile = tempnam(sys_get_temp_dir(), 'img_');
                    if(file_put_contents($tempFile, $raw)){
                        $img = @imagecreatefromwebp($tempFile);
                        if(!$img) error_log('convertImage: imagecreatefromwebp failed');
                        else error_log('convertImage: imagecreatefromwebp SUCCESS');
                        @unlink($tempFile);
                    }
                } else {
                    error_log('convertImage: unknown format, trying imagecreatefromstring');
                    $img = @imagecreatefromstring($raw);
                }
                
                if(!$img){ error_log('convertImage: all image load methods failed'); return false; }
                error_log('convertImage: image resource created');
                
                // For PNG with transparency, create a white background to avoid transparency issues
                $width = imagesx($img);
                $height = imagesy($img);
                $bg = imagecreatetruecolor($width, $height);
                $white = imagecolorallocate($bg, 255, 255, 255);
                imagefill($bg, 0, 0, $white);
                imagecopy($bg, $img, 0, 0, 0, 0, $width, $height);
                imagedestroy($img);
                $img = $bg;
                
                ob_start();
                if(function_exists('imagewebp')){
                    // WebP at quality 80
                    error_log('convertImage: using imagewebp');
                    $result = @imagewebp($img, NULL, 80);
                    if(!$result) error_log('convertImage: imagewebp output failed');
                    $data = ob_get_clean();
                    $type = 'webp';
                } else {
                    // Fallback to JPEG
                    error_log('convertImage: using imagejpeg (fallback)');
                    $result = @imagejpeg($img, NULL, 85);
                    if(!$result) error_log('convertImage: imagejpeg output failed');
                    $data = ob_get_clean();
                    $type = 'jpeg';
                }
                error_log('convertImage: final data size=' . strlen($data ?? '') . ' bytes, type=' . $type);
                imagedestroy($img);
                return [$data, $type];
            };

            // Insert images into post_images (if any)
            if(count($files) > 0){
                $sort = 0;
                $firstImage = null;
                foreach($files as $f){
                    if(empty($f['tmp_name']) || $f['error'] !== UPLOAD_ERR_OK){ error_log('create_post: file upload error - error code ' . ($f['error'] ?? 'unknown')); continue; }
                    if($f['size'] > 5 * 1024 * 1024){ error_log('create_post: file too large - ' . $f['size'] . ' bytes'); continue; }
                    error_log('create_post: processing file ' . ($f['name'] ?? 'unknown') . ' from ' . $f['tmp_name']);
                    
                    $conv = $convertImage($f['tmp_name']);
                    if(!$conv){ error_log('create_post: image conversion FAILED for ' . ($f['name'] ?? 'unknown')); continue; }
                    list($bin, $itype) = $conv;
                    $binSize = strlen($bin ?? '');
                    error_log('create_post: conversion SUCCESS - ' . ($f['name'] ?? 'file') . ' to ' . $itype . ' (' . $binSize . ' bytes)');
                    
                    if($binSize <= 0){ error_log('create_post: WARNING - converted image is empty!'); continue; }
                    
                    // RE-PREPARE statement for each BLOB insert (required by some drivers)
                    $imgStmt = safe_prepare($conn, "INSERT INTO post_images (post_id, image, image_type, sort_order, created_at) VALUES (?, ?, ?, ?, NOW())");
                    if(!$imgStmt){ error_log('create_post: safe_prepare failed on iteration - ' . ($conn ? $conn->error : 'No connection')); continue; }
                    
                    // Bind parameters for BLOB
                    $null = NULL;
                    if(!$imgStmt->bind_param('ibsi', $post_id, $null, $itype, $sort)){ 
                        error_log('create_post: bind_param failed - ' . $imgStmt->error); 
                        $imgStmt->close();
                        continue; 
                    }
                    error_log('create_post: bind_param SUCCESS');
                    
                    if(!$imgStmt->send_long_data(1, $bin)){ 
                        error_log('create_post: send_long_data FAILED - ' . $imgStmt->error); 
                        $imgStmt->close();
                        continue; 
                    }
                    error_log('create_post: send_long_data SUCCESS (' . $binSize . ' bytes)');
                    
                    if(!$imgStmt->execute()){ 
                        error_log('create_post: execute FAILED - ' . $imgStmt->error); 
                        $imgStmt->close();
                        continue;
                    }
                    error_log('create_post: execute SUCCESS for ' . ($f['name'] ?? 'file'));
                    
                    if($firstImage === null){ 
                        $firstImage = ['data'=>$bin,'type'=>$itype]; 
                        error_log('create_post: saved first image (' . strlen($firstImage['data']) . ' bytes)');
                    }
                    $sort++;
                    $imgStmt->close();
                }

                // Update posts.image and posts.image_type for backward compatibility (if columns exist)
                if($firstImage !== null){
                    $upd = safe_prepare($conn, "UPDATE posts SET image = ?, image_type = ? WHERE id = ?");
                    if($upd){
                        // Bind: 'b'=image (BLOB, passed as NULL then via send_long_data), 's'=image_type, 'i'=id
                        $null = NULL;
                        if(!$upd->bind_param('bsi', $null, $firstImage['type'], $post_id)){ 
                            error_log('create_post: update bind_param failed - ' . $upd->error); 
                        } else if(!$upd->send_long_data(0, $firstImage['data'])){ 
                            error_log('create_post: update send_long_data failed - ' . $upd->error); 
                        } else if(!$upd->execute()){ 
                            error_log('create_post: update posts.image execute failed - ' . $upd->error); 
                        } else {
                            error_log('create_post: successfully updated posts.image for post ' . $post_id);
                        }
                        $upd->close();
                    }
                }
        } else {
            error_log('create_post: No files uploaded (files count=' . count($files) . ')');
        }

            error_log('=== CREATE_POST END SUCCESS ===');
            echo json_encode(['status'=>'success','message'=>'Job created successfully']);
        } catch(Exception $e) {
            error_log('create_post EXCEPTION: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            error_log('Trace: ' . $e->getTraceAsString());
            echo json_encode(['status'=>'error','message'=>'Error: ' . $e->getMessage()]);
        } catch(Throwable $t) {
            error_log('create_post THROWABLE: ' . $t->getMessage());
            echo json_encode(['status'=>'error','message'=>'Fatal error: ' . $t->getMessage()]);
        }
        exit;
    }

    if($action === 'list_jobs'){
        // list jobs with optional filters
        $q = trim($_POST['q'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $min = isset($_POST['min_budget']) ? intval($_POST['min_budget']) : null;
        $max = isset($_POST['max_budget']) ? intval($_POST['max_budget']) : null;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;

        $sql = "SELECT p.id, p.title, p.description, p.category, p.budget, p.location, p.created_at, COALESCE(u.username,'Guest') AS username, IF(p.image, CONCAT('data:image/', COALESCE(p.image_type, 'jpeg'), ';base64,', TO_BASE64(p.image)), NULL) AS image FROM posts p LEFT JOIN users u ON p.user_id = u.id";
        $where = [];
        $types = '';
        $params = [];

        if($q !== ''){ $where[] = "(p.title LIKE ? OR p.description LIKE ? )"; $params[] = "%$q%"; $params[] = "%$q%"; $types .= 'ss'; }
        if($category !== ''){ $where[] = "p.category = ?"; $params[] = $category; $types .= 's'; }
        if($location !== ''){ $where[] = "p.location = ?"; $params[] = $location; $types .= 's'; }
        if($min !== null){ $where[] = "p.budget >= ?"; $params[] = $min; $types .= 'i'; }
        if($max !== null){ $where[] = "p.budget <= ?"; $params[] = $max; $types .= 'i'; }

        if(count($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY p.created_at DESC LIMIT ' . intval($limit);

        $stmt = safe_prepare($conn, $sql);
        if(!$stmt){ 
            error_log('list_jobs: safe_prepare failed - ' . ($conn ? $conn->error : 'No connection'));
            echo json_encode(['status'=>'error','message'=>'Database error preparing list']); 
            exit; 
        }

        if($types !== ''){
            // bind dynamically
            $bindNames = [];
            $bindNames[] = $types;
            foreach($params as $i => $v) $bindNames[] = &$params[$i];
            call_user_func_array([$stmt, 'bind_param'], $bindNames);
        }

        if(!$stmt->execute()){
            error_log('list_jobs: execute failed - ' . $stmt->error);
            echo json_encode(['status'=>'error','message'=>'Database error executing query']); 
            exit;
        }

        $rows = [];
        // Prefer get_result() when available (requires mysqlnd). Fall back to bind_result() otherwise.
        if(method_exists($stmt, 'get_result')){
            $res = $stmt->get_result();
            while($r = $res->fetch_assoc()) $rows[] = $r;
        } else {
            // Fallback: fetch results via metadata + bind_result
            $stmt->store_result();
            $meta = $stmt->result_metadata();
            if($meta){
                $fields = [];
                while($f = $meta->fetch_field()) $fields[] = $f->name;
                $meta->free();

                $bindVars = [];
                $row = [];
                foreach($fields as $fld){
                    $row[$fld] = null;
                    $bindVars[] = & $row[$fld];
                }
                if(count($bindVars)){
                    call_user_func_array([$stmt, 'bind_result'], $bindVars);
                    while($stmt->fetch()){
                        $out = [];
                        foreach($row as $k => $v) $out[$k] = $v;
                        $rows[] = $out;
                    }
                }
            }
        }

        $stmt->close();

        // Attach first image (from post_images) to each row when available (prefer post_images over posts.image)
        $imgStmt = $conn->prepare("SELECT image, image_type FROM post_images WHERE post_id = ? ORDER BY sort_order ASC LIMIT 1");
        if($imgStmt){
            foreach($rows as $i => $r){
                $pid = intval($r['id']);
                $imgStmt->bind_param('i', $pid);
                if($imgStmt->execute()){
                    $imgStmt->store_result();
                    if($imgStmt->num_rows > 0){
                        $imgStmt->bind_result($idata, $itype);
                        if($imgStmt->fetch()){
                            if($idata !== null){
                                $rows[$i]['image'] = 'data:image/' . ($itype ?: 'jpeg') . ';base64,' . base64_encode($idata);
                            }
                        }
                    }
                }
            }
            $imgStmt->close();
        }

        echo json_encode(['status'=>'success','jobs'=>$rows]); 
        exit;
    }

    if($action === 'get_post_detail'){
        // fetch full details of a single job post
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if($post_id <= 0){ echo json_encode(['status'=>'error','message'=>'Invalid post ID']); exit; }
        
        $sql = "SELECT p.id, p.title, p.description, p.category, p.budget, p.location, p.created_at, COALESCE(u.username,'Guest') AS username, IF(p.image, CONCAT('data:image/', COALESCE(p.image_type, 'jpeg'), ';base64,', TO_BASE64(p.image)), NULL) AS image FROM posts p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ? LIMIT 1";
        
        $stmt = safe_prepare($conn, $sql);
        if(!$stmt){ echo json_encode(['status'=>'error','message'=>'Database error']); exit; }
        
        $stmt->bind_param('i', $post_id);
        if(!$stmt->execute()){ echo json_encode(['status'=>'error','message'=>'Query failed']); exit; }
        
        $row = null;
        if(method_exists($stmt, 'get_result')){
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
        } else {
            $stmt->store_result();
            if($stmt->num_rows > 0){
                $meta = $stmt->result_metadata();
                if($meta){
                    $fields = [];
                    while($f = $meta->fetch_field()) $fields[] = $f->name;
                    $meta->free();
                    
                    $bindVars = [];
                    $row = [];
                    foreach($fields as $fld){ $row[$fld] = null; $bindVars[] = & $row[$fld]; }
                    if(count($bindVars)){
                        call_user_func_array([$stmt, 'bind_result'], $bindVars);
                        if($stmt->fetch()){ $out = []; foreach($row as $k => $v) $out[$k] = $v; $row = $out; }
                    }
                }
            }
        }
        $stmt->close();

        if(!$row){ echo json_encode(['status'=>'error','message'=>'Post not found']); exit; }

        // Fetch all images for this post
        $images = [];
        $imgQ = $conn->prepare("SELECT image, image_type FROM post_images WHERE post_id = ? ORDER BY sort_order ASC");
        if($imgQ){
            $imgQ->bind_param('i', $post_id);
            if($imgQ->execute()){
                $res = $imgQ->get_result();
                if($res){
                    while($rowImg = $res->fetch_assoc()){
                        if($rowImg['image'] !== null){
                            $images[] = 'data:image/' . ($rowImg['image_type'] ?: 'jpeg') . ';base64,' . base64_encode($rowImg['image']);
                        }
                    }
                }
            }
            $imgQ->close();
        }

        // Provide images array and keep legacy `image` field if present
        $row['images'] = $images;
        if(empty($row['image']) && count($images) > 0) $row['image'] = $images[0];

        echo json_encode(['status'=>'success','post'=>$row]);
        exit;
    }

    if($action === 'logout'){
        session_unset();
        session_destroy();
        clear_session_cookie();
        echo json_encode(['status'=>'success','message'=>'Logged out']);
        exit;
    }

    if($action === 'update_settings'){
        // allow logged-in users to change settings: username, email, session duration
        if(empty($_SESSION['user_id'])){ echo json_encode(['status'=>'error','message'=>'Not authenticated']); exit; }
        $uid = intval($_SESSION['user_id']);
        
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        // Validate inputs
        if(!$username){ echo json_encode(['status'=>'error','message'=>'Username cannot be empty']); exit; }
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){ echo json_encode(['status'=>'error','message'=>'Invalid email']); exit; }
        if(strlen($username) < 3 || strlen($username) > 32){ echo json_encode(['status'=>'error','message'=>'Username must be 3-32 characters']); exit; }
        if(!preg_match('/^[A-Za-z0-9_\-]+$/', $username)){ echo json_encode(['status'=>'error','message'=>'Username may only contain letters, numbers, dash or underscore']); exit; }
        
        // Check if new username/email already exists for OTHER users
        $stmt = safe_prepare($conn, "SELECT id FROM users WHERE (username=? OR email=?) AND id != ?");
        if($stmt){
            $stmt->bind_param('ssi', $username, $email, $uid);
            $stmt->execute();
            $stmt->store_result();
            if($stmt->num_rows > 0){ echo json_encode(['status'=>'error','message'=>'Username or email already taken']); exit; }
            $stmt->close();
        } else { echo json_encode(['status'=>'error','message'=>'Database error']); exit; }
        
        // Update username and email
        $stmt = safe_prepare($conn, "UPDATE users SET username=?, email=? WHERE id=?");
        if($stmt){
            $stmt->bind_param('ssi', $username, $email, $uid);
            if(!$stmt->execute()){ echo json_encode(['status'=>'error','message'=>'Failed to update settings']); exit; }
            echo json_encode(['status'=>'success','message'=>'Settings updated']); exit;
        } else { echo json_encode(['status'=>'error','message'=>'Database error']); exit; }
    }

    if($action === 'upload_profile_picture'){
        // Handle profile picture upload
        if(empty($_SESSION['user_id'])){ echo json_encode(['status'=>'error','message'=>'Not authenticated']); exit; }
        
        if(empty($_FILES['profile_picture'])){ echo json_encode(['status'=>'error','message'=>'No file uploaded']); exit; }
        
        $file = $_FILES['profile_picture'];
        $uid = intval($_SESSION['user_id']);
        
        // Validate file
        if($file['error'] !== UPLOAD_ERR_OK){ 
            echo json_encode(['status'=>'error','message'=>'Upload error: ' . getUploadErrorMessage($file['error'])]); 
            exit; 
        }
        
        if($file['size'] > 5 * 1024 * 1024){ 
            echo json_encode(['status'=>'error','message'=>'File too large (max 5MB)']); 
            exit; 
        }
        
        // Check if GD library is available for image processing
        if(!extension_loaded('gd')){ 
            echo json_encode(['status'=>'error','message'=>'Image processing not available']); 
            exit; 
        }
        
        // Load and validate image
        $img = @imagecreatefromstring(file_get_contents($file['tmp_name']));
        if(!$img){ 
            echo json_encode(['status'=>'error','message'=>'Invalid image file']); 
            exit; 
        }
        
        // Resize to 200x200 (square profile pic)
        $size = 200;
        $thumb = imagecreatetruecolor($size, $size);
        $srcW = imagesx($img);
        $srcH = imagesy($img);
        $minDim = min($srcW, $srcH);
        $srcX = ($srcW - $minDim) / 2;
        $srcY = ($srcH - $minDim) / 2;
        
        imagecopyresampled($thumb, $img, 0, 0, $srcX, $srcY, $size, $size, $minDim, $minDim);
        imagedestroy($img);
        
        // Convert to WEBP
        ob_start();
        imagewebp($thumb, null, 80);
        $imgData = ob_get_clean();
        imagedestroy($thumb);
        
        if(!$imgData || strlen($imgData) === 0){ 
            echo json_encode(['status'=>'error','message'=>'Failed to process image']); 
            exit; 
        }
        
        // Save to database
        $stmt = safe_prepare($conn, "UPDATE users SET profile_picture=?, profile_picture_type='image/webp' WHERE id=?");
        if(!$stmt){ 
            echo json_encode(['status'=>'error','message'=>'Database error']); 
            exit; 
        }
        
        $stmt->bind_param('si', $imgData, $uid);
        if(!$stmt->execute()){ 
            echo json_encode(['status'=>'error','message'=>'Failed to save profile picture']); 
            exit; 
        }
        
        echo json_encode(['status'=>'success','message'=>'Profile picture updated']);
        $stmt->close();
        exit;
    }

    if($action === 'contact'){
        // Handle contact form submission
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $message = trim($_POST['message'] ?? '');

        // Validate inputs
        if(!$name){ echo json_encode(['status'=>'error','message'=>'Name is required']); exit; }
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){ echo json_encode(['status'=>'error','message'=>'Invalid email address']); exit; }
        if(!$message){ echo json_encode(['status'=>'error','message'=>'Message cannot be empty']); exit; }

        // Send contact email to site admin
        $mail = new PHPMailer(true);
        try {
            // Ensure outgoing mail uses UTF-8 charset so Norwegian characters are preserved
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->isSendmail();
            $mail->setFrom($email, $name);
            $mail->addAddress('post@lokale-tjenester.no');
            $mail->isHTML(true);
            $mail->Subject = 'Ny kontaktmelding fra ' . htmlspecialchars($name);
            
            // Format the email body
            $mail->Body = "
                <h2>Ny kontaktmelding mottatt</h2>
                <p><strong>Fra:</strong> " . htmlspecialchars($name) . "</p>
                <p><strong>E-post:</strong> <a href=\"mailto:" . htmlspecialchars($email) . "\">" . htmlspecialchars($email) . "</a></p>
                <p><strong>Melding:</strong></p>
                <p>" . nl2br(htmlspecialchars($message)) . "</p>
                <hr>
                <p><em>Sendt fra kontaktskjemaet på lokale-tjenester.no</em></p>
            ";

            $mail->send();
            echo json_encode(['status'=>'success','message'=>'Takk for meldingen din! Vi svarer så snart som mulig.']); exit;
        } catch (Exception $e) {
            error_log('Contact email error: ' . $mail->ErrorInfo);
            echo json_encode(['status'=>'error','message'=>'Kunne ikke sende melding. Vennligst prøv igjen senere.']); exit;
        }
    }

    // Unknown action
    echo json_encode(['status'=>'error','message'=>'Unknown action']);
    exit;
}

// If included by other pages, expose logged-in state below.
$is_logged_in = false;
$user_name = 'Guest';
$user_email = '';
$user_created = null;
// admin flag (will be set if the users table supports it)
$is_admin = false;
if(isset($_SESSION['user_id']) && $conn){
    // Detect whether the users table has an is_admin column.
    $has_is_admin = false;
    try {
        $res = $conn->query("SHOW COLUMNS FROM users LIKE 'is_admin'");
        if($res && $res->num_rows > 0) $has_is_admin = true;
    } catch (Exception $e) {
        $has_is_admin = false;
    }

    if($has_is_admin){
        $stmt = safe_prepare($conn, "SELECT username, email, created_at, email_verified, COALESCE(is_admin,0) AS is_admin FROM users WHERE id = ? LIMIT 1");
    } else {
        $stmt = safe_prepare($conn, "SELECT username, email, created_at, email_verified FROM users WHERE id = ? LIMIT 1");
    }
    if($stmt){
        $sessUid = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $stmt->bind_param('i', $sessUid);
        $stmt->execute();
        if($has_is_admin){
            $stmt->bind_result($username, $email, $created_at, $email_verified, $is_admin_flag);
            $stmt->fetch();
            $is_admin = !empty($is_admin_flag) ? true : false;
        } else {
            $stmt->bind_result($username, $email, $created_at, $email_verified);
            $stmt->fetch();
            $is_admin = false;
        }

        if($username){
            $is_logged_in = true;
            $user_name = $username;
            $user_email = $email;
            $user_created = $created_at;
            $email_verified = !empty($email_verified) ? true : false;
        }
        // Grant admin to protected runtime users (no DB changes needed)
        $protected_runtime_admins = array('pyxis', 'adminpyx', 'kentanto65', 'lokale-tjenester');
        if(!$is_admin && in_array($user_name, $protected_runtime_admins, true)){
            $is_admin = true;
        }

        $stmt->close();
    }
}

// If this file is requested directly, render a small login/signup page when requested.
if(realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])){
    $act = $_GET['action'] ?? null;
    if($act === 'login' || $act === 'signup'){
        // Minimal standalone page that posts to this same file via fetch
        ?><!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <link rel="icon" type="image/png" href="assets/Lokale_Tjenester_only_logo.png">
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <link rel="stylesheet" href="static/style.css">
            <title><?php echo ($act==='login') ? 'Login' : 'Sign Up'; ?> — Lokale Tjenester</title>
            <style>body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:24px;background:#f7f7f7}.wrap{max-width:640px;margin:0 auto}.card{background:#fff;padding:16px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08)}</style>
        </head>
        <body>
          <div class="wrap">
            <h1><?php echo ($act==='login') ? 'Login' : 'Sign Up'; ?></h1>
            <div class="card">
                <?php if($act === 'login'): ?>
                <form id="loginForm">
                    <div id="loginMsg" style="color:red;margin-bottom:8px" aria-live="polite"></div>
                    <input type="text" name="username" placeholder="Username or email" required style="display:block;margin:8px 0;padding:8px;width:100%">
                    <input type="password" name="password" placeholder="Password" required style="display:block;margin:8px 0;padding:8px;width:100%">
                    <button type="submit">Login</button>
                </form>
                <?php else: ?>
                <form id="signupForm">
                    <div id="signupMsg" style="color:red;margin-bottom:8px" aria-live="polite"></div>
                    <input type="text" name="username" placeholder="Username" required style="display:block;margin:8px 0;padding:8px;width:100%">
                    <input type="email" name="email" placeholder="Email" required style="display:block;margin:8px 0;padding:8px;width:100%">
                    <input type="password" name="password" placeholder="Password" required style="display:block;margin:8px 0;padding:8px;width:100%">
                    <button type="submit">Sign Up</button>
                </form>
                <?php endif; ?>
                <p style="margin-top:12px"><a href="index.php">Return to home</a></p>
            </div>
          </div>
        <script>
        (function(){
            function jsonPost(form, action){
                var data = new FormData(form);
                data.append('action', action);
                fetch('display.php', {method:'POST', body: data, credentials: 'same-origin'})
                .then(r=>r.json()).then(function(resp){
                    if(resp.status === 'success'){
                        window.location = 'index.php';
                    } else {
                        var msgEl = document.getElementById(action === 'login' ? 'loginMsg' : 'signupMsg');
                        if(msgEl) msgEl.textContent = resp.message || 'Error';
                    }
                }).catch(function(err){
                    var msgEl = document.getElementById(action === 'login' ? 'loginMsg' : 'signupMsg');
                    if(msgEl) msgEl.textContent = 'Network error';
                });
            }
            var loginForm = document.getElementById('loginForm');
            if(loginForm) loginForm.addEventListener('submit', function(e){ e.preventDefault(); jsonPost(loginForm,'login'); });
            var signupForm = document.getElementById('signupForm');
            if(signupForm) signupForm.addEventListener('submit', function(e){ e.preventDefault(); jsonPost(signupForm,'signup'); });
        })();
        </script>
        </body>
        </html>
        <?php
        exit;
    }
}

?>

