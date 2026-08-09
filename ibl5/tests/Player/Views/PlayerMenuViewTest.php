<?php

declare(strict_types=1);

namespace Tests\Player\Views;

use PHPUnit\Framework\TestCase;
use Player\PlayerPageType;
use Player\Views\PlayerMenuView;

/** @covers \Player\Views\PlayerMenuView */
class PlayerMenuViewTest extends TestCase
{
    use SnapshotTestTrait;

    public function testNullCurrentPageTypeDefaultsToOverviewActive(): void
    {
        $html = PlayerMenuView::render(10, null);

        // Overview pill must carry the active class
        $this->assertStringContainsString('plr-nav__pill--active">Overview', $html);
        // No other pill may carry the active class
        $this->assertSame(1, substr_count($html, 'plr-nav__pill--active'));
    }

    public function testExplicitOverviewPageTypeIsActive(): void
    {
        // PlayerPageType::OVERVIEW === null, so this call is identical to render(10, null).
        // Both code paths converge: the first disjunct ($currentPageType === $pageType)
        // evaluates null === null = true for the Overview entry, making the second disjunct
        // unreachable. This test independently covers the arm where $currentPageType is
        // not the default-omitted parameter but is explicitly supplied.
        $html = PlayerMenuView::render(10, PlayerPageType::OVERVIEW);

        $this->assertStringContainsString('plr-nav__pill--active">Overview', $html);
        $this->assertSame(1, substr_count($html, 'plr-nav__pill--active'));
    }

    public function testNonOverviewPageTypeIsActive(): void
    {
        $html = PlayerMenuView::render(10, PlayerPageType::AWARDS_AND_NEWS);

        // Overview must NOT be marked active
        $this->assertStringNotContainsString('plr-nav__pill--active">Overview', $html);
        // Some other pill must be active
        $this->assertStringContainsString('plr-nav__pill--active', $html);
    }

    public function testNullColorSchemeUsesDefaultPrimaryColor(): void
    {
        $html = PlayerMenuView::render(10, null, null);

        $this->assertStringContainsString('#f97316', $html);
    }

    public function testExplicitColorSchemeOverridesDefault(): void
    {
        $colorScheme = [
            'primary'        => '#aabbcc',
            'secondary'      => '#112233',
            'gradient_start' => '#aabbcc',
            'gradient_mid'   => '#aaaacc',
            'gradient_end'   => '#aabbdd',
            'border'         => '#aabbcc',
            'border_rgb'     => '170, 187, 204',
            'accent'         => '#ffffff',
            'text'           => '#ffffff',
            'text_muted'     => '#dddddd',
        ];

        $html = PlayerMenuView::render(10, null, $colorScheme);

        $this->assertStringContainsString('#aabbcc', $html);
        $this->assertStringNotContainsString('#f97316', $html);
    }

    public function testRenderSnapshot(): void
    {
        $html = PlayerMenuView::render(10, null, null);

        $this->assertSnapshotMatches($html, 'PlayerMenuView-default.html');
    }
}
