<?php
    require_once 'display.php';
    $user_id = $_SESSION['user_id'] ?? null;
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
    <?php require_once 'navigation/navbar.php'; ?>

    <div class="coming-soon-container">
        <div class="coming-soon-content">
            <div class="coming-soon-logo">
                <img src="assets/Lokale_Tjenester_only_logo.png" alt="Lokale Tjenester Logo">
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
