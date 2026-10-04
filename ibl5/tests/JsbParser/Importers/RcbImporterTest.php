<?php

declare(strict_types=1);

namespace Tests\JsbParser\Importers;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\Importers\RcbImporter;
use JsbParser\RcbFileParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\Importers\RcbImporter
 */
final class RcbImporterTest extends TestCase
{
    /** @var list<list<array<string, mixed>>> */
    private array $alltime = [];

    /** @var list<array{0: int, 1: list<array<string, mixed>>}> */
    private array $season = [];

    /**
     * Build a minimal .rcb file for testing.
     *
     * @param list<string> $alltimeLines 50 lines for all-time section
     * @param list<string> $seasonLines 33 lines for current season section
     */
    private function buildRcbFile(array $alltimeLines, array $seasonLines): string
    {
        // Pad to exactly 50 all-time lines and 33 season lines
        while (count($alltimeLines) < 50) {
            $alltimeLines[] = str_repeat(' ', RcbFileParser::ENTRIES_PER_ALLTIME_LINE * RcbFileParser::ALLTIME_ENTRY_SIZE);
        }
        while (count($seasonLines) < 33) {
            $seasonLines[] = str_repeat(' ', RcbFileParser::ENTRIES_PER_SEASON_LINE * RcbFileParser::SEASON_ENTRY_SIZE);
        }

        // Add trailing lines (83-85)
        $trailing = [
            str_repeat(' ', 110),
            str_repeat(' ', 56),
            str_repeat(' ', 22),
        ];

        $allLines = array_merge($alltimeLines, $seasonLines, $trailing);
        return implode("\r\n", $allLines) . "\r\n";
    }

    /**
     * @return string Valid .rcb file data with one alltime and one season entry
     */
    private function buildMinimalRcbData(): string
    {
        $alltimeEntry = str_pad('Stephen Curry', 33, ' ', STR_PAD_LEFT)
            . str_pad('3851', 5, ' ', STR_PAD_LEFT)
            . str_pad('3611', 6, ' ', STR_PAD_LEFT)
            . str_pad('19', 2, ' ', STR_PAD_LEFT)
            . str_pad('2005', 4);
        $blankAlltime = str_repeat(' ', RcbFileParser::ALLTIME_ENTRY_SIZE);
        $alltimeLine0 = $alltimeEntry . str_repeat($blankAlltime, RcbFileParser::ENTRIES_PER_ALLTIME_LINE - 1);
        $blankAlltimeLine = str_repeat($blankAlltime, RcbFileParser::ENTRIES_PER_ALLTIME_LINE);
        $alltimeLines = [$alltimeLine0];
        for ($i = 1; $i < RcbFileParser::ALLTIME_LINE_COUNT; $i++) {
            $alltimeLines[] = $blankAlltimeLine;
        }

        $seasonEntry = str_pad('PG Stephen Curry', 33, ' ', STR_PAD_LEFT)
            . str_pad('3851', 5, ' ', STR_PAD_LEFT)
            . str_pad('73', 3, ' ', STR_PAD_LEFT)
            . str_pad('2006', 4)
            . str_repeat(' ', 45);
        $blankSeason = str_repeat(' ', RcbFileParser::SEASON_ENTRY_SIZE);
        $seasonLine0 = $seasonEntry . str_repeat($blankSeason, RcbFileParser::ENTRIES_PER_SEASON_LINE - 1);
        $blankSeasonLine = str_repeat($blankSeason, RcbFileParser::ENTRIES_PER_SEASON_LINE);
        $seasonLines = [$seasonLine0];
        for ($i = 1; $i < RcbFileParser::SEASON_LINE_COUNT; $i++) {
            $seasonLines[] = $blankSeasonLine;
        }

        $trailing = [str_repeat(' ', 110), str_repeat(' ', 56), str_repeat(' ', 22)];
        return implode("\r\n", array_merge($alltimeLines, $seasonLines, $trailing)) . "\r\n";
    }

