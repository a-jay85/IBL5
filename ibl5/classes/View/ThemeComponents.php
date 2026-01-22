<?php

declare(strict_types=1);

namespace View;

/**
 * Theme Components
 *
 * Provides mobile-first Tailwind CSS components for the IBL Court Side design system.
 * Replaces legacy PHP-Nuke theme functions with modern, responsive HTML.
 */
class ThemeComponents
{
    /**
     * Open a card container (replaces OpenTable)
     *
     * @param string|null $title Optional title for the card
     * @param string $classes Additional CSS classes
     */
    public static function openCard(?string $title = null, string $classes = ''): void
    {
        $allClasses = trim("ibl-card {$classes}");

        echo "<div class=\"{$allClasses}\">\n";

        if ($title !== null) {
            echo "<div class=\"ibl-card-header\">\n";
            echo "<h3 class=\"ibl-card-title\">" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</h3>\n";
            echo "</div>\n";
        }
    }

    /**
     * Close a card container (replaces CloseTable)
     */
    public static function closeCard(): void
    {
        echo "</div>\n";
    }

    /**
     * Render a sidebar box (replaces themesidebox)
     *
     * @param string $title Box title
     * @param string $content Box content (HTML)
     * @param string $classes Additional CSS classes
     */
    public static function sidebarBox(string $title, string $content, string $classes = ''): string
    {
        $allClasses = trim("ibl-sidebar-box {$classes}");

        $html = "<div class=\"{$allClasses}\">\n";
        $html .= "<div class=\"ibl-sidebar-box-title\">" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</div>\n";
        $html .= "<div class=\"ibl-sidebar-box-content\">{$content}</div>\n";
        $html .= "</div>\n";

        return $html;
    }

    /**
     * Open a responsive table wrapper with horizontal scroll on mobile
     *
     * @param string $classes Additional CSS classes
     */
    public static function openTableWrapper(string $classes = ''): void
    {
        $allClasses = trim("ibl-table-wrapper {$classes}");
        echo "<div class=\"{$allClasses}\">\n";
    }

    /**
     * Close a responsive table wrapper
     */
    public static function closeTableWrapper(): void
    {
        echo "</div>\n";
    }

    /**
     * Open a responsive table container
     *
     * @param string $classes Additional CSS classes (e.g., 'ibl-table-striped', 'ibl-table-compact')
     */
    public static function openTable(string $classes = ''): void
    {
        $allClasses = trim("ibl-table {$classes}");
        echo "<div class=\"ibl-table-wrapper\">\n";
        echo "<table class=\"{$allClasses}\">\n";
    }

    /**
     * Close a responsive table container
     */
    public static function closeTable(): void
    {
        echo "</table>\n";
        echo "</div>\n";
    }

    /**
     * Render a table header row
     *
     * @param array<string> $headers Column headers
     * @param array<string> $classes Per-column classes (optional, use 'stat' for centered stat columns)
     */
    public static function tableHeader(array $headers, array $classes = []): void
    {
        echo "<thead>\n<tr>\n";
        foreach ($headers as $index => $header) {
            $colClass = $classes[$index] ?? '';
            echo "<th class=\"{$colClass}\">" . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . "</th>\n";
        }
        echo "</tr>\n</thead>\n<tbody>\n";
    }

    /**
     * Close table body
     */
    public static function tableBodyEnd(): void
    {
        echo "</tbody>\n";
    }

    /**
     * Render a table row
     *
     * @param array<string> $cells Cell contents (HTML allowed)
     * @param array<string> $classes Per-cell classes (optional)
     */
    public static function tableRow(array $cells, array $classes = []): void
    {
        echo "<tr>\n";
        foreach ($cells as $index => $cell) {
            $cellClass = $classes[$index] ?? '';
            echo "<td class=\"{$cellClass}\">{$cell}</td>\n";
        }
        echo "</tr>\n";
    }

    /**
     * Render an alert/message box
     *
     * @param string $message The message content
     * @param string $type Type of alert: 'info', 'success', 'warning', 'error'
     */
    public static function alert(string $message, string $type = 'info'): void
    {
        $typeClasses = [
            'info' => 'ibl-badge-primary bg-ibl-primary/10 border-ibl-primary/20',
            'success' => 'ibl-badge-success bg-ibl-success/10 border-ibl-success/20',
            'warning' => 'ibl-badge-warning bg-ibl-warning/10 border-ibl-warning/20',
            'error' => 'ibl-badge-danger bg-ibl-danger/10 border-ibl-danger/20',
        ];

        $colorClasses = $typeClasses[$type] ?? $typeClasses['info'];

        echo "<div class=\"{$colorClasses} border rounded-card px-4 py-3 mb-4\" role=\"alert\">\n";
        echo "<p>{$message}</p>\n";
        echo "</div>\n";
    }

