<?php

declare(strict_types=1);

namespace Navigation;

use Navigation\Contracts\NavigationMenuBuilderInterface;
use Navigation\Views\DesktopNavView;
use Navigation\Views\LoginFormView;
use Navigation\Views\MobileNavView;
use Navigation\Views\TeamsDropdownView;

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
    private NavigationMenuBuilderInterface $menuBuilder;
    private DesktopNavView $desktopNavView;
    private MobileNavView $mobileNavView;

    public function __construct(NavigationConfig $config, ?NavigationMenuBuilderInterface $menuBuilder = null)
    {
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

        ob_start();
        ?>
        <!-- Navigation Bar -->
        <nav class="fixed top-0 left-0 right-0 z-50 nav-grain">
            <!-- Background - solid opaque navy matching menus -->
            <div class="absolute inset-0 nav-bar-bg"></div>
            <!-- Bottom accent line -->
            <div class="absolute bottom-0 left-0 right-0 h-[1px] bg-gradient-to-r from-transparent via-accent-500/50 to-transparent"></div>

            <?= $this->desktopNavView->renderDevSwitch() ?>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6">
                <div class="flex items-center justify-between">
                    <!-- Logo -->
                    <a href="index.php" class="flex items-center gap-3 py-2 group">
                        <div class="relative">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-accent-500 to-orange-600 flex items-center justify-center shadow-lg shadow-accent-500/25 group-hover:shadow-accent-500/40 transition-shadow">
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

                    <?= $this->desktopNavView->render($menus, $myTeamMenu, $accountMenu) ?>

                    <!-- Mobile controls -->
                    <div class="lg:hidden flex items-center gap-1">
                        <button id="desktop-view-toggle" class="relative w-10 h-10 flex items-center justify-center text-white hover:bg-white/10 rounded-lg transition-colors" aria-label="Switch to desktop view" title="Switch to desktop view">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25A2.25 2.25 0 015.25 3h13.5A2.25 2.25 0 0121 5.25z"/></svg>
                        </button>
                        <button id="nav-hamburger" class="relative w-10 h-10 flex items-center justify-center text-white hover:bg-white/10 rounded-lg transition-colors" aria-label="Toggle menu" aria-expanded="false">
                            <div class="w-5 h-4 flex flex-col justify-between">
                                <span class="block h-0.5 w-full bg-current rounded-full transition-transform origin-center" id="hamburger-top"></span>
                                <span class="block h-0.5 w-full bg-current rounded-full transition-opacity" id="hamburger-middle"></span>
                                <span class="block h-0.5 w-full bg-current rounded-full transition-transform origin-center" id="hamburger-bottom"></span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <?= $this->mobileNavView->render($menus, $myTeamMenu, $accountMenu) ?>
        <?php
        return (string) ob_get_clean();
    }
}
