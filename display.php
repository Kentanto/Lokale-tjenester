<?php
session_start();

// Defaults exposed to pages that `require_once 'display.php'`.
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
        $stmt = safe_prepare($conn, "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        if($stmt){
            $stmt->bind_param('sss', $username, $email, $hash);
            if(!$stmt->execute()){ echo json_encode(['status'=>'error','message'=>'DB insert failed']); exit; }
            $new_id = $conn->insert_id;
            $_SESSION['user_id'] = $new_id;
            echo json_encode(['status'=>'success','message'=>'Signup successful']);
            exit;
        } else { echo json_encode(['status'=>'error','message'=>'Database error']); exit; }
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
                $_SESSION['user_id'] = $id;
                echo json_encode(['status'=>'success','message'=>'Login successful']);
                exit;
            } else {
                echo json_encode(['status'=>'error','message'=>'Invalid credentials']); exit;
            }
        } else { echo json_encode(['status'=>'error','message'=>'Database error']); exit; }
    }

    if($action === 'logout'){
        session_destroy();
        echo json_encode(['status'=>'success','message'=>'Logged out']);
        exit;
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
    $stmt = safe_prepare($conn, "SELECT username, email, created_at FROM users WHERE id = ? LIMIT 1");
    if($stmt){
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $stmt->bind_result($username, $email, $created_at);
        $stmt->fetch();
        if($username){ $is_logged_in = true; $user_name = $username; $user_email = $email; $user_created = $created_at; }
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

