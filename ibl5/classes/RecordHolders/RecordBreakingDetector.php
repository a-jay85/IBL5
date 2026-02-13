<?php

declare(strict_types=1);

namespace RecordHolders;

use RecordHolders\Contracts\RecordBreakingDetectorInterface;
use RecordHolders\Contracts\RecordHoldersRepositoryInterface;

/**
 * RecordBreakingDetector - Detects and announces broken/tied all-time IBL records.
 *
 * Called after sim data is finalized to compare new results against existing records.
 * Checks player single-game records, team single-game records, and quadruple doubles.
 * Sends Discord notifications when records are broken or tied.
 *
 * @see RecordBreakingDetectorInterface
 */
class RecordBreakingDetector implements RecordBreakingDetectorInterface
{
    private RecordHoldersRepositoryInterface $repository;

    /**
     * Player single-game stat expressions and labels.
     *
     * @var array<string, array{expression: string, unit: string}>
     */
    private const PLAYER_STATS = [
        'points' => ['expression' => 'bs.calc_points', 'unit' => 'points'],
        'rebounds' => ['expression' => 'bs.calc_rebounds', 'unit' => 'rebounds'],
        'assists' => ['expression' => 'bs.gameAST', 'unit' => 'assists'],
        'steals' => ['expression' => 'bs.gameSTL', 'unit' => 'steals'],
        'blocks' => ['expression' => 'bs.gameBLK', 'unit' => 'blocks'],
        'turnovers' => ['expression' => 'bs.gameTOV', 'unit' => 'turnovers'],
        'fg_made' => ['expression' => 'bs.calc_fg_made', 'unit' => 'field goals'],
        'ft_made' => ['expression' => 'bs.gameFTM', 'unit' => 'free throws'],
        '3pt_made' => ['expression' => 'bs.game3GM', 'unit' => 'three pointers'],
    ];

    /**
     * Team single-game stat expressions, labels, and sort directions.
     *
     * @var array<string, array{expression: string, unit: string, order: string}>
     */
    private const TEAM_STATS = [
        'team_points' => ['expression' => 'bs.calc_points', 'unit' => 'points', 'order' => 'DESC'],
        'team_rebounds' => ['expression' => 'bs.calc_rebounds', 'unit' => 'rebounds', 'order' => 'DESC'],
        'team_assists' => ['expression' => 'bs.gameAST', 'unit' => 'assists', 'order' => 'DESC'],
        'team_steals' => ['expression' => 'bs.gameSTL', 'unit' => 'steals', 'order' => 'DESC'],
        'team_blocks' => ['expression' => 'bs.gameBLK', 'unit' => 'blocks', 'order' => 'DESC'],
        'team_fg_made' => ['expression' => 'bs.calc_fg_made', 'unit' => 'field goals', 'order' => 'DESC'],
        'team_ft_made' => ['expression' => 'bs.gameFTM', 'unit' => 'free throws', 'order' => 'DESC'],
        'team_3pt_made' => ['expression' => 'bs.game3GM', 'unit' => 'three pointers', 'order' => 'DESC'],
        'team_fewest_points' => ['expression' => 'bs.calc_points', 'unit' => 'points', 'order' => 'ASC'],
    ];

    /**
     * @var array<string, string>
     */
    private const GAME_TYPE_LABELS = [
        'regularSeason' => 'regular season',
        'playoffs' => 'playoff',
        'heat' => 'HEAT',
    ];

