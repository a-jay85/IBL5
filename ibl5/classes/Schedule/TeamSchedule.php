<?php

declare(strict_types=1);

namespace Schedule;

use Schedule\Contracts\TeamScheduleInterface;

/**
 * @see TeamScheduleInterface
 */
class TeamSchedule extends Schedule implements TeamScheduleInterface
{
    /**
     * @see TeamScheduleInterface::getSchedule()
     *
     * @phpstan-param \mysqli $db
     * @phpstan-return \mysqli_result<int, array<string, mixed>>
     */
    public static function getSchedule(object $db, int $teamID): object
    {
        /** @var \mysqli $db */
        $stmt = $db->prepare(
            "SELECT * FROM `ibl_schedule` WHERE Visitor = ? OR Home = ? ORDER BY Date ASC"
        );
        if ($stmt === false) {
            throw new \Exception('Prepare failed: ' . $db->error);
        }

        $stmt->bind_param('ii', $teamID, $teamID);
        if (!$stmt->execute()) {
            throw new \Exception('Execute failed: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        if ($result === false) {
            throw new \Exception('Failed to get result: ' . $stmt->error);
        }
        $stmt->close();
        return $result;
    }

    /**
     * @see TeamScheduleInterface::getProjectedGamesNextSimResult()
     *
     * @phpstan-param \mysqli $db
     * @phpstan-return \mysqli_result<int, array<string, mixed>>
     */
    public static function getProjectedGamesNextSimResult(object $db, int $teamID, string $lastSimEndDate): object
    {
        /** @var \mysqli $db */
        $league = new \League($db);
        $simLengthInDays = $league->getSimLengthInDays();

        $stmt = $db->prepare(
            "SELECT * FROM `ibl_schedule`
             WHERE (Visitor = ? OR Home = ?)
               AND Date BETWEEN ADDDATE(?, 1) AND ADDDATE(?, ?)
             ORDER BY Date ASC"
        );
        if ($stmt === false) {
            throw new \Exception('Prepare failed: ' . $db->error);
        }

        $stmt->bind_param('iissi', $teamID, $teamID, $lastSimEndDate, $lastSimEndDate, $simLengthInDays);
        if (!$stmt->execute()) {
            throw new \Exception('Execute failed: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        if ($result === false) {
            throw new \Exception('Failed to get result: ' . $stmt->error);
        }
        $stmt->close();
        return $result;
    }
}