<?php
/**
 * staging/mailer_stub.php
 * A minimal, safe mailer stub. Intended for local testing. Does not send production mail
 * unless `?simulate=0` is provided and your PHP mail() is configured.
 */

require_once __DIR__ . '/../display.php';
require_once __DIR__ . '/smtp_mailer.php';

header('Content-Type: application/json; charset=utf-8');

$to = $_GET['to'] ?? 'test@example.com';
$subject = $_GET['subject'] ?? 'Test verification';
$body = $_GET['body'] ?? "This is a test verification email from Finn Hustle staging.";
$simulate = isset($_GET['simulate']) ? intval($_GET['simulate']) : 1; // default: simulate only

// Basic validation
if(!filter_var($to, FILTER_VALIDATE_EMAIL)){
    echo json_encode(['status'=>'error','message'=>'Invalid recipient email']); exit;
}

// If SMTP settings are present and simulate is not true, attempt real SMTP send
$smtpConfigured = (bool)(getenv('SMTP_HOST') ?: getenv('MAIL_HOST'));
if ($smtpConfigured && !$simulate) {
    list($ok, $err) = staging_send_mail($to, $subject, $body);
    if ($ok) {
        echo json_encode(['status'=>'success','message'=>'Mail sent via SMTP']);
    } else {
        echo json_encode(['status'=>'error','message'=>'SMTP send failed', 'detail'=>$err]);
    }
    exit;
}

// If simulate flag is set or SMTP not configured, preserve previous behaviour
if($simulate || !$smtpConfigured){
    // Return a simulated response showing headers and body
    echo json_encode([
        'status' => 'success',
        'simulated' => true,
        'to' => $to,
        'subject' => $subject,
        'body' => $body,
        'smtp_configured' => $smtpConfigured
    ]);
    exit;
}

// Fallback: attempt PHP mail() if simulate=0 but no SMTP low-level send worked
$headers = "From: no-reply@finnhustle.example\r\n" .
           "Reply-To: support@finnhustle.example\r\n" .
           "Content-Type: text/plain; charset=UTF-8\r\n";

$ok = @mail($to, $subject, $body, $headers);
if($ok) echo json_encode(['status'=>'success','message'=>'Mail sent (php mail)']);
else echo json_encode(['status'=>'error','message'=>'Mail failed (check PHP mail config and SMTP settings)']);

exit;
