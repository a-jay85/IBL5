<?php

declare(strict_types=1);

namespace RecordHolders;

use RecordHolders\Contracts\AnnouncementDispatcherInterface;
use RecordHolders\Contracts\RecordBreakingDetectorInterface;
use RecordHolders\Contracts\RecordHoldersRepositoryInterface;
use Utilities\BoxScoreUrlBuilder;

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

    private AnnouncementDispatcherInterface $dispatcher;

    private const SITE_BASE_URL = 'https://iblhoops.net';

    /**
     * @var array<string, string>
     */
    private const GAME_TYPE_LABELS = [
        'regularSeason' => 'regular season',
        'playoffs' => 'playoff',
        'heat' => 'HEAT',
    ];

    public function __construct(
        RecordHoldersRepositoryInterface $repository,
        ?AnnouncementDispatcherInterface $dispatcher = null
    ) {
        $this->repository = $repository;
        $this->dispatcher = $dispatcher ?? new DiscordAnnouncementDispatcher();
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

            // Player single-game records — one query for all stats in the
            // canonical registry, keyed by stat key.
            $expressions = [];
            foreach (RecordStatDefinitions::STATS as $key => $def) {
                $expressions[$key] = $def['expression'];
            }
            $allPlayerRecords = $this->repository->getTopPlayerSingleGameBatch($expressions, $dateFilter);

            foreach (RecordStatDefinitions::STATS as $key => $def) {
                $topRecords = $allPlayerRecords[$key] ?? [];
                $newAnnouncements = $this->detectPlayerRecords($topRecords, $targetDates, $def['unit'], $gameTypeLabel);
                array_push($announcements, ...$newAnnouncements);
            }

            // Team single-game records — the 8 team stats (DESC) plus a synthetic
            // "fewest points" (ASC) that reuses the points expression.
            /** @var array<string, array{expression: string, order: string}> $teamBatchConfig */
            $teamBatchConfig = [];
            foreach (RecordStatDefinitions::STATS as $def) {
                $teamKey = $def['teamKey'];
                if ($teamKey === null) {
                    continue;
                }
                $teamBatchConfig[$teamKey] = ['expression' => $def['expression'], 'order' => 'DESC'];
            }
            $teamBatchConfig['team_fewest_points'] = [
                'expression' => RecordStatDefinitions::STATS['points']['expression'],
                'order' => 'ASC',
            ];
            $allTeamRecords = $this->repository->getTopTeamSingleGameBatch($teamBatchConfig, $dateFilter);

            foreach (RecordStatDefinitions::STATS as $def) {
                $teamKey = $def['teamKey'];
                if ($teamKey === null) {
                    continue;
                }
                $topRecords = $allTeamRecords[$teamKey] ?? [];
                $newAnnouncements = $this->detectTeamRecords($topRecords, $targetDates, $def['unit'], $gameTypeLabel, false);
                array_push($announcements, ...$newAnnouncements);
            }

            $fewestRecords = $allTeamRecords['team_fewest_points'] ?? [];
            $fewestAnnouncements = $this->detectTeamRecords(
                $fewestRecords,
                $targetDates,
                RecordStatDefinitions::STATS['points']['unit'],
                $gameTypeLabel,
                true
            );
            array_push($announcements, ...$fewestAnnouncements);
        }

        // Quadruple doubles (not filtered by game type)
        $allTargetDates = array_flip($gameDates);
        $qdAnnouncements = $this->detectNewQuadrupleDoubles($allTargetDates);
        array_push($announcements, ...$qdAnnouncements);

        // Dispatch each announcement independently — one failed dispatch must
        // not abort the remaining announcements (finding 1.3 resilience fix).
        foreach ($announcements as $message) {
            try {
                $this->dispatcher->dispatch($message);
            } catch (\Throwable) {
                // Swallow deliberately: a dispatch failure (e.g. Discord outage)
                // must not stop later announcements from being dispatched.
            }
        }

        return $announcements;
    }

    /**
     * Detect broken/tied player single-game records from the top entries.
     *
     * @param list<array{pid: int, name: string, teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}> $topRecords
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
                    $record['pid'],
                    $record['team_name'],
                    $record['value'],
                    $statUnit,
                    $previous['name'],
                    $previous['value'],
                    $gameTypeLabel,
                    $isTied,
                    $record['date'],
                    $record['game_of_that_day'],
                    $record['box_id']
                );
            }
        }

        return $announcements;
    }

    /**
     * Detect broken/tied team single-game records from the top entries.
     *
     * @param list<array{teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}> $topRecords
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
                    $isAscending,
                    $record['date'],
                    $record['game_of_that_day'],
                    $record['box_id']
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
                $gameType = IblSeasonDateHelper::getGameTypeFromDate($qd['date']);
                $gameTypeLabel = self::GAME_TYPE_LABELS[$gameType] ?? 'regular season';
                $announcements[] = $this->formatQuadrupleDoubleMessage(
                    $qd['name'],
                    $qd['pid'],
                    $qd['team_name'],
                    $qd['points'],
                    $qd['rebounds'],
                    $qd['assists'],
                    $qd['steals'],
                    $qd['blocks'],
                    $gameTypeLabel,
                    $qd['date'],
                    $qd['game_of_that_day'],
                    $qd['box_id']
                );
            }
        }

        return $announcements;
    }

    /**
     * Find the previous player record holder (best entry NOT from any target date).
     *
     * @param list<array{pid: int, name: string, teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}> $records
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
     * @param list<array{teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}> $records
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
            $gameType = IblSeasonDateHelper::getGameTypeFromDate($date);
            $grouped[$gameType][] = $date;
        }
        return $grouped;
    }

    /**
     * Format a player record-breaking or record-tying announcement.
     */
    private function formatPlayerRecordMessage(
        string $playerName,
        int $pid,
        string $teamName,
        int $newValue,
        string $statUnit,
        string $previousHolder,
        int $previousValue,
        string $gameTypeLabel,
        bool $isTied,
        string $date,
        int $gameOfThatDay,
        int $boxId
    ): string {
        $playerLink = '[' . $playerName . '](' . self::SITE_BASE_URL . '/modules.php?name=Player&pa=showpage&pid=' . $pid . ')';
        $boxScoreUrl = BoxScoreUrlBuilder::buildUrl($date, $gameOfThatDay, $boxId);
        $statText = $newValue . ' ' . $statUnit;
        $linkedStat = $boxScoreUrl !== '' ? '[**' . $statText . '**](' . $boxScoreUrl . ')' : '**' . $statText . '**';

        if ($isTied) {
            return "**IBL RECORD TIED!**\n"
                . $playerLink . ' (' . $teamName . ') just recorded ' . $linkedStat
                . ' in a ' . $gameTypeLabel . ' game, tying '
                . $previousHolder . "'s all-time record!";
        }

        return "**NEW IBL RECORD!**\n"
            . $playerLink . ' (' . $teamName . ') just recorded ' . $linkedStat
            . ' in a ' . $gameTypeLabel . ' game, breaking '
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
        bool $isAscending,
        string $date,
        int $gameOfThatDay,
        int $boxId
    ): string {
        $label = ($isAscending ? 'fewest ' : 'most ') . $statUnit;
        $boxScoreUrl = BoxScoreUrlBuilder::buildUrl($date, $gameOfThatDay, $boxId);
        $statText = $newValue . ' ' . $label;
        $linkedStat = $boxScoreUrl !== '' ? '[**' . $statText . '**](' . $boxScoreUrl . ')' : '**' . $statText . '**';

        if ($isTied) {
            return "**IBL TEAM RECORD TIED!**\n"
                . 'The ' . $teamName . ' just recorded ' . $linkedStat
                . ' in a ' . $gameTypeLabel . ' game, tying '
                . 'the ' . $previousTeam . "'s all-time record!";
        }

        return "**NEW IBL TEAM RECORD!**\n"
            . 'The ' . $teamName . ' just recorded ' . $linkedStat
            . ' in a ' . $gameTypeLabel . ' game, breaking '
            . 'the ' . $previousTeam . "'s all-time record of " . $previousValue . ' ' . $label . '!';
    }

    /**
     * Format a quadruple double announcement.
     */
    private function formatQuadrupleDoubleMessage(
        string $playerName,
        int $pid,
        string $teamName,
        int $points,
        int $rebounds,
        int $assists,
        int $steals,
        int $blocks,
        string $gameTypeLabel,
        string $date,
        int $gameOfThatDay,
        int $boxId
    ): string {
        $playerLink = '[' . $playerName . '](' . self::SITE_BASE_URL . '/modules.php?name=Player&pa=showpage&pid=' . $pid . ')';
        $boxScoreUrl = BoxScoreUrlBuilder::buildUrl($date, $gameOfThatDay, $boxId);

        $stats = $points . 'pts/' . $rebounds . 'reb/' . $assists . 'ast/' . $steals . 'stl';
        if ($blocks >= 10) {
            $stats .= '/' . $blocks . 'blk';
        }

        $linkedStats = $boxScoreUrl !== '' ? '[**' . $stats . '**](' . $boxScoreUrl . ')' : '**' . $stats . '**';

        return "**NEW QUADRUPLE DOUBLE!**\n"
            . $playerLink . ' (' . $teamName . ') recorded a quadruple double (' . $linkedStats
            . ') in a ' . $gameTypeLabel . ' game!';
    }

    /**
     * Get the SQL date filter for a game type.
     */
    private function getDateFilterForType(string $gameType): string
    {
        return RecordStatDefinitions::DATE_FILTERS[$gameType]
            ?? RecordStatDefinitions::DATE_FILTERS['regularSeason'];
    }
}
