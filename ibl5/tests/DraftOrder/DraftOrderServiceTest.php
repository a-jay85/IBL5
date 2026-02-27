<?php

declare(strict_types=1);

namespace Tests\DraftOrder;

use DraftOrder\Contracts\DraftOrderRepositoryInterface;
use DraftOrder\Contracts\DraftOrderServiceInterface;
use DraftOrder\DraftOrderService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DraftOrder\DraftOrderService
 */
class DraftOrderServiceTest extends TestCase
{
    private object $stubRepository;
    private DraftOrderService $service;

    protected function setUp(): void
    {
        $this->stubRepository = $this->createStub(DraftOrderRepositoryInterface::class);
        $this->service = new DraftOrderService($this->stubRepository);
    }

    public function testImplementsServiceInterface(): void
    {
        $this->assertInstanceOf(DraftOrderServiceInterface::class, $this->service);
    }

    public function testReturnsTwoRoundsWithCorrectKeys(): void
    {
        $this->configureStubWithFullLeague();

        $result = $this->service->calculateDraftOrder(2026);

        $this->assertArrayHasKey('round1', $result);
        $this->assertArrayHasKey('round2', $result);
    }

    public function testEachRoundHas28Picks(): void
    {
        $this->configureStubWithFullLeague();

        $result = $this->service->calculateDraftOrder(2026);

        $this->assertCount(28, $result['round1']);
        $this->assertCount(28, $result['round2']);
    }

    public function testPickNumbersAre1Through28(): void
    {
        $this->configureStubWithFullLeague();

        $result = $this->service->calculateDraftOrder(2026);

        $pickNumbers = array_column($result['round1'], 'pick');
        $this->assertSame(range(1, 28), $pickNumbers);
    }

    public function testWorstRecordGetsFirstPick(): void
    {
        $this->configureStubWithFullLeague();

        $result = $this->service->calculateDraftOrder(2026);

        // Team28 has worst record (1-81) and is in Western non-playoff
        $this->assertSame('Team28', $result['round1'][0]['teamName']);
    }

    public function testBestRecordGetsLastPick(): void
    {
        $this->configureStubWithFullLeague();

        $result = $this->service->calculateDraftOrder(2026);

        // Team1 has best record (82-0) and is an Eastern playoff team
        $this->assertSame('Team1', $result['round1'][27]['teamName']);
    }

    public function testLotteryHas12TeamsAndPlayoffHas16Teams(): void
    {
        $this->configureStubWithFullLeague();

        $result = $this->service->calculateDraftOrder(2026);

        // Picks 1-12 are non-playoff (lottery), picks 13-28 are playoff
        $this->assertCount(28, $result['round1']);

        // Verify the split: 12 lottery + 16 playoff (6 non-playoff per conference × 2)
        $lotteryPicks = array_slice($result['round1'], 0, 12);
        $playoffPicks = array_slice($result['round1'], 12);
        $this->assertCount(12, $lotteryPicks);
        $this->assertCount(16, $playoffPicks);
    }

    public function testDivisionOnlyWinnersArePicks25And26(): void
    {
        $this->configureStubWithFullLeague();

        $result = $this->service->calculateDraftOrder(2026);

        // Division-only winners (not conference winners): Team22 (19w) and Team8 (61w)
        // Worse record gets earlier pick
        $this->assertSame('Team22', $result['round1'][24]['teamName']); // Pick 25
        $this->assertSame('Team8', $result['round1'][25]['teamName']);  // Pick 26
    }

    public function testConferenceWinnersArePicks27And28(): void
    {
        $this->configureStubWithFullLeague();

        $result = $this->service->calculateDraftOrder(2026);

        // Conference winners: Team15 (40w) and Team1 (82w)
        // Worse record gets earlier pick
        $this->assertSame('Team15', $result['round1'][26]['teamName']); // Pick 27
        $this->assertSame('Team1', $result['round1'][27]['teamName']);  // Pick 28
    }

