<?php

declare(strict_types=1);

namespace Tests\ProjectedDraftOrder\Support;

trait StandingsFixtures
{
    /**
     * @return array{teamid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, conf_wins: int|null, conf_losses: int|null, div_wins: int|null, div_losses: int|null, clinched_division: int|null, color1: string, color2: string}
     */
    private function makeStandingsRow(
        int $teamid,
        string $name,
        int $wins,
        int $losses,
        string $conference,
        string $division,
        ?int $clinched_division = null,
    ): array {
        $total = $wins + $losses;
        $pct = $total > 0 ? round($wins / $total, 3) : 0.0;

        return [
            'teamid' => $teamid,
            'team_name' => $name,
            'wins' => $wins,
            'losses' => $losses,
            'pct' => $pct,
            'conference' => $conference,
            'division' => $division,
            'conf_wins' => null,
            'conf_losses' => null,
            'div_wins' => null,
            'div_losses' => null,
            'clinched_division' => $clinched_division,
            'color1' => 'AA' . str_pad((string) $teamid, 4, '0', STR_PAD_LEFT),
            'color2' => 'BB' . str_pad((string) $teamid, 4, '0', STR_PAD_LEFT),
        ];
    }

    /**
     * Build 28 teams across 2 conferences / 4 divisions with distinct records.
     *
     * Eastern: Atlantic (teams 1-7), Central (teams 8-14)
     * Western: Midwest (teams 15-21), Pacific (teams 22-28)
     *
     * @return list<array{teamid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, conf_wins: int|null, conf_losses: int|null, div_wins: int|null, div_losses: int|null, clinched_division: int|null, color1: string, color2: string}>
     */
    private function buildFullLeagueStandings(): array
    {
        $teams = [];
        $conferences = [
            'Eastern' => ['Atlantic', 'Central'],
            'Western' => ['Midwest', 'Pacific'],
        ];

        $teamid = 1;
        foreach ($conferences as $conf => $divisions) {
            foreach ($divisions as $div) {
                for ($i = 0; $i < 7; $i++) {
                    $wins = 82 - (($teamid - 1) * 3);
                    if ($wins < 0) {
                        $wins = 0;
                    }
                    $losses = 82 - $wins;
                    $pct = $wins > 0 ? round($wins / 82.0, 3) : 0.0;
                    $teams[] = [
                        'teamid' => $teamid,
                        'team_name' => 'Team' . $teamid,
                        'wins' => $wins,
                        'losses' => $losses,
                        'pct' => $pct,
                        'conference' => $conf,
                        'division' => $div,
                        'conf_wins' => null,
                        'conf_losses' => null,
                        'div_wins' => null,
                        'div_losses' => null,
                        'clinched_division' => null,
                        'color1' => 'AA' . str_pad((string) $teamid, 4, '0', STR_PAD_LEFT),
                        'color2' => 'BB' . str_pad((string) $teamid, 4, '0', STR_PAD_LEFT),
                    ];
                    $teamid++;
                }
            }
        }

        return $teams;
    }

    /**
     * Build standings where a division winner has a worse record than some wild card teams.
     *
     * @return list<array{teamid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, conf_wins: int|null, conf_losses: int|null, div_wins: int|null, div_losses: int|null, clinched_division: int|null, color1: string, color2: string}>
     */
    private function buildStandingsWithWeakDivisionWinner(): array
    {
        $teams = [];
        $teamid = 1;

        // Eastern Atlantic: WeakDivWinner at 30-52 but best in division
        $atlanticRecords = [
            ['name' => 'WeakDivWinner', 'wins' => 30, 'losses' => 52, 'clinched_division' => 1],
            ['name' => 'AtlTeam2', 'wins' => 28, 'losses' => 54, 'clinched_division' => null],
            ['name' => 'AtlTeam3', 'wins' => 25, 'losses' => 57, 'clinched_division' => null],
            ['name' => 'AtlTeam4', 'wins' => 22, 'losses' => 60, 'clinched_division' => null],
            ['name' => 'AtlTeam5', 'wins' => 20, 'losses' => 62, 'clinched_division' => null],
            ['name' => 'AtlTeam6', 'wins' => 18, 'losses' => 64, 'clinched_division' => null],
            ['name' => 'AtlTeam7', 'wins' => 15, 'losses' => 67, 'clinched_division' => null],
        ];

        foreach ($atlanticRecords as $rec) {
            $teams[] = $this->makeStandingsRow($teamid++, $rec['name'], $rec['wins'], $rec['losses'], 'Eastern', 'Atlantic', $rec['clinched_division']);
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
            $teams[] = $this->makeStandingsRow($teamid++, $rec['name'], $rec['wins'], $rec['losses'], 'Eastern', 'Central');
        }

        // Western: 14 teams with middling records
        foreach (['Midwest', 'Pacific'] as $div) {
            for ($i = 0; $i < 7; $i++) {
                $wins = 50 - ($i * 5);
                $teams[] = $this->makeStandingsRow($teamid++, $div . 'T' . ($i + 1), $wins, 82 - $wins, 'Western', $div);
            }
        }

        return $teams;
    }

