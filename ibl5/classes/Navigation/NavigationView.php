<?php

declare(strict_types=1);

namespace Navigation;

use Navigation\Contracts\NavigationMenuBuilderInterface;
use Navigation\Views\DesktopNavView;
use Navigation\Views\LoginFormView;
use Navigation\Views\MobileNavView;
use Navigation\Views\TeamsDropdownView;
use Security\HtmlSanitizer;

/**
 * Thin orchestrator that composes the full navigation bar from sub-views.
 * Desktop nav + mobile overlay + mobile sliding panel.
 *
 * @phpstan-import-type NavLink from NavigationConfig
 * @phpstan-import-type NavMenuData from NavigationConfig
 * @phpstan-import-type NavTeamsData from NavigationConfig
 */
class NavigationView
{
    private NavigationConfig $config;
    private NavigationMenuBuilderInterface $menuBuilder;
    private DesktopNavView $desktopNavView;
    private MobileNavView $mobileNavView;

    public function __construct(NavigationConfig $config, ?NavigationMenuBuilderInterface $menuBuilder = null)
    {
        $this->config = $config;
        $this->menuBuilder = $menuBuilder ?? new NavigationMenuBuilder($config);

        $loginFormView = new LoginFormView();
        $teamsDropdownView = new TeamsDropdownView();
        $this->desktopNavView = new DesktopNavView($config, $loginFormView, $teamsDropdownView);
        $this->mobileNavView = new MobileNavView($config, $loginFormView, $teamsDropdownView);
    }

    /**
     * Render the complete navigation bar (desktop + mobile).
     */
    public function render(): string
    {
        $menus = $this->menuBuilder->getMenuStructure();
        $myTeamMenu = $this->menuBuilder->getMyTeamMenu();
        $accountMenu = $this->menuBuilder->getAccountMenu();
        $showTeamLogoHamburger = $this->config->isLoggedIn && $this->config->teamId !== null;

        ob_start();
        ?>
        <!-- Navigation Bar -->
        <nav class="fixed top-0 left-0 right-0 z-50 nav-grain h-[72px]">
            <!-- Background - solid opaque navy matching menus -->
            <div class="absolute inset-0 nav-bar-bg"></div>
            <!-- Bottom cover: opaque strip at nav's bottom edge, stacked ABOVE
                 dropdown panels (which use z-50) so the dropdowns' top edges
                 appear tucked behind the nav bar instead of rendering on top. -->
            <div class="absolute left-0 right-0 bottom-0 h-2 nav-bar-bg z-[60] pointer-events-none"></div>
            <!-- Bottom accent line (above cover) -->
            <div class="absolute bottom-0 left-0 right-0 h-[1px] z-[61] bg-accent-500"></div>

            <?= HtmlSanitizer::trusted($this->desktopNavView->renderDevSwitch()) ?>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 h-full">
                <div class="flex items-center justify-between h-full">
                    <!-- Logo -->
                    <a href="index.php" class="flex items-center gap-3 py-2 group">
                        <div class="relative">
                            <div class="nav-logo-icon">
                                <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <path d="M12 2C12 12 12 12 12 22" stroke="currentColor" stroke-width="1.5"/>
                                    <path d="M2 12C12 12 12 12 22 12" stroke="currentColor" stroke-width="1.5"/>
                                    <path d="M4.5 4.5C8 8 8 16 4.5 19.5" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <path d="M19.5 4.5C16 8 16 16 19.5 19.5" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                </svg>
                            </div>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-display text-2xl tracking-wider text-white leading-none">IBL</span>
                            <span class="text-base tracking-[0.2em] text-accent-500 font-semibold uppercase">Sim League</span>
                        </div>
                    </a>

                    <?= HtmlSanitizer::trusted($this->desktopNavView->render($menus, $myTeamMenu, $accountMenu)) ?>

                    <!-- Desktop notification bell -->
                    <div class="hidden lg:flex items-center">
                        <?= HtmlSanitizer::trusted($this->renderNotificationBell()) ?>
                    </div>

                    <!-- Mobile controls -->
                    <div class="lg:hidden flex items-center gap-1">
                        <?= HtmlSanitizer::trusted($this->renderNotificationBell()) ?>
                        <button id="desktop-view-toggle" class="nav-icon-btn" aria-label="Switch to desktop view" title="Switch to desktop view">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25A2.25 2.25 0 015.25 3h13.5A2.25 2.25 0 0121 5.25z"/></svg>
                        </button>
                        <button id="nav-hamburger" class="nav-icon-btn" aria-label="Toggle menu" aria-expanded="false">
                            <?php if ($showTeamLogoHamburger): ?>
                                <img id="nav-hamburger-logo" src="/ibl5/images/logo/new<?= (int) $this->config->teamId ?>.png" alt="" class="nav-team-logo-hamburger">
                                <svg id="nav-hamburger-close" class="nav-hamburger-close-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            <?php else: ?>
                                <div class="w-5 h-4 flex flex-col justify-between">
                                    <span class="block h-0.5 w-full bg-current rounded-full transition-transform origin-center" id="hamburger-top"></span>
                                    <span class="block h-0.5 w-full bg-current rounded-full transition-opacity" id="hamburger-middle"></span>
                                    <span class="block h-0.5 w-full bg-current rounded-full transition-transform origin-center" id="hamburger-bottom"></span>
                                </div>
                            <?php endif; ?>
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <?= HtmlSanitizer::trusted($this->mobileNavView->render($menus, $myTeamMenu, $accountMenu)) ?>
        <script src="jslib/navigation.js"></script>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the nav notification bell for a logged-in team owner. Returns an
     * empty string for unauthenticated users or users without a team, so the
     * bell is hidden exactly when the Notifications module is inaccessible.
     */
    private function renderNotificationBell(): string
    {
        if (!$this->config->isLoggedIn || $this->config->teamId === null) {
            return '';
        }

        $badge = '';
        if ($this->config->unreadNotificationCount > 0) {
            $badge = '<span class="notification-bell__badge">'
                . (string) $this->config->unreadNotificationCount
                . '</span>';
        }

        return '<a href="modules.php?name=Notifications" class="notification-bell nav-icon-btn" aria-label="Notifications" title="Notifications">'
            . '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>'
            . '</svg>'
            . $badge
            . '</a>';
    }
}
