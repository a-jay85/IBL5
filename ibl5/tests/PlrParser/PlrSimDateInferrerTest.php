<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PHPUnit\Framework\TestCase;
use PlrParser\Contracts\PlrBoxScoreRepositoryInterface;
use PlrParser\PlrFieldSerializer;
use PlrParser\PlrFileWriter;
use PlrParser\PlrSimDateInferrer;

/**
 * @covers \PlrParser\PlrSimDateInferrer
 */
final class PlrSimDateInferrerTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildRecord(int $ordinal, int $pid, int $gp, int $min, int $twoGm, int $ftm, int $threeGm): string
    {
        $record = str_repeat(' ', PlrFileWriter::PLAYER_RECORD_LENGTH);
        $record = substr_replace($record, PlrFieldSerializer::formatInt($ordinal, PlrFileWriter::WIDTH_ORDINAL), PlrFileWriter::OFFSET_ORDINAL, PlrFileWriter::WIDTH_ORDINAL);
        $record = substr_replace($record, PlrFieldSerializer::formatInt($pid, PlrFileWriter::WIDTH_PID), PlrFileWriter::OFFSET_PID, PlrFileWriter::WIDTH_PID);
        $record = substr_replace($record, PlrFieldSerializer::formatInt($gp, 4), 148, 4);
        $record = substr_replace($record, PlrFieldSerializer::formatInt($min, 4), 152, 4);
        $record = substr_replace($record, PlrFieldSerializer::formatInt($twoGm, 4), 156, 4);
        $record = substr_replace($record, PlrFieldSerializer::formatInt($ftm, 4), 164, 4);
        $record = substr_replace($record, PlrFieldSerializer::formatInt($threeGm, 4), 172, 4);
        return $record;
    }

    private function writePlr(string ...$records): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'plr_infer_');
        file_put_contents($path, implode("\r\n", $records));
        $this->tempFiles[] = $path;
        return $path;
    }

    /**
     * @return array{date: string, gp: int, min: int, two_gm: int, two_ga: int, ftm: int, fta: int, three_gm: int, three_ga: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int}
     */
    private function cumulativeRow(string $date, int $gp, int $min, int $twoGm, int $ftm, int $threeGm): array
    {
        return [
            'date' => $date,
            'gp' => $gp,
            'min' => $min,
            'two_gm' => $twoGm,
            'two_ga' => 0,
            'ftm' => $ftm,
            'fta' => 0,
            'three_gm' => $threeGm,
            'three_ga' => 0,
            'orb' => 0,
            'drb' => 0,
            'ast' => 0,
            'stl' => 0,
            'tov' => 0,
            'blk' => 0,
            'pf' => 0,
        ];
    }

    // -----------------------------------------------------------------------
    // inferBaseEndDate tests
    // -----------------------------------------------------------------------

    public function testInfersSimEndDateFromBoxScoreData(): void
    {
        $path = $this->writePlr($this->buildRecord(1, 101, 30, 900, 150, 60, 40));

        $mockRepo = self::createMock(PlrBoxScoreRepositoryInterface::class);
        $mockRepo->expects(self::once())
            ->method('cumulativeRegularSeasonStatsByDate')
            ->with(101, 2026)
            ->willReturn([
                $this->cumulativeRow('2026-01-10', 29, 900, 150, 60, 40),
                $this->cumulativeRow('2026-01-17', 30, 900, 150, 60, 40),
                $this->cumulativeRow('2026-01-24', 31, 900, 150, 60, 40),
            ]);

        $inferrer = new PlrSimDateInferrer($mockRepo);
        self::assertSame('2026-01-17', $inferrer->inferBaseEndDate($path, 2026));
    }

    public function testReturnsNullWhenNoBoxScoreData(): void
    {
        $path = $this->writePlr($this->buildRecord(1, 101, 30, 900, 150, 60, 40));

        $stubRepo = self::createStub(PlrBoxScoreRepositoryInterface::class);
        $stubRepo->method('cumulativeRegularSeasonStatsByDate')->willReturn([]);

        $inferrer = new PlrSimDateInferrer($stubRepo);
        self::assertNull($inferrer->inferBaseEndDate($path, 2026));
    }

    public function testReturnsNullWhenNoCumulativeRowMatches(): void
    {
        $path = $this->writePlr($this->buildRecord(1, 101, 30, 900, 150, 60, 40));

        // ftm differs on every row (55 instead of 60)
        $stubRepo = self::createStub(PlrBoxScoreRepositoryInterface::class);
        $stubRepo->method('cumulativeRegularSeasonStatsByDate')->willReturn([
            $this->cumulativeRow('2026-01-10', 29, 900, 150, 55, 40),
            $this->cumulativeRow('2026-01-17', 30, 900, 150, 55, 40),
            $this->cumulativeRow('2026-01-24', 31, 900, 150, 55, 40),
        ]);

        $inferrer = new PlrSimDateInferrer($stubRepo);
        self::assertNull($inferrer->inferBaseEndDate($path, 2026));
    }

    public function testReturnsNullWhenNoPlayerMeetsMinuteThreshold(): void
    {
        // min=499 is below the 500-minute threshold
        $path = $this->writePlr($this->buildRecord(1, 101, 30, 499, 150, 60, 40));

        $mockRepo = self::createMock(PlrBoxScoreRepositoryInterface::class);
        $mockRepo->expects(self::never())
            ->method('cumulativeRegularSeasonStatsByDate');

        $inferrer = new PlrSimDateInferrer($mockRepo);
        self::assertNull($inferrer->inferBaseEndDate($path, 2026));
    }

    public function testIncludesPlayerAtExactMinuteThreshold(): void
    {
        // min=500 equals the threshold — should be included
        $path = $this->writePlr($this->buildRecord(1, 202, 20, 500, 80, 30, 25));

        $mockRepo = self::createMock(PlrBoxScoreRepositoryInterface::class);
        $mockRepo->expects(self::once())
            ->method('cumulativeRegularSeasonStatsByDate')
            ->with(202, 2026)
            ->willReturn([
                $this->cumulativeRow('2026-02-01', 20, 500, 80, 30, 25),
            ]);

        $inferrer = new PlrSimDateInferrer($mockRepo);
        self::assertSame('2026-02-01', $inferrer->inferBaseEndDate($path, 2026));
    }

    public function testPicksHighestMinutesPlayerAsReference(): void
    {
        // pid=301 has 600 min, pid=302 has 1200 min — should pick pid=302
        $path = $this->writePlr(
            $this->buildRecord(1, 301, 20, 600, 80, 30, 25),
            $this->buildRecord(2, 302, 40, 1200, 200, 80, 60),
        );

        $mockRepo = self::createMock(PlrBoxScoreRepositoryInterface::class);
        $mockRepo->expects(self::once())
            ->method('cumulativeRegularSeasonStatsByDate')
            ->with(302, 2026)
            ->willReturn([
                $this->cumulativeRow('2026-03-15', 40, 1200, 200, 80, 60),
            ]);

        $inferrer = new PlrSimDateInferrer($mockRepo);
        self::assertSame('2026-03-15', $inferrer->inferBaseEndDate($path, 2026));
    }

    // -----------------------------------------------------------------------
    // inferNextSimEndDate tests
    // -----------------------------------------------------------------------

    public function testInferNextSimEndDateReturnsFollowingDate(): void
    {
        $stubRepo = self::createStub(PlrBoxScoreRepositoryInterface::class);
        $stubRepo->method('simEndDatesForSeason')->willReturn(['2026-01-03', '2026-01-10', '2026-01-17']);

        $inferrer = new PlrSimDateInferrer($stubRepo);
        self::assertSame('2026-01-10', $inferrer->inferNextSimEndDate('2026-01-03', 2026));
        self::assertSame('2026-01-17', $inferrer->inferNextSimEndDate('2026-01-03', 2026, 2));
    }

    public function testInferNextSimEndDateReturnsNullWhenBaseDateUnknown(): void
    {
        $stubRepo = self::createStub(PlrBoxScoreRepositoryInterface::class);
        $stubRepo->method('simEndDatesForSeason')->willReturn(['2026-01-03', '2026-01-10', '2026-01-17']);

        $inferrer = new PlrSimDateInferrer($stubRepo);
        self::assertNull($inferrer->inferNextSimEndDate('2025-12-31', 2026));
    }

    public function testInferNextSimEndDateReturnsNullPastLastSim(): void
    {
        $stubRepo = self::createStub(PlrBoxScoreRepositoryInterface::class);
        $stubRepo->method('simEndDatesForSeason')->willReturn(['2026-01-03', '2026-01-10', '2026-01-17']);

        $inferrer = new PlrSimDateInferrer($stubRepo);
        self::assertNull($inferrer->inferNextSimEndDate('2026-01-17', 2026));
    }

    // -----------------------------------------------------------------------
    // getBoxScoreCoverageForSeason tests
    // -----------------------------------------------------------------------

    public function testGetBoxScoreCoverageUsesRegularSeasonGameType(): void
    {
        $mockRepo = self::createMock(PlrBoxScoreRepositoryInterface::class);
        $mockRepo->expects(self::once())
            ->method('latestGameDate')
            ->with(2026, PlrBoxScoreRepositoryInterface::GAME_TYPE_REGULAR_SEASON)
            ->willReturn('2026-03-01');

        $inferrer = new PlrSimDateInferrer($mockRepo);
        self::assertSame('2026-03-01', $inferrer->getBoxScoreCoverageForSeason(2026));
    }
}
