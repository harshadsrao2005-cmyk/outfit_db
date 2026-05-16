<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'mailer_config.php';

function sendOTP($to, $otp) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Account - Outfit Recommender';
        $mail->Body    = "
            <div style='font-family: sans-serif; max-width: 500px; margin: auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                <h2 style='color: #2b7dff;'>Outfit Recommender</h2>
                <p>Hello,</p>
                <p>Your verification code is:</p>
                <div style='font-size: 32px; font-weight: bold; color: #2b7dff; padding: 10px; background: #f0f7ff; text-align: center; border-radius: 5px; letter-spacing: 5px;'>
                    $otp
                </div>
                <p>This code will expire in 15 minutes.</p>
                <p>If you didn't request this, please ignore this email.</p>
            </div>
        ";
        $mail->AltBody = "Your verification code is: $otp. It will expire in 15 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log error if needed: $mail->ErrorInfo
        return false;
    }
}
?>
