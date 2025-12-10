<?php
// email_verification.php
// A reusable PHPMailer-based verification system
// Include this file anywhere you need to send a verification email.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Make sure Composer autoload is available

class EmailVerifier {
    private $mail;
    private $fromEmail;
    private $fromName;
    private $baseURL;

    public function __construct($fromEmail, $fromName, $baseURL) {
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->baseURL = rtrim($baseURL, '/');

        $this->mail = new PHPMailer(true);

        // SMTP CONFIG (edit this for your environment)
        $this->mail->isSMTP();
        $this->mail->Host = 'smtp.example.com';
        $this->mail->SMTPAuth = true;
        $this->mail->Username = 'your_smtp_user';
        $this->mail->Password = 'your_smtp_pass';
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = 587;
    }

    // Generates a token to store in DB
    public function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    // Sends the verification email
    public function sendVerification($toEmail, $token) {
        try {
            $this->mail->setFrom($this->fromEmail, $this->fromName);
            $this->mail->addAddress($toEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Verify your email';

            $verifyLink = $this->baseURL . '/verify.php?email=' . urlencode($toEmail) . '&token=' . urlencode($token);

            $this->mail->Body = "<p>Please verify your email by clicking the link below:</p>
                                 <p><a href='$verifyLink'>$verifyLink</a></p>";

            return $this->mail->send();
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
