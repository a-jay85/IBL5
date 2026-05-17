<?php

declare(strict_types=1);

namespace Tests\Navigation\Views;

use Navigation\Contracts\NavigationMenuBuilderInterface;
use Navigation\NavigationConfig;
use Navigation\NavigationView;
use Navigation\Views\DesktopNavView;
use Navigation\Views\LoginFormView;
use Navigation\Views\MobileNavView;
use Navigation\Views\TeamsDropdownView;
use PHPUnit\Framework\TestCase;

class NavigationViewsXssTest extends TestCase
{
    private const XSS_PAYLOAD = '<script>alert(1)</script>';
    private const ESCAPED_PAYLOAD = '&lt;script&gt;alert(1)&lt;/script&gt;';

    public function testLoginFormViewEscapesRequestUriXss(): void
    {
        $view = new LoginFormView();
        $html = $view->render('desktop', '/ibl5/index.php?x=' . self::XSS_PAYLOAD);

        $this->assertStringNotContainsString(self::XSS_PAYLOAD, $html);
    }

    public function testDesktopNavViewEscapesUsernameXss(): void
    {
        $config = new NavigationConfig(
            isLoggedIn: true,
            username: self::XSS_PAYLOAD,
            currentLeague: 'ibl',
        );
        $view = new DesktopNavView($config, new LoginFormView(), new TeamsDropdownView());

        $html = $view->render(
            [],
            null,
            [['label' => 'Logout', 'url' => 'modules.php?name=YourAccount&op=logout']],
        );

        $this->assertStringNotContainsString(self::XSS_PAYLOAD, $html);
        $this->assertStringContainsString(self::ESCAPED_PAYLOAD, $html);
    }

    public function testMobileNavViewEscapesUsernameXss(): void
    {
        $config = new NavigationConfig(
            isLoggedIn: true,
            username: self::XSS_PAYLOAD,
            currentLeague: 'ibl',
        );
        $view = new MobileNavView($config, new LoginFormView(), new TeamsDropdownView());

        $html = $view->render(
            [],
            null,
            [['label' => 'Logout', 'url' => 'modules.php?name=YourAccount&op=logout']],
        );

        $this->assertStringNotContainsString(self::XSS_PAYLOAD, $html);
        $this->assertStringContainsString(self::ESCAPED_PAYLOAD, $html);
    }

    public function testTeamsDropdownViewEscapesTeamNameXss(): void
    {
        $teamsData = [
            'Western' => [
                'Pacific' => [
                    ['teamid' => 1, 'team_name' => self::XSS_PAYLOAD, 'team_city' => 'Test'],
                ],
            ],
        ];
        $view = new TeamsDropdownView();
        $html = $view->renderDesktop($teamsData);

        $this->assertStringNotContainsString(self::XSS_PAYLOAD, $html);
        $this->assertStringContainsString(self::ESCAPED_PAYLOAD, $html);
    }

    public function testNavigationViewEscapesXssInMenuLabels(): void
    {
        $config = new NavigationConfig(
            isLoggedIn: true,
            username: self::XSS_PAYLOAD,
            currentLeague: 'ibl',
        );

        $menuBuilder = $this->createStub(NavigationMenuBuilderInterface::class);
        $menuBuilder->method('getMenuStructure')->willReturn([]);
        $menuBuilder->method('getMyTeamMenu')->willReturn(null);
        $menuBuilder->method('getAccountMenu')->willReturn([
            ['label' => 'Logout', 'url' => 'modules.php?name=YourAccount&op=logout'],
        ]);

        $view = new NavigationView($config, $menuBuilder);
        $html = $view->render();

        $this->assertStringNotContainsString(self::XSS_PAYLOAD, $html);
        $this->assertStringContainsString(self::ESCAPED_PAYLOAD, $html);
    }
}
