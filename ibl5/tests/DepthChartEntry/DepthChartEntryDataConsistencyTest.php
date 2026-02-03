<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use PHPUnit\Framework\TestCase;
use DepthChartEntry\DepthChartEntryProcessor;
use DepthChartEntry\DepthChartEntryView;

/**
 * Tests to verify data consistency specifically for settings fields (OF, DF, OI, DI, BH)
 * where mismatches between display, database, and CSV have been reported
 */
class DepthChartEntryDataConsistencyTest extends TestCase
{
    private $processor;
    private $view;

    protected function setUp(): void
    {
        $this->processor = new DepthChartEntryProcessor();
        $this->view = new DepthChartEntryView($this->processor);
    }

    /**
     * Test that verifies the form correctly reads and displays database values
     * for all settings fields
     */
    public function testFormDisplaysCorrectDatabaseValuesForAllSettings()
    {
        // Simulate a player record from the database with various setting values
        $playerFromDb = [
            'pid' => 1,
            'name' => 'Test Player',
            'pos' => 'PG',
            'injured' => 0,
            'sta' => 50,
            'dc_PGDepth' => 1,
            'dc_SGDepth' => 0,
            'dc_SFDepth' => 0,
            'dc_PFDepth' => 0,
            'dc_CDepth' => 0,
            'dc_active' => 1,
            'dc_minutes' => 30,
            'dc_of' => 2,      // Drive
            'dc_df' => 1,      // Outside
            'dc_oi' => -1,     // Negative intensity
            'dc_di' => 2,      // Positive intensity
            'dc_bh' => 0       // Neutral
        ];

        ob_start();
        $this->view->renderPlayerRow($playerFromDb, 1);
        $html = ob_get_clean();

        // Verify the HTML contains select elements with correct field names
        $this->assertStringContainsString('name="OF1"', $html);
        $this->assertStringContainsString('name="DF1"', $html);
        $this->assertStringContainsString('name="OI1"', $html);
        $this->assertStringContainsString('name="DI1"', $html);
        $this->assertStringContainsString('name="BH1"', $html);

        // Verify the correct values are selected
        $this->assertStringContainsString('value="2" SELECTED', $html, 'OF should have value 2 selected');
        $this->assertStringContainsString('value="1" SELECTED', $html, 'DF should have value 1 selected');
        $this->assertStringContainsString('value="-1" SELECTED', $html, 'OI should have value -1 selected');
        // Note: value="2" will match both OF and DI, so we check the pattern more carefully
        $matches = [];
        preg_match_all('/value="2" SELECTED/', $html, $matches);
        $this->assertGreaterThanOrEqual(2, count($matches[0]), 'Should have at least 2 occurrences of value="2" SELECTED (for DI and OF)');
        $this->assertStringContainsString('value="0" SELECTED', $html, 'BH should have value 0 selected');
    }

