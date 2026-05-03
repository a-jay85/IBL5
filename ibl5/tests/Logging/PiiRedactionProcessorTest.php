<?php

declare(strict_types=1);

namespace Tests\Logging;

use Logging\PiiRedactionProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class PiiRedactionProcessorTest extends TestCase
{
    private PiiRedactionProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new PiiRedactionProcessor();
    }

    public function testRedactsEmailAddressesInContext(): void
    {
        $record = $this->makeRecord(context: ['email' => 'user@example.com']);

        $result = ($this->processor)($record);

        $this->assertSame('***@***.***', $result->context['email']);
    }

    public function testRedactsEmailAddressesEmbeddedInStrings(): void
    {
        $record = $this->makeRecord(context: ['detail' => 'Contact user@example.com for help']);

        $result = ($this->processor)($record);

        $this->assertSame('Contact ***@***.*** for help', $result->context['detail']);
    }

    public function testRedactsApiKeysMatchingIblPrefix(): void
    {
        $record = $this->makeRecord(context: ['key' => 'ibl_abc12345xyz']);

        $result = ($this->processor)($record);

        $this->assertSame('ibl_abc1***', $result->context['key']);
    }

    public function testRedactsSensitiveKeyNames(): void
    {
        $record = $this->makeRecord(context: [
            'password' => 'secret123',
            'token' => 'tok_abc',
            'secret' => 'mysecret',
            'raw_key' => 'keydata',
        ]);

        $result = ($this->processor)($record);

        $this->assertSame('[REDACTED]', $result->context['password']);
        $this->assertSame('[REDACTED]', $result->context['token']);
        $this->assertSame('[REDACTED]', $result->context['secret']);
        $this->assertSame('[REDACTED]', $result->context['raw_key']);
    }

    public function testPreservesClientIp(): void
    {
        $record = $this->makeRecord(context: ['client_ip' => '192.168.1.100']);

        $result = ($this->processor)($record);

        $this->assertSame('192.168.1.100', $result->context['client_ip']);
    }

    public function testPreservesUsername(): void
    {
        $record = $this->makeRecord(context: ['username' => 'ajay']);

        $result = ($this->processor)($record);

        $this->assertSame('ajay', $result->context['username']);
    }

    public function testRedactsNestedArrays(): void
    {
        $record = $this->makeRecord(context: [
            'user' => [
                'email' => 'test@example.org',
                'name' => 'John',
            ],
        ]);

        $result = ($this->processor)($record);

        $nested = $result->context['user'];
        \assert(is_array($nested));
        $this->assertSame('***@***.***', $nested['email']);
        $this->assertSame('John', $nested['name']);
    }

    public function testRedactsInExtraArray(): void
    {
        $record = $this->makeRecord(extra: ['email' => 'admin@site.com']);

        $result = ($this->processor)($record);

        $this->assertSame('***@***.***', $result->extra['email']);
    }

    public function testPreservesNonSensitiveData(): void
    {
        $record = $this->makeRecord(context: [
            'action' => 'set_season_phase',
            'count' => 42,
            'enabled' => true,
        ]);

        $result = ($this->processor)($record);

        $this->assertSame('set_season_phase', $result->context['action']);
        $this->assertSame(42, $result->context['count']);
        $this->assertTrue($result->context['enabled']);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     */
    private function makeRecord(
        string $message = 'test',
        array $context = [],
        array $extra = [],
        string $channel = 'app',
    ): LogRecord {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: $channel,
            level: Level::Info,
            message: $message,
            context: $context,
            extra: $extra,
        );
    }
}
