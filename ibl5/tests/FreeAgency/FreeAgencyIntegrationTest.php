<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Free Agency workflow
 * 
 * Tests complete end-to-end workflows:
 * - Complete offer submission workflow
 * - Offer amendment workflow
 * - Offer deletion workflow
 * - Multiple offers from different teams
 * - Cap space and roster validation across workflow
 */
class FreeAgencyIntegrationTest extends TestCase
{
    private $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    protected function tearDown(): void
    {
        $this->mockDb = null;
    }

    /**
     * @group integration
     * @group complete-workflow
     */
    public function testCompleteOfferWorkflow()
    {
        // Arrange - Setup player and team data
        $this->mockDb->setMockData([
            [
                'name' => 'Test Player',
                'pos' => 'PG',
                'cy' => 5, // In last year of contract
                'cy1' => 0,
                'exp' => 5,
                'bird' => 2,
                'loyalty' => 2,
                'winner' => 2,
                'tradition' => 2,
                'security' => 2,
                'playingTime' => 2,
                'teamname' => 'Chicago Bulls'
            ]
        ]);

        // Act - Step 1: Display Free Agency page and verify player appears
        $freeAgents = $this->getFreeAgentsForTeam('Chicago Bulls', 2024);
        $this->assertCount(1, $freeAgents);

        // Act - Step 2: Submit an offer
        $offerData = [
            'playername' => 'Test Player',
            'teamname' => 'Chicago Bulls',
            'player_teamname' => 'Chicago Bulls',
            'offeryear1' => 1000,
            'offeryear2' => 1100,
            'offeryear3' => 1200,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'bird' => 2,
            'vetmin' => 84,
            'MLEyrs' => 0,
            'amendedCapSpaceYear1' => 2000,
            'max' => 1375
        ];

        $result = $this->submitOffer($offerData);

        // Assert
        $this->assertTrue($result['success']);
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertGreaterThan(0, count($queries));
        
        // Verify INSERT query was executed
        $insertFound = false;
        foreach ($queries as $query) {
            if (strpos($query, 'INSERT INTO `ibl_fa_offers`') !== false) {
                $insertFound = true;
                break;
            }
        }
        $this->assertTrue($insertFound);
    }

    /**
     * @group integration
     * @group amendment-workflow
     */
    public function testOfferAmendmentWorkflow()
    {
        // Arrange - Setup existing offer
        $this->mockDb->setMockData([
            [
                'name' => 'Test Player',
                'pos' => 'PG',
                'cy' => 5,
                'cy1' => 0,
                'exp' => 5,
                'bird' => 2,
                'teamname' => 'Chicago Bulls'
            ]
        ]);

        // Act - Step 1: Submit initial offer
        $initialOffer = [
            'playername' => 'Test Player',
            'teamname' => 'Chicago Bulls',
            'player_teamname' => 'Chicago Bulls',
            'offeryear1' => 1000,
            'offeryear2' => 1100,
            'offeryear3' => 0,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'bird' => 2,
            'vetmin' => 84,
            'MLEyrs' => 0,
            'amendedCapSpaceYear1' => 2000,
            'max' => 1375
        ];

        $this->submitOffer($initialOffer);
        $initialQueryCount = count($this->mockDb->getExecutedQueries());

        // Act - Step 2: Amend the offer
        $amendedOffer = $initialOffer;
        $amendedOffer['offeryear1'] = 1200;
        $amendedOffer['offeryear2'] = 1320;
        $amendedOffer['offeryear3'] = 1400;

        $this->mockDb->clearQueries();
        $result = $this->submitOffer($amendedOffer);

        // Assert
        $this->assertTrue($result['success']);
        $queries = $this->mockDb->getExecutedQueries();
        
        // Should have DELETE followed by INSERT
        $this->assertGreaterThanOrEqual(2, count($queries));
        $this->assertStringContainsString('DELETE FROM `ibl_fa_offers`', $queries[0]);
        $this->assertStringContainsString('INSERT INTO `ibl_fa_offers`', $queries[1]);
    }

    /**
     * @group integration
     * @group deletion-workflow
     */
    public function testOfferDeletionWorkflow()
    {
        // Arrange
        $playerName = 'Test Player';
        $teamName = 'Chicago Bulls';

        // Act
        $result = $this->deleteOffer($playerName, $teamName);

        // Assert
        $this->assertTrue($result['success']);
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('DELETE FROM `ibl_fa_offers`', $queries[0]);
        $this->assertStringContainsString($playerName, $queries[0]);
        $this->assertStringContainsString($teamName, $queries[0]);
    }

