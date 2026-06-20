<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\FreeAgencyFormComponents;
use PHPUnit\Framework\TestCase;
use Player\Player;

/** @covers \FreeAgency\FreeAgencyFormComponents */
class FreeAgencyFormComponentsTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/fixtures';

    private Player $player;
    private FreeAgencyFormComponents $formComponents;

    protected function setUp(): void
    {
        $this->player = self::createStub(Player::class);
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
        $this->player->method('getPlayerID')->willReturn(42);
        $this->player->method('getYearsOfExperience')->willReturn(5);
        $this->player->method('getTeamName')->willReturn('TestTeam');

        // No setCsrfHtml() call — $csrfHtml defaults to '' → deterministic output
        $this->formComponents = new FreeAgencyFormComponents('TestTeam', $this->player);
    }

    public function testRenderPlayerRatings(): void
    {
        $html = FreeAgencyFormComponents::renderPlayerRatings($this->player);
        $golden = self::FIXTURES . '/fa-form-player-ratings.golden.html';
        $this->assertStringEqualsFile($golden, $html);
    }

    public function testRenderDemandDisplay(): void
    {
        $html = $this->formComponents->renderDemandDisplay([
            'dem1' => 100,
            'dem2' => 0,
            'dem3' => 50,
            'dem4' => 0,
            'dem5' => 0,
            'dem6' => 0,
        ]);
        $golden = self::FIXTURES . '/fa-form-demand-display.golden.html';
        $this->assertStringEqualsFile($golden, $html);
    }

    public function testRenderOfferInputs(): void
    {
        $html = $this->formComponents->renderOfferInputs([
            'offer1' => 0,
            'offer2' => 200,
            'offer3' => 220,
            'offer4' => 0,
            'offer5' => 0,
            'offer6' => 0,
        ], 0.10);
        $golden = self::FIXTURES . '/fa-form-offer-inputs.golden.html';
        $this->assertStringEqualsFile($golden, $html);
    }

    public function testRenderMaxContractButtonsNoBird(): void
    {
        $html = $this->formComponents->renderMaxContractButtons(
            [0 => 1063, 1 => 1169, 2 => 1275, 3 => 1381, 4 => 1487, 5 => 1593],
            0
        );
        $golden = self::FIXTURES . '/fa-form-max-contract-buttons.golden.html';
        $this->assertStringEqualsFile($golden, $html);
    }

    public function testRenderMaxContractButtonsWithBird(): void
    {
        $html = $this->formComponents->renderMaxContractButtons(
            [0 => 1063, 1 => 1196, 2 => 1329, 3 => 1462, 4 => 1595, 5 => 1728],
            3
        );
        $golden = self::FIXTURES . '/fa-form-max-contract-buttons-bird.golden.html';
        $this->assertStringEqualsFile($golden, $html);
    }

    public function testRenderExceptionButtonsMle(): void
    {
        $html = $this->formComponents->renderExceptionButtons('MLE');
        $golden = self::FIXTURES . '/fa-form-exception-mle.golden.html';
        $this->assertStringEqualsFile($golden, $html);
    }

    public function testRenderExceptionButtonsLle(): void
    {
        $html = $this->formComponents->renderExceptionButtons('LLE');
        $golden = self::FIXTURES . '/fa-form-exception-lle.golden.html';
        $this->assertStringEqualsFile($golden, $html);
    }

    public function testRenderExceptionButtonsVet(): void
    {
        $html = $this->formComponents->renderExceptionButtons('VET');
        $golden = self::FIXTURES . '/fa-form-exception-vet.golden.html';
        $this->assertStringEqualsFile($golden, $html);
    }

    public function testRenderExceptionButtonsUnrecognizedTypeReturnsEmpty(): void
    {
        $html = $this->formComponents->renderExceptionButtons('FOO');
        $this->assertSame('', $html);
    }
}
