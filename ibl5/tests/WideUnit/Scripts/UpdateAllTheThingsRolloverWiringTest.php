<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\TestCase;

/**
 * Source-content pins for the season-rollover wiring in updateAllTheThings.php.
 * Asserts on the script's SOURCE and never runs it: a real run would touch
 * live data. The rollover behavior itself is covered by
 * SeasonRolloverRunnerIntegrationTest.
 */
final class UpdateAllTheThingsRolloverWiringTest extends TestCase
{
    private string $src;
    private string $block;

    protected function setUp(): void
    {
        $this->src = (string) file_get_contents(dirname(__DIR__, 3) . '/scripts/updateAllTheThings.php');

        $startMarker = 'if ($seasonYearOverride === null && !$isOlympics) {';
        $endMarker = "echo \$view->renderInitStatus('Season initialized');";

        $start = strpos($this->src, $startMarker);
        self::assertNotFalse($start, 'rollover block start marker not found');
        $end = strpos($this->src, $endMarker, $start);
        self::assertNotFalse($end, 'rollover block end marker not found');

        $this->block = substr($this->src, $start, $end - $start);
    }

    public function testScriptKeepsGuardsAboveTryBlock(): void
    {
        $tryPos = strpos($this->src, "\ntry {");
        self::assertNotFalse($tryPos);

        $adminPos = strpos($this->src, 'is_admin');
        self::assertNotFalse($adminPos);
        $lcpPos = strpos($this->src, 'lcp_update_all');
        self::assertNotFalse($lcpPos);

        self::assertLessThan($tryPos, $adminPos, 'is_admin guard must precede the try block');
        self::assertLessThan($tryPos, $lcpPos, 'lcp_update_all CSRF guard must precede the try block');
    }

    public function testScriptPassesBasePathAndFilePrefixToDetector(): void
    {
        self::assertStringContainsString('$basePath = $_SERVER[\'DOCUMENT_ROOT\'] . \'/ibl5\';', $this->src);
        self::assertStringContainsString('$backupLocator, $archiveExtractor, $basePath, $filePrefix,', $this->block);
    }

    public function testScriptScopesSettingsRepositoriesToLeagueContext(): void
    {
        self::assertSame(
            2,
            substr_count($this->block, 'new LeagueControlPanel\LeagueControlPanelRepository($mysqli_db, $leagueContext)')
        );
    }

    public function testScriptBuildsAndRunsRunnerInsideNonOlympicsBranch(): void
    {
        self::assertStringContainsString('new Updater\SeasonRollover\SeasonRolloverRunner(', $this->block);
        self::assertStringContainsString('$rolloverRunner->run($season->beginningYear, $season->endingYear)', $this->block);
    }

    public function testScriptRebuildsSeasonOnlyAfterRollover(): void
    {
        $guard = 'if ($rolloverRun->rolledOver()) {';
        self::assertStringContainsString($guard, $this->block);

        $guardPos = strpos($this->block, $guard);
        $rebuildPos = strpos($this->block, '$season = new \Season\Season($mysqli_db);');
        self::assertNotFalse($guardPos);
        self::assertNotFalse($rebuildPos, 'Season rebuild not found in rollover block');
        self::assertGreaterThan($guardPos, $rebuildPos);
    }

    public function testScriptSanitizesRolloverReason(): void
    {
        self::assertStringContainsString('HtmlSanitizer::safeHtmlOutput($rolloverRun->decision->reason)', $this->block);
    }

    public function testScriptHasNoInlineRolloverCalls(): void
    {
        foreach (['->apply($rolloverResult)', '$cashCyAdvancer->advance(', '$rolloverDetector->detect('] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $this->src, 'inline rollover call: ' . $forbidden);
        }
    }
}
