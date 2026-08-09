<?php

declare(strict_types=1);

namespace Tests\Player\Views;

use PHPUnit\Framework\TestCase;
use Player\Contracts\PlayerRepositoryInterface;
use Player\PlayerRepository;
use Player\Views\PlayerOneOnOneView;

/**
 * @covers \Player\Views\PlayerOneOnOneView
 *
 * @phpstan-import-type OneOnOneWinRow from PlayerRepositoryInterface
 * @phpstan-import-type OneOnOneLossRow from PlayerRepositoryInterface
 */
class PlayerOneOnOneViewTest extends TestCase
{
    use SnapshotTestTrait;

    /**
     * @param list<OneOnOneWinRow> $wins
     * @param list<OneOnOneLossRow> $losses
     */
    private function makeView(array $wins, array $losses): PlayerOneOnOneView
    {
        $repo = self::createStub(PlayerRepository::class);
        $repo->method('getOneOnOneWins')->willReturn($wins);
        $repo->method('getOneOnOneLosses')->willReturn($losses);
        return new PlayerOneOnOneView($repo);
    }

    public function testRenderReturnsEmptyString(): void
    {
        $view = $this->makeView([], []);

        $this->assertSame('', $view->render());
    }

    public function testRenderOneOnOneResultsSnapshot(): void
    {
        $view = $this->makeView(
            PlayerViewFixtures::oneOnOneWinRows(),
            PlayerViewFixtures::oneOnOneLossRows()
        );

        $html = $view->renderOneOnOneResults('Test Player');

        $this->assertSnapshotMatches($html, 'PlayerOneOnOneView.html');
    }

    public function testRenderOneOnOneResultsWithEmptyArraysOmitsResultRowMarkup(): void
    {
        $view = $this->makeView([], []);

        $html = $view->renderOneOnOneResults('Test Player');

        // Game result rows contain OneOnOneGame links; empty arrays must not render any.
        $this->assertStringNotContainsString('OneOnOneGame', $html);
    }
}
