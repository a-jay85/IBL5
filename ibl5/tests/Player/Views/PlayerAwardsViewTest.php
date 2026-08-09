<?php

declare(strict_types=1);

namespace Tests\Player\Views;

use PHPUnit\Framework\TestCase;
use Player\Contracts\PlayerRepositoryInterface;
use Player\PlayerRepository;
use Player\Views\PlayerAwardsView;

/**
 * @covers \Player\Views\PlayerAwardsView
 *
 * @phpstan-import-type AwardRow from PlayerRepositoryInterface
 */
class PlayerAwardsViewTest extends TestCase
{
    use SnapshotTestTrait;

    /**
     * @param list<AwardRow> $awards
     */
    private function makeView(
        int $allStar, int $tpc, int $dunk, int $rsc, array $awards
    ): PlayerAwardsView {
        $repo = self::createStub(PlayerRepository::class);
        $repo->method('getAllStarGameCount')->willReturn($allStar);
        $repo->method('getThreePointContestCount')->willReturn($tpc);
        $repo->method('getDunkContestCount')->willReturn($dunk);
        $repo->method('getRookieSophChallengeCount')->willReturn($rsc);
        $repo->method('getAwards')->willReturn($awards);
        return new PlayerAwardsView($repo);
    }

    public function testRenderAllStarActivityWithNonZeroCountsSnapshot(): void
    {
        $view = $this->makeView(2, 1, 1, 1, []);

        $html = $view->renderAllStarActivity('Test Player');

        $this->assertSnapshotMatches($html, 'PlayerAwardsView-allstar.html');
    }

    public function testRenderAllStarActivityWithZeroCountsProducesDifferentOutput(): void
    {
        $nonZeroHtml = $this->makeView(2, 1, 1, 1, [])->renderAllStarActivity('Test Player');
        $zeroHtml = $this->makeView(0, 0, 0, 0, [])->renderAllStarActivity('Test Player');

        // The all-zero render must differ from the non-zero render, killing any
        // mutant that treats zero counts identically to non-zero counts.
        $this->assertNotSame($nonZeroHtml, $zeroHtml);
        $this->assertStringNotContainsString('>2<', $zeroHtml);
    }

    public function testRenderAwardsListSnapshot(): void
    {
        $view = $this->makeView(0, 0, 0, 0, PlayerViewFixtures::awardRows());

        $html = $view->renderAwardsList('Test Player');

        $this->assertSnapshotMatches($html, 'PlayerAwardsView-awards.html');
    }

    public function testRenderAwardsListWithEmptyAwardsOmitsAwardRowMarkup(): void
    {
        $view = $this->makeView(0, 0, 0, 0, []);

        $html = $view->renderAwardsList('Test Player');

        // Award rows use the year-cell class; empty awards must not render any award row.
        $this->assertStringNotContainsString('year-cell', $html);
    }
}
