<?php

declare(strict_types=1);

namespace RecordHolders;

use Security\HtmlSanitizer;

/**
 * Renders the shared record-category table scaffold used by all per-section renderers.
 */
final class RecordTableRenderer
{
    /** @var array<string, string> */
    private const STAT_LABELS = [
        'Most Points' => 'Pts',
        'Most Rebounds' => 'Reb',
        'Most Assists' => 'Ast',
        'Most Steals' => 'Stl',
        'Most Blocks' => 'Blk',
        'Most Turnovers' => 'TO',
        'Most 3-Pointers' => '3PM',
        'Most Three-Pointers' => '3PM',
        'Most Minutes' => 'Min',
        'Highest Scoring Average' => 'PPG',
        'Highest Rebounding Average' => 'RPG',
        'Highest Assists Average' => 'APG',
        'Highest Steals Average' => 'SPG',
        'Highest Blocks Average' => 'BPG',
        'Highest 3-Point' => '3P%',
        'Highest Three-Point' => '3P%',
        'Highest Field Goal' => 'FG%',
        'Highest Free Throw' => 'FT%',
        'Best Season Record' => 'Record',
        'Worst Season Record' => 'Record',
        'Most Wins' => 'Wins',
        'Most Losses' => 'Losses',
        'Most Playoff Appearances' => 'Apps',
        'Most Championship' => 'Titles',
        'Longest Winning Streak' => 'Wins',
        'Longest Losing Streak' => 'Losses',
    ];

    /**
     * Render the shared category-block scaffold: a `.record-category` wrapper
     * with a heading and a `.record-table` whose layout is fixed by a modifier
     * class. Every per-category block differs only in that modifier, its
     * colgroup, its header cells, and its pre-rendered body rows.
     */
    public function renderCategoryTable(string $category, string $modifierClass, string $colgroup, string $thead, string $rows): string
    {
        $output = '<div class="record-category">';
        $output .= $this->renderCategoryHeading($category);
        $output .= '<table class="ibl-data-table record-table ibl-table-subheading ' . $modifierClass . '" data-no-responsive>';
        $output .= '<colgroup>' . $colgroup . '</colgroup>';
        $output .= '<thead><tr>' . $thead . '</tr></thead>';
        $output .= '<tbody>' . $rows . '</tbody></table></div>';

        return $output;
    }

    /**
     * Render a category heading with accent left border.
     */
    public function renderCategoryHeading(string $category): string
    {
        $safeCategory = HtmlSanitizer::safeHtmlOutput($category);
        return '<h3 class="record-category__title">' . $safeCategory . '</h3>';
    }

    /**
     * Get the stat-specific column label for a category name.
     *
     * Maps category names like "Most Points in a Single Game" to abbreviations like "Pts".
     */
    public function getStatColumnLabel(string $category): string
    {
        foreach (self::STAT_LABELS as $prefix => $label) {
            if (str_starts_with($category, $prefix)) {
                return $label;
            }
        }

        return 'Amount';
    }
}
