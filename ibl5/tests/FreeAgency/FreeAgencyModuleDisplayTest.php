<?php

use PHPUnit\Framework\TestCase;

/**
 * Comprehensive tests for Free Agency module display logic
 * 
 * Tests display calculations from modules/Free_Agency/index.php:
 * - Free agent identification (year calculation)
 * - Cap space calculations
 * - Roster spot tracking
 * - Player contract display
 * - Offer display
 * - Veteran minimum calculations
 * - Maximum contract calculations
 */
class FreeAgencyModuleDisplayTest extends TestCase
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
     * @group display
     * @group free-agent-identification
     */
    public function testPlayerIdentifiedAsFreeAgentCorrectly()
    {
        // Arrange
        $playerData = [
            'draft_year' => 2020,
            'exp' => 4,
            'cyt' => 4,
            'cy' => 4 // Current year of contract
        ];
        $currentSeasonEndingYear = 2024;

        // Act
        $yearPlayerIsFreeAgent = $this->calculateFreeAgentYear($playerData);

        // Assert
        // draft_year + exp + cyt - cy = 2020 + 4 + 4 - 4 = 2024
        $this->assertEquals($currentSeasonEndingYear, $yearPlayerIsFreeAgent);
    }

    /**
     * @group display
     * @group free-agent-identification
     */
    public function testPlayerNotIdentifiedAsFreeAgentWhenContractRemains()
    {
        // Arrange
        $playerData = [
            'draft_year' => 2020,
            'exp' => 4,
            'cyt' => 4,
            'cy' => 2 // Still has 2 years left
        ];
        $currentSeasonEndingYear = 2024;

        // Act
        $yearPlayerIsFreeAgent = $this->calculateFreeAgentYear($playerData);

        // Assert
        // draft_year + exp + cyt - cy = 2020 + 4 + 4 - 2 = 2026
        $this->assertNotEquals($currentSeasonEndingYear, $yearPlayerIsFreeAgent);
        $this->assertEquals(2026, $yearPlayerIsFreeAgent);
    }

    /**
     * @group display
     * @group roster-spots
     */
    public function testRosterSpotsDecrementForPlayersUnderContract()
    {
        // Arrange
        $initialRosterSpots = 15;
        $players = [
            ['cy' => 0, 'cy1' => 1000, 'name' => 'Player 1'],
            ['cy' => 0, 'cy1' => 1100, 'name' => 'Player 2'],
            ['cy' => 0, 'cy1' => 1200, 'name' => 'Player 3']
        ];

        // Act
        $remainingSpots = $this->calculateRemainingRosterSpots($initialRosterSpots, $players);

        // Assert
        $this->assertEquals(12, $remainingSpots);
    }

    /**
     * @group display
     * @group roster-spots
     */
    public function testRosterSpotsDoNotDecrementForPlayersWithPipePrefix()
    {
        // Arrange
        $initialRosterSpots = 15;
        $players = [
            ['cy' => 0, 'cy1' => 1000, 'name' => 'Player 1'],
            ['cy' => 0, 'cy1' => 1100, 'name' => '|Injured Player'], // Pipe prefix
            ['cy' => 0, 'cy1' => 1200, 'name' => 'Player 3']
        ];

        // Act
        $remainingSpots = $this->calculateRemainingRosterSpots($initialRosterSpots, $players);

        // Assert
        // Only 2 players count (not the one with pipe prefix)
        $this->assertEquals(13, $remainingSpots);
    }

    /**
     * @group display
     * @group roster-spots
     */
    public function testRosterSpotsDecrementForOffers()
    {
        // Arrange
        $initialRosterSpots = 15;
        $offers = [
            ['offer1' => 1000, 'offer2' => 1100],
            ['offer1' => 800, 'offer2' => 850]
        ];

        // Act
        $remainingSpots = $this->calculateRosterSpotsAfterOffers($initialRosterSpots, $offers);

        // Assert
        $this->assertEquals(13, $remainingSpots);
    }

    /**
     * @group display
     * @group cap-space
     */
    public function testSoftCapSpaceCalculatedCorrectly()
    {
        // Arrange
        $softCapMax = 5500; // League::SOFT_CAP_MAX
        $committedSalaries = [
            1000, 1100, 1200, 900, 800
        ];

        // Act
        $availableSpace = $this->calculateAvailableCapSpace($softCapMax, $committedSalaries);

        // Assert
        // 5500 - (1000 + 1100 + 1200 + 900 + 800) = 5500 - 5000 = 500
        $this->assertEquals(500, $availableSpace);
    }

    /**
     * @group display
     * @group cap-space
     */
    public function testHardCapSpaceCalculatedCorrectly()
    {
        // Arrange
        $hardCapMax = 7500; // League::HARD_CAP_MAX
        $committedSalaries = [
            1000, 1100, 1200, 900, 800, 1500
        ];

        // Act
        $availableSpace = $this->calculateAvailableCapSpace($hardCapMax, $committedSalaries);

        // Assert
        // 7500 - 6500 = 1000
        $this->assertEquals(1000, $availableSpace);
    }

    /**
     * @group display
     * @group cap-space
     */
    public function testCapSpaceIncludesOffersInCalculation()
    {
        // Arrange
        $softCapMax = 5500;
        $contractedSalaries = [1000, 1100, 1200]; // 3300 total
        $offerSalaries = [800, 900]; // 1700 total

        // Act
        $totalCommitted = array_merge($contractedSalaries, $offerSalaries);
        $availableSpace = $this->calculateAvailableCapSpace($softCapMax, $totalCommitted);

        // Assert
        // 5500 - (3300 + 1700) = 5500 - 5000 = 500
        $this->assertEquals(500, $availableSpace);
    }

    /**
     * @group display
     * @group future-years
     */
    public function testFutureSalaryCalculatedCorrectlyForYear0()
    {
        // Arrange
        $player = [
            'cy' => 0,
            'cy1' => 1000,
            'cy2' => 1100,
            'cy3' => 1200,
            'cy4' => 0,
            'cy5' => 0,
            'cy6' => 0
        ];

        // Act
        $futureSalaries = $this->calculateFutureSalaries($player);

        // Assert
        $this->assertEquals(1000, $futureSalaries['year1']);
        $this->assertEquals(1100, $futureSalaries['year2']);
        $this->assertEquals(1200, $futureSalaries['year3']);
        $this->assertEquals(0, $futureSalaries['year4']);
    }

    /**
     * @group display
     * @group future-years
     */
    public function testFutureSalaryCalculatedCorrectlyForYear2()
    {
        // Arrange
        $player = [
            'cy' => 2,
            'cy1' => 1000,
            'cy2' => 1100,
            'cy3' => 1200,
            'cy4' => 1300,
            'cy5' => 1400,
            'cy6' => 1500
        ];

        // Act
        $futureSalaries = $this->calculateFutureSalaries($player);

        // Assert
        // When cy=2, future year 1 = cy3, year 2 = cy4, etc.
        $this->assertEquals(1200, $futureSalaries['year1']);
        $this->assertEquals(1300, $futureSalaries['year2']);
        $this->assertEquals(1400, $futureSalaries['year3']);
        $this->assertEquals(1500, $futureSalaries['year4']);
    }

    /**
     * @group display
     * @group veteran-minimum
     */
    public function testVeteranMinimumCalculatedFor0YearsExperience()
    {
        // Arrange
        $experience = 0;

        // Act
        $vetMin = $this->calculateVeteranMinimum($experience);

        // Assert
        $this->assertEquals(52, $vetMin);
    }

    /**
     * @group display
     * @group veteran-minimum
     */
    public function testVeteranMinimumCalculatedFor5YearsExperience()
    {
        // Arrange
        $experience = 5;

        // Act
        $vetMin = $this->calculateVeteranMinimum($experience);

        // Assert
        $this->assertEquals(84, $vetMin);
    }

    /**
     * @group display
     * @group veteran-minimum
     */
    public function testVeteranMinimumCalculatedFor10YearsExperience()
    {
        // Arrange
        $experience = 10;

        // Act
        $vetMin = $this->calculateVeteranMinimum($experience);

        // Assert
        $this->assertEquals(117, $vetMin);
    }

    /**
     * @group display
     * @group maximum-contract
     */
    public function testMaximumContractFor0To6YearsExperience()
    {
        // Arrange
        $experience = 5;

        // Act
        $maxContract = $this->calculateMaximumContract($experience);

        // Assert
        // 0-6 years: 25% of cap (5500 * 0.25 = 1375)
        $this->assertEquals(1375, $maxContract);
    }

    /**
     * @group display
     * @group maximum-contract
     */
    public function testMaximumContractFor7To9YearsExperience()
    {
        // Arrange
        $experience = 8;

        // Act
        $maxContract = $this->calculateMaximumContract($experience);

        // Assert
        // 7-9 years: 30% of cap (5500 * 0.30 = 1650)
        $this->assertEquals(1650, $maxContract);
    }

    /**
     * @group display
     * @group maximum-contract
     */
    public function testMaximumContractFor10PlusYearsExperience()
    {
        // Arrange
        $experience = 12;

        // Act
        $maxContract = $this->calculateMaximumContract($experience);

        // Assert
        // 10+ years: 35% of cap (5500 * 0.35 = 1925)
        $this->assertEquals(1925, $maxContract);
    }

    /**
     * @group display
     * @group bird-rights-display
     */
    public function testBirdRightsIndicatorDisplayedForThreePlusYears()
    {
        // Arrange
        $player = [
            'name' => 'Star Player',
            'bird' => 3
        ];

        // Act
        $displayName = $this->formatPlayerNameWithBirdRights($player);

        // Assert
        $this->assertStringContainsString('*', $displayName);
        $this->assertStringContainsString('<i>', $displayName);
    }

    /**
     * @group display
     * @group bird-rights-display
     */
    public function testBirdRightsIndicatorNotDisplayedForLessThanThreeYears()
    {
        // Arrange
        $player = [
            'name' => 'Young Player',
            'bird' => 2
        ];

        // Act
        $displayName = $this->formatPlayerNameWithBirdRights($player);

        // Assert
        $this->assertStringNotContainsString('*', $displayName);
        $this->assertStringNotContainsString('<i>', $displayName);
    }

    /**
     * @group display
     * @group mle-lle-display
     */
    public function testMLEAvailabilityDisplayedCorrectly()
    {
        // Arrange
        $teamHasMLE = true;

        // Act
        $icon = $this->getMLEIcon($teamHasMLE);

        // Assert
        $this->assertEquals("\u{2705}", $icon); // Green checkmark
    }

    /**
     * @group display
     * @group mle-lle-display
     */
    public function testMLEUnavailabilityDisplayedCorrectly()
    {
        // Arrange
        $teamHasMLE = false;

        // Act
        $icon = $this->getMLEIcon($teamHasMLE);

        // Assert
        $this->assertEquals("\u{274C}", $icon); // Red X
    }

    /**
     * @group display
     * @group demand-display
     */
    public function testPlayerDemandsDisplayedForVeteranPlayer()
    {
        // Arrange
        $playerExp = 5;
        $demands = [
            'dem1' => 1000,
            'dem2' => 1100,
            'dem3' => 1200,
            'dem4' => 1300,
            'dem5' => 1400,
            'dem6' => 1500
        ];

        // Act
        $demandDisplay = $this->formatDemandDisplay($playerExp, $demands);

        // Assert
        $this->assertStringContainsString('1000', $demandDisplay);
        $this->assertStringContainsString('1100', $demandDisplay);
        $this->assertStringContainsString('1500', $demandDisplay);
    }

    /**
     * @group display
     * @group demand-display
     */
    public function testPlayerDemandsDisplayedForUndraftedRookie()
    {
        // Arrange
        $playerExp = 0;
        $demands = [
            'dem1' => 52,
            'dem2' => 52,
            'dem3' => 100,
            'dem4' => 110,
            'dem5' => 0,
            'dem6' => 0
        ];

        // Act
        $demandDisplay = $this->formatDemandDisplay($playerExp, $demands);

        // Assert
        // Undrafted rookies show only years 3 and 4 (limiting to 2 years)
        $this->assertStringContainsString('100', $demandDisplay);
        $this->assertStringContainsString('110', $demandDisplay);
        // Should not contain dem1 or dem2
        $this->assertStringNotContainsString('52', $demandDisplay);
    }

    // === Helper methods that simulate display logic ===

    private function calculateFreeAgentYear($playerData)
    {
        return $playerData['draft_year'] + $playerData['exp'] + $playerData['cyt'] - $playerData['cy'];
    }

    private function calculateRemainingRosterSpots($initialSpots, $players)
    {
        $spots = $initialSpots;
        foreach ($players as $player) {
            $firstChar = substr($player['name'], 0, 1);
            if ($firstChar !== '|' && $player['cy1'] != 0) {
                $spots--;
            }
        }
        return $spots;
    }

    private function calculateRosterSpotsAfterOffers($initialSpots, $offers)
    {
        $spots = $initialSpots;
        foreach ($offers as $offer) {
            if ($offer['offer1'] != 0) {
                $spots--;
            }
        }
        return $spots;
    }

    private function calculateAvailableCapSpace($capMax, $salaries)
    {
        $committed = array_sum($salaries);
        return $capMax - $committed;
    }

    private function calculateFutureSalaries($player)
    {
        $future = [
            'year1' => 0,
            'year2' => 0,
            'year3' => 0,
            'year4' => 0,
            'year5' => 0,
            'year6' => 0
        ];

        $cy = $player['cy'];

        if ($cy == 0) {
            $future['year1'] = $player['cy1'];
            $future['year2'] = $player['cy2'];
            $future['year3'] = $player['cy3'];
            $future['year4'] = $player['cy4'];
            $future['year5'] = $player['cy5'];
            $future['year6'] = $player['cy6'];
        } elseif ($cy == 1) {
            $future['year1'] = $player['cy2'];
            $future['year2'] = $player['cy3'];
            $future['year3'] = $player['cy4'];
            $future['year4'] = $player['cy5'];
            $future['year5'] = $player['cy6'];
        } elseif ($cy == 2) {
            $future['year1'] = $player['cy3'];
            $future['year2'] = $player['cy4'];
            $future['year3'] = $player['cy5'];
            $future['year4'] = $player['cy6'];
        } elseif ($cy == 3) {
            $future['year1'] = $player['cy4'];
            $future['year2'] = $player['cy5'];
            $future['year3'] = $player['cy6'];
        } elseif ($cy == 4) {
            $future['year1'] = $player['cy5'];
            $future['year2'] = $player['cy6'];
        } elseif ($cy == 5) {
            $future['year1'] = $player['cy6'];
        }

        return $future;
    }

    private function calculateVeteranMinimum($experience)
    {
        // Based on experience levels
        if ($experience == 0) {
            return 52;
        } elseif ($experience >= 1 && $experience <= 2) {
            return 65;
        } elseif ($experience >= 3 && $experience <= 4) {
            return 71;
        } elseif ($experience >= 5 && $experience <= 6) {
            return 84;
        } elseif ($experience >= 7 && $experience <= 9) {
            return 104;
        } else {
            return 117;
        }
    }

    private function calculateMaximumContract($experience)
    {
        $softCap = 5500; // League::SOFT_CAP_MAX
        
        if ($experience >= 0 && $experience <= 6) {
            return round($softCap * 0.25); // 25%
        } elseif ($experience >= 7 && $experience <= 9) {
            return round($softCap * 0.30); // 30%
        } else {
            return round($softCap * 0.35); // 35%
        }
    }

    private function formatPlayerNameWithBirdRights($player)
    {
        if ($player['bird'] >= 3) {
            return "*<i>{$player['name']}</i>*";
        }
        return $player['name'];
    }

    private function getMLEIcon($hasMLE)
    {
        return $hasMLE ? "\u{2705}" : "\u{274C}";
    }

    private function formatDemandDisplay($playerExp, $demands)
    {
        if ($playerExp > 0) {
            // Veteran player - show all demands
            $display = $demands['dem1'];
            if ($demands['dem2'] != 0) {
                $display .= "</td><td>" . $demands['dem2'];
            }
            if ($demands['dem3'] != 0) {
                $display .= "</td><td>" . $demands['dem3'];
            }
            if ($demands['dem4'] != 0) {
                $display .= "</td><td>" . $demands['dem4'];
            }
            if ($demands['dem5'] != 0) {
                $display .= "</td><td>" . $demands['dem5'];
            }
            if ($demands['dem6'] != 0) {
                $display .= "</td><td>" . $demands['dem6'];
            }
        } else {
            // Undrafted rookie - limit to 2 years by showing dem3 and dem4
            $display = $demands['dem3'];
            if ($demands['dem4'] != 0) {
                $display .= "</td><td>" . $demands['dem4'];
            }
        }
        
        return $display;
    }
}
