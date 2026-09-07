<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/phpmailer/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/SMTP.php';

/**
 * Sliding-window rate limit: max 3 reset emails per address per hour.
 * Tracked in $_SESSION['pwd_reset_rate'] — no DB table needed.
 * Returns true when the request is allowed, false when rate-limited.
 */
function checkPasswordResetRateLimit(string $email): bool
{
    $windowStart = time() - 3600;

    if (!isset($_SESSION['pwd_reset_rate'][$email])) {
        $_SESSION['pwd_reset_rate'][$email] = [];
    }

    $_SESSION['pwd_reset_rate'][$email] = array_values(array_filter(
        $_SESSION['pwd_reset_rate'][$email],
        fn(int $ts) => $ts > $windowStart
    ));

    if (count($_SESSION['pwd_reset_rate'][$email]) >= 3) {
        return false;
    }

    $_SESSION['pwd_reset_rate'][$email][] = time();
    return true;
}

/**
 * Send a password-reset email via Gmail SMTP (port 587 STARTTLS).
 * Returns true on success, false on failure (error written to error_log).
 */
function sendPasswordResetEmail(string $toEmail, string $resetLink): bool
{
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = EMAIL_USER;
        $mail->Password   = EMAIL_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom(EMAIL_USER, 'Technical Support');
        $mail->addAddress($toEmail);

        $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request at SPMS App';
        $mail->Body    = '
            <p>Hello,</p>
            <p>We received a request to reset your SPMS account password.</p>
            <p>Click the button below to set a new password.
               This link expires in <strong>30 minutes</strong>.</p>
            <p style="margin:24px 0;">
                <a href="' . $safeLink . '"
                   style="background:#0d6efd;color:#fff;padding:10px 20px;
                          border-radius:4px;text-decoration:none;font-weight:bold;">
                    Reset Password
                </a>
            </p>
            <p>Or copy and paste this URL into your browser:</p>
            <p style="word-break:break-all;">' . $safeLink . '</p>
            <p>If you did not request a password reset, you can safely ignore this email.</p>
            <p>– SPMS Team, COTSU</p>
        ';
        $mail->AltBody = "Password Reset Link:\n$resetLink\n\nThis link expires in 30 minutes.\nIf you did not request this, ignore this email.";

        $mail->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('PHPMailer error for ' . $toEmail . ': ' . $mail->ErrorInfo);
        return false;
    } catch (\Throwable $e) {
        error_log('PHPMailer unexpected error for ' . $toEmail . ': ' . $e->getMessage());
        return false;
    }
}
