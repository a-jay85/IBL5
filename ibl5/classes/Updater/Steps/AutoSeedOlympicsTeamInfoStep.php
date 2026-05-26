<?php

declare(strict_types=1);

namespace Updater\Steps;

use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

final class AutoSeedOlympicsTeamInfoStep implements PipelineStepInterface
{
    public function __construct(
        private readonly \mysqli $db,
        private readonly int $seasonEndingYear,
        private readonly ?int $realTeamCountParam,
    ) {
    }

    public function getLabel(): string
    {
        return 'Olympics team info seeded';
    }

    public function execute(): StepResult
    {
        $existingRealCount = $this->countRealTeams();

        if ($existingRealCount > 0) {
            $realTeamCount = $existingRealCount;
        } else {
            if ($this->realTeamCountParam === null) {
                return StepResult::failure(
                    $this->getLabel(),
                    'First upload requires real_team_count parameter'
                        . ' (e.g. &real_team_count=8)',
                );
            }
            $realTeamCount = $this->realTeamCountParam;
        }

        $slots = $this->fetchLeagueConfigSlots();
        if ($slots === []) {
            return StepResult::failure(
                $this->getLabel(),
                'No league config rows found for season ' . $this->seasonEndingYear,
            );
        }

        $seeded = 0;
        foreach ($slots as $slot) {
            $teamid = $slot['team_slot'];
            $teamName = $slot['team_name'];
            $isReal = $teamid <= $realTeamCount ? 1 : 0;

            $stmt = $this->db->prepare(
                'INSERT IGNORE INTO `ibl_olympics_team_info`
                    (`teamid`, `team_name`, `is_real_team`)
                VALUES (?, ?, ?)'
            );
            if ($stmt === false) {
                return StepResult::failure($this->getLabel(), 'Prepare failed: ' . $this->db->error);
            }
            $stmt->bind_param('isi', $teamid, $teamName, $isReal);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $seeded++;
            }
            $stmt->close();
        }

        $detail = sprintf(
            'Seeded %d team_info rows (%d real, %d placeholder)',
            $seeded,
            min($realTeamCount, count($slots)),
            max(0, count($slots) - $realTeamCount),
        );

        return StepResult::success($this->getLabel(), $detail);
    }

    private function countRealTeams(): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS cnt FROM `ibl_olympics_team_info` WHERE `is_real_team` = 1'
        );
        if ($stmt === false) {
            return 0;
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            $stmt->close();
            return 0;
        }
        /** @var array{cnt: int}|false|null $row */
        $row = $result->fetch_assoc();
        $result->free();
        $stmt->close();
        return $row !== null && $row !== false ? (int) $row['cnt'] : 0;
    }

    /**
     * @return list<array{team_slot: int, team_name: string}>
     */
    private function fetchLeagueConfigSlots(): array
    {
        $stmt = $this->db->prepare(
            'SELECT `team_slot`, `team_name`
             FROM `ibl_olympics_league_config`
             WHERE `season_ending_year` = ?
             ORDER BY `team_slot` ASC'
        );
        if ($stmt === false) {
            return [];
        }
        $year = $this->seasonEndingYear;
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            $stmt->close();
            return [];
        }

        /** @var list<array{team_slot: int, team_name: string}> $rows */
        $rows = [];
        while (($row = $result->fetch_assoc()) !== null && $row !== false) {
            /** @var array{team_slot: int, team_name: string} $row */
            $rows[] = $row;
        }
        $result->free();
        $stmt->close();
        return $rows;
    }
}
