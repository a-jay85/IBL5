<?php

declare(strict_types=1);

namespace UI;

/**
 * GenericTableStyles - Generates reusable CSS styles for tables without team colors
 *
 * Provides consistent styling matching the established aesthetic:
 * - Navy gradient headers
 * - Poppins font for headers
 * - Gray-50 alternating rows
 * - Modern shadows and transitions
 */
class GenericTableStyles
{
    /**
     * Generate CSS styles for generic tables with navy theme
     *
     * @param string $tableClass CSS class name for the table
     * @return string CSS style block
     */
    public static function render(string $tableClass): string
    {
        $tableClass = self::sanitizeClassName($tableClass);

        ob_start();
        ?>
<style>
/* Modern table styling - Navy theme */
.<?= $tableClass ?> {
    font-family: var(--font-sans, 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
    border-collapse: separate;
    border-spacing: 0;
    border: none;
    border-radius: var(--radius-lg, 0.5rem);
    overflow: hidden;
    box-shadow: var(--shadow-md, 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1));
    margin: 0 auto;
    width: 100%;
    max-width: 100%;
}
.<?= $tableClass ?> thead {
    background: linear-gradient(135deg, var(--navy-800, #1e293b), var(--navy-900, #0f172a));
}
.<?= $tableClass ?> th {
    color: white;
    font-family: var(--font-display, 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif);
    font-weight: 600;
    font-size: 0.6875rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.75rem 0.5rem;
    text-align: center;
    white-space: nowrap;
}
.<?= $tableClass ?> td {
    color: var(--gray-800, #1f2937);
    font-size: 0.75rem;
    padding: 0.5rem;
    text-align: center;
}
.<?= $tableClass ?> tbody tr {
    transition: background-color 150ms ease;
}
.<?= $tableClass ?> tbody tr:nth-child(odd) {
    background-color: white;
}
.<?= $tableClass ?> tbody tr:nth-child(even) {
    background-color: var(--gray-50, #f9fafb);
}
.<?= $tableClass ?> tbody tr:hover {
    background-color: var(--gray-100, #f3f4f6);
}
.<?= $tableClass ?> a {
    color: var(--gray-800, #1f2937);
    text-decoration: none;
    font-weight: 500;
    transition: color 150ms ease;
}
.<?= $tableClass ?> a:hover {
    color: var(--accent-500, #f97316);
}
.<?= $tableClass ?> th:first-child,
.<?= $tableClass ?> td:first-child {
    padding-left: 0.75rem;
}
.<?= $tableClass ?> th:last-child,
.<?= $tableClass ?> td:last-child {
    padding-right: 0.75rem;
}
/* Text alignment utilities */
.<?= $tableClass ?> .text-left { text-align: left; }
.<?= $tableClass ?> .text-right { text-align: right; }
.<?= $tableClass ?> .text-center { text-align: center; }
/* Separator styles */
.<?= $tableClass ?> th.sep,
.<?= $tableClass ?> td.sep {
    border-right: 2px solid var(--navy-600, #475569);
    padding-right: 0.75rem;
}
.<?= $tableClass ?> th.sep + th,
.<?= $tableClass ?> th.sep + td,
.<?= $tableClass ?> td.sep + th,
.<?= $tableClass ?> td.sep + td {
    padding-left: 0.75rem;
}
.<?= $tableClass ?> th.sep-weak,
.<?= $tableClass ?> td.sep-weak {
    border-right: 1px solid var(--gray-200, #e5e7eb);
    padding-right: 0.5rem;
}
.<?= $tableClass ?> th.sep-weak + th,
.<?= $tableClass ?> th.sep-weak + td,
.<?= $tableClass ?> td.sep-weak + th,
.<?= $tableClass ?> td.sep-weak + td {
    padding-left: 0.5rem;
}
/* Highlight row */
.<?= $tableClass ?> tbody tr.highlight {
    background-color: var(--accent-100, #ffedd5) !important;
}
.<?= $tableClass ?> tbody tr.highlight:hover {
    background-color: var(--accent-200, #fed7aa) !important;
}
/* Strong text */
.<?= $tableClass ?> strong {
    font-weight: 600;
    color: var(--navy-900, #0f172a);
}
</style>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate CSS styles for compact tables (less padding)
     *
     * @param string $tableClass CSS class name for the table
     * @return string CSS style block
     */
    public static function renderCompact(string $tableClass): string
    {
        $tableClass = self::sanitizeClassName($tableClass);

        ob_start();
        ?>
<style>
/* Compact table styling - Navy theme */
.<?= $tableClass ?> {
    font-family: var(--font-sans, 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
    border-collapse: separate;
    border-spacing: 0;
    border: none;
    border-radius: var(--radius-md, 0.375rem);
    overflow: hidden;
    box-shadow: var(--shadow-sm, 0 1px 2px 0 rgb(0 0 0 / 0.05));
    margin: 0 auto;
    width: 100%;
}
.<?= $tableClass ?> thead {
    background: linear-gradient(135deg, var(--navy-800, #1e293b), var(--navy-900, #0f172a));
}
.<?= $tableClass ?> th {
    color: white;
    font-family: var(--font-display, 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif);
    font-weight: 600;
    font-size: 0.625rem;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    padding: 0.5rem 0.25rem;
    text-align: center;
    white-space: nowrap;
}
.<?= $tableClass ?> td {
    color: var(--gray-800, #1f2937);
    font-size: 0.6875rem;
    padding: 0.375rem 0.25rem;
    text-align: center;
}
.<?= $tableClass ?> tbody tr {
    transition: background-color 150ms ease;
}
.<?= $tableClass ?> tbody tr:nth-child(odd) {
    background-color: white;
}
.<?= $tableClass ?> tbody tr:nth-child(even) {
    background-color: var(--gray-50, #f9fafb);
}
.<?= $tableClass ?> tbody tr:hover {
    background-color: var(--gray-100, #f3f4f6);
}
.<?= $tableClass ?> a {
    color: var(--gray-800, #1f2937);
    text-decoration: none;
    font-weight: 500;
    transition: color 150ms ease;
}
.<?= $tableClass ?> a:hover {
    color: var(--accent-500, #f97316);
}
</style>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate CSS styles for card-style tables (with border)
     *
     * @param string $tableClass CSS class name for the table
     * @return string CSS style block
     */
    public static function renderCard(string $tableClass): string
    {
        $tableClass = self::sanitizeClassName($tableClass);

        ob_start();
        ?>
<style>
/* Card table styling - Navy theme */
.<?= $tableClass ?>-wrapper {
    background: white;
    border-radius: var(--radius-xl, 0.75rem);
    overflow: hidden;
    box-shadow: var(--shadow-md, 0 4px 6px -1px rgb(0 0 0 / 0.1));
    border: 1px solid var(--gray-100, #f3f4f6);
    margin: 1rem auto;
}
.<?= $tableClass ?>-title {
    background: linear-gradient(135deg, var(--navy-800, #1e293b), var(--navy-900, #0f172a));
    padding: 0.75rem 1rem;
    margin: 0;
    font-family: var(--font-display, 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif);
    font-size: 0.875rem;
    font-weight: 600;
    color: white;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.<?= $tableClass ?> {
    font-family: var(--font-sans, 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
    border-collapse: collapse;
    width: 100%;
}
.<?= $tableClass ?> thead {
    background: var(--gray-50, #f9fafb);
    border-bottom: 1px solid var(--gray-200, #e5e7eb);
}
.<?= $tableClass ?> th {
    color: var(--gray-600, #4b5563);
    font-family: var(--font-display, 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif);
    font-weight: 600;
    font-size: 0.6875rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.625rem 0.5rem;
    text-align: center;
}
.<?= $tableClass ?> td {
    color: var(--gray-800, #1f2937);
    font-size: 0.75rem;
    padding: 0.5rem;
    text-align: center;
    border-bottom: 1px solid var(--gray-100, #f3f4f6);
}
.<?= $tableClass ?> tbody tr {
    transition: background-color 150ms ease;
}
.<?= $tableClass ?> tbody tr:hover {
    background-color: var(--gray-50, #f9fafb);
}
.<?= $tableClass ?> a {
    color: var(--gray-800, #1f2937);
    text-decoration: none;
    font-weight: 500;
    transition: color 150ms ease;
}
.<?= $tableClass ?> a:hover {
    color: var(--accent-500, #f97316);
}
</style>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate opening tag for responsive table scroll container
     *
     * Wraps tables in a scrollable container with sticky column support.
     * Use with renderScrollContainerEnd() to close the wrapper.
     *
     * @return string Opening div tag for scroll container
     */
    public static function renderScrollContainerStart(): string
    {
        return '<div class="table-scroll-container">';
    }

    /**
     * Generate closing tag for responsive table scroll container
     *
     * @return string Closing div tag for scroll container
     */
    public static function renderScrollContainerEnd(): string
    {
        return '</div>';
    }

    /**
     * Wrap table HTML in a responsive scroll container
     *
     * @param string $tableHtml The table HTML to wrap
     * @return string Table wrapped in scroll container
     */
    public static function wrapInScrollContainer(string $tableHtml): string
    {
        return self::renderScrollContainerStart() . $tableHtml . self::renderScrollContainerEnd();
    }

    /**
     * Sanitize CSS class name
     *
     * @param string $className CSS class name
     * @return string Sanitized class name
     */
    private static function sanitizeClassName(string $className): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $className);
    }
}
