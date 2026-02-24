<?php

declare(strict_types=1);

namespace UI\Components;

use Utilities\HtmlSanitizer;

/**
 * TableViewDropdown - Dropdown <select> navigation for team page views with split stats
 *
 * Replaces TableViewSwitcher when the team page needs ~45 options across 8 categories.
 * Uses <optgroup> headers to organize Views, Location, Result, etc.
 *
 * Split values are encoded as "split:{key}" in option values; non-split values
 * are bare strings (e.g. "ratings", "total_s").
 */
class TableViewDropdown
{
    /** @var array<string, array<string, string>> Optgroup label => [value => label] */
    private array $groups;

    /** @var string Currently active value (e.g. "ratings" or "split:home") */
    private string $activeValue;

    /** @var string Base URL with plain & (HTML-encoded on output) */
    private string $baseUrl;

    /** @var string Primary team hex color (without #) */
    private string $color1;

    /** @var string Secondary team hex color (without #) */
    private string $color2;

    /**
     * @param array<string, array<string, string>> $groups Optgroup label => [value => label]
     * @param string $activeValue Currently active value
     * @param string $baseUrl Plain URL with & separators (not &amp;)
     * @param string $color1 Primary team hex color (without #)
     * @param string $color2 Secondary team hex color (without #)
     */
    public function __construct(
        array $groups,
        string $activeValue,
        string $baseUrl,
        string $color1,
        string $color2
    ) {
        $this->groups = $groups;
        $this->activeValue = $activeValue;
        $this->baseUrl = $baseUrl;
        $this->color1 = \UI\TableStyles::sanitizeColor($color1);
        $this->color2 = \UI\TableStyles::sanitizeColor($color2);
    }

    /**
     * Render the dropdown HTML
     */
    public function renderDropdown(): string
    {
        $html = '<div class="ibl-view-dropdown" style="--team-tab-bg-color: #' . $this->color1 . '; --team-tab-active-color: #' . $this->color2 . '">';
        $html .= '<select class="ibl-view-select">';

        foreach ($this->groups as $groupLabel => $options) {
            $safeGroupLabel = HtmlSanitizer::safeHtmlOutput($groupLabel);
            $html .= '<optgroup label="' . $safeGroupLabel . '">';

            foreach ($options as $value => $label) {
                $selected = ($value === $this->activeValue) ? ' selected' : '';
                $safeValue = HtmlSanitizer::safeHtmlOutput($value);
                $safeLabel = HtmlSanitizer::safeHtmlOutput($label);
                $html .= '<option value="' . $safeValue . '"' . $selected . '>' . $safeLabel . '</option>';
            }

            $html .= '</optgroup>';
        }

        $html .= '</select>';

        // Hidden noscript fallback link
        $html .= '<noscript>';
        $safeBaseUrl = HtmlSanitizer::safeHtmlOutput($this->baseUrl . '&display=ratings');
        $html .= '<a href="' . $safeBaseUrl . '">Back to Ratings</a>';
        $html .= '</noscript>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Inject dropdown as a <caption> element inside the first <table> tag
     *
     * Same pattern as TableViewSwitcher::injectCaption
     *
     * @param string $tableHtml Pre-rendered table HTML
     * @return string Table HTML with caption injected after opening <table> tag
     */
    public function wrap(string $tableHtml): string
    {
        $dropdownHtml = $this->renderDropdown();

        $result = preg_replace(
            '/(<table\b[^>]*>)/i',
            '$1<caption class="team-table-caption">' . $dropdownHtml . '</caption>',
            $tableHtml,
            1
        );

        return $result ?? $tableHtml;
    }
}
