<?php

declare(strict_types=1);

namespace FranchiseRecordBook;

use FranchiseRecordBook\Contracts\FranchiseRecordBookRepositoryInterface;
use FranchiseRecordBook\Contracts\FranchiseRecordBookServiceInterface;
use JsbParser\JsbImportRepository;
use Utilities\HtmlSanitizer;

/**
 * View for rendering franchise record book pages.
 *
 * @phpstan-import-type AlltimeRecord from FranchiseRecordBookRepositoryInterface
 * @phpstan-import-type TeamInfo from FranchiseRecordBookRepositoryInterface
 * @phpstan-import-type RecordsByCategory from FranchiseRecordBookServiceInterface
 * @phpstan-import-type RecordBookData from FranchiseRecordBookServiceInterface
 */
class FranchiseRecordBookView
{
    /**
     * Stats that display as percentages (e.g., 0.523 → ".523").
     *
     * @var list<string>
     */
    private const PERCENTAGE_STATS = ['fg_pct', 'ft_pct', 'three_pct'];

    /**
     * Render the complete record book page.
     *
     * @param RecordBookData $data
     */
    public function render(array $data): string
    {
        $html = $this->renderTeamSelector($data['teams'], $data['team']);
        $html .= $this->renderTitle($data);

        if ($data['singleSeason'] !== []) {
            $html .= '<h3 class="ibl-title" style="font-size: 1.25rem; margin-top: 1.5rem;">Single-Season Records</h3>';
            $html .= $this->renderRecordSection($data['singleSeason'], 'single_season');
        }

        if ($data['career'] !== []) {
            $html .= '<h3 class="ibl-title" style="font-size: 1.25rem; margin-top: 1.5rem;">Career Records</h3>';
            $html .= $this->renderRecordSection($data['career'], 'career');
        }

        return $html;
    }

    /**
     * Render the page title.
     *
     * @param RecordBookData $data
     */
    private function renderTitle(array $data): string
    {
        if ($data['team'] !== null) {
            /** @var string $teamName */
            $teamName = HtmlSanitizer::safeHtmlOutput($data['team']['team_name']);
            return '<h2 class="ibl-title">' . $teamName . ' Franchise Record Book</h2>';
        }
        return '<h2 class="ibl-title">League-Wide Record Book</h2>';
    }

    /**
     * Render the team selector dropdown.
     *
     * @param list<TeamInfo> $teams
     * @param TeamInfo|null $selectedTeam
     */
    private function renderTeamSelector(array $teams, ?array $selectedTeam): string
    {
        $selectedId = $selectedTeam !== null ? (int) $selectedTeam['teamid'] : 0;

        ob_start();
        ?>
<form method="get" class="record-book-team-selector">
    <input type="hidden" name="name" value="FranchiseRecordBook">
    <label for="record-book-team">Select Team:</label>
    <select name="teamid" id="record-book-team" onchange="this.form.submit()">
        <option value="0"<?= $selectedId === 0 ? ' selected' : '' ?>>League-Wide</option>
        <?php foreach ($teams as $team): ?>
        <?php
            /** @var string $safeTeamName */
            $safeTeamName = HtmlSanitizer::safeHtmlOutput($team['team_name']);
        ?>
        <option value="<?= (int) $team['teamid'] ?>"<?= (int) $team['teamid'] === $selectedId ? ' selected' : '' ?>>
            <?= $safeTeamName ?>
        </option>
        <?php endforeach; ?>
    </select>
</form>
        <?php
        $result = ob_get_clean();
        return $result !== false ? $result : '';
    }

    /**
     * Render a section of records (single-season or career).
     *
     * @param RecordsByCategory $recordsByCategory
     * @param string $recordType 'single_season' or 'career'
     */
    private function renderRecordSection(array $recordsByCategory, string $recordType): string
    {
        $html = '';
        foreach ($recordsByCategory as $category => $records) {
            if ($records === []) {
                continue;
            }
            $html .= $this->renderCategoryTable($category, $records, $recordType);
        }
        return $html;
    }

    /**
     * Render a table for a single stat category.
     *
     * @param string $category Stat category key
     * @param list<AlltimeRecord> $records
     * @param string $recordType 'single_season' or 'career'
     */
    private function renderCategoryTable(string $category, array $records, string $recordType): string
    {
        $label = FranchiseRecordBookService::STAT_LABELS[$category] ?? $category;
        $abbrev = FranchiseRecordBookService::STAT_ABBREV[$category] ?? $category;
        $isPercentage = in_array($category, self::PERCENTAGE_STATS, true);

        ob_start();
        ?>
<div class="record-book-category">
    <?php /** @var string $safeLabel */ $safeLabel = HtmlSanitizer::safeHtmlOutput($label); ?>
    <h4 class="record-book-category-title"><?= $safeLabel ?></h4>
    <table class="ibl-data-table record-book-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Player</th>
                <?php /** @var string $safeAbbrev */ $safeAbbrev = HtmlSanitizer::safeHtmlOutput($abbrev); ?>
                <th><?= $safeAbbrev ?></th>
                <?php if ($recordType === 'single_season'): ?>
                <th>Season</th>
                <?php endif; ?>
                <?php if ($recordType === 'career' && !$isPercentage): ?>
                <th>Total</th>
                <?php endif; ?>
                <th>Team</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($records as $record): ?>
            <tr>
                <td><?= (int) $record['ranking'] ?></td>
                <?php /** @var string $safePlayerName */ $safePlayerName = HtmlSanitizer::safeHtmlOutput($record['player_name']); ?>
                <td><?= $safePlayerName ?></td>
                <td><?= $this->formatStatValue($record, $isPercentage) ?></td>
                <?php if ($recordType === 'single_season'): ?>
                <td><?= $record['season_year'] !== null ? (int) $record['season_year'] : '' ?></td>
                <?php endif; ?>
                <?php if ($recordType === 'career' && !$isPercentage): ?>
                <td><?= $record['career_total'] !== null ? number_format((int) $record['career_total']) : '' ?></td>
                <?php endif; ?>
                <td><?= $this->resolveTeamName((int) ($record['team_of_record'] ?? 0)) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
        <?php
        $result = ob_get_clean();
        return $result !== false ? $result : '';
    }

    /**
     * Format a stat value for display.
     *
     * @param AlltimeRecord $record
     */
    private function formatStatValue(array $record, bool $isPercentage): string
    {
        /** @var string $statValue */
        $statValue = $record['stat_value'];
        $value = (float) $statValue;

        if ($isPercentage) {
            return number_format($value, 4);
        }
        return number_format($value, 2);
    }

    /**
     * Resolve a JSB team ID to a display name.
     */
    private function resolveTeamName(int $jsbTeamId): string
    {
        $name = JsbImportRepository::JSB_TEAM_NAMES[$jsbTeamId] ?? '';

        // Apply alias mapping (JSB names → current DB names)
        if (isset(JsbImportRepository::TEAM_NAME_ALIASES[$name])) {
            $name = JsbImportRepository::TEAM_NAME_ALIASES[$name];
        }

        /** @var string $safe */
        $safe = HtmlSanitizer::safeHtmlOutput($name);
        return $safe;
    }
}
