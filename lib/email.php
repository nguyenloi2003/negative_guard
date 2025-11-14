<?php
// lib/email.php  
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

function send_email(string $to, string $subject, string $body): bool
{
    $mail = new PHPMailer(true);

    try {
        // Cấu hình SMTP  
        $mail->isSMTP();
        $mail->Host = envv('SMTP_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth = true;
        $mail->Username = envv('SMTP_USERNAME');
        $mail->Password = envv('SMTP_PASSWORD');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)envv('SMTP_PORT', 587);

        // Người gửi  
        $mail->setFrom(
            envv('SMTP_FROM_EMAIL', envv('SMTP_USERNAME')),
            envv('SMTP_FROM_NAME', 'IUH Admin')
        );

        // Người nhận  
        $mail->addAddress($to);

        // Nội dung  
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email error: {$mail->ErrorInfo}");
        return false;
    }
}