    private function repositoryCapturing(): JsbImportRepositoryInterface&\PHPUnit\Framework\MockObject\Stub
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('replaceRcbAlltimeRecords')->willReturnCallback(function (array $records): int {
            $this->alltime[] = $records;
            return count($records);
        });
        $repo->method('replaceRcbSeasonRecords')->willReturnCallback(function (int $seasonYear, array $records): int {
            $this->season[] = [$seasonYear, $records];
            return count($records);
        });
        return $repo;
    }

    public function testImportSendsExactAlltimeRecords(): void
    {
        $data = $this->buildMinimalRcbData();
        $p = RcbFileParser::parse($data);

        (new RcbImporter($this->repositoryCapturing()))->import($data, 2006, 'rcb.rcb');

        $this->assertCount(1, $this->alltime);
        $this->assertSame(count($p['alltime']), count($this->alltime[0]));
        $first = $p['alltime'][0];
        $this->assertSame('Stephen Curry', $first['player_name']);
        $this->assertSame(3851, $first['car_block_id']);
        $this->assertSame([
            'scope' => $first['scope'],
            'teamid' => $first['teamid'],
            'record_type' => $first['record_type'],
            'stat_category' => $first['stat_category'],
            'ranking' => $first['ranking'],
            'player_name' => 'Stephen Curry',
            'car_block_id' => 3851,
            'pid' => null,
            'stat_value' => $first['stat_value'],
            'stat_raw' => $first['stat_raw'],
            'team_of_record' => $first['team_of_record'],
            'season_year' => $first['season_year'],
            'career_total' => $first['career_total'],
            'source_file' => 'rcb.rcb',
        ], $this->alltime[0][0]);
    }

    public function testImportSendsExactSeasonRecordsWithSeasonYearArgument(): void
    {
        $data = $this->buildMinimalRcbData();
        $p = RcbFileParser::parse($data);

        (new RcbImporter($this->repositoryCapturing()))->import($data, 2006, 'rcb.rcb');

        $this->assertCount(1, $this->season);
        $this->assertSame(2006, $this->season[0][0]);
        $this->assertSame(count($p['currentSeason']), count($this->season[0][1]));
        $first = $p['currentSeason'][0];
        $this->assertSame('Stephen Curry', $first['player_name']);
        $this->assertSame(3851, $first['car_block_id']);
        $this->assertSame([
            'season_year' => 2006,
            'scope' => $first['scope'],
            'teamid' => $first['teamid'],
            'context' => $first['context'],
            'stat_category' => $first['stat_category'],
            'ranking' => $first['ranking'],
            'player_name' => 'Stephen Curry',
            'player_position' => $first['player_position'],
            'car_block_id' => 3851,
            'pid' => null,
            'stat_value' => $first['stat_value'],
            'record_season_year' => $first['season_year'],
            'source_file' => 'rcb.rcb',
        ], $this->season[0][1][0]);
    }

    public function testImportSkipsAlltimeReplaceWhenFlagFalse(): void
    {
        (new RcbImporter($this->repositoryCapturing()))->import($this->buildMinimalRcbData(), 2006, null, false);

        $this->assertSame([], $this->alltime);
        $this->assertCount(1, $this->season);
    }

    public function testImportSkipsBothReplacesWhenSectionsEmpty(): void
    {
        $result = (new RcbImporter($this->repositoryCapturing()))->import($this->buildRcbFile([], []), 2006, 'rcb.rcb');

        $this->assertSame([], $this->alltime);
        $this->assertSame([], $this->season);
        $this->assertSame(0, $result->errors);
        $messages = implode("\n", $result->messages);
        $this->assertStringContainsString('RCB alltime section empty', $messages);
        $this->assertStringContainsString('RCB season section empty', $messages);
    }

    public function testImportConvertsReplaceExceptionToError(): void
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('replaceRcbAlltimeRecords')->willThrowException(new \RuntimeException('db down'));
        $repo->method('replaceRcbSeasonRecords')->willReturnCallback(function (int $seasonYear, array $records): int {
            $this->season[] = [$seasonYear, $records];
            return count($records);
        });

        $result = (new RcbImporter($repo))->import($this->buildMinimalRcbData(), 2006);

        $this->assertSame(1, $result->errors);
        $this->assertStringContainsString('RCB alltime replace failed: db down', implode("\n", $result->messages));
        $this->assertCount(1, $this->season);
    }

    public function testImportFileReportsMissingFile(): void
    {
        $result = (new RcbImporter(self::createStub(JsbImportRepositoryInterface::class)))->importFile('/nonexistent/x.rcb', 2006);

        $this->assertSame(1, $result->errors);
        $this->assertStringContainsString('RCB file not found: /nonexistent/x.rcb', implode("\n", $result->messages));
    }
}
