<?php

declare(strict_types=1);

namespace Tests\JsbParser\Importers;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\Importers\AswImporter;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/BuildsAswFixtures.php';

/**
 * @covers \JsbParser\Importers\AswImporter
 */
final class AswImporterTest extends TestCase
{
    use BuildsAswFixtures;

    /** @var list<array<string, mixed>> */
    private array $captured = [];

    /** @var list<array<string, mixed>> */
    private array $capturedScores = [];

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

    private function buildAwardFixture(): string
    {
        return $this->buildAswFile(
            allstar1: [101],
            allstar2: [102],
            threePointPids: [301],
            dunkPids: [401],
            rookie1: [201],
            rookie2: [202],
        );
    }

    /**
     * @return JsbImportRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function strictRepositoryWithWrites(): JsbImportRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
    {
        $repo = self::createMock(JsbImportRepositoryInterface::class);
        $repo->method('getPlayerName')->willReturnCallback(static fn (int $pid): string => 'P' . $pid);
        $repo->method('findAllStarConference')->willReturn('Western');
        $repo->method('upsertAllStarRoster')->willReturn(1);
        $repo->method('upsertAllStarScore')->willReturn(1);
        return $repo;
    }

    public function testStaleRostersRejectedWritesNothing(): void
    {
        $repo = self::createMock(JsbImportRepositoryInterface::class);
        $repo->expects(self::once())->method('findOtherSeasonWithIdenticalRosters')->willReturn(2007);
        $repo->expects(self::never())->method('upsertAllStarRoster');
        $repo->expects(self::never())->method('upsertAllStarScore');
        $repo->expects(self::never())->method('insertAwardUnlessFamilyExists');
        $repo->expects(self::never())->method('getPlayerName');

        $result = (new AswImporter($repo))->import($this->buildFixture(), 2009, true);

        $this->assertSame(0, $result->inserted);
        $this->assertSame(0, $result->errors);
        $this->assertStringContainsString('season 2007', implode("\n", $result->messages));
        $this->assertStringContainsString('nothing written for season 2009', implode("\n", $result->messages));
    }

    public function testStaleCheckSkippedWhenFlagOff(): void
    {
        $repo = $this->strictRepositoryWithWrites();
        $repo->expects(self::never())->method('findOtherSeasonWithIdenticalRosters');
        $repo->expects(self::atLeastOnce())->method('insertAwardUnlessFamilyExists')->willReturn(1);

        $result = (new AswImporter($repo))->import($this->buildFixture(), 2009);

        $this->assertSame(0, $result->errors);
        $this->assertGreaterThan(0, $result->inserted);
        $this->assertStringNotContainsString('stale', implode("\n", $result->messages));
    }

    public function testStaleCheckFailureWritesNothing(): void
    {
        $repo = self::createMock(JsbImportRepositoryInterface::class);
        $repo->expects(self::once())->method('findOtherSeasonWithIdenticalRosters')
            ->willThrowException(new \RuntimeException('db down'));
        $repo->expects(self::never())->method('upsertAllStarRoster');
        $repo->expects(self::never())->method('upsertAllStarScore');
        $repo->expects(self::never())->method('insertAwardUnlessFamilyExists');

        $result = (new AswImporter($repo))->import($this->buildFixture(), 2009, true);

        $this->assertSame(1, $result->errors);
        $this->assertSame(0, $result->inserted);
        $this->assertStringContainsString('ASW stale check failed; nothing written: db down', implode("\n", $result->messages));
    }

    public function testAcceptedImportWritesParticipantAwards(): void
    {
        /** @var list<array{int, string, string, string}> $calls */
        $calls = [];
        $repo = $this->strictRepositoryWithWrites();
        $repo->expects(self::once())->method('findOtherSeasonWithIdenticalRosters')->willReturn(null);
        $repo->expects(self::exactly(6))->method('insertAwardUnlessFamilyExists')->willReturnCallback(
            static function (int $year, string $award, string $name, string $sibling) use (&$calls): int {
                $calls[] = [$year, $award, $name, $sibling];
                return 1;
            }
        );

        $result = (new AswImporter($repo))->import($this->buildAwardFixture(), 2009, true);

        $this->assertSame(0, $result->errors);
        $this->assertSame([
            [2009, 'Western Conference All-Star', 'P101', 'Eastern Conference All-Star'],
            [2009, 'Western Conference All-Star', 'P102', 'Eastern Conference All-Star'],
            [2009, 'Rookie-Sophomore Challenge', 'P201', 'Rookie-Sophomore Challenge - MVP'],
            [2009, 'Rookie-Sophomore Challenge', 'P202', 'Rookie-Sophomore Challenge - MVP'],
            [2009, 'Three-Point Contest', 'P301', 'Three-Point Contest - Winner'],
            [2009, 'Slam Dunk Competition', 'P401', 'Slam Dunk Competition - Winner'],
        ], $calls);
        foreach ($calls as [, $award]) {
            $this->assertStringEndsNotWith('- Winner', $award);
            $this->assertStringEndsNotWith('- MVP', $award);
        }
    }

    public function testAllStarWithoutConferenceIsSkippedAndReported(): void
    {
        $repo = self::createMock(JsbImportRepositoryInterface::class);
        $repo->method('getPlayerName')->willReturnCallback(static fn (int $pid): string => 'P' . $pid);
        $repo->method('findAllStarConference')->willReturn(null);
        $repo->method('upsertAllStarRoster')->willReturn(1);
        $repo->method('upsertAllStarScore')->willReturn(1);
        /** @var list<string> $awards */
        $awards = [];
        $repo->expects(self::exactly(4))->method('insertAwardUnlessFamilyExists')->willReturnCallback(
            static function (int $year, string $award) use (&$awards): int {
                $awards[] = $award;
                return 1;
            }
        );

        $result = (new AswImporter($repo))->import($this->buildAwardFixture(), 2009);

        $this->assertSame(0, $result->errors);
        $this->assertSame([
            'Rookie-Sophomore Challenge',
            'Rookie-Sophomore Challenge',
            'Three-Point Contest',
            'Slam Dunk Competition',
        ], $awards);
        $messages = implode("\n", $result->messages);
        $this->assertStringContainsString('no conference for P101 (pid 101) in 2009', $messages);
        $this->assertStringContainsString('no conference for P102 (pid 102) in 2009', $messages);
    }

    public function testAwardSkippedWhenPlayerNameUnknown(): void
    {
        $repo = self::createMock(JsbImportRepositoryInterface::class);
        $repo->method('getPlayerName')->willReturn(null);
        $repo->method('upsertAllStarRoster')->willReturn(1);
        $repo->method('upsertAllStarScore')->willReturn(1);
        $repo->expects(self::never())->method('findAllStarConference');
        $repo->expects(self::never())->method('insertAwardUnlessFamilyExists');

        $result = (new AswImporter($repo))->import($this->buildAwardFixture(), 2009);

        $this->assertSame(0, $result->errors);
        $this->assertStringContainsString('ASW awards: 6 roster slots skipped (unknown player name)', implode("\n", $result->messages));
    }

    public function testImportFileReportsMissingFile(): void
    {
        $result = (new AswImporter(self::createStub(JsbImportRepositoryInterface::class)))->importFile('/nonexistent/x.asw', 2024);

        $this->assertSame(1, $result->errors);
        $this->assertStringContainsString('ASW file not found: /nonexistent/x.asw', implode("\n", $result->messages));
    }
}
