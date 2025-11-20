<?php
session_start();

// default values
$is_logged_in = false;
$user_name = "Guest";
$user_email = '';
$user_created = null;

// check session
if (isset($_SESSION['user_id'])) {

    // connect to DB (use same credentials as index.php)
    $conn = new mysqli("localhost", "pyx", "admin", "DB");

    if (!$conn->connect_error) {
        $stmt = $conn->prepare("SELECT username, email, created_at FROM users WHERE id = ?");
        if($stmt){
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $stmt->bind_result($username, $email, $created_at);
            $stmt->fetch();

            if ($username) {
                $is_logged_in = true;
                $user_name = $username;
                $user_email = $email;
                $user_created = $created_at;
            }
            $stmt->close();
        } else {
            error_log('display.php: prepare failed: ' . $conn->error);
        }
    }
    $conn->close();
}
?>
