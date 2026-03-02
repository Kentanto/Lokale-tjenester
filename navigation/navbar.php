<?php
// Shared navbar component — used on all pages (index.php, pages.php, admin.php, etc.)
// Assumes $is_logged_in, $user_name, $is_admin are defined in display.php
global $conn, $user_id;
?>
<nav>
    <div class="logo">
        <a href="index.php">
            <img src="assets/Lokale_Tjenester.png" alt="Lokale Tjenester">
        </a>
    </div>
    <div class="nav-center"><a>Trygg hjelp med lokale hender</a></div>
    <div class="user-profile">
        <button class="user-btn">
            <div class="user-avatar">
                <?php 
                    $debug = true; // Set to true to see debug info
                    
                    if($debug) {
                        error_log("navbar: is_logged_in=" . ($is_logged_in ? 'true' : 'false') . ", user_id=" . ($user_id ?? 'null') . ", conn=" . (isset($conn) ? 'set' : 'not set') . ", func=" . (function_exists('get_profile_picture_url') ? 'exists' : 'not exists'));
                    }
                    
                    if($is_logged_in) {
                        if(!empty($user_id) && isset($conn) && function_exists('get_profile_picture_url')) {
                            @$profilePicUrl = get_profile_picture_url($conn, $user_id);
                            if($profilePicUrl) {
                                echo '<img src="' . htmlspecialchars($profilePicUrl) . '" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">';
                            } else {
                                echo htmlspecialchars(substr($user_name,0,1));
                            }
                        } else {
                            // user_id not available or conn not available, fallback to first letter
                            echo htmlspecialchars(substr($user_name,0,1));
                        }
                    } else {
                        echo '⋮';
                    }
                ?>
            </div>
            <span><?php echo $is_logged_in ? htmlspecialchars($user_name) : 'Menu'; ?></span>
            <span>▼</span>
        </button>

        <div class="dropdown-menu" id="dropdownMenu">
            <?php if ($is_logged_in): ?>
                <?php if (!empty($is_admin)): ?>
                    <a href="/admin.php">Admin Panel</a>
                <?php endif; ?>
                <a href="/pages.php?page=profile">Profil</a>
                <a href="/pages.php?page=settings">Innstillinger</a>
                <a href="/pages.php?page=dashboard">Dashboard</a>
                <div class="dropdown-divider"></div>
                <button id="logoutBtn">Logg ut</button>
            <?php else: ?>
                <form id="loginForm" class="auth-form">
                    <div class="form-message" aria-live="polite"></div>
                    <input type="text" name="username" placeholder="Brukernavn eller e-post" required>
                    <input type="password" name="password" placeholder="Passord" required>
                    <button type="submit">Logg inn</button>
                </form>
                <div class="dropdown-divider"></div>
                <form id="signupForm" class="auth-form">
                    <div class="form-message" aria-live="polite"></div>
                    <input type="text" name="username" placeholder="Brukernavn" required>
                    <input type="email" name="email" placeholder="E-post" required>
                    <input type="password" name="password" placeholder="Passord" required>
                    <button type="submit" aria-disabled="true" disabled>Registrer (Deaktivert)</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</nav>
