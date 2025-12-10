<?php
// CLI helper to create or update admin users.
// Usage (from project root):
//   php scripts/create_admin.php create <username> <email> <password> [--admin]
//   php scripts/create_admin.php set-admin <username|email> <0|1>
//   php scripts/create_admin.php set-password <username|email> <newpassword>

if(PHP_SAPI !== 'cli'){
    echo "This script is intended to be run from the command line only.\n";
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/display.php';

function usage(){
    global $argv;
    echo "Usage:\n";
    echo "  php {$argv[0]} create <username> <email> <password> [--admin]\n";
    echo "  php {$argv[0]} set-admin <username|email> <0|1>\n";
    echo "  php {$argv[0]} set-password <username|email> <newpassword>\n";
    exit(1);
}

if(!isset($argv[1])) usage();
$cmd = $argv[1];

if(!$conn || ($conn && $conn->connect_error)){
    echo "Database connection not available. Check database settings in display.php\n";
    exit(1);
}

// Ensure helper columns exist (non-destructive)
try {
    $res = $conn->query("SHOW COLUMNS FROM users LIKE 'is_admin'");
    if($res && $res->num_rows === 0){
        $conn->query("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0");
        echo "Added column is_admin to users table.\n";
    }
    $res = $conn->query("SHOW COLUMNS FROM users LIKE 'session_duration'");
    if($res && $res->num_rows === 0){
        $conn->query("ALTER TABLE users ADD COLUMN session_duration INT DEFAULT NULL");
        echo "Added column session_duration to users table.\n";
    }
} catch (Exception $e){
    // continue — the table may already exist or we lack permissions
}

// Helpers
function find_user($conn, $ident){
    $stmt = safe_prepare($conn, "SELECT id, username, email FROM users WHERE username = ? OR email = ? LIMIT 1");
    if(!$stmt) return null;
    $stmt->bind_param('ss', $ident, $ident);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

if($cmd === 'create'){
    if(count($argv) < 5) usage();
    $username = $argv[2];
    $email = $argv[3];
    $password = $argv[4];
    $is_admin = in_array('--admin', $argv, true) ? 1 : 0;

    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        echo "Invalid email address.\n"; exit(1);
    }
    if(strlen($password) < 6){ echo "Password must be at least 6 characters.\n"; exit(1); }

    $existing = find_user($conn, $username) ?? find_user($conn, $email);
    $hash = password_hash($password, PASSWORD_BCRYPT);

    if($existing){
        // update password and admin flag
        $stmt = safe_prepare($conn, "UPDATE users SET password_hash = ?, is_admin = ? WHERE id = ?");
        if(!$stmt){ echo "Failed to prepare update statement.\n"; exit(1); }
        $stmt->bind_param('sii', $hash, $is_admin, $existing['id']);
        if($stmt->execute()){
            echo "Updated existing user '{$existing['username']}' (id={$existing['id']}). Admin={$is_admin}\n";
        } else {
            echo "Failed to update user: " . $stmt->error . "\n"; exit(1);
        }
        $stmt->close();
        exit(0);
    }

    // Insert new user
    $stmt = safe_prepare($conn, "INSERT INTO users (username, email, password_hash, session_duration, is_admin) VALUES (?, ?, ?, ?, ?)");
    if(!$stmt){ echo "Failed to prepare insert statement.\n"; exit(1); }
    $default_dur = FH_DEFAULT_SESSION;
    $stmt->bind_param('sssii', $username, $email, $hash, $default_dur, $is_admin);
    if($stmt->execute()){
        echo "Created user '{$username}' with id={$conn->insert_id}. Admin={$is_admin}\n";
        exit(0);
    } else {
        echo "Failed to insert user: " . $stmt->error . "\n"; exit(1);
    }

} elseif($cmd === 'set-admin'){
    if(count($argv) < 4) usage();
    $ident = $argv[2];
    $flag = intval($argv[3]) ? 1 : 0;
    $user = find_user($conn, $ident);
    if(!$user){ echo "User not found for '{$ident}'.\n"; exit(1); }
    $stmt = safe_prepare($conn, "UPDATE users SET is_admin = ? WHERE id = ?");
    if(!$stmt){ echo "Failed to prepare statement.\n"; exit(1); }
    $stmt->bind_param('ii', $flag, $user['id']);
    if($stmt->execute()){
        echo "Set is_admin={$flag} for user '{$user['username']}' (id={$user['id']}).\n"; exit(0);
    } else { echo "Failed: " . $stmt->error . "\n"; exit(1); }

} elseif($cmd === 'set-password'){
    if(count($argv) < 4) usage();
    $ident = $argv[2];
    $newpw = $argv[3];
    if(strlen($newpw) < 6){ echo "Password must be at least 6 characters.\n"; exit(1); }
    $user = find_user($conn, $ident);
    if(!$user){ echo "User not found for '{$ident}'.\n"; exit(1); }
    $hash = password_hash($newpw, PASSWORD_BCRYPT);
    $stmt = safe_prepare($conn, "UPDATE users SET password_hash = ? WHERE id = ?");
    if(!$stmt){ echo "Failed to prepare statement.\n"; exit(1); }
    $stmt->bind_param('si', $hash, $user['id']);
    if($stmt->execute()){
        echo "Password updated for user '{$user['username']}' (id={$user['id']}).\n"; exit(0);
    } else { echo "Failed: " . $stmt->error . "\n"; exit(1); }

} else {
    usage();
}


