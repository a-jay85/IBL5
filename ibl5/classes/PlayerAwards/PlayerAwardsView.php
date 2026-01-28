<?php

declare(strict_types=1);

namespace PlayerAwards;

use PlayerAwards\Contracts\PlayerAwardsViewInterface;
use Utilities\HtmlSanitizer;

/**
 * PlayerAwardsView - HTML rendering for player awards search interface
 * 
 * Implements the view contract defined in PlayerAwardsViewInterface.
 * See the interface for detailed behavior documentation.
 * 
 * @see PlayerAwardsViewInterface
 */
class PlayerAwardsView implements PlayerAwardsViewInterface
{
    private PlayerAwardsService $service;

    /**
     * Constructor with dependency injection
     * 
     * @param PlayerAwardsService $service Service for getting sort options
     */
    public function __construct(PlayerAwardsService $service)
    {
        $this->service = $service;
    }

    /**
     * @see PlayerAwardsViewInterface::renderSearchForm()
     */
    public function renderSearchForm(array $params): string
    {
        $name = HtmlSanitizer::safeHtmlOutput($params['name'] ?? '');
        $award = HtmlSanitizer::safeHtmlOutput($params['award'] ?? '');
        $year = HtmlSanitizer::safeHtmlOutput((string)($params['year'] ?? ''));
        $sortby = $params['sortby'] ?? 3;

        $sortOptions = $this->service->getSortOptions();

        ob_start();
        ?>
<style>
    .player-awards-form table {
        border: 1px solid #000;
    }
    .player-awards-form td {
        padding: 4px 8px;
    }
    .player-awards-form input[type="text"] {
        padding: 2px 4px;
    }
</style>

<div class="table-scroll-wrapper">
<div class="table-scroll-container">
<p>Partial matches on a name or award are okay and are <strong>not</strong> case sensitive
(e.g., entering "Dard" will match with "Darden" and "Bedard").</p>

<form name="Search" method="post" action="modules.php?name=Player_Awards" class="player-awards-form">
    <table>
        <tr>
            <td>NAME: <input type="text" name="aw_name" size="32" value="<?= $name ?>"></td>
            <td>AWARD: <input type="text" name="aw_Award" size="32" value="<?= $award ?>"></td>
            <td>Year: <input type="text" name="aw_year" size="4" value="<?= $year ?>"></td>
        </tr>
        <tr>
            <td colspan="3">SORT BY:
            <?php foreach ($sortOptions as $value => $label): ?>
                <?php $checked = ($sortby == $value) ? ' checked' : ''; ?>
                <input type="radio" name="aw_sortby" value="<?= $value ?>"<?= $checked ?>> <?= HtmlSanitizer::safeHtmlOutput($label) ?> |
            <?php endforeach; ?>
            </td>
        </tr>
    </table>
    <input type="submit" value="Search for Matches!">
</form>
</div>
</div>
        <?php
        return ob_get_clean();
    }

    /**
     * @see PlayerAwardsViewInterface::renderTableHeader()
     */
    public function renderTableHeader(): string
    {
        ob_start();
        ?>
<div class="table-scroll-wrapper">
<div class="table-scroll-container">
<table class="ibl-data-table">
    <thead>
        <tr>
            <th colspan="3"><em>Search Results</em></th>
        </tr>
        <tr>
            <th>Year</th>
            <th>Player</th>
            <th>Award</th>
        </tr>
    </thead>
    <tbody>
        <?php
        return ob_get_clean();
    }

    /**
     * @see PlayerAwardsViewInterface::renderAwardRow()
     */
    public function renderAwardRow(array $award, int $rowIndex): string
    {
        $year = HtmlSanitizer::safeHtmlOutput((string)($award['year'] ?? ''));
        $name = HtmlSanitizer::safeHtmlOutput($award['name'] ?? '');
        $awardName = HtmlSanitizer::safeHtmlOutput($award['Award'] ?? '');

        ob_start();
        ?>
<tr>
    <td><?= $year ?></td>
    <td><?= $name ?></td>
    <td><?= $awardName ?></td>
</tr>
        <?php
        return ob_get_clean();
    }

    /**
     * @see PlayerAwardsViewInterface::renderTableFooter()
     */
    public function renderTableFooter(): string
    {
        return "</tbody></table></div></div>\n";
    }
}
