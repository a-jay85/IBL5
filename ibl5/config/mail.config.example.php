<?php
/**
 * Mail Service Configuration Template
 *
 * Copy this file to mail.config.php and update the settings for your environment.
 *
 * IMPORTANT: Never commit mail.config.php to version control!
 * It is excluded via .gitignore to protect your SMTP credentials.
 *
 * Transport options:
 *   'smtp' - Send via SMTP (recommended for production)
 *   'mail' - Send via PHP's native mail() function
 *   'log'  - Write emails to error_log (safe for local development)
 *
 * Docker note: docker-compose.yml sets MAIL_TRANSPORT/MAIL_SMTP_HOST/MAIL_SMTP_PORT
 * environment variables on the PHP container, which take priority over this file.
 * Emails sent in Docker are captured by Mailpit at http://localhost:8025.
 */

return [
    // Transport method: 'smtp', 'mail', or 'log'
    'transport' => 'log',

    // SMTP settings (only used when transport is 'smtp')
    'smtp' => [
        'host' => 'smtp.example.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'your-smtp-username',
        'password' => 'your-smtp-password',
    ],

    // Default sender
    'default_from_email' => 'noreply@iblhoops.net',
    'default_from_name' => 'IBL',
];
