<?php

declare(strict_types=1);

namespace Tests\Navigation\Views;

use Navigation\NavigationConfig;
use Navigation\Views\LoginFormView;
use Navigation\Views\MobileNavView;
use Navigation\Views\TeamsDropdownView;
use PHPUnit\Framework\TestCase;

class MobileNavViewTest extends TestCase
{
    private function createView(
        bool $isLoggedIn = true,
        ?string $username = 'TestUser',
        ?int $teamId = 1,
    ): MobileNavView {
        $config = new NavigationConfig(
            isLoggedIn: $isLoggedIn,
            username: $username,
            currentLeague: 'ibl',
            teamId: $teamId,
        );

        return new MobileNavView($config, new LoginFormView(), new TeamsDropdownView());
    }

    public function testAccordionButtonRendered(): void
    {
        $view = $this->createView();
        $menuData = [
            'icon' => '<svg class="w-6 h-6"></svg>',
            'links' => [
                ['label' => 'Standings', 'url' => 'modules.php?name=Standings'],
            ],
        ];
        $html = $view->render(
            ['Season' => $menuData],
            null,
            [['label' => 'Logout', 'url' => 'modules.php?name=YourAccount&op=logout']],
        );

        $this->assertStringContainsString('mobile-dropdown-btn', $html);
        $this->assertStringContainsString('Season', $html);
    }

    public function testLinksInHiddenPanel(): void
    {
        $view = $this->createView();
        $menuData = [
            'icon' => '',
            'links' => [
                ['label' => 'Standings', 'url' => 'modules.php?name=Standings'],
                ['label' => 'Schedule', 'url' => 'modules.php?name=Schedule'],
            ],
        ];
        $html = $view->render(
            ['Season' => $menuData],
            null,
            [['label' => 'Logout', 'url' => 'modules.php?name=YourAccount&op=logout']],
        );

        $this->assertStringContainsString('mobile-dropdown-link', $html);
        $this->assertStringContainsString('Standings', $html);
        $this->assertStringContainsString('Schedule', $html);
    }

    public function testUserGreetingPresentWhenLoggedIn(): void
    {
        $view = $this->createView(isLoggedIn: true, username: 'JohnDoe');
        $html = $view->render(
            [],
            null,
            [['label' => 'Logout', 'url' => 'modules.php?name=YourAccount&op=logout']],
        );

        $this->assertStringContainsString('Welcome back', $html);
        $this->assertStringContainsString('JohnDoe', $html);
    }

    public function testUserGreetingAbsentWhenLoggedOut(): void
    {
        $view = $this->createView(isLoggedIn: false, username: null, teamId: null);
        $html = $view->render(
            [],
            null,
            [['label' => 'Sign Up', 'url' => 'modules.php?name=YourAccount&op=new_user']],
        );

        $this->assertStringNotContainsString('Welcome back', $html);
    }

    // --- Account Merge Tests ---

    /**
     * @return array{links: list<array{label: string, url: string}>, icon: string}
     */
    private function sampleMyTeamMenu(): array
    {
        return [
            'icon' => '<img src="/ibl5/images/logo/new1.png" alt="Team Logo">',
            'links' => [
                ['label' => 'Team Page', 'url' => 'modules.php?name=Team&op=team&teamID=1'],
            ],
        ];
    }

    public function testLogoutFooterAppearsInMyTeamAccordion(): void
    {
        $view = $this->createView(isLoggedIn: true, username: 'A-Jay');
        $html = $view->render(
            [],
            $this->sampleMyTeamMenu(),
            [['label' => 'Logout', 'url' => 'modules.php?name=YourAccount&op=logout', 'noBoost' => true]],
        );

        $this->assertStringContainsString('A-Jay', $html);
        $this->assertStringContainsString('modules.php?name=YourAccount&amp;op=logout', $html);
    }

    public function testAccountAccordionSuppressedWhenMyTeamMenuPresent(): void
    {
        $view = $this->createView(isLoggedIn: true, username: 'A-Jay');
        $html = $view->render(
            [],
            $this->sampleMyTeamMenu(),
            [['label' => 'Logout', 'url' => 'modules.php?name=YourAccount&op=logout', 'noBoost' => true]],
        );

        // "Account" (as an accordion button label) should not appear —
        // logout lives inside the My Team accordion footer.
        $this->assertStringNotContainsString('>Account<', $html);
    }

    public function testAccountAccordionRenderedForGuest(): void
    {
        $view = $this->createView(isLoggedIn: false, username: null, teamId: null);
        $html = $view->render(
            [],
            null,
            [
                ['label' => 'Sign Up', 'url' => 'modules.php?name=YourAccount&op=new_user'],
                ['label' => 'Forgot Password', 'url' => 'modules.php?name=YourAccount&op=pass_lost'],
            ],
        );

        $this->assertStringContainsString('Login', $html);
        $this->assertStringContainsString('Sign Up', $html);
        // Guests never see the logout footer
        $this->assertStringNotContainsString('modules.php?name=YourAccount&amp;op=logout', $html);
    }
}
