<?php
declare(strict_types=1);

const LOCAL_PASSWORD_RESET_OTP_TTL_MINUTES = 10;
const LOCAL_PASSWORD_RESET_RESEND_SECONDS = 60;
const LOCAL_PASSWORD_RESET_MAX_ATTEMPTS = 5;
const LOCAL_PASSWORD_RESET_FROM_EMAIL = 'support@webstripetechnologies.com';
const LOCAL_PASSWORD_RESET_FROM_NAME = 'Maan World Local';

function localSendPasswordResetOtp(string $email, string $name, string $otp): bool
{
    $safeName = htmlspecialchars($name !== '' ? $name : 'User', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $subject = 'Maan World Local - Password Reset OTP';
    $html = '<!doctype html>
<html>
<body style="margin:0;padding:24px;background:#f5f7fb;font-family:Arial,sans-serif;color:#172033;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:16px;padding:28px;box-shadow:0 8px 30px rgba(0,0,0,.06);">
    <h2 style="margin:0 0 12px;">Password Reset</h2>
    <p style="margin:0 0 12px;">Hello ' . $safeName . ',</p>
    <p style="margin:0 0 18px;">Use the following one-time password to reset your Maan World Local account password:</p>
    <div style="font-size:30px;letter-spacing:8px;font-weight:700;text-align:center;padding:16px;background:#f7f9fc;border-radius:12px;">' . $safeOtp . '</div>
    <p style="margin:18px 0 8px;">This OTP is valid for ' . LOCAL_PASSWORD_RESET_OTP_TTL_MINUTES . ' minutes.</p>
    <p style="margin:0;color:#5d6678;">Do not share this OTP with anyone. If you did not request a password reset, you can ignore this email.</p>
  </div>
</body>
</html>';

    $plain = "Hello {$name},

Your Maan World Local password reset OTP is: {$otp}

This OTP is valid for " . LOCAL_PASSWORD_RESET_OTP_TTL_MINUTES . " minutes.
Do not share this OTP with anyone.

If you did not request a password reset, ignore this email.";

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . LOCAL_PASSWORD_RESET_FROM_NAME . ' <' . LOCAL_PASSWORD_RESET_FROM_EMAIL . '>',
        'Reply-To: ' . LOCAL_PASSWORD_RESET_FROM_EMAIL,
    ];

    $body = $html;
    $result = mail($email, $encodedSubject, $body, implode("\r\n", $headers));
    if ($result) {
        return true;
    }

    // Keep a plain-text fallback available for servers where HTML mail handling differs.
    return mail(
        $email,
        $encodedSubject,
        $plain,
        'From: ' . LOCAL_PASSWORD_RESET_FROM_NAME . ' <' . LOCAL_PASSWORD_RESET_FROM_EMAIL . '>\r\n'
        . 'Reply-To: ' . LOCAL_PASSWORD_RESET_FROM_EMAIL . '\r\n'
        . 'Content-Type: text/plain; charset=UTF-8'
    );
}
