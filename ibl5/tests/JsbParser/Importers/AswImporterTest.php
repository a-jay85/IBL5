<?php

declare(strict_types=1);

namespace Tests\JsbParser\Importers;

use JsbParser\AswFileParser;
use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\Importers\AswImporter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\Importers\AswImporter
 */
final class AswImporterTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $captured = [];

    /** @var list<array<string, mixed>> */
    private array $capturedScores = [];

    /**
     * Build a synthetic .asw file with known roster and score data.
     *
     * The .asw format has 112 data lines. Sections:
     * - Lines 0-15: All-Star Team 1 roster (PIDs)
     * - Lines 16-30: All-Star Team 2 roster
     * - Lines 31-45: Rookie Team 1 roster
     * - Lines 46-60: Rookie Team 2 roster
     * - Lines 61-70: 3-Point Shootout participants
     * - Lines 71-80: Dunk Contest participants
     * - Lines 81-96: Dunk scores (81=header, 82-89=round1, 90-92=finals)
     * - Lines 97-111: 3-pt scores (97-104=round1, 105-108=semis, 109-110=finals)
     *
     * @param list<int> $allstar1 PIDs for All-Star Team 1 (up to 16)
     * @param list<int> $allstar2 PIDs for All-Star Team 2 (up to 15)
     * @param list<int> $threePointPids 3-pt contest participant PIDs
     * @param list<int> $dunkPids Dunk contest participant PIDs
     * @param list<int> $dunkRound1Scores Dunk round 1 scores (stored as score*10)
     * @param list<int> $dunkFinalsScores Dunk finals scores
     * @param list<int> $threePtRound1Scores 3-pt round 1 scores (raw count)
     * @param list<int> $threePtSemisScores 3-pt semis scores
     * @param list<int> $threePtFinalsScores 3-pt finals scores
     */
    private function buildAswFile(
        array $allstar1 = [],
        array $allstar2 = [],
        array $threePointPids = [],
        array $dunkPids = [],
        array $dunkRound1Scores = [],
        array $dunkFinalsScores = [],
        array $threePtRound1Scores = [],
        array $threePtSemisScores = [],
        array $threePtFinalsScores = [],
    ): string {
        // Initialize 112 lines with "0". array_map with a string-typed callback yields
        // list<string> (not list<'0'>), matching fillLines()'s by-ref param type.
        $lines = array_map(
            static fn (int $i): string => '0',
            range(0, AswFileParser::TOTAL_DATA_LINES - 1)
        );

        // Fill roster sections
        $this->fillLines($lines, AswFileParser::ALLSTAR_1_START, $allstar1);
        $this->fillLines($lines, AswFileParser::ALLSTAR_2_START, $allstar2);
        // Rookie rosters left as 0
        $this->fillLines($lines, AswFileParser::THREE_POINT_START, $threePointPids);
        $this->fillLines($lines, AswFileParser::DUNK_CONTEST_START, $dunkPids);

        // Fill dunk scores: 82-89 = round1, 90-92 = finals
        $this->fillLines($lines, 82, $dunkRound1Scores);
        $this->fillLines($lines, 90, $dunkFinalsScores);

        // Fill 3-pt scores: 97-104 = round1, 105-108 = semis, 109-110 = finals
        $this->fillLines($lines, 97, $threePtRound1Scores);
        $this->fillLines($lines, 105, $threePtSemisScores);
        $this->fillLines($lines, 109, $threePtFinalsScores);

        $content = implode("\r\n", $lines) . "\r\n";

        // Pad to exactly 10,000 bytes
        if (strlen($content) < AswFileParser::FILE_SIZE) {
            $content = str_pad($content, AswFileParser::FILE_SIZE);
        }

        return $content;
    }

    /**
     * Fill lines array starting at offset with values.
     *
     * @param array<int, string> $lines Lines array (modified in place)
     * @param int $startLine Starting line index
     * @param list<int> $values Values to fill
     */
    private function fillLines(array &$lines, int $startLine, array $values): void
    {
        foreach ($values as $i => $value) {
            $lines[$startLine + $i] = (string) $value;
        }
    }

    private function buildFixture(): string
    {
        return $this->buildAswFile(
            allstar1: [101, 102],
            threePointPids: [301, 302],
            dunkPids: [401, 402],
            dunkRound1Scores: [455, 480, 470],
            threePtRound1Scores: [18, 21],
        );
    }

    private function repositoryCapturing(string $method): JsbImportRepositoryInterface&\PHPUnit\Framework\MockObject\Stub
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method($method)->willReturnCallback(function (array $record): int {
            $this->captured[] = $record;
            return 1;
        });
        return $repo;
    }

    private function scoresCapturing(JsbImportRepositoryInterface&\PHPUnit\Framework\MockObject\Stub $repo): void
    {
        $repo->method('upsertAllStarScore')->willReturnCallback(function (array $record): int {
            $this->capturedScores[] = $record;
            return 1;
        });
    }

    private function repositoryWithNames(): JsbImportRepositoryInterface&\PHPUnit\Framework\MockObject\Stub
    {
        $repo = $this->repositoryCapturing('upsertAllStarRoster');
        $this->scoresCapturing($repo);
        $repo->method('getPlayerName')->willReturnMap([
            [101, 'Star One'],
            [102, 'Star Two'],
            [301, 'Shooter One'],
            [302, 'Shooter Two'],
            [401, 'Dunker One'],
            [402, 'Dunker Two'],
        ]);
        return $repo;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rostersOfType(string $eventType): array
    {
        return array_values(array_filter(
            $this->captured,
            static fn (array $record): bool => $record['event_type'] === $eventType,
        ));
    }

    public function testImportSendsExactRosterRecordsWithOneBasedSlots(): void
    {
        $result = (new AswImporter($this->repositoryWithNames()))->import($this->buildFixture(), 2024);

        $this->assertSame(6 + 5, $result->inserted);
        $this->assertCount(6, $this->captured);
        $this->assertSame([
            [
                'season_year' => 2024,
                'event_type' => 'allstar_1',
                'roster_slot' => 1,
                'pid' => 101,
                'player_name' => 'Star One',
            ],
            [
                'season_year' => 2024,
                'event_type' => 'allstar_1',
                'roster_slot' => 2,
                'pid' => 102,
                'player_name' => 'Star Two',
            ],
        ], $this->rostersOfType('allstar_1'));
        $this->assertSame([
            [
                'season_year' => 2024,
                'event_type' => 'three_point',
                'roster_slot' => 1,
                'pid' => 301,
                'player_name' => 'Shooter One',
            ],
            [
                'season_year' => 2024,
                'event_type' => 'three_point',
                'roster_slot' => 2,
                'pid' => 302,
                'player_name' => 'Shooter Two',
            ],
        ], $this->rostersOfType('three_point'));
        $this->assertSame([
            [
                'season_year' => 2024,
                'event_type' => 'dunk_contest',
                'roster_slot' => 1,
                'pid' => 401,
                'player_name' => 'Dunker One',
            ],
            [
                'season_year' => 2024,
                'event_type' => 'dunk_contest',
                'roster_slot' => 2,
                'pid' => 402,
                'player_name' => 'Dunker Two',
            ],
        ], $this->rostersOfType('dunk_contest'));
    }

    public function testImportSendsExactContestScoreRecords(): void
    {
        (new AswImporter($this->repositoryWithNames()))->import($this->buildFixture(), 2024);

        $this->assertCount(5, $this->capturedScores);
        $threePoint = array_values(array_filter(
            $this->capturedScores,
            static fn (array $record): bool => $record['contest_type'] === 'three_point',
        ));
        $this->assertSame([
            [
                'season_year' => 2024,
                'contest_type' => 'three_point',
                'round' => 1,
                'participant_slot' => 1,
                'pid' => 301,
                'score' => 18,
            ],
            [
                'season_year' => 2024,
                'contest_type' => 'three_point',
                'round' => 1,
                'participant_slot' => 2,
                'pid' => 302,
                'score' => 21,
            ],
        ], $threePoint);
        $this->assertSame([
            'season_year' => 2024,
            'contest_type' => 'dunk_contest',
            'round' => 1,
            'participant_slot' => 1,
            'pid' => 401,
            'score' => 455,
        ], $this->capturedScores[0]);
    }

    public function testImportSetsNullPidWhenScoreSlotHasNoParticipant(): void
    {
        (new AswImporter($this->repositoryWithNames()))->import($this->buildFixture(), 2024);

        $this->assertSame([
            'season_year' => 2024,
            'contest_type' => 'dunk_contest',
            'round' => 1,
            'participant_slot' => 3,
            'pid' => null,
            'score' => 470,
        ], $this->capturedScores[2]);
    }

    public function testImportSendsNullPlayerNameForUnknownPlayer(): void
    {
        $repo = $this->repositoryCapturing('upsertAllStarRoster');
        $this->scoresCapturing($repo);
        $repo->method('getPlayerName')->willReturn(null);

        (new AswImporter($repo))->import($this->buildFixture(), 2024);

        $this->assertSame([
            'season_year' => 2024,
            'event_type' => 'allstar_1',
            'roster_slot' => 1,
            'pid' => 101,
            'player_name' => null,
        ], $this->captured[0]);
    }

    public function testImportConvertsRosterExceptionToError(): void
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('getPlayerName')->willReturn(null);
        $repo->method('upsertAllStarRoster')->willThrowException(new \RuntimeException('db down'));
        $this->scoresCapturing($repo);

        $result = (new AswImporter($repo))->import($this->buildFixture(), 2024);

        $this->assertSame(6, $result->errors);
        $this->assertSame(5, $result->inserted);
        $this->assertStringContainsString('All-Star roster upsert failed: db down', implode("\n", $result->messages));
        $this->assertCount(5, $this->capturedScores);
    }

    public function testImportFileReportsMissingFile(): void
    {
        $result = (new AswImporter(self::createStub(JsbImportRepositoryInterface::class)))->importFile('/nonexistent/x.asw', 2024);

        $this->assertSame(1, $result->errors);
        $this->assertStringContainsString('ASW file not found: /nonexistent/x.asw', implode("\n", $result->messages));
    }
}
