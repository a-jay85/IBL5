<?php

declare(strict_types=1);

namespace Navigation;

use Utilities\HtmlSanitizer;

/**
 * Renders the main navigation bar with Tailwind CSS
 * Desktop: Hover dropdowns
 * Mobile: Full-screen hamburger menu
 */
class NavigationView
{
    private bool $isLoggedIn;
    private ?string $username;
    private string $currentLeague;

    public function __construct(bool $isLoggedIn, ?string $username, string $currentLeague)
    {
        $this->isLoggedIn = $isLoggedIn;
        $this->username = $username;
        $this->currentLeague = $currentLeague;
    }

    /**
     * Get the navigation menu structure
     * @return array<string, array{links: array}>
     */
    private function getMenuStructure(): array
    {
        return [
            'Season' => [
                'links' => [
                    ['label' => 'Standings', 'url' => 'modules.php?name=Standings'],
                    ['label' => 'Schedule', 'url' => 'modules.php?name=Schedule'],
                    ['label' => 'Injuries', 'url' => 'modules.php?name=Injuries'],
                    ['label' => 'Waiver Wire', 'url' => 'modules.php?name=Team&op=team&tid=0'],
                    ['label' => 'Player Database', 'url' => 'modules.php?name=Player_Search'],
                    ['label' => 'Draft Pick Locator', 'url' => 'modules.php?name=Draft_Pick_Locator'],
                    ['label' => 'Cap Space', 'url' => 'modules.php?name=Cap_Info'],
                    ['label' => 'Free Agency Preview', 'url' => '/ibl5/pages/freeAgencyPreview.php'],
                    ['label' => 'JSB Export', 'url' => 'ibl/IBL'],
                ],
            ],
            'Stats' => [
                'links' => [
                    ['label' => 'League Leaders', 'url' => 'modules.php?name=Chunk_Stats&op=season'],
                    ['label' => 'League Starters', 'url' => 'modules.php?name=League_Starters', 'badge' => 'NEW'],
                    ['label' => 'Sim Leaders', 'url' => 'modules.php?name=Chunk_Stats&op=chunk'],
                    ['label' => 'Compare Players', 'url' => 'modules.php?name=Compare_Players'],
                    ['label' => 'Season Highs', 'url' => '/ibl5/pages/seasonHighs.php'],
                    ['label' => 'Series Records', 'url' => 'modules.php?name=Series_Records'],
                    ['label' => 'Team Off/Def Stats', 'url' => 'modules.php?name=League_Stats'],
                ],
            ],
            'History' => [
                'links' => [
                    ['label' => 'Season Archive', 'url' => 'modules.php?name=Content&pa=showpage&pid=5'],
                    ['label' => 'Franchise History', 'url' => 'modules.php?name=Franchise_History'],
                    ['label' => 'Record Holders', 'url' => 'modules.php?name=Content&pa=showpage&pid=8'],
                    ['label' => 'Transaction History', 'url' => 'modules.php?name=Stories_Archive'],
                    ['label' => 'Award History', 'url' => 'modules.php?name=Player_Awards'],
                    ['label' => 'All-Star Appearances', 'url' => '/ibl5/pages/allStarAppearances.php'],
                    ['label' => 'Season Leaderboards', 'url' => 'modules.php?name=Season_Leaders'],
                    ['label' => 'Career Leaderboards', 'url' => 'modules.php?name=Leaderboards'],
                    ['label' => 'Draft History', 'url' => '/ibl5/pages/draftHistory.php'],
                    ['label' => 'Forums (archived)', 'url' => '../iblforum/forum.php'],
                    ['label' => 'v2/v3 Archive', 'url' => '../previous-ibl-archive'],
                ],
            ],
            'Community' => [
                'links' => [
                    ['label' => 'Discord Server', 'url' => 'https://discord.com/invite/QXwBQxR', 'external' => true],
                    ['label' => 'GM Contact List', 'url' => '/ibl5/pages/contactList.php'],
                    ['label' => 'Prime Time Football', 'url' => 'http://www.thakfu.com/ptf/index.php', 'external' => true],
                ],
            ],
            'Games' => [
                'links' => [
                    ['label' => '1-On-1 Game', 'url' => 'modules.php?name=One-on-One'],
                ],
            ],
        ];
    }

