<?php

declare(strict_types=1);

namespace Navigation;

use Utilities\HtmlSanitizer;

/**
 * Renders the main navigation bar with premium sports editorial design
 * Desktop: Elegant hover dropdowns with staggered animations
 * Mobile: Full-height sliding panel with refined aesthetics
 *
 * @phpstan-type NavLink array{label?: string, url?: string, external?: bool, badge?: string, rawHtml?: string}
 * @phpstan-type NavMenuData array{links: list<NavLink>, icon?: string}
 * @phpstan-type NavTeamsData array<string, array<string, list<array{teamid: int, team_name: string, team_city: string}>>>
 */
class NavigationView
{
    private bool $isLoggedIn;
    private ?string $username;
    private string $currentLeague;
    private ?int $teamId;
    /** @var array<string, array<string, list<array{teamid: int, team_name: string, team_city: string}>>>|null */
    private ?array $teamsData;
    private string $seasonPhase;
    private string $allowWaivers;
    private ?string $serverName;
    private ?string $requestUri;

    /**
     * @param array<string, array<string, list<array{teamid: int, team_name: string, team_city: string}>>>|null $teamsData
     */
    public function __construct(bool $isLoggedIn, ?string $username, string $currentLeague, ?int $teamId = null, ?array $teamsData = null, string $seasonPhase = '', string $allowWaivers = '', ?string $serverName = null, ?string $requestUri = null)
    {
        $this->isLoggedIn = $isLoggedIn;
        $this->username = $username;
        $this->currentLeague = $currentLeague;
        $this->teamId = $teamId;
        $this->teamsData = $teamsData;
        $this->seasonPhase = $seasonPhase;
        $this->allowWaivers = $allowWaivers;
        $this->serverName = $serverName;
        $this->requestUri = $requestUri;
    }

    /**
     * Resolve a user's team ID from their username via the database.
     * Looks up the team name from nuke_users, then the team ID from ibl_team_info.
     */
    public static function resolveTeamId(\mysqli $db, string $username): ?int
    {
        $stmt = $db->prepare("SELECT user_ibl_team FROM nuke_users WHERE username = ?");
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            $stmt->close();
            return null;
        }
        $row = $result->fetch_assoc();
        if ($row === null || $row === false) {
            $stmt->close();
            return null;
        }
        $teamName = trim((string) $row['user_ibl_team']);
        $stmt->close();

        if ($teamName === '' || $teamName === '0') {
            return null;
        }

        $stmt2 = $db->prepare("SELECT teamid FROM ibl_team_info WHERE team_name = ?");
        if ($stmt2 === false) {
            return null;
        }
        $stmt2->bind_param('s', $teamName);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        if ($result2 === false) {
            $stmt2->close();
            return null;
        }
        $row2 = $result2->fetch_assoc();
        $teamId = ($row2 !== null && $row2 !== false) ? (int) $row2['teamid'] : null;
        $stmt2->close();

