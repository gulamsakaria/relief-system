<?php
// Sends the NGO registration verification email via SMTP (PHPMailer).

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'info.stratifyx@gmail.com');
define('SMTP_PASS', 'ppcwhiffidstrfef');
define('SMTP_FROM_NAME', 'ত্রাণ বিতরণ সিস্টেম');

// Sends a 6-digit verification code. Returns true on success, or an error message string.
function sendVerificationEmail(string $toEmail, string $toName, string $code) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'আপনার ভেরিফিকেশন কোড — ত্রাণ বিতরণ সিস্টেম';
        $mail->Body    = "
            <div style='font-family:sans-serif;max-width:480px;margin:auto;'>
              <h2>স্বাগতম, {$toName}!</h2>
              <p>আপনার ফাউন্ডেশন রেজিস্ট্রেশন সম্পন্ন করতে নিচের কোডটি ওয়েবসাইটে গিয়ে লিখুন।</p>
              <p style='margin:24px 0; text-align:center;'>
                <span style='display:inline-block; background:#E4F0F3; color:#0A4F66; font-size:32px; font-weight:700; letter-spacing:6px; padding:14px 22px; border-radius:8px;'>{$code}</span>
              </p>
              <p style='color:#888;font-size:12px;'>এই কোডটি ২৪ ঘণ্টা পর মেয়াদোত্তীর্ণ হয়ে যাবে। কাউকে শেয়ার করবেন না।</p>
            </div>
        ";
        $mail->AltBody = "আপনার ভেরিফিকেশন কোড: {$code} (২৪ ঘণ্টা পর মেয়াদোত্তীর্ণ হয়ে যাবে)";

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        return $mail->ErrorInfo;
    }
}

// Sends a 6-digit password reset code. Returns true on success, or an error message string.
function sendPasswordResetEmail(string $toEmail, string $toName, string $code) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'পাসওয়ার্ড রিসেট কোড — ত্রাণ বিতরণ সিস্টেম';
        $mail->Body    = "
            <div style='font-family:sans-serif;max-width:480px;margin:auto;'>
              <h2>পাসওয়ার্ড রিসেট অনুরোধ</h2>
              <p>আপনার অ্যাকাউন্টের পাসওয়ার্ড পরিবর্তন করতে নিচের কোডটি ওয়েবসাইটে গিয়ে লিখুন।</p>
              <p style='margin:24px 0; text-align:center;'>
                <span style='display:inline-block; background:#E4F0F3; color:#0A4F66; font-size:32px; font-weight:700; letter-spacing:6px; padding:14px 22px; border-radius:8px;'>{$code}</span>
              </p>
              <p style='color:#888;font-size:12px;'>এই কোডটি ১ ঘণ্টা পর মেয়াদোত্তীর্ণ হয়ে যাবে। আপনি যদি এই অনুরোধ না করে থাকেন, এই ইমেইলটি উপেক্ষা করুন।</p>
            </div>
        ";
        $mail->AltBody = "আপনার পাসওয়ার্ড রিসেট কোড: {$code} (১ ঘণ্টা পর মেয়াদোত্তীর্ণ হয়ে যাবে)";

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        return $mail->ErrorInfo;
    }
}
