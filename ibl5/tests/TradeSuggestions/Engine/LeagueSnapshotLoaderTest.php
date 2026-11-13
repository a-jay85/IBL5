<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use League\League;
use PHPUnit\Framework\TestCase;
use ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface;
use Standings\Contracts\StandingsRepositoryInterface;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use Trading\Contracts\TradeFormRepositoryInterface;
use TradeSuggestions\Contracts\SuggestionAssetRepositoryInterface;
use TradeSuggestions\Contracts\TeamModeRepositoryInterface;
use TradeSuggestions\Engine\LeagueSnapshotLoader;
use TradeSuggestions\Engine\ModeResolver;
use TradeSuggestions\Projection\Contracts\ProjectionPlayerRepositoryInterface;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TeamModeGuesser;
use TradeSuggestions\TeamModeService;
use TradeSuggestions\TeamModeSetting;
use TradeSuggestions\Valuation\Contracts\ValuationTeamFactorsRepositoryInterface;
use TradeSuggestions\Valuation\TeamFactors;

final class LeagueSnapshotLoaderTest extends TestCase
{
    public function testLoaderResolvesModesThroughModeResolver(): void
    {
        $loader = $this->loader(stored: [2 => TeamModeSetting::Rebuild]);

        $s = $loader->snapshot();

        self::assertSame(TeamMode::Middle, $s->mode(1));
        self::assertSame(TeamMode::Rebuild, $s->mode(2));
    }

    public function testLoaderPassesAdvancesContractYearsAndLastSeasonYearToPlayerRepository(): void
    {
        $players = self::createMock(ProjectionPlayerRepositoryInterface::class);
        $players->expects($this->once())->method('getLeaguePlayers')->with(true, 2026)->willReturn(self::rosters());
        $players->method('getRatingScale')->willReturn(ProjectionFactory::scale());

        $s = $this->loader(players: $players, phase: 'Free Agency')->snapshot();

        self::assertTrue($s->advancesContractYears);
        self::assertFalse($s->regularSeason);
        self::assertSame(2027, $s->seasonYear);
    }

    public function testStandingsRowsReKeyedWithDerivedLosses(): void
    {
        $s = $this->loader()->snapshot();

        self::assertSame(
            ['wins' => 30, 'losses' => 30, 'pct' => 0.5, 'games_unplayed' => 22, 'conference' => 'Eastern'],
            $s->standings[1],
        );
        self::assertSame(League::REGULAR_SEASON_GAMES, $s->standings[2]['games_unplayed']);
        self::assertSame(0, $s->standings[2]['wins']);
        self::assertTrue($s->regularSeason);
    }

    public function testNullStandingsCountsReadAsFullSeasonLeft(): void
    {
        $row = self::standingsRow(1);
        $row['games_unplayed'] = null;
        $row['homeGames'] = null;
        $row['awayGames'] = 40;

        $s = $this->loader(standingsRows: [$row])->snapshot();

        self::assertSame(League::REGULAR_SEASON_GAMES, $s->standings[1]['games_unplayed']);
        self::assertSame(10, $s->standings[1]['losses']);
    }

    public function testNullAwayGamesCountsAsZero(): void
    {
        $row = self::standingsRow(1);
        $row['homeGames'] = 40;
        $row['awayGames'] = null;

        $s = $this->loader(standingsRows: [$row])->snapshot();

        self::assertSame(10, $s->standings[1]['losses']);
    }

    public function testTeamWithoutStandingsRowGetsTheFullZeroRow(): void
    {
        $s = $this->loader()->snapshot();

        self::assertSame(
            ['wins' => 0, 'losses' => 0, 'pct' => 0.0, 'games_unplayed' => League::REGULAR_SEASON_GAMES, 'conference' => ''],
            $s->standings[2],
        );
    }

    public function testStreaksAreReKeyedByTeamWithLastWinAndLastLossOnly(): void
    {
        $streaks = [
            1 => self::streakRow(3, 2),
            2 => self::streakRow(7, 1),
        ];

        $s = $this->loader(streakRows: $streaks)->snapshot();

        self::assertSame([1 => ['last_win' => 3, 'last_loss' => 2], 2 => ['last_win' => 7, 'last_loss' => 1]], $s->streaks);
    }

    public function testLoaderLastKeyIsNull(): void
    {
        $loader = $this->loader();

        self::assertNull($loader->lastKey());
        $loader->snapshot();
        self::assertNull($loader->lastKey());
    }

