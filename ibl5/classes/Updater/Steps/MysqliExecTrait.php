<?php

declare(strict_types=1);

namespace Updater\Steps;

/**
 * Shared zero-parameter DML helper for pipeline steps that hold a raw
 * `\mysqli $db` and manage their own transactions (so they cannot extend
 * BaseMysqliRepository).
 */
trait MysqliExecTrait
{
    /** Prepare and execute a zero-parameter DML statement; returns affected rows. */
    private function dbExec(string $sql): int
    {
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new \RuntimeException('Prepare failed: ' . $this->db->error);
        }
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new \RuntimeException('Execute failed: ' . $err);
        }
        $affected = (int) $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }
}
