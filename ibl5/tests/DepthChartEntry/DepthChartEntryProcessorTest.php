<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use PHPUnit\Framework\TestCase;
use DepthChartEntry\DepthChartEntryProcessor;
use DepthChartEntry\DepthChartEntryView;

/**
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 * @phpstan-type PlayerRowOverrides array{pid?: int, name?: string, nickname?: string|null, age?: int|null, teamid?: int, teamname?: string|null, pos?: string, stamina?: int|null, exp?: int|null, bird?: int|null, cy?: int|null, cyt?: int|null, salary_yr1?: int|null, salary_yr2?: int|null, salary_yr3?: int|null, salary_yr4?: int|null, salary_yr5?: int|null, salary_yr6?: int|null, ordinal?: int|null, injured?: int|null, retired?: int|null, droptime?: int|null, stats_gs?: int|null, stats_gm?: int|null, stats_min?: int|null, stats_fgm?: int|null, stats_fga?: int|null, stats_ftm?: int|null, stats_fta?: int|null, stats_3gm?: int|null, stats_3ga?: int|null, stats_orb?: int|null, stats_drb?: int|null, stats_ast?: int|null, stats_stl?: int|null, stats_tvr?: int|null, stats_blk?: int|null, stats_pf?: int|null, sh_pts?: int|null, sh_reb?: int|null, sh_ast?: int|null, sh_stl?: int|null, sh_blk?: int|null, s_dd?: int|null, s_td?: int|null, sp_pts?: int|null, sp_reb?: int|null, sp_ast?: int|null, sp_stl?: int|null, sp_blk?: int|null, ch_pts?: int|null, ch_reb?: int|null, ch_ast?: int|null, ch_stl?: int|null, ch_blk?: int|null, c_dd?: int|null, c_td?: int|null, cp_pts?: int|null, cp_reb?: int|null, cp_ast?: int|null, cp_stl?: int|null, cp_blk?: int|null, car_gm?: int|null, car_min?: int|null, car_fgm?: int|null, car_fga?: int|null, car_ftm?: int|null, car_fta?: int|null, car_3gm?: int|null, car_3ga?: int|null, car_orb?: int|null, car_drb?: int|null, car_reb?: int|null, car_ast?: int|null, car_stl?: int|null, car_tvr?: int|null, car_blk?: int|null, car_pf?: int|null, r_fga?: int|null, r_fgp?: int|null, r_fta?: int|null, r_ftp?: int|null, r_3ga?: int|null, r_3gp?: int|null, r_orb?: int|null, r_drb?: int|null, r_ast?: int|null, r_stl?: int|null, r_tvr?: int|null, r_blk?: int|null, r_foul?: int|null, oo?: int|null, od?: int|null, r_drive_off?: int|null, dd?: int|null, po?: int|null, pd?: int|null, r_trans_off?: int|null, td?: int|null, clutch?: int|null, consistency?: int|null, talent?: int|null, skill?: int|null, intangibles?: int|null, loyalty?: int|null, playing_time?: int|null, winner?: int|null, tradition?: int|null, security?: int|null, draftround?: int|null, draftedby?: string|null, draftedbycurrentname?: string|null, draftyear?: int|null, draftpickno?: int|null, htft?: int|null, htin?: int|null, wt?: int|null, college?: string|null, dc_pg_depth?: int|null, dc_sg_depth?: int|null, dc_sf_depth?: int|null, dc_pf_depth?: int|null, dc_c_depth?: int|null, dc_can_play_in_game?: int|null, dc_minutes?: int|null, dc_of?: int|null, dc_df?: int|null, dc_oi?: int|null, dc_di?: int|null, dc_bh?: int|null}
 */