    /**
     * Build two tied non-playoff teams for head-to-head tiebreaker testing.
     *
     * @return list<array{teamid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, conf_wins: int|null, conf_losses: int|null, div_wins: int|null, div_losses: int|null, clinched_division: int|null, color1: string, color2: string}>
     */
    private function buildTiedStandings(): array
    {
        $teams = [];
        $teamid = 1;

        // Eastern Atlantic: first 5 are strong playoff teams
        for ($i = 0; $i < 5; $i++) {
            $teams[] = $this->makeStandingsRow($teamid++, 'EATop' . ($i + 1), 60 - ($i * 3), 22 + ($i * 3), 'Eastern', 'Atlantic');
        }
        // Two tied weak teams (will be non-playoff)
        $teams[] = $this->makeStandingsRow(101, 'TeamA', 25, 57, 'Eastern', 'Atlantic');
        $teams[] = $this->makeStandingsRow(102, 'TeamB', 25, 57, 'Eastern', 'Atlantic');

        // Eastern Central: 7 strong teams
        for ($i = 0; $i < 7; $i++) {
            $teams[] = $this->makeStandingsRow($teamid++, 'ECTop' . ($i + 1), 55 - ($i * 3), 27 + ($i * 3), 'Eastern', 'Central');
        }

        // Western: 14 teams
        foreach (['Midwest', 'Pacific'] as $div) {
            for ($i = 0; $i < 7; $i++) {
                $wins = 50 - ($i * 5);
                $teams[] = $this->makeStandingsRow($teamid++, $div . 'T' . ($i + 1), $wins, 82 - $wins, 'Western', $div);
            }
        }

        return $teams;
    }

    /**
     * Build two tied non-playoff teams with different conference records.
     *
     * @return list<array{teamid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, conf_wins: int|null, conf_losses: int|null, div_wins: int|null, div_losses: int|null, clinched_division: int|null, color1: string, color2: string}>
     */
    private function buildTiedStandingsWithConfRecords(): array
    {
        $teams = [];
        $teamid = 1;

        for ($i = 0; $i < 5; $i++) {
            $teams[] = $this->makeStandingsRow($teamid++, 'EATop' . ($i + 1), 60 - ($i * 3), 22 + ($i * 3), 'Eastern', 'Atlantic');
        }
        $teamA = $this->makeStandingsRow(101, 'TeamA', 25, 57, 'Eastern', 'Atlantic');
        $teamA['conf_wins'] = 10;
        $teamA['conf_losses'] = 30;
        $teams[] = $teamA;
        $teamB = $this->makeStandingsRow(102, 'TeamB', 25, 57, 'Eastern', 'Atlantic');
        $teamB['conf_wins'] = 20;
        $teamB['conf_losses'] = 20;
        $teams[] = $teamB;

        for ($i = 0; $i < 7; $i++) {
            $teams[] = $this->makeStandingsRow($teamid++, 'ECTop' . ($i + 1), 55 - ($i * 3), 27 + ($i * 3), 'Eastern', 'Central');
        }

        foreach (['Midwest', 'Pacific'] as $div) {
            for ($i = 0; $i < 7; $i++) {
                $wins = 50 - ($i * 5);
                $teams[] = $this->makeStandingsRow($teamid++, $div . 'T' . ($i + 1), $wins, 82 - $wins, 'Western', $div);
            }
        }

        return $teams;
    }

