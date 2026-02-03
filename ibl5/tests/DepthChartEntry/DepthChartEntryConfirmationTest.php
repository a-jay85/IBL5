<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use PHPUnit\Framework\TestCase;
use DepthChartEntry\DepthChartEntryProcessor;
use DepthChartEntry\DepthChartEntryView;

/**
 * Tests the confirmation page output to ensure it matches the data
 * that will be saved to the database and exported to CSV
 */
class DepthChartEntryConfirmationTest extends TestCase
{
    private $processor;
    private $view;

    protected function setUp(): void
    {
        $this->processor = new DepthChartEntryProcessor();
        $this->view = new DepthChartEntryView($this->processor);
    }

    /**
     * Test that the confirmation page displays the same values that will be in the CSV
     */
    public function testConfirmationPageMatchesCsvExport()
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

        // Generate confirmation page HTML
        ob_start();
        $this->view->renderSubmissionResult('Test Team', $playerData, true);
        $confirmationHtml = ob_get_clean();

        // Generate CSV
        $csv = $this->processor->generateCsvContent($playerData);

        // The CSV should contain these exact values
        $this->assertStringContainsString('Test Player,1,0,0,0,0,1,30,2,1,-1,2,0', $csv);

        // The confirmation page should also display these same values
        // Note: The confirmation page shows values in HTML table cells, not as a CSV line
        $this->assertStringContainsString('Test Player', $confirmationHtml);

        // Check that the confirmation page contains the numeric values
        // Extract the data row from the HTML (it should be in table cells)
        // The confirmation shows: Name | PG | SG | SF | PF | C | Active | Min | OF | DF | OI | DI | BH

        // Verify each value appears in the HTML in the correct context
        $this->assertMatchesRegularExpression('/<td>1<\/td>/', $confirmationHtml, 'PG value 1 should appear');
        $this->assertMatchesRegularExpression('/<td>30<\/td>/', $confirmationHtml, 'Min value 30 should appear');
        $this->assertMatchesRegularExpression('/<td>2<\/td>/', $confirmationHtml, 'OF or DI value 2 should appear');
        $this->assertMatchesRegularExpression('/<td>-1<\/td>/', $confirmationHtml, 'OI value -1 should appear');
    }

    /**
     * Test that all three outputs (confirmation, CSV, database) would receive the same data
     */
    public function testAllThreeOutputsReceiveSameProcessedData()
    {
        // Simulate form submission
        $postData = [
            'Name1' => 'Consistency Test Player',
            'pg1' => '2',
            'sg1' => '1',
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'active1' => '1',
            'min1' => '35',
            'OF1' => '3',
            'DF1' => '2',
            'OI1' => '-2',
            'DI1' => '1',
            'BH1' => '-1',
            'Injury1' => '0'
        ];

        // Process the submission
        $result = $this->processor->processSubmission($postData, 15);
        $processedData = $result['playerData'];

        // This processed data array is what gets passed to:
        // 1. DepthChartEntryRepository::updatePlayerDepthChart() - for database
        // 2. DepthChartEntryProcessor::generateCsvContent() - for CSV
        // 3. DepthChartEntryView::renderSubmissionResult() - for confirmation page

        // Generate all three outputs
        $csvContent = $this->processor->generateCsvContent($processedData);

        ob_start();
        $this->view->renderSubmissionResult('Test Team', $processedData, true);
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
            $player['active'],
            $player['min'],
            $player['of'],
            $player['df'],
            $player['oi'],
            $player['di'],
            $player['bh']
        );
        $this->assertStringContainsString($expectedCsvLine, $csvContent, 'CSV should contain exact values from processed data');

        // Verify confirmation page contains all these values
        $valuesToCheck = [
            $player['pg'],
            $player['sg'],
            $player['active'],
            $player['min'],
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
        // We can verify the array has the expected structure
        $this->assertArrayHasKey('pg', $player);
        $this->assertArrayHasKey('sg', $player);
        $this->assertArrayHasKey('of', $player);
        $this->assertArrayHasKey('df', $player);
        $this->assertArrayHasKey('oi', $player);
        $this->assertArrayHasKey('di', $player);
        $this->assertArrayHasKey('bh', $player);

        // Verify the values match what was submitted
        $this->assertEquals(2, $player['pg']);
        $this->assertEquals(1, $player['sg']);
        $this->assertEquals(35, $player['min']);
        $this->assertEquals(3, $player['of']);
        $this->assertEquals(2, $player['df']);
        $this->assertEquals(-2, $player['oi']);
        $this->assertEquals(1, $player['di']);
        $this->assertEquals(-1, $player['bh']);
    }

    /**
     * Test what a user would actually see: numeric codes vs human-readable labels
     * The FORM shows "Drive" but the CONFIRMATION and CSV show "2"
     * This is by design, but could be what users are reporting as "inconsistent"
     */
    public function testUserSeesNumericCodesInConfirmationAndCsv()
    {
        $playerData = [
            [
                'name' => 'Human Readable Test',
                'pg' => 1,
                'sg' => 0,
                'sf' => 0,
                'pf' => 0,
                'c' => 0,
                'active' => 1,
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
        $this->view->renderSubmissionResult('Test Team', $playerData, true);
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

        // THIS IS BY DESIGN: The form shows human-readable labels, but confirmation and CSV show numeric codes
        // If this is what users are reporting as "inconsistent", it's actually expected behavior,
        // not a bug - it's just different representations of the same data
    }
}
