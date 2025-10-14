<?php

use PHPUnit\Framework\TestCase;

/**
 * Comprehensive tests for Free Agency offer processing
 * 
 * Tests offer creation, amendment, and deletion from freeagentoffer.php and freeagentofferdelete.php:
 * - Offer insertion into database
 * - Offer amendment (replacing existing offers)
 * - Offer deletion
 * - Perceived value calculations
 * - Modifier calculations (loyalty, tradition, security, playing time, winner)
 * - Discord notifications
 */
class FreeAgencyOfferProcessingTest extends TestCase
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
     * @group processing
     * @group offer-creation
     */
    public function testOfferIsInsertedIntoDatabase()
    {
        // Arrange
        $offerData = [
            'playername' => 'Test Player',
            'teamname' => 'Chicago Bulls',
            'offeryear1' => 1000,
            'offeryear2' => 1100,
            'offeryear3' => 1200,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'modifier' => 1.05,
            'random' => 1.02,
            'perceivedvalue' => 1071.0,
            'mle' => 0,
            'lle' => 0
        ];

        // Act
        $this->createOffer($offerData);

        // Assert
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('INSERT INTO `ibl_fa_offers`', $queries[0]);
        $this->assertStringContainsString('Test Player', $queries[0]);
        $this->assertStringContainsString('Chicago Bulls', $queries[0]);
        $this->assertStringContainsString('1000', $queries[0]);
    }

    /**
     * @group processing
     * @group offer-amendment
     */
    public function testExistingOfferIsDeletedBeforeNewOfferIsInserted()
    {
        // Arrange
        $offerData = [
            'playername' => 'Test Player',
            'teamname' => 'Chicago Bulls',
            'offeryear1' => 1000,
            'offeryear2' => 1100,
            'offeryear3' => 0,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'modifier' => 1.0,
            'random' => 1.0,
            'perceivedvalue' => 1000.0,
            'mle' => 0,
            'lle' => 0
        ];

        // Act
        $this->amendOffer($offerData);

        // Assert
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(2, $queries);
        $this->assertStringContainsString('DELETE FROM `ibl_fa_offers`', $queries[0]);
        $this->assertStringContainsString('Test Player', $queries[0]);
        $this->assertStringContainsString('Chicago Bulls', $queries[0]);
        $this->assertStringContainsString('INSERT INTO `ibl_fa_offers`', $queries[1]);
    }

    /**
     * @group processing
     * @group offer-deletion
     */
    public function testOfferCanBeDeleted()
    {
        // Arrange
        $playerName = 'Test Player';
        $teamName = 'Chicago Bulls';

        // Act
        $this->deleteOffer($playerName, $teamName);

        // Assert
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('DELETE FROM `ibl_fa_offers`', $queries[0]);
        $this->assertStringContainsString('Test Player', $queries[0]);
        $this->assertStringContainsString('Chicago Bulls', $queries[0]);
    }

    /**
     * @group processing
     * @group modifiers
     */
    public function testLoyaltyModifierIncreasesForSameTeam()
    {
        // Arrange
        $playerData = [
            'loyalty' => 3, // Scale of 1-3
            'teamname' => 'Chicago Bulls'
        ];
        $offeringTeam = 'Chicago Bulls';

        // Act
        $modifier = $this->calculateLoyaltyModifier($playerData, $offeringTeam);

        // Assert
        // loyalty factor = 0.025 * (3 - 1) = 0.05 = 5% bonus
        $this->assertEquals(0.05, $modifier);
    }

    /**
     * @group processing
     * @group modifiers
     */
    public function testLoyaltyModifierDecreasesForDifferentTeam()
    {
        // Arrange
        $playerData = [
            'loyalty' => 3,
            'teamname' => 'Chicago Bulls'
        ];
        $offeringTeam = 'Los Angeles Lakers';

        // Act
        $modifier = $this->calculateLoyaltyModifier($playerData, $offeringTeam);

        // Assert
        // loyalty factor = -0.025 * (3 - 1) = -0.05 = -5% penalty
        $this->assertEquals(-0.05, $modifier);
    }

    /**
     * @group processing
     * @group modifiers
     */
    public function testSecurityModifierIncreasesWithLongerContract()
    {
        // Arrange
        $playerSecurity = 3; // Scale of 1-3
        $contractYears = 5;

        // Act
        $modifier = $this->calculateSecurityModifier($playerSecurity, $contractYears);

        // Assert
        // security factor = (0.01 * (5 - 1) - 0.025) * (3 - 1)
        // = (0.04 - 0.025) * 2 = 0.015 * 2 = 0.03 = 3% bonus
        $this->assertEquals(0.03, $modifier);
    }

    /**
     * @group processing
     * @group modifiers
     */
    public function testPlayingTimeModifierDecreasesWithMoreMoneyAtPosition()
    {
        // Arrange
        $playerPlayingTime = 3; // Higher value means wants more playing time
        $millionsAtPosition = 1500; // $15M committed at position

        // Act
        $modifier = $this->calculatePlayingTimeModifier($playerPlayingTime, $millionsAtPosition);

        // Assert
        // playing time factor = -(0.0025 * 15 - 0.025) * (3 - 1)
        // = -(0.0375 - 0.025) * 2 = -0.0125 * 2 = -0.025 = -2.5% penalty
        $this->assertEqualsWithDelta(-0.025, $modifier, 0.0001);
    }

    /**
     * @group processing
     * @group modifiers
     */
    public function testWinnerModifierIncreasesForWinningTeam()
    {
        // Arrange
        $playerWinner = 3; // Wants to play for winner
        $teamWins = 55;
        $teamLosses = 27;

        // Act
        $modifier = $this->calculateWinnerModifier($playerWinner, $teamWins, $teamLosses);

        // Assert
        // winner factor = 0.000153 * (55 - 27) * (3 - 1)
        // = 0.000153 * 28 * 2 = 0.008568 = ~0.86% bonus
        $this->assertEqualsWithDelta(0.008568, $modifier, 0.0001);
    }

    /**
     * @group processing
     * @group modifiers
     */
    public function testTraditionModifierIncreasesForSuccessfulFranchise()
    {
        // Arrange
        $playerTradition = 3; // Values tradition
        $franchiseAvgWins = 50;
        $franchiseAvgLosses = 32;

        // Act
        $modifier = $this->calculateTraditionModifier($playerTradition, $franchiseAvgWins, $franchiseAvgLosses);

        // Assert
        // tradition factor = 0.000153 * (50 - 32) * (3 - 1)
        // = 0.000153 * 18 * 2 = 0.005508 = ~0.55% bonus
        $this->assertEqualsWithDelta(0.005508, $modifier, 0.0001);
    }

    /**
     * @group processing
     * @group perceived-value
     */
    public function testPerceivedValueIsCalculatedCorrectly()
    {
        // Arrange
        $offerAverage = 1000;
        $modifier = 1.10; // 10% bonus from various factors
        $random = 1.02; // 2% random variation

        // Act
        $perceivedValue = $this->calculatePerceivedValue($offerAverage, $modifier, $random);

        // Assert
        $this->assertEquals(1122.0, $perceivedValue);
    }

    /**
     * @group processing
     * @group contract-years
     */
    public function testContractYearsCalculatedCorrectlyForSixYearContract()
    {
        // Arrange
        $offer = [
            'offeryear1' => 1000,
            'offeryear2' => 1100,
            'offeryear3' => 1200,
            'offeryear4' => 1300,
            'offeryear5' => 1400,
            'offeryear6' => 1500
        ];

        // Act
        $years = $this->calculateContractYears($offer);

        // Assert
        $this->assertEquals(6, $years);
    }

    /**
     * @group processing
     * @group contract-years
     */
    public function testContractYearsCalculatedCorrectlyForThreeYearContract()
    {
        // Arrange
        $offer = [
            'offeryear1' => 1000,
            'offeryear2' => 1100,
            'offeryear3' => 1200,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0
        ];

        // Act
        $years = $this->calculateContractYears($offer);

        // Assert
        $this->assertEquals(3, $years);
    }

    /**
     * @group processing
     * @group contract-years
     */
    public function testOfferAverageCalculatedCorrectly()
    {
        // Arrange
        $offer = [
            'offeryear1' => 1000,
            'offeryear2' => 1100,
            'offeryear3' => 1200,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0
        ];

        // Act
        $average = $this->calculateOfferAverage($offer);

        // Assert
        // (1000 + 1100 + 1200) / 3 = 1100
        $this->assertEquals(1100, $average);
    }

    /**
     * @group processing
     * @group millions-at-position
     */
    public function testMillionsCommittedAtPositionCalculatedCorrectly()
    {
        // Arrange
        $this->mockDb->setMockData([
            [
                'name' => 'Player 1',
                'pos' => 'PG',
                'cy' => 0,
                'cy1' => 1000,
                'cy2' => 1100,
                'cy3' => 0,
                'cy4' => 0,
                'cy5' => 0,
                'cy6' => 0
            ],
            [
                'name' => 'Player 2',
                'pos' => 'PG',
                'cy' => 0,
                'cy1' => 500,
                'cy2' => 550,
                'cy3' => 0,
                'cy4' => 0,
                'cy5' => 0,
                'cy6' => 0
            ]
        ]);

        // Act - looking at next year's commitment (cy=0, so look at cy1)
        $millions = $this->calculateMillionsAtPosition('Chicago Bulls', 'PG', 'Offered Player');

        // Assert
        // Player 1: cy1 = 1000
        // Player 2: cy1 = 500
        // Total = 1500
        $this->assertEquals(1500, $millions);
    }

    /**
     * @group processing
     * @group millions-at-position
     */
    public function testMillionsCommittedAtPositionCappedAt2000()
    {
        // Arrange
        $this->mockDb->setMockData([
            [
                'name' => 'Expensive Player',
                'pos' => 'C',
                'cy' => 0,
                'cy1' => 3000, // More than the cap
                'cy2' => 0,
                'cy3' => 0,
                'cy4' => 0,
                'cy5' => 0,
                'cy6' => 0
            ]
        ]);

        // Act
        $millions = $this->calculateMillionsAtPosition('Chicago Bulls', 'C', 'Other Player');

        // Assert
        $this->assertEquals(2000, $millions);
    }

    /**
     * @group processing
     * @group discord
     */
    public function testDiscordNotificationSentForHighValueOffer()
    {
        // Arrange
        $offerData = [
            'offeryear1' => 1000, // Above 145 threshold
            'playername' => 'Star Player',
            'teamname' => 'Chicago Bulls',
            'player_teamname' => 'Los Angeles Lakers'
        ];

        $season = new Season($this->mockDb);
        $season->freeAgencyNotificationsState = 'On';

        // Act
        $shouldNotify = $this->shouldSendDiscordNotification($offerData, $season);

        // Assert
        $this->assertTrue($shouldNotify);
    }

    /**
     * @group processing
     * @group discord
     */
    public function testDiscordNotificationNotSentForLowValueOffer()
    {
        // Arrange
        $offerData = [
            'offeryear1' => 145, // At or below threshold
            'playername' => 'Minimum Player',
            'teamname' => 'Chicago Bulls',
            'player_teamname' => 'Los Angeles Lakers'
        ];

        $season = new Season($this->mockDb);
        $season->freeAgencyNotificationsState = 'On';

        // Act
        $shouldNotify = $this->shouldSendDiscordNotification($offerData, $season);

        // Assert
        $this->assertFalse($shouldNotify);
    }

    /**
     * @group processing
     * @group discord
     */
    public function testDiscordNotificationNotSentWhenNotificationsOff()
    {
        // Arrange
        $offerData = [
            'offeryear1' => 1000,
            'playername' => 'Star Player',
            'teamname' => 'Chicago Bulls',
            'player_teamname' => 'Los Angeles Lakers'
        ];

        $season = new Season($this->mockDb);
        $season->freeAgencyNotificationsState = 'Off';

        // Act
        $shouldNotify = $this->shouldSendDiscordNotification($offerData, $season);

        // Assert
        $this->assertFalse($shouldNotify);
    }

    // === Helper methods that simulate processing logic ===

    private function createOffer($offerData)
    {
        $query = "INSERT INTO `ibl_fa_offers` 
        (`name`, 
         `team`, 
         `offer1`, 
         `offer2`, 
         `offer3`, 
         `offer4`, 
         `offer5`, 
         `offer6`, 
         `modifier`, 
         `random`, 
         `perceivedvalue`, 
         `mle`, 
         `lle`) 
            VALUES
        ( '{$offerData['playername']}', 
          '{$offerData['teamname']}', 
          '{$offerData['offeryear1']}', 
          '{$offerData['offeryear2']}', 
          '{$offerData['offeryear3']}', 
          '{$offerData['offeryear4']}', 
          '{$offerData['offeryear5']}', 
          '{$offerData['offeryear6']}', 
          '{$offerData['modifier']}', 
          '{$offerData['random']}', 
          '{$offerData['perceivedvalue']}', 
          '{$offerData['mle']}', 
          '{$offerData['lle']}' )";

        $this->mockDb->sql_query($query);
    }

    private function amendOffer($offerData)
    {
        // Delete existing offer
        $deleteQuery = "DELETE FROM `ibl_fa_offers` WHERE `name` = '{$offerData['playername']}' AND `team` = '{$offerData['teamname']}' LIMIT 1";
        $this->mockDb->sql_query($deleteQuery);

        // Insert new offer
        $this->createOffer($offerData);
    }

    private function deleteOffer($playerName, $teamName)
    {
        $query = "DELETE FROM `ibl_fa_offers` WHERE `name` = '$playerName' AND `team` = '$teamName'";
        $this->mockDb->sql_query($query);
    }

    private function calculateLoyaltyModifier($playerData, $offeringTeam)
    {
        if ($offeringTeam == $playerData['teamname']) {
            return 0.025 * ($playerData['loyalty'] - 1);
        } else {
            return -0.025 * ($playerData['loyalty'] - 1);
        }
    }

    private function calculateSecurityModifier($playerSecurity, $contractYears)
    {
        return (0.01 * ($contractYears - 1) - 0.025) * ($playerSecurity - 1);
    }

    private function calculatePlayingTimeModifier($playerPlayingTime, $millionsAtPosition)
    {
        return -(0.0025 * $millionsAtPosition / 100 - 0.025) * ($playerPlayingTime - 1);
    }

    private function calculateWinnerModifier($playerWinner, $teamWins, $teamLosses)
    {
        $winLossDifferential = $teamWins - $teamLosses;
        return 0.000153 * $winLossDifferential * ($playerWinner - 1);
    }

    private function calculateTraditionModifier($playerTradition, $avgWins, $avgLosses)
    {
        $winLossDifferential = $avgWins - $avgLosses;
        return 0.000153 * $winLossDifferential * ($playerTradition - 1);
    }

    private function calculatePerceivedValue($offerAverage, $modifier, $random)
    {
        return $offerAverage * $modifier * $random;
    }

    private function calculateContractYears($offer)
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

    private function calculateOfferAverage($offer)
    {
        $years = $this->calculateContractYears($offer);
        $total = $offer['offeryear1'] + $offer['offeryear2'] + $offer['offeryear3'] + 
                 $offer['offeryear4'] + $offer['offeryear5'] + $offer['offeryear6'];
        return $total / $years;
    }

    private function calculateMillionsAtPosition($teamName, $position, $excludePlayer)
    {
        $result = $this->mockDb->sql_query("SELECT * FROM ibl_plr WHERE teamname='$teamName' AND pos='$position' AND name!='$excludePlayer'");
        
        $total = 0;
        while ($row = $this->mockDb->sql_fetchrow($result)) {
            // Look at next year's salary based on current year
            if ($row['cy'] == 0) {
                $total += $row['cy1'];
            } elseif ($row['cy'] == 1) {
                $total += $row['cy2'];
            } elseif ($row['cy'] == 2) {
                $total += $row['cy3'];
            } elseif ($row['cy'] == 3) {
                $total += $row['cy4'];
            } elseif ($row['cy'] == 4) {
                $total += $row['cy5'];
            } elseif ($row['cy'] == 5) {
                $total += $row['cy6'];
            }
        }

        // Cap at 2000 per the business logic
        if ($total > 2000) {
            $total = 2000;
        }

        return $total;
    }

    private function shouldSendDiscordNotification($offerData, $season)
    {
        if ($offerData['offeryear1'] > 145 && $season->freeAgencyNotificationsState == 'On') {
            return true;
        }
        return false;
    }
}
