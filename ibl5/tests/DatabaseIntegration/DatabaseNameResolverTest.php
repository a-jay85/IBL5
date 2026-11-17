<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\TestCase;

final class DatabaseNameResolverTest extends TestCase
{
    private string|false $savedFlag = false;
    private string|false $savedToken = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedFlag = getenv('INFECTION_PER_THREAD_DATABASE');
        $this->savedToken = getenv('TEST_TOKEN');
    }

    protected function tearDown(): void
    {
        $this->restoreEnv('INFECTION_PER_THREAD_DATABASE', $this->savedFlag);
        $this->restoreEnv('TEST_TOKEN', $this->savedToken);
        parent::tearDown();
    }

    private function restoreEnv(string $name, string|false $value): void
    {
        putenv($value === false ? $name : $name . '=' . $value);
    }

    public function testReturnsBaseNameWhenPerThreadUnset(): void
    {
        self::assertSame('ibl5', DatabaseNameResolver::resolve('ibl5', false, '2'));
    }

    public function testReturnsBaseNameWhenPerThreadEmpty(): void
    {
        self::assertSame('ibl5', DatabaseNameResolver::resolve('ibl5', '', '2'));
    }

    public function testAppendsThreadTokenWhenPerThreadEnabled(): void
    {
        self::assertSame('ibl5_t3', DatabaseNameResolver::resolve('ibl5', '1', '3'));
    }

    public function testThrowsWhenPerThreadEnabledAndTokenUnset(): void
    {
        try {
            DatabaseNameResolver::resolve('ibl5', '1', false);
            self::fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('TEST_TOKEN is unset', $e->getMessage());
        }
    }

    public function testThrowsWhenPerThreadEnabledAndTokenEmpty(): void
    {
        $this->expectException(\RuntimeException::class);
        DatabaseNameResolver::resolve('ibl5', '1', '');
    }

    public function testThrowsOnNonNumericThreadToken(): void
    {
        $this->expectException(\RuntimeException::class);
        DatabaseNameResolver::resolve('ibl5', '1', 'abc');
    }

    public function testThrowsOnThreeDigitThreadToken(): void
    {
        $this->expectException(\RuntimeException::class);
        DatabaseNameResolver::resolve('ibl5', '1', '100');
    }

    public function testThrowsOnThreadTokenWithTrailingNewline(): void
    {
        $this->expectException(\RuntimeException::class);
        DatabaseNameResolver::resolve('ibl5', '1', "1\n");
    }

    public function testFromEnvironmentReadsFlagAndToken(): void
    {
        putenv('INFECTION_PER_THREAD_DATABASE=1');
        putenv('TEST_TOKEN=4');

        self::assertSame('ibl5_t4', DatabaseNameResolver::fromEnvironment('ibl5'));
    }

    public function testInfectionThreadCountMatchesClonedDatabases(): void
    {
        $config = file_get_contents(dirname(__DIR__, 2) . '/infection.json5');
        self::assertNotFalse($config);
        self::assertSame(1, preg_match('/"threads":\s*(\d+)/', $config, $match));

        $expectedLoop = 'for t in ' . implode(' ', range(1, (int) $match[1])) . '; do';
        foreach (['mutation.yml', 'mutation-pr.yml'] as $workflow) {
            $path = dirname(__DIR__, 3) . '/.github/workflows/' . $workflow;
            if (!is_file($path)) {
                self::markTestSkipped($workflow . ' is not present (only ibl5/ is mounted in the test container)');
            }
            $contents = file_get_contents($path);
            self::assertNotFalse($contents);
            self::assertStringContainsString($expectedLoop, $contents, $workflow . ' must clone one database per Infection thread');
        }
    }
}
