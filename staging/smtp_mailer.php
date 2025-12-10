<?php
/**
 * staging/smtp_mailer.php
 * Minimal SMTP wrapper used by staging mailer. Reads SMTP settings from environment variables:
 *  - SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, SMTP_FROM, SMTP_FROM_NAME, SMTP_SECURE
 * If no SMTP_HOST is configured, callers should fall back to PHP mail() or simulated delivery.
 * NOTE: This is a small, best-effort implementation for staging and dev only. For production use
 * prefer a maintained library like PHPMailer or SwiftMailer.
 */

function staging_smtp_send($to, $subject, $body, $fromEmail = null, $fromName = null) {
    $host = getenv('SMTP_HOST') ?: getenv('MAIL_HOST');
    if (!$host) return ['ok'=>false,'error'=>'SMTP_HOST not configured'];

    $port = intval(getenv('SMTP_PORT') ?: 25);
    $user = getenv('SMTP_USERNAME') ?: getenv('MAIL_USER');
    $pass = getenv('SMTP_PASSWORD') ?: getenv('MAIL_PASS');
    $secure = strtolower(getenv('SMTP_SECURE') ?: ''); // 'ssl' | 'tls' | ''

    $fromEmail = $fromEmail ?: (getenv('SMTP_FROM') ?: 'no-reply@finnhustle.example');
    $fromName = $fromName ?: (getenv('SMTP_FROM_NAME') ?: 'Finn Hustle');

    $timeout = 10;
    $errNo = 0; $errStr = '';
    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errNo, $errStr, $timeout);
    if (!$fp) return ['ok'=>false,'error'=>"Connection failed: $errStr ($errNo)"];

    $read = function() use ($fp) {
        $res = '';
        while (($line = fgets($fp, 515)) !== false) {
            $res .= $line;
            if (preg_match('/^\d{3} /', $line)) break;
        }
        return $res;
    };
    $write = function($cmd) use ($fp) {
        fwrite($fp, $cmd . "\r\n");
    };

    $greet = $read();
    // say EHLO
    $write("EHLO localhost"); $ehlo = $read();

    // If STARTTLS requested and server supports it, upgrade
    if ($secure === 'tls' && stripos($ehlo, 'STARTTLS') !== false) {
        $write('STARTTLS'); $r = $read();
        if (strpos($r, '220') === 0) {
            stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            // re-EHLO
            $write("EHLO localhost"); $ehlo = $read();
        }
    }

    // AUTH LOGIN if credentials provided
    if ($user !== '' && $pass !== '') {
        $write('AUTH LOGIN'); $r = $read();
        if (strpos($r, '334') !== 0) {
            // server didn't accept AUTH LOGIN
            // continue without auth and hope server accepts MAIL FROM (may fail)
        } else {
            $write(base64_encode($user)); $read();
            $write(base64_encode($pass)); $read();
        }
    }

    // MAIL FROM
    $write('MAIL FROM:<' . $fromEmail . '>'); $r = $read();
    if (strpos($r, '250') !== 0) return ['ok'=>false,'error'=>'MAIL FROM rejected: '.$r];

    // RCPT TO
    $write('RCPT TO:<' . $to . '>'); $r = $read();
    if (strpos($r, '250') !== 0 && strpos($r, '251') !== 0) return ['ok'=>false,'error'=>'RCPT TO rejected: '.$r];

    // DATA
    $write('DATA'); $r = $read();
    if (strpos($r, '354') !== 0) return ['ok'=>false,'error'=>'DATA rejected: '.$r];

    $headers = [];
    $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
    $headers[] = 'To: ' . $to;
    $headers[] = 'Subject: ' . $subject;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';

    $data = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
    $write($data);
    $r = $read();
    if (strpos($r, '250') !== 0) return ['ok'=>false,'error'=>'DATA send failed: '.$r];

    $write('QUIT'); $read();
    fclose($fp);
    return ['ok'=>true];
}

// Convenience wrapper returning boolean and message
function staging_send_mail($to, $subject, $body, $fromEmail = null, $fromName = null) {
    $res = staging_smtp_send($to, $subject, $body, $fromEmail, $fromName);
    if (is_array($res) && isset($res['ok']) && $res['ok']) return [true, null];
    return [false, is_array($res) && isset($res['error']) ? $res['error'] : 'Unknown error'];
}

?>
