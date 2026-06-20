<?php

declare(strict_types=1);

namespace Tests\ProjectedDraftOrder;

use PHPUnit\Framework\TestCase;
use ProjectedDraftOrder\Contracts\ProjectedDraftOrderRepositoryInterface;
use ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface;
use ProjectedDraftOrder\ProjectedDraftOrderService;
use Tests\ProjectedDraftOrder\Support\StandingsFixtures;

/**
 * Golden-master characterization pin for calculateDraftOrder().
 *
 * CAPTURE PROCEDURE: each assertion uses a frozen expected literal captured by
 * running this test once against the unmodified service and copying the actual
 * ordering from the failure diff. Do not hand-update these — regenerate via the
 * same capture procedure if behavior is intentionally changed.
 *
 * @covers \ProjectedDraftOrder\ProjectedDraftOrderService
 * @phpstan-import-type DraftSlot from ProjectedDraftOrderServiceInterface
 */
class ProjectedDraftOrderServiceCharacterizationTest extends TestCase
{
    use StandingsFixtures;

    private object $stubRepository;
    private ProjectedDraftOrderService $service;

    protected function setUp(): void
    {
        $this->stubRepository = self::createStub(ProjectedDraftOrderRepositoryInterface::class);
        $this->stubRepository->method('getPickOwnership')->willReturn([]);
        $this->stubRepository->method('getPointDifferentials')->willReturn([]);
        $this->service = new ProjectedDraftOrderService($this->stubRepository);
    }

    /**
     * @param list<DraftSlot> $slots
     * @return list<array{pick: int, teamId: int}>
     */
    private function pickSequence(array $slots): array
    {
        return array_map(static fn (array $s): array => ['pick' => $s['pick'], 'teamId' => $s['teamId']], $slots);
    }

    // ── Row 5: empty standings early-return ───────────────────────────────
    public function testEmptyStandingsReturnsEmptyRounds(): void
    {
        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn([]);

        $result = $this->service->calculateDraftOrder(2026);

        $this->assertSame(['round1' => [], 'round2' => []], $result);
    }

    // ── Row 7: one full DraftSlot snapshot locks slot shape ───────────────
    public function testFullLeagueRound1SlotShape(): void
    {
        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($this->buildFullLeagueStandings());

        $result = $this->service->calculateDraftOrder(2026);
        $firstSlot = $result['round1'][0];

        $this->assertArrayHasKey('pick', $firstSlot);
        $this->assertArrayHasKey('teamId', $firstSlot);
        $this->assertArrayHasKey('teamName', $firstSlot);
        $this->assertArrayHasKey('wins', $firstSlot);
        $this->assertArrayHasKey('losses', $firstSlot);
        $this->assertArrayHasKey('color1', $firstSlot);
        $this->assertArrayHasKey('color2', $firstSlot);
        $this->assertArrayHasKey('ownerId', $firstSlot);
        $this->assertArrayHasKey('ownerName', $firstSlot);
        $this->assertArrayHasKey('ownerColor1', $firstSlot);
        $this->assertArrayHasKey('ownerColor2', $firstSlot);
        $this->assertArrayHasKey('isTraded', $firstSlot);
        $this->assertArrayHasKey('notes', $firstSlot);
        $this->assertArrayHasKey('movement', $firstSlot);
        $this->assertArrayHasKey('player', $firstSlot);
        $this->assertCount(28, $result['round1']);
    }