    /**
     * Like buildTiedStandingsWithConfRecords but both teams have identical conf records.
     *
     * @return list<array{teamid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, conf_wins: int|null, conf_losses: int|null, div_wins: int|null, div_losses: int|null, clinched_division: int|null, color1: string, color2: string}>
     */
    private function buildTiedStandingsWithSameConfRecords(): array
    {
        $teams = [];
        $teamid = 1;

        for ($i = 0; $i < 5; $i++) {
            $teams[] = $this->makeStandingsRow($teamid++, 'EATop' . ($i + 1), 60 - ($i * 3), 22 + ($i * 3), 'Eastern', 'Atlantic');
        }
        $teamA = $this->makeStandingsRow(101, 'TeamA', 25, 57, 'Eastern', 'Atlantic');
        $teamA['conf_wins'] = 15;
        $teamA['conf_losses'] = 25;
        $teams[] = $teamA;
        $teamB = $this->makeStandingsRow(102, 'TeamB', 25, 57, 'Eastern', 'Atlantic');
        $teamB['conf_wins'] = 15;
        $teamB['conf_losses'] = 25;
        $teams[] = $teamB;

        for ($i = 0; $i < 7; $i++) {
            $teams[] = $this->makeStandingsRow($teamid++, 'ECTop' . ($i + 1), 55 - ($i * 3), 27 + ($i * 3), 'Eastern', 'Central');
        }

        foreach (['Midwest', 'Pacific'] as $div) {
            for ($i = 0; $i < 7; $i++) {
                $wins = 50 - ($i * 5);
                $teams[] = $this->makeStandingsRow($teamid++, $div . 'T' . ($i + 1), $wins, 82 - $wins, 'Western', $div);
            }
        }

        return $teams;
    }

    /**
     * Build 28 teams all with 0-0 record.
     *
     * @return list<array{teamid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, conf_wins: int|null, conf_losses: int|null, div_wins: int|null, div_losses: int|null, clinched_division: int|null, color1: string, color2: string}>
     */
    private function buildZeroGameStandings(): array
    {
        $teams = [];
        $teamid = 1;
        $conferences = [
            'Eastern' => ['Atlantic', 'Central'],
            'Western' => ['Midwest', 'Pacific'],
        ];

        foreach ($conferences as $conf => $divisions) {
            foreach ($divisions as $div) {
                for ($i = 0; $i < 7; $i++) {
                    $teams[] = $this->makeStandingsRow($teamid, 'T' . str_pad((string) $teamid, 2, '0', STR_PAD_LEFT), 0, 0, $conf, $div);
                    $teamid++;
                }
            }
        }

        return $teams;
    }

    /**
     * Build standings with two tied playoff teams for testing playoff tiebreaker direction.
     *
     * @return list<array{teamid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, conf_wins: int|null, conf_losses: int|null, div_wins: int|null, div_losses: int|null, clinched_division: int|null, color1: string, color2: string}>
     */
    private function buildTiedPlayoffTeams(): array
    {
        $teams = [];
        $teamid = 1;

        // Eastern Atlantic: Two tied playoff-quality teams
        $teams[] = $this->makeStandingsRow(201, 'PlayoffA', 50, 32, 'Eastern', 'Atlantic');
        $teams[] = $this->makeStandingsRow(202, 'PlayoffB', 50, 32, 'Eastern', 'Atlantic');
        for ($i = 0; $i < 5; $i++) {
            $teams[] = $this->makeStandingsRow($teamid++, 'EAFill' . ($i + 1), 45 - ($i * 5), 37 + ($i * 5), 'Eastern', 'Atlantic');
        }

        // Eastern Central: 7 teams
        for ($i = 0; $i < 7; $i++) {
            $teams[] = $this->makeStandingsRow($teamid++, 'ECFill' . ($i + 1), 55 - ($i * 4), 27 + ($i * 4), 'Eastern', 'Central');
        }

        // Western: 14 teams
        foreach (['Midwest', 'Pacific'] as $div) {
            for ($i = 0; $i < 7; $i++) {
                $wins = 50 - ($i * 5);
                $teams[] = $this->makeStandingsRow($teamid++, $div . 'Fill' . ($i + 1), $wins, 82 - $wins, 'Western', $div);
            }
        }

        return $teams;
    }

