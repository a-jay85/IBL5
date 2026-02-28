<?php

declare(strict_types=1);

namespace Navigation\Views;

use Navigation\NavigationConfig;
use Utilities\HtmlSanitizer;

/**
 * Renders the desktop navigation bar: dropdown menus, teams mega-menu,
 * login/account dropdown, and dev switch.
 *
 * @phpstan-import-type NavLink from \Navigation\NavigationConfig
 * @phpstan-import-type NavMenuData from \Navigation\NavigationConfig
 */
class DesktopNavView
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
     * Render the complete desktop navigation section.
     *
     * @param array<string, NavMenuData> $menus
     * @param NavMenuData|null $myTeamMenu
     * @param list<array{label: string, url: string}> $accountMenu
     */
    public function render(array $menus, ?array $myTeamMenu, array $accountMenu): string
    {
        ob_start();
        ?>
                    <!-- Desktop Navigation (right-aligned) -->
                    <div class="hidden lg:flex items-center ml-auto">
                        <?php foreach ($menus as $title => $menu): ?>
                            <?= $this->renderDropdown(
                                $title,
                                $menu,
                                false,
                                $title === 'Season'
                            ) ?>
                        <?php endforeach; ?>

                        <!-- Teams + My Team wrapper (positioning context for Teams mega-menu) -->
                        <div class="relative flex items-center">
                            <?php if ($this->config->teamsData !== null): ?>
                                <?= $this->teamsDropdownView->renderDesktop($this->config->teamsData) ?>
                            <?php endif; ?>

                            <?php if ($myTeamMenu !== null): ?>
                                <?= $this->renderDropdown('My Team', $myTeamMenu) ?>
                            <?php endif; ?>
                        </div>

                        <!-- Divider -->
                        <div class="w-px h-6 bg-white/10 mx-2"></div>

                        <!-- Account dropdown (right-aligned to stay within viewport) -->
                        <?= $this->renderDropdown(
                            $this->config->isLoggedIn ? ($this->config->username ?? 'Account') : 'Login',
                            [
                                'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
                                'links' => $accountMenu,
                            ],
                            !$this->config->isLoggedIn,
                            false,
                            true
                        ) ?>

                        <!-- Mobile view toggle (visible only in forced desktop mode) -->
                        <button id="mobile-view-toggle" class="hidden w-10 h-10 flex items-center justify-center text-white hover:bg-white/10 rounded-lg transition-colors ml-1" aria-label="Switch to mobile view" title="Switch to mobile view">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/></svg>
                        </button>
                    </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the dev switch button (only for admin on localhost/production).
     */
    public function renderDevSwitch(): string
    {
        if ($this->config->username !== 'A-Jay' || $this->config->serverName === null || $this->config->requestUri === null) {
            return '';
        }

        if ($this->config->serverName !== 'localhost') {
            $url = 'http://localhost' . $this->config->requestUri;
            $title = 'Switch to localhost';
        } else {
            $url = 'https://www.iblhoops.net' . $this->config->requestUri;
            $title = 'Switch to production';
        }

        $safeUrl = HtmlSanitizer::e($url);
        $safeTitle = HtmlSanitizer::e($title);

        ob_start();
        ?>
            <a href="<?= $safeUrl ?>" class="absolute left-1.5 top-1/2 -translate-y-1/2 z-10 w-10 h-10 flex items-center justify-center rounded-full opacity-40 hover:opacity-100 transition-opacity duration-200 overflow-visible nav-dev-switch" title="<?= $safeTitle ?>">
                <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="12" cy="12" r="10"/>
                    <ellipse cx="12" cy="12" rx="4" ry="10"/>
                    <path d="M2 12h20"/>
                    <path d="M5 7h14"/>
                    <path d="M5 17h14"/>
                </svg>
            </a>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render a desktop dropdown menu.
     *
     * @param NavMenuData $data
     */
    private function renderDropdown(string $title, array $data, bool $includeLoginForm = false, bool $includeLeagueSwitcher = false, bool $alignRight = false): string
    {
        $links = $data['links'];
        $icon = $data['icon'] ?? '';

        $minWidth = $includeLoginForm ? 'min-w-[280px]' : 'min-w-[220px]';
        $alignment = $alignRight ? 'right-0' : 'left-0';

        ob_start();
        ?>
        <div class="relative group">
            <button class="flex items-center gap-2 px-3 py-2.5 text-lg font-semibold font-display text-gray-300 hover:text-white transition-colors duration-200">
                <?php if ($icon !== ''): ?>
                    <span class="text-accent-500 group-hover:text-accent-400 transition-colors"><?= $icon ?></span>
                <?php endif; ?>
                <span><?= HtmlSanitizer::e($title) ?></span>
                <svg class="w-3 h-3 opacity-50 group-hover:opacity-100 transition-all duration-200 group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div class="absolute <?= $alignment ?> top-full pt-1 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                <div class="<?= $minWidth ?> bg-navy-800/95 backdrop-blur-xl rounded-lg shadow-2xl shadow-black/30 border border-white/10 overflow-hidden">
                    <?php if ($includeLoginForm): ?>
                        <?= $this->loginFormView->render('desktop', $this->config->requestUri) ?>
                    <?php endif; ?>

                    <div class="py-1">
                        <?php foreach ($links as $link): ?>
                            <?= $this->renderDropdownLink($link) ?>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($includeLeagueSwitcher): ?>
                        <?= $this->renderLeagueSwitcher() ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render a single dropdown link item.
     *
     * @param NavLink $link
     */
    private function renderDropdownLink(array $link): string
    {
        if (isset($link['rawHtml'])) {
            return '<span class="nav-dropdown-item">'
                . $link['rawHtml']
                . '</span>';
        }

        $label = HtmlSanitizer::e($link['label'] ?? '');
        $url = HtmlSanitizer::e($link['url'] ?? '');
        $external = $link['external'] ?? false;
        $badge = $link['badge'] ?? null;

        $target = $external ? ' target="_blank" rel="noopener noreferrer"' : '';
        $externalIcon = $external ? ' <svg class="w-3 h-3 opacity-40 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>' : '';
        $badgeHtml = $badge !== null
            ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-base font-bold bg-accent-500 text-white ml-2 tracking-wide">' . HtmlSanitizer::e($badge) . '</span>'
            : '';

        return '<a href="' . $url . '"' . $target . ' class="nav-dropdown-item">'
            . '<span class="flex items-center justify-between">'
            . '<span>' . $label . $badgeHtml . '</span>'
            . $externalIcon
            . '</span>'
            . '</a>';
    }

    /**
     * Render the league switcher for inside a desktop dropdown menu.
     */
    private function renderLeagueSwitcher(): string
    {
        $iblSelected = $this->config->currentLeague === 'ibl' ? ' selected' : '';
        $olympicsSelected = $this->config->currentLeague === 'olympics' ? ' selected' : '';

        ob_start();
        ?>
        <div class="px-4 py-3 border-t border-white/10 bg-black/20">
            <label class="block text-base font-semibold tracking-widest uppercase text-gray-500 mb-2">League</label>
            <div class="relative">
                <select onchange="window.location.href=this.value" class="w-full appearance-none bg-white/10 text-white text-sm font-medium border border-white/20 rounded-lg px-3 py-2 pr-8 cursor-pointer hover:bg-white/15 hover:border-white/30 focus:outline-none focus:ring-2 focus:ring-accent-500/50 focus:border-accent-500 transition-all">
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