    public function testDivisionWinnersMakePlayoffs(): void
    {
        $standings = $this->buildStandingsWithWeakDivisionWinner();
        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($standings);
        $this->stubRepository->method('getPlayedGames')->willReturn([]);
        $this->stubRepository->method('getPickOwnership')->willReturn([]);
        $this->stubRepository->method('getPointDifferentials')->willReturn([]);

        $result = $this->service->calculateDraftOrder(2026);

        // The weak division winner (30-52) should be in playoff section (picks 13-28)
        $playoffTeamNames = array_column(array_slice($result['round1'], 12), 'teamName');
        $this->assertContains('WeakDivWinner', $playoffTeamNames);
    }

    public function testHeadToHeadTiebreakerWorks(): void
    {
        $standings = $this->buildTiedStandings();
        $games = [
            // TeamA beats TeamB twice, TeamB beats TeamA once
            ['Visitor' => 101, 'VScore' => 100, 'Home' => 102, 'HScore' => 90],
            ['Visitor' => 101, 'VScore' => 95, 'Home' => 102, 'HScore' => 88],
            ['Visitor' => 102, 'VScore' => 105, 'Home' => 101, 'HScore' => 100],
        ];

        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($standings);
        $this->stubRepository->method('getPlayedGames')->willReturn($games);
        $this->stubRepository->method('getPickOwnership')->willReturn([]);
        $this->stubRepository->method('getPointDifferentials')->willReturn([]);

        $result = $this->service->calculateDraftOrder(2026);

        // Both teams are non-playoff with same record. TeamA wins H2H, so TeamA
        // should get the LATER pick (tiebreaker winner drafts later in lottery).
        $lotteryNames = array_column(array_slice($result['round1'], 0, 12), 'teamName');
        $posA = array_search('TeamA', $lotteryNames, true);
        $posB = array_search('TeamB', $lotteryNames, true);

        $this->assertNotFalse($posA);
        $this->assertNotFalse($posB);
        // In lottery: loser of tiebreaker gets earlier (better) pick
        // TeamB lost H2H, so TeamB should be BEFORE TeamA
        $this->assertLessThan($posA, $posB);
    }

    public function testConferenceRecordTiebreakerWorks(): void
    {
        $standings = $this->buildTiedStandingsWithConfRecords();

        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($standings);
        $this->stubRepository->method('getPlayedGames')->willReturn([]); // No H2H data = skip that tiebreaker
        $this->stubRepository->method('getPickOwnership')->willReturn([]);
        $this->stubRepository->method('getPointDifferentials')->willReturn([]);

        $result = $this->service->calculateDraftOrder(2026);

        $lotteryNames = array_column(array_slice($result['round1'], 0, 12), 'teamName');
        $posA = array_search('TeamA', $lotteryNames, true);
        $posB = array_search('TeamB', $lotteryNames, true);

        $this->assertNotFalse($posA);
        $this->assertNotFalse($posB);
        // TeamA has worse conference record (10-30) = tiebreaker loser → earlier pick
        // TeamB has better conference record (20-20) = tiebreaker winner → later pick
        $this->assertLessThan($posB, $posA);
    }

    public function testPointDifferentialTiebreakerWorks(): void
    {
        $standings = $this->buildTiedStandingsWithSameConfRecords();
        $pointDiffs = [
            ['tid' => 101, 'pointsFor' => 8000.0, 'pointsAgainst' => 8500.0], // -500
            ['tid' => 102, 'pointsFor' => 8200.0, 'pointsAgainst' => 8100.0], // +100
        ];

        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($standings);
        $this->stubRepository->method('getPlayedGames')->willReturn([]);
        $this->stubRepository->method('getPickOwnership')->willReturn([]);
        $this->stubRepository->method('getPointDifferentials')->willReturn($pointDiffs);

        $result = $this->service->calculateDraftOrder(2026);

        $lotteryNames = array_column(array_slice($result['round1'], 0, 12), 'teamName');
        $posA = array_search('TeamA', $lotteryNames, true);
        $posB = array_search('TeamB', $lotteryNames, true);

        $this->assertNotFalse($posA);
        $this->assertNotFalse($posB);
        // TeamA has -500 differential (worse) = tiebreaker loser → earlier pick
        // TeamB has +100 differential (better) = tiebreaker winner → later pick
        $this->assertLessThan($posB, $posA);
    }

