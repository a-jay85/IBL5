<?php

declare(strict_types=1);

namespace Tests\Standings;

use PHPUnit\Framework\TestCase;
use Standings\Contracts\StandingsRepositoryInterface;
use Standings\StandingsRowView;

/**
 * Direct unit tests for StandingsRowView.
 *
 * The golden-master snapshots only report an opaque HTML diff when a clinch
 * weight, precedence branch, or cascade boundary regresses. These tests call
 * the six public methods directly so each mutation fails a named scenario.
 *
 * @phpstan-import-type StandingsRow from StandingsRepositoryInterface
 */
final class StandingsRowViewTest extends TestCase
{
    /**
     * @return StandingsRow
     */
    private function makeRow(
        int $league = 0,
        int $conference = 0,
        int $division = 0,
        int $playoffs = 0,
        int $wins = 50,
        int $gamesUnplayed = 0,
        string $teamName = 'Safe Team',
    ): array {
        return [
            'teamid' => 1,
            'team_name' => $teamName,
            'league_record' => '50-32',
            'pct' => '0.610',
            'gamesBack' => '0',
            'conf_record' => '30-18',
            'div_record' => '12-6',
            'home_record' => '28-13',
            'away_record' => '22-19',
            'games_unplayed' => $gamesUnplayed,
            'magicNumber' => 0,
            'clinched_conference' => $conference,
            'clinched_division' => $division,
            'clinched_playoffs' => $playoffs,
            'clinched_league' => $league,
            'wins' => $wins,
            'homeGames' => 41,
            'awayGames' => 41,
            'color1' => 'FF0000',
            'color2' => '000000',
        ];
    }

    public function testGetClinchTierClassReturnsLeagueWhenAllFlagsSet(): void
    {
        $row = $this->makeRow(league: 1, conference: 1, division: 1, playoffs: 1);

        $this->assertSame('clinch-league', StandingsRowView::getClinchTierClass($row));
    }

    public function testGetClinchTierClassPrecedenceConferenceOverDivisionAndPlayoffs(): void
    {
        $row = $this->makeRow(conference: 1, division: 1, playoffs: 1);

        $this->assertSame('clinch-conference', StandingsRowView::getClinchTierClass($row));
    }

    public function testGetClinchTierClassPrecedenceDivisionOverPlayoffs(): void
    {
        $row = $this->makeRow(division: 1, playoffs: 1);

        $this->assertSame('clinch-division', StandingsRowView::getClinchTierClass($row));
    }

    public function testGetClinchTierClassReturnsPlayoffsWhenOnlyPlayoffsSet(): void
    {
        $row = $this->makeRow(playoffs: 1);

        $this->assertSame('clinch-playoffs', StandingsRowView::getClinchTierClass($row));
    }

    public function testGetClinchTierScoreWeightsEachFlag(): void
    {
        $this->assertSame(4, StandingsRowView::getClinchTierScore($this->makeRow(league: 1)));
        $this->assertSame(3, StandingsRowView::getClinchTierScore($this->makeRow(conference: 1)));
        $this->assertSame(2, StandingsRowView::getClinchTierScore($this->makeRow(division: 1)));
        $this->assertSame(1, StandingsRowView::getClinchTierScore($this->makeRow(playoffs: 1)));
        $this->assertSame(
            10,
            StandingsRowView::getClinchTierScore($this->makeRow(league: 1, conference: 1, division: 1, playoffs: 1))
        );
        $this->assertSame(0, StandingsRowView::getClinchTierScore($this->makeRow()));
    }

    public function testHasClinchStatusTrueForEachSingleFlag(): void
    {
        $this->assertTrue(StandingsRowView::hasClinchStatus($this->makeRow(league: 1)));
        $this->assertTrue(StandingsRowView::hasClinchStatus($this->makeRow(conference: 1)));
        $this->assertTrue(StandingsRowView::hasClinchStatus($this->makeRow(division: 1)));
        $this->assertTrue(StandingsRowView::hasClinchStatus($this->makeRow(playoffs: 1)));
        $this->assertFalse(StandingsRowView::hasClinchStatus($this->makeRow()));
    }

