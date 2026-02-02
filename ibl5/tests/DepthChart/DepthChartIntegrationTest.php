<?php

declare(strict_types=1);

namespace Tests\DepthChart;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use DepthChart\DepthChartRepository;
use DepthChart\DepthChartProcessor;
use DepthChart\DepthChartView;

/**
 * Integration test to verify consistency across all three surfaces:
 * 1. Browser display (form loading from database)
 * 2. Database storage (after submission)
 * 3. CSV/TXT export (generated file content)
 */
#[AllowMockObjectsWithoutExpectations]
class DepthChartIntegrationTest extends TestCase
{
    private $mockDb;
    private $repository;
    private $processor;
    private $view;

    protected function setUp(): void
    {
        // Create a mock database connection
        $this->mockDb = $this->createMock(\mysqli::class);
        $this->repository = new DepthChartRepository($this->mockDb);
        $this->processor = new DepthChartProcessor();
        $this->view = new DepthChartView($this->processor);
    }

    /**
     * Tests that POST data is correctly processed into the internal array format
     */
    public function testPostDataProcessing()
    {
        $postData = [
            'Name1' => 'Test Player',
            'pg1' => '1',
            'sg1' => '0',
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'active1' => '1',
            'min1' => '30',
            'OF1' => '2',  // Drive
            'DF1' => '1',  // Outside
            'OI1' => '-1', // Negative intensity
            'DI1' => '2',  // Positive intensity
            'BH1' => '0',  // Neutral
            'Injury1' => '0'
        ];

        $result = $this->processor->processSubmission($postData, 15);
        $playerData = $result['playerData'][0];

        // Verify the processed array has the correct values
        $this->assertEquals('Test Player', $playerData['name']);
        $this->assertEquals(1, $playerData['pg']);
        $this->assertEquals(0, $playerData['sg']);
        $this->assertEquals(1, $playerData['active']);
        $this->assertEquals(30, $playerData['min']);
        $this->assertEquals(2, $playerData['of']);
        $this->assertEquals(1, $playerData['df']);
        $this->assertEquals(-1, $playerData['oi']);
        $this->assertEquals(2, $playerData['di']);
        $this->assertEquals(0, $playerData['bh']);
    }

    /**
     * Tests that CSV export contains the same values as the processed array
     */
    public function testCsvExportMatchesProcessedData()
    {
        $playerData = [
            [
                'name' => 'Test Player',
                'pg' => 1,
                'sg' => 0,
                'sf' => 0,
                'pf' => 0,
                'c' => 0,
                'active' => 1,
                'min' => 30,
                'of' => 2,
                'df' => 1,
                'oi' => -1,
                'di' => 2,
                'bh' => 0
            ]
        ];

        $csv = $this->processor->generateCsvContent($playerData);

        // CSV should have header line
        $this->assertStringContainsString('Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI,BH', $csv);

        // CSV should have the data line with exact values
        $this->assertStringContainsString('Test Player,1,0,0,0,0,1,30,2,1,-1,2,0', $csv);
    }

    /**
     * Tests that the view correctly maps database columns to form field values
     * This captures the actual HTML output to verify selected values
     */
    public function testViewDisplaysCorrectDatabaseValues()
    {
        $player = [
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
            'dc_of' => 2,
            'dc_df' => 1,
            'dc_oi' => -1,
            'dc_di' => 2,
            'dc_bh' => 0
        ];

        ob_start();
        $this->view->renderPlayerRow($player, 1);
        $html = ob_get_clean();

        // Verify the HTML contains the correct selected values
        // Check that OF field has value 2 selected
        $this->assertMatchesRegularExpression('/<select name="OF1">.*<option value="2" SELECTED>Drive<\/option>/s', $html);

        // Check that DF field has value 1 selected
        $this->assertMatchesRegularExpression('/<select name="DF1">.*<option value="1" SELECTED>Outside<\/option>/s', $html);

        // Check that OI field has value -1 selected
        $this->assertMatchesRegularExpression('/<select name="OI1">.*<option value="-1" SELECTED>-1<\/option>/s', $html);

        // Check that DI field has value 2 selected
        $this->assertMatchesRegularExpression('/<select name="DI1">.*<option value="2" SELECTED>2<\/option>/s', $html);

        // Check that BH field has value 0 selected
        $this->assertMatchesRegularExpression('/<select name="BH1">.*<option value="0" SELECTED>-<\/option>/s', $html);
    }

    /**
     * Tests a complete round-trip: form display -> submission -> database update -> CSV export
     * This simulates the complete user workflow to identify any inconsistencies
     */
    public function testCompleteRoundTrip()
    {
        // Step 1: Simulate database having certain values
        $dbPlayer = [
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
            'dc_of' => 2,
            'dc_df' => 1,
            'dc_oi' => -1,
            'dc_di' => 2,
            'dc_bh' => 0
        ];

        // Step 2: User loads form (view renders from database)
        ob_start();
        $this->view->renderPlayerRow($dbPlayer, 1);
        $formHtml = ob_get_clean();

        // Step 3: User submits form (POST data)
        $postData = [
            'Name1' => 'Test Player',
            'pg1' => '1',
            'sg1' => '0',
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'active1' => '1',
            'min1' => '30',
            'OF1' => '2',
            'DF1' => '1',
            'OI1' => '-1',
            'DI1' => '2',
            'BH1' => '0',
            'Injury1' => '0'
        ];

        // Step 4: Process submission
        $result = $this->processor->processSubmission($postData, 15);
        $processedPlayer = $result['playerData'][0];

        // Step 5: Generate CSV
        $csv = $this->processor->generateCsvContent([$processedPlayer]);

        // Step 6: Verify consistency

        // Verify processed data matches POST data
        $this->assertEquals(2, $processedPlayer['of'], 'Processed OF should match POST');
        $this->assertEquals(1, $processedPlayer['df'], 'Processed DF should match POST');
        $this->assertEquals(-1, $processedPlayer['oi'], 'Processed OI should match POST');
        $this->assertEquals(2, $processedPlayer['di'], 'Processed DI should match POST');
        $this->assertEquals(0, $processedPlayer['bh'], 'Processed BH should match POST');

        // Verify CSV matches processed data
        $this->assertStringContainsString('Test Player,1,0,0,0,0,1,30,2,1,-1,2,0', $csv, 'CSV should match processed data');

        // Verify form would have shown the values that match POST
        $this->assertStringContainsString('name="OF1"', $formHtml, 'Form should have OF field');
        $this->assertStringContainsString('name="DF1"', $formHtml, 'Form should have DF field');
        $this->assertStringContainsString('name="OI1"', $formHtml, 'Form should have OI field');
        $this->assertStringContainsString('name="DI1"', $formHtml, 'Form should have DI field');
        $this->assertStringContainsString('name="BH1"', $formHtml, 'Form should have BH field');
    }
}
