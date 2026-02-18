<?php

declare(strict_types=1);

namespace Mail;

use Mail\Contracts\MailServiceInterface;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Utilities\EmailSanitizer;

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
     * Create a MailService from the config file.
     *
     * Loads config/mail.config.php if it exists, falls back to
     * config/mail.config.example.php, then to hardcoded log defaults.
     */
    public static function fromConfig(): self
    {
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
            $mail->SMTPAuth = true;
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
            error_log('[MailService] SMTP send failed: ' . $e->getMessage());
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
        error_log(sprintf(
            '[MailService] LOG transport — To: %s | From: %s | Subject: %s | Body length: %d',
            $to,
            $fromEmail,
            $subject,
            strlen($body)
        ));
        error_log('[MailService] Body: ' . $body);
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
