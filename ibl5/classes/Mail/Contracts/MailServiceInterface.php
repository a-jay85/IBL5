<?php

declare(strict_types=1);

namespace Mail\Contracts;

/**
 * Interface for the mail delivery service.
 *
 * Provides a transport-agnostic API for sending emails. Implementations
 * may deliver via SMTP, native PHP mail(), or log output for development.
 */
interface MailServiceInterface
{
    /**
     * Send an email message.
     *
     * @param string $to        Recipient email address
     * @param string $subject   Email subject (will be sanitized internally)
     * @param string $body      Plain-text email body
     * @param string $fromEmail Sender email address
     * @param string $fromName  Sender display name (optional)
     * @return bool True if the message was accepted for delivery, false on failure
     */
    public function send(string $to, string $subject, string $body, string $fromEmail, string $fromName = ''): bool;

    /**
     * Check whether the configured transport actually delivers emails.
     *
     * Returns true for 'smtp' and 'mail' transports, false for 'log'.
     * Useful for callers that want to show "email sent" vs "email logged" feedback.
     */
    public function isDeliveryEnabled(): bool;
}
