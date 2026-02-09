<?php
    // Keep minimal PHP in index. Session/user logic moved to `display.php`.
    // `display.php` will start the session and expose $is_logged_in and $user_name.
    require_once 'display.php';
    
    // Restrict access to admin users only
    if (!$is_logged_in || empty($is_admin)) {
        header('Location: index.php');
        exit;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="static/style.css">
    <title>Lokale Tjenester - Your Platform</title>
</head>
<body>
    <!-- Navigation Bar -->
    <?php require_once 'navigation/navbar.php'; ?>

    <div class="container">
        <div class="column">
            <div class="column-content">
                <div class="column-image">
                    <img src="https://media.istockphoto.com/id/1199554243/photo/engineer-men-making-handshake-in-construction-site-employee-or-worker-shake-hands-to-employer.jpg?s=612x612&w=0&k=20&c=kU_76i5whSa-3aPuMDdcAJsmRwtq3sF0Z7SLXVdosuc=" alt="Feature 1">
                </div>
                <h2>Create Job</h2>
                <p>Post a new job quickly to reach local providers — set details, budget and availability.</p>
                <div class="column-buttons">
                    <a href="pages.php?page=create_job" class="btn btn-primary">Post a Job</a>
                </div>
            </div>
        </div>

        <div class="column">
            <div class="column-content">
                <div class="column-image">
                    <img src="https://media-cldnry.s-nbcnews.com/image/upload/t_fit-1500w,f_auto,q_auto:best/rockcms/2022-01/shoveling-snow-kb-main-220107-b13b2a.jpg" alt="Feature 2">
                </div>
                <h2>Find Job</h2>
                <p>Browse available jobs nearby or search by category to find the right match for your skills.</p>
                <div class="column-buttons">
                    <a href="pages.php?page=jobs" class="btn btn-primary">Search Jobs</a>
                </div>
            </div>
        </div>
    </div>

    <footer class="site-footer">
            <div class="footer-inner">
                <div class="footer-links">
                    <a href="index2.php">Hjem</a>
                    <a href="pages.php?page=about">Om oss</a>
                    <a href="pages.php?page=services">Tjenester</a>
                    <a href="pages.php?page=contact">Kontakt</a>
                </div>
                <div class="footer-right">
                    <span>&copy; <?php echo date('Y'); ?> Lokale Tjenester</span>
                    <span class="footer-sep">|</span>
                    <a href="LICENSE" class="license-link">Basic Fair Use (US)</a>
                </div>
            </div>
    </footer>

    <script src="static/script.js"></script>
</body>
</html>