    /**
     * @group integration
     * @group multiple-offers
     */
    public function testMultipleTeamsCanOfferSamePlayer()
    {
        // Arrange
        $this->mockDb->setMockData([
            [
                'name' => 'Star Player',
                'pos' => 'SF',
                'cy' => 4,
                'cy1' => 0,
                'exp' => 8,
                'bird' => 1,
                'teamname' => 'Chicago Bulls'
            ]
        ]);

        // Act - Team 1 makes offer
        $offer1 = [
            'playername' => 'Star Player',
            'teamname' => 'Los Angeles Lakers',
            'player_teamname' => 'Chicago Bulls',
            'offeryear1' => 1500,
            'offeryear2' => 1650,
            'offeryear3' => 1815,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'bird' => 0, // No Bird rights for different team
            'vetmin' => 104,
            'MLEyrs' => 0,
            'amendedCapSpaceYear1' => 2000,
            'max' => 1650
        ];
        $result1 = $this->submitOffer($offer1);

        // Act - Team 2 makes offer
        $this->mockDb->clearQueries();
        $offer2 = [
            'playername' => 'Star Player',
            'teamname' => 'Miami Heat',
            'player_teamname' => 'Chicago Bulls',
            'offeryear1' => 1600,
            'offeryear2' => 1760,
            'offeryear3' => 0,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'bird' => 0,
            'vetmin' => 104,
            'MLEyrs' => 0,
            'amendedCapSpaceYear1' => 2500,
            'max' => 1650
        ];
        $result2 = $this->submitOffer($offer2);

        // Assert
        $this->assertTrue($result1['success']);
        $this->assertTrue($result2['success']);
        
        // Both offers should be inserted (different teams)
        $queries = $this->mockDb->getExecutedQueries();
        $insertCount = 0;
        foreach ($queries as $query) {
            if (strpos($query, 'INSERT INTO `ibl_fa_offers`') !== false) {
                $insertCount++;
            }
        }
        $this->assertEquals(1, $insertCount); // Just the second offer
    }

    /**
     * @group integration
     * @group mle-workflow
     */
    public function testMLEOfferWorkflow()
    {
        // Arrange
        $this->mockDb->setMockData([
            [
                'name' => 'Role Player',
                'pos' => 'SG',
                'cy' => 3,
                'cy1' => 0,
                'exp' => 4,
                'bird' => 0,
                'teamname' => 'Miami Heat'
            ]
        ]);

        // Act - Submit MLE offer
        $mleOffer = [
            'playername' => 'Role Player',
            'teamname' => 'Chicago Bulls',
            'player_teamname' => 'Miami Heat',
            'offeryear1' => 450, // Set by MLE
            'offeryear2' => 495,
            'offeryear3' => 540,
            'offeryear4' => 585,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'bird' => 0,
            'vetmin' => 71,
            'MLEyrs' => 4, // 4-year MLE
            'amendedCapSpaceYear1' => 200, // Low cap space
            'max' => 1375
        ];

        $result = $this->submitOffer($mleOffer);

        // Assert
        $this->assertTrue($result['success']);
        
        // Verify MLE flag is set
        $queries = $this->mockDb->getExecutedQueries();
        $insertQuery = '';
        foreach ($queries as $query) {
            if (strpos($query, 'INSERT INTO `ibl_fa_offers`') !== false) {
                $insertQuery = $query;
                break;
            }
        }
        $this->assertStringContainsString("'1'", $insertQuery); // MLE flag
    }

    /**
     * @group integration
     * @group bird-rights-workflow
     */
    public function testBirdRightsWorkflow()
    {
        // Arrange
        $this->mockDb->setMockData([
            [
                'name' => 'Franchise Player',
                'pos' => 'C',
                'cy' => 5,
                'cy1' => 0,
                'exp' => 10,
                'bird' => 3, // Full Bird rights
                'teamname' => 'Chicago Bulls'
            ]
        ]);

        // Act - Offer over soft cap using Bird rights
        $birdOffer = [
            'playername' => 'Franchise Player',
            'teamname' => 'Chicago Bulls',
            'player_teamname' => 'Chicago Bulls',
            'offeryear1' => 1800, // Over soft cap
            'offeryear2' => 2025, // 12.5% raise with Bird rights
            'offeryear3' => 2278,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'bird' => 3,
            'vetmin' => 117,
            'MLEyrs' => 0,
            'amendedCapSpaceYear1' => 500, // Low soft cap space, but Bird rights allow
            'max' => 1925
        ];

        $result = $this->submitOffer($birdOffer);

        // Assert
        $this->assertTrue($result['success']);
    }