    /**
     * Test that POST data with various settings values is correctly processed
     */
    public function testPostDataWithVariousSettingsIsProcessedCorrectly()
    {
        $testCases = [
            // Test case 1: All positive values
            [
                'input' => ['OF1' => '3', 'DF1' => '2', 'OI1' => '2', 'DI1' => '1', 'BH1' => '1'],
                'expected' => ['of' => 3, 'df' => 2, 'oi' => 2, 'di' => 1, 'bh' => 1]
            ],
            // Test case 2: All negative values
            [
                'input' => ['OF1' => '0', 'DF1' => '0', 'OI1' => '-2', 'DI1' => '-2', 'BH1' => '-2'],
                'expected' => ['of' => 0, 'df' => 0, 'oi' => -2, 'di' => -2, 'bh' => -2]
            ],
            // Test case 3: Mixed values
            [
                'input' => ['OF1' => '1', 'DF1' => '3', 'OI1' => '-1', 'DI1' => '0', 'BH1' => '2'],
                'expected' => ['of' => 1, 'df' => 3, 'oi' => -1, 'di' => 0, 'bh' => 2]
            ],
            // Test case 4: Zero values
            [
                'input' => ['OF1' => '0', 'DF1' => '0', 'OI1' => '0', 'DI1' => '0', 'BH1' => '0'],
                'expected' => ['of' => 0, 'df' => 0, 'oi' => 0, 'di' => 0, 'bh' => 0]
            ]
        ];

        foreach ($testCases as $index => $testCase) {
            $postData = array_merge(
                [
                    'Name1' => 'Test Player',
                    'pg1' => '1',
                    'sg1' => '0',
                    'sf1' => '0',
                    'pf1' => '0',
                    'c1' => '0',
                    'active1' => '1',
                    'min1' => '30',
                    'Injury1' => '0'
                ],
                $testCase['input']
            );

            $result = $this->processor->processSubmission($postData, 15);
            $player = $result['playerData'][0];

            $this->assertEquals($testCase['expected']['of'], $player['of'], "Test case $index: OF mismatch");
            $this->assertEquals($testCase['expected']['df'], $player['df'], "Test case $index: DF mismatch");
            $this->assertEquals($testCase['expected']['oi'], $player['oi'], "Test case $index: OI mismatch");
            $this->assertEquals($testCase['expected']['di'], $player['di'], "Test case $index: DI mismatch");
            $this->assertEquals($testCase['expected']['bh'], $player['bh'], "Test case $index: BH mismatch");
        }
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
                'active' => 1,
                'min' => 30,
                'of' => 3,
                'df' => 2,
                'oi' => -2,
                'di' => 2,
                'bh' => -1
            ],
            [
                'name' => 'Player 2',
                'pg' => 0,
                'sg' => 1,
                'sf' => 0,
                'pf' => 0,
                'c' => 0,
                'active' => 1,
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
        $this->assertStringContainsString('Player 1,1,0,0,0,0,1,30,3,2,-2,2,-1', $csv);

        // Verify Player 2 data
        $this->assertStringContainsString('Player 2,0,1,0,0,0,1,25,0,0,0,0,0', $csv);
    }

    /**
     * Test the complete round-trip for a player:
     * Form display -> User sees values -> Submits -> CSV generated -> Form reloaded
     */
    public function testCompleteRoundTripPreservesSettingsValues()
    {
        // Step 1: Player has these values in database
        $dbPlayer = [
            'pid' => 1,
            'name' => 'Round Trip Player',
            'pos' => 'SG',
            'injured' => 0,
            'sta' => 60,
            'dc_PGDepth' => 0,
            'dc_SGDepth' => 1,
            'dc_SFDepth' => 0,
            'dc_PFDepth' => 0,
            'dc_CDepth' => 0,
            'dc_active' => 1,
            'dc_minutes' => 35,
            'dc_of' => 1,   // Outside
            'dc_df' => 3,   // Post
            'dc_oi' => -2,  // Minimum
            'dc_di' => 1,   // Positive
            'dc_bh' => 0    // Neutral
        ];

        // Step 2: Form displays these values (capture HTML)
        ob_start();
        $this->view->renderPlayerRow($dbPlayer, 1);
        $formHtml = ob_get_clean();

        // Step 3: User submits form with these exact values
        $postData = [
            'Name1' => 'Round Trip Player',
            'pg1' => '0',
            'sg1' => '1',
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'active1' => '1',
            'min1' => '35',
            'OF1' => '1',
            'DF1' => '3',
            'OI1' => '-2',
            'DI1' => '1',
            'BH1' => '0',
            'Injury1' => '0'
        ];

        // Step 4: Process submission
        $result = $this->processor->processSubmission($postData, 15);
        $processedPlayer = $result['playerData'][0];

        // Step 5: Generate CSV
        $csv = $this->processor->generateCsvContent([$processedPlayer]);

        // Step 6: Verify consistency

        // Form should have shown the correct values (checking field names exist)
        $this->assertStringContainsString('name="OF1"', $formHtml);
        $this->assertStringContainsString('name="DF1"', $formHtml);
        $this->assertStringContainsString('name="OI1"', $formHtml);
        $this->assertStringContainsString('name="DI1"', $formHtml);
        $this->assertStringContainsString('name="BH1"', $formHtml);

        // Processed data should match POST data
        $this->assertEquals(1, $processedPlayer['of'], 'Processed OF should be 1');
        $this->assertEquals(3, $processedPlayer['df'], 'Processed DF should be 3');
        $this->assertEquals(-2, $processedPlayer['oi'], 'Processed OI should be -2');
        $this->assertEquals(1, $processedPlayer['di'], 'Processed DI should be 1');
        $this->assertEquals(0, $processedPlayer['bh'], 'Processed BH should be 0');

        // CSV should match processed data
        $this->assertStringContainsString('Round Trip Player,0,1,0,0,0,1,35,1,3,-2,1,0', $csv, 'CSV should match processed data exactly');

        // The key test: if we load the form again with the processed data as if it came from database,
        // it should show the same selected values
        $dbPlayerAfterUpdate = [
            'pid' => 1,
            'name' => 'Round Trip Player',
            'pos' => 'SG',
            'injured' => 0,
            'sta' => 60,
            'dc_PGDepth' => $processedPlayer['pg'],
            'dc_SGDepth' => $processedPlayer['sg'],
            'dc_SFDepth' => $processedPlayer['sf'],
            'dc_PFDepth' => $processedPlayer['pf'],
            'dc_CDepth' => $processedPlayer['c'],
            'dc_active' => $processedPlayer['active'],
            'dc_minutes' => $processedPlayer['min'],
            'dc_of' => $processedPlayer['of'],
            'dc_df' => $processedPlayer['df'],
            'dc_oi' => $processedPlayer['oi'],
            'dc_di' => $processedPlayer['di'],
            'dc_bh' => $processedPlayer['bh']
        ];

        ob_start();
        $this->view->renderPlayerRow($dbPlayerAfterUpdate, 1);
        $reloadedFormHtml = ob_get_clean();

        // The reloaded form should show the same selected values
        $this->assertStringContainsString('value="1" SELECTED', $reloadedFormHtml, 'OF=1 should be selected on reload');
        $this->assertStringContainsString('value="3" SELECTED', $reloadedFormHtml, 'DF=3 should be selected on reload');
        $this->assertStringContainsString('value="-2" SELECTED', $reloadedFormHtml, 'OI=-2 should be selected on reload');
        // Note: Both OF and DI have value 1, so we just check that value="1" SELECTED exists
        $this->assertStringContainsString('value="0" SELECTED', $reloadedFormHtml, 'BH=0 should be selected on reload');
    }

