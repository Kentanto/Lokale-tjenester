<?php
    // Keep minimal PHP in index. Session/user logic moved to `display.php`.
    // `display.php` will start the session and expose $is_logged_in and $user_name.
    require_once 'display.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="assets/Lokale_Tjenester_only_logo.png">
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
                <h2>Opprett jobb</h2>
                <p>Post en ny jobb raskt for å nå lokale leverandører — sett detaljer, budsjett og tilgjengelighet.</p>
                <div class="column-buttons">
                    <a href="pages.php?page=create_job" class="btn btn-primary">Post en jobb</a>
                </div>
            </div>
        </div>

        <div class="column">
            <div class="column-content">
                <div class="column-image">
                    <img src="https://media-cldnry.s-nbcnews.com/image/upload/t_fit-1500w,f_auto,q_auto:best/rockcms/2022-01/shoveling-snow-kb-main-220107-b13b2a.jpg" alt="Feature 2">
                </div>
                <h2>Finn jobb</h2>
                <p>Bla gjennom tilgjengelige jobber i nærheten eller søk etter kategori for å finne den rette matchen for dine ferdigheter.</p>
                <div class="column-buttons">
                    <a href="pages.php?page=jobs" class="btn btn-primary">Søk jobber</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms of Service Modal -->
    <div id="termsModal" class="modal">
        <div class="modal-content terms-modal-content">
            <div class="terms-header">
                <h2>Brukervilkår (Terms of Service)</h2>
                <p class="terms-date">Lokale Tjenester UB - Sist oppdatert: 04.03.2026</p>
            </div>
            <div class="terms-body">
                <p>Velkommen til Lokale Tjenester UB. Ved å bruke vår nettside eller tjenester godtar du disse brukervilkårene.</p>

                <h4>1. Om tjenesten</h4>
                <p>Lokale Tjenester UB er en digital plattform som kobler personer som trenger hjelp med småoppgaver med lokale ungdommer som ønsker fleksible småjobber.</p>
                <p>Lokale Tjenester UB fungerer kun som en formidler mellom oppdragsgiver og hjelper. Vi er ikke arbeidsgiver for brukerne og er ikke part i selve avtalen mellom oppdragsgiver og hjelper.</p>

                <h4>2. Bruk av tjenesten</h4>
                <p>For å bruke tjenesten må brukere:</p>
                <ul>
                    <li>Oppgi korrekt og sann informasjon</li>
                    <li>Bruke plattformen på en lovlig og respektfull måte</li>
                    <li>Følge gjeldende lover og regler</li>
                </ul>
                <p>Lokale Tjenester UB forbeholder seg retten til å suspendere eller stenge brukere som misbruker tjenesten.</p>

                <h4>3. Oppdrag</h4>
                <p>Oppdragsgiver og hjelper inngår selv avtalen om oppdraget. Dette inkluderer blant annet:</p>
                <ul>
                    <li>arbeidsoppgaver</li>
                    <li>tidspunkt</li>
                    <li>pris</li>
                </ul>
                <p>Lokale Tjenester UB har ikke ansvar for gjennomføring, kvalitet eller resultat av oppdrag.</p>

                <h4>4. Betaling</h4>
                <p>Betaling for oppdrag skjer direkte mellom oppdragsgiver og hjelper, med mindre annet er oppgitt.</p>
                <p>Lokale Tjenester UB kan ta et formidlingsgebyr for bruk av plattformen.</p>
                <p>Brukere er selv ansvarlige for eventuelle skatter og avgifter knyttet til betaling.</p>

                <h4>5. Alderskrav</h4>
                <p>Brukere må være minst 13 år for å bruke plattformen. Brukere under 18 år bør ha samtykke fra foresatte.</p>

                <h4>6. Ansvar</h4>
                <p>Lokale Tjenester UB er ikke ansvarlig for:</p>
                <ul>
                    <li>skader eller uhell som oppstår under oppdrag</li>
                    <li>kvaliteten på utført arbeid</li>
                    <li>konflikter eller økonomiske tap mellom brukere</li>
                </ul>
                <p>All bruk av tjenesten skjer på eget ansvar.</p>

                <h4>7. Endringer i vilkårene</h4>
                <p>Lokale Tjenester UB kan oppdatere disse vilkårene ved behov. Oppdaterte vilkår publiseres på nettsiden.</p>

                <h4>8. Kontakt</h4>
                <p>Hvis du har spørsmål om disse vilkårene, kan du kontakte oss: <a href="mailto:lokaletjenester.gjovik@gmail.com">lokaletjenester.gjovik@gmail.com</a></p>
            </div>
            <div class="terms-footer">
                <button id="acceptTermsBtn" class="btn btn-primary">Godta vilkårene</button>
            </div>
        </div>
    </div>

    <footer class="site-footer">
            <div class="footer-inner">
                <div class="footer-links">
                    <a href="index.php">Hjem</a>
                    <a href="pages.php?page=about">Om oss</a>
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

    <script src="static/script.js"></script>
</body>
</html>
