<?php

declare(strict_types=1);

namespace FranchiseRecordBook;

use League\League;

/**
 * HTMX endpoint handler for franchise record book team switching.
 *
 * Returns the content HTML (title + record sections) for a given team
 * without the full page layout. Emits HX-Push-Url for browser history.
 */
class FranchiseRecordBookApiHandler
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    public function handle(): void
    {
        header('Content-Type: text/html; charset=utf-8');

        $teamId = 0;
        if (isset($_GET['teamid']) && is_string($_GET['teamid'])) {
            $teamId = (int) $_GET['teamid'];
        }

        if ($teamId !== 0 && !League::isRealFranchise($teamId)) {
            $teamId = 0;
        }

        $pushUrl = 'modules.php?name=FranchiseRecordBook';
        if ($teamId > 0) {
            $pushUrl .= '&teamid=' . $teamId;
        }
        header('HX-Push-Url: ' . $pushUrl);

        $repository = new FranchiseRecordBookRepository($this->db);
        $service = new FranchiseRecordBookService($repository);
        $view = new FranchiseRecordBookView();

        if (League::isRealFranchise($teamId)) {
            $data = $service->getTeamRecordBook($teamId);
        } else {
            $data = $service->getLeagueRecordBook();
        }

        echo $view->renderContent($data);
    }
}
