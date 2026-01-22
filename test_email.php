<?php
// Test email sending page - debug version
// Include necessary files
require_once __DIR__ . '/vendor/autoload.php';

// Debug: Check env vars
echo "<h1>Email Test Debug</h1>";
echo "<h2>Environment Variables:</h2>";
$vars = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'FROM_EMAIL', 'FROM_NAME', 'DOMAIN'];
foreach ($vars as $var) {
    $value = getenv($var);
    echo "<p>$var: " . ($value ? htmlspecialchars($value) : '<strong>MISSING</strong>') . "</p>";
}

echo "<h2>All $_ENV Vars (for Plesk check):</h2><pre>" . print_r($_ENV, true) . "</pre>";
echo getenv('APP_ENV');
// Test database connection (simplified)
$DB_HOST = 'localhost';
$DB_NAME = 'lokale-tjenester';
$DB_USER = 'lokale-tjenester';
$DB_PASS = 'pwlt01!';
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    echo "<p><strong>DB Error:</strong> " . $conn->connect_error . "</p>";
} else {
    echo "<p>DB Connected OK</p>";
}

// Test email sending
echo "<h2>Sending Test Email</h2>";
$test_email = 'wil.oversveen@gmail.com'; // Replace with your real test email
$user_id = 5; // Dummy user ID

try {
    $token = bin2hex(random_bytes(32));
    echo "<p>Generated token: $token</p>";

    $verifyLink = "https://" . getenv('DOMAIN') . "/pages.php?page=verify&token=" . urlencode($token);
    echo "<p>Verify link: $verifyLink</p>";

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSendmail();
    // SMTP commented
    /*
    $mail->isSMTP();
    $mail->Host = getenv('SMTP_HOST');
    $mail->SMTPAuth = true;
    $mail->Username = getenv('SMTP_USERNAME');
    $mail->Password = getenv('SMTP_PASSWORD');
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = (int)getenv('SMTP_PORT');
    */

    $mail->setFrom(getenv('FROM_EMAIL'), getenv('FROM_NAME') ?: 'Test');
    $mail->addAddress($test_email);
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from Debug Page';
    $mail->Body = "<p>HAHAHAHAHHA I GOT IT WORKING BICH, the link doesnt work yet but im sending you a maillllllllllllllll, this is a big step for our sanity <a href='$verifyLink'>Verify Link</a></p>";

    echo "<p>Attempting to send...</p>";
    if ($mail->send()) {
        echo "<p><strong>SUCCESS:</strong> Email sent!</p>";
    } else {
        echo "<p><strong>FAILED:</strong> " . $mail->ErrorInfo . "</p>";
    }
} catch (Exception $e) {
    echo "<p><strong>EXCEPTION:</strong> " . $e->getMessage() . "</p>";
}

echo "<h2>PHPMailer Debug</h2>";
$mail->SMTPDebug = 2;
$mail->Debugoutput = function($str, $level) {
    echo "<p>[$level] $str</p>";
};
// Re-send with debug
try {
    $mail->send();
} catch (Exception $e) {
    echo "<p>Debug send failed: " . $e->getMessage() . "</p>";
}
?>