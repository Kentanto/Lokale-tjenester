<?php
require_once 'display.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

function send_verification_email($user_email, $user_id) {
    global $db; // PDO connection

    $token = bin2hex(random_bytes(16));

    // Insert or update token
    $stmt = $db->prepare("DELETE FROM email_tokens WHERE user_id=?");
    $stmt->execute([$user_id]);

    $stmt = $db->prepare("INSERT INTO email_tokens (user_id, token) VALUES (?, ?)");
    $stmt->execute([$user_id, $token]);

    // Send email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.example.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your_smtp_user';
        $mail->Password   = 'your_smtp_pass';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('no-reply@finnhustle.com', 'Finn Hustle');
        $mail->addAddress($user_email);
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email';
        $link = "https://yoursite.com/pages.php?page=verify&token=$token";
        $mail->Body    = "<p>Click below to verify your email:</p><p><a href='$link'>$link</a></p>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Simple pages router: pages.php?page=about|services|contact|profile|settings|dashboard|login|signup
$page = isset($_GET['page']) ? $_GET['page'] : 'about';
$allowed = ['about','services','contact','profile','settings','dashboard','login','signup','create_job','jobs'];
if (!in_array($page, $allowed)) {
    $page = 'about';
}

// Helper to render header
function render_header($title) {
    // make session/user vars available inside this function
    global $user_name, $is_logged_in;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="static/style.css">
    <title><?php echo htmlspecialchars($title); ?> - Finn Hustle</title>
</head>
<body>
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
                <div class="user-avatar"><?php echo substr((string)$user_name, 0, 1); ?></div>
                <span><?php echo $is_logged_in ? htmlspecialchars($user_name) : 'Menu'; ?></span>
                <span>▼</span>
            </button>

            <div class="dropdown-menu" id="dropdownMenu">
            <!-- Inline login/signup forms like the backup -->
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
                        <button type="submit">Sign Up</button>
                    </form>
                <?php endif; ?>
                <!-- End inline forms -->
            </div>
        </div>
    </nav>

<div class="page-wrapper">
    <main class="page-main">
        <div class="page-header">
            <h1><?php echo htmlspecialchars($title); ?></h1>
        </div>
        <div class="page-content">
    <?php
}

function render_footer() {
    ?>
        </div> <!-- .page-content -->
    </main>

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
                <a href="LICENSE" class="license-link">Basic Fair Use (NOR)</a>
            </div>
        </div>
    </footer>

</div> <!-- .page-wrapper -->

<script src="static/script.js"></script>
</body>
</html>
<?php
}

// Page content
switch ($page) {
    case 'about':
        render_header('About');
        ?>
        <section class="lead">
            <p>Welcome to <strong>Finn Hustle</strong> — your local hub for finding trusted services nearby. We connect
            homeowners and small businesses with local professionals to get things done quickly and reliably!</p>
        </section>

        <section class="features">
            <h2>Why choose us?</h2>
            <ul class="page-list">
                <li><strong>Trusted Providers:</strong> Every provider is reviewed and rated by real users.</li>
                <li><strong>Easy Booking:</strong> Book, manage, and review services from one simple dashboard.</li>
                <li><strong>Great Help</strong> Standard help services from us or your local town/city people</li>
            </ul>
            <p class="cta">
                <a class="btn btn-primary" href="pages.php?page=services">Explore Services</a>
                <a class="btn btn-secondary" href="pages.php?page=signup">Join Now</a>
            </p>
        </section>
        <?php
        render_footer();
        break;

    case 'services':
        render_header('Services');
        ?>
        <section class="services-grid">
            <p>Discover local services organized by category. Click a category to see available providers.</p>
            <div class="grid">
                <div class="service-card">
                    <h3>Home Repair</h3>
                    <p>Handyman services, small repairs, and maintenance.</p>
                </div>
                <div class="service-card">
                    <h3>Cleaning</h3>
                    <p>Residential and commercial cleaning services.</p>
                </div>
                <div class="service-card">
                    <h3>Gardening</h3>
                    <p>Lawn care, planting, and landscape maintenance.</p>
                </div>
                <div class="service-card">
                    <h3>IT & Tech</h3>
                    <p>Setup, troubleshooting, and device support.</p>
                </div>
            </div>
            <p class="note">Don't see what you need? <a href="pages.php?page=contact">Contact us</a> and we'll help.</p>
        </section>
        <?php
        render_footer();
        break;

    case 'create_job':
        render_header('Create Job');
        ?>
        <section class="lead">
            <p>Create a job listing to reach local providers. Fill in the details below.</p>
        </section>

        <section class="services-grid">
            <div class="grid" style="grid-template-columns: 1fr 340px; gap:18px; align-items:start;">
                <div>
                    <form id="createPostForm" class="contact-form" method="post" action="#">
                        <input type="hidden" name="action" value="create_post">
                        <div class="form-message" aria-live="polite"></div>
                        <div class="form-group">
                            <label for="job-title">Title</label>
                            <input id="job-title" name="title" type="text" required placeholder="Fix my leaking tap">
                        </div>
                        <div class="form-group">
                            <label for="job-desc">Description</label>
                            <textarea id="job-desc" name="description" rows="6" required placeholder="Describe the work, any access details, and preferred schedule."></textarea>
                        </div>
                        <div class="form-group" style="display:flex;gap:12px;flex-wrap:wrap">
                            <div style="flex:1;min-width:180px">
                                <label for="job-category">Category</label>
                                <input id="job-category" name="category" type="text" placeholder="e.g. Plumbing">
                            </div>
                            <div style="width:140px">
                                <label for="job-budget">Budget</label>
                                <input id="job-budget" name="budget" type="number" min="0" step="1" placeholder="NOK">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="job-location">Location</label>
                            <input id="job-location" name="location" type="text" placeholder="City or postcode">
                        </div>
                        <button class="btn btn-primary" type="submit">Create Job</button>
                    </form>
                </div>

                <aside class="service-card" style="position:relative">
                    <div style="margin-bottom:12px">
                        <div class="column-image" style="width:100%;height:160px;">
                            <img src="https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=800&q=60" alt="Job help">
                        </div>
                    </div>
                    <h3 style="margin-top:8px">Tips for good job posts</h3>
                    <ul class="page-list">
                        <li>Give a clear title and concise description.</li>
                        <li>Include a realistic budget or mark as negotiable.</li>
                        <li>Mention access, parking, or required tools.</li>
                    </ul>
                    <div style="margin-top:12px">
                        <h4 style="margin:6px 0">Sample budgets</h4>
                        <div class="small-muted">Quick reference for common tasks</div>
                        <ul style="margin-top:8px">
                            <li>Small repair: 200–500 NOK</li>
                            <li>Half-day job: 800–1500 NOK</li>
                            <li>Full-day job: 1500+ NOK</li>
                        </ul>
                    </div>
                </aside>
            </div>
        </section>
        <?php
        render_footer();
        break;

    case 'jobs':
        render_header('Find Jobs');
        ?>
        <section class="lead">
            <p>Browse available jobs nearby. Use the search and filters to narrow results.</p>
        </section>
        <section class="services-grid">
            <div class="grid" style="grid-template-columns: 320px 1fr; gap:18px; align-items:start;">
                <aside class="service-card">
                    <h3>Search & Filters</h3>
                    <form id="jobsSearchForm" class="auth-form" method="post" action="#">
                        <input type="hidden" name="action" value="list_jobs">
                        <div class="form-group">
                            <label for="search-q">Keyword</label>
                            <input name="q" id="search-q" type="search" placeholder="Search title or description">
                        </div>
                        <div class="form-group">
                            <label for="search-category">Category</label>
                            <input name="category" id="search-category" type="text" placeholder="e.g. Cleaning">
                        </div>
                        <div class="form-group">
                            <label for="search-location">Location</label>
                            <input name="location" id="search-location" type="text" placeholder="City or postcode">
                        </div>
                        <div style="display:flex;gap:8px">
                            <div style="flex:1">
                                <label for="search-min">Min</label>
                                <input name="min_budget" id="search-min" type="number" placeholder="Min">
                            </div>
                            <div style="flex:1">
                                <label for="search-max">Max</label>
                                <input name="max_budget" id="search-max" type="number" placeholder="Max">
                            </div>
                        </div>
                        <div style="margin-top:12px;display:flex;gap:8px">
                            <button id="jobsSearchBtn" class="btn btn-primary" type="submit">Search</button>
                            <button id="jobsResetBtn" type="button" class="btn btn-secondary">Reset</button>
                        </div>
                    </form>
                    <div style="margin-top:14px" class="small-muted">Tip: leave filters empty to show latest jobs.</div>
                </aside>

                <div>
                    <div id="jobsList"></div>
                </div>
            </div>
        </section>
        <?php
        render_footer();
        break;

    case 'contact':
        render_header('Contact');
        ?>
        <section class="contact-section">
            <p>If you have a question or need help finding a provider, send us a message and we'll respond as soon as possible.</p>

            <form id="contactForm" class="contact-form" method="POST" action="#">
                <div class="form-group">
                    <label for="name">Name</label>
                    <input id="name" name="name" type="text" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" required>
                </div>
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="5" required></textarea>
                </div>
                <div class="form-message" aria-live="polite"></div>
                <button class="btn btn-primary" type="submit">Send Message</button>
            </form>

            <div class="contact-details">
                <h3>Other ways to reach us</h3>
                <p>Email: <a href="mailto:support@finnhustle.example">example@lokaletjenester.no</a></p>
                <p>Hours: Mon–Fri, 09:00–17:00</p>
            </div>
        </section>
        <?php
        render_footer();
        
        break;
    case 'verify':
        render_header('Verify Email');
        $token = $_GET['token'] ?? '';
        
        if ($token) {
            $stmt = $db->prepare("SELECT user_id FROM email_tokens WHERE token=? LIMIT 1");
            $stmt->execute([$token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // Mark verified
                $db->prepare("UPDATE users SET verified=1 WHERE id=?")->execute([$row['user_id']]);
                $db->prepare("DELETE FROM email_tokens WHERE user_id=?")->execute([$row['user_id']]);
                echo "<p>Email verified successfully! You now have full access.</p>";
            } else {
                echo "<p>Invalid or expired verification link.</p>";
            }
        } else {
            echo "<p>No token provided.</p>";
        }
        render_footer();
        break;
        
    case 'resend_verification':
    if ($is_logged_in && isset($user_id, $user_email)) {
        if (send_verification_email($user_email, $user_id)) {
            echo json_encode(['success'=>true,'message'=>'Verification email resent!']);
        } else {
            echo json_encode(['success'=>false,'message'=>'Failed to send verification email.']);
        }
    } else {
        echo json_encode(['success'=>false,'message'=>'You must be logged in.']);
    }
    exit;

    case 'profile':
        render_header('Profile');
        if ($is_logged_in) {
            ?>
            <div class="profile-section">
                <h2>Your account</h2>
                <p>Welcome back, <strong><?php echo htmlspecialchars($user_name); ?></strong>.</p>
                <ul>
                    <li><strong>Email:</strong> <?php echo htmlspecialchars($user_email ?? ''); ?></li>
                    <li><strong>Member since:</strong> <?php echo htmlspecialchars($user_created ? date('Y-m-d', strtotime($user_created)) : '—'); ?></li>
                </ul>
                <p class="mt-16">
                    <a class="btn btn-primary" href="pages.php?page=dashboard">Go to Dashboard</a>
                    <a class="btn btn-secondary" href="#settingsForm">Account Settings</a>
                </p>

                <h3 style="margin-top:18px;">Edit Settings</h3>
                <form id="settingsForm" class="settings-form">
                    <input type="hidden" name="action" value="update_settings">
                    <div class="form-message" aria-live="polite"></div>
                    <div class="form-group">
                        <label for="profile-username">Username</label>
                        <input id="profile-username" name="username" type="text" value="<?php echo htmlspecialchars($user_name); ?>" required>
                        <div class="field-error" data-for="profile-username"></div>
                    </div>
                    <div class="form-group">
                        <label for="profile-email">Email</label>
                        <input id="profile-email" name="email" type="email" value="<?php echo htmlspecialchars($user_email ?? ''); ?>" required>
                        <div class="field-error" data-for="profile-email"></div>
                    </div>
                    <div class="form-group">
                        <?php $curSess = intval($user_session_duration ?? 604800); ?>
                        <label for="profile-session">Session length</label>
                        <select id="profile-session" name="session_duration">
                            <option value="14400" <?php echo $curSess===14400 ? 'selected' : ''; ?>>4 hours</option>
                            <option value="86400" <?php echo $curSess===86400 ? 'selected' : ''; ?>>1 day</option>
                            <option value="259200" <?php echo $curSess===259200 ? 'selected' : ''; ?>>3 days</option>
                            <option value="604800" <?php echo $curSess===604800 ? 'selected' : ''; ?>>7 days</option>
                            <option value="2592000" <?php echo $curSess===2592000 ? 'selected' : ''; ?>>30 days</option>
                            <option value="5184000" <?php echo $curSess===5184000 ? 'selected' : ''; ?>>60 days</option>
                        </select>
                        <div class="small-muted">Choose how long your login stays active on this device.</div>
                    </div>
                    <button class="btn btn-primary" type="submit">Save Settings</button>
                </form>

                <h3 style="margin-top:18px;">Change Password</h3>
                <form id="passwordForm" class="settings-form">
                    <div class="form-message" aria-live="polite"></div>
                    <div class="form-group">
                        <label for="current-password">Current Password</label>
                        <input id="current-password" name="current_password" type="password" required>
                        <div class="field-error" data-for="current-password"></div>
                    </div>
                    <div class="form-group">
                        <label for="new-password">New Password</label>
                        <input id="new-password" name="new_password" type="password" required>
                        <div class="field-error" data-for="new-password"></div>
                        <div class="small-muted">Choose a password with at least 6 characters.</div>
                    </div>
                    <div class="form-group">
                        <label for="confirm-password">Confirm New Password</label>
                        <input id="confirm-password" name="confirm_password" type="password" required>
                        <div class="field-error" data-for="confirm-password"></div>
                    </div>
                    <button class="btn btn-primary" type="submit">Change Password</button>
                </form>

                <h3 style="margin-top:18px;">Email Verification</h3>
                <div class="lead" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <div>
                        <div><strong>Email:</strong> <?php echo htmlspecialchars($user_email ?? ''); ?></div>
                        <div class="small-muted">Status: <?php echo (isset($user_email) && $user_email? ( (isset($user_created) && $user_created && isset($is_logged_in) ) ? 'Unknown' : 'Unknown' ) : '—'); ?></div>
                    </div>
                    <div>
                        <button id="resendVerifyBtn" class="btn btn-secondary">Resend verification</button>
                    </div>
                </div>
            </div>
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
            <div class="settings-section">
                <h2>Account Settings</h2>
                <form class="settings-form" method="post" action="#">
                    <input type="hidden" name="action" value="update_settings">
                    <div class="form-group">
                        <label for="display-name">Display name</label>
                        <input id="display-name" name="username" type="text" value="<?php echo htmlspecialchars($user_name); ?>">
                    </div>
                    <div class="form-group">
                        <?php $curSess = intval($user_session_duration ?? 604800); ?>
                        <label for="site-session">Session length</label>
                        <select id="site-session" name="session_duration">
                            <option value="14400" <?php echo $curSess===14400 ? 'selected' : ''; ?>>4 hours</option>
                            <option value="86400" <?php echo $curSess===86400 ? 'selected' : ''; ?>>1 day</option>
                            <option value="259200" <?php echo $curSess===259200 ? 'selected' : ''; ?>>3 days</option>
                            <option value="604800" <?php echo $curSess===604800 ? 'selected' : ''; ?>>7 days</option>
                            <option value="2592000" <?php echo $curSess===2592000 ? 'selected' : ''; ?>>30 days</option>
                            <option value="5184000" <?php echo $curSess===5184000 ? 'selected' : ''; ?>>60 days</option>
                        </select>
                        <div class="small-muted">How long to remain logged in on this device.</div>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" checked> Receive email notifications
                        </label>
                    </div>
                    <button class="btn btn-primary" type="submit">Save changes</button>
                </form>
            </div>
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
            <div class="dashboard-section">
                <h2>Overview</h2>
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <h3>5</h3>
                        <p>Active Services</p>
                    </div>
                    <div class="stat-card">
                        <h3>12</h3>
                        <p>Total Bookings</p>
                    </div>
                    <div class="stat-card">
                        <h3>4.8</h3>
                        <p>Your Rating</p>
                    </div>
                </div>

                <h3 class="mt-20">Recent activity</h3>
                <ul class="activity-list">
                    <li>Booked cleaning service — 2 days ago</li>
                    <li>Left a review for John — 1 week ago</li>
                </ul>
            </div>
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
        <div class="auth-section">
            <p>Log in to your Finn Hustle account to manage bookings, providers, and profile settings.</p>
            <form id="loginPageForm" method="post" class="auth-form">
                <input type="hidden" name="action" value="login">
                <div class="form-message" aria-live="polite"></div>
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input id="username" name="username" type="text" required placeholder="username or email">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required>
                </div>
                <button class="btn btn-primary" type="submit">Log In</button>
            </form>
            <p class="auth-link">Don't have an account? <a href="pages.php?page=signup">Sign up</a></p>
        </div>
        <?php
        render_footer();
        break;

    case 'signup':
        render_header('Sign Up');
        if ($action === 'signup') {
            $username = $_POST['username'];
            $email    = $_POST['email'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

            $stmt = $db->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $password]);
            $user_id = $db->lastInsertId();

            if (send_verification_email($email, $user_id)) {
                echo json_encode(['success'=>true,'message'=>'Signup successful! Check your email to verify.']);
            } else {
                echo json_encode(['success'=>false,'message'=>'Signup successful but failed to send email.']);
            }
            exit;
}

        ?>
        <div class="auth-section">
            <p>Create an account to start booking services and managing your listings.</p>
            <form id="signupPageForm" method="post" class="auth-form">
                <input type="hidden" name="action" value="signup">
                <div class="form-message" aria-live="polite"></div>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required>
                </div>
                <button class="btn btn-primary" type="submit">Create Account</button>
            </form>
            <p class="auth-link">Already have an account? <a href="pages.php?page=login">Log in</a></p>
        </div>
        <?php
        render_footer();
        break;
}
