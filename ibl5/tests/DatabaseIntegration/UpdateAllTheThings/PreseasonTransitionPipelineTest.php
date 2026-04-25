<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\UpdateAllTheThings;

class PreseasonTransitionPipelineTest extends PipelineIntegrationTestCase
{
    public function testFirstRegularSeasonSimCleansPreseasonData(): void
    {
        $this->updateSetting('Current Season Phase', 'Regular Season');
        $this->updateSetting('Current Season Ending Year', '2099');
        $this->seedSimDates(1, '2098-10-15', '2098-10-20');
        $this->seedLeagueConfig(2099);

        $this->insertTeamBoxscoreRow('2098-12-28', 'PreGame1', 1, 5, 6);
        $this->insertTeamBoxscoreRow('2098-12-29', 'PreGame2', 1, 7, 8);

        $this->insertPlayerBoxscoreRow('2098-12-28', 1, 'Test Player One', 'PG', 5, 6, 5);
        $this->insertPlayerBoxscoreRow('2098-12-29', 2, 'Test Player Two', 'SF', 7, 8, 7);

        $this->insertRow('ibl_team_awards', [
            'year' => 2099,
            'name' => 'Stars',
            'award' => 'Preseason Test Award',
        ]);
        $this->insertRow('ibl_jsb_history', [
            'season_year' => 2099,
            'team_name' => 'Pipeline Test Team',
            'teamid' => 99,
            'wins' => 10,
            'losses' => 5,
        ]);
        $this->insertRow('ibl_jsb_transactions', [
            'season_year' => 2099,
            'transaction_month' => 11,
            'transaction_day' => 5,
            'transaction_type' => 1,
            'pid' => 1,
            'player_name' => 'Test Player One',
            'from_teamid' => 1,
            'to_teamid' => 2,
        ]);

        $season = $this->buildSeason('Regular Season', 2099);

        $schPath = $this->buildSchFile([
            ['date_slot' => 103, 'game_index' => 0, 'visitor' => 1, 'home' => 2, 'visitor_score' => 105, 'home_score' => 98],
            ['date_slot' => 103, 'game_index' => 1, 'visitor' => 3, 'home' => 4, 'visitor_score' => 110, 'home_score' => 102],
        ]);
        $plrPath = $this->buildPlrFile([
            ['pid' => 200001, 'name' => 'Pipeline Player A', 'teamid' => 1, 'ordinal' => 1],
            ['pid' => 200002, 'name' => 'Pipeline Player B', 'teamid' => 2, 'ordinal' => 2],
        ]);
        $scoPath = $this->buildScoFile();

        $pipeline = $this->buildPipeline($season, $schPath, $plrPath, $scoPath);
        $results = $this->runPipeline($pipeline);

        $this->assertZeroPipelineErrors($pipeline, $results);

        $cleanupResult = $this->findResultByLabel($results, 'Preseason data cleaned');
        self::assertNotNull($cleanupResult, 'CleanupPreseasonDataStep should have run');
        self::assertStringContainsString('Cleaned:', $cleanupResult->detail);

        self::assertSame(0, $this->countRows('ibl_box_scores_teams', "game_date BETWEEN '2098-11-01' AND '2098-12-31'"));
        self::assertSame(0, $this->countRows('ibl_box_scores', "game_date BETWEEN '2098-11-01' AND '2098-12-31'"));
        self::assertSame(0, $this->countRows('ibl_team_awards', "year = 2099"));
        self::assertSame(0, $this->countRows('ibl_jsb_history', "season_year = 2099"));
        self::assertSame(0, $this->countRows('ibl_jsb_transactions', "season_year = 2099"));

        self::assertSame(2, $this->countRows('ibl_schedule', "game_date LIKE '2099-01-%'"));
    }
}