    /**
     * Build 28-team standings with three tied non-playoff teams (25-57) in Eastern Atlantic.
     *
     * @return list<array{teamid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, conf_wins: int|null, conf_losses: int|null, div_wins: int|null, div_losses: int|null, clinched_division: int|null, color1: string, color2: string}>
     */
    private function buildThreeWayTiedStandings(): array
    {
        $teams = [];
        $teamid = 1;

        // Eastern Atlantic: 4 strong playoff teams + 3 tied weak teams
        for ($i = 0; $i < 4; $i++) {
            $teams[] = $this->makeStandingsRow($teamid++, 'EATop' . ($i + 1), 60 - ($i * 3), 22 + ($i * 3), 'Eastern', 'Atlantic');
        }
        $teams[] = $this->makeStandingsRow(301, 'TiedTeamA', 25, 57, 'Eastern', 'Atlantic');
        $teams[] = $this->makeStandingsRow(302, 'TiedTeamB', 25, 57, 'Eastern', 'Atlantic');
        $teams[] = $this->makeStandingsRow(303, 'TiedTeamC', 25, 57, 'Eastern', 'Atlantic');

        // Eastern Central: 7 strong teams
        for ($i = 0; $i < 7; $i++) {
            $teams[] = $this->makeStandingsRow($teamid++, 'ECTop' . ($i + 1), 55 - ($i * 3), 27 + ($i * 3), 'Eastern', 'Central');
        }

        // Western: 14 teams
        foreach (['Midwest', 'Pacific'] as $div) {
            for ($i = 0; $i < 7; $i++) {
                $wins = 50 - ($i * 5);
                $teams[] = $this->makeStandingsRow($teamid++, $div . 'T' . ($i + 1), $wins, 82 - $wins, 'Western', $div);
            }
        }

        return $teams;
    }

    /**
     * Build a 28-team league where two same-pct teams meet in round-2's all-teams sort,
     * with div-winner status as the sole discriminator.
     *
     * TeamA (id=201, clinched_division=1, pct=0.5) is Eastern Atlantic division winner.
     * TeamB (id=202, clinched_division=null, pct=0.5) is Western Midwest non-playoff.
     * No H2H games, no point-diffs → div-winner status decides in applyNonH2HTiebreakers.
     * They're in different round-1 buckets (divisionWinners vs nonPlayoff) and meet in
     * the round-2 allTeamsSorted call only.
     *
     * @return list<array{teamid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, conf_wins: int|null, conf_losses: int|null, div_wins: int|null, div_losses: int|null, clinched_division: int|null, color1: string, color2: string}>
     */
    private function buildTiedWithDivisionWinnerStatus(): array
    {
        $teams = [];
        $teamid = 1;

        // Eastern Atlantic: TeamA is the sole division winner (clinched_division=1, 41-41)
        // Remaining 6 Atlantic teams are clearly below 0.5 pct
        $teams[] = $this->makeStandingsRow(201, 'TeamA', 41, 41, 'Eastern', 'Atlantic', 1);
        $atlanticWins = [28, 24, 20, 16, 12, 8];
        foreach ($atlanticWins as $w) {
            $teams[] = $this->makeStandingsRow($teamid++, 'AtlTeam' . $teamid, $w, 82 - $w, 'Eastern', 'Atlantic');
        }

        // Eastern Central: strong teams, all distinct pcts above 0.5
        $centralWins = [75, 70, 65, 60, 55, 50, 45];
        foreach ($centralWins as $w) {
            $teams[] = $this->makeStandingsRow($teamid++, 'CenTeam' . $teamid, $w, 82 - $w, 'Eastern', 'Central');
        }

        // Western Midwest: 6 strong teams (will be wild cards) + TeamB (non-playoff, 41-41)
        $midwestWins = [72, 67, 62, 57, 52, 47];
        foreach ($midwestWins as $w) {
            $teams[] = $this->makeStandingsRow($teamid++, 'MidTeam' . $teamid, $w, 82 - $w, 'Western', 'Midwest');
        }
        $teams[] = $this->makeStandingsRow(202, 'TeamB', 41, 41, 'Western', 'Midwest', null);

        // Western Pacific: 7 teams — one div winner + 6 non-winners all below TeamB's pct
        $pacificWins = [69, 39, 34, 29, 24, 19, 14];
        foreach ($pacificWins as $w) {
            $teams[] = $this->makeStandingsRow($teamid++, 'PacTeam' . $teamid, $w, 82 - $w, 'Western', 'Pacific');
        }

        return $teams;
    }

