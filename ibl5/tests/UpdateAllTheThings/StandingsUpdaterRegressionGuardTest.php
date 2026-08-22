<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings;

use PHPUnit\Framework\TestCase;

/**
 * Guards the echo->output-buffer migration (maint-1-18): the StandingsUpdater
 * classes must never regress to emitting GM-facing output via echo, and
 * UpdateStandingsStep must never regress to capturing it with ob_start().
 *
 * Echo detection uses token_get_all() rather than a substring scan because the
 * migrated docblocks legitimately contain the word "echo".
 */
class StandingsUpdaterRegressionGuardTest extends TestCase
{
    private function readSource(string $relativePath): string
    {
        $path = __DIR__ . '/../../classes/Updater/' . $relativePath;
        $this->assertFileExists($path);

        $source = file_get_contents($path);
        if ($source === false) {
            self::fail("Could not read {$path}");
        }

        return $source;
    }

    private function assertNoEchoTokens(string $relativePath): void
    {
        $echoTokens = array_filter(
            token_get_all($this->readSource($relativePath)),
            static fn ($token): bool => is_array($token) && $token[0] === T_ECHO,
        );

        $this->assertSame([], $echoTokens, "{$relativePath} must not emit output via echo");
    }

    public function testStandingsUpdaterSourceHasNoEchoStatements(): void
    {
        $this->assertNoEchoTokens('StandingsUpdater.php');
    }

    public function testOlympicsUpdaterSourceHasNoEchoStatements(): void
    {
        $this->assertNoEchoTokens('OlympicsFlatStandingsUpdater.php');
    }

    public function testUpdateStandingsStepSourceHasNoObStart(): void
    {
        $this->assertStringNotContainsString('ob_start', $this->readSource('Steps/UpdateStandingsStep.php'));
    }
}