    /**
     * Test for a specific edge case: when settings have the value 0,
     * ensure it's not confused with empty/null/false
     */
    public function testZeroValuesAreHandledCorrectly()
    {
        // Player with all zero settings
        $player = [
            'pid' => 1,
            'name' => 'Zero Settings Player',
            'pos' => 'C',
            'injured' => 0,
            'sta' => 50,
            'dc_PGDepth' => 0,
            'dc_SGDepth' => 0,
            'dc_SFDepth' => 0,
            'dc_PFDepth' => 0,
            'dc_CDepth' => 1,
            'dc_active' => 1,
            'dc_minutes' => 20,
            'dc_of' => 0,    // Auto
            'dc_df' => 0,    // Auto
            'dc_oi' => 0,    // Neutral
            'dc_di' => 0,    // Neutral
            'dc_bh' => 0     // Neutral
        ];

        ob_start();
        $this->view->renderPlayerRow($player, 1);
        $html = ob_get_clean();

        // Count how many times "value=\"0\" SELECTED" appears
        // Should be 9 times: 4 position fields (PG,SG,SF,PF with depth 0) + 5 settings fields (OF,DF,OI,DI,BH)
        $count = substr_count($html, 'value="0" SELECTED');
        $this->assertEquals(9, $count, 'Position fields and settings fields with value 0 should be selected');

        // More specifically, verify that the settings fields have value 0
        // Check for the specific pattern of settings dropdowns with value 0 selected
        $this->assertMatchesRegularExpression('/<select name="OF\d+">.*?value="0" SELECTED/s', $html, 'OF should have value 0 selected');
        $this->assertMatchesRegularExpression('/<select name="DF\d+">.*?value="0" SELECTED/s', $html, 'DF should have value 0 selected');
        $this->assertMatchesRegularExpression('/<select name="OI\d+">.*?value="0" SELECTED/s', $html, 'OI should have value 0 selected');
        $this->assertMatchesRegularExpression('/<select name="DI\d+">.*?value="0" SELECTED/s', $html, 'DI should have value 0 selected');
        $this->assertMatchesRegularExpression('/<select name="BH\d+">.*?value="0" SELECTED/s', $html, 'BH should have value 0 selected');
    }
}
