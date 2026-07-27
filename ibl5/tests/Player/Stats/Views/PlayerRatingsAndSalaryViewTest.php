<?php

declare(strict_types=1);

namespace Tests\Player\Stats\Views;

use PHPUnit\Framework\TestCase;
use Player\Stats\PlayerStatsRepository;
use Player\Stats\Views\PlayerRatingsAndSalaryView;

/** @covers \Player\Stats\Views\PlayerRatingsAndSalaryView */
class PlayerRatingsAndSalaryViewTest extends TestCase
{
    use SnapshotTestTrait;

    public function testRenderRatingsAndSalaryMatchesSnapshot(): void
    {
        $repository = self::createStub(PlayerStatsRepository::class);
        $repository->method('getHistoricalStats')
            ->willReturn(RegularSeasonViewFixtures::seasonRows());

        $view = new PlayerRatingsAndSalaryView($repository);
        $html = $view->renderRatingsAndSalary(42);

        $this->assertSnapshotMatches($html, 'PlayerRatingsAndSalaryView.html');
    }

    public function testOffensiveAndDefensiveTotalsAreSummedPerSeason(): void
    {
        $repository = self::createStub(PlayerStatsRepository::class);
        $repository->method('getHistoricalStats')
            ->willReturn(RegularSeasonViewFixtures::seasonRows());

        $view = new PlayerRatingsAndSalaryView($repository);
        $html = $view->renderRatingsAndSalary(42);

        // 71+58+49+66 = 244 and 73+54+61+45 = 233 (season 2029).
        $this->assertStringContainsString('<td>244</td>', $html);
        $this->assertStringContainsString('<td>233</td>', $html);
        // 68+74+56+63 = 261 and 81+47+58+52 = 238 (season 2031).
        $this->assertStringContainsString('<td>261</td>', $html);
        $this->assertStringContainsString('<td>238</td>', $html);
    }

    public function testCareerSalaryIsAccumulatedAndScaledToMillions(): void
    {
        $repository = self::createStub(PlayerStatsRepository::class);
        $repository->method('getHistoricalStats')
            ->willReturn(RegularSeasonViewFixtures::seasonRows());

        $view = new PlayerRatingsAndSalaryView($repository);
        $html = $view->renderRatingsAndSalary(42);

        // (3250 + 4300 + 5000) / 100 = 125.5
        $this->assertStringContainsString(
            'Total Career Salary Earned: 125.5 million dollars',
            $html
        );
    }

    public function testEmptyHistoryRendersZeroCareerSalary(): void
    {
        $repository = self::createStub(PlayerStatsRepository::class);
        $repository->method('getHistoricalStats')->willReturn([]);

        $view = new PlayerRatingsAndSalaryView($repository);
        $html = $view->renderRatingsAndSalary(42);

        $this->assertStringContainsString(
            'Total Career Salary Earned: 0 million dollars',
            $html
        );
        $this->assertStringNotContainsString('<td>244</td>', $html);
    }

    public function testRenderReturnsEmptyStringBecauseItNeedsContext(): void
    {
        $repository = self::createStub(PlayerStatsRepository::class);

        $view = new PlayerRatingsAndSalaryView($repository);

        $this->assertSame('', $view->render());
    }
}
