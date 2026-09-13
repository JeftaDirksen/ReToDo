<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

function send_email(string $to, string $subject, string $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.example.com';
        $mail->Port = $_ENV['SMTP_PORT'] ?? 587;
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Username = $_ENV['SMTP_USERNAME'] ?? 'your_email@example.com';
        $mail->Password = $_ENV['SMTP_PASSWORD'] ?? 'your_password';
        $mail->setFrom($_ENV['SMTP_USERNAME'] ?? 'your_email@example.com', 'ReToDo');
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(true);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        print("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function redirect(string $url = '/') {
    header('Location: ' . $url);
    exit;
}
