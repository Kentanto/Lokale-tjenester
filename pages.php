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
    global $user_name, $is_logged_in, $is_admin, $conn, $user_id;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="icon" type="image/png" href="assets/Lokale_Tjenester_only_logo.png">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="static/style.css">
    <title><?php echo htmlspecialchars($title); ?> - Lokale Tjenester</title>
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
                <a href="index.php">Hjem</a>
                <a href="pages.php?page=about">Om</a>
                <a href="pages.php?page=services">Tjenester</a>
                <a href="pages.php?page=contact">Kontakt</a>
            </div>
            <div class="footer-right">
                <span>&copy; <?php echo date('Y'); ?> Lokale Tjenester</span>
                <span class="footer-sep">|</span>
                <a href="LICENSE" class="license-link">Basic Fair Use (NOR)</a>
            </div>
        </div>
    </footer>

</div> <!-- .page-wrapper -->

<!-- Job Detail Modal (positioned at body level for fixed positioning) -->
<div id="jobDetailModal" class="job-detail-modal" style="display: none;">
    <div class="job-detail-overlay"></div>
    <div class="job-detail-box">
        <button class="job-detail-close" onclick="document.getElementById('jobDetailModal').style.display = 'none';">&times;</button>
        <div class="job-detail-content" id="jobDetailContent">
            <p>Loading...</p>
        </div>
    </div>
</div>

<script src="static/script.js"></script>
</body>
</html>
<?php
}