    /**
     * Render a button
     *
     * @param string $text Button text
     * @param string $href URL (if link button) or empty for submit button
     * @param string $variant Button variant: 'primary', 'secondary', 'accent', 'ghost'
     * @param string $size Button size: 'sm', 'md' (default), 'lg'
     * @param string $classes Additional CSS classes
     */
    public static function button(
        string $text,
        string $href = '',
        string $variant = 'primary',
        string $size = 'md',
        string $classes = ''
    ): void {
        $variantClasses = [
            'primary' => 'ibl-btn-primary',
            'secondary' => 'ibl-btn-secondary',
            'accent' => 'ibl-btn-accent',
            'ghost' => 'ibl-btn-ghost',
        ];

        $sizeClasses = [
            'sm' => 'ibl-btn-sm',
            'md' => '',
            'lg' => 'ibl-btn-lg',
        ];

        $btnClass = $variantClasses[$variant] ?? 'ibl-btn-primary';
        $sizeClass = $sizeClasses[$size] ?? '';
        $allClasses = trim("ibl-btn {$btnClass} {$sizeClass} {$classes}");

        if ($href !== '') {
            echo "<a href=\"" . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . "\" class=\"{$allClasses}\">";
            echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            echo "</a>";
        } else {
            echo "<button type=\"submit\" class=\"{$allClasses}\">";
            echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            echo "</button>";
        }
    }

    /**
     * Render a form input field
     *
     * @param string $name Input name
     * @param string $type Input type
     * @param string $value Current value
     * @param string $placeholder Placeholder text
     * @param string $classes Additional CSS classes
     */
    public static function input(
        string $name,
        string $type = 'text',
        string $value = '',
        string $placeholder = '',
        string $classes = ''
    ): void {
        $allClasses = trim("ibl-input {$classes}");

        echo "<input type=\"{$type}\" name=\"" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "\"";
        echo " value=\"" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "\"";

        if ($placeholder !== '') {
            echo " placeholder=\"" . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . "\"";
        }

        echo " class=\"{$allClasses}\">";
    }

    /**
     * Render a select dropdown
     *
     * @param string $name Select name
     * @param array<string, string> $options Key-value pairs for options
     * @param string $selected Currently selected value
     * @param string $classes Additional CSS classes
     */
    public static function select(
        string $name,
        array $options,
        string $selected = '',
        string $classes = ''
    ): void {
        $allClasses = trim("ibl-select {$classes}");

        echo "<select name=\"" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "\" class=\"{$allClasses}\">\n";

        foreach ($options as $value => $label) {
            $selectedAttr = ($value === $selected) ? ' selected' : '';
            echo "<option value=\"" . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . "\"{$selectedAttr}>";
            echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            echo "</option>\n";
        }

        echo "</select>";
    }

    /**
     * Render a navigation link
     *
     * @param string $text Link text
     * @param string $href URL
     * @param bool $active Whether link is currently active
     * @param string $classes Additional CSS classes
     */
    public static function navLink(string $text, string $href, bool $active = false, string $classes = ''): void
    {
        $activeClasses = $active ? 'active' : '';
        $allClasses = trim("ibl-nav-link {$activeClasses} {$classes}");

        echo "<a href=\"" . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . "\" class=\"{$allClasses}\">";
        echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        echo "</a>\n";
    }

    /**
     * Start a navigation group
     *
     * @param string $title Group title
     */
    public static function navGroupStart(string $title): void
    {
        echo "<div class=\"ibl-nav-group\">\n";
        echo "<div class=\"ibl-nav-group-title\">" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</div>\n";
    }

    /**
     * End a navigation group
     */
    public static function navGroupEnd(): void
    {
        echo "</div>\n";
    }

    /**
     * Render a stat box for displaying a single statistic
     *
     * @param string $value The stat value
     * @param string $label The stat label
     * @param string $classes Additional CSS classes
     */
    public static function statBox(string $value, string $label, string $classes = ''): void
    {
        $allClasses = trim("ibl-stat-box {$classes}");

        echo "<div class=\"{$allClasses}\">\n";
        echo "<div class=\"ibl-stat-value\">" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "</div>\n";
        echo "<div class=\"ibl-stat-label\">" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</div>\n";
        echo "</div>\n";
    }

