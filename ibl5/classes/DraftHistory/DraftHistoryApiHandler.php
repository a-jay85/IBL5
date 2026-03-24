<?php

declare(strict_types=1);

namespace DraftHistory;

/**
 * HTMX endpoint handler for draft history year switching.
 *
 * Returns the draft table HTML for a given year without the full page layout.
 * Emits HX-Push-Url for browser history.
 */
class DraftHistoryApiHandler
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    public function handle(): void
    {
        header('Content-Type: text/html; charset=utf-8');

        $repository = new DraftHistoryRepository($this->db);

        $startYear = $repository->getFirstDraftYear();
        $endYear = $repository->getLastDraftYear();

        $year = $endYear;
        if (isset($_GET['year']) && is_string($_GET['year'])) {
            $raw = (int) $_GET['year'];
            if ($raw >= $startYear && $raw <= $endYear) {
                $year = $raw;
            }
        }

        header('HX-Push-Url: modules.php?name=DraftHistory&year=' . $year);

        $draftPicks = $repository->getDraftPicksByYear($year);
        $view = new DraftHistoryView();
        echo $view->renderYearTable($draftPicks);
    }
}