    // ── Row 1: full-league pick sequence (round1 + round2) ───────────────
    public function testFullLeaguePickSequence(): void
    {
        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($this->buildFullLeagueStandings());

        $result = $this->service->calculateDraftOrder(2026);

        $expectedRound1 = [
            ['pick' => 1, 'teamId' => 28], ['pick' => 2, 'teamId' => 27], ['pick' => 3, 'teamId' => 26],
            ['pick' => 4, 'teamId' => 25], ['pick' => 5, 'teamId' => 24], ['pick' => 6, 'teamId' => 23],
            ['pick' => 7, 'teamId' => 14], ['pick' => 8, 'teamId' => 13], ['pick' => 9, 'teamId' => 12],
            ['pick' => 10, 'teamId' => 11], ['pick' => 11, 'teamId' => 10], ['pick' => 12, 'teamId' => 9],
            ['pick' => 13, 'teamId' => 21], ['pick' => 14, 'teamId' => 20], ['pick' => 15, 'teamId' => 19],
            ['pick' => 16, 'teamId' => 18], ['pick' => 17, 'teamId' => 17], ['pick' => 18, 'teamId' => 16],
            ['pick' => 19, 'teamId' => 7], ['pick' => 20, 'teamId' => 6], ['pick' => 21, 'teamId' => 5],
            ['pick' => 22, 'teamId' => 4], ['pick' => 23, 'teamId' => 3], ['pick' => 24, 'teamId' => 2],
            ['pick' => 25, 'teamId' => 22], ['pick' => 26, 'teamId' => 8], ['pick' => 27, 'teamId' => 15],
            ['pick' => 28, 'teamId' => 1],
        ];
        $expectedRound2 = [
            ['pick' => 1, 'teamId' => 28], ['pick' => 2, 'teamId' => 27], ['pick' => 3, 'teamId' => 26],
            ['pick' => 4, 'teamId' => 25], ['pick' => 5, 'teamId' => 24], ['pick' => 6, 'teamId' => 23],
            ['pick' => 7, 'teamId' => 22], ['pick' => 8, 'teamId' => 21], ['pick' => 9, 'teamId' => 20],
            ['pick' => 10, 'teamId' => 19], ['pick' => 11, 'teamId' => 18], ['pick' => 12, 'teamId' => 17],
            ['pick' => 13, 'teamId' => 16], ['pick' => 14, 'teamId' => 15], ['pick' => 15, 'teamId' => 14],
            ['pick' => 16, 'teamId' => 13], ['pick' => 17, 'teamId' => 12], ['pick' => 18, 'teamId' => 11],
            ['pick' => 19, 'teamId' => 10], ['pick' => 20, 'teamId' => 9], ['pick' => 21, 'teamId' => 8],
            ['pick' => 22, 'teamId' => 7], ['pick' => 23, 'teamId' => 6], ['pick' => 24, 'teamId' => 5],
            ['pick' => 25, 'teamId' => 4], ['pick' => 26, 'teamId' => 3], ['pick' => 27, 'teamId' => 2],
            ['pick' => 28, 'teamId' => 1],
        ];

        $this->assertSame($expectedRound1, $this->pickSequence($result['round1']));
        $this->assertSame($expectedRound2, $this->pickSequence($result['round2']));
    }

    // ── Row 1 (continued): weak-div-winner sequence ───────────────────────
    public function testWeakDivisionWinnerPickSequence(): void
    {
        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($this->buildStandingsWithWeakDivisionWinner());

        $result = $this->service->calculateDraftOrder(2026);

        $expectedRound1 = [
            ['pick' => 1, 'teamId' => 7], ['pick' => 2, 'teamId' => 6], ['pick' => 3, 'teamId' => 28],
            ['pick' => 4, 'teamId' => 21], ['pick' => 5, 'teamId' => 5], ['pick' => 6, 'teamId' => 4],
            ['pick' => 7, 'teamId' => 27], ['pick' => 8, 'teamId' => 20], ['pick' => 9, 'teamId' => 3],
            ['pick' => 10, 'teamId' => 2], ['pick' => 11, 'teamId' => 26], ['pick' => 12, 'teamId' => 19],
            ['pick' => 13, 'teamId' => 25], ['pick' => 14, 'teamId' => 18], ['pick' => 15, 'teamId' => 14],
            ['pick' => 16, 'teamId' => 24], ['pick' => 17, 'teamId' => 17], ['pick' => 18, 'teamId' => 13],
            ['pick' => 19, 'teamId' => 23], ['pick' => 20, 'teamId' => 16], ['pick' => 21, 'teamId' => 12],
            ['pick' => 22, 'teamId' => 11], ['pick' => 23, 'teamId' => 10], ['pick' => 24, 'teamId' => 9],
            ['pick' => 25, 'teamId' => 1], ['pick' => 26, 'teamId' => 22], ['pick' => 27, 'teamId' => 15],
            ['pick' => 28, 'teamId' => 8],
        ];
        $expectedRound2 = [
            ['pick' => 1, 'teamId' => 7], ['pick' => 2, 'teamId' => 6], ['pick' => 3, 'teamId' => 28],
            ['pick' => 4, 'teamId' => 21], ['pick' => 5, 'teamId' => 5], ['pick' => 6, 'teamId' => 4],
            ['pick' => 7, 'teamId' => 27], ['pick' => 8, 'teamId' => 20], ['pick' => 9, 'teamId' => 3],
            ['pick' => 10, 'teamId' => 2], ['pick' => 11, 'teamId' => 26], ['pick' => 12, 'teamId' => 19],
            ['pick' => 13, 'teamId' => 1], ['pick' => 14, 'teamId' => 25], ['pick' => 15, 'teamId' => 18],
            ['pick' => 16, 'teamId' => 14], ['pick' => 17, 'teamId' => 24], ['pick' => 18, 'teamId' => 17],
            ['pick' => 19, 'teamId' => 13], ['pick' => 20, 'teamId' => 23], ['pick' => 21, 'teamId' => 16],
            ['pick' => 22, 'teamId' => 12], ['pick' => 23, 'teamId' => 22], ['pick' => 24, 'teamId' => 15],
            ['pick' => 25, 'teamId' => 11], ['pick' => 26, 'teamId' => 10], ['pick' => 27, 'teamId' => 9],
            ['pick' => 28, 'teamId' => 8],
        ];

        $this->assertSame($expectedRound1, $this->pickSequence($result['round1']));
        $this->assertSame($expectedRound2, $this->pickSequence($result['round2']));
    }

