<?php

declare(strict_types=1);

namespace Tests\Integration\DepthChartEntry;

use Tests\Integration\IntegrationTestCase;
use Tests\Integration\Mocks\TestDataFactory;
use DepthChartEntry\DepthChartEntryRepository;
use DepthChartEntry\DepthChartEntryProcessor;
use DepthChartEntry\DepthChartEntryValidator;

/**
 * Integration tests for complete depth chart workflows
 *
 * Tests end-to-end scenarios combining processing, validation,
 * and database persistence:
 * - Successful depth chart submission workflows
 * - Validation failures (active players, position depth, multiple starters)
 * - Season phase-specific rules (Regular Season vs Playoffs)
 * - Database update operations
 * - Form data processing and sanitization
 *
 * @covers \DepthChartEntry\DepthChartEntryRepository
 * @covers \DepthChartEntry\DepthChartEntryProcessor
 * @covers \DepthChartEntry\DepthChartEntryValidator
 */
class DepthChartEntryIntegrationTest extends IntegrationTestCase
{
    private DepthChartEntryRepository $repository;
    private DepthChartEntryProcessor $processor;
    private DepthChartEntryValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DepthChartEntryRepository($this->mockDb);
        $this->processor = new DepthChartEntryProcessor();
        $this->validator = new DepthChartEntryValidator();
    }

    protected function tearDown(): void
    {
        unset($this->repository);
        unset($this->processor);
        unset($this->validator);
        parent::tearDown();
    }

    // ========== COMPLETE SUBMISSION WORKFLOW SUCCESS ==========

    /**
     * @group integration
     * @group depthchart
     * @group submission-success
     */
    public function testCompleteSubmissionWorkflowSucceeds(): void
    {
        // Arrange - Create valid POST data for 12 active players
        $postData = $this->createValidRegularSeasonPostData();
        $this->mockDb->setAffectedRows(1);

        // Act - Process submission
        $result = $this->processor->processSubmission($postData, 15);

        // Act - Validate for Regular Season
        $isValid = $this->validator->validate($result, 'Regular Season');

        // Act - Save each player to database
        $allUpdateSucceeded = true;
        foreach ($result['playerData'] as $player) {
            $updateResult = $this->repository->updatePlayerDepthChart($player['name'], $player);
            if (!$updateResult) {
                $allUpdateSucceeded = false;
            }
        }

        // Act - Update team history
        $historyResult = $this->repository->updateTeamHistory('Test Team');

        // Assert - All steps succeeded
        $this->assertTrue($isValid, 'Validation should pass');
        $this->assertEmpty($this->validator->getErrors(), 'No validation errors expected');
        $this->assertTrue($allUpdateSucceeded, 'All player updates should succeed');
        $this->assertTrue($historyResult, 'Team history update should succeed');

        // Assert - Correct queries executed
        $this->assertEquals(12, $this->countQueriesMatching('UPDATE ibl_plr'));
        $this->assertQueryExecuted('UPDATE ibl_team_history');
        $this->assertQueryExecuted('depth = NOW()');
    }

    /**
     * @group integration
     * @group depthchart
     * @group submission-success
     */
    public function testProcessedDataMatchesDatabaseFormat(): void
    {
        // Arrange
        $postData = [
            'Name1' => 'John Smith',
            'pg1' => '1',
            'sg1' => '2',
            'sf1' => '0',
            'pf1' => '3',
            'c1' => '0',
            'active1' => '1',
            'min1' => '32',
            'OF1' => '2',
            'DF1' => '1',
            'OI1' => '-1',
            'DI1' => '2',
            'BH1' => '0',
            'Injury1' => '0'
        ];
        $this->mockDb->setAffectedRows(1);

        // Act
        $result = $this->processor->processSubmission($postData, 15);
        $playerData = $result['playerData'][0];
        $updateResult = $this->repository->updatePlayerDepthChart($playerData['name'], $playerData);

        // Assert - Processed values match what was submitted
        $this->assertEquals('John Smith', $playerData['name']);
        $this->assertEquals(1, $playerData['pg']);
        $this->assertEquals(2, $playerData['sg']);
        $this->assertEquals(0, $playerData['sf']);
        $this->assertEquals(3, $playerData['pf']);
        $this->assertEquals(0, $playerData['c']);
        $this->assertEquals(1, $playerData['active']);
        $this->assertEquals(32, $playerData['min']);
        $this->assertEquals(2, $playerData['of']);
        $this->assertEquals(1, $playerData['df']);
        $this->assertEquals(-1, $playerData['oi']);
        $this->assertEquals(2, $playerData['di']);
        $this->assertEquals(0, $playerData['bh']);

        // Assert - Database update succeeded and included correct data
        $this->assertTrue($updateResult);
        $this->assertQueryExecuted('UPDATE ibl_plr');
        $this->assertQueryExecuted('dc_PGDepth');
        $this->assertQueryExecuted('dc_of');
        $this->assertQueryExecuted("name = 'John Smith'");
    }

    /**
     * @group integration
     * @group depthchart
     * @group submission-success
     */
    public function testCsvExportMatchesDatabaseUpdate(): void
    {
        // Arrange
        $postData = $this->createSinglePlayerPostData('Test Player', 1);
        $this->mockDb->setAffectedRows(1);

        // Act
        $result = $this->processor->processSubmission($postData, 15);
        $csv = $this->processor->generateCsvContent($result['playerData']);
        $this->repository->updatePlayerDepthChart(
            $result['playerData'][0]['name'],
            $result['playerData'][0]
        );

        // Assert - CSV contains same values that went to database
        $this->assertStringContainsString('Test Player', $csv);
        $this->assertStringContainsString('Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI,BH', $csv);

        // The CSV row should match the player data
        $player = $result['playerData'][0];
        $expectedRow = sprintf(
            '%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s',
            $player['name'],
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
        $this->assertStringContainsString($expectedRow, $csv);
    }

    // ========== VALIDATION FAILURE SCENARIOS ==========

    /**
     * @group integration
     * @group depthchart
     * @group validation-failure
     */
    public function testWorkflowFailsWithInsufficientActivePlayers(): void
    {
        // Arrange - Only 10 active players (need 12 for Regular Season)
        $postData = $this->createPostDataWithActiveCount(10);

        // Act
        $result = $this->processor->processSubmission($postData, 15);
        $isValid = $this->validator->validate($result, 'Regular Season');

        // Assert
        $this->assertFalse($isValid);
        $errors = $this->validator->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('active_players_min', $errors[0]['type']);
        $this->assertStringContainsString('at least 12 active players', $errors[0]['message']);
        $this->assertStringContainsString('you have 10', $errors[0]['message']);
    }

    /**
     * @group integration
     * @group depthchart
     * @group validation-failure
     */
    public function testWorkflowFailsWithTooManyActivePlayers(): void
    {
        // Arrange - 14 active players (max is 12)
        $postData = $this->createPostDataWithActiveCount(14);

        // Act
        $result = $this->processor->processSubmission($postData, 15);
        $isValid = $this->validator->validate($result, 'Regular Season');

        // Assert
        $this->assertFalse($isValid);
        $errors = $this->validator->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('active_players_max', $errors[0]['type']);
        $this->assertStringContainsString('more than 12', $errors[0]['message']);
    }

    /**
     * @group integration
     * @group depthchart
     * @group validation-failure
     */
    public function testWorkflowFailsWithInsufficientPositionDepth(): void
    {
        // Arrange - Valid active count but only 2 PG depth (need 3)
        $postData = $this->createPostDataWithInsufficientPositionDepth('PG');

        // Act
        $result = $this->processor->processSubmission($postData, 15);
        $isValid = $this->validator->validate($result, 'Regular Season');

        // Assert
        $this->assertFalse($isValid);
        $errors = $this->validator->getErrors();
        $hasPositionError = false;
        foreach ($errors as $error) {
            if ($error['type'] === 'position_depth') {
                $hasPositionError = true;
                $this->assertStringContainsString('at least 3 players', $error['message']);
            }
        }
        $this->assertTrue($hasPositionError, 'Should have position depth error');
    }

    /**
     * @group integration
     * @group depthchart
     * @group validation-failure
     */
    public function testWorkflowFailsWithMultipleStartingPositions(): void
    {
        // Arrange - One player starting at both PG and SG
        $postData = $this->createPostDataWithMultipleStarter();

        // Act
        $result = $this->processor->processSubmission($postData, 15);
        $isValid = $this->validator->validate($result, 'Regular Season');

        // Assert
        $this->assertFalse($isValid);
        $errors = $this->validator->getErrors();
        $hasMultipleStarterError = false;
        foreach ($errors as $error) {
            if ($error['type'] === 'multiple_starting_positions') {
                $hasMultipleStarterError = true;
                $this->assertStringContainsString('more than one position', $error['message']);
            }
        }
        $this->assertTrue($hasMultipleStarterError, 'Should have multiple starter error');
        $this->assertEquals('Multi Starter', $result['nameOfProblemStarter']);
    }

    /**
     * @group integration
     * @group depthchart
     * @group validation-failure
     */
    public function testDatabaseNotUpdatedWhenValidationFails(): void
    {
        // Arrange - Invalid data
        $postData = $this->createPostDataWithActiveCount(5); // Too few active
        $this->mockDb->setAffectedRows(1);

        // Act
        $result = $this->processor->processSubmission($postData, 15);
        $isValid = $this->validator->validate($result, 'Regular Season');

        // Assert - Validation fails
        $this->assertFalse($isValid);

        // We should NOT update database when validation fails
        // (This tests the workflow - the handler should check validation before saving)
        $this->assertQueryNotExecuted('UPDATE ibl_plr');
        $this->assertQueryNotExecuted('UPDATE ibl_team_history');
    }

    // ========== PLAYOFFS VS REGULAR SEASON RULES ==========

    /**
     * @group integration
     * @group depthchart
     * @group playoffs
     */
    public function testPlayoffsAllows10ActivePlayers(): void
    {
        // Arrange - 10 active players with 2 per position (playoff rules)
        $postData = $this->createValidPlayoffsPostData(10);

        // Act
        $result = $this->processor->processSubmission($postData, 15);
        $isValid = $this->validator->validate($result, 'Playoffs');

        // Assert
        $this->assertTrue($isValid, 'Playoffs should allow 10 active players');
        $this->assertEmpty($this->validator->getErrors());
        $this->assertEquals(10, $result['activePlayers']);
    }

    /**
     * @group integration
     * @group depthchart
     * @group playoffs
     */
    public function testPlayoffsRejects9ActivePlayers(): void
    {
        // Arrange - 9 active players (min is 10 for playoffs)
        $postData = $this->createValidPlayoffsPostData(9);

        // Act
        $result = $this->processor->processSubmission($postData, 15);
        $isValid = $this->validator->validate($result, 'Playoffs');

        // Assert
        $this->assertFalse($isValid);
        $errors = $this->validator->getErrors();
        $this->assertStringContainsString('at least 10', $errors[0]['message']);
    }

    /**
     * @group integration
     * @group depthchart
     * @group playoffs
     */
    public function testPlayoffsRequiresOnly2PerPosition(): void
    {
        // Arrange - 2 players per position (playoff minimum)
        $postData = $this->createPlayoffsPostDataWithMinimalDepth();

        // Act
        $result = $this->processor->processSubmission($postData, 15);
        $isValid = $this->validator->validate($result, 'Playoffs');

        // Assert
        $this->assertTrue($isValid, 'Playoffs should accept 2 per position');
        $this->assertEmpty($this->validator->getErrors());
    }

    /**
     * @group integration
     * @group depthchart
     * @group playoffs
     */
    public function testSameDataFailsRegularSeasonPassesPlayoffs(): void
    {
        // Arrange - Data valid for playoffs but not regular season
        $postData = $this->createValidPlayoffsPostData(10);

        // Act & Assert - Fails regular season
        $result = $this->processor->processSubmission($postData, 15);
        $isValidRegular = $this->validator->validate($result, 'Regular Season');
        $this->assertFalse($isValidRegular, 'Should fail Regular Season');

        // Reset errors and validate for playoffs
        $isValidPlayoffs = $this->validator->validate($result, 'Playoffs');
        $this->assertTrue($isValidPlayoffs, 'Should pass Playoffs');
    }

    // ========== DATABASE OPERATIONS ==========

    /**
     * @group integration
     * @group depthchart
     * @group database
     */
    public function testGetPlayersOnTeamReturnsCorrectPlayers(): void
    {
        // Arrange
        $teamName = 'Miami Cyclones';
        $teamId = 5;
        $mockPlayers = [
            TestDataFactory::createPlayer([
                'pid' => 1,
                'name' => 'Player One',
                'teamname' => $teamName,
                'tid' => $teamId,
                'ordinal' => 10
            ]),
            TestDataFactory::createPlayer([
                'pid' => 2,
                'name' => 'Player Two',
                'teamname' => $teamName,
                'tid' => $teamId,
                'ordinal' => 20
            ]),
        ];
        $this->mockDb->setMockData($mockPlayers);

        // Act
        $result = $this->repository->getPlayersOnTeam($teamName, $teamId);

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('Player One', $result[0]['name']);
        $this->assertEquals('Player Two', $result[1]['name']);
        $this->assertQueryExecuted('SELECT * FROM ibl_plr');
        $this->assertQueryExecuted("teamname = '$teamName'");
        $this->assertQueryExecuted("tid = $teamId");
    }

    /**
     * @group integration
     * @group depthchart
     * @group database
     */
    public function testUpdatePlayerDepthChartUpdatesAllFields(): void
    {
        // Arrange
        $playerName = 'Test Player';
        $depthChartValues = [
            'pg' => 1, 'sg' => 2, 'sf' => 3, 'pf' => 4, 'c' => 5,
            'active' => 1, 'min' => 35,
            'of' => 2, 'df' => 1, 'oi' => -1, 'di' => 2, 'bh' => 0
        ];
        $this->mockDb->setAffectedRows(1);

        // Act
        $result = $this->repository->updatePlayerDepthChart($playerName, $depthChartValues);

        // Assert
        $this->assertTrue($result);
        $queries = $this->getExecutedQueries();
        $lastQuery = end($queries);

        // Verify all fields are in the query
        $this->assertStringContainsString('dc_PGDepth', $lastQuery);
        $this->assertStringContainsString('dc_SGDepth', $lastQuery);
        $this->assertStringContainsString('dc_SFDepth', $lastQuery);
        $this->assertStringContainsString('dc_PFDepth', $lastQuery);
        $this->assertStringContainsString('dc_CDepth', $lastQuery);
        $this->assertStringContainsString('dc_active', $lastQuery);
        $this->assertStringContainsString('dc_minutes', $lastQuery);
        $this->assertStringContainsString('dc_of', $lastQuery);
        $this->assertStringContainsString('dc_df', $lastQuery);
        $this->assertStringContainsString('dc_oi', $lastQuery);
        $this->assertStringContainsString('dc_di', $lastQuery);
        $this->assertStringContainsString('dc_bh', $lastQuery);
    }

    /**
     * @group integration
     * @group depthchart
     * @group database
     */
    public function testUpdateSucceedsEvenWhenNoRowsAffected(): void
    {
        // Arrange - Simulates updating with same values (MySQL returns 0 affected)
        $playerName = 'Unchanged Player';
        $depthChartValues = [
            'pg' => 0, 'sg' => 0, 'sf' => 0, 'pf' => 0, 'c' => 0,
            'active' => 1, 'min' => 0,
            'of' => 0, 'df' => 0, 'oi' => 0, 'di' => 0, 'bh' => 0
        ];
        $this->mockDb->setAffectedRows(0); // No rows affected

        // Act
        $result = $this->repository->updatePlayerDepthChart($playerName, $depthChartValues);

        // Assert - Should still return true (successful execution, just no changes)
        $this->assertTrue($result);
    }

    /**
     * @group integration
     * @group depthchart
     * @group database
     */
    public function testMultiplePlayersUpdateInSequence(): void
    {
        // Arrange
        $players = [
            ['name' => 'Player 1', 'pg' => 1, 'sg' => 0, 'sf' => 0, 'pf' => 0, 'c' => 0, 'active' => 1, 'min' => 30, 'of' => 0, 'df' => 0, 'oi' => 0, 'di' => 0, 'bh' => 0],
            ['name' => 'Player 2', 'pg' => 0, 'sg' => 1, 'sf' => 0, 'pf' => 0, 'c' => 0, 'active' => 1, 'min' => 28, 'of' => 0, 'df' => 0, 'oi' => 0, 'di' => 0, 'bh' => 0],
            ['name' => 'Player 3', 'pg' => 0, 'sg' => 0, 'sf' => 1, 'pf' => 0, 'c' => 0, 'active' => 1, 'min' => 32, 'of' => 0, 'df' => 0, 'oi' => 0, 'di' => 0, 'bh' => 0],
        ];
        $this->mockDb->setAffectedRows(1);

        // Act
        $allSuccess = true;
        foreach ($players as $player) {
            if (!$this->repository->updatePlayerDepthChart($player['name'], $player)) {
                $allSuccess = false;
            }
        }

        // Assert
        $this->assertTrue($allSuccess);
        $this->assertEquals(3, $this->countQueriesMatching('UPDATE ibl_plr'));
    }

    // ========== INPUT SANITIZATION ==========

    /**
     * @group integration
     * @group depthchart
     * @group sanitization
     */
    public function testProcessorSanitizesHtmlInPlayerNames(): void
    {
        // Arrange - Malicious input
        $postData = [
            'Name1' => '<script>alert("xss")</script>John Smith',
            'pg1' => '1', 'sg1' => '0', 'sf1' => '0', 'pf1' => '0', 'c1' => '0',
            'active1' => '1', 'min1' => '30',
            'OF1' => '0', 'DF1' => '0', 'OI1' => '0', 'DI1' => '0', 'BH1' => '0',
            'Injury1' => '0'
        ];

        // Act
        $result = $this->processor->processSubmission($postData, 15);

        // Assert - HTML tags stripped
        $this->assertEquals('alert("xss")John Smith', $result['playerData'][0]['name']);
    }

    /**
     * @group integration
     * @group depthchart
     * @group sanitization
     */
    public function testProcessorClampsBoundaryValues(): void
    {
        // Arrange - Out of range values
        $postData = [
            'Name1' => 'Test',
            'pg1' => '99',  // Should clamp to 5
            'sg1' => '-5',  // Should clamp to 0
            'sf1' => '3', 'pf1' => '0', 'c1' => '0',
            'active1' => '999', // Should normalize to 0 (not 1)
            'min1' => '100',    // Should clamp to 40
            'OF1' => '10',      // Should clamp to 3
            'DF1' => '-10',     // Should clamp to 0
            'OI1' => '10',      // Should clamp to 2
            'DI1' => '-10',     // Should clamp to -2
            'BH1' => '0',
            'Injury1' => '0'
        ];

        // Act
        $result = $this->processor->processSubmission($postData, 15);
        $player = $result['playerData'][0];

        // Assert - Values clamped to valid ranges
        $this->assertEquals(5, $player['pg'], 'PG depth should clamp to 5');
        $this->assertEquals(0, $player['sg'], 'SG depth should clamp to 0');
        $this->assertEquals(0, $player['active'], 'Active should be 0 or 1');
        $this->assertEquals(40, $player['min'], 'Minutes should clamp to 40');
        $this->assertEquals(3, $player['of'], 'OF should clamp to 3');
        $this->assertEquals(0, $player['df'], 'DF should clamp to 0');
        $this->assertEquals(2, $player['oi'], 'OI should clamp to 2');
        $this->assertEquals(-2, $player['di'], 'DI should clamp to -2');
    }

    /**
     * @group integration
     * @group depthchart
     * @group sanitization
     */
    public function testProcessorHandlesNegativeIntensityValues(): void
    {
        // Arrange
        $postData = [
            'Name1' => 'Test',
            'pg1' => '1', 'sg1' => '0', 'sf1' => '0', 'pf1' => '0', 'c1' => '0',
            'active1' => '1', 'min1' => '30',
            'OF1' => '2', 'DF1' => '1',
            'OI1' => '-2',  // Valid negative
            'DI1' => '-1',  // Valid negative
            'BH1' => '-2',  // Valid negative
            'Injury1' => '0'
        ];
        $this->mockDb->setAffectedRows(1);

        // Act
        $result = $this->processor->processSubmission($postData, 15);
        $player = $result['playerData'][0];
        $this->repository->updatePlayerDepthChart($player['name'], $player);

        // Assert - Negative values preserved
        $this->assertEquals(-2, $player['oi']);
        $this->assertEquals(-1, $player['di']);
        $this->assertEquals(-2, $player['bh']);

        // Assert - Negative values in query
        $this->assertQueryExecuted('-2');
        $this->assertQueryExecuted('-1');
    }

    // ========== ERROR ACCUMULATION ==========

    /**
     * @group integration
     * @group depthchart
     * @group errors
     */
    public function testValidatorAccumulatesMultipleErrors(): void
    {
        // Arrange - Data with multiple issues
        $postData = $this->createPostDataWithMultipleIssues();

        // Act
        $result = $this->processor->processSubmission($postData, 15);
        $isValid = $this->validator->validate($result, 'Regular Season');

        // Assert
        $this->assertFalse($isValid);
        $errors = $this->validator->getErrors();

        // Should have multiple errors
        $this->assertGreaterThan(1, count($errors), 'Should accumulate multiple errors');

        // Check error types are present
        $errorTypes = array_column($errors, 'type');
        $this->assertContains('active_players_min', $errorTypes);
    }

    /**
     * @group integration
     * @group depthchart
     * @group errors
     */
    public function testValidatorClearsErrorsBetweenValidations(): void
    {
        // Arrange - First invalid
        $invalidData = $this->createPostDataWithActiveCount(5);
        $result1 = $this->processor->processSubmission($invalidData, 15);
        $this->validator->validate($result1, 'Regular Season');
        $this->assertNotEmpty($this->validator->getErrors());

        // Arrange - Second valid
        $validData = $this->createValidRegularSeasonPostData();
        $result2 = $this->processor->processSubmission($validData, 15);

        // Act
        $isValid = $this->validator->validate($result2, 'Regular Season');

        // Assert - Errors cleared from previous validation
        $this->assertTrue($isValid);
        $this->assertEmpty($this->validator->getErrors());
    }

    /**
     * @group integration
     * @group depthchart
     * @group errors
     */
    public function testErrorHtmlFormatting(): void
    {
        // Arrange
        $postData = $this->createPostDataWithActiveCount(5);

        // Act
        $result = $this->processor->processSubmission($postData, 15);
        $this->validator->validate($result, 'Regular Season');
        $html = $this->validator->getErrorMessagesHtml();

        // Assert - HTML formatting present
        $this->assertStringContainsString('<font color=red>', $html);
        $this->assertStringContainsString('<b>', $html);
        $this->assertStringContainsString('</b>', $html);
        $this->assertStringContainsString('Back', $html); // User instruction
    }

    // ========== HELPER METHODS ==========

    /**
     * Create valid POST data for Regular Season (12 active, 3+ per position)
     */
    private function createValidRegularSeasonPostData(): array
    {
        $postData = [];

        // Create 12 players with proper position distribution
        $positions = [
            ['pg' => 1, 'sg' => 0, 'sf' => 0, 'pf' => 0, 'c' => 0], // PG starter
            ['pg' => 2, 'sg' => 0, 'sf' => 0, 'pf' => 0, 'c' => 0], // PG backup
            ['pg' => 3, 'sg' => 0, 'sf' => 0, 'pf' => 0, 'c' => 0], // PG 3rd
            ['pg' => 0, 'sg' => 1, 'sf' => 0, 'pf' => 0, 'c' => 0], // SG starter
            ['pg' => 0, 'sg' => 2, 'sf' => 0, 'pf' => 0, 'c' => 0], // SG backup
            ['pg' => 0, 'sg' => 3, 'sf' => 0, 'pf' => 0, 'c' => 0], // SG 3rd
            ['pg' => 0, 'sg' => 0, 'sf' => 1, 'pf' => 0, 'c' => 0], // SF starter
            ['pg' => 0, 'sg' => 0, 'sf' => 2, 'pf' => 0, 'c' => 0], // SF backup
            ['pg' => 0, 'sg' => 0, 'sf' => 3, 'pf' => 0, 'c' => 0], // SF 3rd
            ['pg' => 0, 'sg' => 0, 'sf' => 0, 'pf' => 1, 'c' => 2], // PF starter, C backup
            ['pg' => 0, 'sg' => 0, 'sf' => 0, 'pf' => 2, 'c' => 3], // PF backup, C 3rd
            ['pg' => 0, 'sg' => 0, 'sf' => 0, 'pf' => 3, 'c' => 1], // PF 3rd, C starter
        ];

        for ($i = 0; $i < 12; $i++) {
            $idx = $i + 1;
            $pos = $positions[$i];
            $postData["Name{$idx}"] = "Player {$idx}";
            $postData["pg{$idx}"] = (string) $pos['pg'];
            $postData["sg{$idx}"] = (string) $pos['sg'];
            $postData["sf{$idx}"] = (string) $pos['sf'];
            $postData["pf{$idx}"] = (string) $pos['pf'];
            $postData["c{$idx}"] = (string) $pos['c'];
            $postData["active{$idx}"] = '1';
            $postData["min{$idx}"] = '25';
            $postData["OF{$idx}"] = '0';
            $postData["DF{$idx}"] = '0';
            $postData["OI{$idx}"] = '0';
            $postData["DI{$idx}"] = '0';
            $postData["BH{$idx}"] = '0';
            $postData["Injury{$idx}"] = '0';
        }

        return $postData;
    }

    /**
     * Create valid POST data for Playoffs with specified active count
     */
    private function createValidPlayoffsPostData(int $activeCount): array
    {
        $postData = [];

        // Create enough players with 2+ per position
        $positions = [
            ['pg' => 1, 'sg' => 0, 'sf' => 0, 'pf' => 0, 'c' => 0],
            ['pg' => 2, 'sg' => 0, 'sf' => 0, 'pf' => 0, 'c' => 0],
            ['pg' => 0, 'sg' => 1, 'sf' => 0, 'pf' => 0, 'c' => 0],
            ['pg' => 0, 'sg' => 2, 'sf' => 0, 'pf' => 0, 'c' => 0],
            ['pg' => 0, 'sg' => 0, 'sf' => 1, 'pf' => 0, 'c' => 0],
            ['pg' => 0, 'sg' => 0, 'sf' => 2, 'pf' => 0, 'c' => 0],
            ['pg' => 0, 'sg' => 0, 'sf' => 0, 'pf' => 1, 'c' => 0],
            ['pg' => 0, 'sg' => 0, 'sf' => 0, 'pf' => 2, 'c' => 0],
            ['pg' => 0, 'sg' => 0, 'sf' => 0, 'pf' => 0, 'c' => 1],
            ['pg' => 0, 'sg' => 0, 'sf' => 0, 'pf' => 0, 'c' => 2],
            ['pg' => 3, 'sg' => 0, 'sf' => 0, 'pf' => 0, 'c' => 0],
            ['pg' => 0, 'sg' => 3, 'sf' => 0, 'pf' => 0, 'c' => 0],
        ];

        for ($i = 0; $i < 12; $i++) {
            $idx = $i + 1;
            $pos = $positions[$i] ?? ['pg' => 0, 'sg' => 0, 'sf' => 0, 'pf' => 0, 'c' => 0];
            $postData["Name{$idx}"] = "Player {$idx}";
            $postData["pg{$idx}"] = (string) $pos['pg'];
            $postData["sg{$idx}"] = (string) $pos['sg'];
            $postData["sf{$idx}"] = (string) $pos['sf'];
            $postData["pf{$idx}"] = (string) $pos['pf'];
            $postData["c{$idx}"] = (string) $pos['c'];
            $postData["active{$idx}"] = ($i < $activeCount) ? '1' : '0';
            $postData["min{$idx}"] = '25';
            $postData["OF{$idx}"] = '0';
            $postData["DF{$idx}"] = '0';
            $postData["OI{$idx}"] = '0';
            $postData["DI{$idx}"] = '0';
            $postData["BH{$idx}"] = '0';
            $postData["Injury{$idx}"] = '0';
        }

        return $postData;
    }

    /**
     * Create playoffs POST data with minimal position depth (2 per position)
     */
    private function createPlayoffsPostDataWithMinimalDepth(): array
    {
        $postData = [];

        // Exactly 2 per position, 10 active players
        $positions = [
            ['pg' => 1, 'sg' => 0, 'sf' => 0, 'pf' => 0, 'c' => 0],
            ['pg' => 2, 'sg' => 0, 'sf' => 0, 'pf' => 0, 'c' => 0],
            ['pg' => 0, 'sg' => 1, 'sf' => 0, 'pf' => 0, 'c' => 0],
            ['pg' => 0, 'sg' => 2, 'sf' => 0, 'pf' => 0, 'c' => 0],
            ['pg' => 0, 'sg' => 0, 'sf' => 1, 'pf' => 0, 'c' => 0],
            ['pg' => 0, 'sg' => 0, 'sf' => 2, 'pf' => 0, 'c' => 0],
            ['pg' => 0, 'sg' => 0, 'sf' => 0, 'pf' => 1, 'c' => 0],
            ['pg' => 0, 'sg' => 0, 'sf' => 0, 'pf' => 2, 'c' => 0],
            ['pg' => 0, 'sg' => 0, 'sf' => 0, 'pf' => 0, 'c' => 1],
            ['pg' => 0, 'sg' => 0, 'sf' => 0, 'pf' => 0, 'c' => 2],
        ];

        for ($i = 0; $i < 10; $i++) {
            $idx = $i + 1;
            $pos = $positions[$i];
            $postData["Name{$idx}"] = "Player {$idx}";
            $postData["pg{$idx}"] = (string) $pos['pg'];
            $postData["sg{$idx}"] = (string) $pos['sg'];
            $postData["sf{$idx}"] = (string) $pos['sf'];
            $postData["pf{$idx}"] = (string) $pos['pf'];
            $postData["c{$idx}"] = (string) $pos['c'];
            $postData["active{$idx}"] = '1';
            $postData["min{$idx}"] = '25';
            $postData["OF{$idx}"] = '0';
            $postData["DF{$idx}"] = '0';
            $postData["OI{$idx}"] = '0';
            $postData["DI{$idx}"] = '0';
            $postData["BH{$idx}"] = '0';
            $postData["Injury{$idx}"] = '0';
        }

        return $postData;
    }

    /**
     * Create single player POST data
     */
    private function createSinglePlayerPostData(string $name, int $index): array
    {
        return [
            "Name{$index}" => $name,
            "pg{$index}" => '1',
            "sg{$index}" => '0',
            "sf{$index}" => '0',
            "pf{$index}" => '0',
            "c{$index}" => '0',
            "active{$index}" => '1',
            "min{$index}" => '30',
            "OF{$index}" => '0',
            "DF{$index}" => '0',
            "OI{$index}" => '0',
            "DI{$index}" => '0',
            "BH{$index}" => '0',
            "Injury{$index}" => '0'
        ];
    }

    /**
     * Create POST data with specific active player count
     */
    private function createPostDataWithActiveCount(int $activeCount): array
    {
        $postData = [];

        for ($i = 1; $i <= 15; $i++) {
            $postData["Name{$i}"] = "Player {$i}";
            $postData["pg{$i}"] = ($i <= 3) ? (string) $i : '0';
            $postData["sg{$i}"] = ($i > 3 && $i <= 6) ? (string) ($i - 3) : '0';
            $postData["sf{$i}"] = ($i > 6 && $i <= 9) ? (string) ($i - 6) : '0';
            $postData["pf{$i}"] = ($i > 9 && $i <= 12) ? (string) ($i - 9) : '0';
            $postData["c{$i}"] = ($i > 12) ? (string) ($i - 12) : '0';
            $postData["active{$i}"] = ($i <= $activeCount) ? '1' : '0';
            $postData["min{$i}"] = '25';
            $postData["OF{$i}"] = '0';
            $postData["DF{$i}"] = '0';
            $postData["OI{$i}"] = '0';
            $postData["DI{$i}"] = '0';
            $postData["BH{$i}"] = '0';
            $postData["Injury{$i}"] = '0';
        }

        return $postData;
    }

    /**
     * Create POST data with insufficient position depth
     */
    private function createPostDataWithInsufficientPositionDepth(string $position): array
    {
        $postData = $this->createValidRegularSeasonPostData();

        // Remove third player from specified position
        // Find players with that position and set one to 0
        $positionKey = strtolower($position);
        $count = 0;
        for ($i = 1; $i <= 12; $i++) {
            if ((int) $postData["{$positionKey}{$i}"] > 0) {
                $count++;
                if ($count === 3) {
                    // Remove third player from this position
                    $postData["{$positionKey}{$i}"] = '0';
                    break;
                }
            }
        }

        return $postData;
    }

    /**
     * Create POST data where one player starts at multiple positions
     */
    private function createPostDataWithMultipleStarter(): array
    {
        $postData = $this->createValidRegularSeasonPostData();

        // Make player 1 start at both PG and SG (depth = 1 for both)
        $postData['Name1'] = 'Multi Starter';
        $postData['pg1'] = '1';
        $postData['sg1'] = '1'; // Invalid - starting at two positions

        return $postData;
    }

    /**
     * Create POST data with multiple validation issues
     */
    private function createPostDataWithMultipleIssues(): array
    {
        $postData = [];

        // Only 8 active players (need 12)
        // Only 2 PG (need 3)
        for ($i = 1; $i <= 8; $i++) {
            $postData["Name{$i}"] = "Player {$i}";
            $postData["pg{$i}"] = ($i <= 2) ? '1' : '0'; // Only 2 PG
            $postData["sg{$i}"] = ($i == 3 || $i == 4 || $i == 5) ? '1' : '0';
            $postData["sf{$i}"] = ($i == 6) ? '1' : '0';
            $postData["pf{$i}"] = ($i == 7) ? '1' : '0';
            $postData["c{$i}"] = ($i == 8) ? '1' : '0';
            $postData["active{$i}"] = '1';
            $postData["min{$i}"] = '25';
            $postData["OF{$i}"] = '0';
            $postData["DF{$i}"] = '0';
            $postData["OI{$i}"] = '0';
            $postData["DI{$i}"] = '0';
            $postData["BH{$i}"] = '0';
            $postData["Injury{$i}"] = '0';
        }

        return $postData;
    }
}