// Page content
switch ($page) {
    case 'about':
        render_header('Om');
        ?>
        <section class="lead">
            <p>Velkommen til <strong>Lokale Tjenester</strong> — din lokale hub for å finne pålitelige tjenester i nærheten. Vi kobler
            hjemmeier og små bedrifter med lokale, for å få ting gjort raskt og pålitelig!</p>
        </section>

        <section class="features">
            <h2>hvorfor velge oss?</h2>
            <ul class="page-list">
                <li><strong>Pålitelige leverandører:</strong> Alle leverandører er vurdert og rangert av ekte brukere.</li>
                <li><strong>Enkel bestilling:</strong> Bestill, administrer og vurder tjenester fra ett enkelt dashbord.</li>
                <li><strong>God hjelp:</strong> Standard hjelpetjenester fra oss eller folk i din lokale by/kommune.</li>
            </ul>
            <p class="cta">
                <a class="btn btn-primary" href="pages.php?page=services">Utforsk tjenester</a>
                <a class="btn btn-secondary" href="pages.php?page=signup">Bli med nå</a>
            </p>
        </section>
        <?php
        render_footer();
        break;

    case 'services':
        render_header('Tjenester');
        ?>
        <section class="services-grid">
            <p>Oppdag lokale tjenester organisert etter kategori. Klikk på en kategori for å se tilgjengelige leverandører.</p>
            <div class="grid">
                <div class="service-card">
                    <h3>Hjemmereparasjon</h3>
                    <p>Vaktmestertjenester, små reparasjoner og vedlikehold.</p>
                </div>
                <div class="service-card">
                    <h3>Renhold</h3>
                    <p>Renholdstjenester for bolig og næring.</p>
                </div>
                <div class="service-card">
                    <h3>Hagearbeid</h3>
                    <p>Plenpleie, planting og vedlikehold av uteområder.</p>
                </div>
                <div class="service-card">
                    <h3>IT & Teknologi</h3>
                    <p>Oppsett, feilsøking og støtte for enheter.</p>
                </div>
            </div>
            <p class="note">Finner du ikke det du trenger? <a href="pages.php?page=contact">Kontakt oss</a>, så hjelper vi deg.</p>
        </section>
        <?php
        render_footer();
        break;

    case 'create_job':
            render_header('Lag en jobbannonse');
            ?>
            <section class="lead">
                <p>Opprett en jobbannonse. Fyll inn detaljene nedenfor.</p>
                <?php
                    if($is_logged_in && !empty($user_id)) {
                        $remaining = get_user_remaining_posts($conn, $user_id);
                        if($remaining <= 0) {
                            echo '<div style="background: #ffebee; border-left: 4px solid #f44336; padding: 10px; margin-bottom: 15px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">';
                            echo '<div><strong>Daglig grense nådd:</strong> Du har postet 3 ganger i dag. Prøv igjen i morgen!</div>';
                            if(!empty($is_admin)) {
                                echo '<button id="resetLimitBtn" style="padding: 6px 12px; background: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">Reset (admin)</button>';
                            }
                            echo '</div>';
                        }
                    }
                ?>
            </section>

            <section class="create-job-section">
                <div class="create-job-container">
                    <main class="create-job-form-wrapper">
                        <form id="createPostForm" class="contact-form" method="post" action="#" enctype="multipart/form-data" data-remaining-posts="<?php echo ($is_logged_in && !empty($user_id)) ? htmlspecialchars(get_user_remaining_posts($conn, $user_id)) : '3'; ?>">
                            <input type="hidden" name="action" value="create_post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                            <div class="form-message" aria-live="polite"></div>
                            
                            <fieldset class="form-fieldset">
                                <legend class="sr-only">Jobbannonse detaljer</legend>
                                <div class="form-group">
                                    <label for="job-title">Tittel</label>
                                    <input id="job-title" name="title" type="text" required placeholder="Fiks en lekkende kran">
                                </div>
                                <div class="form-group">
                                    <label for="job-desc">Beskrivelse <span class="char-counter" id="desc-counter">0/2000</span></label>
                                    <textarea id="job-desc" name="description" rows="6" required placeholder="Beskriv arbeidet, eventuelle tilgangsdetaljer og ønsket tidspunkt." maxlength="2000"></textarea>
                                </div>
                                <div class="form-row form-row-two-cols">
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="job-category">Kategori</label>
                                            <select id="job-category" name="category" required>
                                                <option value="not specified" selected>Ikke spesifisert</option>
                                                <option value="husarbeid">Husarbeid</option>
                                                <option value="kjøkkenarbeid">Kjøkkenarbeid</option>
                                                <option value="utearbeid">Utearbeid</option>
                                                <option value="hagearbeid">Hagearbeid</option>
                                                <option value="vinterarbeid">Vinterarbeid</option>
                                                <option value="dyrestell">Dyrestell</option>
                                                <option value="handling / ærender">Handling / ærender</option>
                                                <option value="barnepass">Barnepass</option>
                                                <option value="vedlikehold">Vedlikehold</option>
                                                <option value="andre">Andre</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-col form-col-narrow">
                                        <div class="form-group">
                                            <label for="job-budget">Budsjett</label>
                                            <input id="job-budget" name="budget" type="number" min="0" max="99999" step="1" placeholder="NOK">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="job-location">Sted</label>
                                    <input id="job-location" name="location" type="text" placeholder="By eller postnummer">
                                </div>
                                <div class="form-group">
                                    <label for="job-contact">Kontaktinformasjon <span class="char-counter" id="contact-counter">0/30</span></label>
                                    <input id="job-contact" name="contact_info" type="text" placeholder="Telefon, e-post eller annen kontaktinformasjon" required maxlength="30">
                                </div>
                                <div class="form-group">
                                    <label for="job-image">Bilde (valgfritt)</label>
                                    <input id="job-image" name="image" type="file" accept="image/*" aria-describedby="image-help">
                                    <small id="image-help">Tillatte format: JPEG, PNG, GIF, WebP. Maks 5 MB. Bildet vil bli komprimert automatisk.</small>
                                </div>
                            </fieldset>
                            
                            <button class="btn btn-primary btn-large" type="submit">Opprett jobb</button>
                        </form>
                    </main>

                    <aside class="create-job-tips">
                        <div class="tips-card">
                            <div class="tips-image">
                                <img src="https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=800&q=60" alt="Jobbhjelp illustrasjon">
                            </div>
                            <h3>Tips for gode jobbannonser</h3>
                            <ul class="tips-list">
                                <li>Gi en tydelig tittel og en kortfattet beskrivelse.</li>
                                <li>Inkluder et realistisk budsjett eller merk som forhandlingsbart.</li>
                                <li>Nevn tilgang, parkering eller nødvendige verktøy.</li>
                            </ul>
                            <div class="tips-budget-section">
                                <h4>Eksempelbudsjetter</h4>
                                <div class="tips-subtext">Rask referanse for vanlige oppdrag</div>
                                <ul class="budget-list">
                                    <li><strong>Liten reparasjon:</strong> 200–500 NOK</li>
                                    <li><strong>Halvdagsjobb:</strong> 800–1500 NOK</li>
                                    <li><strong>Heldagsjobb:</strong> 1500+ NOK</li>
                                </ul>
                            </div>
                        </div>
                    </aside>
                </div>
            </section>

            <!-- Job Success & Donation Modal -->
            <div id="donationModal" class="donation-modal" style="display: none;">
                <div class="donation-overlay"></div>
                <div class="donation-box">
                    <button class="donation-close" onclick="document.getElementById('donationModal').style.display = 'none';">&times;</button>
                    <div class="donation-content">
                        <h2>Jobb Publisert!</h2>
                        <p><strong>Din jobbannonse har blitt sendt inn.</strong></p>
                        <p>Din annonse venter på godkjenning fra vårt moderator-team. Du vil bli varslet når den er publisert.</p>
                        
                        <hr style="margin: 25px 0; border: none; border-top: 1px solid #e0e0e0;">
                        
                        <h3 style="margin-top: 20px; font-size: 18px; color: #2e7d32;">💚 Støtt Lokale Tjenester</h3>
                        <p>Lokale tjenester er laget av ett lite team. Hvis du liker hva vi gjør og vil hjelpe oss å holde det gående, setter vi pris på en donasjon.</p>
                        <div class="donation-vipps" style="background: #ff5b24; color: white; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;">
                            <p style="margin: 0 0 10px 0; font-size: 14px; color: #fff5f0;">Doner via Vipps:</p>
                            <p style="margin: 0; font-size: 32px; font-weight: bold;">41789</p>
                        </div>
                        <p style="font-size: 13px; color: #888;"> <em>100% av donasjonen går til vedlikehold og utvikling av plattformen.</em></p>
                        <div class="donation-buttons">
                            <button class="btn btn-primary" onclick="document.getElementById('donationModal').style.display = 'none';">Lukk</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            render_footer();
            break;


    case 'jobs':
        render_header('Finn jobber');
        ?>
        <section class="lead">
            <p>Bla gjennom tilgjengelige jobber i nærheten. Bruk søk og filtre for å avgrense resultatene.</p>
        </section>
        
        <section class="find-jobs-section">
            <div class="find-jobs-container">
                <aside class="find-jobs-sidebar">
                    <div class="filter-card">
                        <h3>Søk og filtre</h3>
                        <form id="jobsSearchForm" class="search-form" method="post" action="#">
                            <input type="hidden" name="action" value="list_jobs">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                            
                            <fieldset class="form-fieldset">
                                <legend class="sr-only">Jobbsøk filtre</legend>
                                
                                <div class="form-group">
                                    <label for="search-q">Søkeord</label>
                                    <input name="q" id="search-q" type="search" placeholder="Søk i tittel eller beskrivelse">
                                </div>
                                
                                <div class="form-group">
                                    <label for="search-category">Kategori</label>
                                    <select name="category" id="search-category">
                                        <option value="not specified" selected>Ikke spesifisert</option>
                                        <option value="husarbeid">Husarbeid</option>
                                        <option value="kjøkkenarbeid">Kjøkkenarbeid</option>
                                        <option value="utearbeid">Utearbeid</option>
                                        <option value="hagearbeid">Hagearbeid</option>
                                        <option value="vinterarbeid">Vinterarbeid</option>
                                        <option value="dyrestell">Dyrestell</option>
                                        <option value="handling / ærender">Handling / ærender</option>
                                        <option value="barnepass">Barnepass</option>
                                        <option value="vedlikehold">Vedlikehold</option>
                                        <option value="andre">Andre</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="search-location">Sted</label>
                                    <input name="location" id="search-location" type="text" placeholder="By eller postnummer">
                                </div>
                                
                                <div class="form-row form-row-budget">
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="search-min">Min budsjett</label>
                                            <input name="min_budget" id="search-min" type="number" placeholder="Min">
                                        </div>
                                    </div>
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="search-max">Maks budsjett</label>
                                            <input name="max_budget" id="search-max" type="number" placeholder="Maks">
                                        </div>
                                    </div>
                                </div>
                            </fieldset>
                            
                            <div class="filter-actions">
                                <button id="jobsSearchBtn" class="btn btn-primary btn-block" type="submit">Søk</button>
                                <button id="jobsResetBtn" type="button" class="btn btn-secondary btn-block">Nullstill</button>
                            </div>
                        </form>
                        <p class="filter-tip">Tips: la filtrene stå tomme for å vise de nyeste jobbene.</p>
                    </div>
                </aside>

                <main class="find-jobs-main">
                    <div id="jobsList" class="jobs-list-container"></div>
                </main>
            </div>
        </section>

        <!-- Job Detail Modal -->
        <div id="jobDetailModal" class="job-detail-modal" style="display: none;">
            <div class="job-detail-overlay"></div>
            <div class="job-detail-box">
                <button class="job-detail-close" onclick="document.getElementById('jobDetailModal').style.display = 'none';">&times;</button>
                <div class="job-detail-content" id="jobDetailContent">
                    <p>Laster inn...</p>
                </div>
            </div>
        </div>
        <?php
        render_footer();
        break;


    case 'contact':
            render_header('Kontakt oss');
            ?>
            <section class="contact-section">
                <p>Hvis du har et spørsmål eller trenger hjelp med å finne en leverandør, send oss en melding, så svarer vi så snart som mulig.</p>

                <form id="contactForm" class="contact-form" method="POST" action="#">
                    <input type="hidden" name="action" value="contact">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                    <div class="form-group">
                        <label for="name">Navn</label>
                        <input id="name" name="name" type="text" required>
                    </div>
                    <div class="form-group">
                        <label for="email">E-post</label>
                        <input id="email" name="email" type="email" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Melding</label>
                        <textarea id="message" name="message" rows="5" required></textarea>
                    </div>
                    <div class="form-message" aria-live="polite"></div>
                    <button class="btn btn-primary" type="submit">Send melding</button>
                </form>

                <div class="contact-details">
                    <h3>Andre måter å kontakte oss på</h3>
                    <p>E-post: <a href="mailto:lokaletjenester.gjovik@gmail.com">lokaletjenester.gjovik@gmail.com</a></p>
                    <p>Åpningstider: Man–Fre, 09:00–17:00</p>
                </div>
            </section>
            <?php
            render_footer();
            
            break;



    case 'verify':
        render_header('Bekreft e-post');

        $token = $_GET['token'] ?? '';

        if ($token) {
            $stmt = $conn->prepare(
                "SELECT user_id FROM email_tokens WHERE token = ? AND created_at > NOW() - INTERVAL 24 HOUR LIMIT 1"
            );
            $stmt->bind_param('s', $token);
            $stmt->execute();
            $stmt->bind_result($uid);
            $stmt->fetch();
            $stmt->close();

            if ($uid) {
                $stmt = $conn->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM email_tokens WHERE user_id = ?");
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $stmt->close();

                echo "<p>E-post verifisert! Du har nå full tilgang.</p>";
            } else {
                echo "<p>Ugyldig eller utløpt verifiseringslenke.</p>";
            }
        } else {
            echo "<p>Ingen token Gitt.</p>";
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
        render_header('Profil');
        if ($is_logged_in) {
            ?>
            <div class="profile-section">
                <!-- Welcome Card -->
                <div class="profile-welcome-card">
                    <div style="display: flex; gap: 20px; align-items: flex-start;">
                        <div>
                            <?php
                                $profilePicUrl = get_profile_picture_url($conn, $user_id);
                                if($profilePicUrl) {
                                    echo '<img src="' . htmlspecialchars($profilePicUrl) . '" alt="Profile" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #ddd;">';
                                } else {
                                    echo '<div style="width: 100px; height: 100px; border-radius: 50%; background: #e0e0e0; display: flex; align-items: center; justify-content: center; font-size: 40px;">👤</div>';
                                }
                            ?>
                        </div>
                        <div style="flex: 1;">
                            <h2>Velkommen tilbake, <?php echo htmlspecialchars($user_name); ?></h2>
                            <p>Her administrerer du kontoen din og innstillingene.</p>
                            <div class="profile-info">
                                <div class="profile-info-item">
                                    <strong>E-post</strong>
                                    <span><?php echo htmlspecialchars($user_email ?? ''); ?></span>
                                </div>
                                <div class="profile-info-item">
                                    <strong>Medlem siden</strong>
                                    <span><?php echo htmlspecialchars($user_created ? date('d. M Y', strtotime($user_created)) : '—'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="profile-actions">
                        <a class="btn btn-primary" href="pages.php?page=dashboard">📊 Kontrollpanel</a>
                        <a class="btn btn-secondary" href="#settingsForm">⚙️ Innstillinger</a>
                    </div>
                </div>

                <!-- Action Cards Grid -->
                <div class="profile-cards-grid">
                    <!-- Upload Profile Picture Card -->
                    <div class="profile-card">
                        <h3><span class="profile-card-icon">📷</span> Profilbilde</h3>
                        <form id="profilePictureForm" class="settings-form" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_profile_picture">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                            <div class="form-message" aria-live="polite"></div>
                            <div class="form-group">
                                <label for="profile-picture-input">Velg bilde</label>
                                <input id="profile-picture-input" name="profile_picture" type="file" accept="image/*" required>
                                <small>Maks 5 MB. Vil bli konvertert til 200x200 px.</small>
                            </div>
                            <button class="btn btn-primary" type="submit">Last opp bilde</button>
                        </form>
                    </div>
                    <!-- Edit Profile Card -->
                    <div class="profile-card">
                        <h3><span class="profile-card-icon">👤</span> Rediger profil</h3>
                        <form id="settingsForm" class="settings-form">
                            <input type="hidden" name="action" value="update_settings">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                            <div class="form-message" aria-live="polite"></div>
                            <div class="form-group">
                                <label for="profile-username">Brukernavn</label>
                                <input id="profile-username" name="username" type="text" value="<?php echo htmlspecialchars($user_name); ?>" required placeholder="Ditt brukernavn">
                                <div class="field-error" data-for="profile-username"></div>
                            </div>
                            <div class="form-group">
                                <label for="profile-email">E-post</label>
                                <input id="profile-email" name="email" type="email" value="<?php echo htmlspecialchars($user_email ?? ''); ?>" required placeholder="Din e-postadresse">
                                <div class="field-error" data-for="profile-email"></div>
                            </div>
                            <button class="btn btn-primary" type="submit">Lagre endringer</button>
                        </form>
                    </div>

                    <!-- Change Password Card -->
                    <div class="profile-card">
                        <h3><span class="profile-card-icon">🔒</span> Endre passord</h3>
                        <form id="passwordForm" class="settings-form">
                            <input type="hidden" name="action" value="change_password">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                            <div class="form-message" aria-live="polite"></div>
                            <div class="form-group">
                                <label for="current-password">Nåværende passord</label>
                                <input id="current-password" name="current_password" type="password" required placeholder="Ditt nåværende passord">
                                <div class="field-error" data-for="current-password"></div>
                            </div>
                            <div class="form-group">
                                <label for="new-password">Nytt passord</label>
                                <input id="new-password" name="new_password" type="password" required placeholder="Velg et sterkt passord">
                                <div class="field-error" data-for="new-password"></div>
                                <div class="small-muted">Minst 6 tegn. Velg noe som er vanskelig å gjette.</div>
                            </div>
                            <div class="form-group">
                                <label for="confirm-password">Bekreft passord</label>
                                <input id="confirm-password" name="confirm_password" type="password" required placeholder="Gjenta det nye passordet">
                                <div class="field-error" data-for="confirm-password"></div>
                            </div>
                            <button class="btn btn-primary" type="submit">Oppdater passord</button>
                        </form>
                    </div>
                </div>

                <!-- Bio Section -->
                <div class="profile-verification-card">
                    <h3>👤 Om meg</h3>
                    <form id="bioForm" class="settings-form" style="margin-top: 12px;">
                        <input type="hidden" name="action" value="update_settings">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                        <input type="hidden" name="username" value="<?php echo htmlspecialchars($user_name); ?>">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($user_email ?? ''); ?>">
                        <div class="form-message" aria-live="polite"></div>
                        <div class="form-group">
                            <label for="profile-bio" style="display: flex; justify-content: space-between; align-items: center;">
                                Bio
                                <span class="char-counter"><span id="bio-char-count">0</span>/800</span>
                            </label>
                            <textarea id="profile-bio" name="bio" rows="5" placeholder="Fortell litt om deg selv..." maxlength="800" style="width: 100%; resize: vertical; word-wrap: break-word; overflow-wrap: break-word; white-space: pre-wrap;"><?php echo htmlspecialchars($user_bio ?? ''); ?></textarea>
                            <small style="display: block; margin-top: 6px; color: var(--muted);">Maks 800 tegn</small>
                        </div>
                        <button class="btn btn-primary" type="submit">Lagre</button>
                    </form>
                </div>

                <!-- Email Verification Section (Only if not verified) -->
                <?php if (!$email_verified): ?>
                <div class="profile-verification-card">
                    <h3>✉️ E-postverifisering</h3>
                    <div class="profile-verification-content">
                        <div>
                            <p><strong>E-post:</strong> <?php echo htmlspecialchars($user_email ?? ''); ?></p>
                            <div class="profile-verification-status unverified">
                                <span class="verification-indicator"></span>
                                Ikke verifisert
                            </div>
                            <p class="small-muted" style="margin-top: 8px;">Verifiser e-posten din for å få full tilgang til alle funksjoner.</p>
                        </div>
                        <button id="resendVerifyBtn" class="btn btn-primary">Send verifiseringslenke</button>
                    </div>
                </div>
                <?php else: ?>
                <div class="profile-verification-card">
                    <h3>✉️ E-postverifisering</h3>
                    <div class="profile-verification-content">
                        <div>
                            <p><strong>E-post:</strong> <?php echo htmlspecialchars($user_email ?? ''); ?></p>
                            <div class="profile-verification-status verified">
                                <span class="verification-indicator"></span>
                                Verifisert
                            </div>
                            <p class="small-muted" style="margin-top: 8px;">Din e-post er verifisert. Du har full tilgang.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php
        } else {
            ?>
            <p>Du er ikke logget inn. <a href="pages.php?page=login">Logg inn</a></p>
            <?php
        }
        render_footer();
        break;


    case 'settings':
        render_header('Instillinger','settings');
        if ($is_logged_in) {
            ?>
            <div class="settings-section">
                <h2>Kontoinnstillinger</h2>
                <form class="settings-form" method="post" action="#">
                    <input type="hidden" name="action" value="update_settings">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                    <div class="form-group">
                        <label for="display-name">Visningsnavn</label>
                        <input id="display-name" name="username" type="text" value="<?php echo htmlspecialchars($user_name); ?>">
                    </div>
                    
                    <!-- Bio/Description field -->
                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" rows="4" placeholder="Fortell litt om deg selv..." maxlength="500"></textarea>
                        <small class="text-muted">Maks 500 tegn</small>
                    </div>
                    
                    <button class="btn btn-primary" type="submit">Lagre endringer</button>
                    <label style="margin-left:12px;">
                        <input type="checkbox" checked> Motta e-postvarsler
                    </label>
                </form>
            </div>



            <div class="settings-section">
                <h2>Endre passord</h2>
                <form id="passwordForm" class="settings-form">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                    <div class="form-message" aria-live="polite"></div>
                    <div class="form-group">
                        <label for="current-password">Nåværende passord</label>
                        <input id="current-password" name="current_password" type="password" required>
                        <div class="field-error" data-for="current-password"></div>
                    </div>
                    <div class="form-group">
                        <label for="new-password">Nytt passord</label>
                        <input id="new-password" name="new_password" type="password" required>
                        <div class="field-error" data-for="new-password"></div>
                        <div class="small-muted">Velg et passord med minst 6 tegn.</div>
                    </div>
                    <div class="form-group">
                        <label for="confirm-password">Bekreft nytt passord</label>
                        <input id="confirm-password" name="confirm_password" type="password" required>
                        <div class="field-error" data-for="confirm-password"></div>
                    </div>
                    <button class="btn btn-primary" type="submit">Endre passord</button>
                </form>
            </div>
            <?php
        } else {
            ?>
            <p>Vennligst <a href="pages.php?page=login">logg inn</a> for å administrere innstillingene.</p>
            <?php
        }
        render_footer();
        break;

    case 'dashboard':
        render_header('Dashboard');
        if ($is_logged_in) {
            ?>
            <div class="dashboard-section">
                <h2>Oversikt</h2>
                <div id="dashboardStats" class="dashboard-stats">
                    <div class="stat-card">
                        <h3 id="statActive">0</h3>
                        <p>Aktive tjenester</p>
                    </div>
                    <div class="stat-card">
                        <h3 id="statOrders">0</h3>
                        <p>Totale bestillinger</p>
                    </div>
                    <div class="stat-card">
                        <h3 id="statRating">0</h3>
                        <p>Din vurdering</p>
                    </div>
                </div>

                <h3 class="mt-20">Dine annonser</h3>
                <div id="userPostsContainer" class="user-posts-container">
                    <p>Laster inn...</p>
                </div>
            </div>
            <?php
        } else {
            ?>
            <p>Vennligst <a href="pages.php?page=login">logg inn</a> for å se kontrollpanelet.</p>
            <?php
        }
        render_footer();
        break;


    case 'login':
        render_header('Logg inn');
        ?>
        <div class="auth-section">
            <p>Logg inn på Lokale Tjenester-kontoen din for å administrere bestillinger, leverandører og profilinnstillinger.</p>
            <form id="loginPageForm" class="auth-form">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                <div class="form-message" aria-live="polite"></div>
                <div class="form-group">
                    <label for="username">Brukernavn eller e-post</label>
                    <input id="username" name="username" type="text" required placeholder="brukernavn eller e-post">
                </div>
                <div class="form-group">
                    <label for="password">Passord</label>
                    <input id="password" name="password" type="password" required>
                </div>
                <div class="form-group">
                    <label>
                        <input id="show_password" type="checkbox">
                        Vis passord
                    </label>
                </div>
                <button class="btn btn-primary" type="submit">Logg inn</button>
            </form>
            <p class="auth-link">Har du ikke en konto? <a href="pages.php?page=signup">Registrer deg</a></p>
        </div>
        <script>
        // Toggle password visibility
        document.getElementById('show_password').addEventListener('change', function(e) {
            const passwordInput = document.getElementById('password');
            passwordInput.type = e.target.checked ? 'text' : 'password';
        });

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
                    msgDiv.innerHTML = '<p style="color: green;">Innlogging vellykket! Omdirigerer...</p>';
                    setTimeout(() => window.location.href = 'pages.php?page=dashboard', 1000);
                } else {
                    msgDiv.innerHTML = '<p style="color: red;">' + (data.message || 'Innlogging mislyktes') + '</p>';
                }
            })
            .catch(err => {
                msgDiv.innerHTML = '<p style="color: red;">Feil: ' + err.message + '</p>';
            });
        });
        </script>
        <?php
        render_footer();
        break;


    case 'signup':
        render_header('Registrer');
        if ($action === 'signup') {
            $username = $_POST['username'];
            $email    = $_POST['email'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $username, $email, $password);
            $stmt->execute();
            $user_id = $conn->insert_id;


            if (send_verification_email($conn, $user_email, $user_id)) {
                echo json_encode(['success'=>true,'message'=>'Registrering suksess! sjekk din e-post for å verifisere. OBS husk å sjekke søppelpostmappen.']);
            } else {
                echo json_encode(['success'=>false,'message'=>'registrering suksess men feil ved sending av e-post.']);
            }
            exit;
}

        ?>
        <div class="auth-section">
            <p>Opprett en konto for å begynne å bestille tjenester og administrere annonsene dine.</p>
            <form id="signupPageForm" method="post" class="auth-form" autocomplete="off">
                <input type="hidden" name="action" value="signup">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                <div class="form-message" aria-live="polite"></div>
                <div class="form-group">
                    <label for="username">Brukernavn</label>
                    <input id="username" name="username" type="text" required>
                </div>
                <div class="form-group">
                    <label for="email">E-post</label>
                    <input id="email" name="email" type="email" title="Må bruke Gmail, iCloud, Hotmail, Outlook, Yahoo, eller norsk mail giver med .com as suffix" required>
                </div>
                <div class="form-group">
                    <label for="password">Passord</label>
                    <input id="password" name="password" type="password" required>
                </div>
                <button class="btn btn-primary" type="submit">Opprett konto</button>
            </form>
            <p class="auth-link">Har du allerede en konto? <a href="pages.php?page=login">Logg inn</a></p>
        </div>
        <?php
        render_footer();
        break;
        }

