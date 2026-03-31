<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use RecordHolders\RecordHoldersRepository;

class RecordHoldersRepositoryTest extends DatabaseTestCase
{
    private RecordHoldersRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new RecordHoldersRepository($this->db);
    }

    // --- Quadruple Doubles ---

    public function testGetQuadrupleDoublesReturnsArrayStructure(): void
    {
        $result = $this->repo->getQuadrupleDoubles();

        self::assertIsArray($result);
        // If production DB has quad doubles, verify structure; otherwise empty is fine
        if ($result !== []) {
            $first = $result[0];
            self::assertArrayHasKey('pid', $first);
            self::assertArrayHasKey('name', $first);
            self::assertArrayHasKey('points', $first);
            self::assertArrayHasKey('rebounds', $first);
            self::assertArrayHasKey('assists', $first);
        }
    }

    public function testGetQuadrupleDoublesFindsQualifyingGame(): void
    {
        $pid = 200090401;
        $this->insertTestPlayer($pid, 'QuadDouble Test');

        // Hist row for season year 2098 (Jan date → season_year = 2098)
        $this->insertRow('ibl_hist_archive', [
            'pid' => $pid,
            'name' => 'QuadDouble Test',
            'year' => 2098,
            'team' => 'Metros',
            'teamid' => 1,
            'games' => 50,
            'minutes' => 1600,
            'fgm' => 300,
            'fga' => 600,
            'ftm' => 100,
            'fta' => 120,
            'tgm' => 50,
            'tga' => 130,
            'orb' => 40,
            'reb' => 200,
            'ast' => 150,
            'stl' => 50,
            'blk' => 20,
            'tvr' => 80,
            'pf' => 100,
            'pts' => 750,
            'salary' => 1500,
        ]);

        // calc_points = 7*2 + 3 + 2*3 = 23 ≥ 10 ✓
        // calc_rebounds = 5 + 5 = 10 ≥ 10 ✓
        // gameAST = 10 ≥ 10 ✓
        // gameSTL = 10 ≥ 10 ✓  → 4 categories ≥ 10
        $this->insertPlayerBoxscoreRow(
            '2098-01-15', $pid, 'QuadDouble Test', 'PG', 2, 1, 1,
            minutes: 40,
            points2m: 7, points2a: 14,
            ftm: 3, fta: 4,
            points3m: 2, points3a: 5,
            orb: 5, drb: 5,
            ast: 10, stl: 10, blk: 10,
            tov: 2, pf: 2,
        );

        $result = $this->repo->getQuadrupleDoubles();

        $found = false;
        foreach ($result as $record) {
            if ($record['pid'] === $pid && $record['date'] === '2098-01-15') {
                $found = true;
                self::assertSame('QuadDouble Test', $record['name']);
                self::assertGreaterThanOrEqual(10, $record['rebounds']);
                self::assertGreaterThanOrEqual(10, $record['assists']);
                self::assertGreaterThanOrEqual(10, $record['steals']);
                self::assertGreaterThanOrEqual(10, $record['blocks']);
                break;
            }
        }
        self::assertTrue($found, 'Quadruple double record not found');
    }

    // --- All-Star Appearances ---

    public function testGetMostAllStarAppearancesReturnsRows(): void
    {
        // Insert unique all-star awards
        $this->insertRow('ibl_awards', ['year' => 2096, 'Award' => 'Eastern Conference All-Star', 'name' => 'AllStar Test']);
        $this->insertRow('ibl_awards', ['year' => 2097, 'Award' => 'Eastern Conference All-Star', 'name' => 'AllStar Test']);
        $this->insertRow('ibl_awards', ['year' => 2098, 'Award' => 'Eastern Conference All-Star', 'name' => 'AllStar Test']);

        $result = $this->repo->getMostAllStarAppearances();

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('name', $first);
        self::assertArrayHasKey('appearances', $first);
        self::assertGreaterThanOrEqual(1, $first['appearances']);
    }

    // --- Team Half Scores ---

    public function testGetTopTeamHalfScoreFirstHalfDesc(): void
    {
        $this->insertTeamBoxscoreRow('2098-01-15', 'Metros', 1, 2, 1);

        $result = $this->repo->getTopTeamHalfScore('first', 'DESC');

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('tid', $first);
        self::assertArrayHasKey('team_name', $first);
        self::assertArrayHasKey('value', $first);
        self::assertGreaterThan(0, $first['value']);
    }

    public function testGetTopTeamHalfScoreSecondHalfAsc(): void
    {
        $this->insertTeamBoxscoreRow('2098-01-15', 'Metros', 1, 2, 1);

        $result = $this->repo->getTopTeamHalfScore('second', 'ASC');

        self::assertNotEmpty($result);
        self::assertArrayHasKey('value', $result[0]);
    }

    // --- Largest Margin of Victory ---

    public function testGetLargestMarginOfVictoryReturnsRows(): void
    {
        // Insert team boxscores with different scores → non-zero margin
        $this->insertTeamBoxscoreRow('2098-01-15', 'Metros', 1, 2, 1);
        $this->insertTeamBoxscoreRow('2098-01-15', 'Sharks', 1, 2, 1);

        $result = $this->repo->getLargestMarginOfVictory('1=1');

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('winner_name', $first);
        self::assertArrayHasKey('loser_name', $first);
        self::assertArrayHasKey('margin', $first);
        self::assertGreaterThan(0, $first['margin']);
    }

    // --- Best/Worst Season Record ---

    public function testGetBestWorstSeasonRecordDesc(): void
    {
        $this->insertTeamBoxscoreRow('2098-01-15', 'Metros', 1, 2, 1);
        $this->insertTeamBoxscoreRow('2098-01-15', 'Sharks', 1, 2, 1);

        $result = $this->repo->getBestWorstSeasonRecord('DESC');

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('team_name', $first);
        self::assertArrayHasKey('year', $first);
        self::assertArrayHasKey('wins', $first);
        self::assertArrayHasKey('losses', $first);
    }

    public function testGetBestWorstSeasonRecordAsc(): void
    {
        $this->insertTeamBoxscoreRow('2098-01-15', 'Metros', 1, 2, 1);
        $this->insertTeamBoxscoreRow('2098-01-15', 'Sharks', 1, 2, 1);

        $result = $this->repo->getBestWorstSeasonRecord('ASC');

        self::assertNotEmpty($result);
    }

    // --- Longest Streak ---

    /**
     * Insert team boxscore pair (both team entries) for a single game.
     */
    private function insertGamePair(string $date, int $homeScore, int $visitorScore): void
    {
        $homeQ = (int) ($homeScore / 4);
        $visitorQ = (int) ($visitorScore / 4);

        foreach (['Metros', 'Sharks'] as $name) {
            $this->insertRow('ibl_box_scores_teams', [
                'Date' => $date,
                'name' => $name,
                'gameOfThatDay' => 1,
                'visitorTeamID' => 2,
                'homeTeamID' => 1,
                'attendance' => 10000,
                'capacity' => 15000,
                'visitorWins' => 0,
                'visitorLosses' => 0,
                'homeWins' => 0,
                'homeLosses' => 0,
                'visitorQ1points' => $visitorQ,
                'visitorQ2points' => $visitorQ,
                'visitorQ3points' => $visitorQ,
                'visitorQ4points' => $visitorScore - 3 * $visitorQ,
                'visitorOTpoints' => 0,
                'homeQ1points' => $homeQ,
                'homeQ2points' => $homeQ,
                'homeQ3points' => $homeQ,
                'homeQ4points' => $homeScore - 3 * $homeQ,
                'homeOTpoints' => 0,
                'game2GM' => 30,
                'game2GA' => 60,
                'gameFTM' => 15,
                'gameFTA' => 20,
                'game3GM' => 8,
                'game3GA' => 22,
                'gameORB' => 10,
                'gameDRB' => 30,
                'gameAST' => 20,
                'gameSTL' => 8,
                'gameTOV' => 12,
                'gameBLK' => 5,
                'gamePF' => 18,
            ]);
        }
    }

    public function testGetLongestStreakWinning(): void
    {
        // Metros (home=1) win 3 consecutive games
        $this->insertGamePair('2098-01-15', 104, 80);
        $this->insertGamePair('2098-01-16', 110, 85);
        $this->insertGamePair('2098-01-17', 100, 90);

        $result = $this->repo->getLongestStreak('winning');

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('team_name', $first);
        self::assertArrayHasKey('streak', $first);
        self::assertGreaterThanOrEqual(3, $first['streak']);
    }

    public function testGetLongestStreakLosing(): void
    {
        // Sharks (visitor=2) lose 3 consecutive games
        $this->insertGamePair('2098-01-15', 104, 80);
        $this->insertGamePair('2098-01-16', 110, 85);
        $this->insertGamePair('2098-01-17', 100, 90);

        $result = $this->repo->getLongestStreak('losing');

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('streak', $first);
        self::assertGreaterThanOrEqual(3, $first['streak']);
    }

    // --- Best/Worst Season Start ---

    public function testGetBestWorstSeasonStartBest(): void
    {
        $this->insertGamePair('2098-01-15', 120, 40);
        $this->insertGamePair('2098-01-16', 120, 40);
        $this->insertGamePair('2098-01-17', 120, 40);

        $result = $this->repo->getBestWorstSeasonStart('best');

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('team_name', $first);
        self::assertArrayHasKey('wins', $first);
        self::assertGreaterThanOrEqual(3, $first['wins']);
    }

    public function testGetBestWorstSeasonStartWorst(): void
    {
        $this->insertGamePair('2098-01-15', 120, 40);
        $this->insertGamePair('2098-01-16', 120, 40);
        $this->insertGamePair('2098-01-17', 120, 40);

        $result = $this->repo->getBestWorstSeasonStart('worst');

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('losses', $first);
        self::assertGreaterThanOrEqual(3, $first['losses']);
    }

    // --- Player Single Game Batch ---

    public function testGetTopPlayerSingleGameBatchReturnsGroupedResults(): void
    {
        $pid = 200090402;
        $this->insertTestPlayer($pid, 'BatchPlayer Test');

        $this->insertRow('ibl_hist_archive', [
            'pid' => $pid,
            'name' => 'BatchPlayer Test',
            'year' => 2098,
            'team' => 'Metros',
            'teamid' => 1,
            'games' => 50,
            'minutes' => 1600,
            'fgm' => 300,
            'fga' => 600,
            'ftm' => 100,
            'fta' => 120,
            'tgm' => 50,
            'tga' => 130,
            'orb' => 40,
            'reb' => 200,
            'ast' => 150,
            'stl' => 50,
            'blk' => 20,
            'tvr' => 80,
            'pf' => 100,
            'pts' => 750,
            'salary' => 1500,
        ]);

        $this->insertPlayerBoxscoreRow(
            '2098-01-15', $pid, 'BatchPlayer Test', 'PG', 2, 1, 1,
            minutes: 35, ast: 8, stl: 3,
        );

        $result = $this->repo->getTopPlayerSingleGameBatch(
            ['Points' => 'bs.calc_points', 'Assists' => 'bs.gameAST'],
            '1=1'
        );

        self::assertArrayHasKey('Points', $result);
        self::assertArrayHasKey('Assists', $result);
        self::assertNotEmpty($result['Points']);
        self::assertNotEmpty($result['Assists']);

        $pointsRecord = $result['Points'][0];
        self::assertArrayHasKey('pid', $pointsRecord);
        self::assertArrayHasKey('name', $pointsRecord);
        self::assertArrayHasKey('value', $pointsRecord);
    }

    // --- Team Single Game Batch ---

    public function testGetTopTeamSingleGameBatchReturnsGroupedResults(): void
    {
        $this->insertTeamBoxscoreRow('2098-01-15', 'Metros', 1, 2, 1);

        $result = $this->repo->getTopTeamSingleGameBatch(
            [
                'Points' => ['expression' => 'bs.game2GM * 2 + bs.gameFTM + bs.game3GM * 3', 'order' => 'DESC'],
                'Assists' => ['expression' => 'bs.gameAST', 'order' => 'DESC'],
            ],
            '1=1'
        );

        self::assertArrayHasKey('Points', $result);
        self::assertArrayHasKey('Assists', $result);
        self::assertNotEmpty($result['Points']);
    }

    // --- Top Season Average Batch ---

    public function testGetTopSeasonAverageBatchReturnsRows(): void
    {
        $result = $this->repo->getTopSeasonAverageBatch(
            ['PPG' => ['statColumn' => 'pts', 'gamesColumn' => 'games']],
            minGames: 10
        );

        self::assertArrayHasKey('PPG', $result);
        self::assertNotEmpty($result['PPG']);
        $first = $result['PPG'][0];
        self::assertArrayHasKey('pid', $first);
        self::assertArrayHasKey('name', $first);
        self::assertArrayHasKey('value', $first);
    }

    // --- Most Titles ---

    public function testGetMostTitlesByTypeReturnsRows(): void
    {
        // Insert a unique team award
        $this->insertRow('ibl_team_awards', [
            'year' => 2096,
            'name' => 'Metros',
            'Award' => 'Test Title 2096',
        ]);
        $this->insertRow('ibl_team_awards', [
            'year' => 2097,
            'name' => 'Metros',
            'Award' => 'Test Title 2097',
        ]);

        $result = $this->repo->getMostTitlesByType('Test Title');

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('team_name', $first);
        self::assertArrayHasKey('count', $first);
        self::assertArrayHasKey('years', $first);
    }

    // --- Announcement Cache ---

    public function testGetLastAnnouncedDateReturnsNullableString(): void
    {
        $result = $this->repo->getLastAnnouncedDate();

        // Returns null if no cache entry, or a date string if one exists
        if ($result !== null) {
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result);
        } else {
            self::assertNull($result);
        }
    }

    public function testMarkAnnouncementsProcessedAndRetrieve(): void
    {
        $this->repo->markAnnouncementsProcessed('2098-01-15');

        $result = $this->repo->getLastAnnouncedDate();

        self::assertSame('2098-01-15', $result);
    }

    // --- Unannounced Game Dates ---

    public function testGetUnannouncedGameDatesReturnsDateList(): void
    {
        $pid = 200090410;
        $this->insertTestPlayer($pid, 'UnannouncedTest');

        // Insert sim date range
        $this->insertRow('ibl_sim_dates', [
            'Sim' => 90001,
            'Start Date' => '2098-01-10',
            'End Date' => '2098-01-20',
        ]);

        // Insert boxscore within the sim date range
        $this->insertPlayerBoxscoreRow(
            '2098-01-15', $pid, 'UnannouncedTest', 'PG', 2, 1, 1,
            overrides: ['uuid' => 'rh-unan-0000-0000-000000000001'],
        );

        $result = $this->repo->getUnannouncedGameDates(null);

        self::assertIsArray($result);
        self::assertContains('2098-01-15', $result);
    }

    public function testGetUnannouncedGameDatesRespectsLastAnnouncedDate(): void
    {
        $this->insertRow('ibl_sim_dates', [
            'Sim' => 90002,
            'Start Date' => '2098-02-10',
            'End Date' => '2098-02-20',
        ]);

        $pid = 200090403;
        $this->insertTestPlayer($pid, 'Unannounced Test', ['pos' => 'SG', 'ordinal' => 2]);

        $this->insertPlayerBoxscoreRow(
            '2098-02-12', $pid, 'Unannounced Test', 'SG', 2, 1, 1,
            overrides: ['uuid' => 'rh-unan-bs12-0000-000000000001'],
        );
        $this->insertPlayerBoxscoreRow(
            '2098-02-18', $pid, 'Unannounced Test', 'SG', 2, 1, 1,
            overrides: ['uuid' => 'rh-unan-bs18-0000-000000000001'],
        );

        // With last announced = 2098-02-15, only dates after that should be returned
        $result = $this->repo->getUnannouncedGameDates('2098-02-15');

        self::assertNotContains('2098-02-12', $result);
        self::assertContains('2098-02-18', $result);
    }

    // --- Most Playoff Appearances ---

    public function testGetMostPlayoffAppearancesReturnsRows(): void
    {
        // Insert playoff boxscores (month=6 → game_type=2)
        for ($day = 1; $day <= 4; $day++) {
            $date = sprintf('2098-06-%02d', $day);
            $this->insertGamePair($date, 113, 73);
        }

        $result = $this->repo->getMostPlayoffAppearances();

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertArrayHasKey('team_name', $first);
        self::assertArrayHasKey('count', $first);
        self::assertArrayHasKey('years', $first);
    }
}
