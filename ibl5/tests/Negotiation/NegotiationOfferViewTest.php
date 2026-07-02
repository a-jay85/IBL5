<?php

declare(strict_types=1);

namespace Tests\Negotiation;

use PHPUnit\Framework\TestCase;
use Negotiation\NegotiationOfferView;
use Player\Player;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\Mocks\TestDataFactory;

/**
 * Tests for NegotiationOfferView
 *
 * Tests HTML rendering for negotiation interface:
 * - Form rendering with proper escaping
 * - Demand display formatting
 * - Error message rendering
 * - Header rendering
 */
class NegotiationOfferViewTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    /**
     * @group view
     * @group rendering
     */
    public function testRendersNegotiationFormWithAllFields(): void
    {
        // Arrange
        $player = $this->createTestPlayer();
        $demands = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 600,
            'year4' => 650,
            'year5' => 700,
            'year6' => 0,
            'years' => 5,
            'total' => 3000,
            'modifier' => 1.0,
        ];
        $capSpace = 1000;
        $maxYearOneSalary = \ContractRules::getMaxContractSalary(0);

        // Act
        $html = NegotiationOfferView::renderNegotiationForm($player, $demands, $capSpace, $maxYearOneSalary);

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
    public function testFormContainsAllHiddenFields(): void
    {
        // Arrange
        $player = $this->createTestPlayer(['pid' => 123, 'teamname' => 'Seattle Supersonics']);
        
        $demands = $this->getDefaultDemands();
        $capSpace = 1000;
        $maxYearOneSalary = \ContractRules::getMaxContractSalary(0);

        // Act
        $html = NegotiationOfferView::renderNegotiationForm($player, $demands, $capSpace, $maxYearOneSalary);

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
    public function testEscapesPlayerNameInForm(): void
    {
        // Arrange
        $player = $this->createTestPlayer(['name' => "O'Neal <script>alert('xss')</script>"]);
        $demands = $this->getDefaultDemands();
        $capSpace = 1000;
        $maxYearOneSalary = \ContractRules::getMaxContractSalary(0);

        // Act
        $html = NegotiationOfferView::renderNegotiationForm($player, $demands, $capSpace, $maxYearOneSalary);

        // Assert
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('O&apos;Neal', $html);
    }

    /**
     * @group view
     * @group security
     */
    public function testEscapesTeamNameInForm(): void
    {
        // Arrange
        $player = $this->createTestPlayer(['teamname' => "Team <img src=x onerror=alert(1)>"]);
        $demands = $this->getDefaultDemands();
        $capSpace = 1000;
        $maxYearOneSalary = \ContractRules::getMaxContractSalary(0);

        // Act
        $html = NegotiationOfferView::renderNegotiationForm($player, $demands, $capSpace, $maxYearOneSalary);

        // Assert — the injected tag's angle brackets are escaped, preventing injection
        $this->assertStringContainsString('&lt;img', $html);
        // No unescaped <img tag with an onerror handler (legitimate player photo <img> is fine)
        $this->assertStringNotContainsString('<img src=x onerror', $html);
    }

    /**
     * @group view
     * @group rendering
     */
    public function testShowsEditableFieldsWhenDemandsUnderMax(): void
    {
        // Arrange
        $player = $this->createTestPlayer();
        $demands = [
            'year1' => 500, // Under max of 1063
            'year2' => 550,
            'year3' => 600,
            'year4' => 0,
            'year5' => 0,
            'year6' => 0,
            'years' => 3,
            'total' => 1650,
            'modifier' => 1.0,
        ];
        $capSpace = 3000;
        $maxYearOneSalary = \ContractRules::getMaxContractSalary(0);

        // Act
        $html = NegotiationOfferView::renderNegotiationForm($player, $demands, $capSpace, $maxYearOneSalary);

        // Assert
        $this->assertStringContainsString('name="maxyr1"', $html);
        $this->assertStringContainsString('value="1063"', $html);
        $this->assertStringContainsString('value="550"', $html);
        $this->assertStringContainsString('value="600"', $html);
    }

    /**
     * @group view
     * @group rendering
     */
    public function testShowsMaxSalaryFieldsWhenDemandsExceedMax(): void
    {
        // Arrange
        $player = $this->createTestPlayer(['bird' => 2]); // No Bird rights
        $demands = [
            'year1' => 1100, // Over max contract salary
            'year2' => 1200,
            'year3' => 1300,
            'year4' => 0,
            'year5' => 0,
            'year6' => 0,
            'years' => 3,
            'total' => 3600,
            'modifier' => 1.0,
        ];
        $capSpace = 2000;
        $maxYearOneSalary = \ContractRules::getMaxContractSalary(0);

        // Act
        $html = NegotiationOfferView::renderNegotiationForm($player, $demands, $capSpace, $maxYearOneSalary);

        // Assert
        $this->assertStringContainsString('value="1063"', $html); // Max year 1
        $this->assertStringContainsString('type="number"', $html); // Number inputs for max salary fields
    }

    /**
     * @group view
     * @group rendering
     */
    public function testDisplaysBirdRightsMessageWhenApplicable(): void
    {
        // Arrange
        $player = $this->createTestPlayer(['bird' => 3]); // Has Bird rights
        $demands = $this->getDefaultDemands();
        $capSpace = 3000;
        $maxYearOneSalary = \ContractRules::getMaxContractSalary(0);

        // Act
        $html = NegotiationOfferView::renderNegotiationForm($player, $demands, $capSpace, $maxYearOneSalary);

        // Assert
        $this->assertStringContainsString('12.5%', $html); // Bird rights percentage
    }

    /**
     * @group view
     * @group rendering
     */
    public function testDisplaysNonBirdRightsMessageWhenNotApplicable(): void
    {
        // Arrange
        $player = $this->createTestPlayer(['bird' => 2]); // No Bird rights
        $demands = $this->getDefaultDemands();
        $capSpace = 1000;
        $maxYearOneSalary = \ContractRules::getMaxContractSalary(0);

        // Act
        $html = NegotiationOfferView::renderNegotiationForm($player, $demands, $capSpace, $maxYearOneSalary);

        // Assert
        $this->assertStringContainsString('10%', $html); // No bird rights percentage
    }

    /**
     * @group view
     * @group rendering
     */
    public function testDisplaysCapSpaceInformation(): void
    {
        // Arrange
        $player = $this->createTestPlayer();
        $demands = $this->getDefaultDemands();
        $capSpace = 1234;
        $maxYearOneSalary = \ContractRules::getMaxContractSalary(0);

        // Act
        $html = NegotiationOfferView::renderNegotiationForm($player, $demands, $capSpace, $maxYearOneSalary);

        // Assert
        $this->assertStringContainsString('1234', $html);
    }

    /**
     * @group view
     * @group error-rendering
     */
    public function testRendersErrorMessage(): void
    {
        // Arrange
        $errorMessage = "This is a test error";

        // Act
        $html = NegotiationOfferView::renderError($errorMessage);

        // Assert
        $this->assertStringContainsString('ibl-alert ibl-alert--error', $html);
        $this->assertStringContainsString('This is a test error', $html);
    }

    /**
     * @group view
     * @group error-rendering
     * @group security
     */
    public function testEscapesErrorMessage(): void
    {
        // Arrange
        $errorMessage = "<script>alert('xss')</script>";

        // Act
        $html = NegotiationOfferView::renderError($errorMessage);

        // Assert
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * @group view
     * @group header-rendering
     */
    public function testRendersHeader(): void
    {
        // Arrange
        $player = $this->createTestPlayer();

        // Act
        $html = NegotiationOfferView::renderHeader($player);

        // Assert
        $this->assertStringContainsString('ibl-title', $html);
        $this->assertStringContainsString('Contract Extension', $html);
    }

    /**
     * @group view
     * @group header-rendering
     */
    public function testHeaderReturnsStaticTitle(): void
    {
        // Arrange
        $player = $this->createTestPlayer();

        // Act
        $html = NegotiationOfferView::renderHeader($player);

        // Assert — header is a static title, player name is in the card
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Contract Extension', $html);
    }

    /**
     * Card 1 now hosts the Player module's flippable trading card, which the
     * service assembles from $db and passes in as pre-rendered HTML. The view
     * must emit that string verbatim (and, with no card, must NOT fabricate a
     * card wrapper or carry over the removed ratings-bar / small-img markup).
     *
     * @group view
     * @group rendering
     */
    public function testRendersProvidedCardHtml(): void
    {
        $player = $this->createTestPlayer();
        $maxYearOneSalary = \ContractRules::getMaxContractSalary(0);
        $cardHtml = '<div class="card-flip-container">SENTINEL</div>';

        $html = NegotiationOfferView::renderNegotiationForm(
            $player,
            $this->getDefaultDemands(),
            1000,
            $maxYearOneSalary,
            $cardHtml
        );

        // The provided card HTML is emitted verbatim.
        $this->assertStringContainsString('card-flip-container', $html);
        $this->assertStringContainsString('SENTINEL', $html);

        // The old ratings bar / small img are gone.
        $this->assertStringNotContainsString('offer-player-img', $html);
        $this->assertStringNotContainsString('offer-ratings', $html);

        // Boundary: with no card, the view emits no card wrapper.
        $htmlNoCard = NegotiationOfferView::renderNegotiationForm(
            $player,
            $this->getDefaultDemands(),
            1000,
            $maxYearOneSalary
        );
        $this->assertStringNotContainsString('card-flip-container', $htmlNoCard);
    }

    /**
     * Helper to create a test player using Player::withPlrRow and TestDataFactory
     *
     * @param array<string, mixed> $overrides
     */
    private function createTestPlayer(array $overrides = []): Player
    {
        $defaults = [
            'pid' => 1,
            'name' => 'Test Player',
            'position' => 'PG',
            'pos' => 'PG',
            'teamname' => 'Test Team',
            'bird' => 3,
        ];

        return Player::withPlrRow($this->mockDb, TestDataFactory::createPlayer(array_merge($defaults, $overrides)));
    }

    /**
     * Helper to get default demands array
     */
    /**
     * @return array{year1: int, year2: int, year3: int, year4: int, year5: int, year6: int, years: int, total: int, modifier: float}
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
            'total' => 3000,
            'modifier' => 1.0,
        ];
    }
}