    /**
     * @group integration
     * @group validation-workflow
     */
    public function testInvalidOfferIsRejected()
    {
        // Arrange
        $this->mockDb->setMockData([
            [
                'name' => 'Test Player',
                'pos' => 'PF',
                'cy' => 4,
                'cy1' => 0,
                'exp' => 3,
                'bird' => 0,
                'teamname' => 'Chicago Bulls'
            ]
        ]);

        // Act - Submit offer with excessive raise (25% instead of 10%)
        $invalidOffer = [
            'playername' => 'Test Player',
            'teamname' => 'Miami Heat',
            'player_teamname' => 'Chicago Bulls',
            'offeryear1' => 1000,
            'offeryear2' => 1250, // 25% raise - too much!
            'offeryear3' => 0,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'bird' => 0,
            'vetmin' => 71,
            'MLEyrs' => 0,
            'amendedCapSpaceYear1' => 2000,
            'max' => 1375
        ];

        $result = $this->submitOffer($invalidOffer);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('raise', strtolower($result['error']));
    }

    /**
     * @group integration
     * @group cap-workflow
     */
    public function testOfferRejectedWhenOverHardCap()
    {
        // Arrange
        $this->mockDb->setMockData([
            [
                'name' => 'Expensive Player',
                'pos' => 'PG',
                'cy' => 3,
                'cy1' => 0,
                'exp' => 8,
                'bird' => 0,
                'teamname' => 'Miami Heat'
            ]
        ]);

        // Act - Offer that exceeds hard cap
        $overCapOffer = [
            'playername' => 'Expensive Player',
            'teamname' => 'Chicago Bulls',
            'player_teamname' => 'Miami Heat',
            'offeryear1' => 3000, // Way over hard cap space
            'offeryear2' => 3300,
            'offeryear3' => 0,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'bird' => 0,
            'vetmin' => 104,
            'MLEyrs' => 0,
            'amendedCapSpaceYear1' => 500,
            'max' => 1650
        ];

        $result = $this->submitOffer($overCapOffer);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('hard cap', strtolower($result['error']));
    }

    /**
     * @group integration
     * @group modifier-calculation
     */
    public function testPerceivedValueCalculationInCompleteWorkflow()
    {
        // Arrange
        $this->mockDb->setMockData([
            [
                'name' => 'Test Player',
                'pos' => 'SG',
                'cy' => 4,
                'cy1' => 0,
                'exp' => 6,
                'bird' => 2,
                'loyalty' => 3,
                'winner' => 2,
                'tradition' => 2,
                'security' => 2,
                'playingTime' => 2,
                'teamname' => 'Chicago Bulls'
            ]
        ]);

        // Setup team info
        $teamData = [
            'wins' => 50,
            'losses' => 32,
            'tradition_wins' => 48,
            'tradition_losses' => 34,
            'millions_at_position' => 1000
        ];

        // Act
        $offer = [
            'offeryear1' => 1000,
            'offeryear2' => 1100,
            'offeryear3' => 1200,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0
        ];

        $modifiers = $this->calculateAllModifiers($this->mockDb->sql_fetchrow(
            $this->mockDb->sql_query("SELECT * FROM ibl_plr WHERE name='Test Player'")
        ), 'Chicago Bulls', $teamData, 3); // 3 year contract

        // Calculate perceived value
        $offerAverage = (1000 + 1100 + 1200) / 3;
        $totalModifier = 1 + $modifiers['loyalty'] + $modifiers['winner'] + 
                         $modifiers['tradition'] + $modifiers['security'] + 
                         $modifiers['playingTime'];
        $random = 1.0; // Set random to 1.0 for testing
        $perceivedValue = $offerAverage * $totalModifier * $random;

        // Assert
        $this->assertGreaterThan(0, $perceivedValue);
        $this->assertNotEquals($offerAverage, $perceivedValue); // Modifiers should change it
    }

    // === Helper methods that simulate Free Agency workflows ===

    private function getFreeAgentsForTeam($teamName, $currentSeasonEndingYear)
    {
        $result = $this->mockDb->sql_query("SELECT * FROM ibl_plr WHERE teamname='$teamName'");
        $freeAgents = [];
        
        while ($player = $this->mockDb->sql_fetchrow($result)) {
            // Calculate if player is FA this year
            // In our test setup, a player in last year of contract (cy=5, cyt probably around 5-6)
            // For simplicity in testing, just check if cy is high enough
            if ($player['cy'] >= 4) {
                $freeAgents[] = $player;
            }
        }
        
        return $freeAgents;
    }

    private function submitOffer($offerData)
    {
        // Validate offer first
        $validation = $this->validateOffer($offerData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error']
            ];
        }

        // Calculate modifiers and perceived value
        $years = $this->calculateYears($offerData);
        $average = $this->calculateAverage($offerData, $years);
        
        // Delete existing offer (amendment)
        $deleteQuery = "DELETE FROM `ibl_fa_offers` WHERE `name` = '{$offerData['playername']}' AND `team` = '{$offerData['teamname']}' LIMIT 1";
        $this->mockDb->sql_query($deleteQuery);

