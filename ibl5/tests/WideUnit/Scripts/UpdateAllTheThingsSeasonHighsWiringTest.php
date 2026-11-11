<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\TestCase;

/**
 * Source-content pins for the Season Highs cache step wiring in updateAllTheThings.php.
 * Asserts on the script's SOURCE and never runs it: a real run would touch
 * live data. The step behavior itself is covered by RefreshSeasonHighsCacheStepTest.
 */
final class UpdateAllTheThingsSeasonHighsWiringTest extends TestCase
{
    private const STEP_CALL = 'RefreshSeasonHighsCacheStep::fromDb(';

    private string $src;

    protected function setUp(): void
    {
        $this->src = (string) file_get_contents(dirname(__DIR__, 3) . '/scripts/updateAllTheThings.php');
    }

    public function testSeasonHighsStepRegisteredAfterBoxscoreImport(): void
    {
        $call = strpos($this->src, self::STEP_CALL);
        $boxscores = strpos($this->src, 'ProcessBoxscoresStep');

        self::assertNotFalse($call);
        self::assertNotFalse($boxscores);
        self::assertGreaterThan($boxscores, $call);
    }

    public function testSeasonHighsStepRegisteredAfterAllStarImport(): void
    {
        $call = strpos($this->src, self::STEP_CALL);
        $allStar = strpos($this->src, 'ProcessAllStarGamesStep');

        self::assertNotFalse($call);
        self::assertNotFalse($allStar);
        self::assertGreaterThan($allStar, $call);
    }

    public function testSeasonHighsStepRegisteredInsideIblOnlyBlock(): void
    {
        $call = strpos($this->src, self::STEP_CALL);
        self::assertNotFalse($call);

        $h2h = strrpos(substr($this->src, 0, $call), 'RefreshHeadToHeadRecordsStep');
        self::assertNotFalse($h2h, 'head-to-head registration not found before the Season Highs step');

        self::assertStringNotContainsString("\n    }", substr($this->src, $h2h, $call - $h2h));
    }

    public function testSeasonHighsStepRunsBeforeRecordHoldersStep(): void
    {
        $call = strpos($this->src, self::STEP_CALL);
        $recordHolders = strpos($this->src, 'RefreshRecordHoldersStep::fromDb(');

        self::assertNotFalse($call);
        self::assertNotFalse($recordHolders);
        self::assertLessThan($recordHolders, $call);
    }

    public function testSeasonHighsStepSharesUpdaterBudgetClock(): void
    {
        $call = strpos($this->src, self::STEP_CALL);
        self::assertNotFalse($call);

        $end = strpos($this->src, ';', $call);
        self::assertNotFalse($end);
        $statement = substr($this->src, $call, $end - $call);

        self::assertStringContainsString('$updaterClock', $statement);
        self::assertStringContainsString('$updaterStartedAt', $statement);
    }
}
