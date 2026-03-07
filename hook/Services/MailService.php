<?php

namespace TLC\Hook\Services;

use TLC\Core\Config;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    private PHPMailer $mailer;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);

        // Server settings
        // $this->mailer->SMTPDebug = 0;                      // Enable verbose debug output
        $this->mailer->isSMTP();                                            // Send using SMTP
        $this->mailer->Host       = Config::get('MAIL_HOST', 'smtp.example.com');                     // Set the SMTP server to send through
        $this->mailer->SMTPAuth   = true;                                   // Enable SMTP authentication
        $this->mailer->Username   = Config::get('MAIL_USERNAME', 'user@example.com');                     // SMTP username
        $this->mailer->Password   = Config::get('MAIL_PASSWORD', 'secret');                               // SMTP password
        $this->mailer->SMTPSecure = Config::get('MAIL_ENCRYPTION', PHPMailer::ENCRYPTION_SMTPS);            // Enable implicit TLS encryption
        $this->mailer->Port       = Config::get('MAIL_PORT', 465);                                    // TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

        // Default Sender
        $fromEmail = Config::get('MAIL_FROM_ADDRESS', 'no-reply@example.com');
        $fromName = Config::get('MAIL_FROM_NAME', 'Universal API');
        $this->mailer->setFrom($fromEmail, $fromName);
    }

    public function sendOtp(string $toEmail, string $otpCode)
    {
        try {
            $this->mailer->addAddress($toEmail);     // Add a recipient

            // Content
            $this->mailer->isHTML(true);                                  // Set email format to HTML
            $this->mailer->Subject = 'Your Login OTP';

            // Simple HTML template
            $body = "<h1>Login OTP</h1><p>Your OTP code is: <strong>{$otpCode}</strong></p><p>This code will expire in 10 minutes.</p>";

            $this->mailer->Body    = $body;
            $this->mailer->AltBody = "Your OTP code is: {$otpCode}. This code will expire in 10 minutes.";

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            // Log error?
            // error_log("Message could not be sent. Mailer Error: {$this->mailer->ErrorInfo}");
            return false;
        }
    }
}