    /**
     * @param array<int, TeamModeSetting> $stored
     * @param list<array<string, mixed>>|null $standingsRows
     * @param array<int, array<string, mixed>> $streakRows
     */
    private function loader(
        ?ProjectionPlayerRepositoryInterface $players = null,
        array $stored = [],
        string $phase = 'Regular Season',
        ?array $standingsRows = null,
        array $streakRows = [],
    ): LeagueSnapshotLoader {
        if ($players === null) {
            $players = self::createStub(ProjectionPlayerRepositoryInterface::class);
            $players->method('getLeaguePlayers')->willReturn(self::rosters());
            $players->method('getRatingScale')->willReturn(ProjectionFactory::scale());
        }

        $factors = self::createStub(ValuationTeamFactorsRepositoryInterface::class);
        $factors->method('getTeamFactors')->willReturn([1 => new TeamFactors(1, 30, 30, 500, 500), 2 => new TeamFactors(2, 30, 30, 500, 500)]);
        $factors->method('getCashByTeamAndOffset')->willReturn([]);

        $standings = self::createStub(StandingsRepositoryInterface::class);
        $standings->method('getAllStandings')->willReturn($standingsRows ?? [self::standingsRow(1)]);
        $standings->method('getAllStreakData')->willReturn($streakRows);

        $draftOrder = self::createStub(ProjectedDraftOrderServiceInterface::class);
        $draftOrder->method('calculateDraftOrder')->willReturn(['round1' => [self::slot(1), self::slot(2)], 'round2' => []]);

        $assets = self::createStub(SuggestionAssetRepositoryInterface::class);
        $assets->method('getRostersForTeams')->willReturn([1 => self::rotation(), 2 => self::rotation()]);
        $assets->method('getRegularSeasonRecords')->willReturn([]);
        $assets->method('getPicksForTeams')->willReturn([]);

        $form = self::createStub(TradeFormRepositoryInterface::class);
        $form->method('getAllTeamsWithCity')->willReturn([
            ['teamid' => 1, 'team_name' => 'One', 'team_city' => 'A', 'color1' => '000000', 'color2' => 'FFFFFF'],
            ['teamid' => 2, 'team_name' => 'Two', 'team_city' => 'B', 'color1' => '000000', 'color2' => 'FFFFFF'],
        ]);

        $modeRepo = self::createStub(TeamModeRepositoryInterface::class);
        $modeRepo->method('getAllStoredSettings')->willReturn($stored);

        $season = new \Tests\WideUnit\Mocks\Season(self::createStub(\mysqli::class));
        $season->endingYear = 2027;
        $season->phase = $phase;

        $resolver = new ModeResolver(
            $assets,
            $draftOrder,
            $modeRepo,
            $form,
            new TeamModeService(self::createStub(TeamModeRepositoryInterface::class), new TeamModeGuesser()),
            $season,
        );

        return new LeagueSnapshotLoader($players, $factors, $standings, $draftOrder, $assets, $form, $resolver, $season);
    }

    /**
     * @return array<int, list<\TradeSuggestions\Projection\ProjectionPlayer>>
     */
    private static function rosters(): array
    {
        $out = [];
        foreach ([1, 2] as $tid) {
            $out[$tid] = [];
            foreach (['PG', 'SG', 'SF', 'PF', 'C'] as $i => $pos) {
                $out[$tid][] = ProjectionFactory::player(pid: $tid * 100 + $i, tid: $tid, pos: $pos, dcDepth: [$pos => 1], dcMinutes: 30);
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function streakRow(int $lastWin, int $lastLoss): array
    {
        return [
            'last_win' => $lastWin, 'last_loss' => $lastLoss, 'streak_type' => 'W', 'streak' => 1, 'ranking' => 1,
            'sos' => 0.5, 'remaining_sos' => 0.5, 'sos_rank' => 1, 'remaining_sos_rank' => 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function standingsRow(int $tid): array
    {
        return [
            'teamid' => $tid, 'team_name' => 'One', 'league_record' => '30-30', 'pct' => '0.500',
            'conf_gb' => '0', 'div_gb' => '0', 'conf_record' => '', 'div_record' => '', 'home_record' => '',
            'away_record' => '', 'games_unplayed' => 22, 'conf_magic_number' => 0, 'div_magic_number' => 0,
            'clinched_conference' => 0, 'clinched_division' => 0, 'clinched_playoffs' => 0, 'clinched_league' => 0,
            'wins' => 30, 'homeGames' => 30, 'awayGames' => 30, 'conference' => 'Eastern', 'division' => 'Atlantic',
            'color1' => '000000', 'color2' => 'FFFFFF',
        ];
    }

    /**
     * @return list<SuggestionPlayer>
     */
    private static function rotation(): array
    {
        $players = [];
        foreach ([1, 2] as $i) {
            $players[] = new SuggestionPlayer(
                pid: $i,
                name: 'Player ' . $i,
                pos: 'SF',
                age: $i === 1 ? 26 : 27,
                injured: false,
                currentSalary: 1000,
                yearsRemaining: $i === 1 ? 2 : 1,
                futureSalary: 0,
                dcMinutes: 30,
            );
        }

        return $players;
    }

    /**
     * @return array{pick: int, teamId: int, teamName: string, wins: int, losses: int, color1: string, color2: string, ownerId: int, ownerName: string, ownerColor1: string, ownerColor2: string, isTraded: bool, notes: string, movement: int|null, player: string}
     */
    private static function slot(int $teamId): array
    {
        return [
            'pick' => $teamId, 'teamId' => $teamId, 'teamName' => 'T' . $teamId, 'wins' => 41, 'losses' => 41,
            'color1' => '000000', 'color2' => 'FFFFFF', 'ownerId' => $teamId, 'ownerName' => 'T' . $teamId,
            'ownerColor1' => '000000', 'ownerColor2' => 'FFFFFF', 'isTraded' => false, 'notes' => '', 'movement' => null, 'player' => '',
        ];
    }
}