    /**
     * Get account menu items based on login state
     * @return array
     */
    private function getAccountMenu(): array
    {
        if ($this->isLoggedIn) {
            return [
                ['label' => 'Your Account', 'url' => 'modules.php?name=Your_Account'],
                ['label' => 'Topics', 'url' => 'modules.php?name=Topics'],
                ['label' => 'Logout', 'url' => 'modules.php?name=Your_Account&op=logout'],
            ];
        }

        return [
            ['label' => 'Topics', 'url' => 'modules.php?name=Topics'],
            ['label' => 'Create Account', 'url' => 'modules.php?name=Your_Account&op=new_user'],
        ];
    }

    /**
     * Render a dropdown link item
     */
    private function renderDropdownLink(array $link): string
    {
        $label = HtmlSanitizer::safeHtmlOutput($link['label']);
        $url = HtmlSanitizer::safeHtmlOutput($link['url']);
        $external = $link['external'] ?? false;
        $badge = $link['badge'] ?? null;

        $target = $external ? ' target="_blank" rel="noopener noreferrer"' : '';
        $externalIcon = $external ? ' <span class="text-xs opacity-60">↗</span>' : '';
        $badgeHtml = $badge ? ' <span class="bg-red-500 text-white text-xs px-1.5 py-0.5 rounded ml-1">' . HtmlSanitizer::safeHtmlOutput($badge) . '</span>' : '';

        return '<a href="' . $url . '"' . $target . ' class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">'
            . $label . $badgeHtml . $externalIcon
            . '</a>';
    }

    /**
     * Render a desktop dropdown menu
     */
    private function renderDesktopDropdown(string $title, array $links): string
    {
        $html = '<div class="relative group">';
        $html .= '<button class="flex items-center gap-1 px-3 py-4 text-white font-medium hover:bg-blue-800 transition-colors">';
        $html .= HtmlSanitizer::safeHtmlOutput($title);
        $html .= ' <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>';
        $html .= '</button>';
        $html .= '<div class="absolute left-0 top-full min-w-48 bg-white shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-150 z-50">';

        foreach ($links as $link) {
            $html .= $this->renderDropdownLink($link);
        }

        $html .= '</div></div>';
        return $html;
    }

    /**
     * Render the mobile menu dropdown section
     */
    private function renderMobileDropdown(string $title, array $links): string
    {
        $html = '<div class="border-b border-blue-800">';
        $html .= '<button class="mobile-dropdown-btn w-full flex items-center justify-between px-4 py-3 text-white font-medium hover:bg-blue-800">';
        $html .= HtmlSanitizer::safeHtmlOutput($title);
        $html .= ' <svg class="dropdown-arrow w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>';
        $html .= '</button>';
        $html .= '<div class="hidden bg-blue-800">';

        foreach ($links as $link) {
            $label = HtmlSanitizer::safeHtmlOutput($link['label']);
            $url = HtmlSanitizer::safeHtmlOutput($link['url']);
            $external = $link['external'] ?? false;
            $badge = $link['badge'] ?? null;

            $target = $external ? ' target="_blank" rel="noopener noreferrer"' : '';
            $externalIcon = $external ? ' <span class="text-xs opacity-60">↗</span>' : '';
            $badgeHtml = $badge ? ' <span class="bg-red-500 text-white text-xs px-1.5 py-0.5 rounded ml-1">' . HtmlSanitizer::safeHtmlOutput($badge) . '</span>' : '';

            $html .= '<a href="' . $url . '"' . $target . ' class="block px-6 py-2 text-blue-100 hover:bg-blue-900 hover:text-white">'
                . $label . $badgeHtml . $externalIcon
                . '</a>';
        }

        $html .= '</div></div>';
        return $html;
    }

