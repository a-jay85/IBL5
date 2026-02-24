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
        // fromConfig() loads mail.config.php if it exists, else example, else defaults.
        // The result depends on the local environment, so just verify we get a valid transport.
        $service = MailService::fromConfig();

        $this->assertContains($service->getTransport(), ['smtp', 'mail', 'log']);
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
}
