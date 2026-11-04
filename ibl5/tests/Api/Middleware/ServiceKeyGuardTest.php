<?php

declare(strict_types=1);

namespace Tests\Api\Middleware;

use Api\Middleware\ServiceKeyGuard;
use Api\Repository\ApiKeyRepository;
use PHPUnit\Framework\TestCase;

class ServiceKeyGuardTest extends TestCase
{
    protected function setUp(): void
    {
        unset($_SERVER['HTTP_X_API_KEY'], $_GET['key']);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_API_KEY'], $_GET['key']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(string $tier): array
    {
        return [
            'key_hash'         => hash('sha256', 'test-bot-key'),
            'key_prefix'       => 'test-bot',
            'owner_name'       => 'x',
            'permission_level' => 'public',
            'rate_limit_tier'  => $tier,
        ];
    }

    /**
     * @param array<string, mixed>|null $row
     */
    private static function guardReturning(?array $row): ServiceKeyGuard
    {
        $keys = self::createStub(ApiKeyRepository::class);
        $keys->method('findByHash')->willReturn($row);
        return new ServiceKeyGuard($keys);
    }

    public function testUnlimitedHeaderKeyIsServiceKey(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'bot-key';

        self::assertTrue(self::guardReturning(self::row('unlimited'))->callerIsServiceKey());
    }

    public function testElevatedHeaderKeyIsServiceKey(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'bot-key';

        self::assertTrue(self::guardReturning(self::row('elevated'))->callerIsServiceKey());
    }

    public function testStandardTierKeyIsNotServiceKey(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'gm-key';

        self::assertFalse(self::guardReturning(self::row('standard'))->callerIsServiceKey());
    }

    public function testUnknownKeyIsNotServiceKey(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'nope';

        self::assertFalse(self::guardReturning(null)->callerIsServiceKey());
    }

    public function testMissingKeyIsRejectedWithoutLookup(): void
    {
        $keys = $this->createMock(ApiKeyRepository::class);
        $keys->expects($this->never())->method('findByHash');

        self::assertFalse((new ServiceKeyGuard($keys))->callerIsServiceKey());
    }

    public function testEmptyHeaderFallsBackToQueryKey(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = '';
        $_GET['key'] = 'bot-key';

        $keys = $this->createMock(ApiKeyRepository::class);
        $keys->expects($this->once())
            ->method('findByHash')
            ->with(hash('sha256', 'bot-key'))
            ->willReturn(self::row('unlimited'));

        self::assertTrue((new ServiceKeyGuard($keys))->callerIsServiceKey());
    }

    public function testNonStringHeaderFallsBackToQueryKey(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = ['x'];
        $_GET['key'] = 'bot-key';

        $keys = $this->createMock(ApiKeyRepository::class);
        $keys->expects($this->once())
            ->method('findByHash')
            ->with(hash('sha256', 'bot-key'))
            ->willReturn(self::row('unlimited'));

        self::assertTrue((new ServiceKeyGuard($keys))->callerIsServiceKey());
    }

    public function testHeaderKeyTakesPrecedenceOverQueryKey(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'gm-key';
        $_GET['key'] = 'bot-key';

        $keys = $this->createMock(ApiKeyRepository::class);
        $keys->expects($this->once())
            ->method('findByHash')
            ->with(hash('sha256', 'gm-key'))
            ->willReturn(self::row('standard'));

        self::assertFalse((new ServiceKeyGuard($keys))->callerIsServiceKey());
    }

    public function testNonStringQueryKeyIsRejectedWithoutLookup(): void
    {
        $_GET['key'] = ['x'];

        $keys = $this->createMock(ApiKeyRepository::class);
        $keys->expects($this->never())->method('findByHash');

        self::assertFalse((new ServiceKeyGuard($keys))->callerIsServiceKey());
    }

    public function testEmptyQueryKeyIsRejectedWithoutLookup(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = '';
        $_GET['key'] = '';

        $keys = $this->createMock(ApiKeyRepository::class);
        $keys->expects($this->never())->method('findByHash');

        self::assertFalse((new ServiceKeyGuard($keys))->callerIsServiceKey());
    }
}
