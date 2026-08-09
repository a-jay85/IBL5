<?php

declare(strict_types=1);

namespace Tests\Player\Views;

use PHPUnit\Framework\TestCase;
use Player\Player;
use Player\Stats\PlayerStats;
use Player\Views\PlayerTradingCardFlipView;

/** @covers \Player\Views\PlayerTradingCardFlipView */
class PlayerTradingCardFlipViewTest extends TestCase
{
    use SnapshotTestTrait;

    /**
     * Build a Player stub with every getter called by both sub-views (FrontView + BackView)
     * and CardBaseStyles::preparePlayerData() / getColorSchemeForTeam().
     *
     * FlipView delegates to both sub-views internally, so all FrontView getters
     * (the superset) must be configured.
     *
     * @return Player&\PHPUnit\Framework\MockObject\Stub
     */
    private function makePlayer(): Player
    {
        /** @var Player&\PHPUnit\Framework\MockObject\Stub $player */
        $player = self::createStub(Player::class);

        // CardBaseStyles::preparePlayerData() and getColorSchemeForTeam() getters
        $player->method('getTeamid')->willReturn(7);
        $player->method('getName')->willReturn('Test Player');
        $player->method('getNickname')->willReturn(null);
        $player->method('getPosition')->willReturn('SF');
        $player->method('getTeamName')->willReturn('Portland');
        $player->method('getAge')->willReturn(28);
        $player->method('getHeightFeet')->willReturn(6);
        $player->method('getHeightInches')->willReturn(7);
        $player->method('getWeightPounds')->willReturn(220);
        $player->method('getCollegeName')->willReturn('Duke');
        $player->method('getDraftYear')->willReturn(2019);
        $player->method('getDraftRound')->willReturn(1);
        $player->method('getDraftPickNumber')->willReturn(14);
        $player->method('getDraftTeamOriginalName')->willReturn('Chicago');

        // FrontView rating row 1 — shooting
        $player->method('getRatingFieldGoalAttempts')->willReturn(7);
        $player->method('getRatingFieldGoalPercentage')->willReturn(50);
        $player->method('getRatingFreeThrowAttempts')->willReturn(6);
        $player->method('getRatingFreeThrowPercentage')->willReturn(78);
        $player->method('getRatingThreePointAttempts')->willReturn(5);
        $player->method('getRatingThreePointPercentage')->willReturn(42);

        // FrontView rating row 2 — rebounding/defense
        $player->method('getRatingOffensiveRebounds')->willReturn(3);
        $player->method('getRatingDefensiveRebounds')->willReturn(7);
        $player->method('getRatingAssists')->willReturn(4);
        $player->method('getRatingSteals')->willReturn(2);
        $player->method('getRatingTurnovers')->willReturn(3);
        $player->method('getRatingBlocks')->willReturn(1);
        $player->method('getRatingFouls')->willReturn(3);

        // FrontView rating row 3 — offense/defense
        $player->method('getRatingOutsideOffense')->willReturn(6);
        $player->method('getRatingDriveOffense')->willReturn(7);
        $player->method('getRatingPostOffense')->willReturn(4);
        $player->method('getRatingTransitionOffense')->willReturn(8);
        $player->method('getRatingOutsideDefense')->willReturn(5);
        $player->method('getRatingDriveDefense')->willReturn(6);
        $player->method('getRatingPostDefense')->willReturn(4);
        $player->method('getRatingTransitionDefense')->willReturn(7);

        // FrontView intangibles pills
        $player->method('getRatingTalent')->willReturn(85);
        $player->method('getRatingSkill')->willReturn(78);
        $player->method('getRatingIntangibles')->willReturn(72);
        $player->method('getRatingClutch')->willReturn(80);
        $player->method('getRatingConsistency')->willReturn(75);

        // FrontView free agency preference pills
        $player->method('getFreeAgencyLoyalty')->willReturn(6);
        $player->method('getFreeAgencyPlayForWinner')->willReturn(8);
        $player->method('getFreeAgencyPlayingTime')->willReturn(7);
        $player->method('getFreeAgencySecurity')->willReturn(5);
        $player->method('getFreeAgencyTradition')->willReturn(4);

        // FrontView contract footer
        $player->method('getYearsOfExperience')->willReturn(5);
        $player->method('getBirdYears')->willReturn(3);

        return $player;
    }

    /**
     * Build a PlayerStats stub with every public property accessed by BackView set.
     *
     * @return PlayerStats&\PHPUnit\Framework\MockObject\Stub
     */
    private function makePlayerStats(): PlayerStats
    {
        /** @var PlayerStats&\PHPUnit\Framework\MockObject\Stub $stats */
        $stats = self::createStub(PlayerStats::class);

        $stats->seasonHighPoints = 38;
        $stats->careerSeasonHighPoints = 45;
        $stats->seasonPlayoffHighPoints = 32;
        $stats->careerPlayoffHighPoints = 40;

        $stats->seasonHighRebounds = 15;
        $stats->careerSeasonHighRebounds = 18;
        $stats->seasonPlayoffHighRebounds = 12;
        $stats->careerPlayoffHighRebounds = 16;

        $stats->seasonHighAssists = 12;
        $stats->careerSeasonHighAssists = 14;
        $stats->seasonPlayoffHighAssists = 10;
        $stats->careerPlayoffHighAssists = 13;

        $stats->seasonHighSteals = 5;
        $stats->careerSeasonHighSteals = 6;
        $stats->seasonPlayoffHighSteals = 4;
        $stats->careerPlayoffHighSteals = 5;

        $stats->seasonHighBlocks = 6;
        $stats->careerSeasonHighBlocks = 7;
        $stats->seasonPlayoffHighBlocks = 5;
        $stats->careerPlayoffHighBlocks = 6;

        $stats->seasonDoubleDoubles = 8;
        $stats->careerDoubleDoubles = 42;
        $stats->seasonTripleDoubles = 3;
        $stats->careerTripleDoubles = 11;

        return $stats;
    }

    public function testGetFlipStylesReturnsNonEmptyString(): void
    {
        $result = PlayerTradingCardFlipView::getFlipStyles();

        $this->assertNotEmpty($result);
    }

    public function testRenderContainsBothFacesSnapshot(): void
    {
        $player = $this->makePlayer();
        $stats  = $this->makePlayerStats();

        $result = PlayerTradingCardFlipView::render($player, $stats, 42, 'Y3/$12M', 0, 0, 0, 0, null);

        // Snapshot captures both card-front (ratings) and card-back (season highs) structure.
        // Any regression in either sub-view breaks this snapshot.
        $this->assertSnapshotMatches($result, 'PlayerTradingCardFlipView.html');
    }

    public function testRenderContainsContractDisplay(): void
    {
        $player = $this->makePlayer();
        $stats  = $this->makePlayerStats();

        $result = PlayerTradingCardFlipView::render($player, $stats, 42, 'Y3/$12M', 0, 0, 0, 0, null);

        // Confirms the contract string is passed through to FrontView.
        $this->assertStringContainsString('Y3/$12M', $result);
    }

    public function testRenderContainsPlayerID(): void
    {
        $player = $this->makePlayer();
        $stats  = $this->makePlayerStats();

        $result = PlayerTradingCardFlipView::render($player, $stats, 42, 'Y3/$12M', 0, 0, 0, 0, null);

        // Separate from contract test — kills independent playerID mutants.
        $this->assertStringContainsString('42', $result);
    }
}
