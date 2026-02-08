<?php

declare(strict_types=1);

namespace DraftHistory\Contracts;

/**
 * View interface for Draft History module rendering.
 *
 * Provides method to render the draft history page.
 *
 * @phpstan-import-type DraftPickByYearRow from DraftHistoryRepositoryInterface
 * @phpstan-import-type DraftPickByTeamRow from DraftHistoryRepositoryInterface
 */
interface DraftHistoryViewInterface
{
    /**
     * Render the draft history page.
     *
     * @param int $selectedYear Currently selected draft year
     * @param int $startYear First available draft year
     * @param int $endYear Last available draft year
     * @param list<DraftPickByYearRow> $draftPicks Array of draft pick data
     * @return string HTML output for the draft history page
     */
    public function render(int $selectedYear, int $startYear, int $endYear, array $draftPicks): string;

    /**
     * Render the team-specific draft history page.
     *
     * @param \Team $team Team object
     * @param list<DraftPickByTeamRow> $draftPicks Array of draft pick data
     * @return string HTML output for the team draft history page
     */
    public function renderTeamHistory(\Team $team, array $draftPicks): string;
}
