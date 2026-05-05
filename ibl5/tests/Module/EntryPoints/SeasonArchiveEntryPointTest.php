<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class SeasonArchiveEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->setMockData([]);
    }

    public function testNoYearShowsSeasonIndex(): void
    {
        $output = $this->runModule('SeasonArchive');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_awards');
    }

    public function testValidYearShowsDetail(): void
    {
        $this->mockDb->onQuery('ibl_awards', [
            ['year' => 2020, 'award' => 'MVP', 'name' => 'Test Player', 'table_id' => 1],
        ]);
        $this->mockDb->onQuery('vw_playoff_series_results', []);
        $this->mockDb->onQuery('ibl_team_awards', []);
        $this->mockDb->onQuery('ibl_box_scores_teams', []);
        $this->mockDb->onQuery('ibl_gm_awards', []);
        $this->mockDb->onQuery('ibl_gm_tenures', []);
        $this->mockDb->onQuery('ibl_heat_win_loss', []);

        $output = $this->runModule('SeasonArchive', ['year' => '2020']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_awards');
    }

    public function testInvalidYearStringCastsToZeroAndShowsIndex(): void
    {
        $output = $this->runModule('SeasonArchive', ['year' => 'garbage']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_awards');
    }

    public function testYearWithNoMatchingSeasonRendersFallback(): void
    {
        $output = $this->runModule('SeasonArchive', ['year' => '1800']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_awards');
    }
}
