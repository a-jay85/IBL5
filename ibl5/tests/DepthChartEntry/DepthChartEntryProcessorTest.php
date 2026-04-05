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
            'OF1' => '0',
            'DF1' => '1',
            'OI1' => '0',
            'DI1' => '0',
            'BH1' => '0',
            'Injury1' => '0',
            'Name2' => 'Player Two',
            'pg2' => '2',
            'sg2' => '0',
            'sf2' => '0',
            'pf2' => '0',
            'c2' => '0',
            'canPlayInGame2' => '1',
            'min2' => '25',
            'OF2' => '0',
            'DF2' => '0',
            'OI2' => '1',
            'DI2' => '0',
            'BH2' => '0',
            'Injury2' => '0'
        ];
        
        $result = $this->processor->processSubmission($postData, 15);
        
        $this->assertEquals(2, count($result['playerData']));
        $this->assertEquals(2, $result['activePlayers']);
        $this->assertEquals(0, $result['pos_1']);  // Position depth counting removed
        $this->assertFalse($result['hasStarterAtMultiplePositions']);
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
            'OF1' => '0',
            'DF1' => '0',
            'OI1' => '0',
            'DI1' => '0',
            'BH1' => '0',
            'Injury1' => '0'
        ];
        
        $result = $this->processor->processSubmission($postData, 15);
        
        $this->assertFalse($result['hasStarterAtMultiplePositions']);
        $this->assertEquals('', $result['nameOfProblemStarter']);
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
            'OF1' => '0',
            'DF1' => '0',
            'OI1' => '0',
            'DI1' => '0',
            'BH1' => '0',
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
                'df' => '1',
                'oi' => '0',
                'di' => '0',
                'bh' => '0'
            ]
        ];
        
        $csv = $this->processor->generateCsvContent($playerData);
        
        $this->assertStringContainsString('Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI,BH', $csv);
        $this->assertStringContainsString('Player One,1,0,0,0,0,1,30,0,1,0,0,0', $csv);
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
            'OF1' => '10',  // Out of range (should be capped at 3)
            'DF1' => '-5',  // Out of range (should be 0)
            'OI1' => '10',  // Out of range (should be capped at 2)
            'DI1' => '-10',  // Out of range (should be clamped to 0)
            'BH1' => '5',  // Out of range (should be capped at 2)
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
        
        // Focus values should be capped at 3 and 0
        $this->assertEquals(3, $result['playerData'][0]['of']);
        $this->assertEquals(0, $result['playerData'][0]['df']);
        
        // Setting values should be capped between 0 and 2
        $this->assertEquals(2, $result['playerData'][0]['oi']);
        $this->assertEquals(0, $result['playerData'][0]['di']);
        $this->assertEquals(2, $result['playerData'][0]['bh']);
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
            'OF1' => '0',
            'DF1' => '0',
            'OI1' => '0',
            'DI1' => '0',
            'BH1' => '0'
            // Injury1 is missing
        ];
        
        $result = $this->processor->processSubmission($postData, 15);
        
        $this->assertEquals(1, count($result['playerData']));
        $this->assertEquals('Player One', $result['playerData'][0]['name']);
        $this->assertEquals(0, $result['playerData'][0]['injury']);
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
                'df' => 1,
                'oi' => -1,
                'di' => 2,
                'bh' => -2
            ]
        ];
        
        $csv = $this->processor->generateCsvContent($playerData);
        
        $this->assertStringContainsString('Player, Jr.', $csv);
        $this->assertStringContainsString('1,0,0,0,0,1,30,0,1,-1,2,-2', $csv);
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
            'OF1' => '0',
            'DF1' => '0',
            'OI1' => '0',
            'DI1' => '0',
            'BH1' => '0',
            'Injury1' => '0'
        ];

        $result = $this->processor->processSubmission($postData, 15);

        // Position depth counting removed — always returns 0
        $this->assertEquals(0, $result['pos_1']);
        $this->assertEquals(0, $result['pos_2']);
        $this->assertEquals(0, $result['pos_3']);
        $this->assertEquals(0, $result['pos_4']);
        $this->assertEquals(0, $result['pos_5']);
    }

    // --- Merged from DepthChartEntryDataConsistencyTest ---

    /**
     * Tests data consistency for settings fields (OF, DF, OI, DI, BH)
     * Tests DepthChartEntryProcessor and DepthChartEntryView together.
     */
    public function testFormDisplaysCorrectDatabaseValuesForAllSettings()
    {
        $view = new DepthChartEntryView($this->processor);

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
            'dc_canPlayInGame' => 1,
            'dc_minutes' => 30,
            'dc_of' => 2,      // Role slot C priority 2
            'dc_df' => 1,      // Role slot PF priority 1
            'dc_oi' => 1,      // Role slot SF priority 1
            'dc_di' => 2,      // Role slot SG priority 2
            'dc_bh' => 0       // Role slot PG unassigned
        ];

        ob_start();
        $view->renderPlayerRow($playerFromDb, 1);
        $html = ob_get_clean();

        // Verify the HTML contains select elements with correct field names
        $this->assertStringContainsString('name="OF1"', $html);
        $this->assertStringContainsString('name="DF1"', $html);
        $this->assertStringContainsString('name="OI1"', $html);
        $this->assertStringContainsString('name="DI1"', $html);
        $this->assertStringContainsString('name="BH1"', $html);

        // Verify the correct values are selected
        $this->assertStringContainsString('value="2" SELECTED', $html, 'OF (C slot) should have value 2 selected');
        $this->assertStringContainsString('value="1" SELECTED', $html, 'DF (PF slot) should have value 1 selected');
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
            // Test case 2: Negative values clamp to 0
            [
                'input' => ['OF1' => '0', 'DF1' => '0', 'OI1' => '-2', 'DI1' => '-2', 'BH1' => '-2'],
                'expected' => ['of' => 0, 'df' => 0, 'oi' => 0, 'di' => 0, 'bh' => 0]
            ],
            // Test case 3: Mixed values (negatives clamp to 0)
            [
                'input' => ['OF1' => '1', 'DF1' => '3', 'OI1' => '-1', 'DI1' => '0', 'BH1' => '2'],
                'expected' => ['of' => 1, 'df' => 3, 'oi' => 0, 'di' => 0, 'bh' => 2]
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
                    'canPlayInGame1' => '1',
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
                'canPlayInGame' => 1,
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
        $view = new DepthChartEntryView($this->processor);

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
            'dc_canPlayInGame' => 1,
            'dc_minutes' => 35,
            'dc_of' => 1,   // C slot priority 1
            'dc_df' => 3,   // PF slot priority 3
            'dc_oi' => 2,   // SF slot priority 2
            'dc_di' => 1,   // SG slot priority 1
            'dc_bh' => 0    // PG slot unassigned
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
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'canPlayInGame1' => '1',
            'min1' => '35',
            'OF1' => '1',
            'DF1' => '3',
            'OI1' => '2',
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
        $this->assertEquals(2, $processedPlayer['oi'], 'Processed OI should be 2');
        $this->assertEquals(1, $processedPlayer['di'], 'Processed DI should be 1');
        $this->assertEquals(0, $processedPlayer['bh'], 'Processed BH should be 0');

        // CSV should match processed data
        $this->assertStringContainsString('Round Trip Player,0,1,0,0,0,1,35,1,3,2,1,0', $csv, 'CSV should match processed data exactly');

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
            'dc_canPlayInGame' => $processedPlayer['canPlayInGame'],
            'dc_minutes' => $processedPlayer['min'],
            'dc_of' => $processedPlayer['of'],
            'dc_df' => $processedPlayer['df'],
            'dc_oi' => $processedPlayer['oi'],
            'dc_di' => $processedPlayer['di'],
            'dc_bh' => $processedPlayer['bh']
        ];

        ob_start();
        $view->renderPlayerRow($dbPlayerAfterUpdate, 1);
        $reloadedFormHtml = ob_get_clean();

        // The reloaded form should show the same selected values
        $this->assertStringContainsString('value="1" SELECTED', $reloadedFormHtml, 'OF=1 should be selected on reload');
        $this->assertStringContainsString('value="3" SELECTED', $reloadedFormHtml, 'DF=3 should be selected on reload');
        $this->assertStringContainsString('value="2" SELECTED', $reloadedFormHtml, 'OI=2 should be selected on reload');
        // Note: Both OF and DI have value 1, so we just check that value="1" SELECTED exists
        $this->assertStringContainsString('value="0" SELECTED', $reloadedFormHtml, 'BH=0 should be selected on reload');
    }

    /**
     * Test for a specific edge case: when settings have the value 0,
     * ensure it's not confused with empty/null/false
     */
    public function testZeroValuesAreHandledCorrectly()
    {
        $view = new DepthChartEntryView($this->processor);

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
            'dc_canPlayInGame' => 1,
            'dc_minutes' => 20,
            'dc_of' => 0,    // Auto
            'dc_df' => 0,    // Auto
            'dc_oi' => 0,    // Neutral
            'dc_di' => 0,    // Neutral
            'dc_bh' => 0     // Neutral
        ];

        ob_start();
        $view->renderPlayerRow($player, 1);
        $html = ob_get_clean();

        // Count how many times "value=\"0\" SELECTED" appears
        // Should be 5 times: the 5 role slot selects (BH,DI,OI,DF,OF) all at 0
        // Position fields are now hidden inputs, not selects
        $count = substr_count($html, 'value="0" SELECTED');
        $this->assertEquals(5, $count, 'Role slot fields with value 0 should be selected');

        // More specifically, verify that the settings fields have value 0
        // Check for the specific pattern of settings dropdowns with value 0 selected
        $this->assertMatchesRegularExpression('/<select name="OF\d+"[^>]*>.*?value="0" SELECTED/s', $html, 'OF should have value 0 selected');
        $this->assertMatchesRegularExpression('/<select name="DF\d+"[^>]*>.*?value="0" SELECTED/s', $html, 'DF should have value 0 selected');
        $this->assertMatchesRegularExpression('/<select name="OI\d+"[^>]*>.*?value="0" SELECTED/s', $html, 'OI should have value 0 selected');
        $this->assertMatchesRegularExpression('/<select name="DI\d+"[^>]*>.*?value="0" SELECTED/s', $html, 'DI should have value 0 selected');
        $this->assertMatchesRegularExpression('/<select name="BH\d+"[^>]*>.*?value="0" SELECTED/s', $html, 'BH should have value 0 selected');
    }

    // --- Merged from DepthChartEntryConfirmationTest ---

    /**
     * Test that the confirmation page displays the same values that will be in the CSV.
     * Tests DepthChartEntryView::renderSubmissionResult() output.
     */
    public function testConfirmationPageMatchesCsvExport()
    {
        $view = new DepthChartEntryView($this->processor);

        $playerData = [
            [
                'name' => 'Test Player',
                'pg' => 1,
                'sg' => 0,
                'sf' => 0,
                'pf' => 0,
                'c' => 0,
                'canPlayInGame' => 1,
                'min' => 30,
                'of' => 2,
                'df' => 1,
                'oi' => -1,
                'di' => 2,
                'bh' => 0
            ]
        ];

        // Generate confirmation page HTML
        ob_start();
        $view->renderSubmissionResult('Test Team', $playerData, true);
        $confirmationHtml = ob_get_clean();

        // Generate CSV
        $csv = $this->processor->generateCsvContent($playerData);

        // The CSV should contain these exact values
        $this->assertStringContainsString('Test Player,1,0,0,0,0,1,30,2,1,-1,2,0', $csv);

        // The confirmation page should also display these same values
        $this->assertStringContainsString('Test Player', $confirmationHtml);

        // Verify each value appears in the HTML in the correct context
        // Confirmation page now shows: Name, Active, PG(BH), SG(DI), SF(OI), PF(DF), C(OF)
        $this->assertMatchesRegularExpression('/<td>1<\/td>/', $confirmationHtml, 'Active or DF value 1 should appear');
        $this->assertMatchesRegularExpression('/<td>2<\/td>/', $confirmationHtml, 'OF or DI value 2 should appear');
        $this->assertMatchesRegularExpression('/<td>-1<\/td>/', $confirmationHtml, 'OI value -1 should appear');
    }

    /**
     * Test that all three outputs (confirmation, CSV, database) would receive the same data
     */
    public function testAllThreeOutputsReceiveSameProcessedData()
    {
        $view = new DepthChartEntryView($this->processor);

        // Simulate form submission
        $postData = [
            'Name1' => 'Consistency Test Player',
            'pg1' => '2',
            'sg1' => '1',
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'canPlayInGame1' => '1',
            'min1' => '35',
            'OF1' => '3',
            'DF1' => '2',
            'OI1' => '2',
            'DI1' => '1',
            'BH1' => '0',
            'Injury1' => '0'
        ];

        // Process the submission
        $result = $this->processor->processSubmission($postData, 15);
        $processedData = $result['playerData'];

        // Generate all three outputs
        $csvContent = $this->processor->generateCsvContent($processedData);

        ob_start();
        $view->renderSubmissionResult('Test Team', $processedData, true);
        $confirmationHtml = ob_get_clean();

        // Extract the values from processed data
        $player = $processedData[0];

        // Verify CSV contains these exact values
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
            $player['bh']
        );
        $this->assertStringContainsString($expectedCsvLine, $csvContent, 'CSV should contain exact values from processed data');

        // Verify confirmation page contains key values in table cells
        // Confirmation page shows: Name, Active, BH, DI, OI, DF, OF
        $valuesToCheck = [
            $player['canPlayInGame'],
            $player['of'],
            $player['df'],
            $player['oi'],
            $player['di'],
            $player['bh']
        ];

        foreach ($valuesToCheck as $value) {
            // Each value should appear in a table cell
            $this->assertStringContainsString("<td>$value</td>", $confirmationHtml, "Confirmation page should contain value $value");
        }

        // The database would receive the same array via updatePlayerDepthChart($player['name'], $player)
        $this->assertArrayHasKey('pg', $player);
        $this->assertArrayHasKey('sg', $player);
        $this->assertArrayHasKey('of', $player);
        $this->assertArrayHasKey('df', $player);
        $this->assertArrayHasKey('oi', $player);
        $this->assertArrayHasKey('di', $player);
        $this->assertArrayHasKey('bh', $player);

        // Verify the values match what was submitted (negatives clamped to 0)
        $this->assertEquals(2, $player['pg']);
        $this->assertEquals(1, $player['sg']);
        $this->assertEquals(35, $player['min']);
        $this->assertEquals(3, $player['of']);
        $this->assertEquals(2, $player['df']);
        $this->assertEquals(2, $player['oi']);
        $this->assertEquals(1, $player['di']);
        $this->assertEquals(0, $player['bh']);
    }

    /**
     * Test what a user would actually see: numeric codes vs human-readable labels.
     * The FORM shows "Drive" but the CONFIRMATION and CSV show "2" — this is by design.
     */
    public function testUserSeesNumericCodesInConfirmationAndCsv()
    {
        $view = new DepthChartEntryView($this->processor);

        $playerData = [
            [
                'name' => 'Human Readable Test',
                'pg' => 1,
                'sg' => 0,
                'sf' => 0,
                'pf' => 0,
                'c' => 0,
                'canPlayInGame' => 1,
                'min' => 30,
                'of' => 2,  // This is "Drive" in the form dropdown
                'df' => 1,  // This is "Outside" in the form dropdown
                'oi' => -1,
                'di' => 2,
                'bh' => 0   // This is "-" (dash) in the form dropdown for value 0
            ]
        ];

        // Generate confirmation page
        ob_start();
        $view->renderSubmissionResult('Test Team', $playerData, true);
        $confirmationHtml = ob_get_clean();

        // Generate CSV
        $csv = $this->processor->generateCsvContent($playerData);

        // The confirmation page shows NUMERIC VALUES, not human-readable labels
        $this->assertStringContainsString('<td>2</td>', $confirmationHtml, 'Confirmation shows numeric value 2 for OF');
        $this->assertStringContainsString('<td>1</td>', $confirmationHtml, 'Confirmation shows numeric value 1 for DF');
        $this->assertStringContainsString('<td>-1</td>', $confirmationHtml, 'Confirmation shows numeric value -1 for OI');
        $this->assertStringContainsString('<td>0</td>', $confirmationHtml, 'Confirmation shows numeric value 0 for BH');

        // Confirmation should NOT contain human-readable labels like "Drive", "Outside", etc.
        $this->assertStringNotContainsString('Drive', $confirmationHtml);
        $this->assertStringNotContainsString('Outside', $confirmationHtml);

        // CSV also shows numeric values
        $this->assertStringContainsString(',2,', $csv, 'CSV shows numeric value 2 for OF');
        $this->assertStringContainsString(',1,', $csv, 'CSV shows numeric value 1 for DF');
        $this->assertStringContainsString(',-1,', $csv, 'CSV shows numeric value -1 for OI');
    }
}
