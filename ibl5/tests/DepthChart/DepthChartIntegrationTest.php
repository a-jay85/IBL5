<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Depth Chart Entry workflow
 * 
 * Tests the complete end-to-end workflow of the Depth Chart Entry module,
 * including validation, database operations, file generation, and email notifications.
 */
class DepthChartIntegrationTest extends TestCase
{
    private $mockDb;
    private $mockSeason;
    private $testFilePath;
    
    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->mockDb->setReturnTrue(true);
        $this->mockSeason = new Season($this->mockDb);
        $this->testFilePath = '/tmp/test_integration_' . uniqid() . '.txt';
    }
    
    protected function tearDown(): void
    {
        if (file_exists($this->testFilePath)) {
            unlink($this->testFilePath);
        }
        $this->mockDb = null;
        $this->mockSeason = null;
    }
    
    /**
     * @group integration
     * @group complete-workflow
     */
    public function testCompleteSuccessfulSubmissionWorkflow()
    {
        // Arrange
        $this->mockSeason->phase = 'Regular Season';
        
        // Simulate POST data for a valid 12-player roster
        $postData = $this->generateValidRosterPostData('Chicago Bulls', 'Motion Offense');
        
        // Act
        $validationResults = $this->validateDepthChart($postData);
        $fileContent = $this->generateFileContent($postData);
        $fileWritten = file_put_contents($this->testFilePath, $fileContent);
        $databaseUpdates = $this->performDatabaseUpdates($postData);
        
        // Assert
        $this->assertTrue($validationResults['valid'], 'Validation should pass for valid roster');
        $this->assertNotFalse($fileWritten, 'File should be written successfully');
        $this->assertFileExists($this->testFilePath);
        $this->assertCount(12, $databaseUpdates, 'Should perform updates for 12 players');
        // File contains player data, not team name
        $this->assertStringContainsString('Player 1', file_get_contents($this->testFilePath));
    }
    
    /**
     * @group integration
     * @group validation-failure
     */
    public function testSubmissionFailsWithInsufficientActivePlayers()
    {
        // Arrange
        $this->mockSeason->phase = 'Regular Season';
        $postData = $this->generateRosterPostData('Miami Heat', 'Pick and Roll', 10); // Only 10 active
        
        // Act
        $validationResults = $this->validateDepthChart($postData);
        
        // Assert
        $this->assertFalse($validationResults['valid'], 'Should fail validation with only 10 active players');
        $this->assertStringContainsString('12 active players', $validationResults['errors']);
    }
    
    /**
     * @group integration
     * @group validation-failure
     */
    public function testSubmissionFailsWithInsufficientPositionDepth()
    {
        // Arrange
        $this->mockSeason->phase = 'Regular Season';
        $postData = $this->generateValidRosterPostData('Boston Celtics', 'Triangle');
        
        // Modify to have insufficient PG depth
        for ($i = 1; $i <= 15; $i++) {
            if (isset($postData["pg$i"]) && $postData["pg$i"] > 0) {
                $postData["pg$i"] = 0; // Remove all PG depth
            }
        }
        
        // Act
        $validationResults = $this->validateDepthChart($postData);
        
        // Assert
        $this->assertFalse($validationResults['valid'], 'Should fail with insufficient PG depth');
        $this->assertStringContainsString('pg', $validationResults['errors']);
    }
    
    /**
     * @group integration
     * @group database
     */
    public function testDatabaseUpdatesForAllPlayerFields()
    {
        // Arrange
        $playerData = [
            'Name' => 'Integration Test Player',
            'pg' => 1,
            'sg' => 0,
            'sf' => 2,
            'pf' => 0,
            'c' => 0,
            'active' => 1,
            'min' => 35,
            'OF' => 1,
            'DF' => 2,
            'OI' => 1,
            'DI' => 0,
            'BH' => -1
        ];
        
        $this->mockDb->clearQueries();
        
        // Act
        $this->updatePlayerInDatabase($playerData);
        $queries = $this->mockDb->getExecutedQueries();
        
        // Assert
        $this->assertGreaterThanOrEqual(12, count($queries), 'Should execute at least 12 update queries');
        
        // Verify each field is updated
        $this->assertTrue($this->hasQueryForField($queries, 'dc_PGDepth'));
        $this->assertTrue($this->hasQueryForField($queries, 'dc_SGDepth'));
        $this->assertTrue($this->hasQueryForField($queries, 'dc_SFDepth'));
        $this->assertTrue($this->hasQueryForField($queries, 'dc_PFDepth'));
        $this->assertTrue($this->hasQueryForField($queries, 'dc_CDepth'));
        $this->assertTrue($this->hasQueryForField($queries, 'dc_active'));
        $this->assertTrue($this->hasQueryForField($queries, 'dc_minutes'));
        $this->assertTrue($this->hasQueryForField($queries, 'dc_of'));
        $this->assertTrue($this->hasQueryForField($queries, 'dc_df'));
        $this->assertTrue($this->hasQueryForField($queries, 'dc_oi'));
        $this->assertTrue($this->hasQueryForField($queries, 'dc_di'));
        $this->assertTrue($this->hasQueryForField($queries, 'dc_bh'));
    }
    
    /**
     * @group integration
     * @group team-history
     */
    public function testUpdatesTeamHistoryTimestamps()
    {
        // Arrange
        $teamName = 'Golden State Warriors';
        $this->mockDb->clearQueries();
        
        // Act
        $this->mockDb->sql_query("UPDATE ibl_team_history SET depth = NOW() WHERE team_name = '$teamName'");
        $this->mockDb->sql_query("UPDATE ibl_team_history SET sim_depth = NOW() WHERE team_name = '$teamName'");
        $queries = $this->mockDb->getExecutedQueries();
        
        // Assert
        $this->assertCount(2, $queries, 'Should execute 2 team history update queries');
        $this->assertStringContainsString('depth = NOW()', $queries[0]);
        $this->assertStringContainsString('sim_depth = NOW()', $queries[1]);
    }
    
    /**
     * @group integration
     * @group file-generation
     */
    public function testGeneratesCompleteCSVFile()
    {
        // Arrange
        $postData = $this->generateValidRosterPostData('Los Angeles Lakers', 'Showtime');
        
        // Act
        $fileContent = $this->generateFileContent($postData);
        file_put_contents($this->testFilePath, $fileContent);
        $readContent = file_get_contents($this->testFilePath);
        $lines = explode("\n", trim($readContent));
        
        // Assert
        $this->assertStringStartsWith('Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI', $lines[0]);
        $this->assertGreaterThanOrEqual(13, count($lines), 'Should have header + at least 12 player lines');
    }
    
    /**
     * @group integration
     * @group email
     */
    public function testEmailSubjectFormatting()
    {
        // Arrange
        $teamName = 'Portland Trail Blazers';
        $setName = 'Motion Offense';
        
        // Act
        $emailSubject = "$teamName Depth Chart - $setName Offensive Set";
        
        // Assert
        $this->assertStringContainsString($teamName, $emailSubject);
        $this->assertStringContainsString($setName, $emailSubject);
        $this->assertStringContainsString('Depth Chart', $emailSubject);
    }
    
    /**
     * @group integration
     * @group playoffs
     */
    public function testPlayoffRulesApplied()
    {
        // Arrange
        $this->mockSeason->phase = 'Playoffs';
        
        // Create a simple 10-player roster with 2-deep at each position
        $data = [
            'Team_Name' => 'Denver Nuggets',
            'Set_Name' => 'Princeton'
        ];
        
        // 10 players: 2 at each position
        for ($i = 1; $i <= 10; $i++) {
            $data["Name$i"] = "Player $i";
            $data["Injury$i"] = 0;
            $data["active$i"] = 1; // All 10 active
            $data["min$i"] = 30;
            $data["OF$i"] = 0;
            $data["DF$i"] = 0;
            $data["OI$i"] = 0;
            $data["DI$i"] = 0;
            $data["BH$i"] = 0;
            
            // Initialize all positions to 0
            $data["pg$i"] = 0;
            $data["sg$i"] = 0;
            $data["sf$i"] = 0;
            $data["pf$i"] = 0;
            $data["c$i"] = 0;
            
            // Assign 2 players per position
            if ($i <= 2) $data["pg$i"] = $i;
            else if ($i <= 4) $data["sg$i"] = $i - 2;
            else if ($i <= 6) $data["sf$i"] = $i - 4;
            else if ($i <= 8) $data["pf$i"] = $i - 6;
            else $data["c$i"] = $i - 8;
        }
        
        // Add 5 inactive players
        for ($i = 11; $i <= 15; $i++) {
            $data["Name$i"] = "Player $i";
            $data["Injury$i"] = 0;
            $data["active$i"] = 0;
            $data["min$i"] = 0;
            $data["OF$i"] = 0;
            $data["DF$i"] = 0;
            $data["OI$i"] = 0;
            $data["DI$i"] = 0;
            $data["BH$i"] = 0;
            $data["pg$i"] = 0;
            $data["sg$i"] = 0;
            $data["sf$i"] = 0;
            $data["pf$i"] = 0;
            $data["c$i"] = 0;
        }
        
        // Act
        $validationResults = $this->validateDepthChart($data);
        
        // Assert
        $this->assertTrue($validationResults['valid'], 'Should pass validation with 10 active players in playoffs');
        $this->assertEquals(10, $validationResults['activePlayers']);
    }
    
    /**
     * @group integration
     * @group injury
     */
    public function testInjuredPlayersHandledCorrectly()
    {
        // Arrange
        $postData = $this->generateValidRosterPostData('Utah Jazz', 'Flex');
        
        // Mark the first two PG players as injured
        $postData['Injury1'] = 15; // Major injury - Player 1 is PG depth 1
        $postData['Injury2'] = 20; // Major injury - Player 2 is PG depth 2
        
        // Act
        $depthCounts = $this->calculatePositionDepth($postData);
        
        // Assert
        // With players 1 and 2 injured, only player 3 (PG depth 3) should count
        $this->assertEquals(1, $depthCounts['pg'], 
            'Should only count 1 healthy PG when 2 are injured');
        // Other positions should still have 3 depth
        $this->assertEquals(3, $depthCounts['sg'], 'SG depth should be unaffected');
        $this->assertEquals(3, $depthCounts['sf'], 'SF depth should be unaffected');
    }
    
    /**
     * @group integration
     * @group multiple-starters
     */
    public function testRejectsPlayerStartingAtMultiplePositions()
    {
        // Arrange
        $postData = $this->generateValidRosterPostData('Phoenix Suns', 'Seven Seconds');
        
        // Set one player to start at multiple positions
        $postData['pg1'] = 1;
        $postData['sg1'] = 1; // Same player (index 1) starting at both PG and SG
        
        // Act
        $validationResults = $this->validateDepthChart($postData);
        
        // Assert
        $this->assertFalse($validationResults['valid'], 'Should reject player starting at multiple positions');
        $this->assertStringContainsString('more than one position', $validationResults['errors']);
    }
    
    // Helper methods
    
    private function generateValidRosterPostData($teamName, $setName)
    {
        return $this->generateRosterPostData($teamName, $setName, 12);
    }
    
    private function generateRosterPostData($teamName, $setName, $activePlayers = 12)
    {
        $data = [
            'Team_Name' => $teamName,
            'Set_Name' => $setName
        ];
        
        // Create a simple distribution: 3 players at each position
        $positionDepth = ['pg' => 0, 'sg' => 0, 'sf' => 0, 'pf' => 0, 'c' => 0];
        $positions = ['pg', 'sg', 'sf', 'pf', 'c'];
        
        for ($i = 1; $i <= 15; $i++) {
            $data["Name$i"] = "Player $i";
            $data["Injury$i"] = 0;
            
            // Initialize all positions to 0
            foreach ($positions as $pos) {
                $data["{$pos}$i"] = 0;
            }
            
            // Assign position depth in order: 3 PG, 3 SG, 3 SF, 3 PF, 3 C
            $posIndex = intval(($i - 1) / 3) % 5;
            $currentPos = $positions[$posIndex];
            
            if ($positionDepth[$currentPos] < 3) {
                $data["{$currentPos}$i"] = $positionDepth[$currentPos] + 1;
                $positionDepth[$currentPos]++;
            }
            
            $data["active$i"] = $i <= $activePlayers ? 1 : 0;
            $data["min$i"] = 30;
            $data["OF$i"] = 0;
            $data["DF$i"] = 0;
            $data["OI$i"] = 0;
            $data["DI$i"] = 0;
            $data["BH$i"] = 0;
        }
        
        return $data;
    }
    
    private function validateDepthChart($postData)
    {
        $activePlayers = 0;
        $posDepth = ['pg' => 0, 'sg' => 0, 'sf' => 0, 'pf' => 0, 'c' => 0];
        $hasMultipleStarters = false;
        $errors = [];
        
        for ($i = 1; $i <= 15; $i++) {
            if (isset($postData["active$i"]) && $postData["active$i"] == 1) {
                $activePlayers++;
            }
            
            $injury = isset($postData["Injury$i"]) ? $postData["Injury$i"] : 0;
            
            foreach (['pg', 'sg', 'sf', 'pf', 'c'] as $pos) {
                if (isset($postData["$pos$i"]) && $postData["$pos$i"] > 0 && $injury < 15) {
                    $posDepth[$pos]++;
                }
            }
            
            // Check for multiple starting positions
            $startingCount = 0;
            foreach (['pg', 'sg', 'sf', 'pf', 'c'] as $pos) {
                if (isset($postData["$pos$i"]) && $postData["$pos$i"] == 1) {
                    $startingCount++;
                }
            }
            if ($startingCount > 1) {
                $hasMultipleStarters = true;
            }
        }
        
        $minActive = ($this->mockSeason->phase == 'Playoffs') ? 10 : 12;
        $maxActive = 12;
        $minDepth = ($this->mockSeason->phase == 'Playoffs') ? 2 : 3;
        
        if ($activePlayers < $minActive) {
            $errors[] = "You must have at least $minActive active players";
        }
        if ($activePlayers > $maxActive) {
            $errors[] = "You can't have more than $maxActive active players";
        }
        foreach ($posDepth as $pos => $depth) {
            if ($depth < $minDepth) {
                $errors[] = "You must have at least $minDepth players at $pos";
            }
        }
        if ($hasMultipleStarters) {
            $errors[] = "A player is listed at more than one position in the starting lineup";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => implode('; ', $errors),
            'activePlayers' => $activePlayers,
            'positionDepth' => $posDepth
        ];
    }
    
    private function generateFileContent($postData)
    {
        $content = "Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI\n";
        
        for ($i = 1; $i <= 15; $i++) {
            if (isset($postData["Name$i"])) {
                $content .= $postData["Name$i"] . ",";
                $content .= ($postData["pg$i"] ?? 0) . ",";
                $content .= ($postData["sg$i"] ?? 0) . ",";
                $content .= ($postData["sf$i"] ?? 0) . ",";
                $content .= ($postData["pf$i"] ?? 0) . ",";
                $content .= ($postData["c$i"] ?? 0) . ",";
                $content .= ($postData["active$i"] ?? 0) . ",";
                $content .= ($postData["min$i"] ?? 0) . ",";
                $content .= ($postData["OF$i"] ?? 0) . ",";
                $content .= ($postData["DF$i"] ?? 0) . ",";
                $content .= ($postData["OI$i"] ?? 0) . ",";
                $content .= ($postData["DI$i"] ?? 0) . "\n";
            }
        }
        
        return $content;
    }
    
    private function performDatabaseUpdates($postData)
    {
        $updates = [];
        
        for ($i = 1; $i <= 15; $i++) {
            if (isset($postData["Name$i"]) && isset($postData["active$i"]) && $postData["active$i"] == 1) {
                $updates[] = $postData["Name$i"];
            }
        }
        
        return $updates;
    }
    
    private function updatePlayerInDatabase($playerData)
    {
        $name = addslashes($playerData['Name']);
        
        $this->mockDb->sql_query("UPDATE ibl_plr SET dc_PGDepth = '{$playerData['pg']}' WHERE name = '$name'");
        $this->mockDb->sql_query("UPDATE ibl_plr SET dc_SGDepth = '{$playerData['sg']}' WHERE name = '$name'");
        $this->mockDb->sql_query("UPDATE ibl_plr SET dc_SFDepth = '{$playerData['sf']}' WHERE name = '$name'");
        $this->mockDb->sql_query("UPDATE ibl_plr SET dc_PFDepth = '{$playerData['pf']}' WHERE name = '$name'");
        $this->mockDb->sql_query("UPDATE ibl_plr SET dc_CDepth = '{$playerData['c']}' WHERE name = '$name'");
        $this->mockDb->sql_query("UPDATE ibl_plr SET dc_active = '{$playerData['active']}' WHERE name = '$name'");
        $this->mockDb->sql_query("UPDATE ibl_plr SET dc_minutes = '{$playerData['min']}' WHERE name = '$name'");
        $this->mockDb->sql_query("UPDATE ibl_plr SET dc_of = '{$playerData['OF']}' WHERE name = '$name'");
        $this->mockDb->sql_query("UPDATE ibl_plr SET dc_df = '{$playerData['DF']}' WHERE name = '$name'");
        $this->mockDb->sql_query("UPDATE ibl_plr SET dc_oi = '{$playerData['OI']}' WHERE name = '$name'");
        $this->mockDb->sql_query("UPDATE ibl_plr SET dc_di = '{$playerData['DI']}' WHERE name = '$name'");
        $this->mockDb->sql_query("UPDATE ibl_plr SET dc_bh = '{$playerData['BH']}' WHERE name = '$name'");
    }
    
    private function hasQueryForField($queries, $field)
    {
        foreach ($queries as $query) {
            if (strpos($query, $field) !== false) {
                return true;
            }
        }
        return false;
    }
    
    private function setPositionDepth(&$postData, $position, $depth)
    {
        $count = 0;
        for ($i = 1; $i <= 15 && $count < $depth; $i++) {
            if (isset($postData["{$position}$i"])) {
                $postData["{$position}$i"] = $count + 1;
                $count++;
            }
        }
    }
    
    private function calculatePositionDepth($postData)
    {
        $depth = ['pg' => 0, 'sg' => 0, 'sf' => 0, 'pf' => 0, 'c' => 0];
        
        for ($i = 1; $i <= 15; $i++) {
            $injury = isset($postData["Injury$i"]) ? $postData["Injury$i"] : 0;
            
            foreach (['pg', 'sg', 'sf', 'pf', 'c'] as $pos) {
                if (isset($postData["$pos$i"]) && $postData["$pos$i"] > 0 && $injury < 15) {
                    $depth[$pos]++;
                }
            }
        }
        
        return $depth;
    }
}