    // ── Row 1 (continued): tied H2H sequence ─────────────────────────────
    public function testTiedStandingsPickSequence(): void
    {
        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($this->buildTiedStandings());

        $result = $this->service->calculateDraftOrder(2026);

        $expectedRound1 = [
            ['pick' => 1, 'teamId' => 26], ['pick' => 2, 'teamId' => 19], ['pick' => 3, 'teamId' => 102],
            ['pick' => 4, 'teamId' => 101], ['pick' => 5, 'teamId' => 25], ['pick' => 6, 'teamId' => 18],
            ['pick' => 7, 'teamId' => 24], ['pick' => 8, 'teamId' => 17], ['pick' => 9, 'teamId' => 12],
            ['pick' => 10, 'teamId' => 11], ['pick' => 11, 'teamId' => 10], ['pick' => 12, 'teamId' => 9],
            ['pick' => 13, 'teamId' => 23], ['pick' => 14, 'teamId' => 16], ['pick' => 15, 'teamId' => 22],
            ['pick' => 16, 'teamId' => 15], ['pick' => 17, 'teamId' => 21], ['pick' => 18, 'teamId' => 14],
            ['pick' => 19, 'teamId' => 5], ['pick' => 20, 'teamId' => 8], ['pick' => 21, 'teamId' => 4],
            ['pick' => 22, 'teamId' => 7], ['pick' => 23, 'teamId' => 3], ['pick' => 24, 'teamId' => 2],
            ['pick' => 25, 'teamId' => 20], ['pick' => 26, 'teamId' => 6], ['pick' => 27, 'teamId' => 13],
            ['pick' => 28, 'teamId' => 1],
        ];
        $expectedRound2 = [
            ['pick' => 1, 'teamId' => 26], ['pick' => 2, 'teamId' => 19], ['pick' => 3, 'teamId' => 102],
            ['pick' => 4, 'teamId' => 101], ['pick' => 5, 'teamId' => 25], ['pick' => 6, 'teamId' => 18],
            ['pick' => 7, 'teamId' => 24], ['pick' => 8, 'teamId' => 17], ['pick' => 9, 'teamId' => 23],
            ['pick' => 10, 'teamId' => 16], ['pick' => 11, 'teamId' => 12], ['pick' => 12, 'teamId' => 22],
            ['pick' => 13, 'teamId' => 15], ['pick' => 14, 'teamId' => 11], ['pick' => 15, 'teamId' => 10],
            ['pick' => 16, 'teamId' => 21], ['pick' => 17, 'teamId' => 14], ['pick' => 18, 'teamId' => 9],
            ['pick' => 19, 'teamId' => 5], ['pick' => 20, 'teamId' => 8], ['pick' => 21, 'teamId' => 20],
            ['pick' => 22, 'teamId' => 13], ['pick' => 23, 'teamId' => 4], ['pick' => 24, 'teamId' => 7],
            ['pick' => 25, 'teamId' => 3], ['pick' => 26, 'teamId' => 6], ['pick' => 27, 'teamId' => 2],
            ['pick' => 28, 'teamId' => 1],
        ];

        $this->assertSame($expectedRound1, $this->pickSequence($result['round1']));
        $this->assertSame($expectedRound2, $this->pickSequence($result['round2']));
    }

