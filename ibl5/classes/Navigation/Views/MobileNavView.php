<?php

declare(strict_types=1);

namespace Navigation\Views;

use Navigation\NavigationConfig;
use Utilities\HtmlSanitizer;

/**
 * Renders the mobile sliding panel navigation: user greeting, accordion
 * sections, teams list, and login form.
 *
 * @phpstan-import-type NavLink from \Navigation\NavigationConfig
 * @phpstan-import-type NavMenuData from \Navigation\NavigationConfig
 */
class MobileNavView
{
    private NavigationConfig $config;
    private LoginFormView $loginFormView;
    private TeamsDropdownView $teamsDropdownView;

    public function __construct(
        NavigationConfig $config,
        LoginFormView $loginFormView,
        TeamsDropdownView $teamsDropdownView,
    ) {
        $this->config = $config;
        $this->loginFormView = $loginFormView;
        $this->teamsDropdownView = $teamsDropdownView;
    }

    /**
     * Render the complete mobile navigation panel.
     *
     * @param array<string, NavMenuData> $menus
     * @param NavMenuData|null $myTeamMenu
     * @param list<array{label: string, url: string}> $accountMenu
     */
    public function render(array $menus, ?array $myTeamMenu, array $accountMenu): string
    {
        $index = 2;

        ob_start();
        ?>
        <!-- Mobile menu overlay -->
        <div id="nav-overlay" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-40 lg:hidden transition-opacity duration-300"></div>

        <!-- Mobile menu -->
        <nav id="nav-mobile-menu" class="fixed top-[71px] right-0 bottom-0 w-[300px] max-w-[85vw] z-50 transform translate-x-full transition-transform duration-300 ease-out lg:hidden">
            <!-- Background - solid opaque navy matching dropdown menus -->
            <div class="absolute inset-0 nav-bar-bg"></div>
            <!-- Left accent line -->
            <div class="absolute top-0 left-0 bottom-0 w-[1px] bg-gradient-to-b from-accent-500/50 via-accent-500/20 to-transparent"></div>

            <div class="relative h-full flex flex-col mobile-menu-scroll overflow-y-auto">
                <!-- User greeting -->
                <?php if ($this->config->isLoggedIn && $this->config->username !== null): ?>
                    <div class="mobile-section px-5 py-4 border-b border-white/5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-accent-500/20 flex items-center justify-center">
                                <svg class="w-5 h-5 text-accent-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wide">Welcome back</div>
                                <div class="text-white font-semibold"><?= HtmlSanitizer::e($this->config->username) ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- My Team section first for thumb reachability (if user has a team) -->
                <?php if ($myTeamMenu !== null): ?>
                    <?= $this->renderDropdown('My Team', $myTeamMenu, $index++) ?>
                <?php endif; ?>

                <!-- Teams mega-menu -->
                <?php if ($this->config->teamsData !== null): ?>
                    <?= $this->teamsDropdownView->renderMobile($this->config->teamsData, $this->config->teamId) ?>
                <?php endif; ?>

                <!-- Menu sections -->
                <?php foreach ($menus as $title => $menu): ?>
                    <?= $this->renderDropdown(
                        $title,
                        $menu,
                        $index++,
                        false,
                        $title === 'Season'
                    ) ?>
                <?php endforeach; ?>

                <!-- Account section -->
                <?= $this->renderDropdown(
                    $this->config->isLoggedIn ? 'Account' : 'Login',
                    [
                        'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
                        'links' => $accountMenu,
                    ],
                    $index++,
                    !$this->config->isLoggedIn
                ) ?>
            </div>
        </nav>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render a mobile accordion dropdown section.
     *
     * @param NavMenuData $data
     */
    private function renderDropdown(string $title, array $data, int $index, bool $includeLoginForm = false, bool $includeLeagueSwitcher = false): string
    {
        $links = $data['links'];
        $icon = $data['icon'] ?? '';

        ob_start();
        ?>
        <div class="mobile-section">
            <button class="mobile-dropdown-btn w-full flex items-center justify-between px-5 py-3.5 text-white hover:bg-white/5 transition-colors">
                <span class="flex items-center gap-3">
                    <?php if ($icon !== ''): ?>
                        <span class="text-accent-500"><?= $icon ?></span>
                    <?php endif; ?>
                    <span class="font-display text-lg font-semibold"><?= HtmlSanitizer::e($title) ?></span>
                </span>
                <svg class="dropdown-arrow w-4 h-4 text-gray-500 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div class="hidden bg-black/20">
                <?php if ($includeLoginForm): ?>
                    <?= $this->loginFormView->render('mobile', $this->config->requestUri) ?>
                    <div class="border-t border-white/10 mx-5 my-2"></div>
                <?php endif; ?>

                <?php foreach ($links as $link): ?>
                    <?php if (isset($link['rawHtml'])): ?>
                        <span class="mobile-dropdown-link"><?= $link['rawHtml'] ?></span>
                    <?php else: ?>
                        <?php
                        $label = HtmlSanitizer::e($link['label'] ?? '');
                        $url = HtmlSanitizer::e($link['url'] ?? '');
                        $external = $link['external'] ?? false;
                        $badge = $link['badge'] ?? null;
                        $target = $external ? ' target="_blank" rel="noopener noreferrer"' : '';
                        $externalIcon = $external ? ' <svg class="w-3 h-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>' : '';
                        $badgeHtml = $badge !== null
                            ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-base font-bold bg-accent-500 text-white ml-2">' . HtmlSanitizer::e($badge) . '</span>'
                            : '';
                        ?>
                        <a href="<?= $url ?>"<?= $target ?> class="mobile-dropdown-link flex items-center justify-between">
                            <span><?= $label . $badgeHtml ?></span>
                            <?= $externalIcon ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($includeLeagueSwitcher): ?>
                    <?= $this->renderLeagueSwitcher() ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the league switcher for inside a mobile dropdown menu.
     */
    private function renderLeagueSwitcher(): string
    {
        $iblSelected = $this->config->currentLeague === 'ibl' ? ' selected' : '';
        $olympicsSelected = $this->config->currentLeague === 'olympics' ? ' selected' : '';

        ob_start();
        ?>
        <div class="px-5 py-3 border-t border-white/10 mt-1">
            <label class="block text-base font-semibold tracking-widest uppercase text-gray-500 mb-2">League</label>
            <div class="relative">
                <select onchange="window.location.href=this.value" class="w-full appearance-none bg-white/10 text-white text-sm font-medium border border-white/20 rounded-xl px-3 py-2.5 pr-8 cursor-pointer hover:bg-white/15 hover:border-white/30 focus:outline-none focus:ring-2 focus:ring-accent-500/50 focus:border-accent-500 transition-all">
                    <option value="index.php?league=ibl"<?= $iblSelected ?> class="bg-navy-800 text-white">IBL</option>
                    <option value="index.php?league=olympics"<?= $olympicsSelected ?> class="bg-navy-800 text-white">Olympics</option>
                </select>
                <svg class="absolute right-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