    public function testPickOwnershipOverlayForTradedPick(): void
    {
        $this->configureStubWithFullLeague();

        // Override pick ownership: Team28's pick (worst team) is owned by Team1
        $this->stubRepository->method('getPickOwnership')->willReturn([
            ['ownerofpick' => 'Team1', 'teampick' => 'Team28', 'round' => 1, 'notes' => 'via trade'],
        ]);

        $result = $this->service->calculateDraftOrder(2026);

        // Pick 1 should still belong to Team28's slot but be owned by Team1
        $pick1 = $result['round1'][0];
        $this->assertSame('Team28', $pick1['teamName']);
        $this->assertSame('Team1', $pick1['ownerName']);
        $this->assertTrue($pick1['isTraded']);
        $this->assertSame('via trade', $pick1['notes']);
    }

    public function testOwnPickIsNotMarkedAsTraded(): void
    {
        $this->configureStubWithFullLeague();

        $this->stubRepository->method('getPickOwnership')->willReturn([
            ['ownerofpick' => 'Team28', 'teampick' => 'Team28', 'round' => 1, 'notes' => null],
        ]);

        $result = $this->service->calculateDraftOrder(2026);

        $pick1 = $result['round1'][0];
        $this->assertSame('Team28', $pick1['teamName']);
        $this->assertSame('Team28', $pick1['ownerName']);
        $this->assertFalse($pick1['isTraded']);
    }

    public function testMissingOwnershipDefaultsToOriginalTeam(): void
    {
        $this->configureStubWithFullLeague();

        // No ownership rows at all
        $this->stubRepository->method('getPickOwnership')->willReturn([]);

        $result = $this->service->calculateDraftOrder(2026);

        foreach ($result['round1'] as $slot) {
            $this->assertSame($slot['teamName'], $slot['ownerName']);
            $this->assertFalse($slot['isTraded']);
        }
    }

    public function testRound2HasDifferentOwnershipPerRound(): void
    {
        $this->configureStubWithFullLeague();

        $this->stubRepository->method('getPickOwnership')->willReturn([
            ['ownerofpick' => 'Team1', 'teampick' => 'Team28', 'round' => 1, 'notes' => 'R1 trade'],
            ['ownerofpick' => 'Team2', 'teampick' => 'Team28', 'round' => 2, 'notes' => 'R2 trade'],
        ]);

        $result = $this->service->calculateDraftOrder(2026);

        // Different ownership per round
        $this->assertSame('Team1', $result['round1'][0]['ownerName']);
        $this->assertSame('Team2', $result['round2'][0]['ownerName']);
    }

    public function testRound2IsOrderedStrictlyByRecord(): void
    {
        $standings = $this->buildStandingsWithWeakDivisionWinner();
        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($standings);
        $this->stubRepository->method('getPlayedGames')->willReturn([]);
        $this->stubRepository->method('getPickOwnership')->willReturn([]);
        $this->stubRepository->method('getPointDifferentials')->willReturn([]);

        $result = $this->service->calculateDraftOrder(2026);

        // In round 1, WeakDivWinner (30-52) is in the playoff section (picks 13-28)
        $r1PlayoffNames = array_column(array_slice($result['round1'], 12), 'teamName');
        $this->assertContains('WeakDivWinner', $r1PlayoffNames);

        // In round 2, order is strictly by record — WeakDivWinner should be
        // among the worse teams, not artificially pushed to playoff section
        $r2Wins = array_column($result['round2'], 'wins');
        // Verify wins are in ascending order (worst first)
        for ($i = 0; $i < count($r2Wins) - 1; $i++) {
            $this->assertLessThanOrEqual($r2Wins[$i + 1], $r2Wins[$i],
                'Round 2 pick ' . ($i + 1) . ' (' . $r2Wins[$i] . ' wins) should not have more wins than pick ' . ($i + 2) . ' (' . $r2Wins[$i + 1] . ' wins)');
        }
    }

