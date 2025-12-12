<?php
require_once 'display.php';

// Only admin users allowed
if(!$is_logged_in || empty($is_admin)){
    header('HTTP/1.1 403 Forbidden');
    echo "<h1>403 Forbidden</h1><p>You are not authorized to access the admin panel.</p>";
    exit;
}

// Ensure DB connection
if(!isset($conn) || !$conn){
    echo "<p>Database connection not available.</p>";
    exit;
}

// Create is_admin column if missing (non-destructive)
try {
    $res = $conn->query("SHOW COLUMNS FROM users LIKE 'is_admin'");
    if($res && $res->num_rows === 0){
        $conn->query("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0");
    }
} catch (Exception $e) {
    // ignore
}

// Handle POST actions
$notice = '';
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])){
    $action = $_POST['action'];
    if($action === 'toggle_admin'){
        $target = intval($_POST['user_id'] ?? 0);
        if($target > 0){
            // Protect specific accounts from being demoted (server-side enforcement)
            $safe = safe_prepare($conn, "SELECT username, COALESCE(is_admin,0) AS is_admin FROM users WHERE id = ? LIMIT 1");
            if($safe){
                $safe->bind_param('i', $target);
                $safe->execute();
                $safe->bind_result($tusername, $t_is_admin);
                $safe->fetch();
                $safe->close();

                if(isset($tusername) && $tusername === 'adminpyx'){
                    $notice = 'This account is protected and cannot be modified.';
                } else {
                    $stmt = safe_prepare($conn, "UPDATE users SET is_admin = NOT COALESCE(is_admin,0) WHERE id = ?");
                    if($stmt){
                        $stmt->bind_param('i', $target);
                        if($stmt->execute()){
                            // fetch resulting state so AJAX clients can update UI inline
                            $q = safe_prepare($conn, "SELECT COALESCE(is_admin,0) FROM users WHERE id = ? LIMIT 1");
                            if($q){
                                $q->bind_param('i', $target);
                                $q->execute();
                                $q->bind_result($new_is_admin);
                                $q->fetch();
                                $q->close();
                                $ajax_is_admin = !empty($new_is_admin) ? 1 : 0;
                            }
                            $notice = 'Toggled admin status.';
                        } else $notice = 'Failed to update admin status.';
                    } else { $notice = 'Database error.'; }
                }
            } else {
                $notice = 'Database error.';
            }
        }
    }
    if($action === 'change_password'){
        $target = intval($_POST['user_id'] ?? 0);
        $newpw = $_POST['new_password'] ?? '';
        if($target > 0 && strlen($newpw) >= 6){
            // Prevent changing password for protected accounts (server-side enforcement)
            $safe = safe_prepare($conn, "SELECT username FROM users WHERE id = ? LIMIT 1");
            if($safe){
                $safe->bind_param('i', $target);
                $safe->execute();
                $safe->bind_result($tusername);
                $safe->fetch();
                $safe->close();
                if(isset($tusername) && $tusername === 'adminpyx'){
                    $notice = 'This account is protected and password cannot be changed here.';
                } else {
                    $hash = password_hash($newpw, PASSWORD_BCRYPT);
                    $stmt = safe_prepare($conn, "UPDATE users SET password_hash = ? WHERE id = ?");
                    if($stmt){
                        $stmt->bind_param('si', $hash, $target);
                        if($stmt->execute()) $notice = 'Password updated.'; else $notice = 'Failed to update password.';
                    } else { $notice = 'Database error.'; }
                }
            } else { $notice = 'Database error.'; }
        } else { $notice = 'Invalid target or password too short (min 6).'; }
    }
    if($action === 'delete_user'){
        $target = intval($_POST['user_id'] ?? 0);
        if($target > 0){
            // prevent deleting self accidentally
            if($target === intval($_SESSION['user_id'])){
                $notice = 'You cannot delete your own account from the admin panel.';
            } else {
                // Prevent deleting protected user(s)
                $safe = safe_prepare($conn, "SELECT username FROM users WHERE id = ? LIMIT 1");
                if($safe){
                    $safe->bind_param('i', $target);
                    $safe->execute();
                    $safe->bind_result($tusername);
                    $safe->fetch();
                    $safe->close();
                    if(isset($tusername) && $tusername === 'adminpyx'){
                        $notice = 'This account is protected and cannot be deleted.';
                    } else {
                        $stmt = safe_prepare($conn, "DELETE FROM users WHERE id = ?");
                        if($stmt){
                            $stmt->bind_param('i', $target);
                            if($stmt->execute()) $notice = 'User deleted.'; else $notice = 'Failed to delete user.';
                        } else { $notice = 'Database error.'; }
                    }
                } else { $notice = 'Database error.'; }
            }
        }
    }
}

// If this was an AJAX request, return a small JSON payload and exit
if($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'){
    header('Content-Type: application/json');
    $status = $notice ? 'ok' : 'error';
    $resp = ['status' => $status, 'message' => $notice];
    if(isset($ajax_is_admin)) $resp['is_admin'] = $ajax_is_admin ? 1 : 0;
    echo json_encode($resp);
    exit;
}