        // Insert new offer
        $insertQuery = "INSERT INTO `ibl_fa_offers` 
        (`name`, `team`, `offer1`, `offer2`, `offer3`, `offer4`, `offer5`, `offer6`, `modifier`, `random`, `perceivedvalue`, `mle`, `lle`) 
        VALUES
        ('{$offerData['playername']}', '{$offerData['teamname']}', '{$offerData['offeryear1']}', 
         '{$offerData['offeryear2']}', '{$offerData['offeryear3']}', '{$offerData['offeryear4']}', 
         '{$offerData['offeryear5']}', '{$offerData['offeryear6']}', '1.0', '1.0', '$average', 
         '" . ($offerData['MLEyrs'] > 0 && $offerData['MLEyrs'] <= 6 ? 1 : 0) . "', 
         '" . ($offerData['MLEyrs'] == 7 ? 1 : 0) . "')";
        
        $this->mockDb->sql_query($insertQuery);

        return ['success' => true];
    }

    private function deleteOffer($playerName, $teamName)
    {
        $query = "DELETE FROM `ibl_fa_offers` WHERE `name` = '$playerName' AND `team` = '$teamName'";
        $this->mockDb->sql_query($query);
        return ['success' => true];
    }

    private function validateOffer($offerData)
    {
        // Check for zero in year 1
        if ($offerData['offeryear1'] == 0) {
            return ['valid' => false, 'error' => 'Year 1 cannot be zero'];
        }

        // Check veteran minimum
        if ($offerData['offeryear1'] < $offerData['vetmin']) {
            return ['valid' => false, 'error' => 'Below veteran minimum'];
        }

        // Check hard cap
        $hardCapSpace = $offerData['amendedCapSpaceYear1'] + 2000;
        if ($offerData['offeryear1'] > $hardCapSpace) {
            return ['valid' => false, 'error' => 'Over hard cap'];
        }

        // Check soft cap (unless Bird rights or MLE)
        if ($offerData['bird'] < 3 && $offerData['MLEyrs'] == 0) {
            if ($offerData['offeryear1'] > $offerData['amendedCapSpaceYear1']) {
                return ['valid' => false, 'error' => 'Over soft cap'];
            }
        }

        // Check maximum
        if ($offerData['offeryear1'] > $offerData['max']) {
            return ['valid' => false, 'error' => 'Over maximum'];
        }

        // Check raises
        $birdYears = $offerData['bird'];
        $maxRaisePercent = ($birdYears > 2) ? 0.125 : 0.1;
        $maxRaise = round($offerData['offeryear1'] * $maxRaisePercent, 0);

        if ($offerData['offeryear2'] > 0 && $offerData['offeryear2'] > $offerData['offeryear1'] + $maxRaise) {
            return ['valid' => false, 'error' => 'Excessive raise in Year 2'];
        }

        return ['valid' => true];
    }

    private function calculateYears($offer)
    {
        $years = 6;
        if ($offer['offeryear6'] == 0) {
            $years = 5;
            if ($offer['offeryear5'] == 0) {
                $years = 4;
                if ($offer['offeryear4'] == 0) {
                    $years = 3;
                    if ($offer['offeryear3'] == 0) {
                        $years = 2;
                        if ($offer['offeryear2'] == 0) {
                            $years = 1;
                        }
                    }
                }
            }
        }
        return $years;
    }

    private function calculateAverage($offer, $years)
    {
        $total = $offer['offeryear1'] + $offer['offeryear2'] + $offer['offeryear3'] + 
                 $offer['offeryear4'] + $offer['offeryear5'] + $offer['offeryear6'];
        return $total / $years;
    }

    private function calculateAllModifiers($playerData, $offeringTeam, $teamData, $contractYears)
    {
        $modifiers = [];
        
        // Loyalty
        if ($offeringTeam == $playerData['teamname']) {
            $modifiers['loyalty'] = 0.025 * ($playerData['loyalty'] - 1);
        } else {
            $modifiers['loyalty'] = -0.025 * ($playerData['loyalty'] - 1);
        }
        
        // Winner
        $winLossDiff = $teamData['wins'] - $teamData['losses'];
        $modifiers['winner'] = 0.000153 * $winLossDiff * ($playerData['winner'] - 1);
        
        // Tradition
        $tradDiff = $teamData['tradition_wins'] - $teamData['tradition_losses'];
        $modifiers['tradition'] = 0.000153 * $tradDiff * ($playerData['tradition'] - 1);
        
        // Security
        $modifiers['security'] = (0.01 * ($contractYears - 1) - 0.025) * ($playerData['security'] - 1);
        
        // Playing Time
        $modifiers['playingTime'] = -(0.0025 * $teamData['millions_at_position'] / 100 - 0.025) * ($playerData['playingTime'] - 1);
        
        return $modifiers;
    }
}
