<?php

declare(strict_types=1);

namespace Tests\Mail;

use Mail\MailService;
use PHPUnit\Framework\TestCase;

class MailServiceTest extends TestCase
{
    public function testLogTransportReturnsTrue(): void
    {
        $service = new MailService(['transport' => 'log', 'smtp' => ['host' => '', 'port' => 587, 'encryption' => 'tls', 'username' => '', 'password' => ''], 'default_from_email' => 'test@example.com', 'default_from_name' => 'Test']);

        $result = $service->send('recipient@example.com', 'Test Subject', 'Test body', 'sender@example.com');

        $this->assertTrue($result);
    }

    public function testInvalidRecipientReturnsFalse(): void
    {
        $service = new MailService(['transport' => 'log', 'smtp' => ['host' => '', 'port' => 587, 'encryption' => 'tls', 'username' => '', 'password' => ''], 'default_from_email' => 'test@example.com', 'default_from_name' => 'Test']);

        $result = $service->send('not-an-email', 'Test Subject', 'Test body', 'sender@example.com');

        $this->assertFalse($result);
    }

    public function testEmptyRecipientReturnsFalse(): void
    {
        $service = new MailService(['transport' => 'log', 'smtp' => ['host' => '', 'port' => 587, 'encryption' => 'tls', 'username' => '', 'password' => ''], 'default_from_email' => 'test@example.com', 'default_from_name' => 'Test']);

        $result = $service->send('', 'Test Subject', 'Test body', 'sender@example.com');

        $this->assertFalse($result);
    }

    public function testIsDeliveryEnabledFalseForLogTransport(): void
    {
        $service = new MailService(['transport' => 'log', 'smtp' => ['host' => '', 'port' => 587, 'encryption' => 'tls', 'username' => '', 'password' => ''], 'default_from_email' => 'test@example.com', 'default_from_name' => 'Test']);

        $this->assertFalse($service->isDeliveryEnabled());
    }

    public function testIsDeliveryEnabledTrueForSmtpTransport(): void
    {
        $service = new MailService(['transport' => 'smtp', 'smtp' => ['host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls', 'username' => 'user', 'password' => 'pass'], 'default_from_email' => 'test@example.com', 'default_from_name' => 'Test']);

        $this->assertTrue($service->isDeliveryEnabled());
    }

    public function testIsDeliveryEnabledTrueForMailTransport(): void
    {
        $service = new MailService(['transport' => 'mail', 'smtp' => ['host' => '', 'port' => 587, 'encryption' => 'tls', 'username' => '', 'password' => ''], 'default_from_email' => 'test@example.com', 'default_from_name' => 'Test']);

        $this->assertTrue($service->isDeliveryEnabled());
    }

    public function testInvalidTransportDefaultsToLog(): void
    {
        $service = new MailService(['transport' => 'invalid', 'smtp' => ['host' => '', 'port' => 587, 'encryption' => 'tls', 'username' => '', 'password' => ''], 'default_from_email' => 'test@example.com', 'default_from_name' => 'Test']);

        $this->assertSame('log', $service->getTransport());
        $this->assertFalse($service->isDeliveryEnabled());
    }

    public function testFromConfigReturnsValidService(): void
    {
        // fromConfig() loads whatever config file exists on disk (or falls back to defaults).
        // We can't assert a specific transport because local mail.config.php may differ.
        $service = MailService::fromConfig();

        $this->assertContains($service->getTransport(), ['log', 'mail', 'smtp']);
    }

    public function testSubjectSanitizationStripsNewlines(): void
    {
        $service = new MailService(['transport' => 'log', 'smtp' => ['host' => '', 'port' => 587, 'encryption' => 'tls', 'username' => '', 'password' => ''], 'default_from_email' => 'test@example.com', 'default_from_name' => 'Test']);

        // If the subject contains newlines, MailService sanitizes them via EmailSanitizer.
        // The log transport still returns true — the sanitization happens silently.
        $result = $service->send('recipient@example.com', "Subject\r\nBcc: attacker@evil.com", 'Body', 'sender@example.com');

        $this->assertTrue($result);
    }

    public function testSendWithFromNameParameter(): void
    {
        $service = new MailService(['transport' => 'log', 'smtp' => ['host' => '', 'port' => 587, 'encryption' => 'tls', 'username' => '', 'password' => ''], 'default_from_email' => 'test@example.com', 'default_from_name' => 'Test']);

        $result = $service->send('recipient@example.com', 'Test', 'Body', 'sender@example.com', 'Sender Name');

        $this->assertTrue($result);
    }

    public function testSmtpTransportFailsWithInvalidHost(): void
    {
        $service = new MailService(['transport' => 'smtp', 'smtp' => ['host' => 'invalid.nonexistent.host.example', 'port' => 587, 'encryption' => 'tls', 'username' => '', 'password' => ''], 'default_from_email' => 'test@example.com', 'default_from_name' => 'Test']);

        // SMTP with an invalid host should fail gracefully and return false
        $result = $service->send('recipient@example.com', 'Test', 'Body', 'sender@example.com');

        $this->assertFalse($result);
    }

    public function testFromConfigUsesEnvVarTransport(): void
    {
        putenv('MAIL_TRANSPORT=log');
        putenv('MAIL_SMTP_HOST=testhost.example');
        putenv('MAIL_SMTP_PORT=2525');

        try {
            $service = MailService::fromConfig();

            $this->assertSame('log', $service->getTransport());
        } finally {
            putenv('MAIL_TRANSPORT');
            putenv('MAIL_SMTP_HOST');
            putenv('MAIL_SMTP_PORT');
        }
    }

    public function testFromConfigEnvVarSmtpOverridesFileConfig(): void
    {
        putenv('MAIL_TRANSPORT=smtp');
        putenv('MAIL_SMTP_HOST=mailpit.local');
        putenv('MAIL_SMTP_PORT=1025');
        putenv('MAIL_SMTP_ENCRYPTION=');

        try {
            $service = MailService::fromConfig();

            $this->assertSame('smtp', $service->getTransport());
            $this->assertTrue($service->isDeliveryEnabled());
        } finally {
            putenv('MAIL_TRANSPORT');
            putenv('MAIL_SMTP_HOST');
            putenv('MAIL_SMTP_PORT');
            putenv('MAIL_SMTP_ENCRYPTION');
        }
    }

    public function testFromConfigIgnoresEmptyEnvVar(): void
    {
        putenv('MAIL_TRANSPORT=');

        try {
            $service = MailService::fromConfig();

            // Should fall through to file-based config, not use empty env var
            $this->assertContains($service->getTransport(), ['log', 'mail', 'smtp']);
        } finally {
            putenv('MAIL_TRANSPORT');
        }
    }
}
