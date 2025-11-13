
<?php
/**
 * submit.php
 *
 * Minimal demo handler for login/signup/logout actions.
 * In a real app this would validate credentials and handle registration.
 */

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;

if ($action === 'login' || $action === 'signup') {
	// Demo: mark user as logged in and set a name.
	$_SESSION['is_logged_in'] = true;
	$_SESSION['user_name'] = 'John Doe';

	// Redirect back to the homepage
	header('Location: index.php');
	exit;
}

if ($action === 'logout') {
	// Clear session and redirect back
	$_SESSION = [];
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000,
			$params['path'], $params['domain'],
			$params['secure'], $params['httponly']
		);
	}
	session_destroy();
	header('Location: index.php');
	exit;
}

// If no valid action, redirect home
header('Location: index.php');
exit;

?>