    // ── Row 1 (continued): conference-record sequence ─────────────────────
    public function testConfRecordPickSequence(): void
    {
        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($this->buildTiedStandingsWithConfRecords());

        $result = $this->service->calculateDraftOrder(2026);

        // TeamA (101) has worse conf record (10-30 = 0.25) → better draft pick (earlier)
        // TeamB (102) has better conf record (20-20 = 0.5) → worse draft pick (later)
        $expectedRound1 = [
            ['pick' => 1, 'teamId' => 26], ['pick' => 2, 'teamId' => 19], ['pick' => 3, 'teamId' => 25],
            ['pick' => 4, 'teamId' => 18], ['pick' => 5, 'teamId' => 101], ['pick' => 6, 'teamId' => 102],
            ['pick' => 7, 'teamId' => 24], ['pick' => 8, 'teamId' => 17], ['pick' => 9, 'teamId' => 12],
            ['pick' => 10, 'teamId' => 11], ['pick' => 11, 'teamId' => 10], ['pick' => 12, 'teamId' => 9],
            ['pick' => 13, 'teamId' => 23], ['pick' => 14, 'teamId' => 16], ['pick' => 15, 'teamId' => 22],
            ['pick' => 16, 'teamId' => 15], ['pick' => 17, 'teamId' => 21], ['pick' => 18, 'teamId' => 14],
            ['pick' => 19, 'teamId' => 5], ['pick' => 20, 'teamId' => 8], ['pick' => 21, 'teamId' => 4],
            ['pick' => 22, 'teamId' => 7], ['pick' => 23, 'teamId' => 3], ['pick' => 24, 'teamId' => 2],
            ['pick' => 25, 'teamId' => 20], ['pick' => 26, 'teamId' => 6], ['pick' => 27, 'teamId' => 13],
            ['pick' => 28, 'teamId' => 1],
        ];
        $expectedRound2 = [
            ['pick' => 1, 'teamId' => 26], ['pick' => 2, 'teamId' => 19], ['pick' => 3, 'teamId' => 25],
            ['pick' => 4, 'teamId' => 18], ['pick' => 5, 'teamId' => 101], ['pick' => 6, 'teamId' => 102],
            ['pick' => 7, 'teamId' => 24], ['pick' => 8, 'teamId' => 17], ['pick' => 9, 'teamId' => 23],
            ['pick' => 10, 'teamId' => 16], ['pick' => 11, 'teamId' => 12], ['pick' => 12, 'teamId' => 22],
            ['pick' => 13, 'teamId' => 15], ['pick' => 14, 'teamId' => 11], ['pick' => 15, 'teamId' => 10],
            ['pick' => 16, 'teamId' => 21], ['pick' => 17, 'teamId' => 14], ['pick' => 18, 'teamId' => 9],
            ['pick' => 19, 'teamId' => 5], ['pick' => 20, 'teamId' => 8], ['pick' => 21, 'teamId' => 20],
            ['pick' => 22, 'teamId' => 13], ['pick' => 23, 'teamId' => 4], ['pick' => 24, 'teamId' => 7],
            ['pick' => 25, 'teamId' => 3], ['pick' => 26, 'teamId' => 6], ['pick' => 27, 'teamId' => 2],
            ['pick' => 28, 'teamId' => 1],
        ];

        $this->assertSame($expectedRound1, $this->pickSequence($result['round1']));
        $this->assertSame($expectedRound2, $this->pickSequence($result['round2']));
    }

    // ── Row 1 (continued): point-differential sequence ───────────────────
    public function testPointDiffPickSequence(): void
    {
        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($this->buildTiedStandingsWithSameConfRecords());
        $this->stubRepository->method('getPointDifferentials')->willReturn([
            ['teamid' => 101, 'pointsFor' => 100.0, 'pointsAgainst' => 90.0],
            ['teamid' => 102, 'pointsFor' => 90.0, 'pointsAgainst' => 100.0],
        ]);

        $result = $this->service->calculateDraftOrder(2026);

        // TeamA (101) has +10 net pts → better team → later draft pick
        // TeamB (102) has -10 net pts → worse team → earlier draft pick
        $expectedRound1 = [
            ['pick' => 1, 'teamId' => 26], ['pick' => 2, 'teamId' => 19], ['pick' => 3, 'teamId' => 25],
            ['pick' => 4, 'teamId' => 18], ['pick' => 5, 'teamId' => 102], ['pick' => 6, 'teamId' => 101],
            ['pick' => 7, 'teamId' => 24], ['pick' => 8, 'teamId' => 17], ['pick' => 9, 'teamId' => 12],
            ['pick' => 10, 'teamId' => 11], ['pick' => 11, 'teamId' => 10], ['pick' => 12, 'teamId' => 9],
            ['pick' => 13, 'teamId' => 23], ['pick' => 14, 'teamId' => 16], ['pick' => 15, 'teamId' => 22],
            ['pick' => 16, 'teamId' => 15], ['pick' => 17, 'teamId' => 21], ['pick' => 18, 'teamId' => 14],
            ['pick' => 19, 'teamId' => 5], ['pick' => 20, 'teamId' => 8], ['pick' => 21, 'teamId' => 4],
            ['pick' => 22, 'teamId' => 7], ['pick' => 23, 'teamId' => 3], ['pick' => 24, 'teamId' => 2],
            ['pick' => 25, 'teamId' => 20], ['pick' => 26, 'teamId' => 6], ['pick' => 27, 'teamId' => 13],
            ['pick' => 28, 'teamId' => 1],
        ];
        $expectedRound2 = [
            ['pick' => 1, 'teamId' => 26], ['pick' => 2, 'teamId' => 19], ['pick' => 3, 'teamId' => 25],
            ['pick' => 4, 'teamId' => 18], ['pick' => 5, 'teamId' => 102], ['pick' => 6, 'teamId' => 101],
            ['pick' => 7, 'teamId' => 24], ['pick' => 8, 'teamId' => 17], ['pick' => 9, 'teamId' => 23],
            ['pick' => 10, 'teamId' => 16], ['pick' => 11, 'teamId' => 12], ['pick' => 12, 'teamId' => 22],
            ['pick' => 13, 'teamId' => 15], ['pick' => 14, 'teamId' => 11], ['pick' => 15, 'teamId' => 10],
            ['pick' => 16, 'teamId' => 21], ['pick' => 17, 'teamId' => 14], ['pick' => 18, 'teamId' => 9],
            ['pick' => 19, 'teamId' => 5], ['pick' => 20, 'teamId' => 8], ['pick' => 21, 'teamId' => 20],
            ['pick' => 22, 'teamId' => 13], ['pick' => 23, 'teamId' => 4], ['pick' => 24, 'teamId' => 7],
            ['pick' => 25, 'teamId' => 3], ['pick' => 26, 'teamId' => 6], ['pick' => 27, 'teamId' => 2],
            ['pick' => 28, 'teamId' => 1],
        ];

        $this->assertSame($expectedRound1, $this->pickSequence($result['round1']));
        $this->assertSame($expectedRound2, $this->pickSequence($result['round2']));
    }