    /**
     * Build a 28-team league where two same-pct same-division teams differ only in
     * division record — the div-record branch in applyNonH2HTiebreakers decides.
     *
     * TeamA (id=401, pct=0.5, div_wins=8, div_losses=2) and
     * TeamB (id=402, pct=0.5, div_wins=2, div_losses=8) are in Eastern Atlantic.
     * No H2H games, equal conf records (null), equal point-diffs.
     *
     * @return list<array{teamid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, conf_wins: int|null, conf_losses: int|null, div_wins: int|null, div_losses: int|null, clinched_division: int|null, color1: string, color2: string}>
     */
    private function buildTiedWithDivisionRecords(): array
    {
        $teams = [];
        $teamid = 1;

        // Eastern Atlantic: 5 strong playoff teams + TeamA + TeamB (both non-playoff, 25-57)
        for ($i = 0; $i < 5; $i++) {
            $teams[] = $this->makeStandingsRow($teamid++, 'EATop' . ($i + 1), 60 - ($i * 3), 22 + ($i * 3), 'Eastern', 'Atlantic');
        }
        $teamA = $this->makeStandingsRow(401, 'TeamA', 25, 57, 'Eastern', 'Atlantic');
        $teamA['div_wins'] = 8;
        $teamA['div_losses'] = 2;
        $teams[] = $teamA;
        $teamB = $this->makeStandingsRow(402, 'TeamB', 25, 57, 'Eastern', 'Atlantic');
        $teamB['div_wins'] = 2;
        $teamB['div_losses'] = 8;
        $teams[] = $teamB;

        // Eastern Central: 7 strong teams
        for ($i = 0; $i < 7; $i++) {
            $teams[] = $this->makeStandingsRow($teamid++, 'ECTop' . ($i + 1), 55 - ($i * 3), 27 + ($i * 3), 'Eastern', 'Central');
        }

        // Western: 14 teams
        foreach (['Midwest', 'Pacific'] as $div) {
            for ($i = 0; $i < 7; $i++) {
                $wins = 50 - ($i * 5);
                $teams[] = $this->makeStandingsRow($teamid++, $div . 'T' . ($i + 1), $wins, 82 - $wins, 'Western', $div);
            }
        }

        return $teams;
    }

    /**
     * Build a 28-team league where two teams are identical in every tiebreaker field;
     * alphabetical team_name is the sole discriminator.
     *
     * TeamA="ArcticTeam" (id=501) and TeamB="ZoneTeam" (id=502) share pct=0.5,
     * same division, null conf/div records, and zero point-diffs. No H2H games.
     * "ArcticTeam" < "ZoneTeam" alphabetically, so ArcticTeam wins the tiebreaker
     * (returns negative in applyNonH2HTiebreakers → comes later in draft order = worse pick).
     *
     * @return list<array{teamid: int, team_name: string, wins: int, losses: int, pct: float, conference: string, division: string, conf_wins: int|null, conf_losses: int|null, div_wins: int|null, div_losses: int|null, clinched_division: int|null, color1: string, color2: string}>
     */
    private function buildFullyTiedPair(): array
    {
        $teams = [];
        $teamid = 1;

        // Eastern Atlantic: 5 strong playoff teams + fully-tied pair (non-playoff)
        for ($i = 0; $i < 5; $i++) {
            $teams[] = $this->makeStandingsRow($teamid++, 'EATop' . ($i + 1), 60 - ($i * 3), 22 + ($i * 3), 'Eastern', 'Atlantic');
        }
        $teams[] = $this->makeStandingsRow(501, 'ArcticTeam', 25, 57, 'Eastern', 'Atlantic');
        $teams[] = $this->makeStandingsRow(502, 'ZoneTeam', 25, 57, 'Eastern', 'Atlantic');

        // Eastern Central: 7 strong teams
        for ($i = 0; $i < 7; $i++) {
            $teams[] = $this->makeStandingsRow($teamid++, 'ECTop' . ($i + 1), 55 - ($i * 3), 27 + ($i * 3), 'Eastern', 'Central');
        }

        // Western: 14 teams
        foreach (['Midwest', 'Pacific'] as $div) {
            for ($i = 0; $i < 7; $i++) {
                $wins = 50 - ($i * 5);
                $teams[] = $this->makeStandingsRow($teamid++, $div . 'T' . ($i + 1), $wins, 82 - $wins, 'Western', $div);
            }
        }

        return $teams;
    }
}
