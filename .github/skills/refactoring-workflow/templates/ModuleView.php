<?php

declare(strict_types=1);

namespace ModuleName;

use Utilities\HtmlSanitizer;
use BasketballStats\StatsFormatter;

/**
 * ModuleView - HTML rendering with output buffering
 *
 * Generates HTML output for the module. All dynamic content
 * is sanitized using HtmlSanitizer::safeHtmlOutput().
 */
class ModuleView
{
    /**
     * Render a table of records
     *
     * @param array<int, array<string, mixed>> $records Records to display
     * @return string HTML output
     */
    public function renderTable(array $records): string
    {
        if (empty($records)) {
            return $this->renderEmpty();
        }

        ob_start();
        ?>
<style>
.module-table { border: 1px solid #000; border-collapse: collapse; width: 100%; }
.module-table th, .module-table td { border: 1px solid #000; padding: 4px; }
.module-table th { background-color: #f0f0f0; font-weight: bold; }
</style>
<table class="module-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Value</th>
            <th>Stats</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($records as $record): ?>
        <tr>
            <td><?= HtmlSanitizer::safeHtmlOutput($record['name']) ?></td>
            <td><?= HtmlSanitizer::safeHtmlOutput($record['value']) ?></td>
            <td><?= StatsFormatter::formatPercentage($record['made'] ?? 0, $record['attempted'] ?? 0) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a single record detail view
     *
     * @param array<string, mixed> $record Record to display
     * @return string HTML output
     */
    public function renderDetail(array $record): string
    {
        ob_start();
        ?>
<div class="module-detail">
    <h2><?= HtmlSanitizer::safeHtmlOutput($record['name']) ?></h2>
    <dl>
        <dt>Value:</dt>
        <dd><?= HtmlSanitizer::safeHtmlOutput($record['value']) ?></dd>
        
        <dt>Average:</dt>
        <dd><?= StatsFormatter::formatAverage($record['average'] ?? 0) ?></dd>
    </dl>
</div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render empty state message
     *
     * @return string HTML output
     */
    private function renderEmpty(): string
    {
        return '<p style="text-align: center; color: #666;">No records found.</p>';
    }
}