        return $teamId;
    }

    /**
     * Get the navigation menu structure
     *
     * @return array<string, array{links: list<array{label?: string, url?: string, external?: bool, badge?: string, rawHtml?: string}>, icon?: string}>
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
                    ['label' => 'Player Database', 'url' => 'modules.php?name=PlayerDatabase'],
                    ['label' => 'Draft Pick Locator', 'url' => 'modules.php?name=DraftPickLocator'],
                    ['label' => 'Cap Space', 'url' => 'modules.php?name=CapSpace'],
                    ['label' => 'Free Agency Preview', 'url' => 'modules.php?name=FreeAgencyPreview'],
                    ['label' => 'Topics (News)', 'url' => 'modules.php?name=Topics'],
                    ['label' => 'Contract List', 'url' => 'modules.php?name=ContractList'],
                    ['label' => 'Player Movement', 'url' => 'modules.php?name=PlayerMovement'],
                    ['label' => 'JSB Export', 'url' => 'ibl/IBL'],
                ],
            ],
            'Stats' => [
                'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>',
                'links' => [
                    ['label' => 'League Starters', 'url' => 'modules.php?name=LeagueStarters', 'badge' => 'NEW'],
                    ['label' => 'Compare Players', 'url' => 'modules.php?name=ComparePlayers'],
                    ['label' => 'Season Highs', 'url' => 'modules.php?name=SeasonHighs'],
                    ['label' => 'Series Records', 'url' => 'modules.php?name=SeriesRecords'],
                    ['label' => 'Team Off/Def Stats', 'url' => 'modules.php?name=TeamOffDefStats'],
                ],
            ],
            'History' => [
                'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>',
                'links' => [
                    ['label' => 'Franchise History', 'url' => 'modules.php?name=FranchiseHistory'],
                    ['label' => 'Transaction History', 'url' => 'modules.php?name=TransactionHistory'],
                    ['label' => 'Draft History', 'url' => 'modules.php?name=DraftHistory'],
                    ['label' => 'Award History', 'url' => 'modules.php?name=AwardHistory'],
                    ['label' => 'Record Holders', 'url' => 'modules.php?name=RecordHolders'],
                    ['label' => 'All-Star Appearances', 'url' => 'modules.php?name=AllStarAppearances'],
                    ['label' => 'Season Leaderboards', 'url' => 'modules.php?name=SeasonLeaderboards'],
                    ['label' => 'Career Leaderboards', 'url' => 'modules.php?name=CareerLeaderboards'],
                    ['label' => 'Season Archive', 'url' => 'modules.php?name=Content&pa=showpage&pid=5'],
                    ['label' => '1-On-1 Game', 'url' => 'modules.php?name=OneOnOneGame'],
                ],
            ],
            'Community' => [
                'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
                'links' => [
                    ['label' => 'Discord Server', 'url' => 'https://discord.com/invite/QXwBQxR', 'external' => true],
                    ['label' => 'Prime Time Football', 'url' => 'http://www.thakfu.com/ptf/index.php', 'external' => true],
                    ['label' => 'Activity Tracker', 'url' => 'modules.php?name=ActivityTracker'],
                    ['label' => 'GM Contact List', 'url' => 'modules.php?name=GMContactList'],
                ],
            ],
        ];
    }

    /**
     * Get account menu items based on login state
     *
     * @return list<array{label: string, url: string}>
     */
    private function getAccountMenu(): array
    {
        if ($this->isLoggedIn) {
            return [
                ['label' => 'Your Account', 'url' => 'modules.php?name=YourAccount'],
                ['label' => 'Logout', 'url' => 'modules.php?name=YourAccount&op=logout'],
            ];
        }

        return [
            ['label' => 'Topics', 'url' => 'modules.php?name=Topics'],
            ['label' => 'Create Account', 'url' => 'modules.php?name=YourAccount&op=new_user'],
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
            <form action="modules.php?name=YourAccount" method="post" class="space-y-3">
                <!-- Username field -->
                <div>
                    <label for="nav-username" class="block text-base font-semibold tracking-widest uppercase text-gray-400 mb-1.5">Username</label>
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
                    <label for="nav-password" class="block text-base font-semibold tracking-widest uppercase text-gray-400 mb-1.5">Password</label>
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
            <form action="modules.php?name=YourAccount" method="post" class="space-y-3">
                <!-- Username field -->
                <div>
                    <label for="mobile-nav-username" class="block text-base font-semibold tracking-widest uppercase text-gray-400 mb-2">Username</label>
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
                    <label for="mobile-nav-password" class="block text-base font-semibold tracking-widest uppercase text-gray-400 mb-2">Password</label>
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
     *
     * @return array{icon: string, links: list<array{label?: string, url?: string, external?: bool, badge?: string, rawHtml?: string}>}|null
     */
    private function getMyTeamMenu(): ?array
    {
        if (!$this->isLoggedIn || $this->teamId === null) {
            return null;
        }

        if ($this->currentLeague === 'ibl') {
            $links = [
                ['label' => 'Team Page', 'url' => 'modules.php?name=Team&op=team&teamID=' . $this->teamId],
                ['label' => 'Schedule', 'url' => 'modules.php?name=Schedule&teamID=' . $this->teamId],
                ['label' => 'Next Sim', 'url' => 'modules.php?name=NextSim', 'badge' => 'NEW'],
                ['label' => 'Depth Chart Entry', 'url' => 'modules.php?name=DepthChartEntry'],
                ['label' => 'Trading', 'url' => 'modules.php?name=Trading&op=reviewtrade'],
                ['label' => 'Voting', 'url' => 'modules.php?name=Voting'],
                ['label' => 'Draft History', 'url' => 'modules.php?name=DraftHistory&teamID=' . $this->teamId],
            ];

            if ($this->allowWaivers === 'Yes') {
                $links[] = ['rawHtml' => 'Waivers: <a href="modules.php?name=Waivers&amp;action=add">Add</a> | <a href="modules.php?name=Waivers&amp;action=waive">Waive</a>'];
            }

            if ($this->seasonPhase === 'Draft') {
                array_unshift($links, ['label' => 'Draft', 'url' => 'modules.php?name=Draft', 'badge' => 'LIVE']);
            }

            if ($this->seasonPhase === 'Free Agency') {
                array_unshift($links, ['label' => 'Free Agency', 'url' => 'modules.php?name=FreeAgency', 'badge' => 'LIVE']);
            }

            return [
                'icon' => '<img src="/ibl5/images/logo/new' . $this->teamId . '.png" alt="Team Logo" class="w-6 h-6 object-contain">',
                'links' => $links,
            ];
        } elseif ($this->currentLeague === 'olympics') {
            return [
                'icon' => '<img src="/ibl5/images/logo/new' . $this->teamId . '.png" alt="Team Logo" class="w-6 h-6 object-contain">',
                'links' => [
                    ['label' => 'Depth Chart Entry', 'url' => 'modules.php?name=DepthChartEntry'],
                    ['label' => 'Activity Tracker', 'url' => 'modules.php?name=ActivityTracker'],
                ]
            ];
        }

        return null;
    }

    /**
     * Render a dropdown link item with stagger animation class
     *
     * @param array{label?: string, url?: string, external?: bool, badge?: string, rawHtml?: string} $link
     */
    private function renderDropdownLink(array $link): string
    {
        if (isset($link['rawHtml'])) {
            return '<span class="nav-dropdown-item block px-4 py-2.5 text-base font-display text-gray-300 border-l-2 border-transparent">'
                . $link['rawHtml']
                . '</span>';
        }

        /** @var string $label */
        $label = HtmlSanitizer::safeHtmlOutput($link['label'] ?? '');
        /** @var string $url */
        $url = HtmlSanitizer::safeHtmlOutput($link['url'] ?? '');
        $external = $link['external'] ?? false;
        $badge = $link['badge'] ?? null;

        $target = $external ? ' target="_blank" rel="noopener noreferrer"' : '';
        $externalIcon = $external ? ' <svg class="w-3 h-3 opacity-40 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>' : '';
        /** @var string $safeBadge */
        $safeBadge = $badge !== null ? HtmlSanitizer::safeHtmlOutput($badge) : '';
        $badgeHtml = $badge !== null ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-base font-bold bg-accent-500 text-white ml-2 tracking-wide">' . $safeBadge . '</span>' : '';

        return '<a href="' . $url . '"' . $target . ' class="nav-dropdown-item block px-4 py-2.5 text-base font-display text-gray-300 hover:text-white hover:bg-white/5 transition-all duration-150 border-l-2 border-transparent hover:border-accent-500">'
            . '<span class="flex items-center justify-between">'
            . '<span>' . $label . $badgeHtml . '</span>'
            . $externalIcon
            . '</span>'
            . '</a>';
    }

    /**
     * Render a desktop dropdown menu
     *
     * @param string $title Menu title
     * @param array{links: list<array{label?: string, url?: string, external?: bool, badge?: string, rawHtml?: string}>, icon?: string} $data Menu data with links and optional icon
     * @param bool $includeLoginForm Whether to include login form at top (for Login menu when logged out)
     * @param bool $includeLeagueSwitcher Whether to include league switcher at bottom
     * @param bool $alignRight Whether to align dropdown to the right edge (for rightmost menu items)
     */
    private function renderDesktopDropdown(string $title, array $data, bool $includeLoginForm = false, bool $includeLeagueSwitcher = false, bool $alignRight = false): string
    {
        $links = $data['links'];
        $icon = $data['icon'] ?? '';

        $html = '<div class="relative group">';
        $html .= '<button class="flex items-center gap-2 px-3 py-2.5 text-lg font-semibold font-display text-gray-300 hover:text-white transition-colors duration-200">';
        if ($icon !== '') {
            $html .= '<span class="text-accent-500 group-hover:text-accent-400 transition-colors">' . $icon . '</span>';
        }
        /** @var string $safeTitle */
        $safeTitle = HtmlSanitizer::safeHtmlOutput($title);
        $html .= '<span>' . $safeTitle . '</span>';
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
            . '<label class="block text-base font-semibold tracking-widest uppercase text-gray-500 mb-2">League</label>'
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
     *
     * @param string $title Menu title
     * @param array{links: list<array{label?: string, url?: string, external?: bool, badge?: string, rawHtml?: string}>, icon?: string} $data Menu data with links and optional icon
     * @param int $index Stagger animation index
     * @param bool $includeLoginForm Whether to include login form at top (for Login menu when logged out)
     * @param bool $includeLeagueSwitcher Whether to include league switcher at bottom
     */
    private function renderMobileDropdown(string $title, array $data, int $index, bool $includeLoginForm = false, bool $includeLeagueSwitcher = false): string
    {
        $links = $data['links'];
        $icon = $data['icon'] ?? '';

        $html = '<div class="mobile-section">';
        $html .= '<button class="mobile-dropdown-btn w-full flex items-center justify-between px-5 py-3.5 text-white hover:bg-white/5 transition-colors">';
        $html .= '<span class="flex items-center gap-3">';
        if ($icon !== '') {
            $html .= '<span class="text-accent-500">' . $icon . '</span>';
        }
        /** @var string $safeMobileTitle */
        $safeMobileTitle = HtmlSanitizer::safeHtmlOutput($title);
        $html .= '<span class="font-display text-lg font-semibold">' . $safeMobileTitle . '</span>';
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
            if (isset($link['rawHtml'])) {
                $html .= '<span class="block px-5 py-3 pl-14 text-base font-display text-gray-400 border-l-2 border-transparent">'
                    . $link['rawHtml']
                    . '</span>';
                continue;
            }

            /** @var string $label */
            $label = HtmlSanitizer::safeHtmlOutput($link['label'] ?? '');
            /** @var string $url */
            $url = HtmlSanitizer::safeHtmlOutput($link['url'] ?? '');
            $external = $link['external'] ?? false;
            $badge = $link['badge'] ?? null;

            $target = $external ? ' target="_blank" rel="noopener noreferrer"' : '';
            $externalIcon = $external ? ' <svg class="w-3 h-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>' : '';
            /** @var string $safeMobileBadge */
            $safeMobileBadge = $badge !== null ? HtmlSanitizer::safeHtmlOutput($badge) : '';
            $badgeHtml = $badge !== null ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-base font-bold bg-accent-500 text-white ml-2">' . $safeMobileBadge . '</span>' : '';

            $html .= '<a href="' . $url . '"' . $target . ' class="flex items-center justify-between px-5 py-3 pl-14 text-base font-display text-gray-400 hover:text-white hover:bg-white/5 border-l-2 border-transparent hover:border-accent-500 transition-all">'
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
            . '<label class="block text-base font-semibold tracking-widest uppercase text-gray-500 mb-2">League</label>'
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
     * Render the desktop Teams mega-menu dropdown with 2x2 conference/division grid
     */
    private function renderDesktopTeamsDropdown(): string
    {
        if ($this->teamsData === null) {
            return '';
        }

        $icon = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="1.5"/><path stroke-linecap="round" stroke-width="1.5" d="M12 2v20M2 12h20"/><path stroke-width="1.5" d="M4.5 4.5C8 8 8 16 4.5 19.5M19.5 4.5C16 8 16 16 19.5 19.5"/></svg>';

        $html = '<div class="group">';
        $html .= '<button class="flex items-center gap-2 px-3 py-2.5 text-lg font-semibold font-display text-gray-300 hover:text-white transition-colors duration-200">';
        $html .= '<span class="text-accent-500 group-hover:text-accent-400 transition-colors">' . $icon . '</span>';
        $html .= '<span>Teams</span>';
        $html .= '<svg class="w-3 h-3 opacity-50 group-hover:opacity-100 transition-all duration-200 group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>';
        $html .= '</button>';

        // Wider dropdown for the 2-column grid
        $html .= '<div class="absolute -right-2 top-full pt-1 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">';
        $html .= '<div class="min-w-[580px] bg-navy-800/95 backdrop-blur-xl rounded-lg shadow-2xl shadow-black/30 border border-white/10 overflow-hidden">';

        // 2-column grid: Western on left, Eastern on right
        $html .= '<div class="grid grid-cols-2 gap-x-8 p-4">';

        // Column order: Western (Midwest, Pacific), Eastern (Atlantic, Central)
        $conferenceOrder = ['Western', 'Eastern'];
        foreach ($conferenceOrder as $conference) {
            $html .= '<div>';
            /** @var string $safeConference */
            $safeConference = HtmlSanitizer::safeHtmlOutput($conference);
            $html .= '<div class="uppercase font-display text-xs tracking-wider text-accent-400 mb-3">' . $safeConference . ' Conference</div>';

            $divisions = $this->teamsData[$conference] ?? [];
            ksort($divisions); // Alphabetical: Atlantic/Central, Midwest/Pacific
            $divIndex = 0;
            foreach ($divisions as $division => $teams) {
                if ($divIndex > 0) {
                    $html .= '<div class="mt-3"></div>';
                }
                /** @var string $safeDivision */
                $safeDivision = HtmlSanitizer::safeHtmlOutput($division);
                $html .= '<div class="uppercase font-display text-xs tracking-wider text-gray-400 mb-1.5">' . $safeDivision . '</div>';
                foreach ($teams as $team) {
                    $teamId = $team['teamid'];
                    /** @var string $teamName */
                    $teamName = HtmlSanitizer::safeHtmlOutput($team['team_city'] . ' ' . $team['team_name']);
                    $html .= '<a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $teamId . '" class="nav-dropdown-item flex items-center gap-2 px-2 py-1.5 text-sm font-display text-gray-300 hover:text-white hover:bg-white/5 rounded transition-all duration-150">';
                    $html .= '<img src="images/logo/new' . $teamId . '.png" alt="" class="w-6 h-6 object-contain" loading="lazy">';
                    $html .= '<span>' . $teamName . '</span>';
                    $html .= '</a>';
                }
                $divIndex++;
            }
            $html .= '</div>';
        }

        $html .= '</div>'; // close grid
        $html .= '</div></div></div>';
        return $html;
    }

    /**
     * Render the mobile Teams collapsible section with division sub-headers
     */
    private function renderMobileTeamsDropdown(): string
    {
        if ($this->teamsData === null) {
            return '';
        }

        $icon = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="1.5"/><path stroke-linecap="round" stroke-width="1.5" d="M12 2v20M2 12h20"/><path stroke-width="1.5" d="M4.5 4.5C8 8 8 16 4.5 19.5M19.5 4.5C16 8 16 16 19.5 19.5"/></svg>';

        $html = '<div class="mobile-section">';
        $html .= '<button class="mobile-dropdown-btn w-full flex items-center justify-between px-5 py-3.5 text-white hover:bg-white/5 transition-colors">';
        $html .= '<span class="flex items-center gap-3">';
        $html .= '<span class="text-accent-500">' . $icon . '</span>';
        $html .= '<span class="font-display text-lg font-semibold">Teams</span>';
        $html .= '</span>';
        $html .= '<svg class="dropdown-arrow w-4 h-4 text-gray-500 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>';
        $html .= '</button>';

        $html .= '<div class="hidden bg-black/20">';

        // Determine user's conference/division to list them first
        $userConference = null;
        $userDivision = null;
        if ($this->teamId !== null) {
            foreach ($this->teamsData as $conf => $divisions) {
                foreach ($divisions as $div => $teams) {
                    foreach ($teams as $team) {
                        if ((int)$team['teamid'] === $this->teamId) {
                            $userConference = $conf;
                            $userDivision = $div;
                            break 3;
                        }
                    }
                }
            }
        }

        // Order conferences: user's conference first, then the other
        $conferenceOrder = array_keys($this->teamsData);
        sort($conferenceOrder); // Alphabetical baseline: Eastern, Western
        if ($userConference !== null) {
            $conferenceOrder = array_values(array_unique(
                array_merge([$userConference], $conferenceOrder)
            ));
        }

        // Conference -> Division -> Teams
        foreach ($conferenceOrder as $conference) {
            $html .= '<div class="px-5 pt-3 pb-1">';
            /** @var string $safeMobileConf */
            $safeMobileConf = HtmlSanitizer::safeHtmlOutput($conference);
            $html .= '<div class="uppercase font-display text-xs tracking-wider text-accent-400">' . $safeMobileConf . ' Conference</div>';
            $html .= '</div>';

            $divisions = $this->teamsData[$conference] ?? [];
            ksort($divisions); // Alphabetical baseline
            // If user's division is in this conference, move it first
            if ($userConference === $conference && $userDivision !== null && isset($divisions[$userDivision])) {
                $userDiv = [$userDivision => $divisions[$userDivision]];
                unset($divisions[$userDivision]);
                $divisions = $userDiv + $divisions;
            }
            foreach ($divisions as $division => $teams) {
                $html .= '<div class="px-5 pt-2 pb-1">';
                /** @var string $safeMobileDiv */
                $safeMobileDiv = HtmlSanitizer::safeHtmlOutput($division);
                $html .= '<div class="uppercase font-display text-xs tracking-wider text-gray-400">' . $safeMobileDiv . '</div>';
                $html .= '</div>';
                foreach ($teams as $team) {
                    $teamId = $team['teamid'];
                    /** @var string $teamName */
                    $teamName = HtmlSanitizer::safeHtmlOutput($team['team_city'] . ' ' . $team['team_name']);
                    $html .= '<a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $teamId . '" class="flex items-center gap-2.5 px-5 py-2.5 pl-10 text-base font-display text-gray-400 hover:text-white hover:bg-white/5 border-l-2 border-transparent hover:border-accent-500 transition-all">';
                    $html .= '<img src="images/logo/new' . $teamId . '.png" alt="" class="w-6 h-6 object-contain" loading="lazy">';
                    $html .= '<span>' . $teamName . '</span>';
                    $html .= '</a>';
                }
            }
        }

        $html .= '</div></div>';
        return $html;
    }

    /**
     * Render the localhost/production switch button for the developer.
     * Absolutely positioned so it has zero impact on layout.
     */
    private function renderDevSwitch(): string
    {
        if ($this->username !== 'A-Jay' || $this->serverName === null || $this->requestUri === null) {
            return '';
        }

        if ($this->serverName !== 'localhost') {
            $url = 'http://localhost' . $this->requestUri;
            $title = 'Switch to localhost';
        } else {
            $url = 'https://www.iblhoops.net' . $this->requestUri;
            $title = 'Switch to production';
        }

        /** @var string $safeUrl */
        $safeUrl = HtmlSanitizer::safeHtmlOutput($url);
        /** @var string $safeTitle */
        $safeTitle = HtmlSanitizer::safeHtmlOutput($title);

        return '<a href="' . $safeUrl . '" class="absolute left-1.5 top-1/2 -translate-y-1/2 z-10 w-10 h-10 flex items-center justify-center rounded-full opacity-40 hover:opacity-100 transition-opacity duration-200 overflow-visible" title="' . $safeTitle . '" style="color: #0EA5E9; contain: layout;">'
            . '<svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">'
            . '<circle cx="12" cy="12" r="10"/>'
            . '<ellipse cx="12" cy="12" rx="4" ry="10"/>'
            . '<path d="M2 12h20"/>'
            . '<path d="M5 7h14"/>'
            . '<path d="M5 17h14"/>'
            . '</svg>'
            . '</a>';
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
            <!-- Background - solid opaque navy matching menus -->
            <div class="absolute inset-0" style="background: #1e293b;"></div>
            <!-- Bottom accent line -->
            <div class="absolute bottom-0 left-0 right-0 h-[1px] bg-gradient-to-r from-transparent via-accent-500/50 to-transparent"></div>

            <?= $this->renderDevSwitch() ?>

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
                            <span class="text-base tracking-[0.2em] text-accent-500 font-semibold uppercase">Sim League</span>
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

                        <!-- Teams + My Team wrapper (positioning context for Teams mega-menu) -->
                        <div class="relative flex items-center">
                            <?= $this->renderDesktopTeamsDropdown() ?>

                            <?php if ($myTeamMenu !== null): ?>
                                <?= $this->renderDesktopDropdown('My Team', $myTeamMenu) ?>
                            <?php endif; ?>
                        </div>

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

                        <!-- Mobile view toggle (visible only in forced desktop mode) -->
                        <button id="mobile-view-toggle" class="hidden w-10 h-10 flex items-center justify-center text-white hover:bg-white/10 rounded-lg transition-colors ml-1" aria-label="Switch to mobile view" title="Switch to mobile view">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/></svg>
                        </button>
                    </div>

                    <!-- Mobile controls -->
                    <div class="lg:hidden flex items-center gap-1">
                        <!-- Desktop view toggle -->
                        <button id="desktop-view-toggle" class="relative w-10 h-10 flex items-center justify-center text-white hover:bg-white/10 rounded-lg transition-colors" aria-label="Switch to desktop view" title="Switch to desktop view">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25A2.25 2.25 0 015.25 3h13.5A2.25 2.25 0 0121 5.25z"/></svg>
                        </button>

                        <!-- Hamburger button -->
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

        <!-- Mobile menu overlay -->
        <div id="nav-overlay" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-40 lg:hidden transition-opacity duration-300"></div>

        <!-- Mobile menu -->
        <nav id="nav-mobile-menu" class="fixed top-[71px] right-0 bottom-0 w-[300px] max-w-[85vw] z-50 transform translate-x-full transition-transform duration-300 ease-out lg:hidden">
            <!-- Background - solid opaque navy matching dropdown menus -->
            <div class="absolute inset-0" style="background: #1e293b;"></div>
            <!-- Left accent line -->
            <div class="absolute top-0 left-0 bottom-0 w-[1px] bg-gradient-to-b from-accent-500/50 via-accent-500/20 to-transparent"></div>

            <div class="relative h-full flex flex-col mobile-menu-scroll overflow-y-auto">
                <!-- User greeting -->
                <?php if ($this->isLoggedIn && $this->username !== null): ?>
                    <div class="mobile-section px-5 py-4 border-b border-white/5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-accent-500/20 flex items-center justify-center">
                                <svg class="w-5 h-5 text-accent-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wide">Welcome back</div>
                                <?php /** @var string $safeUsername */ $safeUsername = HtmlSanitizer::safeHtmlOutput($this->username); ?>
                                <div class="text-white font-semibold"><?= $safeUsername ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- My Team section first for thumb reachability (if user has a team) -->
                <?php $index = 2; ?>
                <?php if ($myTeamMenu !== null): ?>
                    <?= $this->renderMobileDropdown('My Team', $myTeamMenu, $index++) ?>
                <?php endif; ?>

                <!-- Teams mega-menu -->
                <?= $this->renderMobileTeamsDropdown() ?>

                <!-- Menu sections -->
                <?php foreach ($menus as $title => $menu): ?>
                    <?= $this->renderMobileDropdown(
                        $title,
                        $menu,
                        $index++,
                        false, // no login form
                        $title === 'Season' // include league switcher only for Season menu
                    ) ?>
                <?php endforeach; ?>

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
        return (string) ob_get_clean();
    }
}
