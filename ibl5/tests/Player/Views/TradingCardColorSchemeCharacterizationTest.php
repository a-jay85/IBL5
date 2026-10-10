<?php

declare(strict_types=1);

namespace Tests\Player\Views;

use PHPUnit\Framework\TestCase;
use Player\Player;
use Player\TeamColorSchemeResolver;
use Player\Stats\PlayerStats;
use Player\Views\PlayerTradingCardBackView;
use Player\Views\PlayerTradingCardFlipView;
use Player\Views\PlayerTradingCardFrontView;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

/**
 * Pins the team-colored, teamid-0, and missing-row card renders so the color
 * scheme can move out of the views without changing a byte of HTML.
 *
 * @covers \Player\Views\PlayerTradingCardFlipView
 * @covers \Player\Views\PlayerTradingCardFrontView
 * @covers \Player\Views\PlayerTradingCardBackView
 */
final class TradingCardColorSchemeCharacterizationTest extends TestCase
{
    use SnapshotTestTrait;

    /**
     * @return Player&\PHPUnit\Framework\MockObject\Stub
     */
    private function makePlayer(int $teamid): Player
    {
        /** @var Player&\PHPUnit\Framework\MockObject\Stub $player */
        $player = self::createStub(Player::class);

        $player->method('getTeamid')->willReturn($teamid);
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

        $player->method('getRatingFieldGoalAttempts')->willReturn(7);
        $player->method('getRatingFieldGoalPercentage')->willReturn(50);
        $player->method('getRatingFreeThrowAttempts')->willReturn(6);
        $player->method('getRatingFreeThrowPercentage')->willReturn(78);
        $player->method('getRatingThreePointAttempts')->willReturn(5);
        $player->method('getRatingThreePointPercentage')->willReturn(42);

        $player->method('getRatingOffensiveRebounds')->willReturn(3);
        $player->method('getRatingDefensiveRebounds')->willReturn(7);
        $player->method('getRatingAssists')->willReturn(4);
        $player->method('getRatingSteals')->willReturn(2);
        $player->method('getRatingTurnovers')->willReturn(3);
        $player->method('getRatingBlocks')->willReturn(1);
        $player->method('getRatingFouls')->willReturn(3);

        $player->method('getRatingOutsideOffense')->willReturn(6);
        $player->method('getRatingDriveOffense')->willReturn(7);
        $player->method('getRatingPostOffense')->willReturn(4);
        $player->method('getRatingTransitionOffense')->willReturn(8);
        $player->method('getRatingOutsideDefense')->willReturn(5);
        $player->method('getRatingDriveDefense')->willReturn(6);
        $player->method('getRatingPostDefense')->willReturn(4);
        $player->method('getRatingTransitionDefense')->willReturn(7);

        $player->method('getRatingTalent')->willReturn(85);
        $player->method('getRatingSkill')->willReturn(78);
        $player->method('getRatingIntangibles')->willReturn(72);
        $player->method('getRatingClutch')->willReturn(80);
        $player->method('getRatingConsistency')->willReturn(75);

        $player->method('getFreeAgencyLoyalty')->willReturn(6);
        $player->method('getFreeAgencyPlayForWinner')->willReturn(8);
        $player->method('getFreeAgencyPlayingTime')->willReturn(7);
        $player->method('getFreeAgencySecurity')->willReturn(5);
        $player->method('getFreeAgencyTradition')->willReturn(4);

        $player->method('getYearsOfExperience')->willReturn(5);
        $player->method('getBirdYears')->willReturn(3);

        return $player;
    }

    /**
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

    /**
     * @param array{color1: string, color2: string}|null $row
     * @return TeamIdentityRepositoryInterface&\PHPUnit\Framework\MockObject\Stub
     */
    private function makeRepoStub(?array $row): TeamIdentityRepositoryInterface
    {
        /** @var TeamIdentityRepositoryInterface&\PHPUnit\Framework\MockObject\Stub $repo */
        $repo = self::createStub(TeamIdentityRepositoryInterface::class);
        $repo->method('getTeamColorRow')->willReturn($row);

        return $repo;
    }

    public function testFlipCardWithTeamColorRowSnapshot(): void
    {
        $repo = $this->makeRepoStub(['color1' => 'C8102E', 'color2' => '1D428A']);

        $html = PlayerTradingCardFlipView::render(
            $this->makePlayer(7),
            $this->makePlayerStats(),
            42,
            'Y3/$12M',
            0,
            0,
            0,
            0,
            TeamColorSchemeResolver::forTradingCard($repo, 7)
        );

        $this->assertSnapshotMatches($html, 'TradingCardFlipView.teamColors.html');
        // The generated scheme carries color2 as the gradient endpoints, so the snapshot is not the gold default.
        $this->assertStringContainsStringIgnoringCase('--card-grad-start:#1D428A', $html);
        $this->assertStringNotContainsStringIgnoringCase('--card-grad-start:#1e3a5f', $html);
    }

    public function testFlipCardForTeamZeroUsesDefaultSchemeSnapshot(): void
    {
        $repo = $this->createMock(TeamIdentityRepositoryInterface::class);
        $repo->expects(self::never())->method('getTeamColorRow');

        $html = PlayerTradingCardFlipView::render(
            $this->makePlayer(0),
            $this->makePlayerStats(),
            42,
            'Y3/$12M',
            0,
            0,
            0,
            0,
            TeamColorSchemeResolver::forTradingCard($repo, 0)
        );

        $this->assertSnapshotMatches($html, 'TradingCardFlipView.teamZero.html');
        // The seeded Free Agents row is gray (888888); the trading card must stay gold.
        $this->assertStringNotContainsStringIgnoringCase('888888', $html);
    }

    public function testFlipCardWithMissingColorRowSnapshot(): void
    {
        $repo = $this->makeRepoStub(null);

        $html = PlayerTradingCardFlipView::render(
            $this->makePlayer(7),
            $this->makePlayerStats(),
            42,
            'Y3/$12M',
            0,
            0,
            0,
            0,
            TeamColorSchemeResolver::forTradingCard($repo, 7)
        );

        $this->assertSnapshotMatches($html, 'TradingCardFlipView.missingRow.html');
        // A missing row falls back to the gold default scheme (navy gradient endpoints).
        $this->assertStringContainsStringIgnoringCase('--card-grad-start:#1e3a5f', $html);
    }

    public function testFrontCardWithTeamColorRowSnapshot(): void
    {
        $repo = $this->makeRepoStub(['color1' => 'C8102E', 'color2' => '1D428A']);

        $html = PlayerTradingCardFrontView::render($this->makePlayer(7), 42, 'Y3/$12M', TeamColorSchemeResolver::forTradingCard($repo, 7));

        $this->assertSnapshotMatches($html, 'TradingCardFrontView.teamColors.html');
    }

    public function testBackCardWithTeamColorRowSnapshot(): void
    {
        $repo = $this->makeRepoStub(['color1' => 'C8102E', 'color2' => '1D428A']);

        $html = PlayerTradingCardBackView::render(
            $this->makePlayer(7),
            $this->makePlayerStats(),
            42,
            3,
            1,
            1,
            1,
            TeamColorSchemeResolver::forTradingCard($repo, 7)
        );

        $this->assertSnapshotMatches($html, 'TradingCardBackView.teamColors.html');
    }
}
