<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Base class for database integration tests against real MariaDB.
 *
 * - Connects via env vars: DB_HOST, DB_USER, DB_PASS, DB_NAME
 * - Sets MYSQLI_OPT_INT_AND_FLOAT_NATIVE to match production
 * - Wraps each test in begin_transaction() / rollback() for isolation
 * - Excluded from default PHPUnit suite via #[Group('database')]
 */
#[Group('database')]
abstract class DatabaseTestCase extends TestCase
{
    protected \mysqli $db;

    protected function setUp(): void
    {
        parent::setUp();

        $host = $this->requireEnv('DB_HOST');
        $user = $this->requireEnv('DB_USER');
        $pass = $this->requireEnv('DB_PASS');
        $name = $this->requireEnv('DB_NAME');

        $this->db = new \mysqli();
        $this->db->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
        $this->db->real_connect($host, $user, $pass, $name);

        if ($this->db->connect_errno !== 0) {
            $this->fail('Database connection failed: ' . $this->db->connect_error);
        }

        $this->db->begin_transaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->ping()) {
            $this->db->rollback();
            $this->db->close();
        }

        parent::tearDown();
    }

    /**
     * Insert a row into a table and return the last insert ID (0 if no auto-increment).
     *
     * @param array<string, int|float|string|null> $data Column => value pairs
     */
    protected function insertRow(string $table, array $data): int
    {
        $columns = implode(', ', array_map(static fn (string $col): string => "`$col`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $types = '';
        $values = [];
        foreach ($data as $value) {
            if (is_int($value)) {
                $types .= 'i';
                $values[] = $value;
            } elseif (is_float($value)) {
                $types .= 'd';
                $values[] = $value;
            } elseif (is_string($value)) {
                $types .= 's';
                $values[] = $value;
            } else {
                // null — bind as string with empty value (caller should use sentinel values)
                $types .= 's';
                $values[] = '';
            }
        }

        $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        self::assertNotFalse($stmt, "Failed to prepare INSERT into $table: " . $this->db->error);

        if ($types !== '') {
            $stmt->bind_param($types, ...$values);
        }

        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();

        return $id;
    }

    protected function insertTeamBoxscoreRow(string $date, string $name, int $gameOfDay, int $visitorTid, int $homeTid): int
    {
        return $this->insertRow('ibl_box_scores_teams', [
            'Date' => $date,
            'name' => $name,
            'gameOfThatDay' => $gameOfDay,
            'visitorTeamID' => $visitorTid,
            'homeTeamID' => $homeTid,
            'attendance' => 10000,
            'capacity' => 15000,
            'visitorWins' => 20,
            'visitorLosses' => 10,
            'homeWins' => 25,
            'homeLosses' => 5,
            'visitorQ1points' => 20,
            'visitorQ2points' => 22,
            'visitorQ3points' => 18,
            'visitorQ4points' => 25,
            'visitorOTpoints' => 0,
            'homeQ1points' => 28,
            'homeQ2points' => 24,
            'homeQ3points' => 22,
            'homeQ4points' => 30,
            'homeOTpoints' => 0,
            'game2GM' => 30,
            'game2GA' => 60,
            'gameFTM' => 15,
            'gameFTA' => 20,
            'game3GM' => 8,
            'game3GA' => 22,
            'gameORB' => 10,
            'gameDRB' => 30,
            'gameAST' => 20,
            'gameSTL' => 8,
            'gameTOV' => 12,
            'gameBLK' => 5,
            'gamePF' => 18,
        ]);
    }

    private function requireEnv(string $name): string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            self::fail("Environment variable $name is not set — database integration tests require a real MariaDB connection.");
        }
        return $value;
    }
}
