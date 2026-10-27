<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Locks the ordering invariant in modules/DepthChartEntry/index.php: the
 * saved-depth-chart api() path resolves its team (and returns 401/403 when it
 * cannot) before it builds the handler through the factory.
 */
class DepthChartEntrySavedApiGuardOrderTest extends TestCase
{
    private const SCRIPT = __DIR__ . '/../../modules/DepthChartEntry/index.php';
    private const HANDLER_CALL = '->savedDepthChartApiHandler(';

    private function offsetOf(string $source, string $needle, int $from = 0): int
    {
        $pos = strpos($source, $needle, $from);
        self::assertNotFalse($pos, $needle . ' not found in DepthChartEntry/index.php');

        return $pos;
    }

    public function testSavedHandlerIsBuiltAfterEveryTeamGuard(): void
    {
        $source = (string) file_get_contents(self::SCRIPT);
        $apiStart = $this->offsetOf($source, 'function api(');
        $handler = $this->offsetOf($source, self::HANDLER_CALL, $apiStart);

        foreach (['isUser($user)', "'No team assigned'", "'Team not found'"] as $guard) {
            $guardOffset = $this->offsetOf($source, $guard, $apiStart);
            self::assertLessThan($handler, $guardOffset, $guard . ' must come before ' . self::HANDLER_CALL);
        }
    }

    public function testSavedHandlerIsBuiltOnlyOnce(): void
    {
        $source = (string) file_get_contents(self::SCRIPT);

        self::assertSame(1, substr_count($source, self::HANDLER_CALL));
    }
}
