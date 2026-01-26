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
$notice = isset($_SESSION['notice']) ? $_SESSION['notice'] : '';
unset($_SESSION['notice']);

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])){
    $action = $_POST['action'];
    @file_put_contents(__DIR__ . '/debug_admin.log', date('c') . " - POST received: action=$action\n", FILE_APPEND);
    
    if($action === 'update_user'){
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
                    $_SESSION['notice'] = 'This account is protected and cannot be modified.';
                    $_SESSION['notice_type'] = 'danger';
                } else {
                    $stmt = safe_prepare($conn, "UPDATE users SET is_admin = NOT COALESCE(is_admin,0) WHERE id = ?");
                    if($stmt){
                        $stmt->bind_param('i', $target);
                        if($stmt->execute()){
                            // Fetch the new admin status to determine if promoted or demoted
                            $check = safe_prepare($conn, "SELECT COALESCE(is_admin,0) FROM users WHERE id = ? LIMIT 1");
                            if($check){
                                $check->bind_param('i', $target);
                                $check->execute();
                                $check->bind_result($new_status);
                                $check->fetch();
                                $check->close();
                                if($new_status){
                                    $_SESSION['notice'] = $tusername . ' promoted to admin';
                                    $_SESSION['notice_type'] = 'success';
                                } else {
                                    $_SESSION['notice'] = $tusername . ' demoted from admin';
                                    $_SESSION['notice_type'] = 'danger';
                                }
                            } else {
                                $_SESSION['notice'] = 'Toggled admin status.';
                                $_SESSION['notice_type'] = 'success';
                            }
                        } else {
                            $_SESSION['notice'] = 'Failed to update admin status.';
                            $_SESSION['notice_type'] = 'danger';
                        }
                    } else { 
                        $_SESSION['notice'] = 'Database error.'; 
                        $_SESSION['notice_type'] = 'danger';
                    }
                }
            } else {
                $_SESSION['notice'] = 'Database error.';
                $_SESSION['notice_type'] = 'danger';
            }
        }
        header('Location: admin.php');
        exit;
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
                    $_SESSION['notice'] = 'This account is protected and password cannot be changed here.';
                } else {
                    $hash = password_hash($newpw, PASSWORD_BCRYPT);
                    $stmt = safe_prepare($conn, "UPDATE users SET password_hash = ? WHERE id = ?");
                    if($stmt){
                        $stmt->bind_param('si', $hash, $target);
                        if($stmt->execute()) $_SESSION['notice'] = 'Password updated.'; else $_SESSION['notice'] = 'Failed to update password.';
                    } else { $_SESSION['notice'] = 'Database error.'; }
                }
            } else { $_SESSION['notice'] = 'Database error.'; }
        } else { $_SESSION['notice'] = 'Invalid target or password too short (min 6).'; }
        header('Location: admin.php');
        exit;
    }
    if($action === 'delete_user'){
        $target = intval($_POST['user_id'] ?? 0);
        if($target > 0){
            // prevent deleting self accidentally
            if($target === intval($_SESSION['user_id'])){
                $_SESSION['notice'] = 'You cannot delete your own account from the admin panel.';
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
                        $_SESSION['notice'] = 'This account is protected and cannot be deleted.';
                    } else {
                        $stmt = safe_prepare($conn, "DELETE FROM users WHERE id = ?");
                        if($stmt){
                            $stmt->bind_param('i', $target);
                            if($stmt->execute()) $_SESSION['notice'] = 'User deleted.'; else $_SESSION['notice'] = 'Failed to delete user.';
                        } else { $_SESSION['notice'] = 'Database error.'; }
                    }
                } else { $_SESSION['notice'] = 'Database error.'; }
            }
        }
        header('Location: admin.php');
        exit;
    }
    if($action === 'update_user'){
        $target = intval($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $is_admin = !empty($_POST['is_admin']) ? 1 : 0;

        @file_put_contents(__DIR__ . '/debug_admin.log', date('c') . " - Update user start: target=$target, username=$username, email=$email, password=" . (empty($password) ? 'empty' : 'provided') . ", is_admin=$is_admin\n", FILE_APPEND);

        if($target > 0 && $username && $email){
            // Prevent editing protected accounts
            $safe = safe_prepare($conn, "SELECT username FROM users WHERE id = ? LIMIT 1");
            if($safe){
                $safe->bind_param('i', $target);
                $safe->execute();
                $safe->bind_result($tusername);
                $safe->fetch();
                $safe->close();
                if(isset($tusername) && $tusername === 'adminpyx'){
                    $_SESSION['notice'] = 'This account is protected and cannot be edited.';
                    $_SESSION['notice_type'] = 'danger';
                    @file_put_contents(__DIR__ . '/debug_admin.log', date('c') . " - Protected account, abort\n", FILE_APPEND);
                } else {
                    // Check for duplicate username/email
                    $stmt = safe_prepare($conn, "SELECT id FROM users WHERE (username=? OR email=?) AND id != ?");
                    if($stmt){
                        $stmt->bind_param('ssi', $username, $email, $target);
                        $stmt->execute();
                        $stmt->store_result();
                        if($stmt->num_rows > 0){
                            $_SESSION['notice'] = 'Username or email already exists.';
                            $_SESSION['notice_type'] = 'danger';
                            @file_put_contents(__DIR__ . '/debug_admin.log', date('c') . " - Duplicate found, abort\n", FILE_APPEND);
                        } else {
                            // Update user
                            if($password){
                                $hash = password_hash($password, PASSWORD_BCRYPT);
                                $stmt = safe_prepare($conn, "UPDATE users SET username=?, email=?, password_hash=?, is_admin=? WHERE id=?");
                                $stmt->bind_param('sssii', $username, $email, $hash, $is_admin, $target);
                                @file_put_contents(__DIR__ . '/debug_admin.log', date('c') . " - Updating with password\n", FILE_APPEND);
                            } else {
                                $stmt = safe_prepare($conn, "UPDATE users SET username=?, email=?, is_admin=? WHERE id=?");
                                $stmt->bind_param('ssii', $username, $email, $is_admin, $target);
                                @file_put_contents(__DIR__ . '/debug_admin.log', date('c') . " - Updating without password\n", FILE_APPEND);
                            }
                            if($stmt->execute()){
                                $_SESSION['notice'] = 'User updated successfully.';
                                @file_put_contents(__DIR__ . '/debug_admin.log', date('c') . " - Update successful\n", FILE_APPEND);
                            } else {
                                $_SESSION['notice'] = 'Failed to update user.';
                                $_SESSION['notice_type'] = 'danger';
                                @file_put_contents(__DIR__ . '/debug_admin.log', date('c') . " - Update failed: " . $stmt->error . "\n", FILE_APPEND);
                            }
                        }
                        $stmt->close();
                    } else {
                        $_SESSION['notice'] = 'Database error.';
                        $_SESSION['notice_type'] = 'danger';
                        @file_put_contents(__DIR__ . '/debug_admin.log', date('c') . " - DB error on duplicate check\n", FILE_APPEND);
                    }
                }
            } else {
                $_SESSION['notice'] = 'Database error.';
                $_SESSION['notice_type'] = 'danger';
                @file_put_contents(__DIR__ . '/debug_admin.log', date('c') . " - DB error on safe check\n", FILE_APPEND);
            }
        } else {
            $_SESSION['notice'] = 'Invalid input.';
            $_SESSION['notice_type'] = 'danger';
            @file_put_contents(__DIR__ . '/debug_admin.log', date('c') . " - Invalid input: target=$target, username=$username, email=$email\n", FILE_APPEND);
        }
        header('Location: admin.php');
        exit;
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
<title>Admin Panel — Lokale Tjenester</title>
</head>
<body>
    <!-- Edit User Modal -->
    <div id="editUserModal" class="confirm-overlay">
        <div class="confirm-box" style="max-width: 500px;">
            <h3>Edit User</h3>
            <form id="editUserForm" method="post" action="admin.php">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="form-group">
                    <label for="editUsername">Username</label>
                    <input type="text" id="editUsername" name="username" required>
                </div>
                <div class="form-group">
                    <label for="editEmail">Email</label>
                    <input type="email" id="editEmail" name="email" required>
                </div>
                <div class="form-group">
                    <label for="editPassword">New Password (leave blank to keep current)</label>
                    <input type="password" id="editPassword" name="password">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="editIsAdmin" name="is_admin"> Admin
                    </label>
                </div>
                <div class="confirm-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="action" value="update_user" class="btn btn-primary">Save Changes</button>
                    <button type="submit" name="action" value="delete_user" class="btn delete-btn">Delete User</button>
                </div>
            </form>
        </div>
    </div>

    <?php require_once 'navigation/navbar.php'; ?>
<div class="page-wrapper">
    <main class="page-main">
        <div class="page-header"><h1>Admin Panel</h1></div>
        <div class="page-content">
            <?php if($notice): 
                $notice_type = isset($_SESSION['notice_type']) ? $_SESSION['notice_type'] : 'success';
                unset($_SESSION['notice_type']);
                $bg_color = ($notice_type === 'danger') ? '#dc3545' : 'var(--green)';
            ?>
                <div id="notificationBox" class="<?php echo $notice_type === 'danger' ? 'danger' : ''; ?>">
                    <span><?php echo htmlspecialchars($notice); ?></span>
                    <button onclick="document.getElementById('notificationBox').style.display='none';">×</button>
                </div>
            <?php endif; ?>

           
            <form method="get" action="admin.php" class="admin-search-form">
                <input type="search" name="q" placeholder="Search username or email" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" >
                <button class="btn btn-secondary" type="submit">Search</button>
                <?php if(!empty($_GET['q'])): ?>
                    <a class="btn btn-secondary" href="admin.php">Clear</a>
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
                        <div class="user-actions">
                            <button class="btn btn-primary edit-btn" onclick="openEditModal(<?php echo intval($u['id']); ?>, '<?php echo htmlspecialchars(addslashes($u['username'])); ?>', '<?php echo htmlspecialchars(addslashes($u['email'])); ?>', <?php echo intval($u['is_admin']); ?>)">Edit</button>
                        </div>
                        <?php if($is_protected): ?>
                            <div class="card-overlay" role="img" aria-label="System account" title="System account">
                                <span class="overlay-label">System account</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </main>
</div>


    <script src="static/admin.js"></script>
    <script src="static/script.js"></script>
</body>
</html>