    public function __construct(RecordHoldersRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see RecordBreakingDetectorInterface::detectAndAnnounce()
     *
     * @return list<string>
     */
    public function detectAndAnnounce(array $gameDates): array
    {
        if ($gameDates === []) {
            return [];
        }

        /** @var list<string> $announcements */
        $announcements = [];

        // Group dates by game type and run detection per type
        $datesByGameType = $this->groupDatesByGameType($gameDates);

        foreach ($datesByGameType as $gameType => $dates) {
            $targetDates = array_flip($dates);
            $dateFilter = $this->getDateFilterForType($gameType);
            $gameTypeLabel = self::GAME_TYPE_LABELS[$gameType] ?? 'regular season';

            // Player single-game records
            $expressions = [];
            foreach (self::PLAYER_STATS as $key => $stat) {
                $expressions[$key] = $stat['expression'];
            }
            $allPlayerRecords = $this->repository->getTopPlayerSingleGameBatch($expressions, $dateFilter);

            foreach (self::PLAYER_STATS as $key => $stat) {
                $topRecords = $allPlayerRecords[$key] ?? [];
                $newAnnouncements = $this->detectPlayerRecords($topRecords, $targetDates, $stat['unit'], $gameTypeLabel);
                array_push($announcements, ...$newAnnouncements);
            }

            // Team single-game records
            /** @var array<string, array{expression: string, order: string}> $teamBatchConfig */
            $teamBatchConfig = [];
            foreach (self::TEAM_STATS as $key => $stat) {
                $teamBatchConfig[$key] = ['expression' => $stat['expression'], 'order' => $stat['order']];
            }
            $allTeamRecords = $this->repository->getTopTeamSingleGameBatch($teamBatchConfig, $dateFilter);

            foreach (self::TEAM_STATS as $key => $stat) {
                $topRecords = $allTeamRecords[$key] ?? [];
                $isAscending = $stat['order'] === 'ASC';
                $newAnnouncements = $this->detectTeamRecords($topRecords, $targetDates, $stat['unit'], $gameTypeLabel, $isAscending);
                array_push($announcements, ...$newAnnouncements);
            }
        }

        // Quadruple doubles (not filtered by game type)
        $allTargetDates = array_flip($gameDates);
        $qdAnnouncements = $this->detectNewQuadrupleDoubles($allTargetDates);
        array_push($announcements, ...$qdAnnouncements);

        // Send all Discord notifications
        foreach ($announcements as $message) {
            $this->sendDiscordNotification($message);
        }

        return $announcements;
    }

    /**
     * Detect broken/tied player single-game records from the top entries.
     *
     * @param list<array{pid: int, name: string, tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int}> $topRecords
     * @param array<string, int> $targetDates
     * @return list<string>
     */
    private function detectPlayerRecords(array $topRecords, array $targetDates, string $statUnit, string $gameTypeLabel): array
    {
        if ($topRecords === []) {
            return [];
        }

        /** @var list<string> $announcements */
        $announcements = [];
        $topValue = $topRecords[0]['value'];
        $previous = $this->findPreviousPlayerRecord($topRecords, $targetDates);

        if ($previous === null || $topValue < $previous['value']) {
            return [];
        }

        $isTied = $topValue === $previous['value'];

        foreach ($topRecords as $record) {
            if (isset($targetDates[$record['date']]) && $record['value'] === $topValue) {
                $announcements[] = $this->formatPlayerRecordMessage(
                    $record['name'],
                    $record['team_name'],
                    $record['value'],
                    $statUnit,
                    $previous['name'],
                    $previous['value'],
                    $gameTypeLabel,
                    $isTied
                );
            }
        }

        return $announcements;
    }

    /**
     * Detect broken/tied team single-game records from the top entries.
     *
     * @param list<array{tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int}> $topRecords
     * @param array<string, int> $targetDates
     * @return list<string>
     */
    private function detectTeamRecords(array $topRecords, array $targetDates, string $statUnit, string $gameTypeLabel, bool $isAscending): array
    {
        if ($topRecords === []) {
            return [];
        }

        /** @var list<string> $announcements */
        $announcements = [];
        $topValue = $topRecords[0]['value'];
        $previous = $this->findPreviousTeamRecord($topRecords, $targetDates);

        if ($previous === null) {
            return [];
        }

        $isNewRecord = $isAscending
            ? $topValue <= $previous['value']
            : $topValue >= $previous['value'];

        if (!$isNewRecord) {
            return [];
        }

        $isTied = $topValue === $previous['value'];

        foreach ($topRecords as $record) {
            if (isset($targetDates[$record['date']]) && $record['value'] === $topValue) {
                $announcements[] = $this->formatTeamRecordMessage(
                    $record['team_name'],
                    $record['value'],
                    $statUnit,
                    $previous['team_name'],
                    $previous['value'],
                    $gameTypeLabel,
                    $isTied,
                    $isAscending
                );
            }
        }

        return $announcements;
    }

    /**
     * Detect new quadruple doubles from the target dates.
     *
     * @param array<string, int> $targetDates
     * @return list<string>
     */
    private function detectNewQuadrupleDoubles(array $targetDates): array
    {
        $allQuadDoubles = $this->repository->getQuadrupleDoubles();

        /** @var list<string> $announcements */
        $announcements = [];

        foreach ($allQuadDoubles as $qd) {
            if (isset($targetDates[$qd['date']])) {
                $gameType = $this->getGameTypeFromDate($qd['date']);
                $gameTypeLabel = self::GAME_TYPE_LABELS[$gameType] ?? 'regular season';
                $announcements[] = $this->formatQuadrupleDoubleMessage(
                    $qd['name'],
                    $qd['team_name'],
                    $qd['points'],
                    $qd['rebounds'],
                    $qd['assists'],
                    $qd['steals'],
                    $qd['blocks'],
                    $gameTypeLabel
                );
            }
        }

        return $announcements;
    }

    /**
     * Find the previous player record holder (best entry NOT from any target date).
     *
     * @param list<array{pid: int, name: string, tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int}> $records
     * @param array<string, int> $targetDates
     * @return array{name: string, value: int}|null
     */
    private function findPreviousPlayerRecord(array $records, array $targetDates): ?array
    {
        foreach ($records as $record) {
            if (!isset($targetDates[$record['date']])) {
                return ['name' => $record['name'], 'value' => $record['value']];
            }
        }
        return null;
    }

    /**
     * Find the previous team record holder (best entry NOT from any target date).
     *
     * @param list<array{tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int}> $records
     * @param array<string, int> $targetDates
     * @return array{team_name: string, value: int}|null
     */
    private function findPreviousTeamRecord(array $records, array $targetDates): ?array
    {
        foreach ($records as $record) {
            if (!isset($targetDates[$record['date']])) {
                return ['team_name' => $record['team_name'], 'value' => $record['value']];
            }
        }
        return null;
    }

    /**
     * Group dates by their game type (regularSeason, playoffs, heat).
     *
     * @param list<string> $dates
     * @return array<string, list<string>>
     */
    private function groupDatesByGameType(array $dates): array
    {
        /** @var array<string, list<string>> $grouped */
        $grouped = [];
        foreach ($dates as $date) {
            $gameType = $this->getGameTypeFromDate($date);
            $grouped[$gameType][] = $date;
        }
        return $grouped;
    }

    /**
     * Format a player record-breaking or record-tying announcement.
     */
    private function formatPlayerRecordMessage(
        string $playerName,
        string $teamName,
        int $newValue,
        string $statUnit,
        string $previousHolder,
        int $previousValue,
        string $gameTypeLabel,
        bool $isTied
    ): string {
        if ($isTied) {
            return "**IBL RECORD TIED!**\n"
                . $playerName . ' (' . $teamName . ') just recorded **' . $newValue . ' ' . $statUnit
                . '** in a ' . $gameTypeLabel . ' game, tying '
                . $previousHolder . "'s all-time record!";
        }

        return "**NEW IBL RECORD!**\n"
            . $playerName . ' (' . $teamName . ') just recorded **' . $newValue . ' ' . $statUnit
            . '** in a ' . $gameTypeLabel . ' game, breaking '
            . $previousHolder . "'s all-time record of " . $previousValue . ' ' . $statUnit . '!';
    }

    /**
     * Format a team record-breaking or record-tying announcement.
     */
    private function formatTeamRecordMessage(
        string $teamName,
        int $newValue,
        string $statUnit,
        string $previousTeam,
        int $previousValue,
        string $gameTypeLabel,
        bool $isTied,
        bool $isAscending
    ): string {
        $label = ($isAscending ? 'fewest ' : 'most ') . $statUnit;

        if ($isTied) {
            return "**IBL TEAM RECORD TIED!**\n"
                . 'The ' . $teamName . ' just recorded **' . $newValue . ' ' . $label
                . '** in a ' . $gameTypeLabel . ' game, tying '
                . 'the ' . $previousTeam . "'s all-time record!";
        }

        return "**NEW IBL TEAM RECORD!**\n"
            . 'The ' . $teamName . ' just recorded **' . $newValue . ' ' . $label
            . '** in a ' . $gameTypeLabel . ' game, breaking '
            . 'the ' . $previousTeam . "'s all-time record of " . $previousValue . ' ' . $label . '!';
    }

    /**
     * Format a quadruple double announcement.
     */
    private function formatQuadrupleDoubleMessage(
        string $playerName,
        string $teamName,
        int $points,
        int $rebounds,
        int $assists,
        int $steals,
        int $blocks,
        string $gameTypeLabel
    ): string {
        $stats = $points . 'pts/' . $rebounds . 'reb/' . $assists . 'ast/' . $steals . 'stl';
        if ($blocks >= 10) {
            $stats .= '/' . $blocks . 'blk';
        }

        return "**NEW QUADRUPLE DOUBLE!**\n"
            . $playerName . ' (' . $teamName . ') recorded a quadruple double (**' . $stats
            . '**) in a ' . $gameTypeLabel . ' game!';
    }

    /**
     * Send a Discord notification about a record.
     */
    private function sendDiscordNotification(string $message): void
    {
        \Discord::postToChannel('#trades', $message);
        \Discord::postToChannel('#general-chat', $message);
    }

    /**
     * Determine the game type from a date.
     */
    private function getGameTypeFromDate(string $date): string
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return 'regularSeason';
        }
        $month = (int) date('n', $timestamp);

        if ($month === 10) {
            return 'heat';
        }
        if ($month === 6) {
            return 'playoffs';
        }
        return 'regularSeason';
    }

    /**
     * Get the SQL date filter for a game type.
     */
    private function getDateFilterForType(string $gameType): string
    {
        $filters = [
            'regularSeason' => 'bs.game_type = 1',
            'playoffs' => 'bs.game_type = 2',
            'heat' => 'bs.game_type = 3',
        ];

        return $filters[$gameType] ?? $filters['regularSeason'];
    }
}