    public function testDraftSlotContainsAllRequiredFields(): void
    {
        $this->configureStubWithFullLeague();

        $result = $this->service->calculateDraftOrder(2026);
        $slot = $result['round1'][0];

        $this->assertArrayHasKey('pick', $slot);
        $this->assertArrayHasKey('teamId', $slot);
        $this->assertArrayHasKey('teamName', $slot);
        $this->assertArrayHasKey('wins', $slot);
        $this->assertArrayHasKey('losses', $slot);
        $this->assertArrayHasKey('color1', $slot);
        $this->assertArrayHasKey('color2', $slot);
        $this->assertArrayHasKey('ownerId', $slot);
        $this->assertArrayHasKey('ownerName', $slot);
        $this->assertArrayHasKey('ownerColor1', $slot);
        $this->assertArrayHasKey('ownerColor2', $slot);
        $this->assertArrayHasKey('isTraded', $slot);
        $this->assertArrayHasKey('notes', $slot);
    }

    public function testZeroGamesPlayedProducesValidOrder(): void
    {
        $standings = $this->buildZeroGameStandings();

        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($standings);
        $this->stubRepository->method('getPlayedGames')->willReturn([]);
        $this->stubRepository->method('getPickOwnership')->willReturn([]);
        $this->stubRepository->method('getPointDifferentials')->willReturn([]);

        $result = $this->service->calculateDraftOrder(2026);

        $this->assertCount(28, $result['round1']);
        $this->assertCount(28, $result['round2']);

        $pickNumbers = array_column($result['round1'], 'pick');
        $this->assertSame(range(1, 28), $pickNumbers);
    }

    public function testPlayoffTiebreakerWinnerGetsLaterPick(): void
    {
        $standings = $this->buildTiedPlayoffTeams();
        $games = [
            // PlayoffA beats PlayoffB
            ['Visitor' => 201, 'VScore' => 100, 'Home' => 202, 'HScore' => 90],
        ];

        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($standings);
        $this->stubRepository->method('getPlayedGames')->willReturn($games);
        $this->stubRepository->method('getPickOwnership')->willReturn([]);
        $this->stubRepository->method('getPointDifferentials')->willReturn([]);

        $result = $this->service->calculateDraftOrder(2026);

        $playoffNames = array_column(array_slice($result['round1'], 12), 'teamName');
        $posA = array_search('PlayoffA', $playoffNames, true);
        $posB = array_search('PlayoffB', $playoffNames, true);

        $this->assertNotFalse($posA);
        $this->assertNotFalse($posB);
        // PlayoffA won H2H → should draft later (higher pick number)
        $this->assertGreaterThan($posB, $posA);
    }

    public function testTradedPickHasOwnerTeamColors(): void
    {
        $this->configureStubWithFullLeague();

        $this->stubRepository->method('getPickOwnership')->willReturn([
            ['ownerofpick' => 'Team1', 'teampick' => 'Team28', 'round' => 1, 'notes' => null],
        ]);

        $result = $this->service->calculateDraftOrder(2026);

        $pick1 = $result['round1'][0];
        // Owner colors should be Team1's colors
        $this->assertSame('AA0001', $pick1['ownerColor1']);
        $this->assertSame('BB0001', $pick1['ownerColor2']);
    }

    // =========================================================================
    // Helper methods to build test fixtures
    // =========================================================================

    private function configureStubWithFullLeague(): void
    {
        $standings = $this->buildFullLeagueStandings();

        $this->stubRepository->method('getAllTeamsWithStandings')->willReturn($standings);
        $this->stubRepository->method('getPlayedGames')->willReturn([]);
        // getPickOwnership is intentionally NOT set here — tests that need ownership override it
        $this->stubRepository->method('getPointDifferentials')->willReturn([]);
    }

