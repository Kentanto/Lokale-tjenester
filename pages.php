<?php
require_once 'display.php';
$user_id = $_SESSION['user_id'] ?? null;


//Temp error code stack below
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
//Temp error code stack above


// Simple pages router: pages.php?page=about|services|contact|profile|settings|dashboard|login|signup
$page = isset($_GET['page']) ? $_GET['page'] : 'about';
$allowed = ['about','services','contact','profile','settings','dashboard','login','signup','create_job','jobs', 'verify','resend_verification'];
$action = $_POST['action'] ?? $_GET['action'] ?? null;
if (!in_array($page, $allowed)) {
    $page = 'about';
}

// Helper to render header
// $pageClass: optional additional class for the header (e.g. 'settings')
function render_header($title, $pageClass = '') {
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
    <?php require_once 'navigation/navbar.php'; ?>

<div class="page-wrapper">
    <main class="page-main">
        <div class="page-header<?php if ($pageClass) echo ' '.htmlspecialchars($pageClass); ?>">
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
            $stmt = $conn->prepare(
                "SELECT user_id FROM email_tokens WHERE token = ? LIMIT 1"
            );
            $stmt->bind_param('s', $token);
            $stmt->execute();
            $stmt->bind_result($uid);
            $stmt->fetch();
            $stmt->close();

            if ($uid) {
                $stmt = $conn->prepare("UPDATE users SET verified = 1 WHERE id = ?");
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM email_tokens WHERE user_id = ?");
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $stmt->close();

                echo "<p>Email verified successfully! You now have full access.</p>";
            } else {
                echo "<p>Invalid or expired verification link.</p>";
            }
        } else {
            echo "<p>No token provided.</p>";
        }

        render_footer();
        break;

    $user_email = $email;
    case 'resend_verification':
        if ($is_logged_in && !empty($user_id) && !empty($user_email)) {
            if (send_verification_email($conn, $user_email, $user_id)) {
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
        render_header('Settings','settings');
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
                    <button class="btn btn-primary" type="submit">Save changes</button>
                    <label style="margin-left:12px;">
                        <input type="checkbox" checked> Receive email notifications
                    </label>
                </form>
            </div>

            <div class="settings-section">
                <h2>Theme</h2>
                <div class="theme-toggle-box">
                    <label for="darkModeToggle" class="theme-label">Dark Mode</label>
                    <label class="switch">
                        <input type="checkbox" id="darkModeToggle">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="settings-section">
                <h2>Change Password</h2>
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
            <form id="loginPageForm" class="auth-form">
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
        <script>
        document.getElementById('loginPageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const msgDiv = this.querySelector('.form-message');
            
            fetch('display.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if(data.status === 'success') {
                    msgDiv.innerHTML = '<p style="color: green;">Login successful! Redirecting...</p>';
                    setTimeout(() => window.location.href = 'pages.php?page=dashboard', 1000);
                } else {
                    msgDiv.innerHTML = '<p style="color: red;">' + (data.message || 'Login failed') + '</p>';
                }
            })
            .catch(err => {
                msgDiv.innerHTML = '<p style="color: red;">Error: ' + err.message + '</p>';
            });
        });
        </script>
        <?php
        render_footer();
        break;

    case 'signup':
        render_header('Sign Up');
        if ($action === 'signup') {
            $username = $_POST['username'];
            $email    = $_POST['email'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $username, $email, $password);
            $stmt->execute();
            $user_id = $conn->insert_id;


            if (send_verification_email($conn, $user_email, $user_id)
) {
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