    /**
     * Render the league switcher dropdown
     */
    private function renderLeagueSwitcher(): string
    {
        $iblSelected = $this->currentLeague === 'ibl' ? ' selected' : '';
        $olympicsSelected = $this->currentLeague === 'olympics' ? ' selected' : '';

        return '<select onchange="window.location.href=this.value" class="bg-blue-800 text-white text-sm border border-blue-600 rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-400">'
            . '<option value="index.php?league=ibl"' . $iblSelected . '>IBL</option>'
            . '<option value="index.php?league=olympics"' . $olympicsSelected . '>Olympics</option>'
            . '</select>';
    }

    /**
     * Render the complete navigation bar
     */
    public function render(): string
    {
        $menus = $this->getMenuStructure();
        $accountMenu = $this->getAccountMenu();

        ob_start();
        ?>
        <!-- Navigation Bar -->
        <nav class="fixed top-0 left-0 right-0 z-50 bg-blue-700 shadow-lg">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex items-center justify-between">
                    <!-- Logo / Home -->
                    <a href="index.php" class="flex items-center py-3 text-white font-bold text-lg hover:text-blue-200 transition-colors">
                        IBL
                    </a>

                    <!-- Desktop Navigation -->
                    <div class="hidden md:flex items-center">
                        <!-- Home link -->
                        <a href="index.php" class="px-3 py-4 text-white font-medium hover:bg-blue-800 transition-colors">Home</a>

                        <?php foreach ($menus as $title => $menu): ?>
                            <?= $this->renderDesktopDropdown($title, $menu['links']) ?>
                        <?php endforeach; ?>

                        <!-- Account dropdown -->
                        <?= $this->renderDesktopDropdown($this->isLoggedIn ? 'Account' : 'Login', $accountMenu) ?>

                        <!-- League switcher -->
                        <div class="px-3 py-2">
                            <?= $this->renderLeagueSwitcher() ?>
                        </div>
                    </div>

                    <!-- Mobile hamburger button -->
                    <button id="nav-hamburger" class="md:hidden p-2 text-white hover:bg-blue-800 rounded" aria-label="Toggle menu" aria-expanded="false">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </nav>

        <!-- Mobile menu overlay -->
        <div id="nav-overlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40 md:hidden"></div>

        <!-- Mobile menu -->
        <nav id="nav-mobile-menu" class="fixed top-14 left-0 bottom-0 w-72 bg-blue-700 z-50 transform -translate-x-full transition-transform duration-300 ease-in-out md:hidden overflow-y-auto">
            <!-- Home link -->
            <a href="index.php" class="block px-4 py-3 text-white font-medium border-b border-blue-800 hover:bg-blue-800">Home</a>

            <?php foreach ($menus as $title => $menu): ?>
                <?= $this->renderMobileDropdown($title, $menu['links']) ?>
            <?php endforeach; ?>

            <!-- Account section -->
            <?= $this->renderMobileDropdown($this->isLoggedIn ? 'Account' : 'Login', $accountMenu) ?>

            <!-- League switcher in mobile -->
            <div class="px-4 py-3 border-t border-blue-800">
                <label class="block text-blue-200 text-sm mb-1">League</label>
                <?= $this->renderLeagueSwitcher() ?>
            </div>

            <?php if ($this->isLoggedIn && $this->username): ?>
                <div class="px-4 py-3 border-t border-blue-800 text-blue-200 text-sm">
                    Logged in as <strong class="text-white"><?= HtmlSanitizer::safeHtmlOutput($this->username) ?></strong>
                </div>
            <?php endif; ?>
        </nav>
        <?php
        return ob_get_clean();
    }
}
