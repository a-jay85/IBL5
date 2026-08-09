<?php

declare(strict_types=1);

namespace Tests\Player\Views;

use PHPUnit\Framework\TestCase;
use Player\Player;
use Player\Stats\PlayerStats;
use Player\Views\PlayerTradingCardBackView;

/** @covers \Player\Views\PlayerTradingCardBackView */
class PlayerTradingCardBackViewTest extends TestCase
{
    use SnapshotTestTrait;

    /**
     * Build a Player stub with every getter called by CardBaseStyles::preparePlayerData()
     * and getColorSchemeForTeam().
     *
     * @return Player&\PHPUnit\Framework\MockObject\Stub
     */
    private function makePlayer(): Player
    {
        /** @var Player&\PHPUnit\Framework\MockObject\Stub $player */
        $player = self::createStub(Player::class);

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

        return $player;
    }

    /**
     * Build a PlayerStats stub with every public property accessed by
     * PlayerTradingCardBackView::render() set to a distinct non-zero value.
     *
     * Constructor is disabled by createStub(); properties are assigned directly.
     * All 24 accessed properties must be set — typed properties without a default
     * throw an Error on access if left uninitialized.
     *
     * @return PlayerStats&\PHPUnit\Framework\MockObject\Stub
     */
    private function makePlayerStats(): PlayerStats
    {
        /** @var PlayerStats&\PHPUnit\Framework\MockObject\Stub $stats */
        $stats = self::createStub(PlayerStats::class);

        // Points highs
        $stats->seasonHighPoints = 38;
        $stats->careerSeasonHighPoints = 45;
        $stats->seasonPlayoffHighPoints = 32;
        $stats->careerPlayoffHighPoints = 40;

        // Rebounds highs
        $stats->seasonHighRebounds = 15;
        $stats->careerSeasonHighRebounds = 18;
        $stats->seasonPlayoffHighRebounds = 12;
        $stats->careerPlayoffHighRebounds = 16;

        // Assists highs
        $stats->seasonHighAssists = 12;
        $stats->careerSeasonHighAssists = 14;
        $stats->seasonPlayoffHighAssists = 10;
        $stats->careerPlayoffHighAssists = 13;

        // Steals highs
        $stats->seasonHighSteals = 5;
        $stats->careerSeasonHighSteals = 6;
        $stats->seasonPlayoffHighSteals = 4;
        $stats->careerPlayoffHighSteals = 5;

        // Blocks highs
        $stats->seasonHighBlocks = 6;
        $stats->careerSeasonHighBlocks = 7;
        $stats->seasonPlayoffHighBlocks = 5;
        $stats->careerPlayoffHighBlocks = 6;

        // Double/triple doubles
        $stats->seasonDoubleDoubles = 8;
        $stats->careerDoubleDoubles = 42;
        $stats->seasonTripleDoubles = 3;
        $stats->careerTripleDoubles = 11;

        return $stats;
    }

    /**
     * getStyles() is deprecated and always returns ''; it is not under test here.
     * This test exercises render() to maintain 4-test coverage.
     */
    public function testRenderContainsPlayerHighsSection(): void
    {
        $player = $this->makePlayer();
        $stats  = $this->makePlayerStats();

        $result = PlayerTradingCardBackView::render($player, $stats, 42, 0, 0, 0, 0, null);

        $this->assertStringContainsString('Player Highs', $result);
    }

    public function testRenderWithNonZeroAllStarCountsSnapshot(): void
    {
        $player = $this->makePlayer();
        $stats  = $this->makePlayerStats();

        $result = PlayerTradingCardBackView::render($player, $stats, 42, 3, 1, 1, 1, null);

        // Distinct non-zero values (3, 1, 1, 1) so any single-count mutant changes output.
        $this->assertSnapshotMatches($result, 'PlayerTradingCardBackView-with-allstar.html');
    }

    public function testRenderWithZeroAllStarCountsProducesDifferentOutput(): void
    {
        $player = $this->makePlayer();
        $stats  = $this->makePlayerStats();

        $withCounts = PlayerTradingCardBackView::render($player, $stats, 42, 3, 1, 1, 1, null);
        $withZeros  = PlayerTradingCardBackView::render($player, $stats, 42, 0, 0, 0, 0, null);

        // Note: renderAllStarPill() has no > 0 guard — pills always render.
        // The four count values differ between calls (3/1/1/1 vs 0/0/0/0), so the
        // rendered pill text differs and the two outputs are not identical.
        $this->assertNotSame($withCounts, $withZeros);
    }

    public function testRenderContainsPlayerID(): void
    {
        $player = $this->makePlayer();
        $stats  = $this->makePlayerStats();

        $result = PlayerTradingCardBackView::render($player, $stats, 99, 0, 0, 0, 0, null);

        $this->assertStringContainsString('99', $result);
    }
}
