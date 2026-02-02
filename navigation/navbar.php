<?php
// Shared navbar component — used on all pages (index.php, pages.php, admin.php, etc.)
// Assumes $is_logged_in, $user_name, $is_admin are defined in display.php
?>
<nav>
    <div class="logo">
        <a href="index2.php">
            <img src="assets/Lokale_Tjenester.png" alt="Lokale Tjenester">
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
                    <a href="admin.php">Admin Panel</a>
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