    // ── Row 1 (continued): 3-way aggregate H2H sequence ──────────────────
    public function testThreeWayTiedPickSequence(): void
    {
        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($this->buildThreeWayTiedStandings());
        // 301 beat 302 once; 302 beat 303 once; 303 beat 301 once (cyclic)
        $this->stubRepository->method('getPlayedGames')->willReturn([
            ['visitor_teamid' => 301, 'visitor_score' => 110, 'home_teamid' => 302, 'home_score' => 100],
            ['visitor_teamid' => 302, 'visitor_score' => 110, 'home_teamid' => 303, 'home_score' => 100],
            ['visitor_teamid' => 303, 'visitor_score' => 110, 'home_teamid' => 301, 'home_score' => 100],
        ]);

        $result = $this->service->calculateDraftOrder(2026);

        $expectedRound1 = [
            ['pick' => 1, 'teamId' => 25], ['pick' => 2, 'teamId' => 18], ['pick' => 3, 'teamId' => 24],
            ['pick' => 4, 'teamId' => 17], ['pick' => 5, 'teamId' => 303], ['pick' => 6, 'teamId' => 302],
            ['pick' => 7, 'teamId' => 301], ['pick' => 8, 'teamId' => 23], ['pick' => 9, 'teamId' => 16],
            ['pick' => 10, 'teamId' => 11], ['pick' => 11, 'teamId' => 10], ['pick' => 12, 'teamId' => 9],
            ['pick' => 13, 'teamId' => 22], ['pick' => 14, 'teamId' => 15], ['pick' => 15, 'teamId' => 21],
            ['pick' => 16, 'teamId' => 14], ['pick' => 17, 'teamId' => 20], ['pick' => 18, 'teamId' => 13],
            ['pick' => 19, 'teamId' => 8], ['pick' => 20, 'teamId' => 7], ['pick' => 21, 'teamId' => 4],
            ['pick' => 22, 'teamId' => 6], ['pick' => 23, 'teamId' => 3], ['pick' => 24, 'teamId' => 2],
            ['pick' => 25, 'teamId' => 19], ['pick' => 26, 'teamId' => 5], ['pick' => 27, 'teamId' => 12],
            ['pick' => 28, 'teamId' => 1],
        ];
        $expectedRound2 = [
            ['pick' => 1, 'teamId' => 25], ['pick' => 2, 'teamId' => 18], ['pick' => 3, 'teamId' => 24],
            ['pick' => 4, 'teamId' => 17], ['pick' => 5, 'teamId' => 303], ['pick' => 6, 'teamId' => 302],
            ['pick' => 7, 'teamId' => 301], ['pick' => 8, 'teamId' => 23], ['pick' => 9, 'teamId' => 16],
            ['pick' => 10, 'teamId' => 22], ['pick' => 11, 'teamId' => 15], ['pick' => 12, 'teamId' => 11],
            ['pick' => 13, 'teamId' => 21], ['pick' => 14, 'teamId' => 14], ['pick' => 15, 'teamId' => 10],
            ['pick' => 16, 'teamId' => 9], ['pick' => 17, 'teamId' => 20], ['pick' => 18, 'teamId' => 13],
            ['pick' => 19, 'teamId' => 8], ['pick' => 20, 'teamId' => 7], ['pick' => 21, 'teamId' => 19],
            ['pick' => 22, 'teamId' => 12], ['pick' => 23, 'teamId' => 4], ['pick' => 24, 'teamId' => 6],
            ['pick' => 25, 'teamId' => 3], ['pick' => 26, 'teamId' => 5], ['pick' => 27, 'teamId' => 2],
            ['pick' => 28, 'teamId' => 1],
        ];

        $this->assertSame($expectedRound1, $this->pickSequence($result['round1']));
        $this->assertSame($expectedRound2, $this->pickSequence($result['round2']));
    }

