<?php

declare(strict_types=1);

namespace Tests\AllStarAppearances;

use AllStarAppearances\AllStarAppearancesView;
use AllStarAppearances\Contracts\AllStarAppearancesViewInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AllStarAppearances\AllStarAppearancesView
 */
class AllStarAppearancesViewTest extends TestCase
{
    private AllStarAppearancesView $view;

    protected function setUp(): void
    {
        $this->view = new AllStarAppearancesView();
    }

    public function testImplementsViewInterface(): void
    {
        $this->assertInstanceOf(AllStarAppearancesViewInterface::class, $this->view);
    }

    public function testRenderOutputsTable(): void
    {
        $appearances = [self::createAppearance()];

        $html = $this->view->render($appearances);

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('</table>', $html);
    }

    public function testRenderShowsTitle(): void
    {
        $html = $this->view->render([]);

        $this->assertStringContainsString('All-Star Appearances', $html);
    }

    public function testRenderShowsPlayerName(): void
    {
        $appearances = [self::createAppearance(['name' => 'LeBron James'])];

        $html = $this->view->render($appearances);

        $this->assertStringContainsString('LeBron James', $html);
    }

    public function testRenderShowsAppearanceCount(): void
    {
        $appearances = [self::createAppearance(['appearances' => 12])];

        $html = $this->view->render($appearances);

        $this->assertStringContainsString('12', $html);
    }

    public function testRenderShowsTableHeaders(): void
    {
        $html = $this->view->render([self::createAppearance()]);

        $this->assertStringContainsString('<th>Player</th>', $html);
        $this->assertStringContainsString('<th>Appearances</th>', $html);
    }

    public function testRenderIncludesSortableClass(): void
    {
        $html = $this->view->render([self::createAppearance()]);

        $this->assertStringContainsString('sortable', $html);
    }

    public function testRenderLinksToPlayerProfile(): void
    {
        $appearances = [self::createAppearance(['pid' => 42])];

        $html = $this->view->render($appearances);

        $this->assertStringContainsString('pid=42', $html);
    }

    public function testRenderHandlesEmptyArray(): void
    {
        $html = $this->view->render([]);

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('</table>', $html);
    }

    public function testRenderHandlesMultipleRows(): void
    {
        $appearances = [
            self::createAppearance(['name' => 'Player One', 'appearances' => 5]),
            self::createAppearance(['name' => 'Player Two', 'appearances' => 3]),
        ];

        $html = $this->view->render($appearances);

        $this->assertStringContainsString('Player One', $html);
        $this->assertStringContainsString('Player Two', $html);
    }

    /**
     * @return array{name: string, pid: int, appearances: int}
     */
    private static function createAppearance(array $overrides = []): array
    {
        /** @var array{name: string, pid: int, appearances: int} */
        return array_merge([
            'name' => 'Test Player',
            'pid' => 1,
            'appearances' => 5,
        ], $overrides);
    }
}
