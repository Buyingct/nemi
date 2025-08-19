<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/.env.php';

function send_email(string $to, string $subject, string $html): bool {
  global $config;
  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host       = $config['SMTP_HOST'];
    $mail->Port       = $config['SMTP_PORT'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['SMTP_USER'];
    $mail->Password   = $config['SMTP_PASS'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

    $mail->setFrom($config['SMTP_FROM'], $config['SMTP_FROM_NAME'] ?? 'Nemi');
    $mail->addAddress($to);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $html;
    $mail->AltBody = strip_tags(str_replace(['<br>','<br/>','<br />'], "\n", $html));

    $mail->send();
    return true;
  } catch (Exception $e) {
    error_log("Email send failed: " . $mail->ErrorInfo);
    return false;
  }
}

function send_email_otp(string $to, string $code): bool {
  $html = "<p>Your Nemi login code is:</p><h2 style='letter-spacing:6px;'>$code</h2>";
  return send_email($to, 'Your Nemi login code', $html);
}
