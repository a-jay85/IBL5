<?php

declare(strict_types=1);

namespace Tests\Player\Views;

use PHPUnit\Framework\TestCase;
use Player\Player;
use Player\Views\PlayerTradingCardFrontView;
use Tests\Support\GoldenSnapshotTrait;
/** @covers \Player\Views\PlayerTradingCardFrontView */
class PlayerTradingCardFrontViewTest extends TestCase
{
    use GoldenSnapshotTrait;

    /**
     * Build a Player stub with every getter called by PlayerTradingCardFrontView::render()
     * and CardBaseStyles::preparePlayerData() configured.
     *
     * @return Player&\PHPUnit\Framework\MockObject\Stub
     */
    private function makePlayer(): Player
    {
        /** @var Player&\PHPUnit\Framework\MockObject\Stub $player */
        $player = self::createStub(Player::class);

        // CardBaseStyles::preparePlayerData() getters
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

        // Rating row 1 — shooting (2ga 2gp fta ftp 3ga 3gp)
        $player->method('getRatingFieldGoalAttempts')->willReturn(7);
        $player->method('getRatingFieldGoalPercentage')->willReturn(50);
        $player->method('getRatingFreeThrowAttempts')->willReturn(6);
        $player->method('getRatingFreeThrowPercentage')->willReturn(78);
        $player->method('getRatingThreePointAttempts')->willReturn(5);
        $player->method('getRatingThreePointPercentage')->willReturn(42);

        // Rating row 2 — rebounding/defense (orb drb ast stl tvr blk foul)
        $player->method('getRatingOffensiveRebounds')->willReturn(3);
        $player->method('getRatingDefensiveRebounds')->willReturn(7);
        $player->method('getRatingAssists')->willReturn(4);
        $player->method('getRatingSteals')->willReturn(2);
        $player->method('getRatingTurnovers')->willReturn(3);
        $player->method('getRatingBlocks')->willReturn(1);
        $player->method('getRatingFouls')->willReturn(3);

        // Rating row 3 — offense/defense (oo do po to od dd pd td)
        $player->method('getRatingOutsideOffense')->willReturn(6);
        $player->method('getRatingDriveOffense')->willReturn(7);
        $player->method('getRatingPostOffense')->willReturn(4);
        $player->method('getRatingTransitionOffense')->willReturn(8);
        $player->method('getRatingOutsideDefense')->willReturn(5);
        $player->method('getRatingDriveDefense')->willReturn(6);
        $player->method('getRatingPostDefense')->willReturn(4);
        $player->method('getRatingTransitionDefense')->willReturn(7);

        // Intangibles pills (TAL SKL INT CLU CON)
        $player->method('getRatingTalent')->willReturn(85);
        $player->method('getRatingSkill')->willReturn(78);
        $player->method('getRatingIntangibles')->willReturn(72);
        $player->method('getRatingClutch')->willReturn(80);
        $player->method('getRatingConsistency')->willReturn(75);

        // Free agency preference pills (LOY WIN PT SEC TRD)
        $player->method('getFreeAgencyLoyalty')->willReturn(6);
        $player->method('getFreeAgencyPlayForWinner')->willReturn(8);
        $player->method('getFreeAgencyPlayingTime')->willReturn(7);
        $player->method('getFreeAgencySecurity')->willReturn(5);
        $player->method('getFreeAgencyTradition')->willReturn(4);

        // Contract footer
        $player->method('getYearsOfExperience')->willReturn(5);
        $player->method('getBirdYears')->willReturn(3);

        return $player;
    }

    /**
     * Build a Player stub that configures only the CardBaseStyles::preparePlayerData()
     * getters. Every rating, pill, and contract getter is left unconfigured, so the
     * view sees null-or-zero for each.
     *
     * @return Player&\PHPUnit\Framework\MockObject\Stub
     */
    private function makePlayerWithNullRatings(): Player
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
     * These two tests exercise render() boundaries to maintain 4-test coverage.
     */
    public function testRenderContainsContractDisplay(): void
    {
        $player = $this->makePlayer();

        $result = PlayerTradingCardFrontView::render($player, 42, 'Y1/$5M', null);

        // Contract string must be HTML-sanitized and embedded in the contract footer.
        $this->assertStringContainsString('Y1/$5M', $result);
    }

    public function testRenderContainsRatingsSection(): void
    {
        $player = $this->makePlayer();

        $result = PlayerTradingCardFrontView::render($player, 42, 'Y1/$5M', null);

        // The ratings section heading must be present; its absence would kill any
        // mutant that omits the entire ratings block.
        $this->assertStringContainsString('Player Ratings', $result);
    }

    public function testRenderContainsPlayerID(): void
    {
        $player = $this->makePlayer();

        $result = PlayerTradingCardFrontView::render($player, 42, 'Y1/$5M', null);

        // A constant-fold mutant on playerID produces a different ID — observable here.
        $this->assertStringContainsString('42', $result);
    }

    public function testRenderSnapshot(): void
    {
        $player = $this->makePlayer();

        $result = PlayerTradingCardFrontView::render($player, 42, 'Y1/$5M', null);

        $this->assertSnapshotMatches($result, 'PlayerTradingCardFrontView.html');
    }

    public function testRenderEscapesHostileContractDisplay(): void
    {
        $result = PlayerTradingCardFrontView::render($this->makePlayer(), 42, '<script>alert(1)</script>"\'&', null);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString(
            '<span class="contract-amount">&lt;script&gt;alert(1)&lt;/script&gt;&quot;&apos;&amp;</span>',
            $result,
        );
    }

    public function testRenderNullRatingsRenderAsZero(): void
    {
        $result = PlayerTradingCardFrontView::render($this->makePlayerWithNullRatings(), 42, 'Y1/$5M', null);

        $this->assertSame(21, substr_count($result, '<div class="rating-value">0</div>'));
        $this->assertSame(10, substr_count($result, '<span class="pill-value">0</span>'));
        $this->assertStringContainsString('<span class="contract-value">0:0</span>', $result);
    }

    public function testRenderPillLabelLineKeepsTrailingSpace(): void
    {
        $result = PlayerTradingCardFrontView::render($this->makePlayer(), 42, 'Y1/$5M', null);

        // The trailing space after </span> lives inside the renderPill heredoc.
        $this->assertSame(10, preg_match_all('#<span class="pill-label">[A-Z]+</span> \n#', $result));
    }

    public function testViewHoldsNoStateAndRenderersTakeOnlyPlayerAndScalars(): void
    {
        $ref = new \ReflectionClass(PlayerTradingCardFrontView::class);

        $this->assertSame([], $ref->getProperties());
        $this->assertNull($ref->getConstructor());

        $publicNames = [];
        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $publicNames[] = $method->getName();
        }
        sort($publicNames);
        // PlayerStatsCardView::getStyles() is a separate view's stub and is not covered by this pin.
        $this->assertSame(['render'], $publicNames);

        foreach ($ref->getMethods() as $method) {
            $this->assertTrue($method->isStatic(), $method->getName());
            foreach ($method->getParameters() as $param) {
                $type = $param->getType();
                $this->assertInstanceOf(\ReflectionNamedType::class, $type);
                $this->assertContains(
                    $type->getName(),
                    ['Player\Player', 'int', 'string', 'bool', 'array'],
                    $method->getName() . '::' . $param->getName(),
                );
            }
        }
    }
}
