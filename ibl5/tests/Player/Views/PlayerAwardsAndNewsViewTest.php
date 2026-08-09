<?php

declare(strict_types=1);

namespace Tests\Player\Views;

use PHPUnit\Framework\TestCase;
use Player\Contracts\PlayerRepositoryInterface;
use Player\PlayerRepository;
use Player\Views\PlayerAwardsAndNewsView;

/**
 * @covers \Player\Views\PlayerAwardsAndNewsView
 *
 * @phpstan-import-type AwardRow from PlayerRepositoryInterface
 * @phpstan-import-type PlayerNewsRow from PlayerRepositoryInterface
 */
class PlayerAwardsAndNewsViewTest extends TestCase
{
    use SnapshotTestTrait;

    /**
     * @param list<AwardRow> $awards
     * @param list<PlayerNewsRow> $news
     */
    private function makeView(array $awards, array $news): PlayerAwardsAndNewsView
    {
        $repo = self::createStub(PlayerRepository::class);
        $repo->method('getAwards')->willReturn($awards);
        $repo->method('getPlayerNews')->willReturn($news);
        return new PlayerAwardsAndNewsView($repo);
    }

    public function testRenderReturnsEmptyString(): void
    {
        $view = $this->makeView([], []);

        $this->assertSame('', $view->render());
    }

    public function testRenderAwardsAndNewsSnapshot(): void
    {
        $view = $this->makeView(
            PlayerViewFixtures::awardRows(),
            PlayerViewFixtures::playerNewsRows()
        );

        $html = $view->renderAwardsAndNews('Test Player');

        $this->assertSnapshotMatches($html, 'PlayerAwardsAndNewsView.html');
    }

    public function testRenderAwardsAndNewsWithEmptyAwardsOmitsAwardMarkup(): void
    {
        $view = $this->makeView([], PlayerViewFixtures::playerNewsRows());

        $html = $view->renderAwardsAndNews('Test Player');

        // Award rows contain award names not present in the news fixture titles.
        // 'All-IBL' and 'Sixth Man' appear only in award rows, never in news article text.
        $this->assertStringNotContainsString('All-IBL', $html);
        $this->assertStringNotContainsString('Sixth Man', $html);
    }

    public function testRenderAwardsAndNewsWithEmptyNewsOmitsNewsMarkup(): void
    {
        $view = $this->makeView(PlayerViewFixtures::awardRows(), []);

        $html = $view->renderAwardsAndNews('Test Player');

        // News article links contain name=News; empty news must not render them.
        $this->assertStringNotContainsString('name=News', $html);
    }
}