class DepthChartEntryProcessorTest extends TestCase
{
    private DepthChartEntryProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new DepthChartEntryProcessor();
    }

    /**
     * Build a complete PlayerRow for renderPlayerRow() tests.
     * All nullable fields default to null; supply only the keys you care about.
     *
     * @param PlayerRowOverrides $overrides
     * @return PlayerRow
     */
    private static function buildPlayerRow(array $overrides = []): array
    {
        return array_merge([
            'pid' => 1,
            'name' => 'Test Player',
            'nickname' => null,
            'age' => null,
            'teamid' => 1,
            'teamname' => null,
            'pos' => 'PG',
            'stamina' => null,
            'exp' => null,
            'bird' => null,
            'cy' => null,
            'cyt' => null,
            'salary_yr1' => null,
            'salary_yr2' => null,
            'salary_yr3' => null,
            'salary_yr4' => null,
            'salary_yr5' => null,
            'salary_yr6' => null,
            'ordinal' => null,
            'injured' => null,
            'retired' => null,
            'droptime' => null,
            'stats_gs' => null,
            'stats_gm' => null,
            'stats_min' => null,
            'stats_fgm' => null,
            'stats_fga' => null,
            'stats_ftm' => null,
            'stats_fta' => null,
            'stats_3gm' => null,
            'stats_3ga' => null,
            'stats_orb' => null,
            'stats_drb' => null,
            'stats_ast' => null,
            'stats_stl' => null,
            'stats_tvr' => null,
            'stats_blk' => null,
            'stats_pf' => null,
            'sh_pts' => null,
            'sh_reb' => null,
            'sh_ast' => null,
            'sh_stl' => null,
            'sh_blk' => null,
            's_dd' => null,
            's_td' => null,
            'sp_pts' => null,
            'sp_reb' => null,
            'sp_ast' => null,
            'sp_stl' => null,
            'sp_blk' => null,
            'ch_pts' => null,
            'ch_reb' => null,
            'ch_ast' => null,
            'ch_stl' => null,
            'ch_blk' => null,
            'c_dd' => null,
            'c_td' => null,
            'cp_pts' => null,
            'cp_reb' => null,
            'cp_ast' => null,
            'cp_stl' => null,
            'cp_blk' => null,
            'car_gm' => null,
            'car_min' => null,
            'car_fgm' => null,
            'car_fga' => null,
            'car_ftm' => null,
            'car_fta' => null,
            'car_3gm' => null,
            'car_3ga' => null,
            'car_orb' => null,
            'car_drb' => null,
            'car_reb' => null,
            'car_ast' => null,
            'car_stl' => null,
            'car_tvr' => null,
            'car_blk' => null,
            'car_pf' => null,
            'r_fga' => null,
            'r_fgp' => null,
            'r_fta' => null,
            'r_ftp' => null,
            'r_3ga' => null,
            'r_3gp' => null,
            'r_orb' => null,
            'r_drb' => null,
            'r_ast' => null,
            'r_stl' => null,
            'r_tvr' => null,
            'r_blk' => null,
            'r_foul' => null,
            'oo' => null,
            'od' => null,
            'r_drive_off' => null,
            'dd' => null,
            'po' => null,
            'pd' => null,
            'r_trans_off' => null,
            'td' => null,
            'clutch' => null,
            'consistency' => null,
            'talent' => null,
            'skill' => null,
            'intangibles' => null,
            'loyalty' => null,
            'playing_time' => null,
            'winner' => null,
            'tradition' => null,
            'security' => null,
            'draftround' => null,
            'draftedby' => null,
            'draftedbycurrentname' => null,
            'draftyear' => null,
            'draftpickno' => null,
            'htft' => null,
            'htin' => null,
            'wt' => null,
            'college' => null,
            'dc_pg_depth' => null,
            'dc_sg_depth' => null,
            'dc_sf_depth' => null,
            'dc_pf_depth' => null,
            'dc_c_depth' => null,
            'dc_can_play_in_game' => null,
            'dc_minutes' => null,
            'dc_of' => null,
            'dc_df' => null,
            'dc_oi' => null,
            'dc_di' => null,
            'dc_bh' => null,
        ], $overrides);
    }

    public function testProcessesSubmissionCorrectly(): void
    {
        $postData = [
            'Name1' => 'Player One',
            'pg1' => '1',
            'sg1' => '0',
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'canPlayInGame1' => '1',
            'min1' => '30',
            'Injury1' => '0',
            'Name2' => 'Player Two',
            'pg2' => '2',
            'sg2' => '0',
            'sf2' => '0',
            'pf2' => '0',
            'c2' => '0',
            'canPlayInGame2' => '1',
            'min2' => '25',
            'Injury2' => '0'
        ];

        $result = $this->processor->processSubmission($postData, 15);

        $this->assertEquals(2, count($result['playerData']));
        $this->assertSame(2, $result['activePlayers']);
        $this->assertSame(2, $result['pos_1']);  // Both players have pg > 0
        $this->assertFalse($result['hasStarterAtMultiplePositions']);
        // Role slots are hardcoded to 0
        $this->assertSame(0, $result['playerData'][0]['of']);
        $this->assertSame(0, $result['playerData'][0]['df']);
        $this->assertSame(0, $result['playerData'][0]['oi']);
        $this->assertSame(0, $result['playerData'][0]['di']);
        $this->assertSame(0, $result['playerData'][0]['bh']);
    }
    
    public function testDetectsMultipleStartingPositions(): void
    {
        $postData = [
            'Name1' => 'Player One',
            'pg1' => '1',  // Starting at PG
            'sg1' => '1',  // Also starting at SG - INVALID
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'canPlayInGame1' => '1',
            'min1' => '30',
            'Injury1' => '0'
        ];

        $result = $this->processor->processSubmission($postData, 15);

        $this->assertTrue($result['hasStarterAtMultiplePositions']);
        $this->assertSame('Player One', $result['nameOfProblemStarter']);
    }
    
    public function testExcludesInjuredPlayersFromPositionCount(): void
    {
        $postData = [
            'Name1' => 'Player One',
            'pg1' => '1',
            'sg1' => '0',
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'canPlayInGame1' => '1',
            'min1' => '30',
            'Injury1' => '15'  // Injured
        ];

        $result = $this->processor->processSubmission($postData, 15);

        $this->assertSame(0, $result['pos_1']);  // Injured player not counted
        $this->assertSame(1, $result['activePlayers']);  // Still counts as active
    }
    
    public function testGeneratesCsvContentCorrectly(): void
    {
        $playerData = [
            [
                'name' => 'Player One',
                'pg' => 1,
                'sg' => 0,
                'sf' => 0,
                'pf' => 0,
                'c' => 0,
                'canPlayInGame' => 1,
                'min' => 30,
                'of' => 0,
                'df' => 0,
                'oi' => 0,
                'di' => 0,
                'bh' => 0,
                'injury' => 0,
            ]
        ];

        $csv = $this->processor->generateCsvContent($playerData);

        $this->assertStringContainsString('Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI,BH', $csv);
        $this->assertStringContainsString('Player One,1,0,0,0,0,1,30,0,0,0,0,0', $csv);
    }
    

    
    public function testSanitizesInputWithMaliciousData(): void
    {
        $postData = [
            'Name1' => '<script>alert("XSS")</script>Player One',
            'pg1' => '10',  // Out of range (should be capped at 5)
            'sg1' => '-5',  // Negative (should be 0)
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'canPlayInGame1' => '2',  // Invalid (should be 0 or 1)
            'min1' => '100',  // Out of range (should be capped at 40)
            'Injury1' => '0'
        ];

        $result = $this->processor->processSubmission($postData, 15);

        // Player name should have script tags removed (but not the content)
        $this->assertStringNotContainsString('<script>', $result['playerData'][0]['name']);
        $this->assertStringNotContainsString('</script>', $result['playerData'][0]['name']);

        // Depth values should be capped at 5
        $this->assertSame(5, $result['playerData'][0]['pg']);
        $this->assertSame(0, $result['playerData'][0]['sg']);

        // Active should be 0 (invalid value)
        $this->assertSame(0, $result['playerData'][0]['canPlayInGame']);

        // Minutes should be capped at 40
        $this->assertSame(40, $result['playerData'][0]['min']);

        // Role slots are hardcoded to 0 regardless of input
        $this->assertSame(0, $result['playerData'][0]['of']);
        $this->assertSame(0, $result['playerData'][0]['df']);
        $this->assertSame(0, $result['playerData'][0]['oi']);
        $this->assertSame(0, $result['playerData'][0]['di']);
        $this->assertSame(0, $result['playerData'][0]['bh']);
    }
    
    public function testHandlesMissingOptionalFields(): void
    {
        $postData = [
            'Name1' => 'Player One',
            'pg1' => '1',
            'sg1' => '0',
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'canPlayInGame1' => '1',
            'min1' => '30',
            // Injury1 is missing
        ];

        $result = $this->processor->processSubmission($postData, 15);

        $this->assertEquals(1, count($result['playerData']));
        $this->assertSame('Player One', $result['playerData'][0]['name']);
        $this->assertSame(0, $result['playerData'][0]['injury']);
    }
    
    public function testBlankMinutesSubmissionIsConvertedToZero(): void
    {
        // A blank <input type="number"> POSTs as an empty string. The processor
        // must coerce this to 0 so the depth chart writes a numeric value
        // rather than null/empty. The reset button intentionally leaves the
        // minutes field blank, so this code path matters in normal use.
        $postData = [
            'Name1' => 'Player One',
            'pg1' => '0',
            'sg1' => '0',
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'canPlayInGame1' => '1',
            'min1' => '',  // blank minutes — reset state
            'Injury1' => '0'
        ];

        $result = $this->processor->processSubmission($postData, 15);

        $this->assertSame(0, $result['playerData'][0]['min']);
    }

    public function testGeneratesCsvWithSpecialCharacters(): void
    {
        $playerData = [
            [
                'name' => 'Player, Jr.',
                'pg' => 1,
                'sg' => 0,
                'sf' => 0,
                'pf' => 0,
                'c' => 0,
                'canPlayInGame' => 1,
                'min' => 30,
                'of' => 0,
                'df' => 0,
                'oi' => 0,
                'di' => 0,
                'bh' => 0,
                'injury' => 0,
            ]
        ];

        $csv = $this->processor->generateCsvContent($playerData);

        $this->assertStringContainsString('Player, Jr.', $csv);
        $this->assertStringContainsString('1,0,0,0,0,1,30,0,0,0,0,0', $csv);
    }
    
    public function testCountsAllPositionTypesCorrectly(): void
    {
        $postData = [
            'Name1' => 'Player One',
            'pg1' => '1',
            'sg1' => '2',
            'sf1' => '3',
            'pf1' => '4',
            'c1' => '5',
            'canPlayInGame1' => '1',
            'min1' => '30',
            'Injury1' => '0'
        ];

        $result = $this->processor->processSubmission($postData, 15);

        // Position depth counting restored — counts non-injured players with depth > 0
        $this->assertSame(1, $result['pos_1']);
        $this->assertSame(1, $result['pos_2']);
        $this->assertSame(1, $result['pos_3']);
        $this->assertSame(1, $result['pos_4']);
        $this->assertSame(1, $result['pos_5']);
    }

    // --- Merged from DepthChartEntryDataConsistencyTest ---

    /**
     * Tests data consistency for position depth fields (pg, sg, sf, pf, c)
     * Tests DepthChartEntryProcessor and DepthChartEntryView together.
     */
    public function testFormDisplaysCorrectDatabaseValuesForAllSettings(): void
    {
        $view = new DepthChartEntryView(self::createStub(\League\LeagueContext::class), new \DepthChartEntry\DepthChartEntryService());

        // Simulate a player record from the database with various depth values
        $playerFromDb = self::buildPlayerRow([
            'name' => 'Test Player',
            'pos' => 'PG',
            'injured' => 0,
            'stamina' => 50,
            'dc_pg_depth' => 1,
            'dc_sg_depth' => 3,
            'dc_sf_depth' => 0,
            'dc_pf_depth' => 2,
            'dc_c_depth' => 0,
            'dc_can_play_in_game' => 1,
            'dc_minutes' => 30,
            'dc_of' => 0,
            'dc_df' => 0,
            'dc_oi' => 0,
            'dc_di' => 0,
            'dc_bh' => 0,
        ]);

        ob_start();
        $view->renderPlayerRow($playerFromDb, 1);
        $html = ob_get_clean();

        // Verify the HTML contains select elements with correct field names
        $this->assertStringContainsString('name="pg1"', $html);
        $this->assertStringContainsString('name="sg1"', $html);
        $this->assertStringContainsString('name="sf1"', $html);
        $this->assertStringContainsString('name="pf1"', $html);
        $this->assertStringContainsString('name="c1"', $html);

        // Verify the correct values are selected
        $this->assertStringContainsString('value="1" SELECTED', $html, 'PG depth should have value 1 selected');
        $this->assertStringContainsString('value="3" SELECTED', $html, 'SG depth should have value 3 selected');
        $this->assertStringContainsString('value="2" SELECTED', $html, 'PF depth should have value 2 selected');
        $this->assertStringContainsString('value="0" SELECTED', $html, 'SF/C depth should have value 0 selected');
    }

    /**
     * Test that role fields (OF, DF, OI, DI, BH) are hardcoded to 0 regardless of input
     */
    public function testRoleFieldsAreHardcodedToZero(): void
    {
        // Even if POST data contains OF/DF/OI/DI/BH keys, the processor ignores them
        $postData = [
            'Name1' => 'Test Player',
            'pg1' => '1',
            'sg1' => '0',
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'canPlayInGame1' => '1',
            'min1' => '30',
            'OF1' => '3',
            'DF1' => '2',
            'OI1' => '2',
            'DI1' => '1',
            'BH1' => '1',
            'Injury1' => '0'
        ];

        $result = $this->processor->processSubmission($postData, 15);
        $player = $result['playerData'][0];

        // All role fields are hardcoded to 0 — dead storage in JSB
        $this->assertSame(0, $player['of'], 'OF should always be 0');
        $this->assertSame(0, $player['df'], 'DF should always be 0');
        $this->assertSame(0, $player['oi'], 'OI should always be 0');
        $this->assertSame(0, $player['di'], 'DI should always be 0');
        $this->assertSame(0, $player['bh'], 'BH should always be 0');
    }

    /**
     * Test that CSV export correctly represents all processed values
     */
    public function testCsvExportMatchesProcessedValuesForAllSettings(): void
    {
        $playerData = [
            [
                'name' => 'Player 1',
                'pg' => 1,
                'sg' => 0,
                'sf' => 0,
                'pf' => 0,
                'c' => 0,
                'canPlayInGame' => 1,
                'min' => 30,
                'of' => 0,
                'df' => 0,
                'oi' => 0,
                'di' => 0,
                'bh' => 0,
                'injury' => 0,
            ],
            [
                'name' => 'Player 2',
                'pg' => 0,
                'sg' => 1,
                'sf' => 0,
                'pf' => 0,
                'c' => 0,
                'canPlayInGame' => 1,
                'min' => 25,
                'of' => 0,
                'df' => 0,
                'oi' => 0,
                'di' => 0,
                'bh' => 0,
                'injury' => 0,
            ]
        ];

        $csv = $this->processor->generateCsvContent($playerData);

        // Verify header
        $this->assertStringContainsString('Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI,BH', $csv);

        // Verify Player 1 data
        $this->assertStringContainsString('Player 1,1,0,0,0,0,1,30,0,0,0,0,0', $csv);

        // Verify Player 2 data
        $this->assertStringContainsString('Player 2,0,1,0,0,0,1,25,0,0,0,0,0', $csv);
    }

    /**
     * Test the complete round-trip for a player:
     * Form display -> User sees values -> Submits -> CSV generated -> Form reloaded
     */
    public function testCompleteRoundTripPreservesPositionDepthValues(): void
    {
        $view = new DepthChartEntryView(self::createStub(\League\LeagueContext::class), new \DepthChartEntry\DepthChartEntryService());

        // Step 1: Player has these values in database
        $dbPlayer = self::buildPlayerRow([
            'name' => 'Round Trip Player',
            'pos' => 'SG',
            'injured' => 0,
            'stamina' => 60,
            'dc_pg_depth' => 0,
            'dc_sg_depth' => 1,
            'dc_sf_depth' => 2,
            'dc_pf_depth' => 0,
            'dc_c_depth' => 3,
            'dc_can_play_in_game' => 1,
            'dc_minutes' => 35,
            'dc_of' => 0,
            'dc_df' => 0,
            'dc_oi' => 0,
            'dc_di' => 0,
            'dc_bh' => 0,
        ]);

        // Step 2: Form displays these values (capture HTML)
        ob_start();
        $view->renderPlayerRow($dbPlayer, 1);
        $formHtml = ob_get_clean();

        // Step 3: User submits form with these exact values
        $postData = [
            'Name1' => 'Round Trip Player',
            'pg1' => '0',
            'sg1' => '1',
            'sf1' => '2',
            'pf1' => '0',
            'c1' => '3',
            'canPlayInGame1' => '1',
            'min1' => '35',
            'Injury1' => '0'
        ];

        // Step 4: Process submission
        $result = $this->processor->processSubmission($postData, 15);
        $processedPlayer = $result['playerData'][0];

        // Step 5: Generate CSV
        $csv = $this->processor->generateCsvContent([$processedPlayer]);

        // Step 6: Verify consistency

        // Form should have shown the correct field names
        $this->assertStringContainsString('name="pg1"', $formHtml);
        $this->assertStringContainsString('name="sg1"', $formHtml);
        $this->assertStringContainsString('name="sf1"', $formHtml);
        $this->assertStringContainsString('name="pf1"', $formHtml);
        $this->assertStringContainsString('name="c1"', $formHtml);

        // Processed data should match POST data for position depth
        $this->assertSame(0, $processedPlayer['pg'], 'Processed PG should be 0');
        $this->assertSame(1, $processedPlayer['sg'], 'Processed SG should be 1');
        $this->assertSame(2, $processedPlayer['sf'], 'Processed SF should be 2');
        $this->assertSame(0, $processedPlayer['pf'], 'Processed PF should be 0');
        $this->assertSame(3, $processedPlayer['c'], 'Processed C should be 3');
        // Role fields always 0
        $this->assertSame(0, $processedPlayer['of']);
        $this->assertSame(0, $processedPlayer['df']);

        // CSV should match processed data
        $this->assertStringContainsString('Round Trip Player,0,1,2,0,3,1,35,0,0,0,0,0', $csv, 'CSV should match processed data exactly');

        // The key test: if we load the form again with the processed data as if it came from database,
        // it should show the same selected values
        $dbPlayerAfterUpdate = self::buildPlayerRow([
            'name' => 'Round Trip Player',
            'pos' => 'SG',
            'injured' => 0,
            'stamina' => 60,
            'dc_pg_depth' => $processedPlayer['pg'],
            'dc_sg_depth' => $processedPlayer['sg'],
            'dc_sf_depth' => $processedPlayer['sf'],
            'dc_pf_depth' => $processedPlayer['pf'],
            'dc_c_depth' => $processedPlayer['c'],
            'dc_can_play_in_game' => $processedPlayer['canPlayInGame'],
            'dc_minutes' => $processedPlayer['min'],
            'dc_of' => 0,
            'dc_df' => 0,
            'dc_oi' => 0,
            'dc_di' => 0,
            'dc_bh' => 0,
        ]);

        ob_start();
        $view->renderPlayerRow($dbPlayerAfterUpdate, 1);
        $reloadedFormHtml = ob_get_clean();

        // The reloaded form should show the same selected values
        $this->assertStringContainsString('value="1" SELECTED', $reloadedFormHtml, 'SG=1 should be selected on reload');
        $this->assertStringContainsString('value="2" SELECTED', $reloadedFormHtml, 'SF=2 should be selected on reload');
        $this->assertStringContainsString('value="3" SELECTED', $reloadedFormHtml, 'C=3 should be selected on reload');
        $this->assertStringContainsString('value="0" SELECTED', $reloadedFormHtml, 'PG/PF=0 should be selected on reload');
    }

    /**
     * Test for a specific edge case: when position depth values are 0,
     * ensure they're not confused with empty/null/false
     */
    public function testZeroValuesAreHandledCorrectly(): void
    {
        $view = new DepthChartEntryView(self::createStub(\League\LeagueContext::class), new \DepthChartEntry\DepthChartEntryService());

        // Player with all zero position depths except C=1
        $player = self::buildPlayerRow([
            'name' => 'Zero Settings Player',
            'pos' => 'C',
            'injured' => 0,
            'stamina' => 50,
            'dc_pg_depth' => 0,
            'dc_sg_depth' => 0,
            'dc_sf_depth' => 0,
            'dc_pf_depth' => 0,
            'dc_c_depth' => 1,
            'dc_can_play_in_game' => 1,
            'dc_minutes' => 20,
            'dc_of' => 0,
            'dc_df' => 0,
            'dc_oi' => 0,
            'dc_di' => 0,
            'dc_bh' => 0,
        ]);

        ob_start();
        $view->renderPlayerRow($player, 1);
        $html = ob_get_clean();

        // Count how many times "value=\"0\" SELECTED" appears
        // Should be 4 times: the 4 position depth selects (PG,SG,SF,PF) all at 0
        // C has value 1 selected
        $count = substr_count($html, 'value="0" SELECTED');
        $this->assertEquals(4, $count, 'Position depth fields with value 0 should be selected');

        // Verify that position depth dropdowns have value 0 selected
        $this->assertMatchesRegularExpression('/<select name="pg\d+"[^>]*>.*?value="0" SELECTED/s', $html, 'PG should have value 0 selected');
        $this->assertMatchesRegularExpression('/<select name="sg\d+"[^>]*>.*?value="0" SELECTED/s', $html, 'SG should have value 0 selected');
        $this->assertMatchesRegularExpression('/<select name="sf\d+"[^>]*>.*?value="0" SELECTED/s', $html, 'SF should have value 0 selected');
        $this->assertMatchesRegularExpression('/<select name="pf\d+"[^>]*>.*?value="0" SELECTED/s', $html, 'PF should have value 0 selected');
        // C should have value 1 selected
        $this->assertMatchesRegularExpression('/<select name="c\d+"[^>]*>.*?value="1" SELECTED/s', $html, 'C should have value 1 selected');
    }

    // --- CSV + processed-data consistency (confirmation-page assertions
    // removed along with DepthChartEntryView::renderSubmissionResult; PRG
    // flow no longer renders a result table) ---

    /**
     * The CSV export columns match the processed player record exactly.
     */
    public function testCsvExportPreservesPlayerValues(): void
    {
        $playerData = [
            [
                'name' => 'Test Player',
                'pg' => 1,
                'sg' => 2,
                'sf' => 0,
                'pf' => 3,
                'c' => 0,
                'canPlayInGame' => 1,
                'min' => 30,
                'of' => 0,
                'df' => 0,
                'oi' => 0,
                'di' => 0,
                'bh' => 0,
                'injury' => 0,
            ],
        ];

        $csv = $this->processor->generateCsvContent($playerData);

        $this->assertStringContainsString('Test Player,1,2,0,3,0,1,30,0,0,0,0,0', $csv);
    }

    /**
     * processSubmission → generateCsvContent round-trip: the processed
     * record feeds the CSV unchanged, so whatever the user submitted is
     * what gets written to disk / emailed.
     */
    public function testProcessedSubmissionRoundTripsIntoCsv(): void
    {
        $postData = [
            'Name1' => 'Consistency Test Player',
            'pg1' => '2',
            'sg1' => '1',
            'sf1' => '3',
            'pf1' => '0',
            'c1' => '4',
            'canPlayInGame1' => '1',
            'min1' => '35',
            'Injury1' => '0',
        ];

        $result = $this->processor->processSubmission($postData, 15);
        $processedData = $result['playerData'];
        $player = $processedData[0];

        $expectedCsvLine = sprintf(
            'Consistency Test Player,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d',
            $player['pg'],
            $player['sg'],
            $player['sf'],
            $player['pf'],
            $player['c'],
            $player['canPlayInGame'],
            $player['min'],
            $player['of'],
            $player['df'],
            $player['oi'],
            $player['di'],
            $player['bh'],
        );
        $this->assertStringContainsString(
            $expectedCsvLine,
            $this->processor->generateCsvContent($processedData),
            'CSV should contain exact values from processed data',
        );

        // Processed values match input POST
        $this->assertSame(2, $player['pg']);
        $this->assertSame(1, $player['sg']);
        $this->assertSame(3, $player['sf']);
        $this->assertSame(4, $player['c']);
        $this->assertSame(35, $player['min']);
        $this->assertSame(0, $player['of']);
        $this->assertSame(0, $player['df']);
        $this->assertSame(0, $player['oi']);
        $this->assertSame(0, $player['di']);
        $this->assertSame(0, $player['bh']);
    }

    public function testPositionDepthValuesAppearInCorrectCsvColumns(): void
    {
        $cases = [
            ['pg', 1, 1],
            ['sg', 2, 2],
            ['sf', 1, 3],
            ['pf', 3, 4],
            ['c', 5, 5],
        ];

        foreach ($cases as [$positionField, $depthValue, $expectedColumn]) {
            $postData = [
                'Name1' => 'Kevin Martin',
                'pg1' => '0',
                'sg1' => '0',
                'sf1' => '0',
                'pf1' => '0',
                'c1' => '0',
                'canPlayInGame1' => '1',
                'min1' => '40',
                'Injury1' => '0',
            ];
            $postData[$positionField . '1'] = (string) $depthValue;

            $result = $this->processor->processSubmission($postData, 15);
            $csv = $this->processor->generateCsvContent($result['playerData']);

            $lines = explode("\n", trim($csv));
            $this->assertCount(2, $lines);

            $dataColumns = str_getcsv($lines[1], ',', '"', '');
            $this->assertSame(
                (string) $depthValue,
                $dataColumns[$expectedColumn],
                "Position {$positionField} depth value should appear in CSV column {$expectedColumn}"
            );
        }
    }

    public function testFormSubmissionToCSvRoundTripPreservesPositionDepth(): void
    {
        $postData = [
            'Name1' => 'Kevin Martin',
            'pg1' => '0',
            'sg1' => '0',
            'sf1' => '1',
            'pf1' => '0',
            'c1' => '0',
            'canPlayInGame1' => '1',
            'min1' => '40',
            'Injury1' => '0',
        ];

        $result = $this->processor->processSubmission($postData, 15);

        $this->assertSame(1, $result['playerData'][0]['sf']);
        $this->assertSame(0, $result['playerData'][0]['pg']);

        $csv = $this->processor->generateCsvContent($result['playerData']);
        $this->assertStringContainsString('Kevin Martin,0,0,1,0,0,1,40,0,0,0,0,0', $csv);

        $headerColumns = str_getcsv(explode("\n", $csv)[0], ',', '"', '');
        $this->assertSame('SF', $headerColumns[3]);
    }
}
