<?php

declare(strict_types=1);

namespace Team;

/**
 * HTMX endpoint handler for team page tab switching
 *
 * Returns the table HTML for a given display mode without the full page layout.
 * Emits HX-Push-Url header so HTMX pushes the user-friendly URL.
 */
class TeamApiHandler
{
    private const VALID_DISPLAY_MODES = [
        'ratings',
        'total_s',
        'avg_s',
        'per36mins',
        'chunk',
        'playoffs',
        'contracts',
        'split',
    ];

    private \mysqli $db;
    private TeamTableService $tableService;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $repository = new TeamRepository($db);
        $this->tableService = new TeamTableService($db, $repository);
    }

    public function handle(): void
    {
        header('Content-Type: text/html; charset=utf-8');

        $teamid = isset($_GET['teamid']) && is_string($_GET['teamid']) ? (int) $_GET['teamid'] : 0;

        $display = self::extractValidatedDisplay($_GET);
        $yr = self::extractValidatedYr($_GET);

        $split = null;
        if ($display === 'split' && isset($_GET['split']) && is_string($_GET['split'])) {
            $splitRepo = new SplitStatsRepository($this->db);
            $rawSplit = $_GET['split'];
            if (in_array($rawSplit, $splitRepo->getValidSplitKeys(), true)) {
                $split = $rawSplit;
            } else {
                $display = 'ratings';
            }
        } elseif ($display === 'split') {
            $display = 'ratings';
        }

        header('HX-Push-Url: ' . self::buildPushUrl($teamid, $display, $split, $yr));

        $responder = new \Api\Response\HtmlResponder();
        $responder->html($this->tableService->getTableOutput($teamid, $yr, $display, $split));
    }

    /** @param array<mixed> $params */
    public static function extractValidatedDisplay(array $params): string
    {
        if (isset($params['display']) && is_string($params['display'])) {
            $raw = $params['display'];
            if (in_array($raw, self::VALID_DISPLAY_MODES, true)) {
                return $raw;
            }
        }

        return 'ratings';
    }

    /** @param array<mixed> $params */
    public static function extractValidatedYr(array $params): ?string
    {
        if (isset($params['yr']) && is_string($params['yr']) && $params['yr'] !== '') {
            $raw = $params['yr'];
            if (preg_match('/^\d{4}(-\d{2})?$/', $raw) === 1) {
                return $raw;
            }
        }

        return null;
    }

    public static function buildPushUrl(int $teamid, string $display, ?string $split, ?string $yr): string
    {
        $url = 'modules.php?name=Team&op=team&teamid=' . $teamid . '&display=' . $display;
        if ($split !== null) {
            $url .= '&split=' . $split;
        }
        if ($yr !== null) {
            $url .= '&yr=' . $yr;
        }
        return $url;
    }
}
