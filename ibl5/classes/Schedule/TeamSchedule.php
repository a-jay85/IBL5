<?php

declare(strict_types=1);

namespace Schedule;

use Schedule\Contracts\TeamScheduleInterface;

/**
 * @see TeamScheduleInterface
 */
class TeamSchedule extends Schedule implements TeamScheduleInterface
{
    private object $db;

    public function __construct(object $db)
    {
        $this->db = $db;
    }

    /**
     * @see TeamScheduleInterface::getSchedule()
     */
    public static function getSchedule($db, int $teamID, string $scheduleTable = 'ibl_schedule')
    {
        $stmt = $db->prepare(
            "SELECT * FROM `{$scheduleTable}` WHERE Visitor = ? OR Home = ? ORDER BY Date ASC"
        );
        if ($stmt === false) {
            throw new \Exception('Prepare failed: ' . $db->error);
        }
        
        $stmt->bind_param('ii', $teamID, $teamID);
        if (!$stmt->execute()) {
            throw new \Exception('Execute failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $stmt->close();
        return $result;
    }

    /**
     * @see TeamScheduleInterface::getProjectedGamesNextSimResult()
     */
    public static function getProjectedGamesNextSimResult($db, int $teamID, string $lastSimEndDate, string $scheduleTable = 'ibl_schedule')
    {
        $league = new \League($db);
        $simLengthInDays = $league->getSimLengthInDays();
        
        $stmt = $db->prepare(
            "SELECT * FROM `{$scheduleTable}` 
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
        $stmt->close();
        return $result;
    }
}