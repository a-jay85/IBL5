<?php

declare(strict_types=1);

namespace Mail;

use Mail\Contracts\MailServiceInterface;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Mail delivery service with configurable transport.
 *
 * Supports three transports:
 *   - 'smtp': Sends via SMTP using PHPMailer
 *   - 'mail': Sends via PHP's native mail() with proper headers
 *   - 'log':  Writes to error_log for local development (default)
 *
 * @phpstan-type SmtpConfig array{host: string, port: int, encryption: string, username: string, password: string}
 * @phpstan-type MailConfig array{transport: string, smtp: SmtpConfig, default_from_email: string, default_from_name: string}
 */
class MailService implements MailServiceInterface
{
    private string $transport;
    /** @var SmtpConfig */
    private array $smtpConfig;
    private string $defaultFromName;

    private const VALID_TRANSPORTS = ['smtp', 'mail', 'log'];

    private const DEFAULT_CONFIG = [
        'transport' => 'log',
        'smtp' => [
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
        ],
        'default_from_email' => 'noreply@iblhoops.net',
        'default_from_name' => 'IBL',
    ];

    /**
     * @param MailConfig $config
     */
    public function __construct(array $config)
    {
        $transport = $config['transport'] ?? 'log';
        if (!in_array($transport, self::VALID_TRANSPORTS, true)) {
            $transport = 'log';
        }
        $this->transport = $transport;

        $smtpDefaults = self::DEFAULT_CONFIG['smtp'];
        $smtpInput = $config['smtp'] ?? [];
        $this->smtpConfig = [
            'host' => is_string($smtpInput['host'] ?? null) ? $smtpInput['host'] : $smtpDefaults['host'],
            'port' => is_int($smtpInput['port'] ?? null) ? $smtpInput['port'] : $smtpDefaults['port'],
            'encryption' => is_string($smtpInput['encryption'] ?? null) ? $smtpInput['encryption'] : $smtpDefaults['encryption'],
            'username' => is_string($smtpInput['username'] ?? null) ? $smtpInput['username'] : $smtpDefaults['username'],
            'password' => is_string($smtpInput['password'] ?? null) ? $smtpInput['password'] : $smtpDefaults['password'],
        ];

        $this->defaultFromName = is_string($config['default_from_name'] ?? null)
            ? $config['default_from_name']
            : self::DEFAULT_CONFIG['default_from_name'];
    }

    /**
     * Create a MailService from environment variables or config file.
     *
     * Priority: MAIL_TRANSPORT env var > config/mail.config.php > config/mail.config.example.php > defaults.
     * Docker sets MAIL_TRANSPORT=smtp + MAIL_SMTP_HOST/PORT to auto-route to Mailpit.
     */
    public static function fromConfig(): self
    {
        $envConfig = self::buildConfigFromEnv();
        if ($envConfig !== null) {
            return new self($envConfig);
        }

        $configPath = self::resolveConfigDir() . '/mail.config.php';
        $examplePath = self::resolveConfigDir() . '/mail.config.example.php';

        if (file_exists($configPath)) {
            $config = require $configPath;
        } elseif (file_exists($examplePath)) {
            $config = require $examplePath;
        } else {
            $config = self::DEFAULT_CONFIG;
        }

        /** @var MailConfig $config */

        return new self($config);
    }

