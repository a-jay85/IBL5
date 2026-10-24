<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\TestCase;

/**
 * Source-content pins for the record-holders step wiring in updateAllTheThings.php.
 * Asserts on the script's SOURCE and never runs it: a real run would touch
 * live data. The step behavior itself is covered by RefreshRecordHoldersStepTest.
 */
final class UpdateAllTheThingsRecordHoldersWiringTest extends TestCase
{
    private const STEP_CALL = 'RefreshRecordHoldersStep::fromDb(';

    private string $src;
    private int $blockStart;
    private int $blockEnd;

    protected function setUp(): void
    {
        $this->src = (string) file_get_contents(dirname(__DIR__, 3) . '/scripts/updateAllTheThings.php');

        $iblHist = strpos($this->src, 'new Updater\Steps\RefreshIblHistStep($mysqli_db)');
        self::assertNotFalse($iblHist, 'ibl_hist step registration not found');
        $blockStart = strrpos(substr($this->src, 0, $iblHist), 'if (!$isOlympics) {');
        self::assertNotFalse($blockStart, 'non-Olympics block start not found');
        $blockEnd = strpos($this->src, "\n    }\n", $iblHist);
        self::assertNotFalse($blockEnd, 'non-Olympics block end not found');

        $this->blockStart = $blockStart;
        $this->blockEnd = $blockEnd;
    }

    public function testRecordHoldersStepRegisteredAfterProcessBoxscoresStep(): void
    {
        $step = strpos($this->src, self::STEP_CALL);
        $boxscores = strpos($this->src, 'new Updater\Steps\ProcessBoxscoresStep(');

        self::assertNotFalse($step);
        self::assertNotFalse($boxscores);
        self::assertGreaterThan($boxscores, $step);
    }

    public function testRecordHoldersStepRegisteredInsideNonOlympicsBlock(): void
    {
        $step = strpos($this->src, self::STEP_CALL);

        self::assertNotFalse($step);
        self::assertGreaterThan($this->blockStart, $step);
        self::assertLessThan($this->blockEnd, $step);
    }

    public function testRecordHoldersStepRegisteredAfterDerivedTableRefreshes(): void
    {
        $step = strpos($this->src, self::STEP_CALL);
        $playoffs = strpos($this->src, 'new Updater\Steps\RefreshPlayoffSeriesResultsStep(');
        $seasonRecords = strpos($this->src, 'new Updater\Steps\RefreshTeamSeasonRecordsStep(');

        self::assertNotFalse($step);
        self::assertNotFalse($playoffs);
        self::assertNotFalse($seasonRecords);
        self::assertGreaterThan($playoffs, $step);
        self::assertGreaterThan($seasonRecords, $step);
    }

    public function testRecordHoldersStepRegisteredExactlyOnce(): void
    {
        self::assertSame(1, substr_count($this->src, self::STEP_CALL));
    }

    public function testUpdaterStartTimeCapturedAfterTimeLimitAndBeforeRegistration(): void
    {
        $timeLimit = strpos($this->src, 'set_time_limit(300);');
        $startTime = strpos($this->src, '$updaterStartedAt = $updaterClock->microtime();');
        $step = strpos($this->src, self::STEP_CALL);

        self::assertNotFalse($timeLimit);
        self::assertNotFalse($startTime);
        self::assertNotFalse($step);
        self::assertGreaterThan($timeLimit, $startTime);
        self::assertLessThan($step, $startTime);
    }
}
