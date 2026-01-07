<?php
// Temporary debug log to help diagnose 500 errors when browser shows no response.
// The webserver must be able to write files in this directory for these logs to appear.
@file_put_contents(__DIR__ . '/debug_display_exec.log', date('c') . " - display.php loaded\n", FILE_APPEND);
// Maximum supported session lifetime (seconds) - 60 days
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Database credentials (same as other files)
$DB_HOST = 'localhost';
$DB_NAME = 'DB';
$DB_USER = 'pyx';
$DB_PASS = 'admin';

// Try to connect; fail quietly but log errors.
//connect (fail quietly)
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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/vendor/autoload.php';

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

    $verifyLink = "https://yourdomain.com/pages.php?page=verify&token=" . urlencode($token);

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your_smtp_user';
        $mail->Password = 'your_smtp_pass';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('no-reply@yourdomain.com', 'Finn Hustle');
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



// AJAX POST handlers for login/signup (mirrors the backup behavior)
// Only handle AJAX-style POST actions when this file is the requested endpoint
// (i.e. avoid intercepting POSTs intended for pages that `require_once 'display.php'`).
if(realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])){
    // Log the incoming POST request for debugging (temporary)
    $post_log = date('c') . " - POST start: action=" . ($_POST['action'] ?? '(none)') . " method=" . ($_SERVER['REQUEST_METHOD'] ?? '') . " remote=" . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
    @file_put_contents(__DIR__ . '/debug_display_exec.log', $post_log, FILE_APPEND);

    $action = trim(strtolower($_POST['action'] ?? ''));

    header('Content-Type: application/json');
    // Also log POST keys and cookies briefly
    @file_put_contents(__DIR__ . '/debug_display_exec.log', date('c') . " - POST keys: " . implode(',', array_keys($_POST)) . "\n", FILE_APPEND);
    @file_put_contents(__DIR__ . '/debug_display_exec.log', date('c') . " - COOKIES: " . json_encode(array_keys($_COOKIE)) . "\n", FILE_APPEND);

    if($action === 'signup'){
        $username = trim($_POST['username'] ?? '');
        $user_email = trim($_POST['email'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if(send_verification_email($conn, $user_email, $new_id)){
            echo json_encode(['status'=>'success','message'=>'Signup successful! Verification email sent.']);
        } else {
            echo json_encode(['status'=>'success','message'=>'Signup successful but failed to send verification email.']);
        }
        exit;

        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            echo json_encode(['status'=>'error','message'=>'Invalid email']); exit;
        }
        if(strlen($username) < 3 || strlen($username) > 32){ echo json_encode(['status'=>'error','message'=>'Username must be 3-32 characters']); exit; }
        if(!preg_match('/^[A-Za-z0-9_\-]+$/', $username)){ echo json_encode(['status'=>'error','message'=>'Username may only contain letters, numbers, dash or underscore']); exit; }
        if(strlen($password) < 6){ echo json_encode(['status'=>'error','message'=>'Password must be at least 6 characters']); exit; }

        $stmt = safe_prepare($conn, "SELECT id FROM users WHERE username=? OR email=?");
        if($stmt){
            $stmt->bind_param('ss', $username, $email);
            $stmt->execute();
            $stmt->store_result();
            if($stmt->num_rows > 0){ echo json_encode(['status'=>'error','message'=>'Username or email exists']); exit; }
            $stmt->close();
        } else { echo json_encode(['status'=>'error','message'=>'Database error']); exit; }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = safe_prepare($conn, "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        if($stmt){
            $stmt->bind_param('sss', $username, $email, $hash);
            if(!$stmt->execute()){ echo json_encode(['status'=>'error','message'=>'DB insert failed']); exit; }
            $new_id = $conn->insert_id;
            session_regenerate_id(true);
            $_SESSION['user_id'] = $new_id;

            // FIX: set $user_email for email sending
            $user_email = $email;

            // Send verification email
            if(send_verification_email($conn, $user_email, $new_id)){
                echo json_encode(['status'=>'success','message'=>'Signup successful! Verification email sent.']);
            } else {
                echo json_encode(['status'=>'success','message'=>'Signup successful but failed to send verification email.']);
            }
            exit;
        } else { echo json_encode(['status'=>'error','message'=>'Database error']); exit; }
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

    if($action === 'create_post'){
        // create a job/post
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $budget = intval($_POST['budget'] ?? 0);
        $location = trim($_POST['location'] ?? '');

        if(!$title || !$desc){ echo json_encode(['status'=>'error','message'=>'Title and description required']); exit; }

        $stmt = safe_prepare($conn, "INSERT INTO posts (title, description, category, budget, location, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if($stmt){
            $uid = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
            $stmt->bind_param('sssisi', $title, $desc, $category, $budget, $location, $uid);
            if(!$stmt->execute()){ echo json_encode(['status'=>'error','message'=>'Failed to create job']); exit; }
            echo json_encode(['status'=>'success','message'=>'Job created']); exit;
        } else {
            echo json_encode(['status'=>'error','message'=>'Database error creating job']); exit;
        }
    }

    if($action === 'list_jobs'){
        // list jobs with optional filters
        $q = trim($_POST['q'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $min = isset($_POST['min_budget']) ? intval($_POST['min_budget']) : null;
        $max = isset($_POST['max_budget']) ? intval($_POST['max_budget']) : null;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;

        $sql = "SELECT p.id, p.title, p.description, p.category, p.budget, p.location, p.created_at, COALESCE(u.username,'Guest') AS username FROM posts p LEFT JOIN users u ON p.user_id = u.id";
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
        if(!$stmt){ echo json_encode(['status'=>'error','message'=>'DB error preparing list']); exit; }
        if($types !== ''){
            // bind dynamically
            $bindNames = [];
            $bindNames[] = $types;
            foreach($params as $i => $v) $bindNames[] = &$params[$i];
            call_user_func_array([$stmt, 'bind_param'], $bindNames);
        }
        $stmt->execute();
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
        echo json_encode(['status'=>'success','jobs'=>$rows]); exit;
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
        // ignore
        $has_is_admin = false;
    }

    if($has_is_admin){
        $stmt = safe_prepare($conn, "SELECT username, email, created_at, COALESCE(is_admin,0) AS is_admin FROM users WHERE id = ? LIMIT 1");
    } else {
        $stmt = safe_prepare($conn, "SELECT username, email, created_at FROM users WHERE id = ? LIMIT 1");
    }
    if($stmt){
        $sessUid = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $stmt->bind_param('i', $sessUid);
        $stmt->execute();
        if($has_is_admin){
            $stmt->bind_result($username, $email, $created_at, $is_admin_flag);
            $stmt->fetch();
            $is_admin = !empty($is_admin_flag) ? true : false;
        } else {
            $stmt->bind_result($username, $email, $created_at);
            $stmt->fetch();
            $is_admin = false;
        }

        if($username){
            $is_logged_in = true;
            $user_name = $username;
            $user_email = $email;
            $user_created = $created_at;
        }
        // Grant admin to protected runtime users (no DB changes needed)
        $protected_runtime_admins = array('pyxis', 'adminpyx', 'kentanto65');
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
            <title><?php echo ($act==='login') ? 'Login' : 'Sign Up'; ?> — Finn Hustle</title>
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

