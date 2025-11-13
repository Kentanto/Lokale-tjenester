<?php
    // Keep minimal PHP in index. Session/user logic moved to `display.php`.
    // `display.php` will start the session and expose $is_logged_in and $user_name.
    require_once 'display.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Finn Hustle - Your Platform</title>
</head>
<body>
    <!-- Navigation Bar -->
    <nav>
        <div class="logo">
            <span>🚀</span>
            <span>Finn Hustle</span>
        </div>

        <!-- Top nav links removed per request -->

        <div class="user-profile">
            <button class="user-btn">
                <div class="user-avatar"><?php echo substr($user_name, 0, 1); ?></div>
                <span><?php echo $is_logged_in ? $user_name : 'Menu'; ?></span>
                <span>▼</span>
            </button>

            <div class="dropdown-menu" id="dropdownMenu">
            <!--php inline for login and logout function -->
                <?php if ($is_logged_in): ?>
                    <a href="pages.php?page=profile">Profile</a>
                    <a href="pages.php?page=settings">Settings</a>
                    <a href="pages.php?page=dashboard">Dashboard</a>
                    <div class="dropdown-divider"></div>
                    <a href="submit.php?action=logout">Logout</a>
                <?php else: ?>
                    <a href="submit.php?action=login">Login</a>
                    <a href="submit.php?action=signup">Sign Up</a>
                    <a href="pages.php?page=about">About Us</a>
                <?php endif; ?>
                <!-- End php inline -->
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="column">
            <div class="column-content">
                <div class="column-image">
                    <img src="https://via.placeholder.com/250x250?text=Feature+1" alt="Feature 1">
                </div>
                <h2>Feature One</h2>
                <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Hic, aliquid quaerat deserunt repudiandae voluptatibus ipsa!</p>
                <div class="column-buttons">
                    <button class="btn btn-primary">Explore</button>
                    <button class="btn btn-secondary">Learn More</button>
                </div>
            </div>
        </div>

        <div class="column">
            <div class="column-content">
                <div class="column-image">
                    <img src="https://via.placeholder.com/250x250?text=Feature+2" alt="Feature 2">
                </div>
                <h2>Feature Two</h2>
                <p>Lorem ipsum dolor, sit amet consectetur adipisicing elit. Tempore dolorum magnam quisquam debitis? Similique, libero.</p>
                <div class="column-buttons">
                    <button class="btn btn-primary">Get Started</button>
                    <button class="btn btn-secondary">See Demo</button>
                </div>
            </div>
        </div>
    </div>

    <footer class="site-footer">
            <div class="footer-inner">
                <div class="footer-links">
                    <a href="index.php">Home</a>
                    <a href="pages.php?page=about">About</a>
                    <a href="pages.php?page=services">Services</a>
                    <a href="pages.php?page=contact">Contact</a>
                </div>
                <div class="footer-right">
                    <span>&copy; <?php echo date('Y'); ?> Finn Hustle</span>
                    <span class="footer-sep">|</span>
                    <a href="LICENSE" class="license-link">Basic Fair Use (US)</a>
                </div>
            </div>
    </footer>

    <script src="script.js"></script>
</body>
</html>

