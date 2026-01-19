<?php
    require_once 'display.php';
?>
<!DOCTYPE html>
<html lang="no">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="static/style.css">
    <title>Kommer Snart - Lokale Tjenester</title>
</head>
<body>
    <!-- Navigation Bar -->
    <nav>
        <div class="logo">
            <a href="#">
                <img src="assets/Lokale_Tjenester.jpg" alt="Lokale Tjenester">
            </a>
        </div>
        <div class="nav-center"><a>Trygg hjelp med lokale hender</a></div>
        <div class="user-profile">
            <button class="user-btn">
                <div class="user-avatar"><?php echo htmlspecialchars(substr($user_name,0,1)); ?></div>
                <span><?php echo $is_logged_in ? htmlspecialchars($user_name) : 'Menu'; ?></span>
                <span>▼</span>
            </button>

            <div class="dropdown-menu" id="dropdownMenu">
                <?php if ($is_logged_in): ?>
                    <?php if (!empty($is_admin)): ?>
                        <a href="index2.php">Admin Panel</a>
                    <?php endif; ?>
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
                        <button type="submit" aria-disabled="true" disabled>Sign Up (Disabled)</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="coming-soon-container">
        <div class="coming-soon-content">
            <div class="coming-soon-logo">
                <img src="assets/Lokale_Tjenester.jpg" alt="Lokale Tjenester Logo">
            </div>
            
            <h1>Nettsted Kommer Snart</h1>
            <p class="tagline">Vi arbeider hardt for å bringe deg noe fantastisk</p>
            
            <p class="description">
                Vår nye plattform er under utvikling. Vi lanserer snart med spennende funksjoner 
                for å hjelpe deg å finne og legge ut lokale tjenester i ditt område.
            </p>

            <div class="highlight-box">
                <h3>Hva du kan forvente:</h3>
                <p>✓ Enkel måte å finne lokale tjenester på</p>
                <p>✓ Sikker og pålitelig plattform</p>
                <p>✓ Direkte kontakt med lokale tilbydere</p>
            </div>

            <p class="coming-soon-footer-text">
                Takk for din tålmodighet og interesse for Lokale Tjenester!
            </p>
        </div>
    </div>

    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-links">
                <span style="color: var(--muted);">Lokale Tjenester</span>
            </div>
            <div class="footer-right">
                <span>&copy; 2026 Lokale Tjenester</span>
            </div>
        </div>
    </footer>

    <script src="static/script.js"></script>
</body>
</html>
