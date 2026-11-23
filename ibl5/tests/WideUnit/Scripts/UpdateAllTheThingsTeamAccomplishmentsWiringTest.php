<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\TestCase;

/**
 * Source-content pins for the team-accomplishments step wiring in updateAllTheThings.php.
 * Asserts on the script's SOURCE and never runs it: a real run would touch
 * live data. The step behavior itself is covered by RefreshTeamAccomplishmentsStepTest.
 */
final class UpdateAllTheThingsTeamAccomplishmentsWiringTest extends TestCase
{
    private const STEP_CALL = 'RefreshTeamAccomplishmentsStep::fromDb(';

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

    public function testTeamAccomplishmentsStepRegisteredAfterSourceTableWriters(): void
    {
        $step = strpos($this->src, self::STEP_CALL);
        self::assertNotFalse($step);

        $writers = [
            'new Updater\Steps\ProcessBoxscoresStep(',
            'new Updater\Steps\RefreshPlayoffSeriesResultsStep(',
            'new Updater\Steps\RefreshIblHistStep($mysqli_db)',
            'RefreshRecordHoldersStep::fromDb(',
            'new Updater\Steps\GenerateSeasonAwardsStep(',
        ];
        foreach ($writers as $writer) {
            $offset = strpos($this->src, $writer);
            self::assertNotFalse($offset, $writer . ' registration not found');
            self::assertGreaterThan($offset, $step, 'team accomplishments step must come after ' . $writer);
        }
    }

    public function testTeamAccomplishmentsStepRegisteredInsideNonOlympicsBlock(): void
    {
        $step = strpos($this->src, self::STEP_CALL);

        self::assertNotFalse($step);
        self::assertGreaterThan($this->blockStart, $step);
        self::assertLessThan($this->blockEnd, $step);
    }

    public function testTeamAccomplishmentsStepRegisteredExactlyOnce(): void
    {
        self::assertSame(1, substr_count($this->src, self::STEP_CALL));
    }
}