    // ── Row 1 (continued): tied playoff teams sequence ────────────────────
    public function testTiedPlayoffTeamsPickSequence(): void
    {
        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($this->buildTiedPlayoffTeams());
        // PlayoffA (201) beat PlayoffB (202) — pairwise H2H in playoff seeding
        $this->stubRepository->method('getPlayedGames')->willReturn([
            ['visitor_teamid' => 201, 'visitor_score' => 110, 'home_teamid' => 202, 'home_score' => 100],
        ]);

        $result = $this->service->calculateDraftOrder(2026);

        $expectedRound1 = [
            ['pick' => 1, 'teamId' => 26], ['pick' => 2, 'teamId' => 19], ['pick' => 3, 'teamId' => 25],
            ['pick' => 4, 'teamId' => 18], ['pick' => 5, 'teamId' => 5], ['pick' => 6, 'teamId' => 24],
            ['pick' => 7, 'teamId' => 17], ['pick' => 8, 'teamId' => 4], ['pick' => 9, 'teamId' => 12],
            ['pick' => 10, 'teamId' => 11], ['pick' => 11, 'teamId' => 3], ['pick' => 12, 'teamId' => 10],
            ['pick' => 13, 'teamId' => 23], ['pick' => 14, 'teamId' => 16], ['pick' => 15, 'teamId' => 22],
            ['pick' => 16, 'teamId' => 15], ['pick' => 17, 'teamId' => 2], ['pick' => 18, 'teamId' => 9],
            ['pick' => 19, 'teamId' => 21], ['pick' => 20, 'teamId' => 14], ['pick' => 21, 'teamId' => 1],
            ['pick' => 22, 'teamId' => 8], ['pick' => 23, 'teamId' => 202], ['pick' => 24, 'teamId' => 7],
            ['pick' => 25, 'teamId' => 201], ['pick' => 26, 'teamId' => 20], ['pick' => 27, 'teamId' => 13],
            ['pick' => 28, 'teamId' => 6],
        ];
        $expectedRound2 = [
            ['pick' => 1, 'teamId' => 26], ['pick' => 2, 'teamId' => 19], ['pick' => 3, 'teamId' => 25],
            ['pick' => 4, 'teamId' => 18], ['pick' => 5, 'teamId' => 5], ['pick' => 6, 'teamId' => 24],
            ['pick' => 7, 'teamId' => 17], ['pick' => 8, 'teamId' => 4], ['pick' => 9, 'teamId' => 12],
            ['pick' => 10, 'teamId' => 23], ['pick' => 11, 'teamId' => 16], ['pick' => 12, 'teamId' => 11],
            ['pick' => 13, 'teamId' => 3], ['pick' => 14, 'teamId' => 10], ['pick' => 15, 'teamId' => 22],
            ['pick' => 16, 'teamId' => 15], ['pick' => 17, 'teamId' => 2], ['pick' => 18, 'teamId' => 9],
            ['pick' => 19, 'teamId' => 21], ['pick' => 20, 'teamId' => 14], ['pick' => 21, 'teamId' => 1],
            ['pick' => 22, 'teamId' => 8], ['pick' => 23, 'teamId' => 202], ['pick' => 24, 'teamId' => 20],
            ['pick' => 25, 'teamId' => 13], ['pick' => 26, 'teamId' => 201], ['pick' => 27, 'teamId' => 7],
            ['pick' => 28, 'teamId' => 6],
        ];

        $this->assertSame($expectedRound1, $this->pickSequence($result['round1']));
        $this->assertSame($expectedRound2, $this->pickSequence($result['round2']));
    }

    // ── Row 6: zero-games yields 28+28 picks numbered 1-28 ───────────────
    public function testZeroGameStandings28Picks(): void
    {
        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($this->buildZeroGameStandings());

        $result = $this->service->calculateDraftOrder(2026);

        $this->assertCount(28, $result['round1']);
        $this->assertCount(28, $result['round2']);
        $this->assertSame(range(1, 28), array_column($result['round1'], 'pick'));
        $this->assertSame(range(1, 28), array_column($result['round2'], 'pick'));
    }

