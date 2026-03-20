<?php

declare(strict_types=1);

namespace Tests\Mail;

use Mail\MailService;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for MailService SMTP transport via Mailpit.
 *
 * Requires Mailpit running on localhost:1025 (SMTP) and localhost:8025 (API).
 * Skips automatically if Mailpit is not reachable.
 *
 * @group integration
 * @group mailpit
 */
class MailServiceSmtpIntegrationTest extends TestCase
{
    private const MAILPIT_SMTP_HOST = 'localhost';
    private const MAILPIT_SMTP_PORT = 1025;
    private const MAILPIT_API_URL = 'http://localhost:8025/api/v1';

    private MailService $service;

    protected function setUp(): void
    {
        if (!$this->isMailpitReachable()) {
            $this->markTestSkipped('Mailpit is not reachable at localhost:1025');
        }

        $this->deleteAllMailpitMessages();

        $this->service = new MailService([
            'transport' => 'smtp',
            'smtp' => [
                'host' => self::MAILPIT_SMTP_HOST,
                'port' => self::MAILPIT_SMTP_PORT,
                'encryption' => '',
                'username' => '',
                'password' => '',
            ],
            'default_from_email' => 'noreply@iblhoops.net',
            'default_from_name' => 'IBL',
        ]);
    }

    public function testSmtpSendDelivers(): void
    {
        $recipient = 'testuser@example.com';
        $subject = 'IBL Test Email ' . uniqid();
        $body = 'This is a test email body.';
        $fromEmail = 'sender@iblhoops.net';
        $fromName = 'IBL Mailer';

        $result = $this->service->send($recipient, $subject, $body, $fromEmail, $fromName);

        $this->assertTrue($result, 'MailService::send() should return true on successful SMTP delivery');

        $messages = $this->getMailpitMessages();
        $this->assertCount(1, $messages, 'Mailpit should have received exactly 1 message');

        $message = $messages[0];
        $this->assertSame($subject, $message['Subject']);
        $this->assertSame($recipient, $message['To'][0]['Address']);
        $this->assertSame($fromEmail, $message['From']['Address']);
        $this->assertSame($fromName, $message['From']['Name']);
    }

    public function testSmtpSendUsesDefaultFromName(): void
    {
        $recipient = 'testuser@example.com';
        $subject = 'Default Name Test ' . uniqid();

        $result = $this->service->send($recipient, $subject, 'Body text', 'sender@iblhoops.net');

        $this->assertTrue($result);

        $messages = $this->getMailpitMessages();
        $this->assertCount(1, $messages);
        $this->assertSame('IBL', $messages[0]['From']['Name']);
    }

    public function testSmtpSendPlainTextBody(): void
    {
        $recipient = 'testuser@example.com';
        $subject = 'Body Check ' . uniqid();
        $body = "Line 1\nLine 2\nLine 3";

        $result = $this->service->send($recipient, $subject, $body, 'sender@iblhoops.net');

        $this->assertTrue($result);

        $messages = $this->getMailpitMessages();
        $this->assertCount(1, $messages);

        $messageId = $messages[0]['ID'];
        $fullMessage = $this->getMailpitMessage($messageId);
        $this->assertStringContainsString('Line 1', $fullMessage['Text']);
        $this->assertStringContainsString('Line 3', $fullMessage['Text']);
    }

    private function isMailpitReachable(): bool
    {
        $socket = @fsockopen(self::MAILPIT_SMTP_HOST, self::MAILPIT_SMTP_PORT, $errno, $errstr, 2);
        if ($socket === false) {
            return false;
        }
        fclose($socket);
        return true;
    }

    private function deleteAllMailpitMessages(): void
    {
        $ch = curl_init(self::MAILPIT_API_URL . '/messages');
        if ($ch === false) {
            return;
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        unset($ch);
    }

    /**
     * @return list<array{ID: string, Subject: string, From: array{Address: string, Name: string}, To: list<array{Address: string, Name: string}>}>
     */
    private function getMailpitMessages(): array
    {
        $ch = curl_init(self::MAILPIT_API_URL . '/messages');
        if ($ch === false) {
            $this->fail('Failed to initialize curl for Mailpit API');
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        unset($ch);

        if (!is_string($response)) {
            $this->fail('Mailpit API returned non-string response');
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['messages'])) {
            $this->fail('Mailpit API returned unexpected format');
        }

        /** @var list<array{ID: string, Subject: string, From: array{Address: string, Name: string}, To: list<array{Address: string, Name: string}>}> */
        return $data['messages'];
    }

    /**
     * @return array{Text: string, HTML: string}
     */
    private function getMailpitMessage(string $id): array
    {
        $ch = curl_init(self::MAILPIT_API_URL . '/message/' . $id);
        if ($ch === false) {
            $this->fail('Failed to initialize curl for Mailpit message API');
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        unset($ch);

        if (!is_string($response)) {
            $this->fail('Mailpit message API returned non-string response');
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            $this->fail('Mailpit message API returned unexpected format');
        }

        /** @var array{Text: string, HTML: string} */
        return $data;
    }
}