    /**
     * Build config from MAIL_* environment variables, if MAIL_TRANSPORT is set.
     *
     * @return MailConfig|null Config array if MAIL_TRANSPORT is set, null otherwise.
     */
    private static function buildConfigFromEnv(): ?array
    {
        $transport = getenv('MAIL_TRANSPORT');
        if (!is_string($transport) || $transport === '') {
            return null;
        }

        $host = getenv('MAIL_SMTP_HOST');
        $port = getenv('MAIL_SMTP_PORT');
        $encryption = getenv('MAIL_SMTP_ENCRYPTION');
        $username = getenv('MAIL_SMTP_USERNAME');
        $password = getenv('MAIL_SMTP_PASSWORD');

        return [
            'transport' => $transport,
            'smtp' => [
                'host' => is_string($host) && $host !== '' ? $host : self::DEFAULT_CONFIG['smtp']['host'],
                'port' => is_string($port) && $port !== '' && (int) $port >= 1 && (int) $port <= 65535
                    ? (int) $port
                    : self::DEFAULT_CONFIG['smtp']['port'],
                'encryption' => is_string($encryption) ? $encryption : self::DEFAULT_CONFIG['smtp']['encryption'],
                'username' => is_string($username) && $username !== '' ? $username : self::DEFAULT_CONFIG['smtp']['username'],
                'password' => is_string($password) && $password !== '' ? $password : self::DEFAULT_CONFIG['smtp']['password'],
            ],
            'default_from_email' => self::DEFAULT_CONFIG['default_from_email'],
            'default_from_name' => self::DEFAULT_CONFIG['default_from_name'],
        ];
    }

    /** @see MailServiceInterface::send() */
    public function send(string $to, string $subject, string $body, string $fromEmail, string $fromName = ''): bool
    {
        if (!EmailSanitizer::isValidEmail($to)) {
            return false;
        }

        $safeSubject = EmailSanitizer::sanitizeSubject($subject);
        $safeFromEmail = EmailSanitizer::sanitizeHeader($fromEmail);
        $safeFromName = $fromName !== '' ? EmailSanitizer::sanitizeHeader($fromName) : '';

        return match ($this->transport) {
            'smtp' => $this->sendViaSmtp($to, $safeSubject, $body, $safeFromEmail, $safeFromName),
            'mail' => $this->sendViaNativeMail($to, $safeSubject, $body, $safeFromEmail, $safeFromName),
            default => $this->sendViaLog($to, $safeSubject, $body, $safeFromEmail),
        };
    }

    /** @see MailServiceInterface::isDeliveryEnabled() */
    public function isDeliveryEnabled(): bool
    {
        return $this->transport === 'smtp' || $this->transport === 'mail';
    }

    /**
     * Get the configured transport name (for testing/debugging).
     */
    public function getTransport(): string
    {
        return $this->transport;
    }

    private function sendViaSmtp(string $to, string $subject, string $body, string $fromEmail, string $fromName): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->smtpConfig['host'];
            $mail->Port = $this->smtpConfig['port'];
            $mail->SMTPSecure = $this->smtpConfig['encryption'];
            $mail->SMTPAuth = $this->smtpConfig['username'] !== '';
            $mail->Username = $this->smtpConfig['username'];
            $mail->Password = $this->smtpConfig['password'];

            $senderName = $fromName !== '' ? $fromName : $this->defaultFromName;
            $mail->setFrom($fromEmail, $senderName);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML(false);

            return $mail->send();
        } catch (PHPMailerException $e) {
            \Logging\LoggerFactory::getChannel('mail')->error('SMTP send failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function sendViaNativeMail(string $to, string $subject, string $body, string $fromEmail, string $fromName): bool
    {
        $senderName = $fromName !== '' ? $fromName : $this->defaultFromName;
        $headers = "From: {$senderName} <{$fromEmail}>\r\n";
        $headers .= "X-Mailer: PHP/" . PHP_VERSION;

        return mail($to, $subject, $body, $headers);
    }

    private function sendViaLog(string $to, string $subject, string $body, string $fromEmail): bool
    {
        \Logging\LoggerFactory::getChannel('mail')->info('LOG transport — mail sent', [
            'to' => $to,
            'from' => $fromEmail,
            'subject' => $subject,
            'body_length' => strlen($body),
        ]);
        return true;
    }

    private static function resolveConfigDir(): string
    {
        // When running from ibl5/ directory (normal app context)
        if (is_dir('config')) {
            return 'config';
        }

        // When running from project root or tests
        if (is_dir('ibl5/config')) {
            return 'ibl5/config';
        }

        // Absolute fallback based on this file's location
        return dirname(__DIR__, 2) . '/config';
    }
}
