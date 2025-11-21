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
    <link rel="stylesheet" href="static/style.css">
    <title>Finn Hustle - Your Platform</title>
</head>
<body>
    <!-- Navigation Bar -->
    <nav>
        <div class="logo">
            <a href="index.php">
                <img src="assets/Lokale_Tjenester.jpg" alt="">
            </a>
        
        </div>
        <div class="nav-center">
            <a >Trygg hjelp med lokale hender</a>
        </div>
        <!-- Top nav links removed per request -->

        <div class="user-profile">
            <button class="user-btn">
                <div class="user-avatar"><?php echo substr($user_name, 0, 1); ?></div>
                <span><?php echo $is_logged_in ? $user_name : 'Menu'; ?></span>
                <span>▼</span>
            </button>

            <div class="dropdown-menu" id="dropdownMenu">
            <!-- Inline login/signup forms like the backup -->
                <?php if ($is_logged_in): ?>
                    <a href="pages.php?page=profile">Profile</a>
                    <a href="pages.php?page=settings">Settings</a>
                    <a href="pages.php?page=dashboard">Dashboard</a>
                    <div class="dropdown-divider"></div>
                    <button id="logoutBtn">Logout</button>
                <?php else: ?>
                    <form id="loginForm" class="auth-form">
                        <div class="form-message" aria-live="polite"></div>
                        <input type="text" name="username" placeholder="Username or email" required>
                        <input type="password" name="password" placeholder="Password" required>
                        <button type="submit">Login</button>
                    </form>
                    <div class="dropdown-divider"></div>
                    <form id="signupForm" class="auth-form">
                        <div class="form-message" aria-live="polite"></div>
                        <input type="text" name="username" placeholder="Username" required>
                        <input type="email" name="email" placeholder="Email" required>
                        <input type="password" name="password" placeholder="Password" required>
                        <button type="submit">Sign Up</button>
                    </form>
                <?php endif; ?>
                <!-- End inline forms -->
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="column">
            <div class="column-content">
                <div class="column-image">
                    <img src="https://via.placeholder.com/250x250?text=Feature+1" alt="Feature 1">
                </div>
                <h2>Create Job</h2>
                <p>Post a new job quickly to reach local providers — set details, budget and availability.</p>
                <h2>Create Job</h2>
                <p>Post a new job quickly to reach local providers — set details, budget and availability.</p>
                <div class="column-buttons">
                    <button class="btn btn-primary">Post a Job</button>
                    <button class="btn btn-secondary">How It Works</button>
                </div>
            </div>
        </div>

        <div class="column">
            <div class="column-content">
                <div class="column-image">
                    <img src="https://via.placeholder.com/250x250?text=Feature+2" alt="Feature 2">
                </div>
                <h2>Find Job</h2>
                <p>Browse available jobs nearby or search by category to find the right match for your skills.</p>
                <h2>Find Job</h2>
                <p>Browse available jobs nearby or search by category to find the right match for your skills.</p>
                <div class="column-buttons">
                    <button class="btn btn-primary">Search Jobs</button>
                    <button class="btn btn-secondary">Browse Services</button>
                    <button class="btn btn-primary">Search Jobs</button>
                    <button class="btn btn-secondary">Browse Services</button>
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

    <script src="static/script.js"></script>
</body>
</html>
