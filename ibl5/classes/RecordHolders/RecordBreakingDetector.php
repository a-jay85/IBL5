<?php

declare(strict_types=1);

namespace RecordHolders;

use RecordHolders\Contracts\RecordBreakingDetectorInterface;
use RecordHolders\Contracts\RecordHoldersRepositoryInterface;

/**
 * RecordBreakingDetector - Detects and announces broken all-time IBL records.
 *
 * Called after sim data is finalized to compare new results against existing records.
 * Sends Discord notifications when records are broken.
 *
 * @see RecordBreakingDetectorInterface
 */
class RecordBreakingDetector implements RecordBreakingDetectorInterface
{
    private RecordHoldersRepositoryInterface $repository;

    /**
     * Stat SQL expressions and labels for player single-game records.
     *
     * @var array<string, array{expression: string, label: string, unit: string}>
     */
    private const PLAYER_STATS = [
        'points' => ['expression' => '(bs.game2GM * 2 + bs.gameFTM + bs.game3GM * 3)', 'label' => 'points', 'unit' => 'points'],
        'rebounds' => ['expression' => '(bs.gameORB + bs.gameDRB)', 'label' => 'rebounds', 'unit' => 'rebounds'],
        'assists' => ['expression' => 'bs.gameAST', 'label' => 'assists', 'unit' => 'assists'],
        'steals' => ['expression' => 'bs.gameSTL', 'label' => 'steals', 'unit' => 'steals'],
        'blocks' => ['expression' => 'bs.gameBLK', 'label' => 'blocks', 'unit' => 'blocks'],
        'fg_made' => ['expression' => '(bs.game2GM + bs.game3GM)', 'label' => 'field goals made', 'unit' => 'field goals'],
        'ft_made' => ['expression' => 'bs.gameFTM', 'label' => 'free throws made', 'unit' => 'free throws'],
        '3pt_made' => ['expression' => 'bs.game3GM', 'label' => 'three pointers made', 'unit' => 'three pointers'],
    ];

    /**
     * Date filter labels for determining game type from a date.
     *
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
    public function detectAndAnnounce(string $gameDate): array
    {
        /** @var list<string> $brokenRecords */
        $brokenRecords = [];

        $gameType = $this->getGameTypeFromDate($gameDate);
        $dateFilter = $this->getDateFilterForType($gameType);

        // Build expression map and fetch all stats in one UNION ALL query (8 queries → 1)
        $expressions = [];
        foreach (self::PLAYER_STATS as $key => $stat) {
            $expressions[$key] = $stat['expression'];
        }
        $allTopRecords = $this->repository->getTopPlayerSingleGameBatch($expressions, $dateFilter);

        foreach (self::PLAYER_STATS as $key => $stat) {
            $topRecords = $allTopRecords[$key] ?? [];

            if ($topRecords === []) {
                continue;
            }

            // Check if any of the top records were set on the given date
            $topValue = $topRecords[0]['value'];
            foreach ($topRecords as $record) {
                if ($record['date'] === $gameDate && $record['value'] === $topValue) {
                    // Check if this beats the previous record
                    $previousRecord = $this->findPreviousRecord($topRecords, $gameDate);
                    if ($previousRecord !== null && $record['value'] > $previousRecord['value']) {
                        $message = $this->formatRecordMessage(
                            $record['name'],
                            $record['team_name'],
                            $record['value'],
                            $stat['unit'],
                            $previousRecord['name'],
                            $previousRecord['value'],
                            self::GAME_TYPE_LABELS[$gameType] ?? 'regular season'
                        );
                        $brokenRecords[] = $message;
                        $this->sendDiscordNotification($message);
                    }
                }
            }
        }

        return $brokenRecords;
    }

    /**
     * Find the previous record holder (the best entry NOT from the given date).
     *
     * @param list<array{pid: int, name: string, tid: int, team_name: string, date: string, BoxID: int, oppTid: int, opp_team_name: string, value: int}> $records
     * @return array{name: string, value: int}|null
     */
    private function findPreviousRecord(array $records, string $currentDate): ?array
    {
        foreach ($records as $record) {
            if ($record['date'] !== $currentDate) {
                return ['name' => $record['name'], 'value' => $record['value']];
            }
        }
        return null;
    }

    /**
     * Format a record-breaking announcement message.
     */
    private function formatRecordMessage(
        string $playerName,
        string $teamName,
        int $newValue,
        string $statUnit,
        string $previousHolder,
        int $previousValue,
        string $gameTypeLabel
    ): string {
        return "**NEW IBL RECORD!**\n"
            . $playerName . ' (' . $teamName . ') just recorded **' . $newValue . ' ' . $statUnit
            . '** in a ' . $gameTypeLabel . ' game, breaking '
            . $previousHolder . "'s all-time record of " . $previousValue . ' ' . $statUnit . '!';
    }

    /**
     * Send a Discord notification about a broken record.
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
            'regularSeason' => 'MONTH(bs.Date) IN (11, 12, 1, 2, 3, 4, 5)',
            'playoffs' => 'MONTH(bs.Date) = 6',
            'heat' => 'MONTH(bs.Date) = 10',
        ];

        return $filters[$gameType] ?? $filters['regularSeason'];
    }
}
