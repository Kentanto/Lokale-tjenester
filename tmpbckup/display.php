<?php
// Maximum supported session lifetime (seconds) - 60 days
define('FH_MAX_SESSION', 60 * 24 * 3600); // 5184000
// Default per-user session (seconds) - 7 days
define('FH_DEFAULT_SESSION', 7 * 24 * 3600); // 604800

// Configure session GC and cookie parameters to accommodate long sessions.
ini_set('session.gc_maxlifetime', FH_MAX_SESSION);
session_set_cookie_params([
    'lifetime' => FH_MAX_SESSION,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Defaults exposed to pages that `require_once 'display.php'.
$is_logged_in = false;
$user_name = "Guest";
$user_email = '';
$user_created = null;
// expose user-selected session duration (seconds)
$user_session_duration = FH_DEFAULT_SESSION;

// Database credentials (same as other files)
$DB_HOST = 'localhost';
$DB_NAME = 'DB';
$DB_USER = 'pyx';
$DB_PASS = 'admin';

// Try to connect; fail quietly but log errors.
// connect (fail quietly)
$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn && $conn->connect_error) {
    error_log('display.php: DB connect error: ' . $conn->connect_error);
}

function safe_prepare($conn, $sql){
    $stmt = @$conn->prepare($sql);
    if(!$stmt){
        error_log('display.php: prepare failed: ' . $conn->error . ' -- SQL: ' . $sql);
        return null;
    }
    return $stmt;
}

// Logout via GET (link uses display.php?action=logout)
if(isset($_GET['action']) && $_GET['action'] === 'logout'){
    session_unset();
    session_destroy();
    // If requested via XHR, return JSON; otherwise redirect back to home.
    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'){
        header('Content-Type: application/json');
        echo json_encode(['status'=>'success','message'=>'Logged out']);
        exit;
    }
    header('Location: index.php');
    exit;
}

// AJAX POST handlers for login/signup (mirrors the backup behavior)
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])){
    $action = $_POST['action'];
    header('Content-Type: application/json');

    if($action === 'signup'){
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

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
        $stmt = safe_prepare($conn, "INSERT INTO users (username, email, password_hash, session_duration) VALUES (?, ?, ?, ?)");
        if($stmt){
            $default_dur = FH_DEFAULT_SESSION;
            $stmt->bind_param('sssi', $username, $email, $hash, $default_dur);
            if(!$stmt->execute()){ echo json_encode(['status'=>'error','message'=>'DB insert failed']); exit; }
            $new_id = $conn->insert_id;
            session_regenerate_id(true);
            $_SESSION['user_id'] = $new_id;
            $_SESSION['expires_at'] = time() + $default_dur;
            // update client cookie expiry
            setcookie(session_name(), session_id(), time() + $default_dur, '/', '', isset($_SERVER['HTTPS']), true);
            echo json_encode(['status'=>'success','message'=>'Signup successful']);
            exit;
        } else { echo json_encode(['status'=>'error','message'=>'Database error']); exit; }
    }

    if($action === 'login'){
        $identifier = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        // read session_duration so we can apply it on successful login
        $stmt = safe_prepare($conn, "SELECT id, password_hash, COALESCE(session_duration, ?) AS session_duration FROM users WHERE username=? OR email=? LIMIT 1");
        if($stmt){
            $stmt->bind_param('iss', FH_DEFAULT_SESSION, $identifier, $identifier);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($id, $hash, $session_duration);
            $stmt->fetch();
            if($stmt->num_rows === 1 && password_verify($password, $hash)){
                session_regenerate_id(true);
                $_SESSION['user_id'] = $id;
                $dur = intval($session_duration) ?: FH_DEFAULT_SESSION;
                $_SESSION['expires_at'] = time() + $dur;
                setcookie(session_name(), session_id(), time() + $dur, '/', '', isset($_SERVER['HTTPS']), true);
                echo json_encode(['status'=>'success','message'=>'Login successful']);
                exit;
            } else {
                echo json_encode(['status'=>'error','message'=>'Invalid credentials']); exit;
            }
        } else { echo json_encode(['status'=>'error','message'=>'Database error']); exit; }
    }

    if($action === 'logout'){
        session_unset();
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        echo json_encode(['status'=>'success','message'=>'Logged out']);
        exit;
    }

    if($action === 'update_settings'){
        // allow logged-in users to change settings: username, email, session duration
        if(empty($_SESSION['user_id'])){ echo json_encode(['status'=>'error','message'=>'Not authenticated']); exit; }
        $uid = intval($_SESSION['user_id']);
        
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $dur = intval($_POST['session_duration'] ?? 0);
        
        // Validate inputs
        if(!$username){ echo json_encode(['status'=>'error','message'=>'Username cannot be empty']); exit; }
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){ echo json_encode(['status'=>'error','message'=>'Invalid email']); exit; }
        if(strlen($username) < 3 || strlen($username) > 32){ echo json_encode(['status'=>'error','message'=>'Username must be 3-32 characters']); exit; }
        if(!preg_match('/^[A-Za-z0-9_\-]+$/', $username)){ echo json_encode(['status'=>'error','message'=>'Username may only contain letters, numbers, dash or underscore']); exit; }
        
        // clamp session duration: minimum 4 hours, maximum FH_MAX_SESSION
        $min = 4 * 3600; // 4 hours
        if($dur < $min) $dur = $min;
        if($dur > FH_MAX_SESSION) $dur = FH_MAX_SESSION;
        
        // Check if new username/email already exists for OTHER users
        $stmt = safe_prepare($conn, "SELECT id FROM users WHERE (username=? OR email=?) AND id != ?");
        if($stmt){
            $stmt->bind_param('ssi', $username, $email, $uid);
            $stmt->execute();
            $stmt->store_result();
            if($stmt->num_rows > 0){ echo json_encode(['status'=>'error','message'=>'Username or email already taken']); exit; }
            $stmt->close();
        } else { echo json_encode(['status'=>'error','message'=>'Database error']); exit; }
        
        // Update all three fields
        $stmt = safe_prepare($conn, "UPDATE users SET username=?, email=?, session_duration=? WHERE id=?");
        if($stmt){
            $stmt->bind_param('ssii', $username, $email, $dur, $uid);
            if(!$stmt->execute()){ echo json_encode(['status'=>'error','message'=>'Failed to update settings']); exit; }
            // update session variables to reflect new values
            $_SESSION['user_id'] = $uid;
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
if(isset($_SESSION['user_id']) && $conn){
    $stmt = safe_prepare($conn, "SELECT username, email, created_at, COALESCE(session_duration, ?) AS session_duration FROM users WHERE id = ? LIMIT 1");
    if($stmt){
        $default = FH_DEFAULT_SESSION;
        $stmt->bind_param('ii', $default, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->bind_result($username, $email, $created_at, $session_duration);
        $stmt->fetch();

        // Enforce a fixed (max) session lifetime. Do NOT refresh expiry on each request.
        $now = time();
        if(!empty($_SESSION['expires_at']) && $now > intval($_SESSION['expires_at'])){
            // expired server-side
            session_unset();
            session_destroy();
        } else if($username){
            // active session - expose user info but do not slide expiry
            $is_logged_in = true;
            $user_name = $username;
            $user_email = $email;
            $user_created = $created_at;
            $user_session_duration = intval($session_duration) ?: FH_DEFAULT_SESSION;
            // If an older session lacks expires_at, set it now as a one-time max lifetime
            if(empty($_SESSION['expires_at'])){
                $_SESSION['expires_at'] = $now + $user_session_duration;
                setcookie(session_name(), session_id(), time() + $user_session_duration, '/', '', isset($_SERVER['HTTPS']), true);
            }
            // NOTE: we intentionally do NOT update $_SESSION['expires_at'] on subsequent requests.
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

