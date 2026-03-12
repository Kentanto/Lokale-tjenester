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

// Configure session GC and cookie parameters for 30-day persistent sessions
// Set both gc_maxlifetime and cookie lifetime to 30 days to ensure server-side session files aren't garbage collected prematurely
$sessionLifetime = 30 * 24 * 60 * 60; // 30 days in seconds
ini_set('session.gc_maxlifetime', $sessionLifetime); // Server-side session file lifetime
ini_set('session.gc_probability', 1); // Run GC 1% of the time (default is fine)
ini_set('session.gc_divisor', 100); // Run GC every ~100 requests
ini_set('session.cache_expire', 43200); // Cache for 30 days worth of minutes (30*24*60 = 43200)

// Helper: detect if the current request is secure (HTTPS) — robust across servers/proxies
function request_is_secure(){
    if(!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') return true;
    if(!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') return true;
    if(!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return true;
    return false;
}

$isSecure = request_is_secure();

if(defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300){
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
} else {
    session_set_cookie_params($sessionLifetime, '/', '', $isSecure, true);
}

session_start();

// ===== SECURITY HEADERS =====
// Prevent MIME type sniffing
header('X-Content-Type-Options: nosniff');
// Prevent clickjacking
header('X-Frame-Options: SAMEORIGIN');
// Legacy XSS protection
header('X-XSS-Protection: 1; mode=block');
// Force HTTPS (uncomment when on production)
// header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
// Content Security Policy
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' https: data:;");

// ===== DEBUG MODE CONTROL =====
define('DEBUG_MODE', getenv('DEBUG_MODE') === 'true');

function debug_log($msg) {
    if (DEBUG_MODE) {
        @file_put_contents(__DIR__ . '/debug_display_exec.log', date('c') . " - $msg\n", FILE_APPEND);
    }
}

// ===== CSRF TOKEN FUNCTIONS =====
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token = null) {
    $token = $token ?? ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

// ===== LOGIN RATE LIMITING (5 attempts in 15 min, then 1 hour lockout) =====
function check_login_rate_limit($identifier) {
    $key = 'login_attempts_' . md5($identifier);
    $lockout_key = $key . '_locked_until';
    $attempts = $_SESSION[$key] ?? 0;
    $last_attempt = $_SESSION[$key . '_time'] ?? 0;
    $locked_until = $_SESSION[$lockout_key] ?? 0;
    $now = time();
    
    // Check if still in lockout period
    if ($locked_until > $now) {
        $remaining = ceil(($locked_until - $now) / 60); // Minutes remaining
        return ['allowed' => false, 'minutes_remaining' => $remaining];
    }
    
    // Lockout expired, reset everything
    if ($locked_until > 0 && $locked_until <= $now) {
        unset($_SESSION[$key], $_SESSION[$key . '_time'], $_SESSION[$lockout_key]);
        return ['allowed' => true, 'minutes_remaining' => 0];
    }
    
    // Reset counter if more than 15 minutes have passed since last attempt
    if ($now - $last_attempt > 900) {
        $_SESSION[$key] = 0;
        $_SESSION[$key . '_time'] = $now;
        return ['allowed' => true, 'minutes_remaining' => 0];
    }
    
    // Block after 5 attempts in 15 minutes (start 1-hour lockout)
    if ($attempts >= 5) {
        $_SESSION[$lockout_key] = $now + 3600; // 1 hour from now
        return ['allowed' => false, 'minutes_remaining' => 60];
    }
    
    return ['allowed' => true, 'minutes_remaining' => 0];
}

function record_login_attempt($identifier) {
    $key = 'login_attempts_' . md5($identifier);
    $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
    $_SESSION[$key . '_time'] = time();
}

function clear_login_attempts($identifier) {
    $key = 'login_attempts_' . md5($identifier);
    unset($_SESSION[$key], $_SESSION[$key . '_time'], $_SESSION[$key . '_locked_until']);
}

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
$user_id = null;
$user_created = null;
$email_verified = false;

// Database credentials (same as other files)
// Use environment variables for database credentials, fallback to defaults for dev
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_NAME = getenv('DB_NAME') ?: 'lokale-tjenester';
$DB_USER = getenv('DB_USER') ?: 'lokale-tjenester';
$DB_PASS = getenv('DB_PASS') ?: 'pwlt01!';

// Try to connect; fail quietly but log errors.
$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn && $conn->connect_error) {
    error_log('display.php: DB connect error: ' . $conn->connect_error);
} else if ($conn) {
    $conn->set_charset('utf8mb4');
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
    contact_info VARCHAR(255),
    user_id INT UNSIGNED,
    image MEDIUMBLOB DEFAULT NULL,
    image_type VARCHAR(50) DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY (user_id),
    KEY (category),
    KEY (created_at),
    KEY (status)
)");

// Add status column if it doesn't exist (for existing databases)
$conn->query("ALTER TABLE posts ADD COLUMN IF NOT EXISTS status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");

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

// Populate variables from session (now that session_start() has been called)
if(!empty($_SESSION['user_id'])){
    $is_logged_in = true;
    $user_id = intval($_SESSION['user_id']);
    $user_name = $_SESSION['user_name'] ?? 'User';
    $user_email = $_SESSION['user_email'] ?? '';
}

// ---- DEBUGGING: log every POST ----
error_log("=== display.php hit ===");
error_log("POST keys: " . implode(',', array_keys($_POST)));
error_log("SESSION keys: " . implode(',', array_keys($_SESSION)));
error_log("REMOTE_ADDR: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// Make sure $action exists
$action = $_POST['action'] ?? null;
error_log("Action received: " . var_export($action, true));

function send_verification_email(mysqli $conn, string $email, int $user_id): bool {
    error_log("[EMAIL] Starting send_verification_email for user_id=$user_id, email=$email");
    
    $token = bin2hex(random_bytes(32));
    error_log("[EMAIL] Generated token: " . substr($token, 0, 8) . "...");

    // Remove old tokens
    error_log("[EMAIL] Deleting old tokens for user_id=$user_id");
    $stmt = $conn->prepare("DELETE FROM email_tokens WHERE user_id=?");
    if (!$stmt) {
        error_log("[EMAIL] ERROR: Failed to prepare DELETE statement: " . $conn->error);
        return false;
    }
    $stmt->bind_param('i', $user_id);
    if (!$stmt->execute()) {
        error_log("[EMAIL] ERROR: Failed to execute DELETE: " . $stmt->error);
        $stmt->close();
        return false;
    }
    $stmt->close();
    error_log("[EMAIL] Old tokens deleted");

    // Insert new token (24 hour expiry)
    error_log("[EMAIL] Inserting new token into email_tokens table");
    $stmt = $conn->prepare(
        "INSERT INTO email_tokens (user_id, token, created_at) VALUES (?, ?, NOW())"
    );
    if (!$stmt) {
        error_log("[EMAIL] ERROR: Failed to prepare INSERT statement: " . $conn->error);
        return false;
    }
    $stmt->bind_param('is', $user_id, $token);
    if (!$stmt->execute()) {
        error_log("[EMAIL] ERROR: Failed to execute INSERT: " . $stmt->error);
        $stmt->close();
        return false;
    }
    $stmt->close();
    error_log("[EMAIL] Token inserted successfully");

    $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $verifyLink = "https://" . $domain . "/pages.php?page=verify&token=" . urlencode($token);
    error_log("[EMAIL] Verify link: " . substr($verifyLink, 0, 50) . "...");

    // Use PHP's built-in mail() function instead of PHPMailer
    $subject = 'Verifiser din e-postadresse - Lokale Tjenester';
    $fromEmail = 'noreply@lokale-tjenester.no';
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Lokale Tjenester <$fromEmail>\r\n";
    
    $htmlBody = "
        <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2>Verifiser din e-postadresse</h2>
                <p>Hei,</p>
                <p>Takk for at du registrerte deg på Lokale Tjenester UB. For å fullføre registreringen, vennligst verifiser din e-postadresse ved å klikke på lenken nedenfor:</p>
                <p><a href='$verifyLink' style='background-color: #2C8C42; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Verifiser e-postadresse</a></p>
                <p>Eller kopier og lim inn denne lenken i nettleseren din:</p>
                <p><code>$verifyLink</code></p>
                <p>Lenken utløper om 24 timer.</p>
                <p>Hvis du ikke opprettet denne kontoen, kan du ignorere denne e-posten.</p>
                <p>Med vennlig hilsen,<br/>Lokale Tjenester UB</p>
            </body>
        </html>
    ";
    
    error_log("[EMAIL] Attempting to send email using mail()...");
    $sent = @mail($email, $subject, $htmlBody, $headers);
    
    if ($sent) {
        error_log("[EMAIL] ✓ Email sent successfully to $email");
        return true;
    } else {
        error_log("[EMAIL] ✗ mail() returned false for $email");
        return false;
    }
}


function get_profile_picture_url(mysqli $conn, int $user_id): string {
    $stmt = safe_prepare($conn, "SELECT profile_picture, profile_picture_type FROM users WHERE id = ?");
    if(!$stmt) return ''; 
    
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    
    $imgData = null;
    $imgType = null;
    
    // Use get_result() for proper BLOB handling when available
    if(method_exists($stmt, 'get_result')){
        $res = $stmt->get_result();
        if($row = $res->fetch_assoc()){
            $imgData = $row['profile_picture'];
            $imgType = $row['profile_picture_type'];
        }
    } else {
        // Fallback for older MySQL drivers
        $stmt->bind_result($imgData, $imgType);
        $stmt->fetch();
    }
    
    $stmt->close();
    
    if(empty($imgData) || empty($imgType)) return '';
    
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
    // Start output buffering to prevent accidental output before JSON
    ob_start();
    
    // Log the incoming POST request for debugging (temporary)
    $post_log = date('c') . " - POST start: action=" . ($_POST['action'] ?? '(none)') . " method=" . ($_SERVER['REQUEST_METHOD'] ?? '') . " remote=" . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
    @file_put_contents(__DIR__ . '/debug_display_exec.log', $post_log, FILE_APPEND);

    $action = $_POST['action'] ?? null;

    // Clear any buffered output and set correct headers
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    
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
        if(!preg_match('/^[\p{L}0-9_\-\s]+$/u', $username)){ 
            echo json_encode(['status'=>'error','message'=>'Username may only contain letters, numbers, dash, underscore or spaces']); exit; 
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
        // CSRF validation
        if (!validate_csrf_token()) {
            echo json_encode(['status'=>'error','message'=>'Invalid request token']); exit;
        }
        
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember_me']);
        
        if(empty($username) || empty($password)){
            echo json_encode(['status'=>'error','message'=>'Username and password are required']); exit;
        }
        
        // Rate limiting: check login attempts for this username
        $rate_check = check_login_rate_limit($username);
        if (!$rate_check['allowed']) {
            $minutes = $rate_check['minutes_remaining'];
            if ($minutes > 1) {
                $msg = "Too many login attempts. Please try again in $minutes minutes.";
            } else {
                $msg = "Too many login attempts. Please try again in about 1 minute.";
            }
            echo json_encode(['status'=>'error','message'=>$msg]); exit;
        }
        
        $stmt = safe_prepare($conn, "SELECT id, username, email, password_hash FROM users WHERE username=? OR email=?");
        if($stmt){
            $stmt->bind_param('ss', $username, $username);
            $stmt->execute();
            debug_log("Login: query executed for username=$username");
            $stmt->bind_result($user_id, $user_name, $user_email, $password_hash);
            if($stmt->fetch()){
                debug_log("Login: user found, id=$user_id");
                if(password_verify($password, $password_hash)){
                    debug_log("Login: password correct");
                    // Clear rate limit on successful login
                    clear_login_attempts($username);
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['user_name'] = $user_name;
                    $_SESSION['user_email'] = $user_email;
                    
                    // If "remember me" is checked, create a token with expiry
                    if($remember){
                        $token = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days
                        $stmt2 = safe_prepare($conn, "INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                        if($stmt2){
                            $stmt2->bind_param('iss', $user_id, $token, $expires);
                            if($stmt2->execute()){
                                // Set secure cookie for 30 days (httponly, secure in production)
                                $isSecure = request_is_secure();
                                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', $isSecure, true);
                            }
                            $stmt2->close();
                        }
                    }
                    
                    echo json_encode(['status'=>'success','message'=>'Logged in successfully']);
                } else {
                    debug_log("Login: password incorrect");
                    record_login_attempt($username);
                    echo json_encode(['status'=>'error','message'=>'Invalid username or password']);
                }
            } else {
                debug_log("Login: user not found");
                record_login_attempt($username);
                echo json_encode(['status'=>'error','message'=>'Invalid username or password']);
            }
            $stmt->close();
        } else {
            debug_log("Login: DB error preparing statement");
            echo json_encode(['status'=>'error','message'=>'Database error']); exit;
        }
        exit;
    }

    if ($action === 'resend_verification') {
        error_log("[RESEND_VERIFY] Action triggered");
        
        try {
            $user_id = $_SESSION['user_id'] ?? null;
            error_log("[RESEND_VERIFY] User ID from session: " . ($user_id ? $user_id : "NULL"));
            
            if (!$user_id) {
                error_log("[RESEND_VERIFY] ERROR: No user_id in session");
                echo json_encode(['success'=>false,'message'=>'Not logged in']);
                exit;
            }

            // Fetch email from DB
            error_log("[RESEND_VERIFY] Querying database for email, user_id=$user_id");
            $stmt = $conn->prepare("SELECT email FROM users WHERE id=?");
            if (!$stmt) {
                error_log("[RESEND_VERIFY] ERROR: Failed to prepare statement: " . $conn->error);
                echo json_encode(['success'=>false,'message'=>'Database error: ' . $conn->error]);
                exit;
            }
            
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $user_email = $user['email'] ?? null;
            error_log("[RESEND_VERIFY] Email from DB: " . ($user_email ? $user_email : "NULL"));
            
            if (!$user_email) {
                error_log("[RESEND_VERIFY] ERROR: Email not found in database for user_id=$user_id");
                echo json_encode(['success'=>false,'message'=>'Email not found']);
                exit;
            }

            error_log("[RESEND_VERIFY] Calling send_verification_email for $user_email");
            $ok = send_verification_email($conn, $user_email, $user_id);
            error_log("[RESEND_VERIFY] send_verification_email returned: " . ($ok ? "TRUE" : "FALSE"));

            echo json_encode([
                'success' => $ok,
                'message' => $ok ? 'Verification email resent!' : 'Failed to send verification email'
            ]);
            exit;
        } catch (Exception $e) {
            error_log("[RESEND_VERIFY] EXCEPTION CAUGHT: " . $e->getMessage());
            echo json_encode(['success'=>false,'message'=>'Server error: ' . $e->getMessage()]);
            exit;
        }
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
            // CSRF validation
            if (!validate_csrf_token()) {
                echo json_encode(['status'=>'error','message'=>'Invalid request token']); exit;
            }
            
            // create a job/post supporting multiple images; convert uploads to web-friendly format (webp/jpeg)
            debug_log('=== CREATE_POST START ===');
            debug_log('PHP limits: upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size') . ', memory_limit=' . ini_get('memory_limit'));
            debug_log('POST data: title=' . ($_POST['title'] ?? 'MISSING') . ', desc=' . (strlen($_POST['description'] ?? '') > 0 ? 'OK' : 'MISSING') . ', FILES count=' . count($_FILES));
            debug_log('GD library available: ' . (extension_loaded('gd') ? 'YES' : 'NO'));
            debug_log('imagewebp function: ' . (function_exists('imagewebp') ? 'YES' : 'NO'));
            
            // Log file upload errors explicitly
            if(!empty($_FILES['image'])){
                if(is_array($_FILES['image']['error'])){
                    foreach($_FILES['image']['error'] as $idx => $err){
                        if($err !== UPLOAD_ERR_OK){
                            debug_log('File upload error at index ' . $idx . ': ' . getUploadErrorMessage($err));
                        }
                    }
                } elseif($_FILES['image']['error'] !== UPLOAD_ERR_OK){
                    debug_log('File upload error: ' . getUploadErrorMessage($_FILES['image']['error']) . ' (size=' . ($_FILES['image']['size'] ?? 0) . ' bytes)');
                }
            }
            debug_log('FILES[image]: ' . json_encode($_FILES['image'] ?? 'NOT SET'));
        
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $budget = intval($_POST['budget'] ?? 0);
            $location = trim($_POST['location'] ?? '');
            $contact_info = trim($_POST['contact_info'] ?? '');

            if(!$title || !$desc){ debug_log('create_post: Missing title or desc'); echo json_encode(['status'=>'error','message'=>'Title and description required']); exit; }
            if(!$contact_info){ debug_log('create_post: Missing contact info'); echo json_encode(['status'=>'error','message'=>'Contact information is required']); exit; }
            if(empty($_SESSION['user_id'])){ debug_log('create_post: No user_id in session'); echo json_encode(['status'=>'error','message'=>'You must be logged in to create a job']); exit; }
            $uid = intval($_SESSION['user_id']);

            // Check if user's email is verified - fetch directly from database
            $verifyStmt = safe_prepare($conn, "SELECT email_verified FROM users WHERE id = ? LIMIT 1");
            $user_verified = false;
            if($verifyStmt) {
                $verifyStmt->bind_param('i', $uid);
                $verifyStmt->execute();
                $verifyStmt->bind_result($db_verified);
                if($verifyStmt->fetch()) {
                    $user_verified = !empty($db_verified) ? true : false;
                }
                $verifyStmt->close();
            }
            
            if(!$user_verified){ debug_log('create_post: User ' . $uid . ' not verified'); echo json_encode(['status'=>'error','message'=>'Du må verifisere e-posten din før du kan publisere en jobb']); exit; }

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
                debug_log('create_post: User ' . $uid . ' exceeded daily post limit (' . $postsToday . '/' . $POST_LIMIT . ')');
                echo json_encode(['status'=>'error','message'=>'Du har nådd maksimalt antall poster (3) per dag. Prøv igjen i morgen!', 'remaining' => 0]);
                exit;
            }

            // Insert post record first (no image columns relied on here)
            $stmt = safe_prepare($conn, "INSERT INTO posts (title, description, category, budget, location, contact_info, user_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            if(!$stmt){ error_log('create_post: safe_prepare posts insert failed - ' . ($conn ? $conn->error : 'No connection')); echo json_encode(['status'=>'error','message'=>'Database error']); exit; }
            $stmt->bind_param('sssissi', $title, $desc, $category, $budget, $location, $contact_info, $uid);
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
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            
            if(isset($_FILES['image'])){
                if(is_array($_FILES['image']['name'])){
                    for($i=0;$i<count($_FILES['image']['name']);$i++){
                        // Skip if no file uploaded at this index
                        if($_FILES['image']['error'][$i] == UPLOAD_ERR_NO_FILE) continue;
                        // Validate MIME type against whitelist
                        $file_mime = mime_content_type($_FILES['image']['tmp_name'][$i] ?? '');
                        if (!in_array($file_mime, $allowed_mime_types)) {
                            debug_log('File upload rejected: invalid MIME type ' . $file_mime);
                            continue; // Skip invalid files
                        }
                        $files[] = [
                            'name' => $_FILES['image']['name'][$i] ?? '',
                            'tmp_name' => $_FILES['image']['tmp_name'][$i] ?? '',
                            'size' => $_FILES['image']['size'][$i] ?? 0,
                            'error' => $_FILES['image']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                        ];
                    }
                } else {
                    // Skip if no file uploaded
                    if($_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
                        debug_log('create_post: No file uploaded (optional)');
                    } else {
                        // Validate MIME type against whitelist
                        $file_mime = mime_content_type($_FILES['image']['tmp_name'] ?? '');
                        if (in_array($file_mime, $allowed_mime_types)) {
                            $files[] = [ 'name'=>$_FILES['image']['name'],'tmp_name'=>$_FILES['image']['tmp_name'],'size'=>$_FILES['image']['size'],'error'=>$_FILES['image']['error'] ];
                        } else {
                            debug_log('File upload rejected: invalid MIME type ' . $file_mime);
                        }
                    }
                }
            }

            // Helper: convert an uploaded file to webp (if available) or jpeg and return [data, type] or false
            $convertImage = function(string $tmpPath){
                debug_log('convertImage: reading ' . $tmpPath);
                $raw = @file_get_contents($tmpPath);
                if($raw === false){ debug_log('convertImage: file_get_contents failed'); return false; }
                debug_log('convertImage: raw file size=' . strlen($raw) . ' bytes');
                
                // Detect image type from magic bytes
                $img = null;
                if(strpos($raw, "\x89PNG") === 0){
                    debug_log('convertImage: detected PNG format');
                    $tempFile = tempnam(sys_get_temp_dir(), 'img_');
                    if(file_put_contents($tempFile, $raw)){
                        $img = @imagecreatefrompng($tempFile);
                        if(!$img) debug_log('convertImage: imagecreatefrompng failed');
                        else debug_log('convertImage: imagecreatefrompng SUCCESS');
                        @unlink($tempFile);
                    }
                } elseif(strpos($raw, "\xFF\xD8\xFF") === 0){
                    debug_log('convertImage: detected JPEG format');
                    $tempFile = tempnam(sys_get_temp_dir(), 'img_');
                    if(file_put_contents($tempFile, $raw)){
                        $img = @imagecreatefromjpeg($tempFile);
                        if(!$img) debug_log('convertImage: imagecreatefromjpeg failed');
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
            echo json_encode(['status'=>'success','message'=>'Job opprettet! Det avventer godkjenning fra admin før det vises offentlig.']);
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

        $sql = "SELECT p.id, p.title, p.description, p.category, p.budget, p.location, p.created_at, p.user_id, COALESCE(u.username,'Guest') AS username, IF(p.image, CONCAT('data:image/', COALESCE(p.image_type, 'jpeg'), ';base64,', TO_BASE64(p.image)), NULL) AS image, IF(u.profile_picture, CONCAT(COALESCE(u.profile_picture_type, 'image/jpeg'), ';base64,', TO_BASE64(u.profile_picture)), NULL) AS profile_picture FROM posts p LEFT JOIN users u ON p.user_id = u.id";
        $where = [];
        $types = '';
        $params = [];

        // Always filter for approved posts only
        $where[] = "p.status = 'approved'";

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
        
        $sql = "SELECT p.id, p.title, p.description, p.category, p.budget, p.location, p.contact_info, p.user_id, p.status, p.created_at, COALESCE(u.username,'Guest') AS username, IF(p.image, CONCAT('data:image/', COALESCE(p.image_type, 'jpeg'), ';base64,', TO_BASE64(p.image)), NULL) AS image FROM posts p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ? LIMIT 1";
        
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

        // Check permissions: only approved posts can be viewed by others; users can view their own posts regardless of status
        $currentUserId = $_SESSION['user_id'] ?? null;
        if($row['status'] !== 'approved' && $currentUserId != $row['user_id']){
            echo json_encode(['status'=>'error','message'=>'Post not available']); 
            exit;
        }

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

        // Fetch user profile picture if user_id is available
        $row['profile_picture'] = null;
        if(!empty($row['user_id'])) {
            $row['profile_picture'] = get_profile_picture_url($conn, $row['user_id']);
        }

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
        // CSRF validation
        if (!validate_csrf_token()) {
            echo json_encode(['status'=>'error','message'=>'Invalid request token']); exit;
        }
        
        // allow logged-in users to change settings: username, email, session duration
        if(empty($_SESSION['user_id'])){ echo json_encode(['status'=>'error','message'=>'Not authenticated']); exit; }
        $uid = intval($_SESSION['user_id']);
        
        // Authorization check: can only update own settings
        $target_user_id = intval($_POST['user_id'] ?? $uid);
        if ($target_user_id !== $uid && empty($_SESSION['is_admin'])) {
            echo json_encode(['status'=>'error','message'=>'Unauthorized: cannot modify other users']); exit;
        }
        
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
            $_SESSION['user_name'] = $username;
            $_SESSION['user_email'] = $email;
            echo json_encode(['status'=>'success','message'=>'Settings updated']); exit;
        } else { echo json_encode(['status'=>'error','message'=>'Database error']); exit; }
    }

    if($action === 'change_password'){
        // CSRF validation
        if (!validate_csrf_token()) {
            echo json_encode(['status'=>'error','message'=>'Invalid request token']); exit;
        }
        
        // Check authentication
        if(empty($_SESSION['user_id'])){ echo json_encode(['status'=>'error','message'=>'Not authenticated']); exit; }
        $uid = intval($_SESSION['user_id']);
        
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate inputs
        if(!$current_password){ echo json_encode(['status'=>'error','message'=>'Current password is required']); exit; }
        if(!$new_password){ echo json_encode(['status'=>'error','message'=>'New password is required']); exit; }
        if($new_password !== $confirm_password){ echo json_encode(['status'=>'error','message'=>'Passwords do not match']); exit; }
        if(strlen($new_password) < 6){ echo json_encode(['status'=>'error','message'=>'Password must be at least 6 characters']); exit; }
        
        // Get current password hash
        $stmt = safe_prepare($conn, "SELECT password_hash FROM users WHERE id=? LIMIT 1");
        if(!$stmt){ echo json_encode(['status'=>'error','message'=>'Database error']); exit; }
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->bind_result($password_hash);
        if(!$stmt->fetch()) { 
            $stmt->close();
            echo json_encode(['status'=>'error','message'=>'User not found']); exit; 
        }
        $stmt->close();
        
        // Verify current password
        if(!password_verify($current_password, $password_hash)) {
            echo json_encode(['status'=>'error','message'=>'Current password is incorrect']); exit;
        }
        
        // Hash new password
        $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
        
        // Update password
        $stmt = safe_prepare($conn, "UPDATE users SET password_hash=? WHERE id=?");
        if(!$stmt){ echo json_encode(['status'=>'error','message'=>'Database error']); exit; }
        $stmt->bind_param('si', $new_hash, $uid);
        if(!$stmt->execute()){ 
            echo json_encode(['status'=>'error','message'=>'Failed to update password']); exit; 
        }
        $stmt->close();
        
        echo json_encode(['status'=>'success','message'=>'Password updated successfully']); 
        exit;
    }

    if($action === 'upload_profile_picture'){
        // CSRF validation
        if (!validate_csrf_token()) {
            echo json_encode(['status'=>'error','message'=>'Invalid request token']); exit;
        }
        
        // Handle profile picture upload
        if(empty($_SESSION['user_id'])){ echo json_encode(['status'=>'error','message'=>'Not authenticated']); exit; }
        
        if(empty($_FILES['profile_picture'])){ echo json_encode(['status'=>'error','message'=>'No file uploaded']); exit; }
        
        $file = $_FILES['profile_picture'];
        $uid = intval($_SESSION['user_id']);
        
        // Validate MIME type
        $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_mime = mime_content_type($file['tmp_name']);
        if (!in_array($file_mime, $allowed_mime)) {
            echo json_encode(['status'=>'error','message'=>'Invalid image format. Allowed: JPEG, PNG, GIF, WebP']); 
            exit;
        }
        
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
        
        $null = NULL;
        $stmt->bind_param('bi', $null, $uid);
        if(!$stmt->send_long_data(0, $imgData)){ 
            echo json_encode(['status'=>'error','message'=>'Failed to send image data']); 
            exit; 
        }
        
        if(!$stmt->execute()){ 
            echo json_encode(['status'=>'error','message'=>'Failed to save profile picture']); 
            exit; 
        }
        
        echo json_encode(['status'=>'success','message'=>'Profile picture updated']);
        $stmt->close();
        exit;
    }

    if($action === 'contact'){
        // CSRF validation
        if (!validate_csrf_token()) {
            echo json_encode(['status'=>'error','message'=>'Invalid request token']); exit;
        }
        
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
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                    <div id="loginMsg" style="color:red;margin-bottom:8px" aria-live="polite"></div>
                    <input type="text" name="username" placeholder="Username or email" required style="display:block;margin:8px 0;padding:8px;width:100%">
                    <input type="password" name="password" placeholder="Password" required style="display:block;margin:8px 0;padding:8px;width:100%">
                    <button type="submit">Login</button>
                </form>
                <?php else: ?>
                <form id="signupForm">
                    <input type="hidden" name="action" value="signup">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
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

