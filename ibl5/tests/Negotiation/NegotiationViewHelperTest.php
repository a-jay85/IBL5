<?php

use PHPUnit\Framework\TestCase;
use Negotiation\NegotiationViewHelper;
use Player\Player;

/**
 * Tests for NegotiationViewHelper
 * 
 * Tests HTML rendering for negotiation interface:
 * - Form rendering with proper escaping
 * - Demand display formatting
 * - Error message rendering
 * - Header rendering
 */
class NegotiationViewHelperTest extends TestCase
{
    /**
     * @group view
     * @group rendering
     */
    public function testRendersNegotiationFormWithAllFields()
    {
        // Arrange
        $player = $this->createMockPlayer();
        $demands = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 600,
            'year4' => 650,
            'year5' => 700,
            'year6' => 0,
            'years' => 5,
            'total' => 3000
        ];
        $capSpace = 1000;
        $maxYearOneSalary = \FreeAgency\FreeAgencyNegotiationHelper::getMaxContractSalary(0);

        // Act
        $html = NegotiationViewHelper::renderNegotiationForm($player, $demands, $capSpace, $maxYearOneSalary);

        // Assert
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('ExtensionOffer', $html);
        $this->assertStringContainsString('modules/Player/extension.php', $html);
        $this->assertStringContainsString('offerYear1', $html);
        $this->assertStringContainsString('offerYear2', $html);
        $this->assertStringContainsString('offerYear3', $html);
        $this->assertStringContainsString('offerYear4', $html);
        $this->assertStringContainsString('offerYear5', $html);
    }

    /**
     * @group view
     * @group rendering
     */
    public function testFormContainsAllHiddenFields()
    {
        // Arrange
        $player = $this->createMockPlayer();
        $player->playerID = 123;
        $player->name = "Test Player";
        $player->teamName = "Seattle Supersonics";
        
        $demands = $this->getDefaultDemands();
        $capSpace = 1000;
        $maxYearOneSalary = \FreeAgency\FreeAgencyNegotiationHelper::getMaxContractSalary(0);

        // Act
        $html = NegotiationViewHelper::renderNegotiationForm($player, $demands, $capSpace, $maxYearOneSalary);

        // Assert
        $this->assertStringContainsString('name="maxyr1"', $html);
        $this->assertStringContainsString('value="1063"', $html);
        $this->assertStringContainsString('name="demandsTotal"', $html);
        $this->assertStringContainsString('name="demandsYears"', $html);
        $this->assertStringContainsString('name="teamName"', $html);
        $this->assertStringContainsString('name="playerName"', $html);
        $this->assertStringContainsString('name="playerID"', $html);
        $this->assertStringContainsString('value="123"', $html);
    }

    /**
     * @group view
     * @group security
     */
    public function testEscapesPlayerNameInForm()
    {
        // Arrange
        $player = $this->createMockPlayer();
        $player->name = "O'Neal <script>alert('xss')</script>";
        $demands = $this->getDefaultDemands();
        $capSpace = 1000;
        $maxYearOneSalary = \FreeAgency\FreeAgencyNegotiationHelper::getMaxContractSalary(0);

        // Act
        $html = NegotiationViewHelper::renderNegotiationForm($player, $demands, $capSpace, $maxYearOneSalary);

        // Assert
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('O&apos;Neal', $html);
    }

    /**
     * @group view
     * @group security
     */
    public function testEscapesTeamNameInForm()
    {
        // Arrange
        $player = $this->createMockPlayer();
        $player->teamName = "Team <img src=x onerror=alert(1)>";
        $demands = $this->getDefaultDemands();
        $capSpace = 1000;
        $maxYearOneSalary = \FreeAgency\FreeAgencyNegotiationHelper::getMaxContractSalary(0);

        // Act
        $html = NegotiationViewHelper::renderNegotiationForm($player, $demands, $capSpace, $maxYearOneSalary);

        // Assert
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    /**
     * @group view
     * @group rendering
     */
    public function testShowsEditableFieldsWhenDemandsUnderMax()
    {
        // Arrange
        $player = $this->createMockPlayer();
        $demands = [
            'year1' => 500, // Under max of 1063
            'year2' => 550,
            'year3' => 600,
            'year4' => 0,
            'year5' => 0,
            'year6' => 0,
            'years' => 3,
            'total' => 1650
        ];
        $capSpace = 3000;
        $maxYearOneSalary = \FreeAgency\FreeAgencyNegotiationHelper::getMaxContractSalary(0);

        // Act
        $html = NegotiationViewHelper::renderNegotiationForm($player, $demands, $capSpace, $maxYearOneSalary);

        // Assert
        $this->assertStringContainsString('name="maxyr1"', $html);
        $this->assertStringContainsString('value="1063"', $html);
        $this->assertStringContainsString('VALUE="550"', $html);
        $this->assertStringContainsString('VALUE="600"', $html);
    }

    /**
     * @group view
     * @group rendering
     */
    public function testShowsMaxSalaryFieldsWhenDemandsExceedMax()
    {
        // Arrange
        $player = $this->createMockPlayer();
        $player->birdYears = 2; // No Bird rights
        $demands = [
            'year1' => 1100, // Over max contract salary
            'year2' => 1200,
            'year3' => 1300,
            'year4' => 0,
            'year5' => 0,
            'year6' => 0,
            'years' => 3,
            'total' => 3600
        ];
        $capSpace = 2000;
        $maxYearOneSalary = \FreeAgency\FreeAgencyNegotiationHelper::getMaxContractSalary(0);

        // Act
        $html = NegotiationViewHelper::renderNegotiationForm($player, $demands, $capSpace, $maxYearOneSalary);

        // Assert
        $this->assertStringContainsString('VALUE="1063"', $html); // Max year 1
        $this->assertStringContainsString('TYPE="text"', $html); // Text not number when showing max
    }

    /**
     * @group view
     * @group rendering
     */
    public function testDisplaysBirdRightsMessageWhenApplicable()
    {
        // Arrange
        $player = $this->createMockPlayer();
        $player->birdYears = 3; // Has Bird rights
        $demands = $this->getDefaultDemands();
        $capSpace = 3000;
        $maxYearOneSalary = \FreeAgency\FreeAgencyNegotiationHelper::getMaxContractSalary(0);

        // Act
        $html = NegotiationViewHelper::renderNegotiationForm($player, $demands, $capSpace, $maxYearOneSalary);

        // Assert
        $this->assertStringContainsString('12.5%', $html); // Bird rights percentage
    }

    /**
     * @group view
     * @group rendering
     */
    public function testDisplaysNonBirdRightsMessageWhenNotApplicable()
    {
        // Arrange
        $player = $this->createMockPlayer();
        $player->birdYears = 2; // No Bird rights
        $demands = $this->getDefaultDemands();
        $capSpace = 1000;
        $maxYearOneSalary = \FreeAgency\FreeAgencyNegotiationHelper::getMaxContractSalary(0);

        // Act
        $html = NegotiationViewHelper::renderNegotiationForm($player, $demands, $capSpace, $maxYearOneSalary);

        // Assert
        $this->assertStringContainsString('10%', $html); // No bird rights percentage
    }

    /**
     * @group view
     * @group rendering
     */
    public function testDisplaysCapSpaceInformation()
    {
        // Arrange
        $player = $this->createMockPlayer();
        $demands = $this->getDefaultDemands();
        $capSpace = 1234;
        $maxYearOneSalary = \FreeAgency\FreeAgencyNegotiationHelper::getMaxContractSalary(0);

        // Act
        $html = NegotiationViewHelper::renderNegotiationForm($player, $demands, $capSpace, $maxYearOneSalary);

        // Assert
        $this->assertStringContainsString('1234', $html);
    }

    /**
     * @group view
     * @group error-rendering
     */
    public function testRendersErrorMessage()
    {
        // Arrange
        $errorMessage = "This is a test error";

        // Act
        $html = NegotiationViewHelper::renderError($errorMessage);

        // Assert
        $this->assertStringContainsString('<p>', $html);
        $this->assertStringContainsString('This is a test error', $html);
    }

    /**
     * @group view
     * @group error-rendering
     * @group security
     */
    public function testEscapesErrorMessage()
    {
        // Arrange
        $errorMessage = "<script>alert('xss')</script>";

        // Act
        $html = NegotiationViewHelper::renderError($errorMessage);

        // Assert
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * @group view
     * @group header-rendering
     */
    public function testRendersHeader()
    {
        // Arrange
        $player = $this->createMockPlayer();
        $player->name = "Test Player";
        $player->position = "PG";

        // Act
        $html = NegotiationViewHelper::renderHeader($player);

        // Assert
        $this->assertStringContainsString('<b>PG Test Player</b>', $html);
        $this->assertStringContainsString('Contract Demands', $html);
    }

    /**
     * @group view
     * @group header-rendering
     * @group security
     */
    public function testEscapesPlayerNameInHeader()
    {
        // Arrange
        $player = $this->createMockPlayer();
        $player->name = "O'Neal";
        $player->position = "C";

        // Act
        $html = NegotiationViewHelper::renderHeader($player);

        // Assert
        $this->assertStringContainsString('O&apos;Neal', $html);
    }

    /**
     * Helper to create a mock player
     */
    private function createMockPlayer(): Player
    {
        $player = new Player();
        $player->playerID = 1;
        $player->name = "Test Player";
        $player->position = "PG";
        $player->teamName = "Test Team";
        $player->birdYears = 3;
        
        return $player;
    }

    /**
     * Helper to get default demands array
     */
    private function getDefaultDemands(): array
    {
        return [
            'year1' => 500,
            'year2' => 550,
            'year3' => 600,
            'year4' => 650,
            'year5' => 700,
            'year6' => 0,
            'years' => 5,
            'total' => 3000
        ];
    }
}
