<?php
require_once 'display.php';

// Simple pages router: pages.php?page=about|services|contact|profile|settings|dashboard|login|signup
$page = isset($_GET['page']) ? $_GET['page'] : 'about';
$allowed = ['about','services','contact','profile','settings','dashboard','login','signup'];
if (!in_array($page, $allowed)) {
    $page = 'about';
}

// Helper to render header
function render_header($title) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="style.css">
    <title><?php echo htmlspecialchars($title); ?> - Finn Hustle</title>
</head>
<body>
<nav>
    <div class="logo">🚀 Finn Hustle</div>
</nav>
<main class="page-main">
    <h1><?php echo htmlspecialchars($title); ?></h1>
    <?php
}

function render_footer() {
    ?>
    <p class="back-link"><a href="index.php">Back to Home</a></p>
</main>
</body>
</html>
<?php
}

// Page content
switch ($page) {
    case 'about':
        render_header('About');
        ?>
        <p>This is the About page consolidated into <code>pages.php</code>.</p>
        <?php
        render_footer();
        break;

    case 'services':
        render_header('Services');
        ?>
        <p>Services page content (temporary).</p>
        <?php
        render_footer();
        break;

    case 'contact':
        render_header('Contact');
        ?>
        <p>Contact page. Add a form or details here.</p>
        <?php
        render_footer();
        break;

    case 'profile':
        render_header('Profile');
        if ($is_logged_in) {
            ?>
            <p>Welcome, <?php echo htmlspecialchars($user_name); ?>. This is your profile (demo).</p>
            <?php
        } else {
            ?>
            <p>You are not logged in. <a href="pages.php?page=login">Login</a></p>
            <?php
        }
        render_footer();
        break;

    case 'settings':
        render_header('Settings');
        if ($is_logged_in) {
            ?>
            <p>Account settings (demo).</p>
            <?php
        } else {
            ?>
            <p>Please <a href="pages.php?page=login">login</a> to manage settings.</p>
            <?php
        }
        render_footer();
        break;

    case 'dashboard':
        render_header('Dashboard');
        if ($is_logged_in) {
            ?>
            <p>Your dashboard (demo).</p>
            <?php
        } else {
            ?>
            <p>Please <a href="pages.php?page=login">login</a> to view the dashboard.</p>
            <?php
        }
        render_footer();
        break;

    case 'login':
        render_header('Login');
        ?>
        <p>This demo login will set a session and redirect back to the homepage.</p>
        <form method="post" action="submit.php?action=login">
            <button type="submit" class="btn btn-primary">Log me in (demo)</button>
        </form>
        <?php
        render_footer();
        break;

    case 'signup':
        render_header('Sign Up');
        ?>
        <p>This demo signup will set a session and redirect back to the homepage.</p>
        <form method="post" action="submit.php?action=signup">
            <button type="submit" class="btn btn-primary">Sign me up (demo)</button>
        </form>
        <?php
        render_footer();
        break;
}
