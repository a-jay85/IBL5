<?php

declare(strict_types=1);

namespace Tests\Navigation\Views;

use Navigation\NavigationConfig;
use Navigation\Views\DesktopNavView;
use Navigation\Views\LoginFormView;
use Navigation\Views\TeamsDropdownView;
use PHPUnit\Framework\TestCase;

class DesktopNavViewTest extends TestCase
{
    private function createView(
        bool $isLoggedIn = true,
        ?string $username = 'TestUser',
        string $currentLeague = 'ibl',
        ?string $serverName = null,
    ): DesktopNavView {
        $config = new NavigationConfig(
            isLoggedIn: $isLoggedIn,
            username: $username,
            currentLeague: $currentLeague,
            serverName: $serverName,
            requestUri: '/ibl5/index.php',
        );

        return new DesktopNavView($config, new LoginFormView(), new TeamsDropdownView());
    }

    /**
     * @return array{links: list<array{label: string, url: string}>, icon: string}
     */
    private function sampleMenuData(): array
    {
        return [
            'icon' => '<svg class="w-6 h-6"></svg>',
            'links' => [
                ['label' => 'Standings', 'url' => 'modules.php?name=Standings'],
                ['label' => 'Schedule', 'url' => 'modules.php?name=Schedule'],
            ],
        ];
    }

    public function testRenderDropdownWithTitleAndLinks(): void
    {
        $view = $this->createView();
        $html = $view->render(
            ['Season' => $this->sampleMenuData()],
            null,
            [['label' => 'Logout', 'url' => 'modules.php?name=YourAccount&op=logout']],
        );

        $this->assertStringContainsString('Season', $html);
        $this->assertStringContainsString('Standings', $html);
        $this->assertStringContainsString('Schedule', $html);
    }

    public function testBadgeRendering(): void
    {
        $view = $this->createView();
        $menuData = [
            'icon' => '',
            'links' => [
                ['label' => 'Draft', 'url' => 'modules.php?name=Draft', 'badge' => 'LIVE'],
            ],
        ];
        $html = $view->render(
            ['My Team' => $menuData],
            null,
            [['label' => 'Logout', 'url' => 'modules.php?name=YourAccount&op=logout']],
        );

        $this->assertStringContainsString('LIVE', $html);
        $this->assertStringContainsString('bg-accent-500', $html);
    }

    public function testExternalLinkIcon(): void
    {
        $view = $this->createView();
        $menuData = [
            'icon' => '',
            'links' => [
                ['label' => 'Discord', 'url' => 'https://discord.com', 'external' => true],
            ],
        ];
        $html = $view->render(
            ['Community' => $menuData],
            null,
            [['label' => 'Logout', 'url' => 'modules.php?name=YourAccount&op=logout']],
        );

        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('noopener noreferrer', $html);
    }

    public function testRawHtmlLinks(): void
    {
        $view = $this->createView();
        $menuData = [
            'icon' => '',
            'links' => [
                ['rawHtml' => 'Waivers: <a href="modules.php?name=Waivers">Add</a>'],
            ],
        ];
        $html = $view->render(
            ['My Team' => $menuData],
            null,
            [['label' => 'Logout', 'url' => 'modules.php?name=YourAccount&op=logout']],
        );

        $this->assertStringContainsString('Waivers:', $html);
        $this->assertStringContainsString('span class="nav-dropdown-item"', $html);
    }

    public function testDevSwitchHiddenForNonAdmin(): void
    {
        $view = $this->createView(username: 'RegularUser', serverName: 'localhost');
        $html = $view->renderDevSwitch();

        $this->assertSame('', $html);
    }
}
