<?php
/**
 * staging/password_reset.php
 * Prototype 'forgot password' flow.
 * - POST action 'request_reset' with email: generates token and stores it (DB if column exists, otherwise session), logs reset URL.
 * - GET with token+uid: renders reset form.
 * - POST action 'reset_password' with token+uid+new_password: sets a new password (DB if users.password_hash exists else session fallback).
 * This is for staging/dev only and is non-destructive unless DB update paths are used intentionally.
 */

require_once __DIR__ . '/../display.php';
require_once __DIR__ . '/schema_helper.php';

// optional shared mailer
if(is_file(__DIR__ . '/../lib/mailer.php')) require_once __DIR__ . '/../lib/mailer.php';

// Helper: find user by email
function staging_find_user_by_email($conn, $email){
    $stmt = safe_prepare($conn, "SELECT id, username, email FROM users WHERE email = ? LIMIT 1");
    if(!$stmt) return null;
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($id, $username, $em);
    $stmt->fetch();
    $stmt->close();
    if($id) return ['id'=>$id,'username'=>$username,'email'=>$em];
    return null;
}

// POST handlers
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';
    if($action === 'request_reset'){
        $email = trim($_POST['email'] ?? '');
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['status'=>'error','message'=>'Invalid email']); exit; }
        $user = staging_find_user_by_email($conn, $email);
        if(!$user){ echo json_encode(['status'=>'error','message'=>'No user found']); exit; }

        try { $token = bin2hex(random_bytes(16)); } catch (Exception $e) { $token = bin2hex(openssl_random_pseudo_bytes(16)); }
        $uid = intval($user['id']);

        // store token in DB column 'reset_token' if exists, otherwise session
        if(staging_column_exists($conn,'users','reset_token')){
            $stmt = safe_prepare($conn, "UPDATE users SET reset_token = ? WHERE id = ?");
            if($stmt){ $stmt->bind_param('si', $token, $uid); $stmt->execute(); $stmt->close(); }
        } else {
            if(!isset($_SESSION['staging_password_resets'])) $_SESSION['staging_password_resets'] = [];
            $_SESSION['staging_password_resets'][$uid] = $token;
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $resetUrl = $proto . '://' . $host . dirname($_SERVER['SCRIPT_NAME']) . '/password_reset.php?token=' . rawurlencode($token) . '&uid=' . $uid;
        @file_put_contents(__DIR__ . '/../debug_display_exec.log', date('c') . " - password reset link for user {$uid}: {$resetUrl}\n", FILE_APPEND);

        // log reset URL for staging/debug
        @file_put_contents(__DIR__ . '/../debug_display_exec.log', date('c') . " - reset link for user {$uid}: {$resetUrl}\n", FILE_APPEND);
        $sent = false; $mailErr = null;
        if(function_exists('fh_send_mail')){
          // if we have the user's email (we do, from staging_find_user_by_email), attempt to send
          $to = $user['email'] ?? null;
          if($to && filter_var($to, FILTER_VALIDATE_EMAIL)){
            list($ok, $err) = fh_send_mail($to, 'Finn Hustle password reset', "Reset your password by visiting: $resetUrl\n\nIf you didn't request this, ignore this email.");
            $sent = $ok; $mailErr = $err;
            @file_put_contents(__DIR__ . '/../debug_display_exec.log', date('c') . " - password reset send for {$to}: " . json_encode([$ok,$err]) . "\n", FILE_APPEND);
          }
        }
        // respond with staged info plus email result
        echo json_encode(['status'=>'success','message'=>'Reset generated','reset_url'=>$resetUrl,'email_sent'=>$sent,'email_error'=>$mailErr]);
        exit;
        echo json_encode(['status'=>'success','message'=>'Reset link generated','reset_url'=>$resetUrl]); exit;
    }

    if($action === 'reset_password'){
        $token = $_POST['token'] ?? '';
        $uid = intval($_POST['uid'] ?? 0);
        $newpw = $_POST['new_password'] ?? '';
        if($uid <= 0 || !$token || strlen($newpw) < 6){ echo json_encode(['status'=>'error','message'=>'Invalid request or password too short']); exit; }

        $valid = false;
        if(staging_column_exists($conn,'users','reset_token')){
            $stmt = safe_prepare($conn, "SELECT id FROM users WHERE id = ? AND reset_token = ? LIMIT 1");
            if($stmt){ $stmt->bind_param('is', $uid, $token); $stmt->execute(); $stmt->store_result(); if($stmt->num_rows === 1) $valid = true; $stmt->close(); }
        } else {
            $stored = $_SESSION['staging_password_resets'][$uid] ?? null;
            if($stored && hash_equals($stored, $token)) $valid = true;
        }

        if(!$valid){ echo json_encode(['status'=>'error','message'=>'Invalid or expired token']); exit; }

        // Update password in DB if possible, else store in session (demo)
        $hash = password_hash($newpw, PASSWORD_BCRYPT);
        if(staging_column_exists($conn,'users','password_hash')){
            $stmt = safe_prepare($conn, "UPDATE users SET password_hash = ?, reset_token = NULL WHERE id = ?");
            if($stmt){ $stmt->bind_param('si', $hash, $uid); $ok = $stmt->execute(); $stmt->close(); if($ok) echo json_encode(['status'=>'success','message'=>'Password updated']); else echo json_encode(['status'=>'error','message'=>'Failed to update']); }
            else echo json_encode(['status'=>'error','message'=>'DB error']);
            exit;
        }

        // session fallback: store hashed password (demo only)
        if(!isset($_SESSION['staging_passwords'])) $_SESSION['staging_passwords'] = [];
        $_SESSION['staging_passwords'][$uid] = $hash;
        unset($_SESSION['staging_password_resets'][$uid]);
        echo json_encode(['status'=>'success','message'=>'Password stored in session (demo)']); exit;
    }

    echo json_encode(['status'=>'error','message'=>'Unknown action']); exit;
}

// GET: render reset form if token+uid present, else show request form
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html>
<head><meta charset="utf-8"><title>Password Reset (Staging)</title></head>
<body>
  <h1>Password Reset (Staging)</h1>
  <div>
    <?php if(isset($_GET['token']) && isset($_GET['uid'])): ?>
      <form id="resetForm">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
        <input type="hidden" name="uid" value="<?php echo intval($_GET['uid']); ?>">
        <label>New password: <input type="password" name="new_password" required></label>
        <button type="submit">Set new password</button>
      </form>
      <pre id="result"></pre>
      <script>
      document.getElementById('resetForm').addEventListener('submit', async function(e){
        e.preventDefault(); const fd = new FormData(e.target);
        const res = await fetch('password_reset.php',{method:'POST', body: fd, credentials:'same-origin'});
        const j = await res.json(); document.getElementById('result').textContent = JSON.stringify(j,null,2);
      });
      </script>
    <?php else: ?>
      <form id="requestForm">
        <input type="hidden" name="action" value="request_reset">
        <label>Email: <input type="email" name="email" required></label>
        <button type="submit">Request reset</button>
      </form>
      <pre id="result"></pre>
      <script>
      document.getElementById('requestForm').addEventListener('submit', async function(e){
        e.preventDefault(); const fd = new FormData(e.target); const res = await fetch('password_reset.php',{method:'POST', body: fd, credentials:'same-origin'}); const j = await res.json(); document.getElementById('result').textContent = JSON.stringify(j,null,2);
      });
      </script>
    <?php endif; ?>
  </div>
</body>
</html>