    // ── Row 2: div-winner-status branch decides via round-2 sequence ──────
    public function testDivisionWinnerStatusPickSequence(): void
    {
        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($this->buildTiedWithDivisionWinnerStatus());

        $result = $this->service->calculateDraftOrder(2026);

        // Round-2 picks 13-14: TeamB (202, non-div-winner) picks before TeamA (201, div-winner)
        // because div-winner-status means "better" team → later draft pick
        $expectedRound1 = [
            ['pick' => 1, 'teamId' => 6], ['pick' => 2, 'teamId' => 5], ['pick' => 3, 'teamId' => 26],
            ['pick' => 4, 'teamId' => 4], ['pick' => 5, 'teamId' => 25], ['pick' => 6, 'teamId' => 3],
            ['pick' => 7, 'teamId' => 24], ['pick' => 8, 'teamId' => 2], ['pick' => 9, 'teamId' => 1],
            ['pick' => 10, 'teamId' => 23], ['pick' => 11, 'teamId' => 22], ['pick' => 12, 'teamId' => 21],
            ['pick' => 13, 'teamId' => 202], ['pick' => 14, 'teamId' => 13], ['pick' => 15, 'teamId' => 19],
            ['pick' => 16, 'teamId' => 12], ['pick' => 17, 'teamId' => 18], ['pick' => 18, 'teamId' => 11],
            ['pick' => 19, 'teamId' => 17], ['pick' => 20, 'teamId' => 10], ['pick' => 21, 'teamId' => 16],
            ['pick' => 22, 'teamId' => 9], ['pick' => 23, 'teamId' => 15], ['pick' => 24, 'teamId' => 8],
            ['pick' => 25, 'teamId' => 201], ['pick' => 26, 'teamId' => 20], ['pick' => 27, 'teamId' => 14],
            ['pick' => 28, 'teamId' => 7],
        ];
        $expectedRound2 = [
            ['pick' => 1, 'teamId' => 6], ['pick' => 2, 'teamId' => 5], ['pick' => 3, 'teamId' => 26],
            ['pick' => 4, 'teamId' => 4], ['pick' => 5, 'teamId' => 25], ['pick' => 6, 'teamId' => 3],
            ['pick' => 7, 'teamId' => 24], ['pick' => 8, 'teamId' => 2], ['pick' => 9, 'teamId' => 1],
            ['pick' => 10, 'teamId' => 23], ['pick' => 11, 'teamId' => 22], ['pick' => 12, 'teamId' => 21],
            ['pick' => 13, 'teamId' => 202], ['pick' => 14, 'teamId' => 201], ['pick' => 15, 'teamId' => 13],
            ['pick' => 16, 'teamId' => 19], ['pick' => 17, 'teamId' => 12], ['pick' => 18, 'teamId' => 18],
            ['pick' => 19, 'teamId' => 11], ['pick' => 20, 'teamId' => 17], ['pick' => 21, 'teamId' => 10],
            ['pick' => 22, 'teamId' => 16], ['pick' => 23, 'teamId' => 9], ['pick' => 24, 'teamId' => 15],
            ['pick' => 25, 'teamId' => 20], ['pick' => 26, 'teamId' => 8], ['pick' => 27, 'teamId' => 14],
            ['pick' => 28, 'teamId' => 7],
        ];

        $this->assertSame($expectedRound1, $this->pickSequence($result['round1']));
        $this->assertSame($expectedRound2, $this->pickSequence($result['round2']));
    }

    // ── Row 3: division-record branch decides ordering ────────────────────
    public function testDivisionRecordPickSequence(): void
    {
        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($this->buildTiedWithDivisionRecords());

        $result = $this->service->calculateDraftOrder(2026);

        // TeamB (402, div 2-8 = worse div record) picks before TeamA (401, div 8-2 = better)
        $expectedRound1 = [
            ['pick' => 1, 'teamId' => 26], ['pick' => 2, 'teamId' => 19], ['pick' => 3, 'teamId' => 402],
            ['pick' => 4, 'teamId' => 401], ['pick' => 5, 'teamId' => 25], ['pick' => 6, 'teamId' => 18],
            ['pick' => 7, 'teamId' => 24], ['pick' => 8, 'teamId' => 17], ['pick' => 9, 'teamId' => 12],
            ['pick' => 10, 'teamId' => 11], ['pick' => 11, 'teamId' => 10], ['pick' => 12, 'teamId' => 9],
            ['pick' => 13, 'teamId' => 23], ['pick' => 14, 'teamId' => 16], ['pick' => 15, 'teamId' => 22],
            ['pick' => 16, 'teamId' => 15], ['pick' => 17, 'teamId' => 21], ['pick' => 18, 'teamId' => 14],
            ['pick' => 19, 'teamId' => 5], ['pick' => 20, 'teamId' => 8], ['pick' => 21, 'teamId' => 4],
            ['pick' => 22, 'teamId' => 7], ['pick' => 23, 'teamId' => 3], ['pick' => 24, 'teamId' => 2],
            ['pick' => 25, 'teamId' => 20], ['pick' => 26, 'teamId' => 6], ['pick' => 27, 'teamId' => 13],
            ['pick' => 28, 'teamId' => 1],
        ];
        $expectedRound2 = [
            ['pick' => 1, 'teamId' => 26], ['pick' => 2, 'teamId' => 19], ['pick' => 3, 'teamId' => 402],
            ['pick' => 4, 'teamId' => 401], ['pick' => 5, 'teamId' => 25], ['pick' => 6, 'teamId' => 18],
            ['pick' => 7, 'teamId' => 24], ['pick' => 8, 'teamId' => 17], ['pick' => 9, 'teamId' => 23],
            ['pick' => 10, 'teamId' => 16], ['pick' => 11, 'teamId' => 12], ['pick' => 12, 'teamId' => 22],
            ['pick' => 13, 'teamId' => 15], ['pick' => 14, 'teamId' => 11], ['pick' => 15, 'teamId' => 10],
            ['pick' => 16, 'teamId' => 21], ['pick' => 17, 'teamId' => 14], ['pick' => 18, 'teamId' => 9],
            ['pick' => 19, 'teamId' => 5], ['pick' => 20, 'teamId' => 8], ['pick' => 21, 'teamId' => 20],
            ['pick' => 22, 'teamId' => 13], ['pick' => 23, 'teamId' => 4], ['pick' => 24, 'teamId' => 7],
            ['pick' => 25, 'teamId' => 3], ['pick' => 26, 'teamId' => 6], ['pick' => 27, 'teamId' => 2],
            ['pick' => 28, 'teamId' => 1],
        ];

        $this->assertSame($expectedRound1, $this->pickSequence($result['round1']));
        $this->assertSame($expectedRound2, $this->pickSequence($result['round2']));
    }

