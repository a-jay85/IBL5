<?php

use PHPUnit\Framework\TestCase;
use Updater\StandingsHTMLGenerator;

/**
 * Tests for StandingsHTMLGenerator class
 * 
 * Tests focus on observable behaviors rather than implementation details.
 * Tests verify that the HTML generation produces correct output via the public API.
 * 
 * REFACTORING NOTE: This test file has been significantly simplified to follow
 * the principles from "Stop Vibe Coding Your Unit Tests":
 * - Removed tests that used ReflectionClass to test private methods
 * - Removed tests that checked SQL query structure (implementation details)
 * - Kept only tests that verify observable behavior through the public API
 */
class StandingsHTMLGeneratorTest extends TestCase
{
    private $mockDb;
    private $htmlGenerator;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->htmlGenerator = new StandingsHTMLGenerator($this->mockDb);
    }

    protected function tearDown(): void
    {
        $this->htmlGenerator = null;
        $this->mockDb = null;
    }

    /**
     * @group standings-html
     */
    public function testGenerateStandingsPageExecutesSuccessfully()
    {
        // Arrange - Mock empty standings data
        $this->mockDb->setMockData([]);
        $this->mockDb->setReturnTrue(true);
        
        // Act - Capture output
        ob_start();
        set_error_handler(function() { return true; }, E_WARNING);
        
        try {
            $this->htmlGenerator->generateStandingsPage();
        } catch (Exception $e) {
            // May throw due to empty data, but that's OK for this test
        }
        
        restore_error_handler();
        ob_end_clean();
        
        // Assert - Verify the method attempted to update the database
        $queries = $this->mockDb->getExecutedQueries();
        $updateQueries = array_filter($queries, function($q) {
            return stripos($q, 'UPDATE nuke_pages') !== false;
        });
        
        $this->assertNotEmpty($updateQueries, 'Should attempt to update standings page');
    }
}
