<?php
require_once 'display.php';
$user_id = $_SESSION['user_id'] ?? null;

if (!$is_logged_in || empty($is_admin)) {
    header('HTTP/1.1 403 Forbidden');
    echo "<h1>403 Forbidden</h1><p>You are not authorized to access the admin panel.</p>";
    exit;
}

if(!isset($conn) || !$conn){
    echo "<p>Database connection not available.</p>";
    exit;
}

try {
    $res = $conn->query("SHOW COLUMNS FROM users LIKE 'is_admin'");
    if($res && $res->num_rows === 0){
        $conn->query("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0");
    }
} catch (Exception $e) {
}

$is_ajax_request = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])){
    $action = $_POST['action'];
    @file_put_contents(__DIR__ . '/debug_admin.log', date('c') . " - POST received: action=$action, is_ajax=$is_ajax_request\n", FILE_APPEND);
    
    if($action === 'delete_user'){
        $target = intval($_POST['user_id'] ?? 0);
        if($target > 0){
            if($target === intval($_SESSION['user_id'])){
                $_SESSION['notice'] = 'You cannot delete your own account from the admin panel.';
                $_SESSION['notice_type'] = 'danger';
            } else {
                $safe = safe_prepare($conn, "SELECT username FROM users WHERE id = ? LIMIT 1");
                if($safe){
                    $safe->bind_param('i', $target);
                    $safe->execute();
                    $safe->bind_result($tusername);
                    $safe->fetch();
                    $safe->close();
                    if(isset($tusername) && ($tusername === 'adminpyx' || $tusername === 'kentanto' || $tusername === 'system' || $tusername === 'lokale-tjenester')){
                        $_SESSION['notice'] = 'This account is protected and cannot be deleted.';
                        $_SESSION['notice_type'] = 'danger';
                    } else {
                        $stmt = safe_prepare($conn, "DELETE FROM users WHERE id = ?");
                        if($stmt){
                            $stmt->bind_param('i', $target);
                            if($stmt->execute()) {
                                $_SESSION['notice'] = 'User deleted.';
                                $_SESSION['notice_type'] = 'success';
                            } else {
                                $_SESSION['notice'] = 'Failed to delete user.';
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
        }
        if (!$is_ajax_request) { header('Location: admin.php'); exit; }
    }
    if($action === 'update_user'){
        $target = intval($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $is_admin = !empty($_POST['is_admin']) ? 1 : 0;

        @file_put_contents(__DIR__ . '/debug_admin.log', date('c') . " - Update user start: target=$target, username=$username, email=$email, password=" . (empty($password) ? 'empty' : 'provided') . ", is_admin=$is_admin\n", FILE_APPEND);

        if($target > 0 && $username && $email){
            $safe = safe_prepare($conn, "SELECT username FROM users WHERE id = ? LIMIT 1");
            if($safe){
                $safe->bind_param('i', $target);
                $safe->execute();
                $safe->bind_result($tusername);
                $safe->fetch();
                $safe->close();
                if(isset($tusername) && ($tusername === 'adminpyx' || $tusername === 'kentanto' || $tusername === 'system' || $tusername === 'lokale-tjenester')){
                    $_SESSION['notice'] = 'This account is protected and cannot be edited.';
                    $_SESSION['notice_type'] = 'danger';
                    @file_put_contents(__DIR__ . '/debug_admin.log', date('c') . " - Protected account, abort\n", FILE_APPEND);
                } else {
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
        if (!$is_ajax_request) { header('Location: admin.php'); exit; }
    }
    if($action === 'approve_post'){
        $post_id = intval($_POST['post_id'] ?? 0);
        if($post_id > 0){
            $stmt = safe_prepare($conn, "UPDATE posts SET status = 'approved' WHERE id = ?");
            if($stmt){
                $stmt->bind_param('i', $post_id);
                if($stmt->execute()){
                    $_SESSION['notice'] = 'Job approved and is now public.';
                    $_SESSION['notice_type'] = 'success';
                } else {
                    $_SESSION['notice'] = 'Failed to approve job.';
                    $_SESSION['notice_type'] = 'danger';
                }
                $stmt->close();
            } else {
                $_SESSION['notice'] = 'Database error.';
                $_SESSION['notice_type'] = 'danger';
            }
        }
        if (!$is_ajax_request) { header('Location: admin.php'); exit; }
    }
    if($action === 'reject_post'){
        $post_id = intval($_POST['post_id'] ?? 0);
        if($post_id > 0){
            $stmt = safe_prepare($conn, "UPDATE posts SET status = 'rejected' WHERE id = ?");
            if($stmt){
                $stmt->bind_param('i', $post_id);
                if($stmt->execute()){
                    $_SESSION['notice'] = 'Job rejected.';
                    $_SESSION['notice_type'] = 'success';
                } else {
                    $_SESSION['notice'] = 'Failed to reject job.';
                    $_SESSION['notice_type'] = 'danger';
                }
                $stmt->close();
            } else {
                $_SESSION['notice'] = 'Database error.';
                $_SESSION['notice_type'] = 'danger';
            }
        }
        if (!$is_ajax_request) { header('Location: admin.php'); exit; }
    }
}

// If this was an AJAX request, return a small JSON payload and exit 
$notice = isset($_SESSION['notice']) ? $_SESSION['notice'] : '';
unset($_SESSION['notice']);


if($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'){
    header('Content-Type: application/json');
    $status = $notice ? 'ok' : 'error';
    $resp = ['status' => $status, 'message' => $notice];
    if(isset($ajax_is_admin)) $resp['is_admin'] = $ajax_is_admin ? 1 : 0;
    echo json_encode($resp);
    exit;
}
// fetch users and search behaviour
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

// Fetch pending posts/jobs with images for safety
$pending_posts = [];
$stmt = safe_prepare($conn, "SELECT p.id, p.title, p.description, p.category, p.budget, p.location, p.created_at, COALESCE(u.username,'Guest') AS username FROM posts p LEFT JOIN users u ON p.user_id = u.id WHERE p.status = 'pending' ORDER BY p.created_at ASC LIMIT 200");
if($stmt){
    $stmt->execute();
    if(method_exists($stmt, 'get_result')){
        $res = $stmt->get_result();
        while($r = $res->fetch_assoc()) {
            $imageStmt = safe_prepare($conn, "SELECT image, image_type FROM post_images WHERE post_id = ? ORDER BY sort_order ASC LIMIT 1");
            if($imageStmt){
                $imageStmt->bind_param('i', $r['id']);
                $imageStmt->execute();
                $imageStmt->bind_result($image_data, $image_type);
                if($imageStmt->fetch()){
                    $r['image'] = 'data:image/' . ($image_type ?: 'jpeg') . ';base64,' . base64_encode($image_data);
                } else {
                    $r['image'] = null;
                }
                $imageStmt->close();
            }
            $pending_posts[] = $r;
        }
    } else {
        $stmt->store_result();
        $stmt->bind_result($id,$title,$description,$category,$budget,$location,$created_at,$username);
        while($stmt->fetch()){
            $item = ['id'=>$id,'title'=>$title,'description'=>$description,'category'=>$category,'budget'=>$budget,'location'=>$location,'created_at'=>$created_at,'username'=>$username];
            $imageStmt = safe_prepare($conn, "SELECT image, image_type FROM post_images WHERE post_id = ? ORDER BY sort_order ASC LIMIT 1");
            if($imageStmt){
                $imageStmt->bind_param('i', $id);
                $imageStmt->execute();
                $imageStmt->bind_result($image_data, $image_type);
                if($imageStmt->fetch()){
                    $item['image'] = 'data:image/' . ($image_type ?: 'jpeg') . ';base64,' . base64_encode($image_data);
                } else {
                    $item['image'] = null;
                }
                $imageStmt->close();
            }
            $pending_posts[] = $item;
        }
    }
    $stmt->close();
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<link rel="icon" type="image/png" href="assets/Lokale_Tjenester_only_logo.png">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="static/style.css">
<title>Admin Panel — Lokale Tjenester</title>
</head>
<body>
    <!-- Edit User Modal -->
    <div id="editUserModal" class="confirm-overlay">
        <div class="confirm-box" style="max-width: 500px;">
            <h3>Edit User</h3>
            <form id="editUserForm" method="post" action="admin.php" novalidate>
                <input type="hidden" name="action" id="formAction" value="update_user">
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
                    <button type="button" class="btn btn-primary" onclick="submitForm('update_user')">Save Changes</button>
                    <button type="button" class="btn delete-btn" onclick="submitForm('delete_user')">Delete User</button>
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

            <!-- PENDING JOBS SECTION --> 
            <?php if(!empty($pending_posts)): ?>
            <section class="pending-jobs-section" style="margin-bottom: 40px; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                <h2>📋 Jobber avventende godkjenning (<?php echo count($pending_posts); ?>)</h2>
                <p style="color: #856404; margin-bottom: 20px;">Godkjenn eller avvis disse jobbannonsene for å kontrollere quality.</p>
                <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                    <?php foreach($pending_posts as $p): ?>
                    <div class="service-card" style="border: 2px solid #ffc107;">
                        <?php if(!empty($p['image'])): ?>
                        <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['title']); ?>" style="width:100%;height:200px;object-fit:cover;border-radius:6px;margin-bottom:12px;">
                        <?php endif; ?>
                        <h3><?php echo htmlspecialchars($p['title']); ?></h3>
                        <p><small style="color: #666;">av <?php echo htmlspecialchars($p['username']); ?> • <?php echo htmlspecialchars($p['created_at']); ?></small></p>
                        <p><?php echo htmlspecialchars(substr($p['description'], 0, 100)); ?><?php if(strlen($p['description']) > 100) echo '...'; ?></p>
                        <p style="color: #666; font-size: 13px;">
                            <?php if($p['category']) echo '📁 ' . htmlspecialchars($p['category']) . ' • '; ?>
                            <?php if($p['location']) echo '📍 ' . htmlspecialchars($p['location']); ?>
                        </p>
                        <?php if($p['budget']): ?>
                        <p style="font-weight: 600; color: var(--green);">💰 <?php echo intval($p['budget']); ?> NOK</p>
                        <?php endif; ?>
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <form method="POST" style="flex: 1;">
                                <input type="hidden" name="action" value="approve_post">
                                <input type="hidden" name="post_id" value="<?php echo intval($p['id']); ?>">
                                <button type="submit" class="btn btn-primary" style="width: 100%;">✓ Godkjenn</button>
                            </form>
                            <form method="POST" style="flex: 1;">
                                <input type="hidden" name="action" value="reject_post">
                                <input type="hidden" name="post_id" value="<?php echo intval($p['id']); ?>">
                                <button type="submit" class="btn delete-btn" style="width: 100%;">✗ Avvis</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
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
                    <?php $is_protected = ($u['username'] === 'adminpyx' || $u['username'] === 'system' || $u['username'] === 'kentanto' || $u['username'] === 'lokale-tjenester'); ?>
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
