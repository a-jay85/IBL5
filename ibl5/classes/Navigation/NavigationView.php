<?php

declare(strict_types=1);

namespace Navigation;

use Utilities\HtmlSanitizer;

/**
 * Renders the main navigation bar with premium sports editorial design
 * Desktop: Elegant hover dropdowns with staggered animations
 * Mobile: Full-height sliding panel with refined aesthetics
 */
class NavigationView
{
    private bool $isLoggedIn;
    private ?string $username;
    private string $currentLeague;
    private ?int $teamId;

    public function __construct(bool $isLoggedIn, ?string $username, string $currentLeague, ?int $teamId = null)
    {
        $this->isLoggedIn = $isLoggedIn;
        $this->username = $username;
        $this->currentLeague = $currentLeague;
        $this->teamId = $teamId;
    }

    /**
     * Get the navigation menu structure
     * @return array<string, array{links: array, icon?: string}>
     */
    private function getMenuStructure(): array
    {
        return [
            'Season' => [
                'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
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
                'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>',
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
                'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>',
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
                'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
                'links' => [
                    ['label' => 'Discord Server', 'url' => 'https://discord.com/invite/QXwBQxR', 'external' => true],
                    ['label' => 'GM Contact List', 'url' => '/ibl5/pages/contactList.php'],
                    ['label' => 'Prime Time Football', 'url' => 'http://www.thakfu.com/ptf/index.php', 'external' => true],
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
     * Get My Team menu based on current league
     * @return array|null
     */
    private function getMyTeamMenu(): ?array
    {
        if (!$this->isLoggedIn || !$this->teamId) {
            return null;
        }

        if ($this->currentLeague === 'ibl') {
            return [
                'icon' => '<img src="/ibl5/images/logo/new' . $this->teamId . '.png" alt="Team Logo" class="w-6 h-6 object-contain">',
                'links' => [
                    ['label' => 'Next Sim', 'url' => 'modules.php?name=Next_Sim', 'badge' => 'NEW'],
                    ['label' => 'Depth Chart Form', 'url' => 'modules.php?name=Depth_Chart_Entry'],
                    ['label' => 'Depth Chart Tracker', 'url' => 'modules.php?name=Depth_Record'],
                    ['label' => 'Offer Trade', 'url' => 'modules.php?name=Trading&op=reviewtrade'],
                    ['label' => 'Waiver Wire', 'url' => 'modules.php?name=Waivers&action=add'],
                    ['label' => 'Waive Player', 'url' => 'modules.php?name=Waivers&action=drop'],
                    ['label' => 'ASG/Award Voting', 'url' => 'modules.php?name=Voting'],
                    ['label' => 'Draft Scout/Select', 'url' => 'modules.php?name=Draft'],
                    ['label' => 'Free Agency', 'url' => 'modules.php?name=Free_Agency'],
                    ['label' => 'Player Movement', 'url' => 'modules.php?name=Player_Movement'],
                ]
            ];
        } elseif ($this->currentLeague === 'olympics') {
            return [
                'icon' => '<img src="/ibl5/images/logo/new' . $this->teamId . '.png" alt="Team Logo" class="w-6 h-6 object-contain">',
                'links' => [
                    ['label' => 'Depth Chart Form', 'url' => 'modules.php?name=Depth_Chart_Entry'],
                    ['label' => 'Depth Chart Tracker', 'url' => 'modules.php?name=Depth_Record'],
                ]
            ];
        }

        return null;
    }

    /**
     * Render a dropdown link item with stagger animation class
     */
    private function renderDropdownLink(array $link): string
    {
        $label = HtmlSanitizer::safeHtmlOutput($link['label']);
        $url = HtmlSanitizer::safeHtmlOutput($link['url']);
        $external = $link['external'] ?? false;
        $badge = $link['badge'] ?? null;

        $target = $external ? ' target="_blank" rel="noopener noreferrer"' : '';
        $externalIcon = $external ? ' <svg class="w-3 h-3 opacity-40 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>' : '';
        $badgeHtml = $badge ? ' <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-accent-500 text-white ml-2 tracking-wide">' . HtmlSanitizer::safeHtmlOutput($badge) . '</span>' : '';

        return '<a href="' . $url . '"' . $target . ' class="nav-dropdown-item block px-4 py-2.5 text-sm text-gray-300 hover:text-white hover:bg-white/5 transition-all duration-150 border-l-2 border-transparent hover:border-accent-500">'
            . '<span class="flex items-center justify-between">'
            . '<span>' . $label . $badgeHtml . '</span>'
            . $externalIcon
            . '</span>'
            . '</a>';
    }

    /**
     * Render a desktop dropdown menu
     */
    private function renderDesktopDropdown(string $title, array $data): string
    {
        $links = $data['links'];
        $icon = $data['icon'] ?? '';

        $html = '<div class="relative group">';
        $html .= '<button class="flex items-center gap-2 px-3 py-5 text-sm font-medium text-gray-300 hover:text-white transition-colors duration-200">';
        if ($icon) {
            $html .= '<span class="text-accent-500 group-hover:text-accent-400 transition-colors">' . $icon . '</span>';
        }
        $html .= '<span>' . HtmlSanitizer::safeHtmlOutput($title) . '</span>';
        $html .= '<svg class="w-3 h-3 opacity-50 group-hover:opacity-100 transition-all duration-200 group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>';
        $html .= '</button>';

        // Dropdown panel
        $html .= '<div class="absolute left-0 top-full pt-1 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">';
        $html .= '<div class="min-w-[220px] bg-navy-800/95 backdrop-blur-xl rounded-lg shadow-2xl shadow-black/30 border border-white/10 overflow-hidden">';

        // Header
        $html .= '<div class="px-4 py-2.5 border-b border-white/5">';
        $html .= '<span class="text-[11px] font-semibold tracking-widest uppercase text-accent-500">' . HtmlSanitizer::safeHtmlOutput($title) . '</span>';
        $html .= '</div>';

        // Links
        $html .= '<div class="py-1">';
        foreach ($links as $link) {
            $html .= $this->renderDropdownLink($link);
        }
        $html .= '</div>';

        $html .= '</div></div></div>';
        return $html;
    }

    /**
     * Render the mobile menu dropdown section
     */
    private function renderMobileDropdown(string $title, array $data, int $index): string
    {
        $links = $data['links'];
        $icon = $data['icon'] ?? '';

        $html = '<div class="mobile-section">';
        $html .= '<button class="mobile-dropdown-btn w-full flex items-center justify-between px-5 py-4 text-white hover:bg-white/5 transition-colors">';
        $html .= '<span class="flex items-center gap-3">';
        if ($icon) {
            $html .= '<span class="text-accent-500">' . $icon . '</span>';
        }
        $html .= '<span class="font-medium">' . HtmlSanitizer::safeHtmlOutput($title) . '</span>';
        $html .= '</span>';
        $html .= '<svg class="dropdown-arrow w-4 h-4 text-gray-500 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>';
        $html .= '</button>';

        $html .= '<div class="hidden bg-black/20">';
        foreach ($links as $link) {
            $label = HtmlSanitizer::safeHtmlOutput($link['label']);
            $url = HtmlSanitizer::safeHtmlOutput($link['url']);
            $external = $link['external'] ?? false;
            $badge = $link['badge'] ?? null;

            $target = $external ? ' target="_blank" rel="noopener noreferrer"' : '';
            $externalIcon = $external ? ' <svg class="w-3 h-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>' : '';
            $badgeHtml = $badge ? ' <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-accent-500 text-white ml-2">' . HtmlSanitizer::safeHtmlOutput($badge) . '</span>' : '';

            $html .= '<a href="' . $url . '"' . $target . ' class="flex items-center justify-between px-5 py-3 pl-12 text-sm text-gray-400 hover:text-white hover:bg-white/5 border-l-2 border-transparent hover:border-accent-500 transition-all">'
                . '<span>' . $label . $badgeHtml . '</span>'
                . $externalIcon
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

        return '<div class="relative">'
            . '<select onchange="window.location.href=this.value" class="appearance-none bg-white/10 text-white text-sm font-medium border border-white/20 rounded-lg px-3 py-2 pr-8 cursor-pointer hover:bg-white/15 hover:border-white/30 focus:outline-none focus:ring-2 focus:ring-accent-500/50 focus:border-accent-500 transition-all">'
            . '<option value="index.php?league=ibl"' . $iblSelected . ' class="bg-navy-800 text-white">IBL</option>'
            . '<option value="index.php?league=olympics"' . $olympicsSelected . ' class="bg-navy-800 text-white">Olympics</option>'
            . '</select>'
            . '<svg class="absolute right-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>'
            . '</div>';
    }

    /**
     * Render the complete navigation bar
     */
    public function render(): string
    {
        $menus = $this->getMenuStructure();
        $myTeamMenu = $this->getMyTeamMenu();
        $accountMenu = $this->getAccountMenu();

        ob_start();
        ?>
        <!-- Navigation Bar -->
        <nav class="fixed top-0 left-0 right-0 z-50 nav-grain">
            <!-- Background with gradient -->
            <div class="absolute inset-0 bg-gradient-to-r from-navy-900 via-navy-800 to-navy-900"></div>
            <!-- Bottom accent line -->
            <div class="absolute bottom-0 left-0 right-0 h-[1px] bg-gradient-to-r from-transparent via-accent-500/50 to-transparent"></div>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6">
                <div class="flex items-center justify-between">
                    <!-- Logo -->
                    <a href="index.php" class="flex items-center gap-3 py-4 group">
                        <!-- Basketball icon -->
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
                        <!-- Text logo -->
                        <div class="flex flex-col">
                            <span class="font-display text-2xl tracking-wider text-white leading-none">IBL</span>
                            <span class="text-[9px] tracking-[0.2em] text-accent-500 font-semibold uppercase">Fantasy League</span>
                        </div>
                    </a>

                    <!-- Desktop Navigation -->
                    <div class="hidden lg:flex items-center">
                        <?php foreach ($menus as $title => $menu): ?>
                            <?= $this->renderDesktopDropdown($title, $menu) ?>
                        <?php endforeach; ?>

                        <!-- My Team dropdown (if user has a team) -->
                        <?php if ($myTeamMenu): ?>
                            <?= $this->renderDesktopDropdown('My Team', $myTeamMenu) ?>
                        <?php endif; ?>

                        <!-- Divider -->
                        <div class="w-px h-6 bg-white/10 mx-2"></div>

                        <!-- Account dropdown -->
                        <?= $this->renderDesktopDropdown(
                            $this->isLoggedIn ? ($this->username ?? 'Account') : 'Login',
                            [
                                'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
                                'links' => $accountMenu
                            ]
                        ) ?>

                        <!-- League switcher -->
                        <div class="pl-3">
                            <?= $this->renderLeagueSwitcher() ?>
                        </div>
                    </div>

                    <!-- Mobile hamburger button -->
                    <button id="nav-hamburger" class="lg:hidden relative w-10 h-10 flex items-center justify-center text-white hover:bg-white/10 rounded-lg transition-colors" aria-label="Toggle menu" aria-expanded="false">
                        <div class="w-5 h-4 flex flex-col justify-between">
                            <span class="block h-0.5 w-full bg-current rounded-full transition-transform origin-center" id="hamburger-top"></span>
                            <span class="block h-0.5 w-full bg-current rounded-full transition-opacity" id="hamburger-middle"></span>
                            <span class="block h-0.5 w-full bg-current rounded-full transition-transform origin-center" id="hamburger-bottom"></span>
                        </div>
                    </button>
                </div>
            </div>
        </nav>

        <!-- Mobile menu overlay -->
        <div id="nav-overlay" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-40 lg:hidden transition-opacity duration-300"></div>

        <!-- Mobile menu -->
        <nav id="nav-mobile-menu" class="fixed top-16 right-0 bottom-0 w-[300px] max-w-[85vw] z-50 transform translate-x-full transition-transform duration-300 ease-out lg:hidden">
            <!-- Background -->
            <div class="absolute inset-0 bg-gradient-to-b from-navy-800 to-navy-900"></div>
            <!-- Left accent line -->
            <div class="absolute top-0 left-0 bottom-0 w-[1px] bg-gradient-to-b from-accent-500/50 via-accent-500/20 to-transparent"></div>

            <div class="relative h-full flex flex-col mobile-menu-scroll overflow-y-auto">
                <!-- User greeting -->
                <?php if ($this->isLoggedIn && $this->username): ?>
                    <div class="mobile-section px-5 py-4 border-b border-white/5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-accent-500/20 flex items-center justify-center">
                                <svg class="w-5 h-5 text-accent-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wide">Welcome back</div>
                                <div class="text-white font-semibold"><?= HtmlSanitizer::safeHtmlOutput($this->username) ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Menu sections -->
                <?php $index = 2; foreach ($menus as $title => $menu): ?>
                    <?= $this->renderMobileDropdown($title, $menu, $index++) ?>
                <?php endforeach; ?>

                <!-- My Team section (if user has a team) -->
                <?php if ($myTeamMenu): ?>
                    <?= $this->renderMobileDropdown('My Team', $myTeamMenu, $index++) ?>
                <?php endif; ?>

                <!-- Account section -->
                <?= $this->renderMobileDropdown(
                    $this->isLoggedIn ? 'Account' : 'Login',
                    [
                        'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
                        'links' => $accountMenu
                    ],
                    $index++
                ) ?>

                <!-- League switcher -->
                <div class="mobile-section mt-auto px-5 py-4 border-t border-white/5 bg-black/20">
                    <label class="block text-xs text-gray-500 uppercase tracking-wide mb-2">League</label>
                    <?= $this->renderLeagueSwitcher() ?>
                </div>
            </div>
        </nav>
        <?php
        return ob_get_clean();
    }
}
