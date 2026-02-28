<?php

declare(strict_types=1);

namespace Navigation;

use Navigation\Contracts\NavigationMenuBuilderInterface;

/**
 * Builds navigation menu data structures from NavigationConfig.
 * Contains all conditional business logic (no HTML rendering).
 *
 * @phpstan-import-type NavLink from NavigationConfig
 * @phpstan-import-type NavMenuData from NavigationConfig
 */
class NavigationMenuBuilder implements NavigationMenuBuilderInterface
{
    private NavigationConfig $config;

    public function __construct(NavigationConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @see NavigationMenuBuilderInterface::getMenuStructure()
     *
     * @return array<string, NavMenuData>
     */
    public function getMenuStructure(): array
    {
        return [
            'Season' => [
                'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
                'links' => [
                    ['label' => 'Standings', 'url' => 'modules.php?name=Standings'],
                    ['label' => 'Schedule', 'url' => 'modules.php?name=Schedule'],
                    ['label' => 'Injuries', 'url' => 'modules.php?name=Injuries'],
                    ['label' => 'Player Database', 'url' => 'modules.php?name=PlayerDatabase'],
                    ['label' => 'Projected Draft Order', 'url' => 'modules.php?name=ProjectedDraftOrder'],
                    ['label' => 'Draft Pick Locator', 'url' => 'modules.php?name=DraftPickLocator'],
                    ['label' => 'Cap Space', 'url' => 'modules.php?name=CapSpace'],
                    ['label' => 'Free Agency Preview', 'url' => 'modules.php?name=FreeAgencyPreview'],
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
                    ['label' => 'Franchise Record Book', 'url' => 'modules.php?name=FranchiseRecordBook'],
                    ['label' => 'All-Star Appearances', 'url' => 'modules.php?name=AllStarAppearances'],
                    ['label' => 'Season Leaderboards', 'url' => 'modules.php?name=SeasonLeaderboards'],
                    ['label' => 'Career Leaderboards', 'url' => 'modules.php?name=CareerLeaderboards'],
                    ['label' => 'Season Archive', 'url' => 'modules.php?name=SeasonArchive'],
                    ['label' => '1-On-1 Game', 'url' => 'modules.php?name=OneOnOneGame'],
                ],
            ],
            'Community' => [
                'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
                'links' => array_values(array_filter([
                    ['label' => 'Discord Server', 'url' => 'https://discord.com/invite/QXwBQxR', 'external' => true],
                    ['label' => 'Prime Time Football', 'url' => 'http://www.thakfu.com/ptf/index.php', 'external' => true],
                    ['label' => 'Activity Tracker', 'url' => 'modules.php?name=ActivityTracker'],
                    ['label' => 'Topics (News)', 'url' => 'modules.php?name=Topics'],
                    $this->config->isLoggedIn && $this->config->teamId !== null
                        ? ['label' => 'GM Contact List', 'url' => 'modules.php?name=GMContactList']
                        : null,
                ], static fn (mixed $item): bool => $item !== null)),
            ],
        ];
    }

    /**
     * @see NavigationMenuBuilderInterface::getMyTeamMenu()
     *
     * @return NavMenuData|null
     */
    public function getMyTeamMenu(): ?array
    {
        if (!$this->config->isLoggedIn || $this->config->teamId === null) {
            return null;
        }

        if ($this->config->currentLeague === 'ibl') {
            return $this->getIblTeamMenu();
        }

        if ($this->config->currentLeague === 'olympics') {
            return [
                'icon' => '<img src="/ibl5/images/logo/new' . $this->config->teamId . '.png" alt="Team Logo" class="w-6 h-6 object-contain">',
                'links' => [
                    ['label' => 'Depth Chart Entry', 'url' => 'modules.php?name=DepthChartEntry'],
                    ['label' => 'Activity Tracker', 'url' => 'modules.php?name=ActivityTracker'],
                ],
            ];
        }

        return null;
    }

    /** @see NavigationMenuBuilderInterface::getAccountMenu() */
    public function getAccountMenu(): array
    {
        if ($this->config->isLoggedIn) {
            return [
                ['label' => 'Logout', 'url' => 'modules.php?name=YourAccount&op=logout'],
            ];
        }

        return [
            ['label' => 'Sign Up', 'url' => 'modules.php?name=YourAccount&op=new_user'],
            ['label' => 'Forgot Password', 'url' => 'modules.php?name=YourAccount&op=pass_lost'],
        ];
    }

    /**
     * Build the IBL team menu with conditional Draft/FA/Waivers links.
     *
     * @return NavMenuData
     */
    private function getIblTeamMenu(): array
    {
        $teamId = $this->config->teamId;
        /** @var list<NavLink> $links */
        $links = [
            ['label' => 'Team Page', 'url' => 'modules.php?name=Team&op=team&teamID=' . $teamId],
            ['label' => 'Schedule', 'url' => 'modules.php?name=Schedule&teamID=' . $teamId],
            ['label' => 'Next Sim', 'url' => 'modules.php?name=NextSim', 'badge' => 'NEW'],
            ['label' => 'Depth Chart Entry', 'url' => 'modules.php?name=DepthChartEntry'],
            ['label' => 'Trading', 'url' => 'modules.php?name=Trading&op=reviewtrade'],
            ['label' => 'Voting', 'url' => 'modules.php?name=Voting'],
            ['label' => 'Draft History', 'url' => 'modules.php?name=DraftHistory&teamID=' . $teamId],
        ];

        if ($this->config->allowWaivers === 'Yes') {
            $links[] = ['rawHtml' => 'Waivers: <a href="modules.php?name=Waivers&amp;action=add">Add</a> | <a href="modules.php?name=Waivers&amp;action=waive">Waive</a>'];
        }

        if ($this->config->seasonPhase === 'Draft') {
            array_unshift($links, ['label' => 'Draft', 'url' => 'modules.php?name=Draft', 'badge' => 'LIVE']);
        } elseif ($this->config->showDraftLink === 'On') {
            array_unshift($links, ['label' => 'Draft', 'url' => 'modules.php?name=Draft']);
        }

        if ($this->config->seasonPhase === 'Free Agency') {
            array_unshift($links, ['label' => 'Free Agency', 'url' => 'modules.php?name=FreeAgency', 'badge' => 'LIVE']);
        }

        return [
            'icon' => '<img src="/ibl5/images/logo/new' . $teamId . '.png" alt="Team Logo" class="w-6 h-6 object-contain">',
            'links' => $links,
        ];
    }
}
