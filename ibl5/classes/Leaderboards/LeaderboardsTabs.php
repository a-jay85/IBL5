<?php

declare(strict_types=1);

namespace Leaderboards;

/** Tab resolution and tab-bar markup for modules.php?name=Leaderboards. */
final class LeaderboardsTabs
{
    public const SEASON = 'season';
    public const CAREER = 'career';

    /** @var array<string, string> tab key => label */
    private const LABELS = [
        self::SEASON => 'Season',
        self::CAREER => 'Career',
    ];

    /** Mirrors RecordHolders' op= whitelist: anything but the exact string 'career' is season. */
    public static function resolve(mixed $raw): string
    {
        return (is_string($raw) && $raw === self::CAREER) ? self::CAREER : self::SEASON;
    }

    public static function render(string $activeTab): string
    {
        $html = '<nav class="ibl-tabs leaderboards-tabs" aria-label="Leaderboard type">';
        foreach (self::LABELS as $key => $label) {
            $isActive = $key === $activeTab;
            $html .= '<a class="ibl-tab' . ($isActive ? ' ibl-tab--active' : '') . '"'
                . ' href="modules.php?name=Leaderboards&amp;tab=' . $key . '"'
                . ($isActive ? ' aria-current="page"' : '')
                . '>' . $label . '</a>';
        }

        return $html . '</nav>';
    }
}