    public function testGetBottomLockedIndexesSeasonOverLocksAllUnclinched(): void
    {
        $view = new StandingsRowView();

        $standings = [
            $this->makeRow(playoffs: 1, wins: 50),
            $this->makeRow(wins: 40),
            $this->makeRow(wins: 30),
        ];
        $this->assertSame([1 => true, 2 => true], $view->getBottomLockedIndexes($standings));

        // Index 1 is catchable (49 vs 50), so only the season-over branch locks both rows.
        $closeStandings = [
            $this->makeRow(wins: 50),
            $this->makeRow(wins: 49),
        ];
        $this->assertSame([0 => true, 1 => true], $view->getBottomLockedIndexes($closeStandings));
    }

    public function testGetBottomLockedIndexesCascadeLocksBottomTwo(): void
    {
        $view = new StandingsRowView();

        $standings = [
            $this->makeRow(wins: 50, gamesUnplayed: 5),
            $this->makeRow(wins: 40, gamesUnplayed: 10),
            $this->makeRow(wins: 30, gamesUnplayed: 5),
            $this->makeRow(wins: 20, gamesUnplayed: 5),
        ];

        $locked = $view->getBottomLockedIndexes($standings);

        // Index 1 sits on the boundary: 40 + 10 = 50, equal to index 0's wins, so it is catchable.
        $this->assertSame([3 => true, 2 => true], $locked);
        $this->assertArrayNotHasKey(1, $locked);
        $this->assertArrayNotHasKey(0, $locked);
    }

    public function testGetBottomLockedIndexesHaltsOnClinchedTeam(): void
    {
        $view = new StandingsRowView();

        // Index 1 is eliminated on arithmetic, but the cascade stops at clinched index 2 first.
        $standings = [
            $this->makeRow(wins: 50, gamesUnplayed: 5),
            $this->makeRow(wins: 10, gamesUnplayed: 0),
            $this->makeRow(playoffs: 1, wins: 20, gamesUnplayed: 5),
        ];

        $this->assertSame([], $view->getBottomLockedIndexes($standings));
    }

    public function testGetBottomLockedIndexesHaltsWhenCatchable(): void
    {
        $view = new StandingsRowView();

        // Index 2 can catch index 1 (20 + 15 = 35 >= 30), so the eliminated index 1 stays unlocked.
        $standings = [
            $this->makeRow(wins: 50, gamesUnplayed: 5),
            $this->makeRow(wins: 30, gamesUnplayed: 0),
            $this->makeRow(wins: 20, gamesUnplayed: 15),
        ];

        $this->assertSame([], $view->getBottomLockedIndexes($standings));
    }

    public function testGetBottomLockedIndexesSingleTeam(): void
    {
        $view = new StandingsRowView();

        $this->assertSame([], $view->getBottomLockedIndexes([$this->makeRow(gamesUnplayed: 5)]));
        $this->assertSame([0 => true], $view->getBottomLockedIndexes([$this->makeRow(gamesUnplayed: 0)]));
        $this->assertSame([], $view->getBottomLockedIndexes([$this->makeRow(playoffs: 1, gamesUnplayed: 0)]));
    }

    public function testFormatTeamNameWithoutFlagsReturnsEscapedNameOnly(): void
    {
        $view = new StandingsRowView();

        $result = $view->formatTeamName($this->makeRow());

        $this->assertSame('Safe Team', $result);
        $this->assertStringNotContainsString('ibl-clinched-indicator', $result);
    }

    public function testFormatTeamNamePrefixesIndicatorByPrecedence(): void
    {
        $view = new StandingsRowView();

        $this->assertSame(
            '<span class="ibl-clinched-indicator">W</span>-Safe Team',
            $view->formatTeamName($this->makeRow(league: 1, conference: 1, division: 1, playoffs: 1))
        );
        $this->assertSame(
            '<span class="ibl-clinched-indicator">Z</span>-Safe Team',
            $view->formatTeamName($this->makeRow(conference: 1, division: 1, playoffs: 1))
        );
        $this->assertSame(
            '<span class="ibl-clinched-indicator">Y</span>-Safe Team',
            $view->formatTeamName($this->makeRow(division: 1, playoffs: 1))
        );
        $this->assertSame(
            '<span class="ibl-clinched-indicator">X</span>-Safe Team',
            $view->formatTeamName($this->makeRow(playoffs: 1))
        );
    }

    public function testFormatTeamNameEscapesAngleBracket(): void
    {
        $view = new StandingsRowView();

        $result = $view->formatTeamName($this->makeRow(playoffs: 1, teamName: 'Team <b>'));

        $this->assertSame('<span class="ibl-clinched-indicator">X</span>-Team &lt;b&gt;', $result);
        $this->assertStringNotContainsString('<b>', $result);
    }
}