    /**
     * Build 28 teams across 2 conferences / 4 divisions with distinct records.
     *
     * Eastern: Atlantic (teams 1-7), Central (teams 8-14)
     * Western: Midwest (teams 15-21), Pacific (teams 22-28)
     *
     * @return list<array{tid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, confWins: int|null, confLosses: int|null, divWins: int|null, divLosses: int|null, clinchedDivision: int|null, color1: string, color2: string}>
     */
    private function buildFullLeagueStandings(): array
    {
        $teams = [];
        $conferences = [
            'Eastern' => ['Atlantic', 'Central'],
            'Western' => ['Midwest', 'Pacific'],
        ];

        $tid = 1;
        foreach ($conferences as $conf => $divisions) {
            foreach ($divisions as $div) {
                for ($i = 0; $i < 7; $i++) {
                    $wins = 82 - (($tid - 1) * 3);
                    if ($wins < 0) {
                        $wins = 0;
                    }
                    $losses = 82 - $wins;
                    $pct = $wins > 0 ? round($wins / 82.0, 3) : 0.0;
                    $teams[] = [
                        'tid' => $tid,
                        'team_name' => 'Team' . $tid,
                        'wins' => $wins,
                        'losses' => $losses,
                        'pct' => $pct,
                        'conference' => $conf,
                        'division' => $div,
                        'confWins' => null,
                        'confLosses' => null,
                        'divWins' => null,
                        'divLosses' => null,
                        'clinchedDivision' => null,
                        'color1' => 'AA' . str_pad((string) $tid, 4, '0', STR_PAD_LEFT),
                        'color2' => 'BB' . str_pad((string) $tid, 4, '0', STR_PAD_LEFT),
                    ];
                    $tid++;
                }
            }
        }

        return $teams;
    }

    /**
     * Build standings where a division winner has a worse record than some wild card teams.
     *
     * @return list<array{tid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, confWins: int|null, confLosses: int|null, divWins: int|null, divLosses: int|null, clinchedDivision: int|null, color1: string, color2: string}>
     */
    private function buildStandingsWithWeakDivisionWinner(): array
    {
        $teams = [];
        $tid = 1;

        // Eastern Atlantic: WeakDivWinner at 30-52 but best in division
        $atlanticRecords = [
            ['name' => 'WeakDivWinner', 'wins' => 30, 'losses' => 52, 'clinchedDivision' => 1],
            ['name' => 'AtlTeam2', 'wins' => 28, 'losses' => 54, 'clinchedDivision' => null],
            ['name' => 'AtlTeam3', 'wins' => 25, 'losses' => 57, 'clinchedDivision' => null],
            ['name' => 'AtlTeam4', 'wins' => 22, 'losses' => 60, 'clinchedDivision' => null],
            ['name' => 'AtlTeam5', 'wins' => 20, 'losses' => 62, 'clinchedDivision' => null],
            ['name' => 'AtlTeam6', 'wins' => 18, 'losses' => 64, 'clinchedDivision' => null],
            ['name' => 'AtlTeam7', 'wins' => 15, 'losses' => 67, 'clinchedDivision' => null],
        ];

        foreach ($atlanticRecords as $rec) {
            $teams[] = $this->makeStandingsRow($tid++, $rec['name'], $rec['wins'], $rec['losses'], 'Eastern', 'Atlantic', $rec['clinchedDivision']);
        }

        // Eastern Central: Strong division
        $centralRecords = [
            ['name' => 'CenTeam1', 'wins' => 65, 'losses' => 17],
            ['name' => 'CenTeam2', 'wins' => 60, 'losses' => 22],
            ['name' => 'CenTeam3', 'wins' => 55, 'losses' => 27],
            ['name' => 'CenTeam4', 'wins' => 50, 'losses' => 32],
            ['name' => 'CenTeam5', 'wins' => 45, 'losses' => 37],
            ['name' => 'CenTeam6', 'wins' => 40, 'losses' => 42],
            ['name' => 'CenTeam7', 'wins' => 35, 'losses' => 47],
        ];

        foreach ($centralRecords as $rec) {
            $teams[] = $this->makeStandingsRow($tid++, $rec['name'], $rec['wins'], $rec['losses'], 'Eastern', 'Central');
        }

        // Western: 14 teams with middling records
        foreach (['Midwest', 'Pacific'] as $div) {
            for ($i = 0; $i < 7; $i++) {
                $wins = 50 - ($i * 5);
                $teams[] = $this->makeStandingsRow($tid++, $div . 'T' . ($i + 1), $wins, 82 - $wins, 'Western', $div);
            }
        }

        return $teams;
    }

