<?php

declare(strict_types=1);

namespace Tests\Contracts;

use Contracts\Contracts\ContractsViewInterface;
use Contracts\ContractsView;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ContractsViewTest - Tests for the Contracts tab nav wrapper
 *
 * @covers \Contracts\ContractsView
 */
class ContractsViewTest extends TestCase
{
    private ContractsViewInterface $view;

    protected function setUp(): void
    {
        $this->view = new ContractsView();
    }

    public function testRenderMarksTeamsTabActive(): void
    {
        $result = $this->view->render('teams', '');

        $this->assertSame(1, substr_count($result, 'ibl-tab--active'));
        $this->assertSame(1, substr_count($result, 'aria-current="page"'));
        // Plain navigation links, not ARIA tabs: a tablist role would require role="tab" children (axe aria-required-children)
        $this->assertStringNotContainsString('role="tablist"', $result);
        $this->assertStringContainsString(
            '<a class="ibl-tab ibl-tab--active" href="modules.php?name=Contracts&amp;tab=teams" data-tab="teams" aria-current="page">Teams</a>',
            $result
        );
        $this->assertStringContainsString(
            '<a class="ibl-tab" href="modules.php?name=Contracts&amp;tab=players" data-tab="players">Players</a>',
            $result
        );
    }

    public function testRenderMarksPlayersTabActive(): void
    {
        $result = $this->view->render('players', '');

        $this->assertSame(1, substr_count($result, 'ibl-tab--active'));
        $this->assertStringContainsString(
            '<a class="ibl-tab ibl-tab--active" href="modules.php?name=Contracts&amp;tab=players" data-tab="players" aria-current="page">Players</a>',
            $result
        );
        $this->assertStringContainsString(
            '<a class="ibl-tab" href="modules.php?name=Contracts&amp;tab=teams" data-tab="teams">Teams</a>',
            $result
        );
    }

    public function testResolveTabAcceptsWhitelistedValues(): void
    {
        $this->assertSame('teams', ContractsView::resolveTab('teams'));
        $this->assertSame('players', ContractsView::resolveTab('players'));
    }

    #[DataProvider('unknownTabProvider')]
    public function testResolveTabFallsBackToTeamsForUnknownValue(mixed $raw): void
    {
        $this->assertSame('teams', ContractsView::resolveTab($raw));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unknownTabProvider(): array
    {
        return [
            'bogus' => ['bogus'],
            'empty string' => [''],
            'uppercase' => ['TEAMS'],
            'null' => [null],
            'array' => [['teams']],
            'markup' => ['players<script>'],
        ];
    }

    public function testRenderEscapesTabHrefAmpersand(): void
    {
        $result = $this->view->render('teams', '');

        $this->assertStringContainsString('&amp;tab=teams', $result);
        $this->assertStringContainsString('&amp;tab=players', $result);
        $this->assertStringNotContainsString('&tab=', $result);
    }

    public function testRenderIncludesTabContentVerbatim(): void
    {
        $sentinel = '<table id="sentinel"><tr><td>x</td></tr></table>';

        $result = $this->view->render('players', $sentinel);

        $this->assertStringContainsString(
            '<div class="ibl-tab-panel" data-tab="players">' . $sentinel . '</div>',
            $result
        );
    }

    public function testRenderWithUnknownTabStillMarksExactlyOneActiveTab(): void
    {
        $result = $this->view->render('bogus', '');

        $this->assertSame(1, substr_count($result, 'ibl-tab--active'));
        $this->assertStringContainsString('data-tab="teams"', $result);
        $this->assertStringNotContainsString('bogus', $result);
    }
}
