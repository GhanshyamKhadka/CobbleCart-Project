<?php
require_once __DIR__ . '/lib/SmtpMailer.php';

function sendEmail(string $toEmail, string $toName, string $subject, string $body): bool
{
    $configPath = __DIR__ . '/mail-config.local.php';
    if (!is_file($configPath)) {
        error_log('sendEmail: mail-config.local.php not found. Copy mail-config.local.example.php and fill in your SMTP creds.');
        return false;
    }
    $config = require $configPath;
    if (!is_array($config)) {
        error_log('sendEmail: mail-config.local.php did not return an array');
        return false;
    }

    try {
        (new SmtpMailer($config))->send($toEmail, $toName, $subject, $body);
        return true;
    } catch (Throwable $e) {
        error_log('sendEmail: ' . $e->getMessage());
        return false;
    }
}