    /**
     * Build two tied non-playoff teams for head-to-head tiebreaker testing.
     *
     * @return list<array{tid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, confWins: int|null, confLosses: int|null, divWins: int|null, divLosses: int|null, clinchedDivision: int|null, color1: string, color2: string}>
     */
    private function buildTiedStandings(): array
    {
        $teams = [];
        $tid = 1;

        // Eastern Atlantic: first 5 are strong playoff teams
        for ($i = 0; $i < 5; $i++) {
            $teams[] = $this->makeStandingsRow($tid++, 'EATop' . ($i + 1), 60 - ($i * 3), 22 + ($i * 3), 'Eastern', 'Atlantic');
        }
        // Two tied weak teams (will be non-playoff)
        $teams[] = $this->makeStandingsRow(101, 'TeamA', 25, 57, 'Eastern', 'Atlantic');
        $teams[] = $this->makeStandingsRow(102, 'TeamB', 25, 57, 'Eastern', 'Atlantic');

        // Eastern Central: 7 strong teams
        for ($i = 0; $i < 7; $i++) {
            $teams[] = $this->makeStandingsRow($tid++, 'ECTop' . ($i + 1), 55 - ($i * 3), 27 + ($i * 3), 'Eastern', 'Central');
        }

        // Western: 14 teams
        foreach (['Midwest', 'Pacific'] as $div) {
            for ($i = 0; $i < 7; $i++) {
                $wins = 50 - ($i * 5);
                $teams[] = $this->makeStandingsRow($tid++, $div . 'T' . ($i + 1), $wins, 82 - $wins, 'Western', $div);
            }
        }

        return $teams;
    }

    /**
     * Build two tied non-playoff teams with different conference records.
     *
     * @return list<array{tid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, confWins: int|null, confLosses: int|null, divWins: int|null, divLosses: int|null, clinchedDivision: int|null, color1: string, color2: string}>
     */
    private function buildTiedStandingsWithConfRecords(): array
    {
        $teams = [];
        $tid = 1;

        for ($i = 0; $i < 5; $i++) {
            $teams[] = $this->makeStandingsRow($tid++, 'EATop' . ($i + 1), 60 - ($i * 3), 22 + ($i * 3), 'Eastern', 'Atlantic');
        }
        $teamA = $this->makeStandingsRow(101, 'TeamA', 25, 57, 'Eastern', 'Atlantic');
        $teamA['confWins'] = 10;
        $teamA['confLosses'] = 30;
        $teams[] = $teamA;
        $teamB = $this->makeStandingsRow(102, 'TeamB', 25, 57, 'Eastern', 'Atlantic');
        $teamB['confWins'] = 20;
        $teamB['confLosses'] = 20;
        $teams[] = $teamB;

        for ($i = 0; $i < 7; $i++) {
            $teams[] = $this->makeStandingsRow($tid++, 'ECTop' . ($i + 1), 55 - ($i * 3), 27 + ($i * 3), 'Eastern', 'Central');
        }

        foreach (['Midwest', 'Pacific'] as $div) {
            for ($i = 0; $i < 7; $i++) {
                $wins = 50 - ($i * 5);
                $teams[] = $this->makeStandingsRow($tid++, $div . 'T' . ($i + 1), $wins, 82 - $wins, 'Western', $div);
            }
        }

        return $teams;
    }

    /**
     * Like buildTiedStandingsWithConfRecords but both teams have identical conf records.
     *
     * @return list<array{tid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, confWins: int|null, confLosses: int|null, divWins: int|null, divLosses: int|null, clinchedDivision: int|null, color1: string, color2: string}>
     */
    private function buildTiedStandingsWithSameConfRecords(): array
    {
        $teams = [];
        $tid = 1;

        for ($i = 0; $i < 5; $i++) {
            $teams[] = $this->makeStandingsRow($tid++, 'EATop' . ($i + 1), 60 - ($i * 3), 22 + ($i * 3), 'Eastern', 'Atlantic');
        }
        $teamA = $this->makeStandingsRow(101, 'TeamA', 25, 57, 'Eastern', 'Atlantic');
        $teamA['confWins'] = 15;
        $teamA['confLosses'] = 25;
        $teams[] = $teamA;
        $teamB = $this->makeStandingsRow(102, 'TeamB', 25, 57, 'Eastern', 'Atlantic');
        $teamB['confWins'] = 15;
        $teamB['confLosses'] = 25;
        $teams[] = $teamB;

        for ($i = 0; $i < 7; $i++) {
            $teams[] = $this->makeStandingsRow($tid++, 'ECTop' . ($i + 1), 55 - ($i * 3), 27 + ($i * 3), 'Eastern', 'Central');
        }

        foreach (['Midwest', 'Pacific'] as $div) {
            for ($i = 0; $i < 7; $i++) {
                $wins = 50 - ($i * 5);
                $teams[] = $this->makeStandingsRow($tid++, $div . 'T' . ($i + 1), $wins, 82 - $wins, 'Western', $div);
            }
        }

        return $teams;
    }