    /**
     * Render a hero stat (large, prominent stat display)
     *
     * @param string $value The stat value
     * @param string $label The stat label
     * @param string $classes Additional CSS classes
     */
    public static function statHero(string $value, string $label, string $classes = ''): void
    {
        $allClasses = trim("ibl-stat-hero {$classes}");

        echo "<div class=\"{$allClasses}\">\n";
        echo "<div class=\"ibl-stat-hero-value\">" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "</div>\n";
        echo "<div class=\"ibl-stat-hero-label\">" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</div>\n";
        echo "</div>\n";
    }

    /**
     * Start a stat grid (responsive grid of stat boxes)
     *
     * @param string $classes Additional CSS classes
     */
    public static function statGridStart(string $classes = ''): void
    {
        $allClasses = trim("ibl-stat-grid {$classes}");
        echo "<div class=\"{$allClasses}\">\n";
    }

    /**
     * End a stat grid
     */
    public static function statGridEnd(): void
    {
        echo "</div>\n";
    }

    /**
     * Render a badge/tag
     *
     * @param string $text Badge text
     * @param string $variant Badge variant: 'default', 'primary', 'success', 'danger', 'warning'
     * @param string $classes Additional CSS classes
     */
    public static function badge(string $text, string $variant = 'default', string $classes = ''): string
    {
        $variantClasses = [
            'default' => 'ibl-badge-default',
            'primary' => 'ibl-badge-primary',
            'success' => 'ibl-badge-success',
            'danger' => 'ibl-badge-danger',
            'warning' => 'ibl-badge-warning',
        ];

        $badgeClass = $variantClasses[$variant] ?? 'ibl-badge-default';
        $allClasses = trim("ibl-badge {$badgeClass} {$classes}");

        return "<span class=\"{$allClasses}\">" . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . "</span>";
    }

    /**
     * Render a mobile-friendly data card (alternative to table rows on small screens)
     *
     * @param string $title Card title (e.g., player name, team name)
     * @param string|null $subtitle Optional subtitle
     * @param array<string, string> $stats Key-value pairs of stats to display
     * @param string|null $badge Optional badge text
     * @param string $href Optional link URL
     */
    public static function dataCard(
        string $title,
        ?string $subtitle = null,
        array $stats = [],
        ?string $badge = null,
        string $href = ''
    ): void {
        echo "<div class=\"ibl-data-card\">\n";
        echo "<div class=\"ibl-data-card-header\">\n";
        echo "<div>\n";

        if ($href !== '') {
            echo "<a href=\"" . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . "\" class=\"ibl-data-card-title hover:text-ibl-primary\">";
            echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            echo "</a>\n";
        } else {
            echo "<div class=\"ibl-data-card-title\">" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</div>\n";
        }

        if ($subtitle !== null) {
            echo "<div class=\"ibl-data-card-subtitle\">" . htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') . "</div>\n";
        }

        echo "</div>\n";

        if ($badge !== null) {
            echo "<span class=\"ibl-data-card-badge\">" . htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') . "</span>\n";
        }

        echo "</div>\n";

        if (!empty($stats)) {
            echo "<div class=\"ibl-data-card-stats\">\n";
            foreach ($stats as $label => $value) {
                echo "<div class=\"ibl-data-card-stat\">\n";
                echo "<div class=\"ibl-data-card-stat-value\">" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "</div>\n";
                echo "<div class=\"ibl-data-card-stat-label\">" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</div>\n";
                echo "</div>\n";
            }
            echo "</div>\n";
        }

        echo "</div>\n";
    }

    /**
     * Start a container for data cards (mobile view alternative to tables)
     */
    public static function dataCardsStart(): void
    {
        echo "<div class=\"ibl-data-cards\">\n";
    }

    /**
     * End a data cards container
     */
    public static function dataCardsEnd(): void
    {
        echo "</div>\n";
    }

    /**
     * Render a responsive table that shows cards on mobile and table on desktop
     * This wraps both views in a container that switches based on screen size.
     *
     * @param string $classes Additional CSS classes
     */
    public static function responsiveTableStart(string $classes = ''): void
    {
        $allClasses = trim("responsive-table {$classes}");
        echo "<div class=\"{$allClasses}\">\n";
    }

    /**
     * End responsive table container
     */
    public static function responsiveTableEnd(): void
    {
        echo "</div>\n";
    }
}
