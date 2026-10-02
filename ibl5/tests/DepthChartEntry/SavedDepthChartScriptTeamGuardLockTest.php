<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use PHPUnit\Framework\TestCase;

/**
 * The saved-depth-chart API branch of modules/DepthChartEntry/index.php is a procedural script that reads
 * $user, $authService and $mysqli_db from the including scope, so it is not unit-instantiable. These tests
 * pin the source structure of its two refusal guards instead.
 */
class SavedDepthChartScriptTeamGuardLockTest extends TestCase
{
    private const HANDLER = 'new SavedDepthChart\SavedDepthChartApiHandler(';

    private function source(): string
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/modules/DepthChartEntry/index.php');
        self::assertIsString($src);

        return $src;
    }

    public function testSavedDepthChartScriptRefusesTeamlessSessionBeforeHandler(): void
    {
        $src = $this->source();

        $matched = preg_match(
            '/if \(\$teamName === null \|\| \$teamName === \'\' \|\| \$teamName === (?:\'Free Agents\'|\\\\League\\\\League::FREE_AGENTS_TEAM_NAME)\) \{\s*header\([^\n]*\);\s*http_response_code\(403\);[^}]*return;/',
            $src,
            $m,
            PREG_OFFSET_CAPTURE
        );
        self::assertSame(1, $matched, 'saved-depth-chart team guard removed or weakened');

        $handlerPos = strpos($src, self::HANDLER);
        self::assertIsInt($handlerPos, 'saved-depth-chart handler construction not found');
        self::assertLessThan($handlerPos, $m[0][1], 'saved-depth-chart team guard must precede handler construction');
    }

    public function testSavedDepthChartScriptRefusesUnresolvedTeamIdBeforeHandler(): void
    {
        $src = $this->source();

        $matched = preg_match(
            '/\$teamid = \$commonRepo->getTidFromTeamname\(\$teamName\) \?\? 0;\s*if \(\$teamid === 0\) \{\s*header\([^\n]*\);\s*http_response_code\(403\);[^}]*return;/',
            $src,
            $m,
            PREG_OFFSET_CAPTURE
        );
        self::assertSame(1, $matched, 'saved-depth-chart unresolved-teamid guard removed or weakened');

        $handlerPos = strpos($src, self::HANDLER);
        self::assertIsInt($handlerPos, 'saved-depth-chart handler construction not found');
        self::assertLessThan($handlerPos, $m[0][1], 'saved-depth-chart unresolved-teamid guard must precede handler construction');
    }
}
