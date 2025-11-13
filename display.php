
<?php
/**
 * display.php
 *
 * Centralized place for session / display-related PHP.
 * Included by `index.php` to keep template markup clean.
 */

// Start or resume session so we can read user state
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

// Simple demo logic: read login state from session
$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
$user_name = $is_logged_in && !empty($_SESSION['user_name']) ? $_SESSION['user_name'] : 'John Doe';

// You can replace the above logic with real auth / user-fetching code.

?>
