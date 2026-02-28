<?php

declare(strict_types=1);

namespace Navigation\Views;

use Utilities\HtmlSanitizer;

/**
 * Renders the Teams mega-menu/list for both desktop and mobile navigation.
 *
 * @phpstan-import-type NavTeamsData from \Navigation\NavigationConfig
 */
class TeamsDropdownView
{
    /**
     * Render the desktop Teams mega-menu with 2x2 conference/division grid.
     *
     * @param NavTeamsData $teamsData
     */
    public function renderDesktop(array $teamsData): string
    {
        $icon = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="1.5"/><path stroke-linecap="round" stroke-width="1.5" d="M12 2v20M2 12h20"/><path stroke-width="1.5" d="M4.5 4.5C8 8 8 16 4.5 19.5M19.5 4.5C16 8 16 16 19.5 19.5"/></svg>';

        ob_start();
        ?>
        <div class="group">
            <button class="flex items-center gap-2 px-3 py-2.5 text-lg font-semibold font-display text-gray-300 hover:text-white transition-colors duration-200">
                <span class="text-accent-500 group-hover:text-accent-400 transition-colors"><?= $icon ?></span>
                <span>Teams</span>
                <svg class="w-3 h-3 opacity-50 group-hover:opacity-100 transition-all duration-200 group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div class="absolute -right-2 top-full pt-1 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                <div class="min-w-[580px] bg-navy-800/95 backdrop-blur-xl rounded-lg shadow-2xl shadow-black/30 border border-white/10 overflow-hidden">
                    <div class="grid grid-cols-2 gap-x-8 p-4">
                        <?php
                        $conferenceOrder = ['Western', 'Eastern'];
                        foreach ($conferenceOrder as $conference):
                            ?>
                            <div>
                                <div class="uppercase font-display text-xs tracking-wider text-accent-400 mb-3"><?= HtmlSanitizer::e($conference) ?> Conference</div>
                                <?php
                                $divisions = $teamsData[$conference] ?? [];
                                ksort($divisions);
                                $divIndex = 0;
                                foreach ($divisions as $division => $teams):
                                    ?>
                                    <?php if ($divIndex > 0): ?><div class="mt-3"></div><?php endif; ?>
                                    <div class="uppercase font-display text-xs tracking-wider text-gray-400 mb-1.5"><?= HtmlSanitizer::e($division) ?></div>
                                    <?php foreach ($teams as $team): ?>
                                        <a href="modules.php?name=Team&amp;op=team&amp;teamID=<?= $team['teamid'] ?>" class="nav-dropdown-item flex items-center gap-2 px-2 py-1.5 text-sm font-display text-gray-300 hover:text-white hover:bg-white/5 rounded transition-all duration-150">
                                            <span class="inline-flex items-center justify-center nav-team-logo-container"><img src="images/logo/new<?= $team['teamid'] ?>.png" alt="" class="nav-team-logo-img" loading="lazy"></span>
                                            <span><?= HtmlSanitizer::e($team['team_city'] . ' ' . $team['team_name']) ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                    <?php $divIndex++; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the mobile Teams collapsible section with division sub-headers.
     *
     * @param NavTeamsData $teamsData
     */
    public function renderMobile(array $teamsData, ?int $userTeamId): string
    {
        $icon = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="1.5"/><path stroke-linecap="round" stroke-width="1.5" d="M12 2v20M2 12h20"/><path stroke-width="1.5" d="M4.5 4.5C8 8 8 16 4.5 19.5M19.5 4.5C16 8 16 16 19.5 19.5"/></svg>';

        // Determine user's conference/division to list them first
        $userConference = null;
        $userDivision = null;
        if ($userTeamId !== null) {
            foreach ($teamsData as $conf => $divisions) {
                foreach ($divisions as $div => $teams) {
                    foreach ($teams as $team) {
                        if ($team['teamid'] === $userTeamId) {
                            $userConference = $conf;
                            $userDivision = $div;
                            break 3;
                        }
                    }
                }
            }
        }

        // Order conferences: user's conference first, then the other
        $conferenceOrder = array_keys($teamsData);
        sort($conferenceOrder);
        if ($userConference !== null) {
            $conferenceOrder = array_values(array_unique(
                array_merge([$userConference], $conferenceOrder)
            ));
        }

        ob_start();
        ?>
        <div class="mobile-section">
            <button class="mobile-dropdown-btn w-full flex items-center justify-between px-5 py-3.5 text-white hover:bg-white/5 transition-colors">
                <span class="flex items-center gap-3">
                    <span class="text-accent-500"><?= $icon ?></span>
                    <span class="font-display text-lg font-semibold">Teams</span>
                </span>
                <svg class="dropdown-arrow w-4 h-4 text-gray-500 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div class="hidden bg-black/20">
                <?php foreach ($conferenceOrder as $conference): ?>
                    <div class="px-5 pt-3 pb-1">
                        <div class="uppercase font-display text-xs tracking-wider text-accent-400"><?= HtmlSanitizer::e($conference) ?> Conference</div>
                    </div>
                    <?php
                    $divisions = $teamsData[$conference] ?? [];
                    ksort($divisions);
                    if ($userConference === $conference && $userDivision !== null && isset($divisions[$userDivision])) {
                        $userDiv = [$userDivision => $divisions[$userDivision]];
                        unset($divisions[$userDivision]);
                        $divisions = $userDiv + $divisions;
                    }
                    foreach ($divisions as $division => $teams):
                        ?>
                        <div class="px-5 pt-2 pb-1">
                            <div class="uppercase font-display text-xs tracking-wider text-gray-400"><?= HtmlSanitizer::e($division) ?></div>
                        </div>
                        <?php foreach ($teams as $team): ?>
                            <a href="modules.php?name=Team&amp;op=team&amp;teamID=<?= $team['teamid'] ?>" class="flex items-center gap-2.5 px-5 py-2.5 pl-10 text-base font-display text-gray-400 hover:text-white hover:bg-white/5 border-l-2 border-transparent hover:border-accent-500 transition-all">
                                <span class="inline-flex items-center justify-center nav-team-logo-container"><img src="images/logo/new<?= $team['teamid'] ?>.png" alt="" class="nav-team-logo-img" loading="lazy"></span>
                                <span><?= HtmlSanitizer::e($team['team_city'] . ' ' . $team['team_name']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