    // ── Row 4: alphabetical terminal fallback ─────────────────────────────
    public function testAlphabeticalFallbackPickSequence(): void
    {
        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($this->buildFullyTiedPair());

        $result = $this->service->calculateDraftOrder(2026);

        // ZoneTeam (502) picks before ArcticTeam (501): 'Z' > 'A' alphabetically means
        // ZoneTeam is "worse" in the tiebreaker → earlier draft pick (sign-inverted in sortTiedGroup)
        $expectedRound1 = [
            ['pick' => 1, 'teamId' => 26], ['pick' => 2, 'teamId' => 19], ['pick' => 3, 'teamId' => 502],
            ['pick' => 4, 'teamId' => 25], ['pick' => 5, 'teamId' => 18], ['pick' => 6, 'teamId' => 501],
            ['pick' => 7, 'teamId' => 24], ['pick' => 8, 'teamId' => 17], ['pick' => 9, 'teamId' => 12],
            ['pick' => 10, 'teamId' => 11], ['pick' => 11, 'teamId' => 10], ['pick' => 12, 'teamId' => 9],
            ['pick' => 13, 'teamId' => 23], ['pick' => 14, 'teamId' => 16], ['pick' => 15, 'teamId' => 22],
            ['pick' => 16, 'teamId' => 15], ['pick' => 17, 'teamId' => 21], ['pick' => 18, 'teamId' => 14],
            ['pick' => 19, 'teamId' => 5], ['pick' => 20, 'teamId' => 8], ['pick' => 21, 'teamId' => 4],
            ['pick' => 22, 'teamId' => 7], ['pick' => 23, 'teamId' => 3], ['pick' => 24, 'teamId' => 2],
            ['pick' => 25, 'teamId' => 20], ['pick' => 26, 'teamId' => 6], ['pick' => 27, 'teamId' => 13],
            ['pick' => 28, 'teamId' => 1],
        ];
        $expectedRound2 = [
            ['pick' => 1, 'teamId' => 26], ['pick' => 2, 'teamId' => 19], ['pick' => 3, 'teamId' => 502],
            ['pick' => 4, 'teamId' => 25], ['pick' => 5, 'teamId' => 18], ['pick' => 6, 'teamId' => 501],
            ['pick' => 7, 'teamId' => 24], ['pick' => 8, 'teamId' => 17], ['pick' => 9, 'teamId' => 23],
            ['pick' => 10, 'teamId' => 16], ['pick' => 11, 'teamId' => 12], ['pick' => 12, 'teamId' => 22],
            ['pick' => 13, 'teamId' => 15], ['pick' => 14, 'teamId' => 11], ['pick' => 15, 'teamId' => 10],
            ['pick' => 16, 'teamId' => 21], ['pick' => 17, 'teamId' => 14], ['pick' => 18, 'teamId' => 9],
            ['pick' => 19, 'teamId' => 5], ['pick' => 20, 'teamId' => 8], ['pick' => 21, 'teamId' => 20],
            ['pick' => 22, 'teamId' => 13], ['pick' => 23, 'teamId' => 4], ['pick' => 24, 'teamId' => 7],
            ['pick' => 25, 'teamId' => 3], ['pick' => 26, 'teamId' => 6], ['pick' => 27, 'teamId' => 2],
            ['pick' => 28, 'teamId' => 1],
        ];

        $this->assertSame($expectedRound1, $this->pickSequence($result['round1']));
        $this->assertSame($expectedRound2, $this->pickSequence($result['round2']));
    }
}
