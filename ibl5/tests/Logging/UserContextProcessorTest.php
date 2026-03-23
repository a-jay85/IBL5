<?php

declare(strict_types=1);

namespace Tests\Logging;

use Logging\UserContextProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class UserContextProcessorTest extends TestCase
{
    private UserContextProcessor $processor;

    /** @var array<string, mixed> */
    private array $originalSession;

    protected function setUp(): void
    {
        $this->processor = new UserContextProcessor();
        $this->originalSession = $_SESSION ?? [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
    }

    public function testAddsUserContextWhenAuthenticated(): void
    {
        $_SESSION['auth_user_id'] = 42;
        $_SESSION['auth_username'] = 'jsmith';

        $record = ($this->processor)($this->createRecord());

        $this->assertSame(42, $record->extra['user_id']);
        $this->assertSame('jsmith', $record->extra['username']);
    }

    public function testOmitsContextWhenUnauthenticated(): void
    {
        $_SESSION = [];

        $record = ($this->processor)($this->createRecord());

        $this->assertArrayNotHasKey('user_id', $record->extra);
        $this->assertArrayNotHasKey('username', $record->extra);
    }

    public function testRejectsWrongTypes(): void
    {
        $_SESSION['auth_user_id'] = '5';
        $_SESSION['auth_username'] = 123;

        $record = ($this->processor)($this->createRecord());

        $this->assertArrayNotHasKey('user_id', $record->extra);
        $this->assertArrayNotHasKey('username', $record->extra);
    }

    public function testPreservesExistingExtra(): void
    {
        $_SESSION['auth_user_id'] = 1;
        $_SESSION['auth_username'] = 'admin';

        $record = $this->createRecord(['foo' => 'bar']);
        $result = ($this->processor)($record);

        $this->assertSame('bar', $result->extra['foo']);
        $this->assertSame(1, $result->extra['user_id']);
        $this->assertSame('admin', $result->extra['username']);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function createRecord(array $extra = []): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'test message',
            extra: $extra,
        );
    }
}
