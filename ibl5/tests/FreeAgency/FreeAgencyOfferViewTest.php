<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\FreeAgencyFormComponents;
use FreeAgency\FreeAgencyOfferView;
use PHPUnit\Framework\TestCase;
use Player\Player;
use Team\Team;

/**
 * @covers \FreeAgency\FreeAgencyOfferView
 * @phpstan-import-type NegotiationData from \FreeAgency\FreeAgencyOfferView
 */
class FreeAgencyOfferViewTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/fixtures';

    private Player $player;
    private Team $team;
    private FreeAgencyOfferView $view;

    protected function setUp(): void
    {
        $this->player = self::createStub(Player::class);
        $this->player->method('getPosition')->willReturn('PG');
        $this->player->method('getName')->willReturn('Test Player');
        $this->player->method('getPlayerID')->willReturn(42);
        $this->player->method('getTeamName')->willReturn('TestTeam');
        $this->player->method('getBirdYears')->willReturn(0);
        $this->player->method('getYearsOfExperience')->willReturn(5);
        $this->player->method('getRatingFieldGoalAttempts')->willReturn(1);
        $this->player->method('getRatingFieldGoalPercentage')->willReturn(2);
        $this->player->method('getRatingFreeThrowAttempts')->willReturn(3);
        $this->player->method('getRatingFreeThrowPercentage')->willReturn(4);
        $this->player->method('getRatingThreePointAttempts')->willReturn(5);
        $this->player->method('getRatingThreePointPercentage')->willReturn(6);
        $this->player->method('getRatingOffensiveRebounds')->willReturn(7);
        $this->player->method('getRatingDefensiveRebounds')->willReturn(8);
        $this->player->method('getRatingAssists')->willReturn(9);
        $this->player->method('getRatingSteals')->willReturn(10);
        $this->player->method('getRatingTurnovers')->willReturn(11);
        $this->player->method('getRatingBlocks')->willReturn(12);
        $this->player->method('getRatingFouls')->willReturn(13);
        $this->player->method('getRatingOutsideOffense')->willReturn(14);
        $this->player->method('getRatingDriveOffense')->willReturn(15);
        $this->player->method('getRatingPostOffense')->willReturn(16);
        $this->player->method('getRatingTransitionOffense')->willReturn(17);
        $this->player->method('getRatingOutsideDefense')->willReturn(18);
        $this->player->method('getRatingDriveDefense')->willReturn(19);
        $this->player->method('getRatingPostDefense')->willReturn(20);
        $this->player->method('getRatingTransitionDefense')->willReturn(21);

        $this->team = self::createStub(Team::class);
        $this->team->name = 'TestTeam';
        $this->team->teamid = 1;

        $formComponents = new FreeAgencyFormComponents('TestTeam', $this->player);
        $this->view = new FreeAgencyOfferView($formComponents);
    }

    /**
     * @phpstan-return NegotiationData
     */
    private function baseData(bool $hasExistingOffer = false, int $rosterSpot0 = 1): array
    {
        return [
            'player' => $this->player,
            'capMetrics' => [
                'totalSalaries' => [0, 0, 0, 0, 0, 0],
                'softCapSpace' => [500, 400, 300, 200, 100, 0],
                'hardCapSpace' => [600, 500, 400, 300, 200, 100],
                'rosterSpots' => [$rosterSpot0, 1, 1, 1, 1, 1],
            ],
            'demands' => ['dem1' => 100, 'dem2' => 0, 'dem3' => 50, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0],
            'existingOffer' => ['offer1' => 0, 'offer2' => 0, 'offer3' => 0, 'offer4' => 0, 'offer5' => 0, 'offer6' => 0],
            'amendedCapSpace' => 500,
            'hasExistingOffer' => $hasExistingOffer,
            'veteranMinimum' => 70,
            'maxContract' => 1063,
            'team' => $this->team,
        ];
    }

    private static function normalizeCsrf(string $html): string
    {
        return preg_replace(
            '/name="_csrf_token" value="[0-9a-f]{64}"/',
            'name="_csrf_token" value="__CSRF__"',
            $html
        ) ?? $html;
    }

    public function testFullRenderHappyPath(): void
    {
        $html = $this->view->render($this->baseData());
        $normalized = self::normalizeCsrf($html);
        $this->assertStringEqualsFile(self::FIXTURES . '/fa-offer-full-render.golden.html', $normalized);
    }

    public function testFullRenderWithDeleteForm(): void
    {
        $html = $this->view->render($this->baseData(hasExistingOffer: true));
        $normalized = self::normalizeCsrf($html);
        $this->assertStringEqualsFile(self::FIXTURES . '/fa-offer-full-render-with-delete.golden.html', $normalized);
    }

    public function testNoRosterSpotsEarlyReturn(): void
    {
        $html = $this->view->render($this->baseData(hasExistingOffer: false, rosterSpot0: 0));

        $this->assertStringContainsString('Sorry, you have no roster spots remaining', $html);
        $this->assertStringNotContainsString('Quick Offer Presets', $html);
    }

    public function testErrorBannerRendersEvenWithZeroSpots(): void
    {
        $html = $this->view->render($this->baseData(hasExistingOffer: false, rosterSpot0: 0), 'Offer rejected');

        $this->assertStringContainsString('ibl-alert--error', $html);
        $this->assertStringContainsString('Offer rejected', $html);
    }

    public function testExistingOfferSuppressesNoSpotsEarlyReturn(): void
    {
        $html = $this->view->render($this->baseData(hasExistingOffer: true, rosterSpot0: 0));

        $this->assertStringNotContainsString('no roster spots remaining', $html);
        $this->assertStringContainsString('Quick Offer Presets', $html);
    }

    public function testDeleteFormAbsentWhenNoExistingOffer(): void
    {
        $html = $this->view->render($this->baseData());

        $this->assertStringNotContainsString('pa=deleteoffer', $html);
    }

    public function testDeleteFormPresentWhenHasExistingOffer(): void
    {
        $html = $this->view->render($this->baseData(hasExistingOffer: true));

        $this->assertStringContainsString('pa=deleteoffer', $html);
    }

    public function testSharedCsrfTokenReusedAcrossAllForms(): void
    {
        $html = $this->view->render($this->baseData());

        preg_match_all('/value="([0-9a-f]{64})"/', $html, $matches);
        $uniqueTokens = array_unique($matches[1]);

        $this->assertNotEmpty($uniqueTokens, 'Expected at least one CSRF token in full render');
        $this->assertCount(1, $uniqueTokens, 'All forms must share exactly one CSRF token');
    }
}
