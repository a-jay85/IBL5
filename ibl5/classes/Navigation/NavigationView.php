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
                    ['label' => 'Free Agency Preview', 'url' => 'modules.php?name=Free_Agency_Preview'],
                    ['label' => 'Contract List', 'url' => 'modules.php?name=Contract_List'],
                    ['label' => 'JSB Export', 'url' => 'ibl/IBL'],
                ],
            ],
            'Stats' => [
                'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>',
                'links' => [
                    ['label' => 'League Starters', 'url' => 'modules.php?name=League_Starters', 'badge' => 'NEW'],
                    ['label' => 'Compare Players', 'url' => 'modules.php?name=Compare_Players'],
                    ['label' => 'Season Highs', 'url' => 'modules.php?name=Season_Highs'],
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
                    ['label' => 'All-Star Appearances', 'url' => 'modules.php?name=All_Star_Appearances'],
                    ['label' => 'Season Leaderboards', 'url' => 'modules.php?name=Season_Leaders'],
                    ['label' => 'Career Leaderboards', 'url' => 'modules.php?name=Leaderboards'],
                    ['label' => 'Draft History', 'url' => 'modules.php?name=Draft_History'],
                    ['label' => 'Forums (archived)', 'url' => '../iblforum/forum.php'],
                    ['label' => 'v2/v3 Archive', 'url' => '../previous-ibl-archive'],
                ],
            ],
            'Community' => [
                'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
                'links' => [
                    ['label' => 'Discord Server', 'url' => 'https://discord.com/invite/QXwBQxR', 'external' => true],
                    ['label' => 'GM Contact List', 'url' => 'modules.php?name=Contact_List'],
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
     * Render the inline login form for the dropdown
     * Premium sports editorial design with clean, modern aesthetics
     */
    private function renderLoginForm(): string
    {
        return '
        <div class="px-4 pt-4 pb-3">
            <form action="modules.php?name=Your_Account" method="post" class="space-y-3">
                <!-- Username field -->
                <div>
                    <label for="nav-username" class="block text-[10px] font-semibold tracking-widest uppercase text-gray-400 mb-1.5">Username</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </span>
                        <input
                            type="text"
                            name="username"
                            id="nav-username"
                            maxlength="25"
                            required
                            placeholder="Enter username"
                            class="w-full bg-white/5 border border-white/10 rounded-lg py-2.5 pl-10 pr-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-accent-500 focus:ring-1 focus:ring-accent-500/50 transition-all"
                        >
                    </div>
                </div>

                <!-- Password field -->
                <div>
                    <label for="nav-password" class="block text-[10px] font-semibold tracking-widest uppercase text-gray-400 mb-1.5">Password</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                        <input
                            type="password"
                            name="user_password"
                            id="nav-password"
                            maxlength="20"
                            required
                            placeholder="Enter password"
                            class="w-full bg-white/5 border border-white/10 rounded-lg py-2.5 pl-10 pr-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-accent-500 focus:ring-1 focus:ring-accent-500/50 transition-all"
                        >
                    </div>
                </div>

                <input type="hidden" name="op" value="login">

                <!-- Submit button -->
                <button
                    type="submit"
                    class="w-full bg-gradient-to-r from-accent-500 to-orange-600 hover:from-accent-400 hover:to-orange-500 text-white font-semibold py-2.5 px-4 rounded-lg shadow-lg shadow-accent-500/25 hover:shadow-accent-500/40 transition-all duration-200 text-sm tracking-wide"
                >
                    Login
                </button>
            </form>
        </div>

        <!-- Divider -->
        <div class="border-t border-white/10 mx-4"></div>
        ';
    }

    /**
     * Render the mobile login form
     * Full-width design optimized for touch interactions
     */
    private function renderMobileLoginForm(): string
    {
        return '
        <div class="px-5 pt-4 pb-4 bg-gradient-to-b from-accent-500/10 to-transparent">
            <form action="modules.php?name=Your_Account" method="post" class="space-y-3">
                <!-- Username field -->
                <div>
                    <label for="mobile-nav-username" class="block text-[10px] font-semibold tracking-widest uppercase text-gray-400 mb-2">Username</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </span>
                        <input
                            type="text"
                            name="username"
                            id="mobile-nav-username"
                            maxlength="25"
                            required
                            placeholder="Enter username"
                            class="w-full bg-white/5 border border-white/10 rounded-xl py-3 pl-11 pr-4 text-base text-white placeholder-gray-500 focus:outline-none focus:border-accent-500 focus:ring-2 focus:ring-accent-500/30 transition-all"
                        >
                    </div>
                </div>

                <!-- Password field -->
                <div>
                    <label for="mobile-nav-password" class="block text-[10px] font-semibold tracking-widest uppercase text-gray-400 mb-2">Password</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                        <input
                            type="password"
                            name="user_password"
                            id="mobile-nav-password"
                            maxlength="20"
                            required
                            placeholder="Enter password"
                            class="w-full bg-white/5 border border-white/10 rounded-xl py-3 pl-11 pr-4 text-base text-white placeholder-gray-500 focus:outline-none focus:border-accent-500 focus:ring-2 focus:ring-accent-500/30 transition-all"
                        >
                    </div>
                </div>

                <input type="hidden" name="op" value="login">

                <!-- Submit button -->
                <button
                    type="submit"
                    class="w-full bg-gradient-to-r from-accent-500 to-orange-600 hover:from-accent-400 hover:to-orange-500 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg shadow-accent-500/25 hover:shadow-accent-500/40 transition-all duration-200 text-base tracking-wide active:scale-[0.98]"
                >
                    Login
                </button>
            </form>
        </div>
        ';
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
     * @param string $title Menu title
     * @param array $data Menu data with links and optional icon
     * @param bool $includeLoginForm Whether to include login form at top (for Login menu when logged out)
     * @param bool $includeLeagueSwitcher Whether to include league switcher at bottom
     * @param bool $alignRight Whether to align dropdown to the right edge (for rightmost menu items)
     */
    private function renderDesktopDropdown(string $title, array $data, bool $includeLoginForm = false, bool $includeLeagueSwitcher = false, bool $alignRight = false): string
    {
        $links = $data['links'];
        $icon = $data['icon'] ?? '';

        $html = '<div class="relative group">';
        $html .= '<button class="flex items-center gap-2 px-3 py-3 text-sm font-medium text-gray-300 hover:text-white transition-colors duration-200">';
        if ($icon) {
            $html .= '<span class="text-accent-500 group-hover:text-accent-400 transition-colors">' . $icon . '</span>';
        }
        $html .= '<span>' . HtmlSanitizer::safeHtmlOutput($title) . '</span>';
        $html .= '<svg class="w-3 h-3 opacity-50 group-hover:opacity-100 transition-all duration-200 group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>';
        $html .= '</button>';

        // Dropdown panel - wider when login form is included, align right for edge menus
        $minWidth = $includeLoginForm ? 'min-w-[280px]' : 'min-w-[220px]';
        $alignment = $alignRight ? 'right-0' : 'left-0';
        $html .= '<div class="absolute ' . $alignment . ' top-full pt-1 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">';
        $html .= '<div class="' . $minWidth . ' bg-navy-800/95 backdrop-blur-xl rounded-lg shadow-2xl shadow-black/30 border border-white/10 overflow-hidden">';

        // Login form at top (if not logged in and this is the Login dropdown)
        if ($includeLoginForm) {
            $html .= $this->renderLoginForm();
        } else {
            // Header (only show if no login form)
            $html .= '<div class="px-4 py-2.5 border-b border-white/5">';
            $html .= '<span class="text-[11px] font-semibold tracking-widest uppercase text-accent-500">' . HtmlSanitizer::safeHtmlOutput($title) . '</span>';
            $html .= '</div>';
        }

        // Links
        $html .= '<div class="py-1">';
        foreach ($links as $link) {
            $html .= $this->renderDropdownLink($link);
        }
        $html .= '</div>';

        // League switcher at bottom (for Season menu)
        if ($includeLeagueSwitcher) {
            $html .= $this->renderDropdownLeagueSwitcher();
        }

        $html .= '</div></div></div>';
        return $html;
    }

    /**
     * Render the league switcher for inside a dropdown menu
     */
    private function renderDropdownLeagueSwitcher(): string
    {
        $iblSelected = $this->currentLeague === 'ibl' ? ' selected' : '';
        $olympicsSelected = $this->currentLeague === 'olympics' ? ' selected' : '';

        return '<div class="px-4 py-3 border-t border-white/10 bg-black/20">'
            . '<label class="block text-[10px] font-semibold tracking-widest uppercase text-gray-500 mb-2">League</label>'
            . '<div class="relative">'
            . '<select onchange="window.location.href=this.value" class="w-full appearance-none bg-white/10 text-white text-sm font-medium border border-white/20 rounded-lg px-3 py-2 pr-8 cursor-pointer hover:bg-white/15 hover:border-white/30 focus:outline-none focus:ring-2 focus:ring-accent-500/50 focus:border-accent-500 transition-all">'
            . '<option value="index.php?league=ibl"' . $iblSelected . ' class="bg-navy-800 text-white">IBL</option>'
            . '<option value="index.php?league=olympics"' . $olympicsSelected . ' class="bg-navy-800 text-white">Olympics</option>'
            . '</select>'
            . '<svg class="absolute right-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render the mobile menu dropdown section
     * @param string $title Menu title
     * @param array $data Menu data with links and optional icon
     * @param int $index Stagger animation index
     * @param bool $includeLoginForm Whether to include login form at top (for Login menu when logged out)
     * @param bool $includeLeagueSwitcher Whether to include league switcher at bottom
     */
    private function renderMobileDropdown(string $title, array $data, int $index, bool $includeLoginForm = false, bool $includeLeagueSwitcher = false): string
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

        // Login form at top (if not logged in and this is the Login dropdown)
        if ($includeLoginForm) {
            $html .= $this->renderMobileLoginForm();
            // Divider before other links
            $html .= '<div class="border-t border-white/10 mx-5 my-2"></div>';
        }

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

        // League switcher at bottom (for Season menu)
        if ($includeLeagueSwitcher) {
            $html .= $this->renderMobileDropdownLeagueSwitcher();
        }

        $html .= '</div></div>';

        return $html;
    }

    /**
     * Render the league switcher for inside a mobile dropdown menu
     */
    private function renderMobileDropdownLeagueSwitcher(): string
    {
        $iblSelected = $this->currentLeague === 'ibl' ? ' selected' : '';
        $olympicsSelected = $this->currentLeague === 'olympics' ? ' selected' : '';

        return '<div class="px-5 py-3 border-t border-white/10 mt-1">'
            . '<label class="block text-[10px] font-semibold tracking-widest uppercase text-gray-500 mb-2">League</label>'
            . '<div class="relative">'
            . '<select onchange="window.location.href=this.value" class="w-full appearance-none bg-white/10 text-white text-sm font-medium border border-white/20 rounded-xl px-3 py-2.5 pr-8 cursor-pointer hover:bg-white/15 hover:border-white/30 focus:outline-none focus:ring-2 focus:ring-accent-500/50 focus:border-accent-500 transition-all">'
            . '<option value="index.php?league=ibl"' . $iblSelected . ' class="bg-navy-800 text-white">IBL</option>'
            . '<option value="index.php?league=olympics"' . $olympicsSelected . ' class="bg-navy-800 text-white">Olympics</option>'
            . '</select>'
            . '<svg class="absolute right-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>'
            . '</div>'
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
                    <a href="index.php" class="flex items-center gap-3 py-2 group">
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
                            <span class="text-[9px] tracking-[0.2em] text-accent-500 font-semibold uppercase">Sim League</span>
                        </div>
                    </a>

                    <!-- Desktop Navigation (right-aligned) -->
                    <div class="hidden lg:flex items-center ml-auto">
                        <?php foreach ($menus as $title => $menu): ?>
                            <?= $this->renderDesktopDropdown(
                                $title,
                                $menu,
                                false, // no login form
                                $title === 'Season' // include league switcher only for Season menu
                            ) ?>
                        <?php endforeach; ?>

                        <!-- My Team dropdown (if user has a team) -->
                        <?php if ($myTeamMenu): ?>
                            <?= $this->renderDesktopDropdown('My Team', $myTeamMenu) ?>
                        <?php endif; ?>

                        <!-- Divider -->
                        <div class="w-px h-6 bg-white/10 mx-2"></div>

                        <!-- Account dropdown (right-aligned to stay within viewport) -->
                        <?= $this->renderDesktopDropdown(
                            $this->isLoggedIn ? ($this->username ?? 'Account') : 'Login',
                            [
                                'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
                                'links' => $accountMenu
                            ],
                            !$this->isLoggedIn, // Include login form when not logged in
                            false, // No league switcher
                            true // Align dropdown to right edge
                        ) ?>
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
        <nav id="nav-mobile-menu" class="fixed top-12 right-0 bottom-0 w-[300px] max-w-[85vw] z-50 transform translate-x-full transition-transform duration-300 ease-out lg:hidden">
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
                    <?= $this->renderMobileDropdown(
                        $title,
                        $menu,
                        $index++,
                        false, // no login form
                        $title === 'Season' // include league switcher only for Season menu
                    ) ?>
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
                    $index++,
                    !$this->isLoggedIn // Include login form when not logged in
                ) ?>
            </div>
        </nav>
        <?php
        return ob_get_clean();
    }
}