// Fetch users list (allow simple search via GET 'q' param: username OR email)
$q = trim($_GET['q'] ?? '');
$users = [];
if($q !== ''){
    $like = '%' . $q . '%';
    $stmt = safe_prepare($conn, "SELECT id, username, email, created_at, COALESCE(is_admin,0) AS is_admin FROM users WHERE username LIKE ? OR email LIKE ? ORDER BY COALESCE(is_admin,0) DESC, id DESC LIMIT 200");
    if($stmt){
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        if(method_exists($stmt, 'get_result')){
            $res = $stmt->get_result();
            while($r = $res->fetch_assoc()) $users[] = $r;
        } else {
            $stmt->store_result();
            $stmt->bind_result($id,$username,$email,$created_at,$is_admin_val);
            while($stmt->fetch()){
                $users[] = ['id'=>$id,'username'=>$username,'email'=>$email,'created_at'=>$created_at,'is_admin'=>$is_admin_val];
            }
        }
    }
} else {
    $stmt = safe_prepare($conn, "SELECT id, username, email, created_at, COALESCE(is_admin,0) AS is_admin FROM users ORDER BY COALESCE(is_admin,0) DESC, id DESC LIMIT 200");
    if($stmt){
        $stmt->execute();
        if(method_exists($stmt, 'get_result')){
            $res = $stmt->get_result();
            while($r = $res->fetch_assoc()) $users[] = $r;
        } else {
            $stmt->store_result();
            $stmt->bind_result($id,$username,$email,$created_at,$is_admin_val);
            while($stmt->fetch()){
                $users[] = ['id'=>$id,'username'=>$username,'email'=>$email,'created_at'=>$created_at,'is_admin'=>$is_admin_val];
            }
        }
    }
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="static/style.css">
<title>Admin Panel — Finn Hustle</title>
</head>
<body>
    <?php require_once 'navigation/navbar.php'; ?>
<div class="page-wrapper">
    <main class="page-main">
        <div class="page-header"><h1>Admin Panel</h1></div>
        <div class="page-content">
            <?php if($notice): ?><p class="note"><?php echo htmlspecialchars($notice); ?></p><?php endif; ?>

            <form method="get" action="admin.php" style="margin:12px 0;display:flex;gap:8px;align-items:center">
                <input type="search" name="q" placeholder="Search username or email" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" style="padding:8px;border-radius:6px;border:1px solid #ddd;flex:1">
                <button class="btn btn-secondary" type="submit">Search</button>
                <?php if(!empty($_GET['q'])): ?>
                    <a class="btn" href="admin.php" style="background:#eee;border-radius:6px;padding:8px 12px;text-decoration:none;color:#333">Clear</a>
                <?php endif; ?>
            </form>
            <section class="lead centered">
                <p>Manage users: appoint admins, change passwords and delete accounts. Actions are immediate.</p>
            </section>

            <section class="services-grid">
                <div class="grid">
                    <?php foreach($users as $u): ?>
                    <?php $is_protected = ($u['username'] === 'adminpyx'); ?>
                    <div class="service-card<?php echo $is_protected ? ' protected-card' : ''; ?>">
                        <h3><?php echo htmlspecialchars($u['username']); ?> <?php if(!empty($u['is_admin'])): ?> <small style="color:var(--green);font-weight:700">(admin)</small><?php endif; ?></h3>
                        <p>Email: <?php echo htmlspecialchars($u['email']); ?></p>
                        <p>Created: <?php echo htmlspecialchars($u['created_at']); ?></p>
                        <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
                            <form method="post" style="display:inline">
                                <input type="hidden" name="user_id" value="<?php echo intval($u['id']); ?>">
                                <input type="hidden" name="action" value="toggle_admin">
                                <button class="btn btn-secondary" type="submit" <?php echo $is_protected ? 'disabled' : ''; ?>><?php echo empty($u['is_admin']) ? 'Make Admin' : 'Revoke Admin'; ?></button>
                            </form>

                            <form method="post" style="display:inline">
                                <input type="hidden" name="user_id" value="<?php echo intval($u['id']); ?>">
                                <input type="password" name="new_password" placeholder="New password" required style="padding:8px;border-radius:6px;border:1px solid #ddd" <?php echo $is_protected ? 'disabled' : ''; ?>>
                                <input type="hidden" name="action" value="change_password">
                                <button class="btn btn-primary" type="submit" <?php echo $is_protected ? 'disabled' : ''; ?>>Change Password</button>
                            </form>

                            <form method="post" style="display:inline" onsubmit="return confirm('Delete user <?php echo htmlspecialchars(addslashes($u['username'])); ?>? This cannot be undone.');">
                                <input type="hidden" name="user_id" value="<?php echo intval($u['id']); ?>">
                                <input type="hidden" name="action" value="delete_user">
                                <button class="btn" style="background:#ef4444;color:#fff;border-radius:8px;padding:8px 12px;border:none" type="submit" <?php echo $is_protected ? 'disabled' : ''; ?>>Delete</button>
                            </form>
                            <?php if($is_protected): ?>
                                <div class="card-overlay" role="img" aria-label="System account" title="System account">
                                    <span class="overlay-label">System account</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </main>
</div>
<script>
// Attach AJAX submit handlers to toggle_admin forms so toggling is immediate.
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('form').forEach(function(f){
        var act = f.querySelector('input[name="action"]');
        if(!act) return;
        if(act.value === 'toggle_admin'){
            f.addEventListener('submit', function(e){
                e.preventDefault();
                var fd = new FormData(f);
                fetch('admin.php', {method:'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if(data && data.status === 'ok'){
                        // reload the page so UI reflects server state consistently
                        window.location.reload();
                    } else {
                        alert(data && data.message ? data.message : 'Failed to update admin status');
                    }
                }).catch(function(){ alert('Network error'); });
            });
        }
    });
});
</script>

    <script src="static/script.js"></script>
</body>
</html>