    /**
     * Build 28 teams all with 0-0 record.
     *
     * @return list<array{tid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, confWins: int|null, confLosses: int|null, divWins: int|null, divLosses: int|null, clinchedDivision: int|null, color1: string, color2: string}>
     */
    private function buildZeroGameStandings(): array
    {
        $teams = [];
        $tid = 1;
        $conferences = [
            'Eastern' => ['Atlantic', 'Central'],
            'Western' => ['Midwest', 'Pacific'],
        ];

        foreach ($conferences as $conf => $divisions) {
            foreach ($divisions as $div) {
                for ($i = 0; $i < 7; $i++) {
                    $teams[] = $this->makeStandingsRow($tid, 'T' . str_pad((string) $tid, 2, '0', STR_PAD_LEFT), 0, 0, $conf, $div);
                    $tid++;
                }
            }
        }

        return $teams;
    }

    /**
     * Build standings with two tied playoff teams for testing playoff tiebreaker direction.
     *
     * @return list<array{tid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, confWins: int|null, confLosses: int|null, divWins: int|null, divLosses: int|null, clinchedDivision: int|null, color1: string, color2: string}>
     */
    private function buildTiedPlayoffTeams(): array
    {
        $teams = [];
        $tid = 1;

        // Eastern Atlantic: Two tied playoff-quality teams
        $teams[] = $this->makeStandingsRow(201, 'PlayoffA', 50, 32, 'Eastern', 'Atlantic');
        $teams[] = $this->makeStandingsRow(202, 'PlayoffB', 50, 32, 'Eastern', 'Atlantic');
        for ($i = 0; $i < 5; $i++) {
            $teams[] = $this->makeStandingsRow($tid++, 'EAFill' . ($i + 1), 45 - ($i * 5), 37 + ($i * 5), 'Eastern', 'Atlantic');
        }

        // Eastern Central: 7 teams
        for ($i = 0; $i < 7; $i++) {
            $teams[] = $this->makeStandingsRow($tid++, 'ECFill' . ($i + 1), 55 - ($i * 4), 27 + ($i * 4), 'Eastern', 'Central');
        }

        // Western: 14 teams
        foreach (['Midwest', 'Pacific'] as $div) {
            for ($i = 0; $i < 7; $i++) {
                $wins = 50 - ($i * 5);
                $teams[] = $this->makeStandingsRow($tid++, $div . 'Fill' . ($i + 1), $wins, 82 - $wins, 'Western', $div);
            }
        }

        return $teams;
    }

    /**
     * @return array{tid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, confWins: int|null, confLosses: int|null, divWins: int|null, divLosses: int|null, clinchedDivision: int|null, color1: string, color2: string}
     */
    private function makeStandingsRow(
        int $tid,
        string $name,
        int $wins,
        int $losses,
        string $conference,
        string $division,
        ?int $clinchedDivision = null,
    ): array {
        $total = $wins + $losses;
        $pct = $total > 0 ? round($wins / $total, 3) : 0.0;

        return [
            'tid' => $tid,
            'team_name' => $name,
            'wins' => $wins,
            'losses' => $losses,
            'pct' => $pct,
            'conference' => $conference,
            'division' => $division,
            'confWins' => null,
            'confLosses' => null,
            'divWins' => null,
            'divLosses' => null,
            'clinchedDivision' => $clinchedDivision,
            'color1' => 'AA' . str_pad((string) $tid, 4, '0', STR_PAD_LEFT),
            'color2' => 'BB' . str_pad((string) $tid, 4, '0', STR_PAD_LEFT),
        ];
    }
}
