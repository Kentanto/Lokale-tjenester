<?php
/**
 * staging/email_verification.php
 * Prototype email verification flow.
 * - Generates a verification token for the logged-in user
 * - Stores token in DB `users.verify_token` if column exists, otherwise falls back to session
 * - Returns a simulated verification email (verification URL) and logs it to debug log
 */

require_once __DIR__ . '/../display.php';
require_once __DIR__ . '/schema_helper.php';
// optional shared mailer
if(is_file(__DIR__ . '/../lib/mailer.php')) require_once __DIR__ . '/../lib/mailer.php';

// Simple routing: GET to show the page for testing, POST to request/resend
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    header('Content-Type: application/json; charset=utf-8');
    if(empty($_SESSION['user_id'])){ echo json_encode(['status'=>'error','message'=>'Not authenticated']); exit; }
    $uid = intval($_SESSION['user_id']);

    $action = $_POST['action'] ?? '';
    if($action !== 'request_verification' && $action !== 'resend_verification'){
        echo json_encode(['status'=>'error','message'=>'Unknown action']); exit;
    }

    // Create token
    try { $token = bin2hex(random_bytes(16)); } catch (Exception $e) { $token = bin2hex(openssl_random_pseudo_bytes(16)); }

    // Store token
    if(staging_column_exists($conn, 'users', 'verify_token')){
        $stmt = safe_prepare($conn, "UPDATE users SET verify_token = ?, email_verified = 0 WHERE id = ?");
        if($stmt){ $stmt->bind_param('si', $token, $uid); $stmt->execute(); $stmt->close(); }
    } else {
        if(!isset($_SESSION['staging_verifications'])) $_SESSION['staging_verifications'] = [];
        $_SESSION['staging_verifications'][$uid] = $token;
    }

    // Build verification URL
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $verifyUrl = $proto . '://' . $host . dirname($_SERVER['SCRIPT_NAME']) . '/email_verification.php?token=' . rawurlencode($token) . '&uid=' . $uid;

    // Log simulated email to debug log so ops can copy the link
    @file_put_contents(__DIR__ . '/../debug_display_exec.log', date('c') . " - verification link for user {$uid}: {$verifyUrl}\n", FILE_APPEND);
    $mailResult = null;
    // If shared mailer is available, attempt to send a verification email to the user
    $sent = false;
    if(function_exists('fh_send_mail')){
        // fetch email for user
        $stmt = safe_prepare($conn, "SELECT email FROM users WHERE id = ? LIMIT 1");
        if($stmt){
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $stmt->bind_result($targetEmail);
            $stmt->fetch();
            $stmt->close();
            if(!empty($targetEmail) && filter_var($targetEmail, FILTER_VALIDATE_EMAIL)){
                list($ok, $err) = fh_send_mail($targetEmail, 'Verify your Finn Hustle account', "Please verify your account by visiting: $verifyUrl\n\nIf you didn't request this, ignore this email.");
                $sent = $ok;
                $mailResult = $err;
                @file_put_contents(__DIR__ . '/../debug_display_exec.log', date('c') . " - email send result for user {$uid}: " . json_encode([$ok,$err]) . "\n", FILE_APPEND);
            }
        }
    }

    // Return response (include verification_url for staging convenience)
    $resp = ['status'=>'success','message'=>'Verification generated','verification_url'=>$verifyUrl];
    if($sent) $resp['email_sent'] = true; else $resp['email_sent'] = false;
    if($mailResult) $resp['email_error'] = $mailResult;
    echo json_encode($resp);
    exit;
}

// GET: verify token or render test page
if($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token']) && isset($_GET['uid'])){
    $token = $_GET['token'];
    $uid = intval($_GET['uid']);

    // Check DB first
    $verified = false;
    if(staging_column_exists($conn,'users','verify_token')){
        $stmt = safe_prepare($conn, "SELECT id FROM users WHERE id = ? AND verify_token = ? LIMIT 1");
        if($stmt){ $stmt->bind_param('is',$uid,$token); $stmt->execute(); $stmt->store_result(); if($stmt->num_rows === 1){
            $stmt->close(); $u = $uid; $stmt2 = safe_prepare($conn, "UPDATE users SET email_verified = 1, verify_token = NULL WHERE id = ?"); if($stmt2){ $stmt2->bind_param('i',$u); $stmt2->execute(); $stmt2->close(); $verified = true; }
        } else { $stmt->close(); }}
    } else {
        $sv = $_SESSION['staging_verifications'][$uid] ?? null;
        if($sv && hash_equals($sv, $token)){
            // mark in session
            $_SESSION['staging_verifications'][$uid . '_verified'] = true;
            $verified = true;
            unset($_SESSION['staging_verifications'][$uid]);
        }
    }

    header('Content-Type: text/html; charset=utf-8');
    ?><!doctype html><html><head><meta charset="utf-8"><title>Verify</title></head><body><?php
    if($verified) echo '<h1>Verification successful</h1><p>Your email is now verified (staging).</p>';
    else echo '<h1>Verification failed</h1><p>Invalid or expired token.</p>';
    echo '</body></html>';
    exit;
}

// Otherwise render a small test page that triggers a POST when clicking 'send'
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html>
<head><meta charset="utf-8"><title>Email Verification (Staging)</title></head>
<body>
  <h1>Email Verification (Staging)</h1>
  <?php if(empty($_SESSION['user_id'])): ?>
    <p>Please log in on the main site first.</p>
  <?php else: ?>
    <p>User: <?php echo htmlspecialchars($user_name); ?></p>
    <form id="sendForm" method="post">
      <input type="hidden" name="action" value="request_verification">
      <label>Email (optional): <input name="email" value="<?php echo htmlspecialchars($user_email); ?>"></label>
      <button type="submit">Send verification</button>
    </form>
    <div id="result"></div>
    <script>
    document.getElementById('sendForm').addEventListener('submit', async function(e){
        e.preventDefault();
        const fd = new FormData(e.target);
        const res = await fetch('email_verification.php',{method:'POST', body: fd, credentials:'same-origin'});
        const j = await res.json(); document.getElementById('result').textContent = JSON.stringify(j, null, 2);
    });
    </script>
  <?php endif; ?>
</body>
</html>
