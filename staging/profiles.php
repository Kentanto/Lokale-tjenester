<?php
/**
 * staging/profiles.php
 * Simple profile bio editor prototype.
 * - If the `users` table has a `bio` column, this will attempt to update it (requires DB rights).
 * - Otherwise it falls back to storing the bio in `$_SESSION['staging_bio']` for demo purposes.
 * This file is safe for staging and will not perform destructive schema changes.
 */

require_once __DIR__ . '/../display.php';
require_once __DIR__ . '/schema_helper.php';
header('Content-Type: text/html; charset=utf-8');

// Accept AJAX POST to save bio
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_bio'){
    header('Content-Type: application/json');
    if(empty($_SESSION['user_id'])){
        echo json_encode(['status'=>'error','message'=>'Not authenticated']); exit;
    }
    $bio = trim($_POST['bio'] ?? '');
    // enforce length
    if(strlen($bio) > 2000) $bio = substr($bio,0,2000);

    if(staging_column_exists($conn, 'users', 'bio')){
        $stmt = safe_prepare($conn, "UPDATE users SET bio=? WHERE id=?");
        if(!$stmt){ echo json_encode(['status'=>'error','message'=>'DB error']); exit; }
        $uid = intval($_SESSION['user_id']);
        $stmt->bind_param('si', $bio, $uid);
        if(!$stmt->execute()){ echo json_encode(['status'=>'error','message'=>'Failed to save']); exit; }
        echo json_encode(['status'=>'success','message'=>'Bio saved']); exit;
    }

    // fallback: save in session for demo only
    $_SESSION['staging_bio'] = $bio;
    echo json_encode(['status'=>'success','message'=>'Bio saved (session demo)']); exit;
}

// Render page
?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Profile (Staging)</title>
  <style>body{font-family:Arial,Helvetica,sans-serif;padding:18px} .card{background:#fff;padding:12px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);max-width:760px} textarea{width:100%;min-height:120px}</style>
</head>
<body>
  <div class="card">
    <h1>Profile Bio (Staging)</h1>
    <?php if(!empty($is_logged_in)): ?>
        <p>Editing bio for <strong><?php echo htmlspecialchars($user_name); ?></strong></p>
        <?php
        // load existing bio (DB if available, otherwise session)
        $current = '';
            if(staging_column_exists($conn,'users','bio')){
                $stmt = safe_prepare($conn, "SELECT bio FROM users WHERE id = ? LIMIT 1");
            if($stmt){ $uid = intval($_SESSION['user_id']); $stmt->bind_param('i',$uid); $stmt->execute(); $stmt->bind_result($b); $stmt->fetch(); $current = $b ?? ''; $stmt->close(); }
            } else {
            $current = $_SESSION['staging_bio'] ?? '';
        }
        ?>
        <form id="bioForm">
            <div class="form-message" aria-live="polite"></div>
            <textarea name="bio" id="bioField"><?php echo htmlspecialchars($current); ?></textarea>
            <p style="margin-top:8px"><button type="submit">Save Bio</button></p>
        </form>
        <script>
        document.getElementById('bioForm').addEventListener('submit', async function(e){
            e.preventDefault();
            let fd = new FormData(e.target);
            fd.append('action','save_bio');
            const msg = e.target.querySelector('.form-message');
            msg.textContent = 'Saving...';
            try{
                const res = await fetch('profiles.php',{method:'POST', body: fd, credentials: 'same-origin'});
                const data = await res.json();
                msg.textContent = data.message || (data.status === 'success' ? 'Saved' : 'Error');
            }catch(err){ msg.textContent = 'Network error'; }
        });
        </script>
    <?php else: ?>
        <p>You are not logged in. Use the main site to log in and then return here to edit your bio.</p>
    <?php endif; ?>
  </div>
</body>
</html>
