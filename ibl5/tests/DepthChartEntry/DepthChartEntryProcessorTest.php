<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use PHPUnit\Framework\TestCase;
use DepthChartEntry\DepthChartEntryProcessor;
use DepthChartEntry\DepthChartEntryView;

class DepthChartEntryProcessorTest extends TestCase
{
    private $processor;
    
    protected function setUp(): void
    {
        $this->processor = new DepthChartEntryProcessor();
    }
    
    public function testProcessesSubmissionCorrectly()
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
        $this->assertEquals(2, $result['activePlayers']);
        $this->assertEquals(2, $result['pos_1']);  // Both players have pg > 0
        $this->assertFalse($result['hasStarterAtMultiplePositions']);
        // Role slots are hardcoded to 0
        $this->assertEquals(0, $result['playerData'][0]['of']);
        $this->assertEquals(0, $result['playerData'][0]['df']);
        $this->assertEquals(0, $result['playerData'][0]['oi']);
        $this->assertEquals(0, $result['playerData'][0]['di']);
        $this->assertEquals(0, $result['playerData'][0]['bh']);
    }
    
    public function testDetectsMultipleStartingPositions()
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
        $this->assertEquals('Player One', $result['nameOfProblemStarter']);
    }
    
    public function testExcludesInjuredPlayersFromPositionCount()
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

        $this->assertEquals(0, $result['pos_1']);  // Injured player not counted
        $this->assertEquals(1, $result['activePlayers']);  // Still counts as active
    }
    
    public function testGeneratesCsvContentCorrectly()
    {
        $playerData = [
            [
                'name' => 'Player One',
                'pg' => '1',
                'sg' => '0',
                'sf' => '0',
                'pf' => '0',
                'c' => '0',
                'canPlayInGame' => '1',
                'min' => '30',
                'of' => '0',
                'df' => '0',
                'oi' => '0',
                'di' => '0',
                'bh' => '0'
            ]
        ];

        $csv = $this->processor->generateCsvContent($playerData);

        $this->assertStringContainsString('Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI,BH', $csv);
        $this->assertStringContainsString('Player One,1,0,0,0,0,1,30,0,0,0,0,0', $csv);
    }
    

    
    public function testSanitizesInputWithMaliciousData()
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
        $this->assertEquals(5, $result['playerData'][0]['pg']);
        $this->assertEquals(0, $result['playerData'][0]['sg']);

        // Active should be 0 (invalid value)
        $this->assertEquals(0, $result['playerData'][0]['canPlayInGame']);

        // Minutes should be capped at 40
        $this->assertEquals(40, $result['playerData'][0]['min']);

        // Role slots are hardcoded to 0 regardless of input
        $this->assertEquals(0, $result['playerData'][0]['of']);
        $this->assertEquals(0, $result['playerData'][0]['df']);
        $this->assertEquals(0, $result['playerData'][0]['oi']);
        $this->assertEquals(0, $result['playerData'][0]['di']);
        $this->assertEquals(0, $result['playerData'][0]['bh']);
    }
    
    public function testHandlesMissingOptionalFields()
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
        $this->assertEquals('Player One', $result['playerData'][0]['name']);
        $this->assertEquals(0, $result['playerData'][0]['injury']);
    }
    
    public function testBlankMinutesSubmissionIsConvertedToZero()
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

    public function testGeneratesCsvWithSpecialCharacters()
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
                'bh' => 0
            ]
        ];

        $csv = $this->processor->generateCsvContent($playerData);

        $this->assertStringContainsString('Player, Jr.', $csv);
        $this->assertStringContainsString('1,0,0,0,0,1,30,0,0,0,0,0', $csv);
    }
    
    public function testCountsAllPositionTypesCorrectly()
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
        $this->assertEquals(1, $result['pos_1']);
        $this->assertEquals(1, $result['pos_2']);
        $this->assertEquals(1, $result['pos_3']);
        $this->assertEquals(1, $result['pos_4']);
        $this->assertEquals(1, $result['pos_5']);
    }

    // --- Merged from DepthChartEntryDataConsistencyTest ---

    /**
     * Tests data consistency for position depth fields (pg, sg, sf, pf, c)
     * Tests DepthChartEntryProcessor and DepthChartEntryView together.
     */
    public function testFormDisplaysCorrectDatabaseValuesForAllSettings()
    {
        $view = new DepthChartEntryView();

        // Simulate a player record from the database with various depth values
        $playerFromDb = [
            'pid' => 1,
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
            'dc_bh' => 0
        ];

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
    public function testRoleFieldsAreHardcodedToZero()
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
        $this->assertEquals(0, $player['of'], 'OF should always be 0');
        $this->assertEquals(0, $player['df'], 'DF should always be 0');
        $this->assertEquals(0, $player['oi'], 'OI should always be 0');
        $this->assertEquals(0, $player['di'], 'DI should always be 0');
        $this->assertEquals(0, $player['bh'], 'BH should always be 0');
    }

    /**
     * Test that CSV export correctly represents all processed values
     */
    public function testCsvExportMatchesProcessedValuesForAllSettings()
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
                'bh' => 0
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
                'bh' => 0
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
    public function testCompleteRoundTripPreservesPositionDepthValues()
    {
        $view = new DepthChartEntryView();

        // Step 1: Player has these values in database
        $dbPlayer = [
            'pid' => 1,
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
            'dc_bh' => 0
        ];

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
        $this->assertEquals(0, $processedPlayer['pg'], 'Processed PG should be 0');
        $this->assertEquals(1, $processedPlayer['sg'], 'Processed SG should be 1');
        $this->assertEquals(2, $processedPlayer['sf'], 'Processed SF should be 2');
        $this->assertEquals(0, $processedPlayer['pf'], 'Processed PF should be 0');
        $this->assertEquals(3, $processedPlayer['c'], 'Processed C should be 3');
        // Role fields always 0
        $this->assertEquals(0, $processedPlayer['of']);
        $this->assertEquals(0, $processedPlayer['df']);

        // CSV should match processed data
        $this->assertStringContainsString('Round Trip Player,0,1,2,0,3,1,35,0,0,0,0,0', $csv, 'CSV should match processed data exactly');

        // The key test: if we load the form again with the processed data as if it came from database,
        // it should show the same selected values
        $dbPlayerAfterUpdate = [
            'pid' => 1,
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
            'dc_bh' => 0
        ];

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
    public function testZeroValuesAreHandledCorrectly()
    {
        $view = new DepthChartEntryView();

        // Player with all zero position depths except C=1
        $player = [
            'pid' => 1,
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
            'dc_bh' => 0
        ];

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
